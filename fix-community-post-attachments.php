<?php
require_once __DIR__ . '/includes/bootstrap.php';

try {
    $pdo = get_db_connection();
    echo "✅ Connected to database<br><br>";

    echo "Creating community_post_attachments table if it doesn't exist...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS community_post_attachments (
            attachment_id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(50),
            FOREIGN KEY (post_id) REFERENCES community_posts(post_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "✅ Done! community_post_attachments table is ready.<br>";
    echo "<a href='communities.php'>Go to Communities</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
