<?php
require_once 'includes/bootstrap.php';

try {
    $pdo = get_db_connection();
    echo "Connected to DB successfully!<br>";

    // List tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in DB: " . implode(', ', $tables) . "<br>";

    // Check users
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<br>Users:<br>";
    foreach ($users as $u) {
        echo "ID: {$u['user_id']}, Name: {$u['first_name']} {$u['last_name']}, Email: {$u['email']}, Role: {$u['role']}<br>";
    }

    // Check conversations
    $stmt = $pdo->query("SELECT * FROM conversations");
    $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<br>Conversations:<br>";
    foreach ($convs as $c) {
        echo "Conv ID: {$c['conversation_id']}, User A: {$c['user_a']}, User B: {$c['user_b']}<br>";
    }

    // Check messages
    $stmt = $pdo->query("SELECT * FROM messages");
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<br>Messages:<br>";
    foreach ($msgs as $m) {
        echo "Msg ID: {$m['message_id']}, Conv ID: {$m['conversation_id']}, Sender: {$m['sender_id']}, Body: {$m['body']}<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Trace: " . $e->getTraceAsString();
}
?>