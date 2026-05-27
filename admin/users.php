<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'users';
$pageTitle = 'Manage Users - Admin';

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'delete' && $userId > 0) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        header('Location: users.php');
        exit;
    }
}

$users = $pdo->query('SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC')->fetchAll();
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
        <a href="dashboard.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
        <a href="users.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Users</a>
        <a href="posts.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Posts</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Users</h1>
                <p class="text-sm text-slate-500">View user details / remove accounts (DB soon)</p>
            </div>
        </div>
        <span class="text-xs text-slate-500">Admin: <?php echo htmlspecialchars($_SESSION['admin']['email'] ?? ''); ?></span>
    </header>

    <div class="p-4 sm:p-6 space-y-6">
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-extrabold text-slate-900">All Users</h2>
                <div class="flex items-center gap-2">
                    <input type="text" placeholder="Search (UI only)" class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm w-52">
                    <select class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm">
                        <option>All roles</option>
                        <option>User</option>
                        <option>Admin</option>
                    </select>
                </div>
            </div>

            <div class="p-5">
                <?php if (empty($users)): ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-600 text-sm">
                        No user records yet (because there’s no database). Once someone logs in on the main site,
                        you’ll see at least the current session user here as a placeholder.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500 border-b border-slate-200">
                                    <th class="py-3 pr-4">ID</th>
                                    <th class="py-3 pr-4">Username</th>
                                    <th class="py-3 pr-4">Email</th>
                                    <th class="py-3 pr-4">Role</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="py-3 pr-4"><?php echo htmlspecialchars((string)$u['id']); ?></td>
                                        <td class="py-3 pr-4 font-semibold text-slate-900"><?php echo htmlspecialchars((string)$u['username']); ?></td>
                                        <td class="py-3 pr-4"><?php echo htmlspecialchars((string)$u['email']); ?></td>
                                        <td class="py-3 pr-4"><?php echo htmlspecialchars((string)$u['role']); ?></td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-50 text-green-700 border border-green-200">
                                                <?php echo htmlspecialchars((string)$u['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <button type="button" class="px-3 py-2 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800"
                                                onclick="showUserDetails(<?php echo htmlspecialchars(json_encode($u)); ?>);">
                                                View
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="px-3 py-2 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 ml-2">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div id="userModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-extrabold text-slate-900">User Details</h3>
                <button onclick="closeUserModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="userModalContent" class="space-y-3"></div>
            <div class="mt-6 text-right">
                <button onclick="closeUserModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Close</button>
            </div>
        </div>
    </div>

    <script>
        function showUserDetails(user) {
            const content = document.getElementById('userModalContent');
            content.innerHTML = `
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="col-span-1">
                        <span class="text-slate-500 font-semibold">ID:</span>
                        <p class="text-slate-900 font-bold">${user.id}</p>
                    </div>
                    <div class="col-span-1">
                        <span class="text-slate-500 font-semibold">Role:</span>
                        <p class="text-slate-900 font-bold capitalize">${user.role}</p>
                    </div>
                    <div class="col-span-1">
                        <span class="text-slate-500 font-semibold">Username:</span>
                        <p class="text-slate-900">${user.username}</p>
                    </div>
                    <div class="col-span-1">
                        <span class="text-slate-500 font-semibold">Status:</span>
                        <p class="text-slate-900 capitalize">${user.status}</p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-slate-500 font-semibold">Email:</span>
                        <p class="text-slate-900">${user.email}</p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-slate-500 font-semibold">Created At:</span>
                        <p class="text-slate-900">${user.created_at}</p>
                    </div>
                </div>
            `;
            document.getElementById('userModal').classList.remove('hidden');
        }
        
        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
        }
    </script>
<?php include __DIR__ . '/partials/bottom.php'; ?>

