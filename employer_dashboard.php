<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';
cs_require_auth();
cs_init_assessments();

// Auto-upgrade session role if file store shows approval
if (isset($_SESSION['user']) && !cs_is_employer() && !cs_is_admin()) {
    $myApp = cs_get_employer_application_by_email($_SESSION['user']['email']);
    if ($myApp && $myApp['status'] === 'approved') {
        $_SESSION['user']['role']              = 'employer';
        $_SESSION['user']['employer_verified'] = true;
        $_SESSION['user']['company_name']      = $myApp['company_name'] ?? $_SESSION['user']['username'];
    }
}

if (!cs_is_employer() && !cs_is_admin()) {
    $_SESSION['flash_error'] = 'Your employer account is not yet verified.';
    header('Location: employer_apply.php'); exit;
}

$pageTitle   = 'Employer Dashboard - CyberSphere';
$currentPage = 'employer_dashboard';
$tab         = $_GET['tab'] ?? 'applicants';
$flash       = '';
$flashType   = 'green';
$myEmail     = $_SESSION['user']['email'] ?? '';
$companyName = $_SESSION['user']['company_name'] ?? $_SESSION['user']['username'] ?? 'Your Company';

// ── Handle Create Assessment POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_assessment') {
    $title      = trim($_POST['assess_title'] ?? '');
    $role       = trim($_POST['assess_role'] ?? '');
    $timeLimit  = max(5, min(180, (int)($_POST['assess_time'] ?? 30)));
    $instructions = trim($_POST['assess_instructions'] ?? '');

    $challengesRaw = $_POST['challenges'] ?? [];
    $challenges = [];
    foreach ($challengesRaw as $c) {
        $ctype  = trim($c['type'] ?? 'flag');
        $ctitle = trim($c['title'] ?? '');
        $cbody  = trim($c['body'] ?? '');
        $cpts   = max(10, min(500, (int)($c['points'] ?? 100)));
        $chint  = trim($c['hint'] ?? '');
        $cflag  = trim($c['correct_flag'] ?? '');
        if (!$ctitle || !$cbody) continue;
        $challenges[] = [
            'id'           => 'c_' . uniqid('', true),
            'type'         => in_array($ctype, ['flag','code','short']) ? $ctype : 'flag',
            'title'        => $ctitle,
            'body'         => $cbody,
            'points'       => $cpts,
            'hint'         => $chint,
            'correct_flag' => $cflag,
            'attachment'   => null,
        ];
    }

    $assessErrors = [];
    if (!$title) $assessErrors[] = 'Assessment title is required.';
    if (empty($challenges)) $assessErrors[] = 'Add at least one challenge.';

    if (empty($assessErrors)) {
        $assessId = 'assess_' . uniqid('', true);
        $_SESSION['cs_assessments'][] = [
            'id'            => $assessId,
            'employer_email'=> $myEmail,
            'employer_name' => $companyName,
            'title'         => $title,
            'role'          => $role,
            'time_limit'    => $timeLimit,
            'instructions'  => $instructions,
            'challenges'    => $challenges,
            'created_at'    => date('M j, Y g:i A'),
            'total_pts'     => array_sum(array_column($challenges, 'points')),
        ];
        $flash = 'Assessment "' . htmlspecialchars($title) . '" created successfully!';
        header('Location: employer_dashboard.php?tab=assessments&flash=' . urlencode($flash));
        exit;
    }
}

