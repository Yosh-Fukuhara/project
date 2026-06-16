<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'community_posts';
$pageTitle = 'Manage Community Posts - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $community_id = (int)$_POST['community_id'];
        $post_author_id = (int)$_POST['post_author_id'];
        $content = trim($_POST['content']);
        
        try {
            $stmt = $pdo->prepare('INSERT INTO community_posts (community_id, post_author_id, content) VALUES (?, ?, ?)');
            $stmt->execute([$community_id, $post_author_id, $content]);
            $post_id = $pdo->lastInsertId();
            
            // Handle attachment if needed (we'll skip for now to keep it simple)
            
            $success = 'Community post created successfully!';
        } catch (PDOException $e) {
            $error = 'Error creating community post: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $post_id = (int)$_POST['post_id'];
        $content = trim($_POST['content']);
        
        try {
            $stmt = $pdo->prepare('UPDATE community_posts SET content = ? WHERE post_id = ?');
            $stmt->execute([$content, $post_id]);
            $success = 'Community post updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating community post: ' . $e->getMessage();
        }
    } elseif ($action === 'delete' && isset($_POST['post_id'])) {
        $post_id = (int)$_POST['post_id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM community_posts WHERE post_id = ?');
            $stmt->execute([$post_id]);
            $success = 'Community post deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting community post: ' . $e->getMessage();
        }
    }
}

// Fetch all community posts
$community_posts = $pdo->query('
    SELECT cp.*, c.name as community_name, u.first_name, u.last_name, u.email
    FROM community_posts cp
    JOIN communities c ON cp.community_id = c.community_id
    JOIN users u ON cp.post_author_id = u.user_id
    ORDER BY cp.created_at DESC
')->fetchAll();

// Fetch all users and communities for create form
$users = $pdo->query('SELECT user_id, first_name, last_name, email FROM users ORDER BY first_name, last_name')->fetchAll();
$communities = $pdo->query('SELECT community_id, name FROM communities ORDER BY name')->fetchAll();
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
        <a href="users.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Users</a>
        <a href="employers.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Employers</a>
        <a href="community_posts.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Community Posts</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Community Posts</h1>
                <p class="text-sm text-slate-500">Manage all community posts</p>
            </div>
        </div>
        <button onclick="openCreateModal()" class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold transition">
            Add Community Post
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
                <h2 class="font-extrabold text-slate-900">All Community Posts</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Community</th>
                            <th class="text-left px-5 py-3">Author</th>
                            <th class="text-left px-5 py-3">Content</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($community_posts as $post): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($post['post_id']); ?></td>
                                <td class="px-5 py-4 text-slate-900 font-semibold"><?php echo htmlspecialchars($post['community_name']); ?></td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($post['email']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($post['content'], 0, 80, '...')); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($post)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($post)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this community post?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
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

<!-- Create Community Post Modal -->
<div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Create Community Post</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Community</label>
                <select name="community_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($communities as $community): ?>
                        <option value="<?php echo $community['community_id']; ?>"><?php echo htmlspecialchars($community['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Author User</label>
                <select name="post_author_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Content</label>
                <textarea name="content" rows="5" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- View Community Post Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Community Post Details</h3>
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

<!-- Edit Community Post Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit Community Post</h3>
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
                <label class="block text-sm font-semibold text-slate-700 mb-1">Content</label>
                <textarea name="content" id="edit_content" rows="5" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
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

function openViewModal(post) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="space-y-3">
            <div>
                <span class="text-slate-500 font-semibold">Post ID:</span>
                <p class="text-slate-900 font-bold">${post.post_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Community:</span>
                <p class="text-slate-900">${post.community_name}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Author:</span>
                <p class="text-slate-900">${post.first_name} ${post.last_name} (${post.email})</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Content:</span>
                <p class="text-slate-900 mt-1 p-3 bg-slate-50 rounded-lg whitespace-pre-wrap">${post.content}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Created:</span>
                <p class="text-slate-900">${post.created_at}</p>
            </div>
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openEditModal(post) {
    document.getElementById('edit_post_id').value = post.post_id;
    document.getElementById('edit_content').value = post.content;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>