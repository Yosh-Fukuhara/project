<?php
require_once 'includes/bootstrap.php';

try {
    $pdo = get_db_connection();
    
    echo "<h1>Fixing job_applications Table</h1>";
    
    // Check if the wrong columns exist
    $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM job_applications LIKE 'app_name'");
    $stmtCheck->execute();
    $hasAppName = $stmtCheck->fetch() !== false;
    
    $stmtCheckFN = $pdo->prepare("SHOW COLUMNS FROM job_applications LIKE 'app_first_name'");
    $stmtCheckFN->execute();
    $hasFirstName = $stmtCheckFN->fetch() !== false;
    
    if ($hasAppName && !$hasFirstName) {
        echo "<p>Found old 'app_name' column, adding 'app_first_name' and 'app_last_name'...</p>";
        
        // Add new columns first
        $stmtAddFN = $pdo->prepare("ALTER TABLE job_applications ADD COLUMN app_first_name VARCHAR(50) NOT NULL AFTER post_id");
        $stmtAddFN->execute();
        
        $stmtAddLN = $pdo->prepare("ALTER TABLE job_applications ADD COLUMN app_last_name VARCHAR(50) NOT NULL AFTER app_first_name");
        $stmtAddLN->execute();
        
        // If there are existing rows, split app_name into first/last name
        $stmtCheckRows = $pdo->query("SELECT COUNT(*) FROM job_applications");
        $rowCount = $stmtCheckRows->fetchColumn();
        if ($rowCount > 0) {
            echo "<p>Migrating existing " . $rowCount . " application(s)...";
            $rows = $pdo->query("SELECT application_id, app_name FROM job_applications")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $parts = explode(' ', $row['app_name'], 2);
                $firstName = $parts[0] ?? 'Unknown';
                $lastName = $parts[1] ?? '';
                $stmtUpdate = $pdo->prepare("UPDATE job_applications SET app_first_name = ?, app_last_name = ? WHERE application_id = ?");
                $stmtUpdate->execute([$firstName, $lastName, $row['application_id']]);
            }
            echo " done!</p>";
        }
        
        // Drop the old app_name column
        $stmtDrop = $pdo->prepare("ALTER TABLE job_applications DROP COLUMN app_name");
        $stmtDrop->execute();
        
        // Also check and fix other column mismatches
        echo "<p>Checking other columns...</p>";
        
        // Check app_email length
        $stmtCheckEmail = $pdo->prepare("SHOW COLUMNS FROM job_applications LIKE 'app_email'");
        $stmtCheckEmail->execute();
        $emailCol = $stmtCheckEmail->fetch(PDO::FETCH_ASSOC);
        if ($emailCol && strpos($emailCol['Type'], '191') !== false) {
            $stmtAlterEmail = $pdo->prepare("ALTER TABLE job_applications MODIFY COLUMN app_email VARCHAR(100) NOT NULL");
            $stmtAlterEmail->execute();
            echo "<p>Fixed app_email length from 191 to 100.</p>";
        }
        
        // Check resume_path - make it NOT NULL (matches schema)
        $stmtCheckResume = $pdo->prepare("SHOW COLUMNS FROM job_applications LIKE 'resume_path'");
        $stmtCheckResume->execute();
        $resumeCol = $stmtCheckResume->fetch(PDO::FETCH_ASSOC);
        if ($resumeCol && $resumeCol['Null'] === 'YES') {
            $stmtAlterResume = $pdo->prepare("ALTER TABLE job_applications MODIFY COLUMN resume_path VARCHAR(255) NOT NULL");
            $stmtAlterResume->execute();
            echo "<p>Fixed resume_path to NOT NULL.</p>";
        }
        
        echo "<p style='color: green; font-weight: bold;'>✅ Table fixed successfully!</p>";
    } elseif ($hasFirstName) {
        echo "<p style='color: blue; font-weight: bold;'>✅ Table already has the correct columns!</p>";
    }
    
    // Show final structure
    echo "<h3>Final Table Structure</h3>";
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
    
    echo "<hr><a href='index.php'>Go back to the website</a>";
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
