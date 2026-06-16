<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'announcements';
$pageTitle = 'Manage Announcements - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($userId <= 0 || $content === '') {
            $error = 'Author and announcement content are required.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO posts (user_id, type, content) VALUES (?, ?, ?)');
                $stmt->execute([$userId, 'announcement', $content]);
                $success = 'Announcement created successfully!';
            } catch (PDOException $e) {
                $error = 'Error creating announcement: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($postId <= 0 || $userId <= 0 || $content === '') {
            $error = 'Announcement, author, and content are required.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE posts SET user_id = ?, type = ?, content = ? WHERE post_id = ?');
                $stmt->execute([$userId, 'announcement', $content, $postId]);
                $success = 'Announcement updated successfully!';
            } catch (PDOException $e) {
                $error = 'Error updating announcement: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && isset($_POST['post_id'])) {
        $postId = (int)$_POST['post_id'];

        try {
            $stmt = $pdo->prepare('DELETE FROM posts WHERE post_id = ? AND type = ?');
            $stmt->execute([$postId, 'announcement']);
            $success = 'Announcement deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting announcement: ' . $e->getMessage();
        }
    }
}

$users = $pdo->query('SELECT user_id, first_name, last_name, email FROM users ORDER BY first_name, last_name')->fetchAll();
$announcements = $pdo->query("
    SELECT p.*, u.first_name, u.last_name, u.email
    FROM posts p
    JOIN users u ON u.user_id = p.user_id
    WHERE p.type = 'announcement'
    ORDER BY p.created_at DESC
")->fetchAll();
?>

<?php include __DIR__ . '/partials/top.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div id="adminMobileOverlay" class="fixed inset-0 bg-black/40 hidden z-40 md:hidden"></div>
<div id="adminMobileDrawer" class="fixed top-0 right-0 h-full w-72 bg-white z-50 translate-x-full transition-transform md:hidden border-l border-slate-200">
    <div class="p-5 border-b border-slate-200">
        <p class="font-extrabold text-slate-900">Admin Panel</p>
        <p class="text-xs text-slate-500">CyberSphere</p>
    </div>
    <div class="p-3 space-y-1">
        <a href="dashboard.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
        <a href="announcements.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Announcements</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Announcements</h1>
                <p class="text-sm text-slate-500">Create and edit admin announcements</p>
            </div>
        </div>
        <button onclick="openCreateModal()" class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold transition">
            Add Announcement
        </button>
    </header>

    <div class="p-4 sm:p-6 space-y-6">
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-extrabold text-slate-900">All Announcements</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Author</th>
                            <th class="text-left px-5 py-3">Content</th>
                            <th class="text-left px-5 py-3">Updated</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($announcements as $announcement): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($announcement['post_id']); ?></td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($announcement['email']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($announcement['content'], 0, 90, '...')); ?></td>
                                <td class="px-5 py-4 text-xs text-slate-500"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($announcement['updated_at'] ?? $announcement['created_at']))); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($announcement)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($announcement)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="post_id" value="<?php echo $announcement['post_id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Create Announcement</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Author User</label>
                <select name="user_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Announcement</label>
                <textarea name="content" rows="6" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Create</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Announcement Details</h3>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="viewModalContent" class="space-y-4"></div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeViewModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Close</button>
        </div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit Announcement</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="post_id" id="edit_post_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Author User</label>
                <select name="user_id" id="edit_user_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Announcement</label>
                <textarea name="content" id="edit_content" rows="6" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function openViewModal(announcement) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="space-y-3 text-sm">
            <div>
                <span class="text-slate-500 font-semibold">ID:</span>
                <p class="text-slate-900 font-bold">${announcement.post_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Author:</span>
                <p class="text-slate-900">${announcement.first_name} ${announcement.last_name} (${announcement.email})</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Announcement:</span>
                <p class="text-slate-900 mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 p-3">${announcement.content}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Created:</span>
                <p class="text-slate-900">${announcement.created_at}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Updated:</span>
                <p class="text-slate-900">${announcement.updated_at || announcement.created_at}</p>
            </div>
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openEditModal(announcement) {
    document.getElementById('edit_post_id').value = announcement.post_id;
    document.getElementById('edit_user_id').value = announcement.user_id;
    document.getElementById('edit_content').value = announcement.content;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>
