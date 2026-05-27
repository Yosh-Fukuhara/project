<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'posts';
$pageTitle = 'Manage Posts - Admin';

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);
    
    if ($action === 'delete' && $postId > 0) {
        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        header('Location: posts.php');
        exit;
    }
}

$posts = $pdo->query('SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC')->fetchAll();

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
                <p class="text-sm text-slate-500">Delete posts (UI-first; DB wiring later)</p>
            </div>
        </div>
        <span class="text-xs text-slate-500">Posts in session: <?php echo (int)count($posts); ?></span>
    </header>

    <div class="p-4 sm:p-6 space-y-6">
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-extrabold text-slate-900">All Posts</h2>
                <div class="flex items-center gap-2">
                    <input type="text" placeholder="Search (UI only)" class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm w-52">
                    <select class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm">
                        <option>All types</option>
                        <option>User</option>
                        <option>Job</option>
                    </select>
                </div>
            </div>

            <div class="p-5">
                <?php if (empty($posts)): ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-600 text-sm">
                        No posts yet in this session. Create a post on the main site, then refresh this page.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500 border-b border-slate-200">
                                    <th class="py-3 pr-4">ID</th>
                                    <th class="py-3 pr-4">Author</th>
                                    <th class="py-3 pr-4">Time</th>
                                    <th class="py-3 pr-4">Content</th>
                                    <th class="py-3 pr-4">Attachment</th>
                                    <th class="py-3 pr-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($posts as $p): ?>
                                    <?php
                                        $pid = (string)($p['id'] ?? '—');
                                        $author = (string)($p['username'] ?? '—');
                                        $time = (string)($p['created_at'] ?? '—');
                                        $content = (string)($p['content'] ?? '');
                                        $att = $p['attachment_path'] ?? null;
                                    ?>
                                    <tr>
                                        <td class="py-3 pr-4 font-mono text-xs"><?php echo htmlspecialchars($pid); ?></td>
                                        <td class="py-3 pr-4 font-semibold text-slate-900"><?php echo htmlspecialchars($author); ?></td>
                                        <td class="py-3 pr-4"><?php echo htmlspecialchars($time); ?></td>
                                        <td class="py-3 pr-4"><?php echo htmlspecialchars(excerpt($content)); ?></td>
                                        <td class="py-3 pr-4">
                                            <?php if (!empty($att)): ?>
                                                <a class="text-blue-800 hover:underline" href="../<?php echo htmlspecialchars($att); ?>" target="_blank">View</a>
                                            <?php else: ?>
                                                <span class="text-slate-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <button type="button"
                                                class="px-3 py-2 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800"
                                                onclick="showPostDetails(<?php echo htmlspecialchars(json_encode($p)); ?>);">
                                                View
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="post_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit"
                                                    class="px-3 py-2 rounded-lg bg-red-600 text-white font-semibold ml-2 hover:bg-red-700">
                                                    Delete
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

    <div id="postModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-extrabold text-slate-900">Post Details</h3>
                <button onclick="closePostModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="postModalContent" class="space-y-3"></div>
            <div class="mt-6 text-right">
                <button onclick="closePostModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Close</button>
            </div>
        </div>
    </div>

    <script>
        function showPostDetails(post) {
            const content = document.getElementById('postModalContent');
            content.innerHTML = `
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-slate-500 font-semibold">ID:</span>
                        <p class="text-slate-900 font-bold">${post.id}</p>
                    </div>
                    <div>
                        <span class="text-slate-500 font-semibold">Author:</span>
                        <p class="text-slate-900 font-bold">${post.username}</p>
                    </div>
                    <div>
                        <span class="text-slate-500 font-semibold">Created At:</span>
                        <p class="text-slate-900">${post.created_at}</p>
                    </div>
                    <div>
                        <span class="text-slate-500 font-semibold">Content:</span>
                        <p class="text-slate-900 mt-1 p-3 bg-slate-50 rounded-lg">${post.content}</p>
                    </div>
                    ${post.attachment_path ? `
                    <div>
                        <span class="text-slate-500 font-semibold">Attachment:</span>
                        <p class="text-slate-900 mt-1">
                            <a href="../${post.attachment_path}" target="_blank" class="text-blue-800 hover:underline">View Attachment</a>
                        </p>
                    </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('postModal').classList.remove('hidden');
        }
        
        function closePostModal() {
            document.getElementById('postModal').classList.add('hidden');
        }
    </script>
<?php include __DIR__ . '/partials/bottom.php'; ?>
