<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'includes/bootstrap.php';

echo "<h1>Test Message Flow</h1>";

// Check if logged in
if (!isset($_SESSION['user'])) {
    echo "<p>Please <a href='login.php'>log in</a> first.</p>";
    exit;
}

echo "<h2>Logged in as:</h2>";
echo "<pre>" . print_r($_SESSION['user'], true) . "</pre>";

// Get a list of all active users to test with
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email FROM users WHERE status = 'active' AND user_id != ? LIMIT 5");
    $stmt->execute([$_SESSION['user']['user_id']]);
    $otherUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Other Active Users:</h2>";
    if (empty($otherUsers)) {
        echo "<p>No other active users. Please create another account first.</p>";
    } else {
        foreach ($otherUsers as $u) {
            $testUrl = "messages.php?user=" . urlencode($u['user_id']);
            echo "<p>User: {$u['first_name']} {$u['last_name']} (ID: {$u['user_id']}) → <a href='{$testUrl}'>Test Message Link</a></p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error getting users: " . $e->getMessage() . "</p>";
}
?>