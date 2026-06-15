<?php
/**
 * App bootstrap (session + shared helpers + DB)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ── Database migrations ──
// For now, we assume the database is already set up with the schema in database.sql
// No automatic migrations here, to avoid conflicts with user's manual setup

// ── String helpers (works even without mbstring) ──
function cs_strlen(string $s): int {
    return function_exists('mb_strlen') ? (int)mb_strlen($s, 'UTF-8') : strlen($s);
}

function cs_substr(string $s, int $start, ?int $length = null): string {
    if (function_exists('mb_substr')) {
        return $length === null ? (string)mb_substr($s, $start) : (string)mb_substr($s, $start, $length);
    }
    return $length === null ? substr($s, $start) : substr($s, $start, $length);
}

function cs_ensure_session_array(string $key): void {
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
}

// Common session-backed stores (for backward compatibility)
cs_ensure_session_array('posts');
cs_ensure_session_array('reactions');
cs_ensure_session_array('my_reactions');
cs_ensure_session_array('comments');
cs_ensure_session_array('shares');
cs_ensure_session_array('notifications');
cs_ensure_session_array('applications');
cs_ensure_session_array('saved_jobs');
cs_ensure_session_array('my_applications');
cs_ensure_session_array('cart');

// Helper function to save a notification to the database
function cs_save_notification($user_id, $message, $link = null) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $message, $link]);
        $insertId = $pdo->lastInsertId();

        // Also update the session for immediate display
        if (isset($_SESSION['user']) && $_SESSION['user']['user_id'] == $user_id) {
            $notif = [
                'notification_id' => $insertId,
                'msg' => $message,
                'link' => $link,
                'read' => false,
                'time' => date('M j, Y g:i A')
            ];
            array_unshift($_SESSION['notifications'], $notif);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Load user and profile from database if logged in
if (isset($_SESSION['user']) && isset($_SESSION['user']['user_id'])) {
    try {
        $pdo = get_db_connection();
        // Load user data
        $stmtUser = $pdo->prepare('SELECT user_id, first_name, last_name, email, role, status, created_at FROM users WHERE user_id = ?');
        $stmtUser->execute([$_SESSION['user']['user_id']]);
        $userFromDb = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($userFromDb) {
            // Build username from first and last name
            $username = trim($userFromDb['first_name'] . ' ' . $userFromDb['last_name']);
            $userFromDb['username'] = $username;
            
            $_SESSION['user'] = array_merge($_SESSION['user'], $userFromDb);
            
            // Load profile data
            $stmtProfile = $pdo->prepare('SELECT profile_pic, cover_pic, bio, location, website, phone, updated_at FROM user_profiles WHERE user_id = ?');
            $stmtProfile->execute([$userFromDb['user_id']]);
            $profileFromDb = $stmtProfile->fetch(PDO::FETCH_ASSOC);
            
            if ($profileFromDb) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $profileFromDb);
            }
        }
        
        // Also check employer_verified as fallback if role is empty
        if (empty($_SESSION['user']['role']) && !empty($_SESSION['user']['employer_verified'])) {
            $_SESSION['user']['role'] = 'employer';
            // Also update database to fix it permanently
            $stmtFixRole = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
            $stmtFixRole->execute(['employer', $_SESSION['user']['user_id']]);
        }
    } catch (Exception $e) {
        // Fall back to existing session data if DB fails
    }
}

// Load notifications from database into session if user is logged in
if (isset($_SESSION['user'])) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT notification_id, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$_SESSION['user']['user_id']]);
        $notifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'notification_id' => $row['notification_id'],
                'msg' => $row['message'],
                'link' => $row['link'],
                'read' => (bool)$row['is_read'],
                'time' => date('M j, Y g:i A', strtotime($row['created_at']))
            ];
        }
        $_SESSION['notifications'] = $notifications;
    } catch (Exception $e) {
        // Fall back to session if DB fails
    }
}

