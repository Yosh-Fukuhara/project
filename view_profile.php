<?php
require_once 'includes/bootstrap.php';

// ─── Resolve which user to show ────────────────────────────────────────────
$viewedUser = null;
$isOwnProfile = false;

try {
    $pdo = get_db_connection();

    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $stmt = $pdo->prepare(
            'SELECT user_id, username, email, role, profile_pic, cover_pic, bio, location, work, education, website, phone
             FROM users WHERE user_id = ? AND status = "active" LIMIT 1'
        );
        $stmt->execute([(int)$_GET['id']]);
        $viewedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } elseif (isset($_GET['username'])) {
        $stmt = $pdo->prepare(
            'SELECT user_id, username, email, role, profile_pic, cover_pic, bio, location, work, education, website, phone
             FROM users WHERE username = ? AND status = "active" LIMIT 1'
        );
        $stmt->execute([trim($_GET['username'])]);
        $viewedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Exception $e) {
    $viewedUser = null;
}

// Fallback: if this is the logged-in user, redirect to their own profile page
if ($viewedUser && isset($_SESSION['user']) && (int)$viewedUser['user_id'] === (int)$_SESSION['user']['user_id']) {
    header('Location: profile.php');
    exit;
}

// 404-style if user not found
if (!$viewedUser) {
    $pageTitle   = 'Profile Not Found - CyberSphere';
    $currentPage = 'profile';
    include 'includes/header.php';
    ?>
    <div class="container mx-auto px-4 py-16 text-center">
        <div class="max-w-md mx-auto">
            <div class="text-6xl mb-4">👤</div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Profile Not Found</h1>
            <p class="text-gray-500 mb-6">This user doesn't exist or their account is inactive.</p>
            <a href="index.php" class="bg-blue-900 text-white px-6 py-2 rounded-xl font-semibold hover:bg-blue-800 transition">Back to Home</a>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

$pageTitle   = htmlspecialchars($viewedUser['username']) . ' - CyberSphere';
$currentPage = 'profile';
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">

        <!-- Profile Card -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition mb-6">

            <!-- Cover Photo -->
            <div class="w-full h-48 md:h-64 bg-gradient-to-r from-blue-900 to-cyan-800 overflow-hidden flex items-center justify-center">
                <?php if (!empty($viewedUser['cover_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($viewedUser['cover_pic']); ?>"
                         alt="Cover photo"
                         class="w-full h-full object-cover cursor-pointer"
                         id="coverPhotoBtn">
                <?php endif; ?>
            </div>

            <!-- Avatar + Name -->
            <div class="px-6 pb-6 -mt-10 relative z-10">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <div class="w-20 h-20 bg-gray-200 rounded-full border-4 border-white flex items-center justify-center text-3xl text-blue-800 overflow-hidden shadow-md flex-shrink-0">
                        <?php if (!empty($viewedUser['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($viewedUser['profile_pic']); ?>"
                                 alt="Profile photo"
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo strtoupper(substr($viewedUser['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 mt-2 sm:mt-8">
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($viewedUser['username']); ?></h1>
                        <?php if (!empty($viewedUser['work'])): ?>
                            <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($viewedUser['work']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($viewedUser['location'])): ?>
                            <p class="text-gray-500 text-sm flex items-center gap-1 mt-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <?php echo htmlspecialchars($viewedUser['location']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Message Button (only for logged-in users) -->
                    <?php if (isset($_SESSION['user'])): ?>
                    <div class="flex gap-2 mt-2 sm:mt-8">
                        <a href="messages.php"
                           class="flex items-center gap-2 bg-blue-900 text-white px-5 py-2 rounded-xl font-semibold hover:bg-blue-800 transition text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            Message
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bio -->
        <?php if (!empty($viewedUser['bio'])): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($viewedUser['bio'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Details Grid -->
        <?php
        $details = [
            ['icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label' => 'Work', 'value' => $viewedUser['work']],
            ['icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 7l-9-5 9 5 9-5-9 5zm0-14l-9 5 9 5 9-5-9-5z', 'label' => 'Education', 'value' => $viewedUser['education']],
            ['icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9', 'label' => 'Website', 'value' => $viewedUser['website'], 'link' => true],
        ];
        $hasDetails = array_filter($details, fn($d) => !empty($d['value']));
        if ($hasDetails):
        ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">About</h2>
            <div class="space-y-3">
                <?php foreach ($details as $d): ?>
                    <?php if (!empty($d['value'])): ?>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $d['icon']; ?>"/>
                        </svg>
                        <div>
                            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide"><?php echo $d['label']; ?></p>
                            <?php if (!empty($d['link'])): ?>
                                <a href="<?php echo htmlspecialchars($d['value']); ?>" target="_blank" rel="noopener"
                                   class="text-blue-700 hover:underline text-sm"><?php echo htmlspecialchars($d['value']); ?></a>
                            <?php else: ?>
                                <p class="text-gray-800 text-sm"><?php echo htmlspecialchars($d['value']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Posts Section -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Posts</h2>
            <?php
            // Seeded static posts for accounts whose posts live in index.php, not the DB
            $seededStaticPosts = [
                'hr@netsentinel.com' => [
                    [
                        'content'         => 'We are looking for: SOC analyst, Full stack Developer, Graphic Designer, and Data analyst. Submit your credentials and apply now!',
                        'attachment_path' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&h=400&fit=crop',
                        'time_label'      => '2h ago',
                    ],
                ],
                'marcus.vane@example.com' => [
                    [
                        'content'         => 'I am currently seeking a new challenge in Digital Forensics and Incident Response (DFIR). 8+ years of experience in enterprise defense. Open to hybrid roles in London.',
                        'attachment_path' => null,
                        'time_label'      => '1d ago',
                    ],
                ],
            ];

            $userPosts = [];
            try {
                $pdo2 = get_db_connection();
                $pStmt = $pdo2->prepare(
                    'SELECT p.id, p.content, p.attachment_path, p.created_at,
                            u.username, u.profile_pic
                     FROM posts p
                     JOIN users u ON u.id = p.user_id
                     WHERE p.user_id = ?
                     ORDER BY p.created_at DESC
                     LIMIT 10'
                );
                $pStmt->execute([$viewedUser['id']]);
                $userPosts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $userPosts = [];
            }

            // Merge seeded static posts if not already present in DB
            $viewedEmail = $viewedUser['email'] ?? '';
            if (isset($seededStaticPosts[$viewedEmail])) {
                foreach ($seededStaticPosts[$viewedEmail] as $sp) {
                    $alreadyInDb = false;
                    foreach ($userPosts as $dbp) {
                        if (trim((string)($dbp['content'] ?? '')) === trim($sp['content'])) {
                            $alreadyInDb = true;
                            break;
                        }
                    }
                    if (!$alreadyInDb) {
                        $userPosts[] = array_merge($sp, [
                            'username'    => $viewedUser['username'],
                            'profile_pic' => $viewedUser['profile_pic'] ?? null,
                            'created_at'  => null,
                        ]);
                    }
                }
            }
            ?>
            <?php if (empty($userPosts)): ?>
                <div class="bg-gray-50 rounded-xl p-8 text-center">
                    <div class="text-2xl mb-2">&#x1F4DD;</div>
                    <p class="text-gray-500">This user hasn't posted anything yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($userPosts as $post): ?>
                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-900 font-bold flex items-center justify-center overflow-hidden flex-shrink-0">
                                <?php if (!empty($viewedUser['profile_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($viewedUser['profile_pic']); ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($viewedUser['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($viewedUser['username']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php
                                    if (!empty($post['created_at'])) {
                                        echo date('M j, Y g:i A', strtotime($post['created_at']));
                                    } else {
                                        echo htmlspecialchars($post['time_label'] ?? 'Recently');
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($post['content'])): ?>
                            <p class="text-gray-800 text-sm whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($post['attachment_path'])): ?>
                            <?php
                            $ap  = $post['attachment_path'];
                            $ext = strtolower(pathinfo(parse_url($ap, PHP_URL_PATH) ?: $ap, PATHINFO_EXTENSION));
                            $ext = strtok($ext, '?');
                            $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp'])
                                  || preg_match('#^https?://(images\.unsplash\.com|i\.imgur\.com|imgur\.com)#i', $ap);
                            ?>
                            <?php if ($isImg): ?>
                                <img src="<?php echo htmlspecialchars($ap); ?>"
                                     alt="Post image"
                                     class="mt-3 rounded-xl max-h-72 object-cover w-full">
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($ap); ?>"
                                   class="mt-3 inline-flex items-center gap-2 text-blue-700 hover:underline text-sm"
                                   target="_blank" rel="noopener">
                                    &#x1F4CE; <?php echo htmlspecialchars(basename(parse_url($ap, PHP_URL_PATH) ?: $ap)); ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.max-w-5xl -->
</div>

<!-- Cover Photo Modal -->
<?php if (!empty($viewedUser['cover_pic'])): ?>
<div id="coverModal" class="fixed inset-0 bg-black/70 hidden z-50 items-center justify-center p-4">
    <button id="coverModalClose"
            class="absolute top-4 right-4 text-white bg-white/10 hover:bg-white/20 rounded-full p-2"
            aria-label="Close">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
    <img src="<?php echo htmlspecialchars($viewedUser['cover_pic']); ?>"
         alt="Cover photo"
         class="max-w-full max-h-full object-contain rounded-xl shadow-2xl">
</div>
<script>
(function () {
    const btn   = document.getElementById('coverPhotoBtn');
    const modal = document.getElementById('coverModal');
    const close = document.getElementById('coverModalClose');
    if (btn)   btn.addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
    if (close) close.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); });
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { modal.classList.add('hidden'); modal.classList.remove('flex'); } });
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
