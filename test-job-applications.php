<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';

echo "<h1>Testing job_applications Table</h1>";

try {
    $pdo = get_db_connection();
    
    // 1. Check table structure
    echo "<h3>1. Table Structure</h3>";
    $stmtShow = $pdo->prepare("DESCRIBE job_applications");
    $stmtShow->execute();
    $columns = $stmtShow->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        foreach ($col as $val) {
            echo "<td style='padding: 5px 10px;'>" . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Show existing job applications
    echo "<h3>2. Existing Job Applications</h3>";
    $stmtApps = $pdo->query("SELECT * FROM job_applications ORDER BY created_at DESC");
    $apps = $stmtApps->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($apps)) {
        echo "<p>No job applications yet. To test, apply to a job post from another account!</p>";
    } else {
        echo "<p>Found " . count($apps) . " job application(s):</p>";
        foreach ($apps as $app) {
            echo "<hr><pre>";
            print_r($app);
            echo "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr><a href='index.php'>Go back to the website</a>";
?>
