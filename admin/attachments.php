<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'attachments';
$pageTitle = 'Manage Attachments - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['attachment_id'], $_POST['type'])) {
    $action = $_POST['action'];
    $attachment_id = (int)$_POST['attachment_id'];
    $type = $_POST['type'];
    
    try {
        if ($type === 'post') {
            $stmt = $pdo->prepare('SELECT file_path FROM post_attachments WHERE attachment_id = ?');
        } elseif ($type === 'community') {
            $stmt = $pdo->prepare('SELECT file_path FROM community_post_attachments WHERE attachment_id = ?');
        }
        $stmt->execute([$attachment_id]);
        $file_path = $stmt->fetchColumn();
        
        if ($file_path && file_exists(__DIR__ . '/../' . $file_path)) {
            unlink(__DIR__ . '/../' . $file_path);
        }
        
        if ($type === 'post') {
            $stmt = $pdo->prepare('DELETE FROM post_attachments WHERE attachment_id = ?');
        } elseif ($type === 'community') {
            $stmt = $pdo->prepare('DELETE FROM community_post_attachments WHERE attachment_id = ?');
        }
        $stmt->execute([$attachment_id]);
        
        $success = 'Attachment deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Error deleting attachment: ' . $e->getMessage();
    }
}

// Fetch all attachments
$post_attachments = $pdo->query('
    SELECT pa.*, p.content as post_content, u.first_name, u.last_name, u.email
    FROM post_attachments pa
    JOIN posts p ON pa.post_id = p.post_id
    JOIN users u ON p.user_id = u.user_id
    ORDER BY pa.attachment_id DESC
')->fetchAll();

$community_attachments = $pdo->query('
    SELECT cpa.*, cp.content as post_content, u.first_name, u.last_name, u.email
    FROM community_post_attachments cpa
    JOIN community_posts cp ON cpa.post_id = cp.post_id
    JOIN users u ON cp.post_author_id = u.user_id
    ORDER BY cpa.attachment_id DESC
')->fetchAll();
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
        <a href="attachments.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Attachments</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Attachments</h1>
                <p class="text-sm text-slate-500">Manage all uploaded files</p>
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

        <!-- Post Attachments -->
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-extrabold text-slate-900">Post Attachments</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">File</th>
                            <th class="text-left px-5 py-3">Author</th>
                            <th class="text-left px-5 py-3">Post Preview</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($post_attachments as $attachment): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($attachment['attachment_id']); ?></td>
                                <td class="px-5 py-4">
                                    <a href="../<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="text-blue-700 hover:underline">
                                        <?php echo htmlspecialchars(basename($attachment['file_path'])); ?>
                                    </a>
                                    <span class="text-xs text-slate-500 ml-2">(<?php echo htmlspecialchars($attachment['mime_type']); ?>)</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($attachment['first_name'] . ' ' . $attachment['last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($attachment['email']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($attachment['post_content'], 0, 50, '...')); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this attachment?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="attachment_id" value="<?php echo $attachment['attachment_id']; ?>">
                                        <input type="hidden" name="type" value="post">
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Community Post Attachments -->
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-extrabold text-slate-900">Community Post Attachments</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">File</th>
                            <th class="text-left px-5 py-3">Author</th>
                            <th class="text-left px-5 py-3">Post Preview</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($community_attachments as $attachment): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($attachment['attachment_id']); ?></td>
                                <td class="px-5 py-4">
                                    <a href="../<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="text-blue-700 hover:underline">
                                        <?php echo htmlspecialchars(basename($attachment['file_path'])); ?>
                                    </a>
                                    <span class="text-xs text-slate-500 ml-2">(<?php echo htmlspecialchars($attachment['mime_type']); ?>)</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($attachment['first_name'] . ' ' . $attachment['last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($attachment['email']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($attachment['post_content'], 0, 50, '...')); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this attachment?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="attachment_id" value="<?php echo $attachment['attachment_id']; ?>">
                                        <input type="hidden" name="type" value="community">
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

<?php include __DIR__ . '/partials/bottom.php'; ?>