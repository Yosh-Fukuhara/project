<?php
require_once 'includes/bootstrap.php';

// ── Static simulation data ────────────────────────────────────────────────
$session = [
    'id'        => 'sess_netsentinel_001',
    'title'     => 'SOC Analyst Skill Assessment',
    'company'   => 'NetSentinel Solutions',
    'role'      => 'SOC Analyst',
    'posted_by' => 'hr@netsentinel.com',
    'created'   => 'May 28, 2026',
    'deadline'  => 'Jun 5, 2026',
    'total_pts' => 650,
    'challenges'=> 5,
];

// Simulated applicant results
$applicants = [
    [
        'id'           => 'app_001',
        'name'         => 'John Doe',
        'email'        => 'john@example.com',
        'avatar'       => null,
        'applied'      => 'May 27, 2026',
        'completed_at' => 'May 29, 2026 10:14 AM',
        'time_used'    => '22:37', // mm:ss
        'time_secs'    => 1357,
        'score'        => 525,
        'solved'       => 4,
        'challenge_results' => [
            ['id'=>'c1','title'=>'Network Recon',      'pts'=>100,'earned'=>100,'time'=>'04:12','status'=>'correct'],
            ['id'=>'c2','title'=>'Subnet Calculation', 'pts'=>150,'earned'=>150,'time'=>'06:05','status'=>'correct'],
            ['id'=>'c3','title'=>'Log Parser',         'pts'=>200,'earned'=>200,'time'=>'08:44','status'=>'submitted'],
            ['id'=>'c4','title'=>'SIEM Alert Triage',  'pts'=>125,'earned'=>0,  'time'=>'—',    'status'=>'wrong'],
            ['id'=>'c5','title'=>'Incident Response',  'pts'=>75, 'earned'=>75, 'time'=>'03:36','status'=>'submitted'],
        ],
        'status' => 'completed',
        'notes'  => '',
    ],
    [
        'id'           => 'app_002',
        'name'         => 'Ava Reyes',
        'email'        => 'ava.reyes@example.com',
        'avatar'       => null,
        'applied'      => 'May 27, 2026',
        'completed_at' => 'May 29, 2026 11:02 AM',
        'time_used'    => '31:18',
        'time_secs'    => 1878,
        'score'        => 450,
        'solved'       => 3,
        'challenge_results' => [
            ['id'=>'c1','title'=>'Network Recon',      'pts'=>100,'earned'=>100,'time'=>'07:30','status'=>'correct'],
            ['id'=>'c2','title'=>'Subnet Calculation', 'pts'=>150,'earned'=>150,'time'=>'09:14','status'=>'correct'],
            ['id'=>'c3','title'=>'Log Parser',         'pts'=>200,'earned'=>200,'time'=>'14:34','status'=>'submitted'],
            ['id'=>'c4','title'=>'SIEM Alert Triage',  'pts'=>125,'earned'=>0,  'time'=>'—',    'status'=>'wrong'],
            ['id'=>'c5','title'=>'Incident Response',  'pts'=>75, 'earned'=>0,  'time'=>'—',    'status'=>'skipped'],
        ],
        'status' => 'completed',
        'notes'  => '',
    ],
    [
        'id'           => 'app_003',
        'name'         => 'Luis Tan',
        'email'        => 'luis.tan@example.com',
        'avatar'       => null,
        'applied'      => 'May 28, 2026',
        'completed_at' => 'May 29, 2026 02:45 PM',
        'time_used'    => '38:51',
        'time_secs'    => 2331,
        'score'        => 375,
        'solved'       => 3,
        'challenge_results' => [
            ['id'=>'c1','title'=>'Network Recon',      'pts'=>100,'earned'=>0,  'time'=>'—',    'status'=>'wrong'],
            ['id'=>'c2','title'=>'Subnet Calculation', 'pts'=>150,'earned'=>150,'time'=>'12:20','status'=>'correct'],
            ['id'=>'c3','title'=>'Log Parser',         'pts'=>200,'earned'=>200,'time'=>'18:05','status'=>'submitted'],
            ['id'=>'c4','title'=>'SIEM Alert Triage',  'pts'=>125,'earned'=>0,  'time'=>'—',    'status'=>'wrong'],
            ['id'=>'c5','title'=>'Incident Response',  'pts'=>75, 'earned'=>75, 'time'=>'08:26','status'=>'submitted'],
        ],
        'status' => 'completed',
        'notes'  => '',
    ],
    [
        'id'           => 'app_004',
        'name'         => 'Sofia Mendoza',
        'email'        => 'sofia.m@example.com',
        'avatar'       => null,
        'applied'      => 'May 28, 2026',
        'completed_at' => null,
        'time_used'    => null,
        'time_secs'    => null,
        'score'        => null,
        'solved'       => null,
        'challenge_results' => [],
        'status' => 'invited',
        'notes'  => '',
    ],
    [
        'id'           => 'app_005',
        'name'         => 'Marco Dela Cruz',
        'email'        => 'marco.dc@example.com',
        'avatar'       => null,
        'applied'      => 'May 28, 2026',
        'completed_at' => null,
        'time_used'    => null,
        'time_secs'    => null,
        'score'        => null,
        'solved'       => null,
        'challenge_results' => [],
        'status' => 'pending',
        'notes'  => '',
    ],
];

