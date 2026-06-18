<?php
require_once 'includes/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'Advanced_Penetration_Testing.php';
    header('Location: login.php');
    exit;
}

$pageTitle = 'Advanced Penetration Testing - CyberSphere';
$currentPage = 'market';

include 'includes/header.php';
?>

<div class="bg-blue-900 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl">
            <h1 class="text-4xl sm:text-5xl font-bold mb-4">Advanced Penetration Testing</h1>
            <p class="text-xl text-blue-200 mb-6">Take your ethical hacking skills to the next level</p>
            <div class="flex flex-wrap gap-4">
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Courses</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">15 Hours</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">5 Labs</span>
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
                    <p class="text-gray-600 mb-6">This advanced course deep dives into modern infrastructure exploitation, pivoting, Active Directory attacks, and bypass techniques.</p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 1</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Infrastructure Exploitation</h3>
                                <p class="text-gray-600 text-sm">Deep dive into modern exploitation techniques</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 2</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Pivoting Techniques</h3>
                                <p class="text-gray-600 text-sm">Learn to move laterally through networks</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 3</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Active Directory Attacks</h3>
                                <p class="text-gray-600 text-sm">Compromise domain environments</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 4</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Bypass Techniques</h3>
                                <p class="text-gray-600 text-sm">Evade modern security controls</p>
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
                                <h3 class="font-semibold text-gray-800">15 Hours of Video</h3>
                                <p class="text-gray-500 text-sm">High-definition lectures</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🏟️</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">5 Lab Environments</h3>
                                <p class="text-gray-500 text-sm">Real-world vulnerable labs</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">📄</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Cheat Sheets</h3>
                                <p class="text-gray-500 text-sm">Downloadable resources</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🏆</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Certificate</h3>
                                <p class="text-gray-500 text-sm">Certificate of completion</p>
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
                            Start Learning
                        </button>
                        <button class="w-full border border-gray-300 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-50 transition">
                            Download Certificate
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
