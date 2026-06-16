<?php
require_once 'includes/bootstrap.php';

try {
    $pdo = get_db_connection();
    echo "<h1>Fixing assessment_sessions table</h1>";
    
    // Make post_id nullable
    $sql = "ALTER TABLE assessment_sessions MODIFY COLUMN post_id INT NULL";
    $pdo->exec($sql);
    echo "<p>✅ Made post_id nullable</p>";
    
    // Also add the foreign key back (we might have dropped it before)
    $sql = "ALTER TABLE assessment_sessions 
            ADD CONSTRAINT assessment_sessions_ibfk_2 
            FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL";
    try {
        $pdo->exec($sql);
        echo "<p>✅ Added foreign key back</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Foreign key might already exist: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>Done!</h2>";
    echo "<p><a href='employer_dashboard.php'>Go back to employer dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?>