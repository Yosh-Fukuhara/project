<?php
require_once 'includes/bootstrap.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = 'Please enter a valid email address';
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
        
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $checkStmt->execute([$email, $username]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Email or username already exists';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
            $insertStmt->execute([$username, $email, $hashedPassword]);
            $userId = $pdo->lastInsertId();

            $_SESSION['user'] = [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => 'user',
                'profile_pic' => null,
                'cover_pic' => null,
                'bio' => '',
                'location' => '',
                'work' => '',
                'education' => '',
                'website' => '',
                'phone' => ''
            ];

            session_regenerate_id(true);
            setcookie('last_login', date('Y-m-d H:i:s'), time() + (86400 * 30), "/");
            setcookie('welcome_seen', 'true', time() + (86400 * 365), "/");

            header('Location: index.php');
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
            <div>
                <label class="block text-xl font-semibold mb-1">Username</label>
                <input 
                    type="text" 
                    name="username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                    placeholder="John Doe"
                >
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
                <input 
                    type="password" 
                    name="confirm_password"
                    id="confirm_password"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                    placeholder="************"
                >
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
