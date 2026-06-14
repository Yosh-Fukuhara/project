<?php
/**
 * App bootstrap (session + shared helpers + DB)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ── Database migrations ──
try {
    $pdo = get_db_connection();
    // Add emoji column to post_likes if not exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM post_likes LIKE 'emoji'");
    if (!$checkCol->fetch()) {
        $pdo->exec("ALTER TABLE post_likes ADD COLUMN emoji VARCHAR(10) NOT NULL DEFAULT '👍' AFTER user_id");
    }
    // Add is_hiring and enable_apply to posts if not exists
    $checkHiring = $pdo->query("SHOW COLUMNS FROM posts LIKE 'is_hiring'");
    if (!$checkHiring->fetch()) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN is_hiring TINYINT(1) DEFAULT 0 AFTER attachment_mime");
    }
    $checkEnableApply = $pdo->query("SHOW COLUMNS FROM posts LIKE 'enable_apply'");
    if (!$checkEnableApply->fetch()) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN enable_apply TINYINT(1) DEFAULT 0 AFTER is_hiring");
    }
    // Delete orphaned post_likes entries (reactions for posts that no longer exist)
    $pdo->exec("DELETE pl FROM post_likes pl LEFT JOIN posts p ON pl.post_id = p.post_id WHERE p.post_id IS NULL");
} catch (Exception $e) {
    // Ignore migration errors for now
}

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