// ── Handle Send Assessment via message ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_assessment_msg') {
    $assessId    = $_POST['assess_id'] ?? '';
    $recipEmail  = trim($_POST['recipient_email'] ?? '');
    $customMsg   = trim($_POST['custom_message'] ?? '');
    $assessment  = cs_get_assessment_by_id($assessId);

    if ($assessment && $recipEmail) {
        $link    = 'assessment_session.php?session=' . urlencode($assessId);
        $msgText = ($customMsg ? $customMsg . "\n\n" : '')
                 . '📋 Skill Assessment: ' . $assessment['title'] . "\n"
                 . 'Role: ' . ($assessment['role'] ?: 'General') . "\n"
                 . 'Time Limit: ' . $assessment['time_limit'] . " minutes\n"
                 . 'Start here: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . '/' . $link : $link);

        // Push into conversations
        if (!isset($_SESSION['messages'])) $_SESSION['messages'] = [];
        // Find or create a conversation with the recipient
        $convId = null;
        foreach ($_SESSION['messages'] as $conv) {
            $parts = $conv['participants'] ?? [];
            if (in_array($myEmail, $parts) && in_array($recipEmail, $parts)) {
                $convId = $conv['id'];
                break;
            }
        }
        if (!$convId) {
            $convId = 'conv_' . uniqid('', true);
            $_SESSION['messages'][] = [
                'id'           => $convId,
                'participants' => [$myEmail, $recipEmail],
                'messages'     => [],
            ];
        }
        foreach ($_SESSION['messages'] as &$conv) {
            if ($conv['id'] === $convId) {
                $conv['messages'][] = [
                    'id'   => 'msg_' . uniqid('', true),
                    'from' => $myEmail,
                    'text' => $msgText,
                    'time' => date('g:i A'),
                    'ts'   => time(),
                ];
                break;
            }
        }
        unset($conv);

        // Notify recipient
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
            $stmt->execute([$recipEmail]);
            $recip_user_id = $stmt->fetchColumn();
            if ($recip_user_id) {
                cs_save_notification($recip_user_id, $companyName . ' sent you a skill assessment: ' . $assessment['title'], 'messages.php');
            } else {
                // Fall back to old session method if user not found in DB
                array_unshift($_SESSION['notifications'], [
                    'msg'  => $companyName . ' sent you a skill assessment: ' . $assessment['title'],
                    'time' => date('M j, Y g:i A'),
                    'read' => false,
                    'link' => 'messages.php',
                ]);
            }
        } catch (Exception $e) {
            // Fall back to old method
            array_unshift($_SESSION['notifications'], [
                'msg'  => $companyName . ' sent you a skill assessment: ' . $assessment['title'],
                'time' => date('M j, Y g:i A'),
                'read' => false,
                'link' => 'messages.php',
            ]);
        }

        $flash = 'Assessment link sent to ' . htmlspecialchars($recipEmail) . ' via messages.';
        header('Location: employer_dashboard.php?tab=assessments&flash=' . urlencode($flash));
        exit;
    }
}

if (isset($_GET['flash'])) { $flash = $_GET['flash']; $flashType = $_GET['ft'] ?? 'green'; }

