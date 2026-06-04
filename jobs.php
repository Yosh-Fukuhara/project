<?php
require_once 'includes/bootstrap.php';
require_once 'autoload.php';

$pageTitle = 'Available Jobs - CyberSphere';
$currentPage = 'home';

// Get all hiring posts from session
$allJobs = [];
foreach ($_SESSION['posts'] as $post) {
    if (isset($post['type']) && $post['type'] === 'user' && !empty($post['hiring'])) {
        $allJobs[] = $post;
    }
}

// Add static fallback jobs if session is empty
if (empty($allJobs)) {
    $allJobs = [
        [
            'type' => 'user',
            'id' => 'static_1',
            'username' => 'NetSentinel Solutions',
            'avatar' => null,
            'logo' => '🛡️',
            'time' => '2 days ago',
            'content' => 'Senior Penetration Tester - Remote position. Experience with OSCP preferred.',
            'hiring' => true,
            'enable_apply' => true,
            'tags' => ['#Hiring', '#Cybersecurity']
        ],
        [
            'type' => 'user',
            'id' => 'static_2',
            'username' => 'SecureBank',
            'avatar' => null,
            'logo' => '🏦',
            'time' => '4h ago',
            'content' => 'GRC Specialist needed for immediate start. Focus on ISO 27001 compliance.',
            'hiring' => true,
            'enable_apply' => true,
            'tags' => ['#Hiring', '#Compliance']
        ],
        [
            'type' => 'user',
            'id' => 'static_3',
            'username' => 'CyberGuard Inc',
            'avatar' => null,
            'logo' => '🔐',
            'time' => '1 day ago',
            'content' => 'SOC Analyst (Level 2). Night shift available. 3+ years experience required.',
            'hiring' => true,
            'enable_apply' => true,
            'tags' => ['#Hiring', '#SOC']
        ]
    ];
}

include 'includes/header.php';
?>

<div class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-8">
            <a href="index.php" class="text-blue-800 hover:underline">Home</a>
            <span>/</span>
            <span class="text-gray-700 font-semibold">All Available Jobs</span>
        </nav>

        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Available Jobs</h1>
                    <p class="text-gray-600">Find your next opportunity in cybersecurity and tech</p>
                </div>
                <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-full font-bold text-sm">
                    <?php echo count($allJobs); ?> Opportunities
                </div>
            </div>

            <div class="space-y-6">
                <?php foreach ($allJobs as $job): 
                    $pid = $job['id'] ?? 'job_' . uniqid();
                    $applyEnabled = !empty($job['enable_apply']);
                ?>
                    <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition border border-gray-100">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center text-3xl flex-shrink-0">
                                    <?php if (!empty($job['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($job['avatar']); ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo $job['logo'] ?? '💼'; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <h2 class="text-xl font-bold text-gray-900 truncate"><?php echo htmlspecialchars($job['username'] ?? $job['company'] ?? 'Company'); ?></h2>
                                        <span class="text-xs text-gray-400"><?php echo htmlspecialchars($job['time']); ?></span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <?php if (!empty($job['tags'])): ?>
                                            <?php foreach ($job['tags'] as $tag): ?>
                                                <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-700 leading-relaxed mb-6 whitespace-pre-wrap">
                                        <?php echo nl2br(htmlspecialchars($job['content'])); ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-between pt-6 border-t border-gray-50">
                                        <div class="flex items-center gap-4">
                                            <span class="text-sm text-gray-500 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                                Remote / Global
                                            </span>
                                            <span class="text-sm text-gray-500 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Full-time
                                            </span>
                                        </div>
                                        <?php if ($applyEnabled): ?>
                                            <button class="apply-modal-btn bg-pink-700 hover:bg-pink-800 text-white font-bold px-8 py-3 rounded-xl transition shadow-lg shadow-pink-700/20" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                                                Apply Now
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm italic">Direct application disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reusing the application modal from index.php -->
<div id="applyModalBackdrop" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Apply for this position</h3>
            <button id="applyModalClose" type="button" class="text-gray-500 hover:text-gray-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="applyForm" class="p-5 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" value="apply_job">
            <input type="hidden" id="applyPostId" name="post_id" value="">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="app_name" required class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Your full name">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                <input type="email" name="app_email" required class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="you@example.com">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                <input type="tel" name="app_phone" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="+1 555 000 0000">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Resume / CV</label>
                <input type="file" name="app_resume" accept=".pdf,.doc,.docx" class="w-full text-sm">
                <p class="text-xs text-gray-400 mt-1">PDF, DOC or DOCX (max 25MB)</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Cover Message</label>
                <textarea name="app_message" rows="3" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Tell us why you're a great fit…"></textarea>
            </div>
            <div id="applyFormMsg" class="hidden text-sm font-semibold rounded-lg px-4 py-2"></div>
            <button type="submit" class="w-full bg-pink-700 hover:bg-pink-800 text-white font-bold py-3 rounded-xl transition">
                Submit Application
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const backdrop = document.getElementById('applyModalBackdrop');
    const closeBtn = document.getElementById('applyModalClose');
    const applyForm = document.getElementById('applyForm');
    const postIdInput = document.getElementById('applyPostId');
    const msgBox = document.getElementById('applyFormMsg');

    document.querySelectorAll('.apply-modal-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            postIdInput.value = btn.getAttribute('data-post-id');
            backdrop.classList.remove('hidden');
            backdrop.classList.add('flex');
            msgBox.classList.add('hidden');
            applyForm.reset();
        });
    });

    closeBtn.addEventListener('click', () => {
        backdrop.classList.add('hidden');
        backdrop.classList.remove('flex');
    });

    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeBtn.click();
    });

    applyForm.addEventListener('submit', function(e) {
        e.preventDefault();
        msgBox.className = 'text-sm font-semibold rounded-lg px-4 py-2 bg-blue-100 text-blue-800';
        msgBox.textContent = 'Submitting application...';
        msgBox.classList.remove('hidden');

        const formData = new FormData(this);
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                msgBox.className = 'text-sm font-semibold rounded-lg px-4 py-2 bg-green-100 text-green-800';
                msgBox.textContent = data.msg || 'Application sent successfully!';
                setTimeout(() => closeBtn.click(), 2000);
            } else {
                msgBox.className = 'text-sm font-semibold rounded-lg px-4 py-2 bg-red-100 text-red-800';
                msgBox.textContent = data.msg || 'Failed to send application.';
            }
        })
        .catch(() => {
            msgBox.className = 'text-sm font-semibold rounded-lg px-4 py-2 bg-red-100 text-red-800';
            msgBox.textContent = 'An error occurred. Please try again.';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
