<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';

// Let's test with user_id 5 (Marck Mabalot)
$testApp = [
    'user_id' => 5,
    'company_name' => 'Test Company',
    'industry' => 'Cybersecurity',
    'company_size' => '1-10',
    'website' => 'https://example.com',
    'description' => 'Test description that is long enough',
    'contact_name' => 'Test Contact',
    'contact_phone' => '1234567890',
    'logo_url' => 'uploads/test-logo.jpg',
    'documents' => []
];

echo "Testing cs_save_employer_application...\n";
echo "Test app data: ";
print_r($testApp);

$savedApp = cs_save_employer_application($testApp);
echo "\nSaved app returned: ";
print_r($savedApp);

// Now let's check directly from the database
$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT * FROM employer_applications WHERE user_id = 5 ORDER BY eapp_id DESC LIMIT 1');
$stmt->execute();
$directDb = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nDirect DB result: ";
print_r($directDb);
?>