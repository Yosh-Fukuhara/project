<?php
require_once __DIR__ . '/bootstrap.php';

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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-3 sm:px-4">
            <div class="flex justify-between items-center h-16 md:h-20">
                <div class="flex items-center gap-3 sm:gap-6 md:gap-8 min-w-0">
                    <a href="index.php" class="flex items-center gap-2">
                        <div class="w-10 h-10 md:w-12 md:h-12 bg-gradient-to-br from-blue-900 to-cyan-800 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 md:w-8 md:h-8 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <span class="text-xl sm:text-2xl font-bold text-blue-900 truncate">CyberSphere</span>
                    </a>
                    <div class="hidden md:flex items-center gap-8">
                        <a href="index.php" class="text-lg font-medium <?php echo (isset($currentPage) && $currentPage === 'home') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Jobs</a>
                        <a href="communities.php" class="text-lg font-medium <?php echo (isset($currentPage) && $currentPage === 'communities') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Communities</a>
                        <a href="market.php" class="text-lg font-medium <?php echo (isset($currentPage) && $currentPage === 'market') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Marketplace</a>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 md:gap-4 flex-1 justify-end">
                    <form action="index.php" method="GET" class="flex items-center bg-gray-100 rounded-full px-3 py-1.5 md:px-4 md:py-2 w-full max-w-[120px] sm:max-w-xs md:max-w-md transition-all focus-within:max-w-md">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" class="flex-1 bg-transparent border-0 focus:outline-none ml-2 text-sm md:text-base min-w-0">
                    </form>
                    
                    <div class="flex items-center gap-1.5 sm:gap-2 md:gap-4 flex-shrink-0">
                        <a href="messages.php" class="text-gray-700 hover:text-blue-900 relative">
                            <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <?php
                            $totalUnreadMessages = 0;
                            if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
                                foreach ($_SESSION['messages'] as $conv) {
                                    $totalUnreadMessages += $conv['unread'] ?? 0;
                                }
                            }
                            ?>
                            <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 md:w-5 md:h-5 flex items-center justify-center pointer-events-none <?php echo $totalUnreadMessages === 0 ? 'hidden' : ''; ?>">
                                <?php echo $totalUnreadMessages > 9 ? '9+' : $totalUnreadMessages; ?>
                            </span>
                        </a>
                        <button id="bellBtn" type="button" class="text-gray-700 hover:text-blue-900 relative p-0 bg-transparent border-0 cursor-pointer" aria-label="Notifications">
                            <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            <span id="notifBadge" class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 md:w-5 md:h-5 flex items-center justify-center pointer-events-none <?php echo $unreadCount === 0 ? 'hidden' : ''; ?>">
                                <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                            </span>
                        </button>
                        <a href="cart.php" class="text-gray-700 hover:text-blue-900 relative <?php echo (isset($currentPage) && $currentPage === 'cart') ? 'text-pink-700' : ''; ?>">
                            <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] w-4 h-4 md:w-5 md:h-5 rounded-full flex items-center justify-center">
                                    <?php echo count($_SESSION['cart']); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php if (isset($_SESSION['user'])): ?>
                            <div class="relative">
                                <button id="userMenuBtn" type="button" class="w-8 h-8 md:w-11 md:h-11 rounded-full bg-gray-200 flex items-center justify-center text-xl md:text-2xl text-blue-900 font-bold hover:bg-gray-300 transition overflow-hidden" aria-label="User menu">
                                    <?php if (!empty($_SESSION['user']['profile_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['user']['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </button>
                                <div id="userMenu" class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg py-2 hidden">
                                    <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                                    <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">Logout</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="bg-blue-900 text-white px-3 sm:px-5 py-1.5 rounded-lg font-medium hover:bg-blue-800 transition text-xs sm:text-sm">
                                Sign In
                            </a>
                        <?php endif; ?>

                        <button id="mobileMenuBtn" type="button" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 text-gray-700" aria-label="Open menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div id="mobileMenu" class="md:hidden hidden border-t border-gray-200 pb-6 pt-3 px-2 overflow-y-auto max-h-[calc(100vh-80px)]">
                <div class="flex flex-col gap-2 mb-6">
                    <a href="index.php" class="px-2 py-2 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo (isset($currentPage) && $currentPage === 'home') ? 'font-semibold text-blue-900 bg-blue-50' : ''; ?>">Jobs</a>
                    <a href="communities.php" class="px-2 py-2 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo (isset($currentPage) && $currentPage === 'communities') ? 'font-semibold text-blue-900 bg-blue-50' : ''; ?>">Communities</a>
                    <a href="market.php" class="px-2 py-2 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo (isset($currentPage) && $currentPage === 'market') ? 'font-semibold text-blue-900 bg-blue-50' : ''; ?>">Marketplace</a>
                </div>

                <!-- Mobile: Jobs for You -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3 px-2">
                        <h3 class="font-bold text-base text-gray-800">Jobs for You</h3>
                        <a href="index.php?filter=jobs" class="text-xs text-blue-800 font-semibold hover:underline">See all</a>
                    </div>
                    <div class="space-y-3">
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
                        <div class="flex items-center gap-3 bg-gray-50 p-3 rounded-xl">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0 text-xl"><?php echo $jobLogo; ?></div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-gray-800 text-sm truncate"><?php echo $jobTitle; ?></h4>
                                <p class="text-gray-500 text-xs truncate"><?php echo $jobCompany; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Mobile: Trending Skills -->
                <div>
                    <div class="flex items-center justify-between mb-3 px-2">
                        <h3 class="font-bold text-base text-gray-800">Trending Skills</h3>
                        <a href="skills.php" class="text-xs text-blue-800 font-semibold hover:underline">Explore</a>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <?php
                        $mobileSkills = [
                            ['name' => 'Penetration Testing', 'count' => rand(5000, 20000)],
                            ['name' => 'Zero Trust Architecture', 'count' => rand(5000, 20000)],
                            ['name' => 'AI Threat Detection', 'count' => rand(5000, 20000)],
                        ];
                        foreach (array_slice($mobileSkills, 0, 3) as $skill):
                            $countFormatted = number_format($skill['count'] / 1000, 1) . 'k';
                        ?>
                        <div class="bg-gray-50 p-3 rounded-xl">
                            <p class="text-gray-800 font-medium text-sm"><?php echo htmlspecialchars($skill['name']); ?></p>
                            <p class="text-gray-400 text-[10px]"><?php echo $countFormatted; ?> professionals</p>
                        </div>
                        <?php endforeach; ?>
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
