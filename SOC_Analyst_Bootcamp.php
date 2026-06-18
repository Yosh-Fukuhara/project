<?php
require_once 'includes/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'SOC_Analyst_Bootcamp.php';
    header('Location: login.php');
    exit;
}

$pageTitle = 'SOC Analyst Bootcamp - CyberSphere';
$currentPage = 'market';

include 'includes/header.php';
?>

<div class="bg-blue-900 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl">
            <h1 class="text-4xl sm:text-5xl font-bold mb-4">SOC Analyst Bootcamp</h1>
            <p class="text-xl text-blue-200 mb-6">Your fast track to entering a Security Operations Center.</p>
            <div class="flex flex-wrap gap-4">
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Course</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">40+ Hours</span>
                <span class="bg-white/10 px-4 py-2 rounded-full text-sm font-semibold">Hands-on Labs</span>
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
                    <p class="text-gray-600 mb-6">Learn how to monitor traffic, analyze logs, investigate alerts, and respond to incidents using industry-standard SIEM tools.</p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 1</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">SIEM Fundamentals</h3>
                                <p class="text-gray-600 text-sm">Splunk, ELK Stack, QRadar basics</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 2</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Log Analysis & Threat Detection</h3>
                                <p class="text-gray-600 text-sm">Identifying anomalies and indicators of compromise</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 3</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Incident Response & Management</h3>
                                <p class="text-gray-600 text-sm">Playbooks, containment, eradication</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl">
                            <span class="bg-blue-900 text-white px-4 py-2 rounded-lg font-bold">Module 4</span>
                            <div>
                                <h3 class="font-semibold text-blue-900">Advanced Threat Hunting</h3>
                                <p class="text-gray-600 text-sm">Proactive search for hidden threats</p>
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
                                <h3 class="font-semibold text-gray-800">40+ Hours Video Content</h3>
                                <p class="text-gray-500 text-sm">High-definition video lectures</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">🧪</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Hands-on Labs</h3>
                                <p class="text-gray-500 text-sm">Real-world scenarios with virtual labs</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl">
                            <span class="text-pink-700 text-2xl">📝</span>
                            <div>
                                <h3 class="font-semibold text-gray-800">Incident Playbooks</h3>
                                <p class="text-gray-500 text-sm">Downloadable response guides</p>
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
                            Download Playbooks
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