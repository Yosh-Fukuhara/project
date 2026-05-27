<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'dashboard';
$pageTitle = 'Admin Dashboard - CyberSphere';

$pdo = get_db_connection();

$totalUsersStmt = $pdo->query('SELECT COUNT(*) FROM users');
$totalUsers = $totalUsersStmt->fetchColumn();

$totalPostsStmt = $pdo->query('SELECT COUNT(*) FROM posts');
$totalPosts = $totalPostsStmt->fetchColumn();

$activeUsersStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
$activeUsersStmt->execute(['active']);
$activeUsers = $activeUsersStmt->fetchColumn();

$recentPostsStmt = $pdo->query('SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 5');
$recentPosts = $recentPostsStmt->fetchAll();

$currentUserEmail = $_SESSION['user']['email'] ?? null;
?>

<?php include __DIR__ . '/partials/top.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<!-- Mobile drawer -->
<div id="adminMobileOverlay" class="fixed inset-0 bg-black/40 hidden z-40 md:hidden"></div>
<div id="adminMobileDrawer" class="fixed top-0 right-0 h-full w-72 bg-white z-50 translate-x-full transition-transform md:hidden border-l border-slate-200">
    <div class="p-5 border-b border-slate-200">
        <p class="font-extrabold text-slate-900">Admin Panel</p>
        <p class="text-xs text-slate-500">CyberSphere</p>
    </div>
    <div class="p-3 space-y-1">
        <a href="dashboard.php" class="block px-3 py-2 rounded-lg font-semibold <?php echo $active === 'dashboard' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?>">Dashboard</a>
        <a href="users.php" class="block px-3 py-2 rounded-lg font-semibold <?php echo $active === 'users' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?>">Users</a>
        <a href="posts.php" class="block px-3 py-2 rounded-lg font-semibold <?php echo $active === 'posts' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?>">Posts</a>
        <a href="../index.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Back to site</a>
        <a href="logout.php" class="block px-3 py-2 rounded-lg font-semibold text-red-600 hover:bg-red-50">Logout</a>
    </div>
</div>

<main class="flex-1 min-w-0">
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button id="adminMobileMenuBtn" type="button" class="md:hidden p-2 rounded-lg hover:bg-slate-100 border border-slate-200" aria-label="Open menu">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900">Dashboard</h1>
                <p class="text-sm text-slate-500">Admin: <?php echo htmlspecialchars($_SESSION['admin']['email'] ?? ''); ?></p>
            </div>
        </div>
        <span class="text-xs text-slate-500">UI-first (DB coming soon)</span>
    </header>

    <div class="p-4 sm:p-6 space-y-6">
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-sm text-slate-500">Total Users</p>
                <p class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo (int)$totalUsers; ?></p>
                <p class="text-xs text-slate-500 mt-1">From database</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-sm text-slate-500">Total Posts</p>
                <p class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo (int)$totalPosts; ?></p>
                <p class="text-xs text-slate-500 mt-1">From database</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-sm text-slate-500">Active Users</p>
                <p class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo (int)$activeUsers; ?></p>
                <p class="text-xs text-slate-500 mt-1">Active status</p>
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-extrabold text-slate-900">Quick actions</h2>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <a href="users.php" class="block bg-blue-900 text-white text-center font-bold py-3 rounded-xl hover:bg-blue-800 transition">Manage Users</a>
                <a href="posts.php" class="block bg-slate-900 text-white text-center font-bold py-3 rounded-xl hover:bg-slate-800 transition">Manage Posts</a>
                <a href="../index.php" class="block bg-white text-slate-900 text-center font-bold py-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition">Go to Site</a>
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-extrabold text-slate-900">Notes</h2>
            <ul class="list-disc list-inside text-sm text-slate-600 mt-2 space-y-1">
                <li>Admin pages are protected by session (hardcoded admin for now).</li>
                <li>“Users” and “Posts” pages are UI-first; DB wiring can be added later.</li>
                <li>Right now, posts count is taken from <code class="bg-slate-100 px-1 rounded">$_SESSION['posts']</code>.</li>
            </ul>
        </section>
    </div>

<?php include __DIR__ . '/partials/bottom.php'; ?>

