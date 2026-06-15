<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

echo '<h1>Delete Test Conversations</h1>';

try {
    $pdo = get_db_connection();
    $my_id = $_SESSION['user']['user_id'];

    // Get all conversations for the current user with other user info
    $stmt = $pdo->prepare('
        SELECT 
            c.conversation_id,
            c.user_a,
            c.user_b,
            c.created_at,
            CASE 
                WHEN c.user_a = ? THEN u_b.first_name || " " || u_b.last_name
                ELSE u_a.first_name || " " || u_a.last_name
            END AS other_user_name,
            CASE 
                WHEN c.user_a = ? THEN u_b.user_id
                ELSE u_a.user_id
            END AS other_user_id
        FROM conversations c
        JOIN users u_a ON c.user_a = u_a.user_id
        JOIN users u_b ON c.user_b = u_b.user_id
        WHERE c.user_a = ? OR c.user_b = ?
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([$my_id, $my_id, $my_id, $my_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation_id'])) {
        $delete_id = (int)$_POST['delete_conversation_id'];
        
        // Verify user is part of the conversation
        $verifyStmt = $pdo->prepare('SELECT conversation_id FROM conversations WHERE conversation_id = ? AND (user_a = ? OR user_b = ?)');
        $verifyStmt->execute([$delete_id, $my_id, $my_id]);
        if ($verifyStmt->fetch()) {
            // Delete will cascade to messages and message_attachments
            $deleteStmt = $pdo->prepare('DELETE FROM conversations WHERE conversation_id = ?');
            $deleteStmt->execute([$delete_id]);
            echo '<p style="color: green;">Conversation deleted successfully!</p>';
        } else {
            echo '<p style="color: red;">You don\'t have permission to delete that conversation!</p>';
        }
    }

    if (empty($conversations)) {
        echo '<p>No conversations found!</p>';
    } else {
        echo '<ul style="list-style-type: none; padding: 0;">';
        foreach ($conversations as $conv) {
            echo '<li style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">';
            echo '<p><strong>Conversation ID:</strong> ' . htmlspecialchars($conv['conversation_id']) . '</p>';
            echo '<p><strong>With:</strong> ' . htmlspecialchars($conv['other_user_name']) . ' (User ID: ' . htmlspecialchars($conv['other_user_id']) . ')</p>';
            echo '<p><strong>Created:</strong> ' . htmlspecialchars($conv['created_at']) . '</p>';
            echo '<form method="POST">';
            echo '<input type="hidden" name="delete_conversation_id" value="' . htmlspecialchars($conv['conversation_id']) . '">';
            echo '<button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Delete Conversation</button>';
            echo '</form>';
            echo '</li>';
        }
        echo '</ul>';
    }

} catch (Exception $e) {
    echo '<p style="color: red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<p><a href="messages.php">Go back to messages</a></p>';
