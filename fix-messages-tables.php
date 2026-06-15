<?php
require_once 'includes/bootstrap.php';

try {
    $pdo = get_db_connection();
    echo "✅ Connected to database<br><br>";

    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Create conversations table if not exists
    echo "Creating conversations table if not exists...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conversations (
            conversation_id INT AUTO_INCREMENT PRIMARY KEY,
            user_a INT NOT NULL,
            user_b INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_a) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (user_b) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ conversations table ready<br>";

    // Create messages table if not exists
    echo "Creating messages table if not exists...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            message_id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender_id INT NOT NULL,
            body TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ messages table ready<br>";

    // Create message_attachments table if not exists
    echo "Creating message_attachments table if not exists...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS message_attachments (
            attachment_id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            file_name VARCHAR(100) NOT NULL,
            file_url VARCHAR(255) NOT NULL,
            FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ message_attachments table ready<br>";

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<br><a href='communities.php'>Go to Communities</a> or <a href='messages.php'>Go to Messages</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
