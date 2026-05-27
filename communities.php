<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Communities - CyberSphere';
$currentPage = 'communities';

$meEmail = $_SESSION['user']['email'] ?? '';
$meName  = $_SESSION['user']['username'] ?? 'User';

function cs_now_label(): string {
    return date('M j, Y g:i A');
}

function cs_make_id(string $prefix): string {
    return $prefix . '_' . uniqid('', true);
}

// Seed communities
if (!isset($_SESSION['communities']) || !is_array($_SESSION['communities'])) {
    $_SESSION['communities'] = [
        [
            'id' => 'c_cybernews',
            'name' => 'Cyber News & Updates',
            'type' => 'group',
            'description' => 'Daily cybersecurity news, CVEs, and threat intel discussions.',
            'created_by' => 'system',
            'created_at' => 'May 1, 2026',
            'members' => 1240,
        ],
        [
            'id' => 'c_dfir',
            'name' => 'DFIR Philippines',
            'type' => 'organization',
            'description' => 'Incident response and forensics community for PH practitioners.',
            'created_by' => 'system',
            'created_at' => 'Apr 19, 2026',
            'members' => 620,
        ],
        [
            'id' => 'c_pentest',
            'name' => 'PenTest Lab',
            'type' => 'group',
            'description' => 'Share writeups, tools, and lab tips. Beginner-friendly.',
            'created_by' => 'system',
            'created_at' => 'Mar 12, 2026',
            'members' => 980,
        ],
    ];
}

if (!isset($_SESSION['joined_communities']) || !is_array($_SESSION['joined_communities'])) {
    $_SESSION['joined_communities'] = [];
}
if (!isset($_SESSION['community_posts']) || !is_array($_SESSION['community_posts'])) {
    $_SESSION['community_posts'] = [];
}

// Ensure current user has a joined list
if (!isset($_SESSION['joined_communities'][$meEmail]) || !is_array($_SESSION['joined_communities'][$meEmail])) {
    $_SESSION['joined_communities'][$meEmail] = [];
}

function cs_find_community_index(string $cid): ?int {
    foreach (($_SESSION['communities'] ?? []) as $i => $c) {
        if (($c['id'] ?? '') === $cid) return $i;
    }
    return null;
}

function cs_is_joined(string $email, string $cid): bool {
    return in_array($cid, $_SESSION['joined_communities'][$email] ?? [], true);
}

$errors = [];

// ── Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_community') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'group';
        $desc = trim($_POST['description'] ?? '');

        if ($name === '') $errors[] = 'Community name is required.';
        if (!in_array($type, ['group','organization'], true)) $errors[] = 'Invalid community type.';
        if (cs_strlen($name) > 60) $errors[] = 'Community name is too long (max 60 chars).';
        if (cs_strlen($desc) > 240) $errors[] = 'Description is too long (max 240 chars).';

        // Ensure unique name (simple check)
        foreach ($_SESSION['communities'] as $c) {
            if (strcasecmp($c['name'] ?? '', $name) === 0) {
                $errors[] = 'A community with that name already exists.';
                break;
            }
        }

        if (empty($errors)) {
            $cid = cs_make_id('c');
            array_unshift($_SESSION['communities'], [
                'id' => $cid,
                'name' => $name,
                'type' => $type,
                'description' => $desc,
                'created_by' => $meEmail,
                'created_at' => cs_now_label(),
                'members' => 1,
            ]);

            // Auto-join creator
            $_SESSION['joined_communities'][$meEmail][] = $cid;

            // Notification
            array_unshift($_SESSION['notifications'], [
                'msg' => 'You created a new community: ' . htmlspecialchars($name),
                'time' => cs_now_label(),
                'read' => false,
                'link' => 'communities.php?c=' . urlencode($cid),
            ]);

            header('Location: communities.php?c=' . urlencode($cid) . '&created=1');
            exit;
        }
    }

    if ($action === 'join_community') {
        $cid = $_POST['community_id'] ?? '';
        $idx = $cid ? cs_find_community_index($cid) : null;
        if ($idx === null) $errors[] = 'Community not found.';

        if (empty($errors)) {
            if (!cs_is_joined($meEmail, $cid)) {
                $_SESSION['joined_communities'][$meEmail][] = $cid;
                $_SESSION['communities'][$idx]['members'] = (int)($_SESSION['communities'][$idx]['members'] ?? 0) + 1;
            }
            header('Location: communities.php?c=' . urlencode($cid));
            exit;
        }
    }

    if ($action === 'leave_community') {
        $cid = $_POST['community_id'] ?? '';
        $idx = $cid ? cs_find_community_index($cid) : null;
        if ($idx === null) $errors[] = 'Community not found.';

        if (empty($errors)) {
            $list = $_SESSION['joined_communities'][$meEmail] ?? [];
            $pos = array_search($cid, $list, true);
            if ($pos !== false) {
                array_splice($_SESSION['joined_communities'][$meEmail], $pos, 1);
                $_SESSION['communities'][$idx]['members'] = max(0, (int)($_SESSION['communities'][$idx]['members'] ?? 0) - 1);
            }
            header('Location: communities.php');
            exit;
        }
    }

    if ($action === 'create_community_post') {
        $cid = $_POST['community_id'] ?? '';
        $idx = $cid ? cs_find_community_index($cid) : null;
        $content = trim($_POST['content'] ?? '');

        if ($idx === null) $errors[] = 'Community not found.';
        if (!cs_is_joined($meEmail, $cid)) $errors[] = 'Join the community to post.';
        if ($content === '') $errors[] = 'Post content is empty.';
        if (cs_strlen($content) > 2000) $errors[] = 'Post is too long (max 2000 chars).';

        if (empty($errors)) {
            if (!isset($_SESSION['community_posts'][$cid]) || !is_array($_SESSION['community_posts'][$cid])) {
                $_SESSION['community_posts'][$cid] = [];
            }
            $postId = cs_make_id('cp');
            array_unshift($_SESSION['community_posts'][$cid], [
                'id' => $postId,
                'community_id' => $cid,
                'user' => $meName,
                'email' => $meEmail,
                'avatar' => $_SESSION['user']['profile_pic'] ?? null,
                'time' => cs_now_label(),
                'content' => $content,
            ]);

            header('Location: communities.php?c=' . urlencode($cid) . '&post=' . urlencode($postId));
            exit;
        }
    }
}

