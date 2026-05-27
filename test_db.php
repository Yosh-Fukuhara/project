<?php
require_once 'config/database.php';

try {
    $pdo = get_db_connection();
    echo '<h1 style="color: green; text-align: center; margin-top: 50px;">✅ Database Connection Successful!</h1>';
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $userCount = $stmt->fetchColumn();
    echo '<p style="text-align: center; font-size: 20px;">Total Users: ' . $userCount . '</p>';
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM posts');
    $postCount = $stmt->fetchColumn();
    echo '<p style="text-align: center; font-size: 20px;">Total Posts: ' . $postCount . '</p>';
    
    echo '<p style="text-align: center; margin-top: 30px;"><a href="admin/login.php" style="font-size: 20px;">Go to Admin Panel</a></p>';
    
} catch (Exception $e) {
    echo '<h1 style="color: red; text-align: center; margin-top: 50px;">❌ Database Connection Failed</h1>';
    echo '<p style="text-align: center;">Error: ' . $e->getMessage() . '</p>';
}
