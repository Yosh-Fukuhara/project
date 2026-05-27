<?php
require_once __DIR__ . '/admin_auth.php';

if (admin_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (trim($email) === '' || trim($password) === '') {
        $errors[] = 'Email and password are required.';
    } else {
        if (admin_login_attempt($email, $password)) {
            header('Location: dashboard.php');
            exit;
        }
        $errors[] = 'Invalid admin credentials.';
    }
}

$pageTitle = 'Admin Login - CyberSphere';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-blue-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8">
        <div class="mb-6">
            <p class="text-sm text-slate-500">CyberSphere</p>
            <h1 class="text-2xl font-extrabold text-slate-900">Admin Login</h1>
            <p class="text-sm text-slate-500 mt-1">Temporary hardcoded admin (until DB is added).</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                <input type="email" name="email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-slate-50"
                    placeholder="admin@example.com" />
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" name="password"
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-slate-50"
                    placeholder="••••••••" />
            </div>
            <button type="submit"
                class="w-full bg-blue-900 text-white font-bold py-3 rounded-xl hover:bg-blue-800 transition">
                Sign in
            </button>
        </form>

        <div class="mt-5 text-sm text-slate-600">
            <a href="../index.php" class="text-blue-800 hover:underline">Back to site</a>
        </div>
    </div>
</body>
</html>