// Sort completed applicants by score desc, then time asc
usort($applicants, function($a, $b) {
    if ($a['status'] !== 'completed' && $b['status'] !== 'completed') return 0;
    if ($a['status'] !== 'completed') return 1;
    if ($b['status'] !== 'completed') return -1;
    if ($b['score'] !== $a['score']) return $b['score'] - $a['score'];
    return $a['time_secs'] - $b['time_secs'];
});

$completed  = array_filter($applicants, fn($a) => $a['status'] === 'completed');
$avgScore   = count($completed) ? round(array_sum(array_column(iterator_to_array($completed), 'score')) / count($completed)) : 0;
$topScore   = count($completed) ? max(array_column(iterator_to_array($completed), 'score')) : 0;

$pageTitle   = 'Assessment Dashboard - CyberSphere';
$currentPage = 'assessment';
?>
<?php include 'includes/header.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Syne:wght@400;600;700;800&display=swap');

.dash-header { font-family: 'Syne', sans-serif; }
.dash-mono   { font-family: 'JetBrains Mono', monospace; }

.rank-1 { background: linear-gradient(135deg,#fef9c3,#fde68a); border-color: #f59e0b; }
.rank-2 { background: linear-gradient(135deg,#f1f5f9,#e2e8f0); border-color: #94a3b8; }
.rank-3 { background: linear-gradient(135deg,#fff7ed,#fed7aa); border-color: #f97316; }

.score-bar-fill {
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(90deg, #1e3a8a, #3b82f6);
    transition: width .8s cubic-bezier(.4,0,.2,1);
}

.status-completed { background:#dcfce7; color:#16a34a; }
.status-invited   { background:#dbeafe; color:#1d4ed8; }
.status-pending   { background:#f3f4f6; color:#6b7280; }

.challenge-dot-correct  { background:#22c55e; }
.challenge-dot-submitted{ background:#3b82f6; }
.challenge-dot-wrong    { background:#ef4444; }
.challenge-dot-skipped  { background:#d1d5db; }

@keyframes slide-in {
    from { opacity:0; transform:translateY(12px); }
    to   { opacity:1; transform:translateY(0); }
}
.row-animate { animation: slide-in .4s ease both; }

.medal-1::before { content:'🥇'; }
.medal-2::before { content:'🥈'; }
.medal-3::before { content:'🥉'; }
</style>

<div class="min-h-screen bg-gray-50 pb-12">

    <!-- ── Page header ── -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 py-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <a href="index.php" class="hover:text-blue-800">Home</a>
                        <span>/</span>
                        <span>Assessment Dashboard</span>
                    </div>
                    <h1 class="dash-header text-2xl font-bold text-blue-900"><?php echo htmlspecialchars($session['title']); ?></h1>
                    <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <?php echo htmlspecialchars($session['company']); ?>
                        </span>
                        <span>•</span>
                        <span><?php echo htmlspecialchars($session['role']); ?></span>
                        <span>•</span>
                        <span><?php echo $session['challenges']; ?> challenges</span>
                        <span>•</span>
                        <span><?php echo $session['total_pts']; ?> pts total</span>
                    </div>
                </div>
                <div class="flex gap-2 flex-shrink-0">
                    <a href="assessment_session.php"
                       target="_blank"
                       class="flex items-center gap-2 border border-gray-300 text-gray-700 text-sm font-semibold px-4 py-2 rounded-xl hover:bg-gray-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Preview Assessment
                    </a>
                    <button onclick="copyLink()" class="flex items-center gap-2 bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span id="copyBtnLabel">Copy Link</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

        <!-- ── Summary cards ── -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            $stats = [
                ['label'=>'Applicants',  'value'=> count($applicants),  'icon'=>'👥', 'color'=>'blue'],
                ['label'=>'Completed',   'value'=> count($completed),    'icon'=>'✅', 'color'=>'green'],
                ['label'=>'Avg Score',   'value'=> $avgScore . ' pts',   'icon'=>'📊', 'color'=>'purple'],
                ['label'=>'Top Score',   'value'=> $topScore . ' pts',   'icon'=>'🏆', 'color'=>'amber'],
            ];
            $colors = ['blue'=>'bg-blue-50 border-blue-100','green'=>'bg-green-50 border-green-100','purple'=>'bg-purple-50 border-purple-100','amber'=>'bg-amber-50 border-amber-100'];
            $textColors = ['blue'=>'text-blue-900','green'=>'text-green-700','purple'=>'text-purple-700','amber'=>'text-amber-700'];
            foreach ($stats as $s):
            ?>
            <div class="<?php echo $colors[$s['color']]; ?> border rounded-2xl p-4 text-center">
                <div class="text-2xl mb-1"><?php echo $s['icon']; ?></div>
                <p class="dash-mono text-xl font-bold <?php echo $textColors[$s['color']]; ?>"><?php echo $s['value']; ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?php echo $s['label']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Leaderboard ── -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="dash-header font-bold text-gray-900 text-lg">Leaderboard</h2>
                <div class="flex gap-2 text-xs">
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full challenge-dot-correct inline-block"></span> Correct</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full challenge-dot-submitted inline-block"></span> Submitted</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full challenge-dot-wrong inline-block"></span> Wrong</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full challenge-dot-skipped inline-block"></span> Skipped</span>
                </div>
            </div>

            <!-- Table header -->
            <div class="grid grid-cols-12 gap-2 px-6 py-2 bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <div class="col-span-1 text-center">Rank</div>
                <div class="col-span-3">Applicant</div>
                <div class="col-span-2 text-center">Score</div>
                <div class="col-span-2 text-center">Time Used</div>
                <div class="col-span-2 text-center">Challenges</div>
                <div class="col-span-2 text-center">Action</div>
            </div>

            <?php
            $rank = 0;
            foreach ($applicants as $i => $app):
                $isCompleted = $app['status'] === 'completed';
                if ($isCompleted) $rank++;
                $pct = $isCompleted ? round(($app['score'] / $session['total_pts']) * 100) : 0;
                $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : ''));
                $medalClass = $rank === 1 ? 'medal-1' : ($rank === 2 ? 'medal-2' : ($rank === 3 ? 'medal-3' : ''));
                $delay = $i * 60;
            ?>
            <div class="grid grid-cols-12 gap-2 px-6 py-4 border-b border-gray-100 items-center hover:bg-gray-50/60 transition row-animate <?php echo $rankClass; ?>"
                 style="animation-delay:<?php echo $delay; ?>ms">

                <!-- Rank -->
                <div class="col-span-1 text-center">
                    <?php if ($isCompleted && $rank <= 3): ?>
                        <span class="text-xl <?php echo $medalClass; ?>"></span>
                    <?php elseif ($isCompleted): ?>
                        <span class="dash-mono font-bold text-gray-500 text-sm">#<?php echo $rank; ?></span>
                    <?php else: ?>
                        <span class="text-gray-300 text-lg">—</span>
                    <?php endif; ?>
                </div>

                <!-- Applicant -->
                <div class="col-span-3 flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-900 font-bold text-sm flex items-center justify-center flex-shrink-0">
                        <?php echo strtoupper(substr($app['name'], 0, 1)); ?>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($app['name']); ?></p>
                        <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($app['email']); ?></p>
                    </div>
                </div>

                <!-- Score -->
                <div class="col-span-2 text-center">
                    <?php if ($isCompleted): ?>
                        <p class="dash-mono font-bold text-blue-900 text-sm"><?php echo $app['score']; ?> <span class="text-gray-400 font-normal">/ <?php echo $session['total_pts']; ?></span></p>
                        <div class="w-full bg-gray-200 rounded-full mt-1.5 h-1.5">
                            <div class="score-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5"><?php echo $pct; ?>%</p>
                    <?php else: ?>
                        <span class="text-gray-300 text-sm">—</span>
                    <?php endif; ?>
                </div>

                <!-- Time -->
                <div class="col-span-2 text-center">
                    <?php if ($isCompleted): ?>
                        <p class="dash-mono font-semibold text-gray-800 text-sm"><?php echo $app['time_used']; ?></p>
                        <p class="text-xs text-gray-400">of 45:00</p>
                    <?php else: ?>
                        <span class="text-xs px-2.5 py-1 rounded-full font-semibold status-<?php echo $app['status']; ?>">
                            <?php echo ucfirst($app['status']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Challenge dots -->
                <div class="col-span-2 flex items-center justify-center gap-1.5 flex-wrap">
                    <?php if ($isCompleted): ?>
                        <?php foreach ($app['challenge_results'] as $cr): ?>
                        <div class="relative group">
                            <span class="w-3 h-3 rounded-full inline-block challenge-dot-<?php echo $cr['status']; ?>"></span>
                            <!-- Tooltip -->
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block z-10 pointer-events-none">
                                <div class="bg-gray-900 text-white text-xs rounded-lg px-2.5 py-1.5 whitespace-nowrap shadow-lg">
                                    <p class="font-semibold"><?php echo htmlspecialchars($cr['title']); ?></p>
                                    <p class="text-gray-300"><?php echo $cr['earned']; ?>/<?php echo $cr['pts']; ?> pts • <?php echo $cr['time']; ?></p>
                                </div>
                                <div class="w-2 h-2 bg-gray-900 rotate-45 mx-auto -mt-1"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-gray-300 text-xs">Not started</span>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="col-span-2 flex items-center justify-center gap-2">
                    <?php if ($isCompleted): ?>
                        <button onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($app)); ?>)"
                                class="text-xs font-semibold text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition">
                            Details
                        </button>
                        <a href="messages.php" class="text-xs font-semibold text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">
                            Message
                        </a>
                    <?php elseif ($app['status'] === 'pending'): ?>
                        <button onclick="openSendAssessmentModal(<?php echo htmlspecialchars(json_encode(['name'=>$app['name'],'email'=>$app['email'],'conv'=>'conv1'])); ?>)"
                                class="text-xs font-semibold text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition">
                            Send Link
                        </button>
                    <?php else: ?>
                        <span class="text-xs text-gray-400 italic">Awaiting...</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Challenge breakdown ── -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="dash-header font-bold text-gray-900 text-lg">Challenge Breakdown</h2>
                <p class="text-sm text-gray-500 mt-0.5">How each applicant performed per challenge</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-6 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Applicant</th>
                            <?php
                            $challengeTitles = ['Network Recon','Subnet Calc','Log Parser','SIEM Triage','IR Response'];
                            $challengePts    = [100, 150, 200, 125, 75];
                            foreach ($challengeTitles as $ci => $ct):
                            ?>
                            <th class="text-center px-3 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">
                                <?php echo $ct; ?><br>
                                <span class="text-gray-400 font-normal normal-case dash-mono"><?php echo $challengePts[$ci]; ?>pts</span>
                            </th>
                            <?php endforeach; ?>
                            <th class="text-center px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applicants as $app): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50/60 transition">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-900 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                        <?php echo strtoupper(substr($app['name'],0,1)); ?>
                                    </div>
                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($app['name']); ?></span>
                                </div>
                            </td>
                            <?php
                            if ($app['status'] === 'completed') {
                                foreach ($app['challenge_results'] as $cr) {
                                    $bg = $cr['status'] === 'correct' ? 'bg-green-100 text-green-800' :
                                          ($cr['status'] === 'submitted' ? 'bg-blue-100 text-blue-800' :
                                          ($cr['status'] === 'wrong' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400'));
                                    echo '<td class="px-3 py-3 text-center"><span class="dash-mono text-xs font-bold px-2.5 py-1 rounded-full ' . $bg . '">' . $cr['earned'] . '</span></td>';
                                }
                            } else {
                                for ($ci = 0; $ci < 5; $ci++) {
                                    echo '<td class="px-3 py-3 text-center"><span class="text-gray-300 text-xs">—</span></td>';
                                }
                            }
                            ?>
                            <td class="px-4 py-3 text-center">
                                <span class="dash-mono font-bold text-blue-900 text-sm">
                                    <?php echo $app['score'] !== null ? $app['score'] : '—'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.max-w-6xl -->
</div>

<!-- ── Send Assessment Modal ── -->
<div id="sendAssessmentModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="dash-header text-lg font-bold text-gray-900">Send Assessment Link</h3>
            <button onclick="closeSendAssessmentModal()" class="text-gray-400 hover:text-gray-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-900 font-bold text-sm flex items-center justify-center" id="sendModalAvatar">J</div>
                <div>
                    <p class="font-semibold text-gray-900 text-sm" id="sendModalName">Applicant</p>
                    <p class="text-gray-500 text-xs" id="sendModalEmail">email</p>
                </div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                <p class="text-xs font-semibold text-blue-700 mb-1">Assessment link to be sent:</p>
                <p class="text-xs text-blue-900 font-mono break-all" id="sendModalLink"></p>
            </div>
            <textarea id="sendModalMessage" rows="4"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm resize-none"
                placeholder="Add a message (optional)..."></textarea>
            <p id="sendModalFeedback" class="text-xs font-semibold mt-2 hidden"></p>
        </div>
        <div class="flex border-t border-gray-200">
            <button onclick="closeSendAssessmentModal()" class="flex-1 py-3 font-semibold text-gray-600 hover:bg-gray-50 transition text-sm">Cancel</button>
            <button id="sendAssessmentConfirmBtn" class="flex-1 py-3 font-bold bg-blue-900 text-white hover:bg-blue-800 transition text-sm">Send via Messages</button>
        </div>
    </div>
</div>

<!-- ── Applicant Detail Modal ── -->
<div id="detailModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
            <h3 class="dash-header text-lg font-bold text-gray-900" id="detailName">Applicant Details</h3>
            <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1" id="detailBody"></div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 flex-shrink-0">
            <a href="messages.php" class="flex-1 text-center bg-blue-900 hover:bg-blue-800 text-white font-bold py-2.5 rounded-xl transition text-sm">
                Message Applicant
            </a>
            <button onclick="closeDetailModal()" class="flex-1 border border-gray-300 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-50 transition text-sm">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ── Copy toast ── -->
<div id="copyToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-sm font-semibold px-5 py-2.5 rounded-full shadow-lg hidden z-50 transition-all">
    ✓ Link copied to clipboard
</div>

<script>
// ── Send Assessment Modal ─────────────────────────────────────────────────
let _sendTarget = null;

function openSendAssessmentModal(app) {
    _sendTarget = app;
    const assessmentUrl = window.location.origin + '/assessment_session.php?session=sess_netsentinel_001';
    document.getElementById('sendModalAvatar').textContent  = app.name.charAt(0).toUpperCase();
    document.getElementById('sendModalName').textContent    = app.name;
    document.getElementById('sendModalEmail').textContent   = app.email;
    document.getElementById('sendModalLink').textContent    = assessmentUrl;
    document.getElementById('sendModalMessage').value       = '';
    document.getElementById('sendModalFeedback').classList.add('hidden');
    const modal = document.getElementById('sendAssessmentModal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}

function closeSendAssessmentModal() {
    const modal = document.getElementById('sendAssessmentModal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
    _sendTarget = null;
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('sendAssessmentConfirmBtn').addEventListener('click', function() {
        if (!_sendTarget) return;
        const assessmentUrl = window.location.origin + '/assessment_session.php?session=sess_netsentinel_001';
        const customMsg = document.getElementById('sendModalMessage').value.trim();
        const fullText = (customMsg ? customMsg + '\n\n' : '') +
            '📋 Assessment Link for ' + <?php echo json_encode($session['title']); ?> + ':\n' + assessmentUrl;
        const fb = document.getElementById('sendModalFeedback');
        const btn = document.getElementById('sendAssessmentConfirmBtn');
        btn.textContent = 'Sending...';
        btn.disabled = true;

        // POST to messages.php send_message action using conv1 (NetSentinel conversation)
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('conv', 'conv1');
        formData.append('text', fullText);

        fetch('messages.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    fb.textContent = '✓ Assessment link sent via Messages!';
                    fb.className = 'text-xs font-semibold mt-2 text-green-600';
                    fb.classList.remove('hidden');
                    btn.textContent = 'Sent!';
                    setTimeout(() => closeSendAssessmentModal(), 1800);
                } else {
                    fb.textContent = '✗ ' + (data.error || 'Could not send message.');
                    fb.className = 'text-xs font-semibold mt-2 text-red-500';
                    fb.classList.remove('hidden');
                    btn.textContent = 'Send via Messages';
                    btn.disabled = false;
                }
            })
            .catch(() => {
                fb.textContent = '✗ Network error. Please try again.';
                fb.className = 'text-xs font-semibold mt-2 text-red-500';
                fb.classList.remove('hidden');
                btn.textContent = 'Send via Messages';
                btn.disabled = false;
            });
    });

    // Close on backdrop click
    document.getElementById('sendAssessmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeSendAssessmentModal();
    });
});

function copyLink() {
    const url = window.location.origin + '/assessment_session.php?session=sess_netsentinel_001';
    navigator.clipboard.writeText(url).then(() => showToast('✓ Assessment link copied!'));
    document.getElementById('copyBtnLabel').textContent = 'Copied!';
    setTimeout(() => document.getElementById('copyBtnLabel').textContent = 'Copy Link', 2000);
}

function copyAssessmentLink(name) {
    const url = window.location.origin + '/assessment_session.php?session=sess_netsentinel_001';
    navigator.clipboard.writeText(url).then(() => showToast(`✓ Link copied — send to ${name}`));
}

function showToast(msg) {
    const t = document.getElementById('copyToast');
    t.textContent = msg;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 2500);
}

function openDetailModal(app) {
    document.getElementById('detailName').textContent = app.name + ' — Full Report';

    const statusColors = {
        correct:   'bg-green-100 text-green-800',
        submitted: 'bg-blue-100 text-blue-800',
        wrong:     'bg-red-100 text-red-700',
        skipped:   'bg-gray-100 text-gray-500',
    };
    const statusLabels = { correct:'Correct', submitted:'Submitted', wrong:'Wrong', skipped:'Skipped' };

    let rows = app.challenge_results.map(cr => `
        <tr class="border-b border-gray-100">
            <td class="py-3 pr-3 font-semibold text-gray-800 text-sm">${cr.title}</td>
            <td class="py-3 px-3 text-center">
                <span class="text-xs font-bold dash-mono px-2.5 py-1 rounded-full ${statusColors[cr.status] || 'bg-gray-100 text-gray-500'}">
                    ${statusLabels[cr.status] || cr.status}
                </span>
            </td>
            <td class="py-3 px-3 text-center dash-mono text-sm font-bold text-blue-900">${cr.earned} <span class="text-gray-400 font-normal">/ ${cr.pts}</span></td>
            <td class="py-3 pl-3 text-center text-sm text-gray-500 dash-mono">${cr.time}</td>
        </tr>
    `).join('');

    const pct = Math.round((app.score / <?php echo $session['total_pts']; ?>) * 100);

    document.getElementById('detailBody').innerHTML = `
        <div class="flex items-center gap-4 mb-5">
            <div class="w-14 h-14 rounded-full bg-blue-100 text-blue-900 font-bold text-xl flex items-center justify-center">
                ${app.name.charAt(0).toUpperCase()}
            </div>
            <div>
                <h4 class="dash-header text-xl font-bold text-gray-900">${app.name}</h4>
                <p class="text-gray-500 text-sm">${app.email}</p>
                <p class="text-xs text-gray-400 mt-0.5">Completed: ${app.completed_at}</p>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-blue-50 rounded-xl p-3 text-center">
                <p class="dash-mono text-xl font-bold text-blue-900">${app.score}</p>
                <p class="text-xs text-gray-500">Points (${pct}%)</p>
            </div>
            <div class="bg-green-50 rounded-xl p-3 text-center">
                <p class="dash-mono text-xl font-bold text-green-700">${app.solved}/${app.challenge_results.length}</p>
                <p class="text-xs text-gray-500">Solved</p>
            </div>
            <div class="bg-amber-50 rounded-xl p-3 text-center">
                <p class="dash-mono text-xl font-bold text-amber-700">${app.time_used}</p>
                <p class="text-xs text-gray-500">Time Used</p>
            </div>
        </div>
        <table class="w-full">
            <thead>
                <tr class="text-xs uppercase tracking-wide text-gray-500 border-b border-gray-200">
                    <th class="text-left pb-2 pr-3">Challenge</th>
                    <th class="text-center pb-2 px-3">Status</th>
                    <th class="text-center pb-2 px-3">Points</th>
                    <th class="text-center pb-2 pl-3">Time</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;

    const modal = document.getElementById('detailModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeDetailModal() {
    const modal = document.getElementById('detailModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetailModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetailModal(); });
</script>

<?php include 'includes/footer.php'; ?>
