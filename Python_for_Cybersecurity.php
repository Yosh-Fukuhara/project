<?php
require_once 'includes/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'Python_for_Cybersecurity.php';
    header('Location: login.php');
    exit;
}

$pageTitle = 'Python for Cybersecurity - CyberSphere';
$currentPage = 'market';

include 'includes/header.php';
?>

<div class="bg-blue-900 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl">
            <h1 class="text-4xl sm:text-5xl font-bold mb-4">Python for Cybersecurity</h1>
            <p class="text-xl text-blue-200 mb-6">Automate security tasks, develop offensive tools, and analyze data like a pro.</p>
            <div class="flex flex-wrap gap-4">
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Course</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">25 Hours</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Code-along Projects</span>
            </div>
        </div>
    </div>
</div>

<div class="bg-gray-100 py-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-md p-8 mb-8">
                    <h2 class="text-2xl font-bold text-blue-900 mb-4">Course Content</h2>
                    <p class="text-gray-600 mb-6">Master Python for network scanning, vulnerability analysis, web exploitation, and forensic scripting.</p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 1</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Network Scanning & Recon</h3>
                                <p class="text-gray-600 text-sm">Building custom scanners with Scapy</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 2</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Vulnerability Analysis</h3>
                                <p class="text-gray-600 text-sm">Automating CVE searches and exploit development</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 3</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Web Exploitation with Python</h3>
                                <p class="text-gray-600 text-sm">Scripting XSS, SQLi, and authentication bypasses</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 4</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Digital Forensics Scripting</h3>
                                <p class="text-gray-600 text-sm">Automating log analysis and evidence collection</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-md p-8">
                    <h2 class="text-2xl font-bold text-blue-900 mb-6">What You Get</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🎬</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">25 Hours of Video</h3>
                                <p class="text-gray-500 text-sm">High-definition video lectures</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">💻</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Code-along Projects</h3>
                                <p class="text-gray-500 text-sm">Hands-on practical coding challenges</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">📄</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Cheat Sheets & Scripts</h3>
                                <p class="text-gray-500 text-sm">Downloadable Python tools</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🏅</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Certificate of Completion</h3>
                                <p class="text-gray-500 text-sm">Verify your new skills</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-md p-6 sticky top-24">
                    <div class="text-center mb-6">
                        <div class="text-5xl mb-4">🎉</div>
                        <p class="text-green-600 font-bold text-lg mb-2">You Have Access!</p>
                        <p class="text-gray-500 text-sm">Thank you for your purchase</p>
                    </div>
                    <div class="space-y-3 mb-6">
                        <button class="w-full bg-blue-900 text-white py-3 rounded-xl font-semibold hover:bg-blue-800 transition">
                            Start Course
                        </button>
                        <button class="w-full border border-gray-300 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-50 transition">
                            Download Resources
                        </button>
                    </div>
                    <a href="market.php" class="block text-center text-blue-700 font-semibold hover:underline">
                        Back to Marketplace
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>