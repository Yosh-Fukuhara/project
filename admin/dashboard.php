<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../role_helpers.php';
admin_require_login();

$active = 'dashboard';
$pageTitle = 'Admin Dashboard - CyberSphere';

$pdo = get_db_connection();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['app_id'])) {
    $appId = (int)$_POST['app_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        cs_update_employer_application_status((string)$appId, $status);
    }
    
    // Redirect to avoid resubmission
    header('Location: dashboard.php');
    exit;
}

$totalUsersStmt = $pdo->query('SELECT COUNT(*) FROM users');
$totalUsers = $totalUsersStmt->fetchColumn();

$totalPostsStmt = $pdo->query('SELECT COUNT(*) FROM posts');
$totalPosts = $totalPostsStmt->fetchColumn();

$activeUsersStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
$activeUsersStmt->execute(['active']);
$activeUsers = $activeUsersStmt->fetchColumn();

$recentPostsStmt = $pdo->query('SELECT p.*, u.first_name, u.last_name FROM posts p JOIN users u ON p.user_id = u.user_id ORDER BY p.created_at DESC LIMIT 5');
$recentPosts = $recentPostsStmt->fetchAll();

// Get employer applications
$employerApps = cs_get_employer_applications();
$pendingCount = count(array_filter($employerApps, fn($app) => $app['status'] === 'pending'));
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
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-sm text-slate-500">Pending Employers</p>
                <p class="text-3xl font-extrabold text-amber-600 mt-2"><?php echo $pendingCount; ?></p>
                <p class="text-xs text-slate-500 mt-1">Awaiting approval</p>
            </div>
        </section>

        <!-- Employer Applications -->
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-extrabold text-slate-900">Employer Applications</h2>
            </div>
            <?php if (empty($employerApps)): ?>
                <div class="p-12 text-center text-slate-400">
                    <div class="text-5xl mb-3">📋</div>
                    <p class="font-medium">No employer applications yet</p>
                </div>
            <?php else: ?>
                <div class="p-5 space-y-6">
                    <?php foreach ($employerApps as $app): ?>
                        <div class="border border-slate-200 rounded-xl p-4">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <?php if ($app['logo_url']): ?>
                                        <img src="<?php echo htmlspecialchars($app['logo_url']); ?>" alt="Company Logo" class="w-12 h-12 rounded-lg object-cover">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-lg">
                                            <?php echo strtoupper(substr($app['company_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
                                            <h3 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($app['company_name']); ?></h3>
                                            <?php 
                                            $statusBadge = [
                                                'pending' => 'bg-amber-100 text-amber-700',
                                                'approved' => 'bg-green-100 text-green-700',
                                                'rejected' => 'bg-red-100 text-red-700'
                                            ];
                                            ?>
                                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold <?php echo $statusBadge[$app['status']] ?? 'bg-slate-100 text-slate-700'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($app['status'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-600 mb-1">
                                            <span class="font-semibold">Applicant:</span> <?php echo htmlspecialchars($app['username'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?>)
                                        </p>
                                        <p class="text-sm text-slate-600 mb-1">
                                            <span class="font-semibold">Industry:</span> <?php echo htmlspecialchars($app['industry']); ?>
                                            <?php if ($app['company_size']): ?>
                                                • <span class="font-semibold">Size:</span> <?php echo htmlspecialchars($app['company_size']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($app['website']): ?>
                                            <p class="text-sm text-slate-600 mb-1">
                                                <span class="font-semibold">Website:</span> <a href="<?php echo htmlspecialchars($app['website']); ?>" target="_blank" class="text-blue-700 hover:underline"><?php echo htmlspecialchars($app['website']); ?></a>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($app['contact_name'] || $app['contact_phone']): ?>
                                            <p class="text-sm text-slate-600 mb-1">
                                                <span class="font-semibold">Contact:</span> 
                                                <?php if ($app['contact_name']): ?>
                                                    <?php echo htmlspecialchars($app['contact_name']); ?>
                                                <?php endif; ?>
                                                <?php if ($app['contact_phone']): ?>
                                                    • <?php echo htmlspecialchars($app['contact_phone']); ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($app['description']): ?>
                                            <p class="text-sm text-slate-600 mt-2">
                                                <span class="font-semibold">Description:</span> <?php echo htmlspecialchars($app['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($app['documents'])): ?>
                                            <div class="mt-3">
                                                <p class="text-xs font-semibold text-slate-500 mb-1">Documents:</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($app['documents'] as $doc): ?>
                                                        <a href="<?php echo htmlspecialchars($doc['path'] ?? $doc['file_url']); ?>" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold hover:bg-slate-200 transition">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                            </svg>
                                                            <?php echo htmlspecialchars($doc['name'] ?? 'Document'); ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <p class="text-xs text-slate-400 mt-2">
                                            Applied: <?php echo htmlspecialchars($app['submitted_at'] ?? 'N/A'); ?>
                                            <?php if ($app['reviewed_at']): ?>
                                                • Reviewed: <?php echo htmlspecialchars($app['reviewed_at']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-start md:items-end gap-2">
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <form method="POST" class="flex gap-2">
                                            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                                                Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                                                Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
            <h2 class="font-extrabold text-slate-900">Recent Posts</h2>
            <?php if (empty($recentPosts)): ?>
                <p class="text-sm text-slate-500 mt-2">No posts yet</p>
            <?php else: ?>
                <div class="mt-4 space-y-3">
                    <?php foreach ($recentPosts as $post): ?>
                        <div class="border border-slate-200 rounded-xl p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-slate-800">
                                        <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                    </p>
                                    <p class="text-slate-600 text-sm mt-1"><?php echo htmlspecialchars(mb_strimwidth($post['content'], 0, 100, '...')); ?></p>
                                </div>
                                <span class="text-xs text-slate-500"><?php echo htmlspecialchars(date('M j, Y', strtotime($post['created_at']))); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

<?php include __DIR__ . '/partials/bottom.php'; ?>

