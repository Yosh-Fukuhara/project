<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';
cs_init_assessments();

// ── Load session: dynamic (employer-created) or static fallback ───────────
$sessionId = $_GET['session'] ?? 'sess_netsentinel_001';
$dynamicAssessment = cs_get_assessment_by_id($sessionId);

if ($dynamicAssessment) {
    $session = [
        'id'           => $dynamicAssessment['id'],
        'title'        => $dynamicAssessment['title'],
        'company'      => $dynamicAssessment['employer_name'] ?? 'CyberSphere',
        'company_logo' => '🛡️',
        'role'         => $dynamicAssessment['role'] ?? '',
        'time_limit'   => $dynamicAssessment['time_limit'] ?? 30,
        'instructions' => $dynamicAssessment['instructions'] ?? 'Answer each challenge as accurately as possible.',
    ];
    $challenges = $dynamicAssessment['challenges'];
    // Build CORRECT map from correct_flag fields
    $correctMap = [];
    foreach ($challenges as $ch) {
        $correctMap[$ch['id']] = !empty($ch['correct_flag']) ? $ch['correct_flag'] : null;
    }
} else {
    // ── Static simulation data (NetSentinel default) ──────────────────────
    $session = [
        'id'          => 'sess_netsentinel_001',
        'title'       => 'SOC Analyst Skill Assessment',
        'company'     => 'NetSentinel Solutions',
        'company_logo'=> '🛡️',
        'role'        => 'SOC Analyst',
        'time_limit'  => 45,
        'instructions'=> 'Answer each challenge as accurately as possible. For flag-style answers enter the exact flag string. For code challenges paste your solution in the text box. Your score is based on correctness and time of completion.',
    ];

    $challenges = [
    [
        'id'     => 'c1',
        'type'   => 'flag',
        'points' => 100,
        'title'  => 'Network Recon',
        'body'   => 'A packet capture file has been attached. Analyse the traffic and find the attacker\'s C2 server IP address. Submit it in the format: <code class="bg-gray-100 px-2 py-0.5 rounded font-mono text-sm">FLAG{x.x.x.x}</code>',
        'attachment' => ['name' => 'capture_traffic.pkt', 'icon' => '📡', 'note' => 'Open in Cisco Packet Tracer'],
        'hint'   => 'Filter for unusual outbound TCP connections on non-standard ports.',
    ],
    [
        'id'     => 'c2',
        'type'   => 'flag',
        'points' => 150,
        'title'  => 'Subnet Calculation',
        'body'   => 'Given the network <strong>192.168.10.0/27</strong>, answer the following:<br><br>
                     <ul class="list-disc ml-5 space-y-1 text-gray-700 text-sm mt-2">
                       <li>What is the subnet mask?</li>
                       <li>How many usable host addresses are there?</li>
                       <li>What is the broadcast address?</li>
                     </ul><br>
                     Combine your answers as: <code class="bg-gray-100 px-2 py-0.5 rounded font-mono text-sm">FLAG{mask_hosts_broadcast}</code>',
        'attachment' => null,
        'hint'   => 'Remember: /27 = 3 bits borrowed from the host portion.',
    ],
    [
        'id'     => 'c3',
        'type'   => 'code',
        'points' => 200,
        'title'  => 'Log Parser',
        'body'   => 'Write a Python or Bash script that reads a web server access log and outputs the <strong>top 5 IP addresses</strong> by request count, sorted descending. Your script should accept the log filename as a command-line argument.<br><br>
                     Sample log line format:<br>
                     <code class="block bg-gray-900 text-green-400 text-xs p-3 rounded-xl mt-2 font-mono">203.0.113.5 - - [12/May/2026:10:23:01 +0000] "GET /login HTTP/1.1" 200 512</code>',
        'attachment' => null,
        'hint'   => 'The IP address is always the first field on each line.',
    ],
    [
        'id'     => 'c4',
        'type'   => 'flag',
        'points' => 125,
        'title'  => 'SIEM Alert Triage',
        'body'   => 'Review the SIEM alert screenshot below. Identify the MITRE ATT&CK technique ID being used and submit it as: <code class="bg-gray-100 px-2 py-0.5 rounded font-mono text-sm">FLAG{T####.###}</code>',
        'attachment' => ['name' => 'siem_alert.png', 'icon' => '🖼️', 'note' => 'Simulated SIEM screenshot — lateral movement detected'],
        'hint'   => 'Look at the process spawning chain: cmd.exe → powershell.exe → net.exe',
    ],
    [
        'id'     => 'c5',
        'type'   => 'short',
        'points' => 75,
        'title'  => 'Incident Response',
        'body'   => 'A user reports their workstation is behaving strangely — CPU is spiking to 100%, unknown outbound connections are being made every 5 minutes, and a new scheduled task appeared overnight.<br><br>
                     In <strong>3–5 sentences</strong>, describe your initial triage steps as a SOC analyst. What do you check first and why?',
        'attachment' => null,
        'hint'   => null,
    ],
];
    $correctMap = [
        'c1' => 'FLAG{10.0.2.15}',
        'c2' => 'FLAG{255.255.255.224_30_192.168.10.31}',
        'c3' => null,
        'c4' => 'FLAG{T1059.001}',
        'c5' => null,
    ];
} // end else (static fallback)

