<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';

cs_require_auth();
cs_require_role('admin');

$pageTitle   = 'Admin Dashboard - CyberSphere';
$currentPage = 'admin';

$tab    = $_GET['tab'] ?? 'overview';
$flash  = '';
$flashType = 'green';

// ── Handle admin actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Approve / Reject employer application
    if ($action === 'approve_employer' || $action === 'reject_employer') {
        $appId  = $_POST['app_id'] ?? '';
        $status = ($action === 'approve_employer') ? 'approved' : 'rejected';
        cs_update_employer_application_status($appId, $status);

        if ($status === 'approved') {
            $flash = 'Employer application approved. The user will become an Employer on their next login.';
        } else {
            $flash = 'Employer application rejected.';
            $flashType = 'red';
        }
        header('Location: admin.php?tab=employers&flash=' . urlencode($flash) . '&ft=' . $flashType);
        exit;
    }

    // Delete a post (admin power)
    if ($action === 'admin_delete_post') {
        $pid = $_POST['post_id'] ?? '';
        foreach ($_SESSION['posts'] as $idx => $p) {
            if (($p['id'] ?? '') === $pid) {
                array_splice($_SESSION['posts'], $idx, 1);
                break;
            }
        }
        $flash = 'Post removed.';
        header('Location: admin.php?tab=posts&flash=' . urlencode($flash));
        exit;
    }

    // Suspend / restore user (session-based simulation)
    if ($action === 'toggle_suspend_user') {
        $uid = $_POST['user_id'] ?? '';
        if (!isset($_SESSION['admin_suspended_users'])) $_SESSION['admin_suspended_users'] = [];
        if (in_array($uid, $_SESSION['admin_suspended_users'])) {
            $_SESSION['admin_suspended_users'] = array_values(array_filter($_SESSION['admin_suspended_users'], fn($x) => $x !== $uid));
            $flash = 'User restored.';
        } else {
            $_SESSION['admin_suspended_users'][] = $uid;
            $flash = 'User suspended.';
            $flashType = 'amber';
        }
        header('Location: admin.php?tab=users&flash=' . urlencode($flash) . '&ft=' . $flashType);
        exit;
    }
}

if (isset($_GET['flash'])) { $flash = $_GET['flash']; $flashType = $_GET['ft'] ?? 'green'; }