// ── Data ───────────────────────────────────────────────────────────────────
$myApplications  = cs_get_all_employer_post_applications($myEmail);
$myAssessments   = cs_get_assessments_by_employer($myEmail);
$myPosts         = array_values(array_filter($_SESSION['posts'] ?? [], fn($p) => ($p['email'] ?? '') === $myEmail && !empty($p['hiring'])));

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 pb-12">
<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-blue-900"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-gray-500 text-sm mt-1">Employer Dashboard</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="bg-purple-100 text-purple-700 text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider">Employer</span>
            <a href="index.php" class="text-sm text-blue-700 font-semibold hover:underline">← Feed</a>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold
        <?php echo $flashType === 'red' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <?php
        $stats = [
            ['Hiring Posts', count($myPosts), '📝'],
            ['Total Applicants', count($myApplications), '👥'],
            ['Assessments', count($myAssessments), '📋'],
            ['New This Week', count(array_filter($myApplications, fn($a) => (strtotime($a['time'] ?? '0') > strtotime('-7 days')))), '🔔'],
        ];
        foreach ($stats as [$label, $val, $icon]): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm text-center">
            <div class="text-2xl mb-1"><?php echo $icon; ?></div>
            <div class="text-3xl font-extrabold text-blue-900"><?php echo $val; ?></div>
            <div class="text-xs text-gray-500 mt-1"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-white rounded-2xl shadow-sm border border-gray-200 p-1 mb-8 overflow-x-auto">
        <?php foreach (['applicants'=>'Applicants','assessments'=>'Assessments','create_assessment'=>'+ New Assessment'] as $key=>$label): ?>
        <a href="?tab=<?php echo $key; ?>"
           class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition
           <?php echo $tab === $key ? 'bg-blue-900 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
            <?php echo $label; ?>
            <?php if ($key === 'applicants' && count($myApplications) > 0): ?>
            <span class="ml-1 bg-blue-700 text-white text-xs rounded-full px-1.5 py-0.5"><?php echo count($myApplications); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── APPLICANTS TAB ── -->
    <?php if ($tab === 'applicants'): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900">All Applicants (<?php echo count($myApplications); ?>)</h2>
        </div>
        <?php if (empty($myApplications)): ?>
        <div class="p-12 text-center text-gray-400">
            <div class="text-5xl mb-3">👥</div>
            <p class="font-medium">No applicants yet.</p>
            <p class="text-xs mt-1">Post a hiring job on the feed and enable the "Apply" button to start receiving applications.</p>
            <a href="index.php" class="inline-block mt-4 bg-blue-900 text-white font-bold px-5 py-2.5 rounded-xl text-sm hover:bg-blue-800 transition">Go to Feed</a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="text-left px-5 py-3">Applicant</th>
                        <th class="text-left px-4 py-3">Post</th>
                        <th class="text-left px-4 py-3">Applied</th>
                        <th class="text-left px-4 py-3">Resume</th>
                        <th class="text-center px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($myApplications as $app): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-sm flex-shrink-0">
                                    <?php echo strtoupper(substr($app['name'] ?? '?', 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($app['name'] ?? '—'); ?></p>
                                    <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($app['email'] ?? ''); ?></p>
                                    <?php if (!empty($app['phone'])): ?>
                                    <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($app['phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-gray-600 text-xs max-w-[160px] truncate"><?php echo htmlspecialchars($app['post_content'] ?? '—'); ?></p>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($app['time'] ?? '—'); ?></p>
                        </td>
                        <td class="px-4 py-4">
                            <?php if (!empty($app['resume'])): ?>
                            <a href="<?php echo htmlspecialchars($app['resume']); ?>" target="_blank"
                               class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                                📎 View Resume
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400 text-xs">No resume</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <button
                                class="send-assess-btn bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold px-3 py-1.5 rounded-xl transition"
                                data-name="<?php echo htmlspecialchars($app['name'] ?? ''); ?>"
                                data-email="<?php echo htmlspecialchars($app['email'] ?? ''); ?>">
                                Send Assessment
                            </button>
                        </td>
                    </tr>
                    <?php if (!empty($app['message'])): ?>
                    <tr class="bg-gray-50">
                        <td colspan="5" class="px-5 py-3">
                            <p class="text-xs text-gray-600"><strong class="text-gray-700">Cover message:</strong> <?php echo nl2br(htmlspecialchars($app['message'])); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── ASSESSMENTS TAB ── -->
    <?php elseif ($tab === 'assessments'): ?>
    <div class="space-y-4">
        <?php if (empty($myAssessments)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-400 shadow-sm">
            <div class="text-5xl mb-3">📋</div>
            <p class="font-medium">No assessments yet.</p>
            <a href="?tab=create_assessment" class="inline-block mt-4 bg-blue-900 text-white font-bold px-5 py-2.5 rounded-xl text-sm hover:bg-blue-800 transition">Create Your First Assessment</a>
        </div>
        <?php else: ?>
        <?php foreach ($myAssessments as $a): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <h3 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($a['title']); ?></h3>
                    <p class="text-gray-500 text-sm mt-0.5">
                        <?php if ($a['role']): ?><span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full mr-2"><?php echo htmlspecialchars($a['role']); ?></span><?php endif; ?>
                        <?php echo count($a['challenges']); ?> challenges &bull; <?php echo $a['time_limit']; ?> min &bull; <?php echo $a['total_pts']; ?> pts total
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Created <?php echo htmlspecialchars($a['created_at']); ?></p>
                </div>
                <div class="flex flex-wrap gap-2 flex-shrink-0">
                    <a href="assessment_session.php?session=<?php echo urlencode($a['id']); ?>" target="_blank"
                       class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-2 rounded-xl transition">Preview</a>
                    <a href="assessment_dashboard.php?assess_id=<?php echo urlencode($a['id']); ?>"
                       class="text-xs bg-blue-900 hover:bg-blue-800 text-white font-semibold px-3 py-2 rounded-xl transition">View Results</a>
                    <button class="send-assess-btn text-xs bg-purple-700 hover:bg-purple-800 text-white font-semibold px-3 py-2 rounded-xl transition"
                            data-assess-id="<?php echo htmlspecialchars($a['id']); ?>"
                            data-assess-title="<?php echo htmlspecialchars($a['title']); ?>">
                        Send to Applicant
                    </button>
                </div>
            </div>

            <!-- Challenge list -->
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($a['challenges'] as $ch): ?>
                <div class="bg-gray-50 rounded-xl p-3 text-xs">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($ch['title']); ?></span>
                        <span class="bg-blue-100 text-blue-700 font-bold px-1.5 rounded-full ml-2 flex-shrink-0"><?php echo $ch['points']; ?>pts</span>
                    </div>
                    <span class="<?php echo ['flag'=>'bg-orange-100 text-orange-700','code'=>'bg-green-100 text-green-700','short'=>'bg-gray-200 text-gray-600'][$ch['type']] ?? 'bg-gray-100 text-gray-600'; ?> px-1.5 py-0.5 rounded-full font-semibold"><?php echo ucfirst($ch['type']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── CREATE ASSESSMENT TAB ── -->
    <?php elseif ($tab === 'create_assessment'): ?>
    <?php if (!empty($assessErrors ?? [])): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($assessErrors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Create Skill Assessment</h2>
        <p class="text-gray-500 text-sm mb-6">Build a custom assessment to send to applicants. You can add flag-style, coding, or short-answer challenges.</p>

        <form method="POST" id="assessForm" class="space-y-6">
            <input type="hidden" name="action" value="create_assessment">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Assessment Title <span class="text-red-500">*</span></label>
                    <input name="assess_title" required maxlength="100" placeholder="e.g. SOC Analyst Skill Assessment"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Role / Position</label>
                    <input name="assess_role" maxlength="80" placeholder="e.g. SOC Analyst, Penetration Tester"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Time Limit (minutes)</label>
                    <input name="assess_time" type="number" min="5" max="180" value="30"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Instructions</label>
                    <input name="assess_instructions" maxlength="300" placeholder="Optional instructions for applicants…"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <!-- Challenges builder -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-gray-800">Challenges <span class="text-red-500">*</span></h3>
                    <button type="button" id="addChallengeBtn"
                            class="bg-blue-100 hover:bg-blue-200 text-blue-900 text-xs font-bold px-4 py-2 rounded-xl transition">
                        + Add Challenge
                    </button>
                </div>
                <div id="challengesList" class="space-y-4">
                    <!-- Injected by JS -->
                </div>
                <p id="noChallengesMsg" class="text-gray-400 text-sm py-4 text-center border-2 border-dashed border-gray-200 rounded-xl">
                    Click "Add Challenge" to build your assessment.
                </p>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit" class="flex-1 bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                    Create Assessment
                </button>
                <a href="?tab=assessments" class="px-5 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition text-sm flex items-center">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Send Assessment Modal -->
<div id="sendAssessModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="font-bold text-gray-900">Send Assessment</h3>
            <button onclick="closeSendModal()" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="send_assessment_msg">
            <input type="hidden" id="sendAssessId" name="assess_id" value="">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Assessment</label>
                <p id="sendAssessTitle" class="text-blue-900 font-bold text-sm"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Recipient Email <span class="text-red-500">*</span></label>
                <input type="email" name="recipient_email" id="sendAssessEmail" required
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm" placeholder="applicant@email.com">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Personal Message (optional)</label>
                <textarea name="custom_message" rows="3"
                          class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm resize-none"
                          placeholder="Hi [Name], we'd like you to complete this assessment…"></textarea>
            </div>
            <button type="submit" class="w-full bg-purple-700 hover:bg-purple-800 text-white font-bold py-3 rounded-xl transition">
                Send via Messages
            </button>
        </form>
    </div>
</div>

<script>
// ── Send Assessment Modal ─────────────────────────────────────────────────
const sendModal = document.getElementById('sendAssessModal');
const sendAssessId    = document.getElementById('sendAssessId');
const sendAssessTitle = document.getElementById('sendAssessTitle');
const sendAssessEmail = document.getElementById('sendAssessEmail');

<?php
// Build an assessment ID→title map for JS
$assessMap = [];
foreach ($myAssessments as $a) { $assessMap[$a['id']] = $a['title']; }
?>
const assessTitles = <?php echo json_encode($assessMap); ?>;

document.addEventListener('click', e => {
    const btn = e.target.closest('.send-assess-btn');
    if (!btn) return;
    const id    = btn.dataset.assessId  || '';
    const title = btn.dataset.assessTitle || (id ? (assessTitles[id] || '') : '');
    const email = btn.dataset.email || '';
    const name  = btn.dataset.name  || '';

    // If no assess id given (coming from applicant row), pick first available
    const firstId = id || Object.keys(assessTitles)[0] || '';
    sendAssessId.value    = firstId;
    sendAssessTitle.textContent = title || (firstId ? (assessTitles[firstId] || 'Assessment') : 'No assessments yet — create one first.');
    sendAssessEmail.value = email;

    <?php if (empty($myAssessments)): ?>
    alert('Create an assessment first in the "Assessments" tab.');
    return;
    <?php endif; ?>

    sendModal.classList.remove('hidden');
    sendModal.classList.add('flex');
});

function closeSendModal() {
    sendModal.classList.add('hidden');
    sendModal.classList.remove('flex');
}
sendModal.addEventListener('click', e => { if (e.target === sendModal) closeSendModal(); });

// ── Challenge Builder ─────────────────────────────────────────────────────
let challengeCount = 0;
const challengesList = document.getElementById('challengesList');
const noChallengesMsg = document.getElementById('noChallengesMsg');
const addBtn = document.getElementById('addChallengeBtn');

if (addBtn) {
    addBtn.addEventListener('click', addChallenge);
}

function addChallenge() {
    noChallengesMsg?.classList.add('hidden');
    const i = challengeCount++;
    const card = document.createElement('div');
    card.className = 'bg-gray-50 border border-gray-200 rounded-2xl p-5 relative';
    card.innerHTML = `
        <button type="button" onclick="this.closest('.bg-gray-50').remove(); checkEmpty();"
                class="absolute top-3 right-3 text-red-400 hover:text-red-600 font-bold text-lg leading-none">&times;</button>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Challenge Title *</label>
                <input name="challenges[${i}][title]" required maxlength="120" placeholder="e.g. Subnet Calculation"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Type</label>
                    <select name="challenges[${i}][type]" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 challenge-type-sel">
                        <option value="flag">Flag (exact answer)</option>
                        <option value="code">Code (open-ended)</option>
                        <option value="short">Short Answer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Points</label>
                    <input name="challenges[${i}][points]" type="number" min="10" max="500" value="100"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Challenge Description *</label>
            <textarea name="challenges[${i}][body]" rows="3" required placeholder="Describe the challenge…"
                      class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flag-field-wrap">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Correct Flag / Answer</label>
                <input name="challenges[${i}][correct_flag]" placeholder="FLAG{answer} or exact answer"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">Leave empty for code/short answers (manual review).</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Hint (optional)</label>
                <input name="challenges[${i}][hint]" maxlength="200" placeholder="Optional hint for applicants"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    `;

    challengesList.appendChild(card);

    // Show/hide flag field based on type
    const sel = card.querySelector('.challenge-type-sel');
    const flagWrap = card.querySelector('.flag-field-wrap');
    sel.addEventListener('change', () => {
        flagWrap.style.opacity = (sel.value === 'flag') ? '1' : '0.4';
    });
}

function checkEmpty() {
    if (challengesList.children.length === 0) {
        noChallengesMsg?.classList.remove('hidden');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
