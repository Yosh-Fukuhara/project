<?php
require_once 'includes/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'Web_Application_Hackers_Handbook.php';
    header('Location: login.php');
    exit;
}

$pageTitle = 'Web Application Hacker\'s Handbook - CyberSphere';
$currentPage = 'market';

include 'includes/header.php';
?>

<div class="bg-blue-900 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl">
            <h1 class="text-4xl sm:text-5xl font-bold mb-4">Web Application Hacker's Handbook</h1>
            <p class="text-xl text-blue-200 mb-6">The definitive guide to finding and exploiting security flaws in web applications.</p>
            <div class="flex flex-wrap gap-4">
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Book</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Comprehensive Guide</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Practical Examples</span>
            </div>
        </div>
    </div>
</div>

<div class="bg-gray-100 py-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-md p-8 mb-8">
                    <h2 class="text-2xl font-bold text-blue-900 mb-4">Book Content Overview</h2>
                    <p class="text-gray-600 mb-6">Dive deep into the most common and cutting-edge web vulnerabilities, from SQL injection and XSS to CSRF and authentication bypasses.</p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Chapter 1</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Web Application Technologies</h3>
                                <p class="text-gray-600 text-sm">Understanding the attack surface</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Chapter 2</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">SQL Injection</h3>
                                <p class="text-gray-600 text-sm">Techniques for database exploitation</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Chapter 3</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Cross-Site Scripting (XSS)</h3>
                                <p class="text-gray-600 text-sm">Client-side attacks and defenses</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Chapter 4</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Authentication & Session Hijacking</h3>
                                <p class="text-gray-600 text-sm">Exploiting login mechanisms</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-md p-8">
                    <h2 class="text-2xl font-bold text-blue-900 mb-6">What You Get</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">📚</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Digital Book Formats</h3>
                                <p class="text-gray-500 text-sm">Interactive PDF, ePub, and Mobi</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🛠️</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Practical Exercises</h3>
                                <p class="text-gray-500 text-sm">Hands-on challenges for each chapter</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">📖</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Updated Content</h3>
                                <p class="text-gray-500 text-sm">Covers modern web vulnerabilities</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🌐</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Companion Web Lab</h3>
                                <p class="text-gray-500 text-sm">Online lab environment for practice</p>
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
                            Read Book
                        </button>
                        <button class="w-full border border-gray-300 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-50 transition">
                            Access Web Lab
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