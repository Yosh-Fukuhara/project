    <footer class="bg-gray-900 text-gray-400 py-12 mt-16">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-900 to-cyan-800 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white">CyberSphere</span>
                    </div>
                    <p class="text-sm">Your gateway to cybersecurity careers and education.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">For Job Seekers</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">Find Jobs</a></li>
                        <li><a href="#" class="hover:text-white transition">Browse Companies</a></li>
                        <li><a href="#" class="hover:text-white transition">Salary Guide</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">For Recruiters</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">Post a Job</a></li>
                        <li><a href="#" class="hover:text-white transition">Browse Talent</a></li>
                        <li><a href="#" class="hover:text-white transition">Interview Suite</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">Company</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">About</a></li>
                        <li><a href="#" class="hover:text-white transition">Blog</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-sm">
                <p>&copy; 2026 CyberSphere. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <script>
        (function () {
            const alerts = document.querySelectorAll('[data-autodismiss]');
            alerts.forEach((el) => {
                const ms = parseInt(el.getAttribute('data-autodismiss') || '5000', 10);
                if (!Number.isFinite(ms) || ms <= 0) return;

                setTimeout(() => {
                    el.style.transition = 'opacity 300ms ease';
                    el.style.opacity = '0';
                    setTimeout(() => {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    }, 350);
                }, ms);
            });
        })();
    </script>
</body>
</html>
