<?php
/**
 * App bootstrap (session + shared helpers + DB)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ── Database migrations ──
function ensure_tables_exist() {
    try {
        $pdo = get_db_connection();
        
        // Check/create employer_applications table
        $stmt = $pdo->query("SHOW TABLES LIKE 'employer_applications'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE TABLE employer_applications (
                eapp_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                industry VARCHAR(100),
                company_size VARCHAR(50),
                website VARCHAR(255),
                description TEXT,
                contact_name VARCHAR(255),
                contact_phone VARCHAR(50),
                logo_url VARCHAR(255),
                status VARCHAR(50) DEFAULT 'pending',
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            // Ensure all columns exist
            $columns = ['logo_url'];
            foreach ($columns as $col) {
                $checkCol = $pdo->query("SHOW COLUMNS FROM employer_applications LIKE '$col'");
                if (!$checkCol->fetch()) {
                    $pdo->exec("ALTER TABLE employer_applications ADD COLUMN $col VARCHAR(255) NULL");
                }
            }
        }

        // Ensure user_profiles has all columns
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_profiles'");
        if ($stmt->fetch()) {
            $upColumns = ['profile_pic', 'cover_pic', 'bio', 'work', 'location', 'education', 'address', 'website', 'phone'];
            foreach ($upColumns as $col) {
                $checkCol = $pdo->query("SHOW COLUMNS FROM user_profiles LIKE '$col'");
                if (!$checkCol->fetch()) {
                    if (in_array($col, ['bio'])) {
                        $pdo->exec("ALTER TABLE user_profiles ADD COLUMN $col TEXT NULL");
                    } else {
                        $pdo->exec("ALTER TABLE user_profiles ADD COLUMN $col VARCHAR(255) NULL");
                    }
                }
            }
        }
        
        // Check/create employer_application_documents table (case-insensitive check)
        $tableExists = false;
        $stmt = $pdo->query("SHOW TABLES LIKE 'employer_application_documents'");
        if ($stmt->fetch()) {
            $tableExists = true;
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE 'EMPLOYER_APPLICATION_DOCUMENTS'");
            if ($stmt->fetch()) {
                $tableExists = true;
                // Rename to lowercase for consistency
                $pdo->exec("RENAME TABLE EMPLOYER_APPLICATION_DOCUMENTS TO employer_application_documents");
            }
        }
        
        if (!$tableExists) {
            $pdo->exec("CREATE TABLE employer_application_documents (
                doc_id INT AUTO_INCREMENT PRIMARY KEY,
                eapp_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_url VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (eapp_id) REFERENCES employer_applications(eapp_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Check if conversations table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
        if (!$stmt->fetch()) {
            // Create conversations table
            $pdo->exec("CREATE TABLE conversations (
                conversation_id INT AUTO_INCREMENT PRIMARY KEY,
                user_a INT NOT NULL,
                user_b INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_a) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (user_b) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Check if messages table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE TABLE messages (
                message_id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                sender_id INT NOT NULL,
                body TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            // Check if is_read column exists, add if not
            $checkCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
            if (!$checkCol->fetch()) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
            }
            // Check if sent_at column exists, add if not
            $checkCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'sent_at'");
            if (!$checkCol->fetch()) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }
        }
        
        // Check if message_attachments table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'message_attachments'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE TABLE message_attachments (
                attachment_id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_url VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Check assessment_sessions table - fix post_id to allow NULL
        $stmt = $pdo->query("SHOW TABLES LIKE 'assessment_sessions'");
        if ($stmt->fetch()) {
            try {
                // Drop any foreign key constraint on post_id first
                $result = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'assessment_sessions' AND COLUMN_NAME = 'post_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
                $fkRow = $result->fetch(PDO::FETCH_ASSOC);
                if ($fkRow) {
                    $fkName = $fkRow['CONSTRAINT_NAME'];
                    $pdo->exec("ALTER TABLE assessment_sessions DROP FOREIGN KEY `$fkName`");
                }
                // Make post_id nullable
                $pdo->exec("ALTER TABLE assessment_sessions MODIFY COLUMN post_id INT NULL");
                // Re-add foreign key with ON DELETE SET NULL
                $pdo->exec("ALTER TABLE assessment_sessions ADD CONSTRAINT fk_assessment_sessions_post_id FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL");
            } catch (Exception $e) {
                // Ignore errors
            }
        }

        // Fix order_items.product_id and seed products
        try {
            // Make order_items.product_id nullable
            $result = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            $fkRow = $result->fetch(PDO::FETCH_ASSOC);
            if ($fkRow) {
                $fkName = $fkRow['CONSTRAINT_NAME'];
                $pdo->exec("ALTER TABLE order_items DROP FOREIGN KEY `$fkName`");
            }
            $pdo->exec("ALTER TABLE order_items MODIFY COLUMN product_id INT NULL");
            // Re-add foreign key with ON DELETE SET NULL
            $pdo->exec("ALTER TABLE order_items ADD CONSTRAINT fk_order_items_product_id FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL");

            // Seed product categories and products
            $categories = [
                'Courses',
                'Books',
                'Resources'
            ];
            $categoryMap = [];
            foreach ($categories as $catName) {
                $stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE name = ?");
                $stmt->execute([$catName]);
                $existing = $stmt->fetchColumn();
                if (!$existing) {
                    $stmt = $pdo->prepare("INSERT INTO product_categories (name) VALUES (?)");
                    $stmt->execute([$catName]);
                    $categoryMap[$catName] = $pdo->lastInsertId();
                } else {
                    $categoryMap[$catName] = $existing;
                }
            }

            // Define product data directly here (no include)
            $productData = [
                [
                    'id' => 'pci-dss-360',
                    'name' => 'PCI-DSS 360°',
                    'category' => 'Courses',
                    'description' => 'Master the requirements, implementation, and assessment of PCI-DSS for payment card security.',
                    'price' => 12999,
                    'image' => 'https://plus.unsplash.com/premium_photo-1664303693721-e3c0f2757f64?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                    'badge' => 'TOP'
                ],
                [
                    'id' => 'soc-analyst',
                    'name' => 'SOC Analyst Pro',
                    'category' => 'Courses',
                    'description' => 'Step into a Security Operations Center and learn incident response, threat hunting, and SIEM management.',
                    'price' => 9500,
                    'image' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=1934&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                    'badge' => 'NEW'
                ],
                [
                    'id' => 'iso-27001-lead',
                    'name' => 'ISO 27001 Lead Auditor',
                    'category' => 'Courses',
                    'description' => 'Become a certified lead auditor for Information Security Management Systems.',
                    'price' => 14999,
                    'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=2015&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ],
                [
                    'id' => 'cyber-forensics',
                    'name' => 'Cyber Forensics Fundamentals',
                    'category' => 'Courses',
                    'description' => 'Learn digital investigation techniques, chain of custody, and forensic analysis tools.',
                    'price' => 8500,
                    'image' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ],
                [
                    'id' => 'art-of-invisibility',
                    'name' => 'The Art of Invisibility',
                    'category' => 'Books',
                    'description' => 'Your guide to privacy and anonymity on the internet.',
                    'price' => 1250,
                    'image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?q=80&w=1974&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ],
                [
                    'id' => 'security-engineering',
                    'name' => 'Security Engineering',
                    'category' => 'Books',
                    'description' => 'A comprehensive guide to building secure systems.',
                    'price' => 2100,
                    'image' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?q=80&w=1945&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ],
                [
                    'id' => 'ctf-toolkit',
                    'name' => 'CTF Toolkit Pro',
                    'category' => 'Resources',
                    'description' => 'Over 50 custom scripts, wordlists, and payloads for Capture The Flag competitions.',
                    'price' => 4500,
                    'image' => 'https://images.unsplash.com/photo-1550751659-4b76e7099f6b?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                    'badge' => 'HOT'
                ],
                [
                    'id' => 'policy-templates',
                    'name' => 'Enterprise Policy Pack',
                    'category' => 'Resources',
                    'description' => 'Ready-to-use security policy templates for ISO 27001, HIPAA, and GDPR compliance.',
                    'price' => 6500,
                    'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ],
                [
                    'id' => 'red-team-pro',
                    'name' => 'Red Team Operations',
                    'category' => 'Courses',
                    'description' => 'Learn advanced adversarial tactics, techniques, and procedures.',
                    'price' => 16999,
                    'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ],
                [
                    'id' => 'bug-bounty-playbook',
                    'name' => 'Bug Bounty Playbook',
                    'category' => 'Books',
                    'description' => 'Strategies and techniques for finding security vulnerabilities.',
                    'price' => 1500,
                    'image' => 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ]
            ];

            // Seed products
            foreach ($productData as $product) {
                $stmt = $pdo->prepare("SELECT product_id FROM products WHERE product_id = ?");
                $stmt->execute([$product['id']]);
                $exists = $stmt->fetchColumn();

                $catId = $categoryMap[$product['category']] ?? 1;
                if (!$exists) {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (product_id, category_id, name, description, price, image_url, badge, stock, discount_pct)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 100, 0.00)
                    ");
                    $stmt->execute([
                        $product['id'],
                        $catId,
                        $product['name'],
                        $product['description'],
                        $product['price'],
                        $product['image'],
                        $product['badge'] ?? null
                    ]);
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }
    } catch (Exception $e) {
        // Silently ignore table errors in production
    }
}
ensure_tables_exist();

// ── String helpers (works even without mbstring) ──
function cs_strlen(string $s): int {
    return function_exists('mb_strlen') ? (int)mb_strlen($s, 'UTF-8') : strlen($s);
}

function cs_substr(string $s, int $start, ?int $length = null): string {
    if (function_exists('mb_substr')) {
        return $length === null ? (string)mb_substr($s, $start) : (string)mb_substr($s, $start, $length);
    }
    return $length === null ? substr($s, $start) : substr($s, $start, $length);
}

function cs_ensure_session_array(string $key): void {
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
}

// Common session-backed stores (for backward compatibility)
cs_ensure_session_array('posts');
cs_ensure_session_array('reactions');
cs_ensure_session_array('my_reactions');
cs_ensure_session_array('comments');
cs_ensure_session_array('shares');
cs_ensure_session_array('notifications');
cs_ensure_session_array('applications');
cs_ensure_session_array('saved_jobs');
cs_ensure_session_array('my_applications');
cs_ensure_session_array('cart');

// Helper function to save a notification to the database
function cs_save_notification($user_id, $message, $link = null) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $message, $link]);
        $insertId = $pdo->lastInsertId();

        // Also update the session for immediate display
        if (isset($_SESSION['user']) && $_SESSION['user']['user_id'] == $user_id) {
            $notif = [
                'notification_id' => $insertId,
                'msg' => $message,
                'link' => $link,
                'read' => false,
                'time' => date('M j, Y g:i A')
            ];
            array_unshift($_SESSION['notifications'], $notif);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Load user and profile from database if logged in
if (isset($_SESSION['user']) && isset($_SESSION['user']['user_id'])) {
    try {
        $pdo = get_db_connection();
        // Load user data
        $stmtUser = $pdo->prepare('SELECT user_id, first_name, last_name, email, role, status, created_at FROM users WHERE user_id = ?');
        $stmtUser->execute([$_SESSION['user']['user_id']]);
        $userFromDb = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($userFromDb) {
            // Build username from first and last name
            $username = trim($userFromDb['first_name'] . ' ' . $userFromDb['last_name']);
            $userFromDb['username'] = $username;
            
            $_SESSION['user'] = array_merge($_SESSION['user'], $userFromDb);
            
            // Load profile data
            $stmtProfile = $pdo->prepare('SELECT profile_pic, cover_pic, bio, location, website, phone, updated_at FROM user_profiles WHERE user_id = ?');
            $stmtProfile->execute([$userFromDb['user_id']]);
            $profileFromDb = $stmtProfile->fetch(PDO::FETCH_ASSOC);
            
            if ($profileFromDb) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $profileFromDb);
            }
        }
        
        // Also check employer_verified as fallback if role is empty
        if (empty($_SESSION['user']['role']) && !empty($_SESSION['user']['employer_verified'])) {
            $_SESSION['user']['role'] = 'employer';
            // Also update database to fix it permanently
            $stmtFixRole = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
            $stmtFixRole->execute(['employer', $_SESSION['user']['user_id']]);
        }
    } catch (Exception $e) {
        // Fall back to existing session data if DB fails
    }
}

// Load notifications from database into session if user is logged in
if (isset($_SESSION['user'])) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT notification_id, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$_SESSION['user']['user_id']]);
        $notifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = [
                'notification_id' => $row['notification_id'],
                'msg' => $row['message'],
                'link' => $row['link'],
                'read' => (bool)$row['is_read'],
                'time' => date('M j, Y g:i A', strtotime($row['created_at']))
            ];
        }
        $_SESSION['notifications'] = $notifications;
    } catch (Exception $e) {
        // Fall back to session if DB fails
    }

    // Load saved jobs from database into session
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT post_id FROM saved_jobs WHERE user_id = ?');
        $stmt->execute([$_SESSION['user']['user_id']]);
        $savedJobs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $savedJobs[] = (string)$row['post_id'];
        }
        $_SESSION['saved_jobs'] = $savedJobs;
    } catch (Exception $e) {
        // Fall back to existing session data if DB fails
    }
}

