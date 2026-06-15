<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/bootstrap.php';

try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Existing Tables:</h2>";
    echo "<pre>" . print_r($tables, true) . "</pre>";
    
    $requiredTables = ['conversations', 'messages', 'message_attachments', 'community_post_attachments'];
    echo "<h2>Required Tables Check:</h2>";
    foreach ($requiredTables as $t) {
        $exists = in_array($t, $tables);
        echo "<p style='color: " . ($exists ? 'green' : 'red') . "'>" . ($exists ? '✓' : '✗') . " $t</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>