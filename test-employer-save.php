<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';

echo "<h1>Testing Employer Application Save</h1>";

// Test 1: Check if tables exist
echo "<h2>1. Checking Tables...</h2>";
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'employer_applications'");
    if ($stmt->fetch()) {
        echo "<p style='color:green'>✅ employer_applications table exists</p>";
    } else {
        echo "<p style='color:red'>❌ employer_applications table missing</p>";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'employer_application_documents'");
    if ($stmt->fetch()) {
        echo "<p style='color:green'>✅ employer_application_documents table exists</p>";
    } else {
        echo "<p style='color:red'>❌ employer_application_documents table missing</p>";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'EMPLOYER_APPLICATION_DOCUMENTS'");
    if ($stmt->fetch()) {
        echo "<p style='color:orange'>⚠️ EMPLOYER_APPLICATION_DOCUMENTS (uppercase) exists - might be a case issue</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error checking tables: " . $e->getMessage() . "</p>";
}

// Test 2: Check if we have a user in session
echo "<h2>2. Checking Session User...</h2>";
if (isset($_SESSION['user'])) {
    echo "<pre>";
    print_r($_SESSION['user']);
    echo "</pre>";
} else {
    echo "<p style='color:orange'>⚠️ No user in session - please log in first at <a href='login.php'>login.php</a></p>";
}

// Test 3: Try to save a test application
echo "<h2>3. Testing Save Function...</h2>";
if (isset($_SESSION['user']) && isset($_SESSION['user']['user_id'])) {
    $testApp = [
        'company_name' => 'Test Corp ' . time(),
        'industry' => 'Technology',
        'company_size' => '11-50',
        'website' => 'https://test.com',
        'description' => 'Test company description for debugging',
        'contact_name' => 'Test Contact',
        'contact_phone' => '1234567890',
        'logo_url' => null,
        'email' => $_SESSION['user']['email'],
        'username' => $_SESSION['user']['username'] ?? 'Test User',
        'documents' => [],
        'status' => 'pending',
        'submitted_at' => date('M j, Y g:i A')
    ];
    
    echo "<h3>Test Application Data:</h3><pre>";
    print_r($testApp);
    echo "</pre>";
    
    try {
        $saved = cs_save_employer_application($testApp);
        if ($saved) {
            echo "<p style='color:green'>✅ Save successful! Returned app data:</p>";
            echo "<pre>";
            print_r($saved);
            echo "</pre>";
            
            // Verify it's in the database
            echo "<h3>Verifying in Database...</h3>";
            $stmt = $pdo->prepare("SELECT * FROM employer_applications WHERE eapp_id = ?");
            $stmt->execute([$saved['id']]);
            $dbApp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbApp) {
                echo "<p style='color:green'>✅ Found in database:</p>";
                echo "<pre>";
                print_r($dbApp);
                echo "</pre>";
            } else {
                echo "<p style='color:red'>❌ NOT found in database!</p>";
            }
        } else {
            echo "<p style='color:red'>❌ cs_save_employer_application returned null!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Exception: " . $e->getMessage() . "</p>";
        echo "<p>Stack trace:</p><pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<p><a href='index.php'>Go back to home</a></p>";
?>