// ── Stats ──────────────────────────────────────────────────────────────────
$allPosts        = $_SESSION['posts'] ?? [];
$allApps         = cs_get_employer_applications();
$pendingApps     = array_values(array_filter($allApps, fn($a) => $a['status'] === 'pending'));
$approvedApps    = array_values(array_filter($allApps, fn($a) => $a['status'] === 'approved'));
$totalJobApps    = array_sum(array_map('count', $_SESSION['applications'] ?? []));
$totalAssessments= count($_SESSION['cs_assessments'] ?? []);
$totalPosts      = count($allPosts);
$suspendedUsers  = $_SESSION['admin_suspended_users'] ?? [];

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50">
<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-blue-900">Admin Dashboard</h1>
            <p class="text-gray-500 text-sm mt-1">Full control over CyberSphere</p>
        </div>
        <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider">Admin</span>
    </div>

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold
        <?php echo $flashType === 'red' ? 'bg-red-100 text-red-700' : ($flashType === 'amber' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'); ?>">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-1 bg-white rounded-2xl shadow-sm border border-gray-200 p-1 mb-8 overflow-x-auto">
        <?php foreach (['overview'=>'Overview','employers'=>'Employer Requests','posts'=>'All Posts','users'=>'Users','assessments'=>'Assessments'] as $key=>$label): ?>
        <a href="?tab=<?php echo $key; ?>"
           class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition
           <?php echo $tab === $key ? 'bg-blue-900 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
            <?php echo $label; ?>
            <?php if ($key === 'employers' && count($pendingApps) > 0): ?>
            <span class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5"><?php echo count($pendingApps); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── OVERVIEW TAB ── -->
    <?php if ($tab === 'overview'): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <?php
        $stats = [
            ['Total Posts', $totalPosts, 'bg-blue-50 text-blue-900', '📝'],
            ['Employer Requests', count($allApps), 'bg-purple-50 text-purple-900', '🏢'],
            ['Pending Approvals', count($pendingApps), 'bg-amber-50 text-amber-900', '⏳'],
            ['Job Applications', $totalJobApps, 'bg-green-50 text-green-900', '📄'],
        ];
        foreach ($stats as [$label, $val, $cls, $icon]): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
            <div class="text-2xl mb-2"><?php echo $icon; ?></div>
            <div class="text-3xl font-extrabold <?php echo explode(' ', $cls)[1]; ?>"><?php echo $val; ?></div>
            <div class="text-sm text-gray-500 mt-1"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($pendingApps) > 0): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6">
        <h3 class="font-bold text-amber-800 mb-3">⏳ <?php echo count($pendingApps); ?> Pending Employer Verification<?php echo count($pendingApps) > 1 ? 's' : ''; ?></h3>
        <?php foreach (array_slice($pendingApps, 0, 3) as $a): ?>
        <div class="flex items-center justify-between py-2 border-b border-amber-100 last:border-0">
            <div>
                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($a['company_name']); ?></span>
                <span class="text-gray-500 text-xs ml-2"><?php echo htmlspecialchars($a['email']); ?></span>
            </div>
            <a href="?tab=employers" class="text-blue-700 text-xs font-semibold hover:underline">Review →</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <h3 class="font-bold text-gray-800 mb-4">Recent Posts</h3>
        <?php if (empty($allPosts)): ?>
        <p class="text-gray-400 text-sm">No posts yet.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach (array_slice($allPosts, 0, 5) as $post): ?>
            <?php if (!in_array($post['type'] ?? 'user', ['user','job'])) continue; ?>
            <div class="flex items-start justify-between gap-3 py-2 border-b border-gray-100 last:border-0">
                <div class="min-w-0">
                    <span class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($post['username'] ?? $post['company'] ?? '—'); ?></span>
                    <?php if (!empty($post['tags'])): ?>
                        <?php foreach($post['tags'] as $t): ?><span class="ml-1 text-xs bg-blue-100 text-blue-700 px-1.5 rounded-full"><?php echo htmlspecialchars($t); ?></span><?php endforeach; ?>
                    <?php endif; ?>
                    <p class="text-gray-500 text-xs mt-0.5 truncate"><?php echo htmlspecialchars(mb_strimwidth($post['content'] ?? '', 0, 80, '…')); ?></p>
                </div>
                <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Delete this post?')">
                    <input type="hidden" name="action" value="admin_delete_post">
                    <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id'] ?? ''); ?>">
                    <button class="text-red-500 text-xs font-semibold hover:underline">Delete</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── EMPLOYER REQUESTS TAB ── -->
    <?php elseif ($tab === 'employers'): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900">Employer Verification Requests</h2>
            <span class="text-sm text-gray-500"><?php echo count($allApps); ?> total</span>
        </div>
        <?php if (empty($allApps)): ?>
        <div class="p-8 text-center text-gray-400">
            <div class="text-5xl mb-3">🏢</div>
            <p>No employer requests yet.</p>
            <p class="text-xs mt-1">When companies apply for employer accounts, they'll appear here.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($allApps as $a):
                $statusColors = ['pending'=>'bg-amber-100 text-amber-700','approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700'];
                $sc = $statusColors[$a['status']] ?? 'bg-gray-100 text-gray-600';
            ?>
            <div class="p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($a['company_name']); ?></h3>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $sc; ?>"><?php echo ucfirst($a['status']); ?></span>
                        </div>
                        <p class="text-gray-500 text-sm mt-0.5"><?php echo htmlspecialchars($a['email']); ?> &bull; <?php echo htmlspecialchars($a['industry'] ?? ''); ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Submitted: <?php echo htmlspecialchars($a['submitted_at'] ?? ''); ?></p>
                    </div>
                    <?php if ($a['status'] === 'pending'): ?>
                    <div class="flex gap-2 flex-shrink-0">
                        <form method="POST">
                            <input type="hidden" name="action" value="approve_employer">
                            <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($a['id']); ?>">
                            <button class="bg-green-600 hover:bg-green-700 text-white text-sm font-bold px-4 py-2 rounded-xl transition">✓ Approve</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Reject this employer?')">
                            <input type="hidden" name="action" value="reject_employer">
                            <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($a['id']); ?>">
                            <button class="bg-red-100 hover:bg-red-200 text-red-700 text-sm font-bold px-4 py-2 rounded-xl transition">✗ Reject</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <span class="text-xs text-gray-400">Reviewed <?php echo htmlspecialchars($a['reviewed_at'] ?? ''); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Expandable details -->
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                    <?php
                    $fields = [
                        'Website' => $a['website'] ?? '',
                        'Company Size' => $a['company_size'] ?? '',
                        'Industry' => $a['industry'] ?? '',
                        'Contact Name' => $a['contact_name'] ?? '',
                        'Contact Phone' => $a['contact_phone'] ?? '',
                    ];
                    foreach ($fields as $k => $v): if (!$v) continue; ?>
                    <div class="bg-gray-50 rounded-lg px-3 py-2">
                        <span class="text-gray-500 text-xs"><?php echo $k; ?></span>
                        <p class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($v); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($a['description'])): ?>
                <div class="mt-3 bg-blue-50 rounded-xl p-3 text-sm text-gray-700">
                    <strong class="text-xs uppercase text-gray-500 tracking-wide">Company Description</strong>
                    <p class="mt-1"><?php echo nl2br(htmlspecialchars($a['description'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($a['documents'])): ?>
                <div class="mt-3">
                    <p class="text-xs text-gray-500 font-semibold mb-1">Uploaded Documents</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($a['documents'] as $doc): ?>
                        <a href="<?php echo htmlspecialchars($doc['path']); ?>" target="_blank"
                           class="inline-flex items-center gap-1.5 bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-blue-700 hover:bg-blue-50 transition">
                            📎 <?php echo htmlspecialchars($doc['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── ALL POSTS TAB ── -->
    <?php elseif ($tab === 'posts'): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">All Posts (<?php echo count($allPosts); ?>)</h2>
        </div>
        <?php if (empty($allPosts)): ?>
        <div class="p-8 text-center text-gray-400">No posts yet.</div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($allPosts as $post):
                $isHiring = !empty($post['hiring']) || ($post['type'] ?? '') === 'job';
                $pid = $post['id'] ?? '';
                $apps = count($_SESSION['applications'][$pid] ?? []);
            ?>
            <div class="px-5 py-4 flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($post['username'] ?? $post['company'] ?? '—'); ?></span>
                        <?php if (!empty($post['tags'])): foreach($post['tags'] as $t): ?>
                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 rounded-full"><?php echo htmlspecialchars($t); ?></span>
                        <?php endforeach; endif; ?>
                        <?php if ($isHiring): ?><span class="text-xs bg-purple-100 text-purple-700 px-1.5 rounded-full font-semibold">Hiring</span><?php endif; ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1 line-clamp-2"><?php echo htmlspecialchars(mb_strimwidth($post['content'] ?? '', 0, 120, '…')); ?></p>
                    <p class="text-gray-400 text-xs mt-1"><?php echo htmlspecialchars($post['time'] ?? ''); ?>
                        <?php if ($apps > 0): ?>&bull; <span class="text-green-600 font-semibold"><?php echo $apps; ?> application<?php echo $apps !== 1 ? 's' : ''; ?></span><?php endif; ?>
                    </p>
                </div>
                <?php if ($pid): ?>
                <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Delete this post?')">
                    <input type="hidden" name="action" value="admin_delete_post">
                    <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($pid); ?>">
                    <button class="text-red-500 text-xs font-bold hover:bg-red-50 px-3 py-1.5 rounded-lg transition">Delete</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── USERS TAB ── -->
    <?php elseif ($tab === 'users'): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">Users</h2>
            <p class="text-xs text-gray-400 mt-1">Session-based users. In production, this pulls from the users DB table.</p>
        </div>
        <?php
        // Build a combined user list from session data (real DB users + seeded static)
        $sessionUser = $_SESSION['user'] ?? null;
        $seedUsers = [
            ['id'=>'seed_1','username'=>'NetSentinel Solutions','email'=>'hr@netsentinel.com','role'=>'employer','employer_verified'=>true],
            ['id'=>'seed_2','username'=>'Marcus Vane','email'=>'marcus.vane@example.com','role'=>'applicant','employer_verified'=>false],
            ['id'=>'seed_3','username'=>'SecureBank Hiring','email'=>'securebank@example.com','role'=>'employer','employer_verified'=>true],
        ];
        $displayUsers = $seedUsers;
        if ($sessionUser && !in_array($sessionUser['email'], array_column($seedUsers, 'email'))) {
            array_unshift($displayUsers, $sessionUser);
        }
        ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($displayUsers as $u):
                $uid = $u['id'] ?? $u['email'] ?? '';
                $isSuspended = in_array($uid, $suspendedUsers);
                $role = $u['role'] ?? 'applicant';
                $verified = !empty($u['employer_verified']);
            ?>
            <div class="px-5 py-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-sm flex-shrink-0 overflow-hidden">
                        <?php if (!empty($u['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($u['profile_pic']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                        <?php echo strtoupper(substr($u['username'] ?? '?', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($u['username'] ?? '—'); ?></span>
                            <?php echo cs_role_badge($role, $verified); ?>
                            <?php if ($isSuspended): ?><span class="text-xs bg-red-100 text-red-600 font-semibold px-2 py-0.5 rounded-full">Suspended</span><?php endif; ?>
                        </div>
                        <p class="text-gray-400 text-xs truncate"><?php echo htmlspecialchars($u['email'] ?? ''); ?></p>
                    </div>
                </div>
                <?php if (($u['email'] ?? '') !== ($_SESSION['user']['email'] ?? '')): ?>
                <form method="POST" class="flex-shrink-0">
                    <input type="hidden" name="action" value="toggle_suspend_user">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($uid); ?>">
                    <button class="text-xs font-bold px-3 py-1.5 rounded-lg transition
                        <?php echo $isSuspended ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-600 hover:bg-red-200'; ?>">
                        <?php echo $isSuspended ? 'Restore' : 'Suspend'; ?>
                    </button>
                </form>
                <?php else: ?>
                <span class="text-xs text-gray-400 italic">You</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── ASSESSMENTS TAB ── -->
    <?php elseif ($tab === 'assessments'): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">All Assessments</h2>
        </div>
        <?php $allAssessments = $_SESSION['cs_assessments'] ?? []; ?>
        <?php if (empty($allAssessments)): ?>
        <div class="p-8 text-center text-gray-400">
            <div class="text-5xl mb-3">📋</div>
            <p>No assessments created yet.</p>
            <p class="text-xs mt-1">Employers can create skill assessments from their dashboard.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($allAssessments as $a): ?>
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($a['title']); ?></h3>
                        <p class="text-sm text-gray-500">By <?php echo htmlspecialchars($a['employer_name'] ?? $a['employer_email']); ?> &bull; <?php echo count($a['challenges'] ?? []); ?> challenges &bull; <?php echo $a['time_limit']; ?> min</p>
                        <p class="text-xs text-gray-400 mt-0.5">Created <?php echo htmlspecialchars($a['created_at']); ?></p>
                    </div>
                    <span class="text-xs bg-blue-100 text-blue-700 font-semibold px-2 py-0.5 rounded-full"><?php echo htmlspecialchars($a['role'] ?? 'General'); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<?php include 'includes/footer.php'; ?>
