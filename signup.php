<?php
require_once 'includes/bootstrap.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($firstName)) {
        $errors[] = 'First name is required';
    }
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    }

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = 'Please enter a valid email address';
    }

    if (empty($phoneNumber)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^\\+?[0-9]{10,15}$/', $phoneNumber)) { // Basic validation for 10-15 digits, optional +
        $errors[] = 'Please enter a valid phone number';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        $pdo = get_db_connection();
        
        $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Email already exists';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, phone_number, is_verified) VALUES (?, ?, ?, ?, ?, ?)');
            $insertStmt->execute([$firstName, $lastName, $email, $hashedPassword, $phoneNumber, 0]); // is_verified = 0 by default
            $userId = $pdo->lastInsertId();
            
            // Insert a blank user profile record
            $profileStmt = $pdo->prepare('INSERT INTO user_profiles (user_id) VALUES (?)');
            $profileStmt->execute([$userId]);

            // Generate OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expires_at'] = time() + (5 * 60); // OTP valid for 5 minutes
            $_SESSION['signup_email'] = $email; // Store email to verify later
            $_SESSION['signup_phone_number'] = $phoneNumber; // Store phone number
            $_SESSION['signup_user_id'] = $userId; // Store user ID temporarily

            // Instead of directly logging in and redirecting to index.php,
            // redirect to OTP verification page
            header('Location: verify_otp.php');
            exit;
        }
    }
}

$pageTitle = 'Sign Up - CyberSphere';
$currentPage = 'signup';
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
            <h1 class="text-2xl font-bold">Create Account</h1>
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

        <form method="POST" class="space-y-8">
            <!-- Note: profile picture upload is available on your Profile page after signing in -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xl font-semibold mb-1">First Name</label>
                    <input 
                        type="text" 
                        name="first_name"
                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                        placeholder="John"
                    >
                </div>
                <div>
                    <label class="block text-xl font-semibold mb-1">Last Name</label>
                    <input 
                        type="text" 
                        name="last_name"
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                        placeholder="Doe"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xl font-semibold mb-1">Email Address</label>
                <input 
                    type="email" 
                    name="email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                    placeholder="Example@gmail.com"
                >
            </div>

            <div>
                <label class="block text-xl font-semibold mb-1">Phone Number</label>
                <input 
                    type="text" 
                    name="phone_number"
                    value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                    placeholder="+639123456789"
                >
            </div>

            <div>
                <label class="block text-xl font-semibold mb-1">Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        name="password"
                        id="password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md pr-12"
                        placeholder="************"
                    >
                    <button type="button" class="togglePassword absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-blue-900 focus:outline-none" data-target="password">
                        <svg class="eyeOpen w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg class="eyeClosed w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 014.13-5.555M15.812 4.138A10.042 10.042 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.555m-1.292-1.292a3 3 0 11-4.243-4.243M3 3l18 18"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xl font-semibold mb-1">Confirm Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        name="confirm_password"
                        id="confirm_password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md pr-12"
                        placeholder="************"
                    >
                    <button type="button" class="togglePassword absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-blue-900 focus:outline-none" data-target="confirm_password">
                        <svg class="eyeOpen w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg class="eyeClosed w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 014.13-5.555M15.812 4.138A10.042 10.042 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.555m-1.292-1.292a3 3 0 11-4.243-4.243M3 3l18 18"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <script>
                document.querySelectorAll('.togglePassword').forEach(button => {
                    button.addEventListener('click', function() {
                        const targetId = this.getAttribute('data-target');
                        const passwordInput = document.getElementById(targetId);
                        const eyeOpen = this.querySelector('.eyeOpen');
                        const eyeClosed = this.querySelector('.eyeClosed');
                        
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            eyeOpen.classList.add('hidden');
                            eyeClosed.classList.remove('hidden');
                        } else {
                            passwordInput.type = 'password';
                            eyeOpen.classList.remove('hidden');
                            eyeClosed.classList.add('hidden');
                        }
                    });
                });
            </script>

            <div class="flex items-start gap-3 bg-blue-50 p-4 rounded-xl border border-blue-100">
                <input type="checkbox" name="terms" required class="mt-1 w-5 h-5 text-blue-900 focus:ring-blue-500 rounded border-gray-300">
                <p class="text-sm text-gray-700 leading-relaxed">
                    By creating an account, you agree to our <span class="font-bold text-blue-900">Terms & Conditions</span>. 
                    You acknowledge that we adhere to international legal standards for digital assets. 
                    <span class="font-bold">Privacy Notice:</span> Please be aware that uploaded files are not currently hashed; exercise caution when sharing sensitive information.
                </p>
            </div>

            <button 
                type="submit"
                class="w-full bg-blue-900 text-white font-bold py-4 rounded-xl hover:bg-blue-800 transition shadow-md text-xl"
            >
                Create Account <svg class="w-6 h-6 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </form>

        <div class="border-t border-gray-600 mt-8 pt-8 text-center">
            <p class="text-lg">
                Already have an account? 
                <a href="login.php" class="text-pink-600 font-bold hover:underline text-xl">Sign In</a>
            </p>
        </div>
    </div>
</body>
</html>