// ── View ──
$activeCommunityId = $_GET['c'] ?? '';
$activeCommunity = null;
if ($activeCommunityId) {
    $idx = cs_find_community_index($activeCommunityId);
    if ($idx !== null) $activeCommunity = $_SESSION['communities'][$idx];
}

$search = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'all';
$allowedViews = ['all','joined','recommended','groups','organizations'];
if (!in_array($view, $allowedViews, true)) $view = 'all';

$communitiesAll = $_SESSION['communities'];
if ($search !== '') {
    $communitiesAll = array_values(array_filter($communitiesAll, function ($c) use ($search) {
        $name = $c['name'] ?? '';
        $desc = $c['description'] ?? '';
        return stripos($name . ' ' . $desc, $search) !== false;
    }));
}

$joined = $_SESSION['joined_communities'][$meEmail] ?? [];

// Helper: build links while preserving current search/community selection
function cs_build_communities_url(array $params): string {
    $q = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'communities.php' . (empty($q) ? '' : ('?' . http_build_query($q)));
}

// Filter list by view (Reddit-like sidebar)
$communities = $communitiesAll;
if ($view === 'joined') {
    $communities = array_values(array_filter($communities, function ($c) use ($joined) {
        return in_array($c['id'] ?? '', $joined, true);
    }));
} elseif ($view === 'recommended') {
    $communities = array_values(array_filter($communities, function ($c) use ($joined) {
        return !in_array($c['id'] ?? '', $joined, true);
    }));
} elseif ($view === 'groups') {
    $communities = array_values(array_filter($communities, function ($c) {
        return (($c['type'] ?? 'group') === 'group');
    }));
} elseif ($view === 'organizations') {
    $communities = array_values(array_filter($communities, function ($c) {
        return (($c['type'] ?? 'group') === 'organization');
    }));
}

// Sorting:
// - recommended: by members desc
// - others: joined first, then members desc
usort($communities, function ($a, $b) use ($joined, $view) {
    if ($view !== 'recommended') {
        $aj = in_array($a['id'] ?? '', $joined, true) ? 1 : 0;
        $bj = in_array($b['id'] ?? '', $joined, true) ? 1 : 0;
        if ($aj !== $bj) return $bj <=> $aj;
    }
    return (int)($b['members'] ?? 0) <=> (int)($a['members'] ?? 0);
});

