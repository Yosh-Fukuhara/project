<?php
require_once 'config/database.php';
$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, role FROM users WHERE role = ?');
$stmt->execute(['admin']);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if ($admin) {
    echo "<h2>Admin Account Exists</h2>";
    echo "<pre>";
    print_r($admin);
    echo "</pre>";
} else {
    echo "No admin account found!";
}
