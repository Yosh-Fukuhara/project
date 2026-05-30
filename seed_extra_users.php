<?php
/**
 * seed_extra_users.php
 * -----------------------------------------------------------
 * Run this ONCE to add the three seeded accounts to the DB.
 * Their posts are handled statically in index.php and
 * view_profile.php — no DB posts are created here.
 *
 * Place in your project root, visit in browser, then DELETE.
 * -----------------------------------------------------------
 */

require_once 'config/database.php';

$users = [
    [
        'username'  => 'NetSentinel Solutions',
        'email'     => 'hr@netsentinel.com',
        'password'  => 'NetSentinel@2026',
        'bio'       => 'Leading cybersecurity firm specializing in penetration testing, SOC operations, and enterprise threat intelligence. We are actively hiring!',
        'location'  => 'Manila, Philippines',
        'work'      => 'Cybersecurity Services',
        'education' => '',
        'website'   => 'https://netsentinel.example.com',
        'phone'     => '',
    ],
    [
        'username'  => 'Marcus Vane',
        'email'     => 'marcus.vane@example.com',
        'password'  => 'MarcusVane@2026',
        'bio'       => 'CISSP Certified Threat Hunter with 8+ years in enterprise defense. Seeking DFIR roles. Open to hybrid roles in London. Skilled in Malware Analysis and Splunk.',
        'location'  => 'London, UK',
        'work'      => 'Threat Intelligence Consultant',
        'education' => 'BS Computer Science - University of London',
        'website'   => '',
        'phone'     => '',
    ],
    [
        'username'  => 'SecureBank Hiring',
        'email'     => 'securebank@example.com',
        'password'  => 'SecureBank@2026',
        'bio'       => 'SecureBank is a leading financial institution actively hiring cybersecurity professionals across GRC, SOC, and DevSecOps roles.',
        'location'  => 'Makati, Philippines',
        'work'      => 'Financial Technology & Banking',
        'education' => '',
        'website'   => '',
        'phone'     => '',
    ],
];

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password, role, status, bio, location, work, education, website, phone)
         VALUES (:username, :email, :password, :role, :status, :bio, :location, :work, :education, :website, :phone)
         ON DUPLICATE KEY UPDATE
             username  = VALUES(username),
             bio       = VALUES(bio),
             location  = VALUES(location),
             work      = VALUES(work),
             education = VALUES(education),
             website   = VALUES(website)'
    );

    $results = [];
    foreach ($users as $u) {
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            ':username'  => $u['username'],
            ':email'     => $u['email'],
            ':password'  => $hash,
            ':role'      => 'user',
            ':status'    => 'active',
            ':bio'       => $u['bio'],
            ':location'  => $u['location'],
            ':work'      => $u['work'],
            ':education' => $u['education'],
            ':website'   => $u['website'],
            ':phone'     => $u['phone'],
        ]);

        $idStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $idStmt->execute([$u['email']]);
        $userId = (int) $idStmt->fetchColumn();

        $results[] = [
            'username' => $u['username'],
            'email'    => $u['email'],
            'password' => $u['password'],
            'user_id'  => $userId,
        ];
    }

    echo '<style>body{font-family:sans-serif;max-width:680px;margin:40px auto;padding:0 20px}
          table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px;text-align:left}
          th{background:#f0f4f8}</style>';
    echo '<h2 style="color:#1e3a5f">&#x2705; Seed Complete</h2>';
    echo '<p>These accounts are now in the database. Their posts show automatically via <code>index.php</code> and <code>view_profile.php</code> — no extra DB rows needed.</p>';
    echo '<table>';
    echo '<tr><th>Username</th><th>Email</th><th>Password</th><th>User ID</th></tr>';
    foreach ($results as $r) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['username']) . '</td>';
        echo '<td>' . htmlspecialchars($r['email']) . '</td>';
        echo '<td>' . htmlspecialchars($r['password']) . '</td>';
        echo '<td>' . $r['user_id'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<p style="color:red;font-weight:bold;margin-top:20px">&#x26A0;&#xFE0F; DELETE this file from your server now!</p>';
    echo '<p><a href="index.php">&#x2190; Go to homepage</a></p>';

} catch (Exception $e) {
    echo '<h2 style="color:red">&#x274C; Error</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
