<?php
/**
 * App bootstrap (session + shared helpers + DB)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

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

