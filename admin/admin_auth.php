<?php
require_once __DIR__ . '/admin_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_is_logged_in(): bool {
    return !empty($_SESSION['admin']) && is_array($_SESSION['admin']) && !empty($_SESSION['admin']['id']);
}

function admin_require_login(): void {
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_login_attempt(string $email, string $password): bool {
    $email = trim($email);
    $password = trim($password);

    if (empty($email) || empty($password)) {
        return false;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, password, role, status FROM users WHERE email = ? AND role = ? LIMIT 1');
    $stmt->execute([$email, 'admin']);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password']) && $user['status'] === 'active') {
        $username = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['admin'] = [
            'id' => $user['user_id'],
            'email' => $user['email'],
            'name' => $username,
            'role' => $user['role'],
            'logged_in_at' => date('Y-m-d H:i:s'),
        ];
        session_regenerate_id(true);
        return true;
    }

    return false;
}

function admin_logout(): void {
    unset($_SESSION['admin']);
    session_destroy();
}
