<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'posts';
$pageTitle = 'Manage Posts - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);
    
    if ($action === 'delete' && $postId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM posts WHERE post_id = ?');
            $stmt->execute([$postId]);
            $success = 'Post deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting post: ' . $e->getMessage();
        }
    } elseif ($action === 'update' && $postId > 0) {
        $content = trim($_POST['content'] ?? '');
        try {
            $stmt = $pdo->prepare('UPDATE posts SET content = ? WHERE post_id = ?');
            $stmt->execute([$content, $postId]);
            $success = 'Post updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating post: ' . $e->getMessage();
        }
    }
}

// Fetch all regular posts (excluding job posts which have a separate page)
$posts = $pdo->query('
    SELECT p.*, u.first_name, u.last_name, u.email
    FROM posts p 
    JOIN users u ON p.user_id = u.user_id 
    ORDER BY p.created_at DESC
')->fetchAll();

// Fetch all attachments for posts
$attachments = [];
$stmt = $pdo->query('SELECT * FROM post_attachments');
while ($row = $stmt->fetch()) {
    if (!isset($attachments[$row['post_id']])) {
        $attachments[$row['post_id']] = [];
    }
    $attachments[$row['post_id']][] = $row;
}

function excerpt(string $text, int $max = 80): string {
    $t = trim($text);
    $len = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
    if ($len <= $max) return $t;
    $sub = function_exists('mb_substr') ? mb_substr($t, 0, $max) : substr($t, 0, $max);
    return $sub . '...';
}
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
        <a href="posts.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Posts</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Posts</h1>
                <p class="text-sm text-slate-500">Manage all regular user posts</p>
            </div>
        </div>
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
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-extrabold text-slate-900">All Posts</h2>
                <div class="flex items-center gap-2">
                    <input type="text" id="searchInput" placeholder="Search posts..." class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm w-52">
                </div>
            </div>

            <div class="p-5">
                <?php if (empty($posts)): ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-600 text-sm">
                        No posts yet.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm" id="postsTable">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                                <tr class="text-left border-b border-slate-200">
                                    <th class="py-3 px-5">ID</th>
                                    <th class="py-3 px-5">Author</th>
                                    <th class="py-3 px-5">Type</th>
                                    <th class="py-3 px-5">Content</th>
                                    <th class="py-3 px-5">Attachments</th>
                                    <th class="py-3 px-5">Created</th>
                                    <th class="py-3 px-5 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($posts as $p): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-4 px-5 font-mono text-xs"><?php echo htmlspecialchars($p['post_id']); ?></td>
                                        <td class="py-4 px-5">
                                            <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($p['email']); ?></p>
                                        </td>
                                        <td class="py-4 px-5">
                                            <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded-full text-xs">
                                                <?php echo htmlspecialchars(ucfirst($p['type'])); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-5 text-slate-600"><?php echo htmlspecialchars(excerpt($p['content'])); ?></td>
                                        <td class="py-4 px-5">
                                            <?php if (isset($attachments[$p['post_id']]) && !empty($attachments[$p['post_id']])): ?>
                                                <span class="text-blue-700 font-semibold text-xs">
                                                    <?php echo count($attachments[$p['post_id']]); ?> file(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-5 text-xs text-slate-500"><?php echo htmlspecialchars($p['created_at']); ?></td>
                                        <td class="py-4 px-5 text-center">
                                            <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode(array_merge($p, ['attachments' => $attachments[$p['post_id']] ?? []]))); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="post_id" value="<?php echo $p['post_id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Delete</button>
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
</main>

<!-- View Post Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Post Details</h3>
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

<!-- Edit Post Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit Post</h3>
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
function openViewModal(post) {
    const content = document.getElementById('viewModalContent');
    let attachmentsHtml = '';
    
    if (post.attachments && post.attachments.length > 0) {
        attachmentsHtml = `
            <div>
                <span class="text-slate-500 font-semibold">Attachments:</span>
                <div class="mt-2 space-y-2">
                    ${post.attachments.map(att => `
                        <a href="../${att.file_path}" target="_blank" class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg text-slate-900 hover:bg-slate-100">
                            <span class="text-slate-500">📎</span>
                            <span class="text-sm">${att.file_path}</span>
                        </a>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = `
        <div class="space-y-3 text-sm">
            <div>
                <span class="text-slate-500 font-semibold">Post ID:</span>
                <p class="text-slate-900 font-bold">${post.post_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Author:</span>
                <p class="text-slate-900">${post.first_name} ${post.last_name} (${post.email})</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Type:</span>
                <p class="text-slate-900">${post.type}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Created At:</span>
                <p class="text-slate-900">${post.created_at}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Content:</span>
                <p class="text-slate-900 mt-1 p-3 bg-slate-50 rounded-lg whitespace-pre-wrap">${post.content}</p>
            </div>
            ${attachmentsHtml}
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

// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#postsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>
<?php include __DIR__ . '/partials/bottom.php'; ?>
