<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';

echo "<h1>Setting Up Test Data</h1>";

try {
    $pdo = get_db_connection();
    echo "<p>✅ Connected to database</p>";

    // 1. Ensure employer_applications table exists
    echo "<h2>Checking employer_applications table...</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'employer_applications'");
    if (!$stmt->fetch()) {
        echo "<p>Creating employer_applications table...</p>";
        $pdo->exec("
            CREATE TABLE employer_applications (
                application_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                company_email VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                website VARCHAR(255),
                message TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p>✅ employer_applications table created</p>";
    } else {
        echo "<p>✅ employer_applications table already exists</p>";
    }

    // 2. Insert test users if not present
    echo "<h2>Checking test users...</h2>";
    $testUsers = [
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'user'
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'employer'
        ],
        [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin'
        ]
    ];

    foreach ($testUsers as $u) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$u['email']]);
        if (!$stmt->fetch()) {
            $hashedPassword = password_hash($u['password'], PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, role, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmtInsert->execute([
                $u['first_name'],
                $u['last_name'],
                $u['email'],
                $hashedPassword,
                $u['role']
            ]);
            $userId = $pdo->lastInsertId();
            
            // Also insert profile
            $stmtProfile = $pdo->prepare("
                INSERT INTO user_profiles (user_id) VALUES (?)
            ");
            $stmtProfile->execute([$userId]);
            
            echo "<p>✅ Inserted user: {$u['first_name']} {$u['last_name']} ({$u['email']})</p>";
        } else {
            echo "<p>✅ User already exists: {$u['email']}</p>";
        }
    }

    echo "<h2>Setup Complete!</h2>";
    echo "<p>Test users:</p>";
    echo "<ul>";
    echo "<li>Applicant: john@example.com / password123</li>";
    echo "<li>Employer: jane@example.com / password123</li>";
    echo "<li>Admin: admin@example.com / password</li>";
    echo "</ul>";
    echo "<p><a href='index.php'>Go to home</a></p>";
    echo "<p><a href='test_db.php'>Check database</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>