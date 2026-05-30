<?php
require_once 'includes/bootstrap.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = 'Please enter a valid email address';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    }

    if (empty($errors)) {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT id, username, email, password, role, status, profile_pic, cover_pic, bio, location, work, education, website, phone FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['status'] === 'active') {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'profile_pic' => $user['profile_pic'],
                'cover_pic' => $user['cover_pic'],
                'bio' => $user['bio'],
                'location' => $user['location'],
                'work' => $user['work'],
                'education' => $user['education'],
                'website' => $user['website'],
                'phone' => $user['phone']
            ];

            session_regenerate_id(true);
            setcookie('last_login', date('Y-m-d H:i:s'), time() + (86400 * 30), "/");
            setcookie('welcome_seen', 'true', time() + (86400 * 365), "/");

            // Redirect back to intended page (e.g. cart) if set
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            // Only allow relative paths to prevent open redirect
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php(\?.*)?$/', $redirect)) {
                $redirect = 'index.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = 'Invalid email or password';
        }
    }
}

$pageTitle = 'Login - CyberSphere';
$currentPage = 'login';
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
        <div class="text-center mb-10">
            <h1 class="text-2xl font-bold mb-2">Welcome Back</h1>
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
            <div>
                <label class="block text-xl font-semibold mb-1">Email</label>
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
                <input 
                    type="password" 
                    name="password"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 shadow-md"
                    placeholder="************"
                >
            </div>

            <button 
                type="submit"
                class="w-full bg-blue-900 text-white font-bold py-4 rounded-xl hover:bg-blue-800 transition shadow-md"
            >
                Login <svg class="w-8 h-8 inline-block ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </form>

        <div class="flex items-center my-8">
            <div class="flex-1 border-t border-gray-800"></div>
            <span class="px-4 font-bold text-xl">OR</span>
            <div class="flex-1 border-t border-gray-800"></div>
        </div>

        <a href="signup.php" class="block w-full bg-white text-pink-600 font-bold py-4 rounded-xl hover:bg-gray-50 transition border-2 border-pink-600 text-center text-2xl">
            Sign Up
        </a>
    </div>
</body>
</html>
