-- ============================================================
-- CyberSphere: Seed extra accounts
-- These accounts map to the hardcoded posts/messages in the app.
-- Run via:  mysql -u root -p cybersphere_db < seed_extra_users.sql
-- OR use seed_extra_users.php (auto-hashes passwords at runtime)
-- ============================================================

-- NOTE: Replace the password hashes below with real bcrypt hashes.
-- Use seed_extra_users.php instead if you cannot pre-generate hashes.
-- password_hash('NetSentinel@2026', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, email, password, role, status, bio, location, work, website)
VALUES (
    'NetSentinel Solutions',
    'hr@netsentinel.com',
    'REPLACE_WITH_BCRYPT_HASH',
    'user', 'active',
    'Leading cybersecurity firm specializing in penetration testing, SOC operations, and enterprise threat intelligence. We are actively hiring!',
    'Manila, Philippines',
    'Cybersecurity Services',
    'https://netsentinel.example.com'
);

-- password_hash('MarcusVane@2026', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, email, password, role, status, bio, location, work, education)
VALUES (
    'Marcus Vane',
    'marcus.vane@example.com',
    'REPLACE_WITH_BCRYPT_HASH',
    'user', 'active',
    'CISSP Certified Threat Hunter with 8+ years in enterprise defense. Seeking DFIR roles. Open to hybrid roles in London. Skilled in Malware Analysis and Splunk.',
    'London, UK',
    'Threat Intelligence Consultant',
    'BS Computer Science - University of London'
);

-- password_hash('SecureBank@2026', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, email, password, role, status, bio, location, work)
VALUES (
    'SecureBank Hiring',
    'securebank@example.com',
    'REPLACE_WITH_BCRYPT_HASH',
    'user', 'active',
    'SecureBank is a leading financial institution actively hiring cybersecurity professionals across GRC, SOC, and DevSecOps roles.',
    'Makati, Philippines',
    'Financial Technology & Banking'
);
