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
    </script>
</body>
</html>

