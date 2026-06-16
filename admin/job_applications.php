<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'job_applications';
$pageTitle = 'Manage Job Applications - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $application_id = (int)$_POST['application_id'];
        $status = trim($_POST['status']);
        
        try {
            $stmt = $pdo->prepare('UPDATE job_applications SET status = ? WHERE application_id = ?');
            $stmt->execute([$status, $application_id]);
            $success = 'Application status updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating application: ' . $e->getMessage();
        }
    } elseif ($action === 'delete' && isset($_POST['application_id'])) {
        $application_id = (int)$_POST['application_id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM job_applications WHERE application_id = ?');
            $stmt->execute([$application_id]);
            $success = 'Application deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting application: ' . $e->getMessage();
        }
    }
}

// Fetch all job applications
$applications = $pdo->query('
    SELECT ja.*, u.first_name as app_first_name, u.last_name as app_last_name, u.email as app_email,
           p.content as post_content
    FROM job_applications ja
    JOIN users u ON ja.applicant_id = u.user_id
    JOIN posts p ON ja.post_id = p.post_id
    ORDER BY ja.created_at DESC
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
        <a href="job_posts.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Job Posts</a>
        <a href="job_applications.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Job Applications</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Job Applications</h1>
                <p class="text-sm text-slate-500">Manage all job applications</p>
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
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-extrabold text-slate-900">All Job Applications</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Applicant</th>
                            <th class="text-left px-5 py-3">Job Post</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="text-left px-5 py-3">Created</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($applications as $app): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($app['application_id']); ?></td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($app['app_first_name'] . ' ' . $app['app_last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($app['app_email']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($app['post_content'], 0, 50, '...')); ?></td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold 
                                        <?php echo ($app['status'] == 'pending') ? 'bg-amber-100 text-amber-700' : 
                                              (($app['status'] == 'reviewing') ? 'bg-blue-100 text-blue-700' : 
                                              (($app['status'] == 'interview') ? 'bg-purple-100 text-purple-700' : 
                                              (($app['status'] == 'accepted') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'))); ?>">
                                        <?php echo htmlspecialchars(ucfirst($app['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500"><?php echo htmlspecialchars(date('M j, Y', strtotime($app['created_at']))); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openStatusModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Update Status</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this application?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
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

<!-- View Application Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Application Details</h3>
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

<!-- Update Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Update Application Status</h3>
            <button onclick="closeStatusModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="application_id" id="status_application_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                <select name="status" id="status_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="pending">Pending</option>
                    <option value="reviewing">Reviewing</option>
                    <option value="interview">Interview</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeStatusModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openViewModal(app) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <span class="text-slate-500 font-semibold">Application ID:</span>
                <p class="text-slate-900 font-bold">${app.application_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Status:</span>
                <p class="text-slate-900 font-bold capitalize">${app.status}</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Applicant:</span>
                <p class="text-slate-900">${app.app_first_name} ${app.app_last_name} (${app.app_email})</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Application Message:</span>
                <p class="text-slate-900 mt-1 p-3 bg-slate-50 rounded-lg">${app.app_message || 'No message'}</p>
            </div>
            ${app.resume_path ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Resume:</span>
                <p class="text-slate-900 mt-1"><a href="../${app.resume_path}" target="_blank" class="text-blue-700 hover:underline">View Resume</a></p>
            </div>` : ''}
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Created:</span>
                <p class="text-slate-900">${app.created_at}</p>
            </div>
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openStatusModal(app) {
    document.getElementById('status_application_id').value = app.application_id;
    document.getElementById('status_status').value = app.status;
    document.getElementById('statusModal').classList.remove('hidden');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>