// Counts for sidebar chips
$countAll = count($communitiesAll);
$countJoined = 0;
$countRecommended = 0;
$countGroups = 0;
$countOrgs = 0;
foreach ($communitiesAll as $c) {
    $cid = $c['id'] ?? '';
    $isJ = $cid && in_array($cid, $joined, true);
    if ($isJ) $countJoined++;
    else $countRecommended++;
    if (($c['type'] ?? 'group') === 'group') $countGroups++;
    if (($c['type'] ?? 'group') === 'organization') $countOrgs++;
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left: List -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-bold text-gray-900">Communities</h2>
                    <button id="openCreateCommunity" type="button" class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-3 py-2 rounded-xl transition">
                        Create
                    </button>
                </div>

                <div class="p-4 border-b border-gray-200">
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search communities..." class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                        <?php if (!empty($activeCommunityId)): ?>
                            <input type="hidden" name="c" value="<?php echo htmlspecialchars($activeCommunityId); ?>">
                        <?php endif; ?>
                        <button class="bg-gray-900 hover:bg-gray-800 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>
                    </form>
                </div>

                <!-- Sidebar tabs (Reddit-like) -->
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <?php
                        $base = ['q' => $search, 'c' => $activeCommunityId ?: null];
                        $tabClass = function($key) use ($view) {
                            return $view === $key
                                ? 'bg-blue-900 text-white border-blue-900'
                                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50';
                        };
                    ?>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'all'])); ?>"
                           class="text-xs font-semibold px-3 py-2 rounded-xl border transition <?php echo $tabClass('all'); ?>">
                            All <span class="opacity-80">(<?php echo $countAll; ?>)</span>
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'joined'])); ?>"
                           class="text-xs font-semibold px-3 py-2 rounded-xl border transition <?php echo $tabClass('joined'); ?>">
                            Joined <span class="opacity-80">(<?php echo $countJoined; ?>)</span>
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'recommended'])); ?>"
                           class="text-xs font-semibold px-3 py-2 rounded-xl border transition <?php echo $tabClass('recommended'); ?>">
                            Recommended <span class="opacity-80">(<?php echo $countRecommended; ?>)</span>
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'groups'])); ?>"
                           class="text-xs font-semibold px-3 py-2 rounded-xl border transition <?php echo $tabClass('groups'); ?>">
                            Groups <span class="opacity-80">(<?php echo $countGroups; ?>)</span>
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'organizations'])); ?>"
                           class="text-xs font-semibold px-3 py-2 rounded-xl border transition <?php echo $tabClass('organizations'); ?>">
                            Orgs <span class="opacity-80">(<?php echo $countOrgs; ?>)</span>
                        </a>
                        <button type="button" class="text-xs font-semibold px-3 py-2 rounded-xl border border-dashed border-gray-300 text-gray-500 cursor-default">
                            Filter
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">
                        <?php
                            $labelMap = [
                                'all' => 'Showing all communities',
                                'joined' => 'Showing communities you joined',
                                'recommended' => 'Showing recommended communities',
                                'groups' => 'Showing groups',
                                'organizations' => 'Showing organizations',
                            ];
                            echo htmlspecialchars($labelMap[$view] ?? 'Showing communities');
                        ?>
                    </p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="p-4 bg-red-50 border-b border-red-200 text-red-700 text-sm">
                        <?php foreach ($errors as $e): ?>
                            <p><?php echo htmlspecialchars($e); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="divide-y divide-gray-100">
                    <?php if (empty($communities)): ?>
                        <div class="p-6 text-center text-sm text-gray-500">
                            No communities found.
                        </div>
                    <?php endif; ?>
                    <?php foreach ($communities as $c): ?>
                        <?php
                            $cid = $c['id'];
                            $isJoined = cs_is_joined($meEmail, $cid);
                            $isActive = $activeCommunityId === $cid;
                            $typeLabel = ($c['type'] ?? 'group') === 'organization' ? 'Organization' : 'Group';
                        ?>
                        <a href="communities.php?c=<?php echo urlencode($cid); ?>" class="block p-4 hover:bg-gray-50 transition <?php echo $isActive ? 'bg-blue-50' : ''; ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-blue-100 text-blue-900 font-bold flex-shrink-0">
                                            <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($c['name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $typeLabel; ?> • <?php echo number_format((int)($c['members'] ?? 0)); ?> members</p>
                                        </div>
                                    </div>
                                    <?php if (!empty($c['description'])): ?>
                                        <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?php echo htmlspecialchars($c['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isJoined): ?>
                                    <span class="text-xs font-bold text-green-700 bg-green-100 px-2 py-1 rounded-full flex-shrink-0">Joined</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Detail -->
        <div class="lg:col-span-8">
            <?php if (!$activeCommunity): ?>
                <div class="bg-white rounded-2xl shadow-md p-10 text-center">
                    <div class="text-4xl mb-3">👥</div>
                    <h3 class="text-xl font-bold text-gray-900">Pick a community</h3>
                    <p class="text-gray-500 mt-2">Join groups or organizations and start posting like Reddit communities.</p>
                </div>
            <?php else: ?>
                <?php
                    $cid = $activeCommunity['id'];
                    $isJoined = cs_is_joined($meEmail, $cid);
                    $typeLabel = ($activeCommunity['type'] ?? 'group') === 'organization' ? 'Organization' : 'Group';
                    $posts = $_SESSION['community_posts'][$cid] ?? [];
                ?>
                <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                    <div class="p-5 border-b border-gray-200 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-900 to-cyan-800 text-white flex items-center justify-center font-black text-xl flex-shrink-0">
                                    <?php echo strtoupper(substr($activeCommunity['name'], 0, 1)); ?>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-2xl font-bold text-gray-900 truncate"><?php echo htmlspecialchars($activeCommunity['name']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo $typeLabel; ?> • <?php echo number_format((int)($activeCommunity['members'] ?? 0)); ?> members • Created <?php echo htmlspecialchars($activeCommunity['created_at'] ?? ''); ?></p>
                                </div>
                            </div>
                            <?php if (!empty($activeCommunity['description'])): ?>
                                <p class="mt-3 text-gray-700"><?php echo htmlspecialchars($activeCommunity['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <?php if (!$isJoined): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="join_community">
                                    <input type="hidden" name="community_id" value="<?php echo htmlspecialchars($cid); ?>">
                                    <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-4 py-2 rounded-xl transition text-sm">Join</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" onsubmit="return confirm('Leave this community?');">
                                    <input type="hidden" name="action" value="leave_community">
                                    <input type="hidden" name="community_id" value="<?php echo htmlspecialchars($cid); ?>">
                                    <button class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 font-semibold px-4 py-2 rounded-xl transition text-sm">Joined ✓</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-5 border-b border-gray-100 bg-gray-50">
                        <?php if (!$isJoined): ?>
                            <p class="text-sm text-gray-600">Join this community to create posts.</p>
                        <?php else: ?>
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="action" value="create_community_post">
                                <input type="hidden" name="community_id" value="<?php echo htmlspecialchars($cid); ?>">
                                <textarea name="content" rows="3" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Create a post..."></textarea>
                                <div class="flex justify-end">
                                    <button class="bg-blue-900 hover:bg-blue-800 text-white font-bold px-5 py-2 rounded-xl transition text-sm">Post</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="p-5 space-y-4">
                        <?php if (empty($posts)): ?>
                            <div class="text-center py-10">
                                <div class="text-3xl mb-2">🗨️</div>
                                <p class="text-gray-500">No posts yet. Be the first to post!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $p): ?>
                                <div class="border border-gray-200 rounded-2xl p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-900 font-bold flex items-center justify-center overflow-hidden flex-shrink-0">
                                                <?php if (!empty($p['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($p['avatar']); ?>" alt="" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($p['user'] ?? 'U', 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($p['user'] ?? 'User'); ?></p>
                                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($p['time'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-gray-800 whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($p['content'] ?? '')); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Community Modal -->
<div id="createCommunityBackdrop" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Create a community</h3>
            <button id="closeCreateCommunity" type="button" class="text-gray-500 hover:text-gray-800" aria-label="Close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="create_community">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Name</label>
                <input type="text" name="name" maxlength="60" required class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="e.g., Blue Team PH">
                <p class="text-xs text-gray-400 mt-1">Max 60 characters.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="group">Group</option>
                    <option value="organization">Organization</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" maxlength="240" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="What is this community about?"></textarea>
                <p class="text-xs text-gray-400 mt-1">Optional. Max 240 characters.</p>
            </div>
            <button type="submit" class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                Create
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const openBtn = document.getElementById('openCreateCommunity');
    const closeBtn = document.getElementById('closeCreateCommunity');
    const backdrop = document.getElementById('createCommunityBackdrop');

    function openModal() {
        if (!backdrop) return;
        backdrop.classList.remove('hidden');
        backdrop.classList.add('flex');
        const first = backdrop.querySelector('input[name="name"]');
        if (first) setTimeout(() => first.focus(), 50);
    }
    function closeModal() {
        if (!backdrop) return;
        backdrop.classList.add('hidden');
        backdrop.classList.remove('flex');
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
})();
</script>

<?php include 'includes/footer.php'; ?>
