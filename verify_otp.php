<?php
require_once 'includes/bootstrap.php';
require_once 'config/database.php';

$pageTitle = 'Verify OTP - CyberSphere';
$currentPage = 'verify_otp';

$errors = [];
$message = '';

// Check if OTP data exists in session
if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expires_at']) || !isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_flow'])) {
    header('Location: index.php'); // Redirect to home if no valid OTP context
    exit;
}

$userId = $_SESSION['otp_user_id'];
$expectedOtp = $_SESSION['otp'];
$otpExpiresAt = $_SESSION['otp_expires_at'];
$currentFlow = $_SESSION['otp_flow'];
$phoneNumber = $_SESSION['otp_phone_number'] ?? ''; // Phone number from the flow

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = trim($_POST['otp'] ?? '');

    if (empty($enteredOtp)) {
        $errors[] = 'Please enter the OTP.';
    } elseif ($enteredOtp !== $expectedOtp) {
        $errors[] = 'Invalid OTP. Please try again.';
    } elseif (time() > $otpExpiresAt) {
        $errors[] = 'OTP has expired. Please request a new one.';
    }

    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            // Clear OTP data from session regardless of flow
            unset($_SESSION['otp']);
            unset($_SESSION['otp_expires_at']);
            unset($_SESSION['otp_user_id']);
            unset($_SESSION['otp_phone_number']);
            unset($_SESSION['otp_flow']);

            if ($currentFlow === 'signup') {
                $stmt = $pdo->prepare('UPDATE users SET is_verified = 1 WHERE user_id = ?');
                $stmt->execute([$userId]);

                // Fetch user details to populate session (assuming phone_number is already in DB)
                $userStmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, phone_number, role FROM users WHERE user_id = ?');
                $userStmt->execute([$userId]);
                $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

                if ($userData) {
                    // Log the user in
                    $_SESSION['user'] = [
                        'user_id' => $userData['user_id'],
                        'first_name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'username' => trim($userData['first_name'] . ' ' . $userData['last_name']),
                        'email' => $userData['email'],
                        'role' => $userData['role'],
                        'profile_pic' => null,
                        'cover_pic' => null,
                        'bio' => null,
                        'location' => null,
                        'website' => null,
                        'phone' => $userData['phone_number']
                    ];
                }

                session_regenerate_id(true);
                setcookie('last_login', date('Y-m-d H:i:s'), time() + (86400 * 30), "/");
                setcookie('welcome_seen', 'true', time() + (86400 * 365), "/");

                header('Location: index.php');
                exit;

            } elseif ($currentFlow === 'checkout') {
                // For checkout, OTP is for confirmation, no DB update needed (already done in cart.php)
                // Redirect back to cart.php with a success flag
                header('Location: cart.php?checkout_success=true');
                exit;
            }

        } catch (PDOException $e) {
            $errors[] = 'Database error during verification. Please try again later.';
            // Log error: $e->getMessage();
        }
    }
}

// Handle resend OTP
if (isset($_GET['resend'])) {
    // Regenerate OTP
    $newOtp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = $newOtp;
    $_SESSION['otp_expires_at'] = time() + (5 * 60); // New OTP valid for 5 minutes
    // The current flow and user ID are already in session, no need to re-set
    $expectedOtp = $newOtp; // Update for display

    $message = 'A new OTP has been sent to your phone number.';
    // Here you would integrate SMS sending if available
    // For now, it will just update the session OTP
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-8 md:p-12">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold">Verify Your Phone Number</h1>
            <p class="text-gray-600">Please enter the 6-digit OTP sent to your phone number: <strong><?php echo htmlspecialchars($phoneNumber); ?></strong></p>
            <p class="text-sm text-blue-500 mt-2">Your OTP is: <?php echo htmlspecialchars($expectedOtp); ?> (Expires in 5 minutes)</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="otp" class="block text-xl font-semibold mb-1">One Time Password (OTP)</label>
                <input 
                    type="text" 
                    name="otp"
                    id="otp"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md text-center text-2xl tracking-widest"
                    placeholder="______"
                    maxlength="6"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    required
                >
            </div>
            <button 
                type="submit"
                class="w-full bg-blue-900 text-white font-bold py-4 rounded-xl hover:bg-blue-800 transition shadow-md text-xl"
            >
                Verify OTP
            </button>
        </form>

        <div class="text-center mt-6">
            <p class="text-gray-600">Didn't receive the OTP?</p>
            <a href="verify_otp.php?resend=true" class="text-blue-600 hover:underline font-semibold">Resend OTP</a>
        </div>
    </div>
</body>
</html>