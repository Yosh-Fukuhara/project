<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../role_helpers.php';

// ── AJAX: Mark all notifications read (Global) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_notifs_read') {
    header('Content-Type: application/json');
    if (isset($_SESSION['notifications'])) {
        foreach ($_SESSION['notifications'] as &$n) { $n['read'] = true; }
        unset($n);
    }
    // Also update database
    if (isset($_SESSION['user'])) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->execute([$_SESSION['user']['user_id']]);
        } catch (Exception $e) {
            // Ignore database errors for now
        }
    }
    echo json_encode(['ok'=>true]);
    exit;
}

// ── AJAX: Mark single notification read ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_single_notif_read') {
    header('Content-Type: application/json');
    $notifId = $_POST['notification_id'] ?? '';
    if (isset($_SESSION['notifications']) && $notifId) {
        foreach ($_SESSION['notifications'] as &$n) { 
            if (isset($n['notification_id']) && $n['notification_id'] == $notifId) {
                $n['read'] = true;
            }
        }
        unset($n);
    }
    // Also update database
    if (isset($_SESSION['user']) && $notifId) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
            $stmt->execute([$notifId, $_SESSION['user']['user_id']]);
        } catch (Exception $e) {
            // Ignore database errors for now
        }
    }
    echo json_encode(['ok'=>true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'CyberSphere'; ?></title>
    
    <!-- Favicon - just replace the filename below! -->
    <?php 
    $faviconFilename = 'favicon.png'; // Change this to your favicon filename!
    $faviconPath = 'assets/' . $faviconFilename;
    ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($faviconPath); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars($faviconPath); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 sm:h-20">
                <!-- Logo & Brand -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="index.php" class="flex items-center gap-2 flex-shrink-0">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 bg-gradient-to-br from-blue-900 to-cyan-800 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <span class="text-lg sm:text-xl lg:text-2xl font-bold text-blue-900 truncate">CyberSphere</span>
                    </a>

                    <!-- Desktop Nav -->
                    <div class="hidden lg:flex items-center gap-6 ml-8">
                        <a href="index.php" class="text-base font-medium <?php echo (isset($currentPage) && $currentPage === 'home') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Jobs</a>
                        <a href="communities.php" class="text-base font-medium <?php echo (isset($currentPage) && $currentPage === 'communities') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Communities</a>
                        <a href="market.php" class="text-base font-medium <?php echo (isset($currentPage) && $currentPage === 'market') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Marketplace</a>
                        <?php if (cs_is_employer() || cs_is_admin()): ?>
                            <a href="employer_dashboard.php" class="text-base font-medium <?php echo (isset($currentPage) && $currentPage === 'employer') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Employer Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions & Mobile Menu -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Desktop Search -->
                    <form action="index.php" method="GET" class="hidden md:flex items-center bg-gray-100 rounded-full px-4 py-2 flex-1 max-w-md mx-4 transition-all focus-within:ring-2 focus-within:ring-blue-500">
                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" class="flex-1 bg-transparent border-0 focus:outline-none ml-2 text-sm lg:text-base">
                    </form>

                    <!-- Icons -->
                    <div class="flex items-center gap-1.5 sm:gap-2 lg:gap-3 flex-shrink-0">
                        <!-- Messages -->
                        <a href="messages.php" class="text-gray-700 hover:text-blue-900 relative p-1 sm:p-1.5 rounded-lg hover:bg-gray-100 transition">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <?php
                            $totalUnreadMessages = 0;
                            if (isset($_SESSION['user'])) {
                                try {
                                    $pdo = get_db_connection();
                                    $stmt = $pdo->prepare('SELECT COUNT(*) AS unread_count
                                        FROM messages m
                                        JOIN conversations c ON m.conversation_id = c.conversation_id
                                        WHERE m.is_read = 0
                                        AND m.sender_id != ?
                                        AND (c.user_a = ? OR c.user_b = ?)');
                                    $stmt->execute([
                                        $_SESSION['user']['user_id'],
                                        $_SESSION['user']['user_id'],
                                        $_SESSION['user']['user_id']
                                    ]);
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $totalUnreadMessages = $result['unread_count'] ?? 0;
                                } catch (Exception $e) {
                                    $totalUnreadMessages = 0;
                                }
                            }
                            ?>
                            <span class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center <?php echo $totalUnreadMessages === 0 ? 'hidden' : ''; ?>">
                                <?php echo $totalUnreadMessages > 9 ? '9+' : $totalUnreadMessages; ?>
                            </span>
                        </a>

                        <!-- Notifications -->
                        <button id="bellBtn" type="button" class="text-gray-700 hover:text-blue-900 relative p-1 sm:p-1.5 rounded-lg hover:bg-gray-100 transition cursor-pointer" aria-label="Notifications">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php
                            $unreadCount = 0;
                            if (!empty($_SESSION['notifications'])) {
                                foreach ($_SESSION['notifications'] as $n) {
                                    if (empty($n['read'])) $unreadCount++;
                                }
                            }
                            ?>
                            <span id="notifBadge" class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center <?php echo $unreadCount === 0 ? 'hidden' : ''; ?>">
                                <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                            </span>
                        </button>

                        <!-- Cart -->
                        <a href="cart.php" class="text-gray-700 hover:text-blue-900 relative p-1 sm:p-1.5 rounded-lg hover:bg-gray-100 transition <?php echo (isset($currentPage) && $currentPage === 'cart') ? 'text-pink-700' : ''; ?>">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                <span class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">
                                    <?php echo count($_SESSION['cart']); ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- User / Login -->
                        <?php if (isset($_SESSION['user'])): ?>
                            <div class="relative">
                                <button id="userMenuBtn" type="button" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gray-200 flex items-center justify-center text-lg sm:text-xl text-blue-900 font-bold hover:bg-gray-300 transition overflow-hidden" aria-label="User menu">
                                    <?php if (!empty($_SESSION['user']['profile_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['user']['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </button>
                                <div id="userMenu" class="absolute right-0 top-full mt-2 w-48 sm:w-56 bg-white rounded-lg shadow-xl py-2 hidden z-[60]">
                                    <a href="profile.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 text-sm">Profile</a>
                                    <?php if (cs_is_admin()): ?>
                                        <a href="admin/dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 text-sm">Admin Dashboard</a>
                                    <?php endif; ?>
                                    <?php if (cs_is_employer() || cs_is_admin()): ?>
                                        <a href="employer_dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 text-sm">Employer Dashboard</a>
                                    <?php endif; ?>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-gray-50 text-sm">Logout</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="bg-blue-900 text-white px-4 sm:px-6 py-2 rounded-lg font-medium hover:bg-blue-800 transition text-sm">
                                Sign In
                            </a>
                        <?php endif; ?>

                        <!-- Mobile Menu Button -->
                        <button id="mobileMenuBtn" type="button" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-700 transition" aria-label="Open menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Search -->
            <div class="md:hidden pb-3">
                <form action="index.php" method="GET" class="flex items-center bg-gray-100 rounded-full px-4 py-2">
                    <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" class="flex-1 bg-transparent border-0 focus:outline-none ml-2 text-sm">
                </form>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="lg:hidden hidden border-t border-gray-200 pb-6 pt-4 px-2 overflow-y-auto max-h-[calc(100vh-200px)] bg-white">
                <!-- Nav Links -->
                <div class="flex flex-col gap-1 mb-6">
                    <a href="index.php" class="px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium <?php echo (isset($currentPage) && $currentPage === 'home') ? 'text-blue-900 bg-blue-50' : ''; ?>">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.25A2.25 2.25 0 0118.75 15.5h-7.5a2.25 2.25 0 01-2.25-2.25v-4.5A2.25 2.25 0 0111.25 6.5h7.5a2.25 2.25 0 012.25 2.25v4.5zm-7.5 3.75h.008v.008H13.5v-.008z"></path>
                            </svg>
                            Jobs
                        </span>
                    </a>
                    <a href="communities.php" class="px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium <?php echo (isset($currentPage) && $currentPage === 'communities') ? 'text-blue-900 bg-blue-50' : ''; ?>">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Communities
                        </span>
                    </a>
                    <a href="market.php" class="px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium <?php echo (isset($currentPage) && $currentPage === 'market') ? 'text-blue-900 bg-blue-50' : ''; ?>">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Marketplace
                        </span>
                    </a>
                    <?php if (cs_is_employer() || cs_is_admin()): ?>
                        <div class="border-t border-gray-100 my-2"></div>
                        <a href="employer_dashboard.php" class="px-3 py-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium <?php echo (isset($currentPage) && $currentPage === 'employer') ? 'text-blue-900 bg-blue-50' : ''; ?>">
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"></path>
                                </svg>
                                Employer Dashboard
                            </span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Quick Access -->
                <div class="border-t border-gray-100 pt-4 mb-6">
                    <h3 class="font-bold text-sm text-gray-800 mb-3 px-3">Quick Access</h3>
                    <div class="space-y-3">
                        <!-- Jobs for You -->
                        <div>
                            <div class="flex items-center justify-between mb-2 px-3">
                                <h4 class="text-xs font-semibold text-gray-600">Jobs for You</h4>
                                <a href="jobs.php" class="text-xs text-blue-700 font-semibold">See all</a>
                            </div>
                            <div class="space-y-2">
                                <?php
                                $mobileJobs = [];
                                if (isset($_SESSION['posts'])) {
                                    foreach ($_SESSION['posts'] as $post) {
                                        if (($post['type'] ?? '') === 'user' && !empty($post['hiring'])) {
                                            $mobileJobs[] = $post;
                                        }
                                    }
                                }
                                if (empty($mobileJobs)) {
                                    $mobileJobs = [
                                        ['company' => 'NetSentinel Solutions', 'logo' => '🛡️', 'content' => 'Penetration Tester', 'time' => '2 days ago'],
                                        ['company' => 'SecureBank', 'logo' => '🏦', 'content' => 'GRC Specialist', 'time' => '4h ago'],
                                    ];
                                }
                                foreach (array_slice($mobileJobs, 0, 2) as $job):
                                    $jobTitle = htmlspecialchars(substr($job['content'] ?? 'Job Opening', 0, 30));
                                    $jobCompany = htmlspecialchars($job['company'] ?? $job['username'] ?? 'Company');
                                    $jobLogo = $job['logo'] ?? '💼';
                                ?>
                                <a href="jobs.php" class="block flex items-center gap-3 bg-gray-50 p-3 rounded-xl mx-1 hover:bg-gray-100 transition">
                                    <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center text-lg"><?php echo $jobLogo; ?></div>
                                    <div class="min-w-0">
                                        <h4 class="font-semibold text-gray-800 text-xs truncate"><?php echo $jobTitle; ?></h4>
                                        <p class="text-gray-500 text-[10px] truncate"><?php echo $jobCompany; ?></p>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div id="notifPanel" class="fixed top-14 right-4 w-80 bg-white rounded-2xl shadow-2xl border border-gray-200 hidden z-50 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h4 class="font-bold text-gray-900 text-sm">Notifications</h4>
            <button id="markAllRead" class="text-xs text-blue-700 hover:underline font-semibold">Mark all read</button>
        </div>
        <div id="notifList" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <?php if (empty($_SESSION['notifications'])): ?>
            <p class="text-gray-400 text-sm px-4 py-6 text-center">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($_SESSION['notifications'] as $notif): ?>
                <div class="px-4 py-3 text-sm <?php echo $notif['read'] ? 'bg-white text-gray-600' : 'bg-blue-50 text-gray-800 font-medium'; ?> cursor-pointer hover:bg-gray-50" 
                    data-notification-id="<?php echo htmlspecialchars($notif['notification_id'] ?? ''); ?>"
                    onclick="handleNotifClick(this, <?php echo !empty($notif['link']) ? "'" . htmlspecialchars($notif['link']) . "'" : 'null'; ?>)">
                    <p><?php echo htmlspecialchars($notif['msg']); ?></p>
                    <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($notif['time']); ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <style>
        /* Prevent layout shifts and stabilize page */
        * {
            box-sizing: border-box;
        }
        html {
            scroll-padding-top: 80px;
        }
        body {
            scroll-behavior: smooth;
            overflow-y: scroll; /* Always show scrollbar to prevent shift */
        }
        /* Ensure badges don't cause layout shifts */
        .flex-shrink-0 {
            flex-shrink: 0 !important;
        }
        /* Prevent panel from causing layout shift */
        #notifPanel, #userMenu, #mobileMenu {
            transform: translateZ(0);
        }
    </style>
    <script>
        // Define global helpers for notifications (used by components updating states via AJAX)
        window.updateBadge = function(count) {
            const badge = document.getElementById('notifBadge');
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        };

        // prependNotif(msg, link?) -> adds a clickable notif that can deep-link.
        window.prependNotif = function(msg, link) {
            const list = document.getElementById('notifList');
            if (!list) return;
            const empty = list.querySelector('p');
            if (empty && empty.textContent.includes('No notifications yet')) empty.remove();
            const el = document.createElement('div');
            el.className = 'px-4 py-3 text-sm bg-blue-50 text-gray-800 font-medium' + (link ? ' cursor-pointer hover:bg-gray-50' : '');
            const now = new Date().toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
            el.innerHTML = `<p>${msg}</p><p class="text-xs text-gray-400 mt-0.5">${now}</p>`;
            if (link) {
                el.addEventListener('click', () => { window.location.href = link; });
            }
            list.prepend(el);
        };

        // Handle clicking a notification
        window.handleNotifClick = function(el, link) {
            const notifId = el.dataset.notificationId;
            if (notifId) {
                // Mark as read first
                fetch('', { 
                    method: 'POST', 
                    headers: {'Content-Type':'application/x-www-form-urlencoded'}, 
                    body: 'action=mark_single_notif_read&notification_id=' + encodeURIComponent(notifId) 
                }).then(() => {
                    // Update the UI immediately
                    el.classList.remove('bg-blue-50', 'font-medium');
                    el.classList.add('bg-white', 'text-gray-600');
                    // Update badge count
                    updateBadgeFromSession();
                });
            }
            if (link) {
                setTimeout(() => {
                    window.location.href = link;
                }, 100);
            }
        };

        // Update badge from session (for when a single notif is marked read)
        window.updateBadgeFromSession = function() {
            let unreadCount = 0;
            <?php 
            if (isset($_SESSION['notifications'])) {
                foreach ($_SESSION['notifications'] as $n) {
                    if (empty($n['read'])) {
                        $unreadCount++;
                    }
                }
            }
            ?>
            unreadCount = <?php echo $unreadCount; ?>;
            window.updateBadge(unreadCount);
        };

        (function () {
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const userBtn = document.getElementById('userMenuBtn');
            const userMenu = document.getElementById('userMenu');
            
            // Notification Elements
            const bellBtn = document.getElementById('bellBtn');
            const notifPanel = document.getElementById('notifPanel');
            const markAllReadBtn = document.getElementById('markAllRead');

            function toggle(el) {
                if (!el) return;
                el.classList.toggle('hidden');
            }

            if (mobileBtn && mobileMenu) {
                mobileBtn.addEventListener('click', () => toggle(mobileMenu));
            }

            if (userBtn && userMenu) {
                userBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    toggle(userMenu);
                });
            }

            if (bellBtn && notifPanel) {
                bellBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggle(notifPanel);
                });
            }

            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', () => {
                    fetch('', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=mark_notifs_read' })
                        .then(() => {
                            document.querySelectorAll('#notifList > div').forEach(d => {
                                d.classList.remove('bg-blue-50','font-medium');
                                d.classList.add('bg-white','text-gray-600');
                            });
                            window.updateBadge(0);
                        });
                });
            }

            document.addEventListener('click', (e) => {
                if (userMenu && !userMenu.classList.contains('hidden')) {
                    if (!e.target.closest('#userMenu') && !e.target.closest('#userMenuBtn')) {
                        userMenu.classList.add('hidden');
                    }
                }
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    if (!e.target.closest('#mobileMenu') && !e.target.closest('#mobileMenuBtn')) {
                        // don't auto-close while clicking a link inside menu
                    }
                }
                if (notifPanel && !notifPanel.classList.contains('hidden')) {
                    if (!e.target.closest('#notifPanel') && !e.target.closest('#bellBtn')) {
                        notifPanel.classList.add('hidden');
                    }
                }
            });
        })();
    </script>
