        </main>
    </div>

    <script>
        const btn = document.getElementById('adminMobileMenuBtn');
        const drawer = document.getElementById('adminMobileDrawer');
        const overlay = document.getElementById('adminMobileOverlay');
        if (btn && drawer && overlay) {
            const close = () => {
                drawer.classList.add('translate-x-full');
                overlay.classList.add('hidden');
            };
            const open = () => {
                drawer.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
            };
            btn.addEventListener('click', open);
            overlay.addEventListener('click', close);
        }

        // ADMIN INACTIVITY TIMER - 60 SECONDS
        let adminInactivityTimer;
        const ADMIN_INACTIVITY_TIMEOUT = 60 * 1000; // 60 seconds

        function resetAdminInactivityTimer() {
            clearTimeout(adminInactivityTimer);
            adminInactivityTimer = setTimeout(logoutAdmin, ADMIN_INACTIVITY_TIMEOUT);
        }

        function logoutAdmin() {
            window.location.href = 'logout.php';
        }

        // Listen for any user activity to reset the timer
        const adminEvents = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click'];
        adminEvents.forEach(event => {
            document.addEventListener(event, resetAdminInactivityTimer, true);
        });

        // Start the timer when the page loads
        resetAdminInactivityTimer();
    </script>
</body>
</html>

