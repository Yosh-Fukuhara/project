<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (file_exists(__DIR__ . '/../role_helpers.php')) {
    require_once __DIR__ . '/../role_helpers.php';
} elseif (file_exists(__DIR__ . '/role_helpers.php')) {
    require_once __DIR__ . '/role_helpers.php';
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
                        <a href="#" class="text-lg font-medium text-gray-700 hover:text-blue-900">Communities</a>
                        <a href="market.php" class="text-lg font-medium <?php echo (isset($currentPage) && $currentPage === 'market') ? 'text-blue-900 border-b-2 border-blue-900 pb-1' : 'text-gray-700 hover:text-blue-900'; ?>">Marketplace</a>
                        <?php if (isset($_SESSION['user']) && function_exists('cs_is_employer') && cs_is_employer()): ?>
                        <a href="employer_dashboard.php" class="text-lg font-medium <?php echo (isset($currentPage) && $currentPage === 'employer_dashboard') ? 'text-purple-700 border-b-2 border-purple-700 pb-1' : 'text-purple-700 hover:text-purple-900 font-semibold'; ?>">Employer</a>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['user']) && function_exists('cs_is_admin') && cs_is_admin()): ?>
                        <a href="admin.php" class="text-lg font-medium <?php echo (isset($currentPage) && $currentPage === 'admin') ? 'text-red-700 border-b-2 border-red-700 pb-1' : 'text-red-600 hover:text-red-800 font-semibold'; ?>">Admin</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 md:gap-4">
                    <div class="hidden lg:flex items-center bg-gray-100 rounded-full px-4 py-2 w-full max-w-md min-w-0">
                        <svg class="w-6 h-6 md:w-8 md:h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" placeholder="Search..." class="flex-1 bg-transparent border-0 focus:outline-none ml-2 text-base md:text-lg min-w-0">
                    </div>
                    <div class="flex items-center gap-1 sm:gap-2 md:gap-4">
                        <!-- Mobile menu button -->
                        <button id="mobileMenuBtn" type="button" class="md:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-700" aria-label="Open menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>

                        <a href="#" class="text-gray-700 hover:text-blue-900">
                            <svg class="w-7 h-7 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012-2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-700 hover:text-blue-900 relative">
                            <svg class="w-7 h-7 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </a>
                        <a href="cart.php" class="text-gray-700 hover:text-blue-900 relative <?php echo (isset($currentPage) && $currentPage === 'cart') ? 'text-pink-700' : ''; ?>">
                            <svg class="w-7 h-7 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-pink-700 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">
                                    <?php echo count($_SESSION['cart']); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php if (isset($_SESSION['user'])): ?>
                            <div class="relative">
                                <button id="userMenuBtn" type="button" class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-gray-200 flex items-center justify-center text-2xl md:text-3xl text-blue-900 font-bold hover:bg-gray-300 transition overflow-hidden" aria-label="User menu">
                                    <?php if (!empty($_SESSION['user']['profile_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['user']['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </button>
                                <div id="userMenu" class="absolute right-0 top-full mt-2 w-56 bg-white rounded-lg shadow-lg py-2 hidden z-50">
                                    <?php if (function_exists('cs_role')): ?>
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <?php echo cs_role_badge(cs_role(), !empty($_SESSION['user']['employer_verified'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                                    <?php if (function_exists('cs_is_employer') && cs_is_employer()): ?>
                                    <a href="employer_dashboard.php" class="block px-4 py-2 text-purple-700 font-semibold hover:bg-purple-50">Employer Dashboard</a>
                                    <?php elseif (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] === 'applicant'): ?>
                                    <a href="employer_apply.php" class="block px-4 py-2 text-gray-500 hover:bg-gray-100 text-sm">Apply as Employer</a>
                                    <?php endif; ?>
                                    <?php if (function_exists('cs_is_admin') && cs_is_admin()): ?>
                                    <a href="admin.php" class="block px-4 py-2 text-red-600 font-semibold hover:bg-red-50">Admin Panel</a>
                                    <?php endif; ?>
                                    <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">Logout</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="bg-blue-900 text-white px-4 sm:px-6 py-2 rounded-lg font-medium hover:bg-blue-800 transition text-sm sm:text-base">
                                Sign In
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mobile menu (links) -->
            <div id="mobileMenu" class="md:hidden hidden border-t border-gray-200 pb-3 pt-3">
                <div class="flex flex-col gap-2">
                    <a href="index.php" class="px-2 py-2 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo (isset($currentPage) && $currentPage === 'home') ? 'font-semibold text-blue-900' : ''; ?>">Jobs</a>
                    <a href="#" class="px-2 py-2 rounded-lg text-gray-700 hover:bg-gray-100">Communities</a>
                    <a href="market.php" class="px-2 py-2 rounded-lg text-gray-700 hover:bg-gray-100 <?php echo (isset($currentPage) && $currentPage === 'market') ? 'font-semibold text-blue-900' : ''; ?>">Marketplace</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        (function () {
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const userBtn = document.getElementById('userMenuBtn');
            const userMenu = document.getElementById('userMenu');

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
            });
        })();
    </script>