$totalPoints = array_sum(array_column($challenges, 'points'));
$pageTitle   = $session['title'] . ' - CyberSphere';
$currentPage = 'assessment';
?>
<?php include 'includes/header.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Syne:wght@400;600;700;800&display=swap');

:root {
    --sol-bg: #0d1117;
    --sol-surface: #161b22;
    --sol-border: #30363d;
    --sol-accent: #f78166;
    --sol-green: #3fb950;
    --sol-blue: #58a6ff;
    --sol-yellow: #e3b341;
    --sol-muted: #8b949e;
}

.ctf-header { font-family: 'Syne', sans-serif; }
.ctf-mono   { font-family: 'JetBrains Mono', monospace; }

/* Challenge cards */
.challenge-card {
    border: 1px solid #e5e7eb;
    border-left: 4px solid #e5e7eb;
    transition: border-color .2s, box-shadow .2s;
}
.challenge-card.solved {
    border-left-color: #22c55e;
    background: #f0fdf4;
}
.challenge-card.active {
    border-left-color: #1e3a8a;
    box-shadow: 0 0 0 3px rgba(30,58,138,.08);
}

/* Timer pulse when low */
@keyframes pulse-red {
    0%, 100% { color: #dc2626; }
    50%       { color: #ef4444; opacity: .6; }
}
.timer-low { animation: pulse-red 1s ease-in-out infinite; }

/* Points badge */
.pts-badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: .7rem;
    letter-spacing: .05em;
}

/* Solve animation */
@keyframes pop-in {
    0%   { transform: scale(.8); opacity: 0; }
    70%  { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}
.solve-pop { animation: pop-in .35s cubic-bezier(.34,1.56,.64,1) forwards; }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #f1f5f9; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
</style>

<div class="min-h-screen bg-gray-50">

    <!-- ── Top bar ── -->
    <div class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-2xl"><?php echo $session['company_logo']; ?></span>
                <div class="min-w-0">
                    <p class="ctf-header font-bold text-blue-900 text-sm leading-tight truncate"><?php echo htmlspecialchars($session['company']); ?></p>
                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($session['title']); ?></p>
                </div>
            </div>

            <div class="flex items-center gap-4 flex-shrink-0">
                <!-- Live score -->
                <div class="hidden sm:flex flex-col items-center">
                    <span id="liveScore" class="ctf-mono font-bold text-lg text-blue-900">0</span>
                    <span class="text-xs text-gray-400">/ <?php echo $totalPoints; ?> pts</span>
                </div>

                <!-- Timer -->
                <div class="flex flex-col items-center">
                    <span id="timerDisplay" class="ctf-mono font-bold text-lg text-gray-800">
                        <?php echo str_pad($session['time_limit'], 2, '0', STR_PAD_LEFT); ?>:00
                    </span>
                    <span class="text-xs text-gray-400">remaining</span>
                </div>

                <!-- Submit all -->
                <button id="submitAllBtn"
                        class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-bold px-4 py-2 rounded-xl transition">
                    Submit All
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-6 grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- ── Left sidebar: challenge index ── -->
        <aside class="lg:col-span-3">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden sticky top-20">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                    <p class="ctf-header font-bold text-gray-700 text-sm uppercase tracking-wider">Challenges</p>
                </div>
                <div class="divide-y divide-gray-100" id="challengeIndex">
                    <?php foreach ($challenges as $i => $ch): ?>
                    <button type="button"
                            data-jump="<?php echo $ch['id']; ?>"
                            class="challenge-nav-btn w-full text-left px-4 py-3 hover:bg-gray-50 transition flex items-center gap-3">
                        <span id="nav-icon-<?php echo $ch['id']; ?>"
                              class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0 text-xs font-bold text-gray-400">
                            <?php echo $i + 1; ?>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($ch['title']); ?></p>
                            <p class="pts-badge text-gray-400"><?php echo $ch['points']; ?> pts</p>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Progress summary -->
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                    <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                        <span>Progress</span>
                        <span id="progressLabel">0 / <?php echo count($challenges); ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="progressBar" class="bg-blue-900 h-2 rounded-full transition-all duration-500" style="width:0%"></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ── Main: challenges ── -->
        <main class="lg:col-span-9 space-y-5">

            <!-- Instructions banner -->
            <div class="bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 flex gap-3">
                <svg class="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="font-semibold text-blue-900 text-sm">Instructions</p>
                    <p class="text-blue-800 text-sm mt-0.5"><?php echo htmlspecialchars($session['instructions']); ?></p>
                </div>
            </div>

            <!-- Challenge cards -->
            <?php foreach ($challenges as $i => $ch): ?>
            <div class="challenge-card bg-white rounded-2xl shadow-sm p-6" id="card-<?php echo $ch['id']; ?>">

                <!-- Card header -->
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-sm ctf-mono flex-shrink-0">
                            <?php echo $i + 1; ?>
                        </div>
                        <div>
                            <h2 class="ctf-header font-bold text-gray-900 text-base"><?php echo htmlspecialchars($ch['title']); ?></h2>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="pts-badge bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                                    <?php echo $ch['points']; ?> pts
                                </span>
                                <span class="pts-badge bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full uppercase">
                                    <?php echo $ch['type'] === 'flag' ? '🚩 Flag' : ($ch['type'] === 'code' ? '💻 Code' : '✍️ Short Answer'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Solved badge (hidden by default) -->
                    <div id="solved-badge-<?php echo $ch['id']; ?>"
                         class="hidden solve-pop flex-shrink-0 bg-green-100 text-green-700 font-bold text-xs px-3 py-1.5 rounded-full flex items-center gap-1">
                        ✓ Solved
                    </div>
                </div>

                <!-- Body -->
                <div class="text-gray-700 text-sm leading-relaxed mb-4">
                    <?php echo $ch['body']; ?>
                </div>

                <!-- Attachment -->
                <?php if ($ch['attachment']): ?>
                <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 mb-4 text-sm">
                    <span class="text-2xl"><?php echo $ch['attachment']['icon']; ?></span>
                    <div>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($ch['attachment']['name']); ?></p>
                        <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($ch['attachment']['note']); ?></p>
                    </div>
                    <button class="ml-auto text-blue-700 hover:text-blue-900 font-semibold text-xs border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition">
                        Download
                    </button>
                </div>
                <?php endif; ?>

                <!-- Hint -->
                <?php if ($ch['hint']): ?>
                <details class="mb-4">
                    <summary class="text-xs font-semibold text-amber-600 cursor-pointer hover:text-amber-700 select-none flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        Show Hint (−10 pts penalty)
                    </summary>
                    <p class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                        <?php echo htmlspecialchars($ch['hint']); ?>
                    </p>
                </details>
                <?php endif; ?>

                <!-- Answer input -->
                <?php if ($ch['type'] === 'code' || $ch['type'] === 'short'): ?>
                <textarea
                    id="answer-<?php echo $ch['id']; ?>"
                    rows="<?php echo $ch['type'] === 'code' ? 8 : 4; ?>"
                    placeholder="<?php echo $ch['type'] === 'code' ? '# Paste your code here...' : 'Write your answer here...'; ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm <?php echo $ch['type'] === 'code' ? 'ctf-mono' : ''; ?> resize-y"
                ></textarea>
                <?php else: ?>
                <input
                    type="text"
                    id="answer-<?php echo $ch['id']; ?>"
                    placeholder="FLAG{...}"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm ctf-mono"
                >
                <?php endif; ?>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <p id="feedback-<?php echo $ch['id']; ?>" class="text-xs font-semibold hidden"></p>
                    <button
                        type="button"
                        data-challenge="<?php echo $ch['id']; ?>"
                        data-points="<?php echo $ch['points']; ?>"
                        data-type="<?php echo $ch['type']; ?>"
                        class="submit-challenge-btn ml-auto bg-blue-900 hover:bg-blue-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition">
                        Submit Answer
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

        </main>
    </div>
</div>

<!-- ── Submit All Confirmation Modal ── -->
<div id="submitAllModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6">
            <h3 class="ctf-header text-xl font-bold text-gray-900 mb-2">Submit Assessment?</h3>
            <p class="text-gray-600 text-sm mb-4">You've answered <span id="modalAnsweredCount" class="font-bold text-blue-900">0</span> of <?php echo count($challenges); ?> challenges.</p>
            <div class="bg-gray-50 rounded-xl p-4 text-sm space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-gray-500">Score</span>
                    <span class="font-bold ctf-mono text-blue-900" id="modalScore">0 / <?php echo $totalPoints; ?> pts</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Time used</span>
                    <span class="font-bold ctf-mono text-gray-800" id="modalTimeUsed">—</span>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-3">Unanswered challenges will receive 0 points. You cannot edit answers after submission.</p>
        </div>
        <div class="flex border-t border-gray-200">
            <button id="cancelSubmitAll" class="flex-1 py-3 font-semibold text-gray-600 hover:bg-gray-50 transition text-sm">Cancel</button>
            <button id="confirmSubmitAll" class="flex-1 py-3 font-bold bg-blue-900 text-white hover:bg-blue-800 transition text-sm">Submit Now</button>
        </div>
    </div>
</div>

<!-- ── Completion Modal ── -->
<div id="completionModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-br from-blue-900 to-cyan-800 p-8 text-center text-white">
            <div class="text-5xl mb-3">🏆</div>
            <h2 class="ctf-header text-2xl font-bold">Assessment Complete!</h2>
            <p class="text-blue-200 text-sm mt-1"><?php echo htmlspecialchars($session['title']); ?></p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-3 gap-4 mb-5">
                <div class="text-center bg-blue-50 rounded-xl p-3">
                    <p class="ctf-mono text-2xl font-bold text-blue-900" id="finalScore">0</p>
                    <p class="text-xs text-gray-500 mt-0.5">Points</p>
                </div>
                <div class="text-center bg-green-50 rounded-xl p-3">
                    <p class="ctf-mono text-2xl font-bold text-green-700" id="finalSolved">0/<?php echo count($challenges); ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">Solved</p>
                </div>
                <div class="text-center bg-amber-50 rounded-xl p-3">
                    <p class="ctf-mono text-2xl font-bold text-amber-700" id="finalTime">—</p>
                    <p class="text-xs text-gray-500 mt-0.5">Time Used</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 text-center mb-5">
                Your results have been sent to <strong><?php echo htmlspecialchars($session['company']); ?></strong>. They will review all applicants and get back to you.
            </p>
            <a href="index.php" class="block w-full text-center bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                Back to Home
            </a>
        </div>
    </div>
</div>

<script>
(function () {
    // ── Config ────────────────────────────────────────────────────────────
    const TIME_LIMIT_SECS = <?php echo $session['time_limit'] * 60; ?>;
    const TOTAL_POINTS    = <?php echo $totalPoints; ?>;
    const CHALLENGES = <?php echo json_encode(array_column($challenges, 'id')); ?>;

    // Correct answers (null = open-ended, accepted if non-empty)
    const CORRECT = <?php echo json_encode($correctMap); ?>;

    // ── State ─────────────────────────────────────────────────────────────
    let secondsLeft  = TIME_LIMIT_SECS;
    let secondsUsed  = 0;
    let score        = 0;
    let solved       = new Set();
    let timerRunning = true;
    let timerInterval;

    // ── Timer ─────────────────────────────────────────────────────────────
    const timerEl = document.getElementById('timerDisplay');

    function formatTime(secs) {
        const m = Math.floor(secs / 60).toString().padStart(2, '0');
        const s = (secs % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    function tickTimer() {
        if (!timerRunning) return;
        secondsLeft--;
        secondsUsed++;
        timerEl.textContent = formatTime(secondsLeft);

        if (secondsLeft <= 300) timerEl.classList.add('timer-low');
        if (secondsLeft <= 0) {
            timerRunning = false;
            clearInterval(timerInterval);
            openCompletionModal();
        }
    }
    timerInterval = setInterval(tickTimer, 1000);

    // ── Score + progress ──────────────────────────────────────────────────
    function updateScore(pts) {
        score += pts;
        document.getElementById('liveScore').textContent = score;
    }

    function updateProgress() {
        const pct = (solved.size / CHALLENGES.length) * 100;
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressLabel').textContent = `${solved.size} / ${CHALLENGES.length}`;
    }

    // ── Jump to challenge ─────────────────────────────────────────────────
    document.querySelectorAll('[data-jump]').forEach(btn => {
        btn.addEventListener('click', () => {
            const el = document.getElementById('card-' + btn.dataset.jump);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    // ── Submit individual answer ──────────────────────────────────────────
    document.querySelectorAll('.submit-challenge-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const cid    = btn.dataset.challenge;
            const pts    = parseInt(btn.dataset.points, 10);
            const type   = btn.dataset.type;
            const input  = document.getElementById('answer-' + cid);
            const answer = (input?.value || '').trim();
            const fb     = document.getElementById('feedback-' + cid);
            const card   = document.getElementById('card-' + cid);
            const badge  = document.getElementById('solved-badge-' + cid);
            const navIcon = document.getElementById('nav-icon-' + cid);

            if (!answer) {
                fb.textContent = '⚠ Please enter an answer first.';
                fb.className   = 'text-xs font-semibold text-amber-600';
                fb.classList.remove('hidden');
                return;
            }

            if (solved.has(cid)) {
                fb.textContent = '✓ Already submitted.';
                fb.className   = 'text-xs font-semibold text-green-600';
                fb.classList.remove('hidden');
                return;
            }

            let correct = false;
            if (type === 'code' || type === 'short') {
                // Non-flag: accept any non-empty answer, award points
                correct = true;
            } else {
                correct = (answer.toUpperCase() === (CORRECT[cid] || '').toUpperCase());
            }

            if (correct) {
                solved.add(cid);
                updateScore(pts);
                updateProgress();

                card.classList.add('solved');
                badge.classList.remove('hidden');
                badge.classList.add('flex');

                navIcon.className = 'w-6 h-6 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0 text-white';
                navIcon.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`;

                fb.textContent = `✓ Correct! +${pts} pts`;
                fb.className   = 'text-xs font-semibold text-green-600';
                fb.classList.remove('hidden');

                input.disabled = true;
                btn.disabled   = true;
                btn.textContent = 'Submitted';
                btn.className   = btn.className.replace('bg-blue-900 hover:bg-blue-800', 'bg-green-500 cursor-default');
            } else {
                fb.textContent = '✗ Incorrect flag. Try again.';
                fb.className   = 'text-xs font-semibold text-red-500';
                fb.classList.remove('hidden');

                input.classList.add('border-red-300', 'bg-red-50');
                setTimeout(() => input.classList.remove('border-red-300', 'bg-red-50'), 1200);
            }
        });
    });

    // ── Submit All ────────────────────────────────────────────────────────
    const submitAllBtn   = document.getElementById('submitAllBtn');
    const submitAllModal = document.getElementById('submitAllModal');
    const cancelBtn      = document.getElementById('cancelSubmitAll');
    const confirmBtn     = document.getElementById('confirmSubmitAll');

    submitAllBtn.addEventListener('click', () => {
        document.getElementById('modalAnsweredCount').textContent = solved.size;
        document.getElementById('modalScore').textContent = `${score} / ${TOTAL_POINTS} pts`;
        document.getElementById('modalTimeUsed').textContent = formatTime(secondsUsed);
        submitAllModal.classList.remove('hidden');
        submitAllModal.classList.add('flex');
    });

    cancelBtn.addEventListener('click', () => {
        submitAllModal.classList.add('hidden');
        submitAllModal.classList.remove('flex');
    });

    confirmBtn.addEventListener('click', () => {
        submitAllModal.classList.add('hidden');
        submitAllModal.classList.remove('flex');
        openCompletionModal();
    });

    function openCompletionModal() {
        timerRunning = false;
        clearInterval(timerInterval);
        const modal = document.getElementById('completionModal');
        document.getElementById('finalScore').textContent  = score;
        document.getElementById('finalSolved').textContent = `${solved.size}/${CHALLENGES.length}`;
        document.getElementById('finalTime').textContent   = formatTime(secondsUsed);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // ── Scroll-spy: highlight active card in sidebar ──────────────────────
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            const cid = e.target.id.replace('card-', '');
            const navBtn = document.querySelector(`[data-jump="${cid}"]`);
            if (navBtn) navBtn.classList.toggle('bg-blue-50', e.isIntersecting);
        });
    }, { threshold: 0.4 });

    CHALLENGES.forEach(cid => {
        const el = document.getElementById('card-' + cid);
        if (el) observer.observe(el);
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
