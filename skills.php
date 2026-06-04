<?php
require_once 'includes/bootstrap.php';
require_once 'autoload.php';

$pageTitle = 'In-Demand Skills - CyberSphere';
$currentPage = 'home';

$inDemandSkills = [
    [
        'name' => 'Penetration Testing',
        'category' => 'Offensive Security',
        'demand' => 'Very High',
        'growth' => '+25%',
        'professionals' => '18.5k',
        'description' => 'The practice of testing a computer system, network or web application to find security vulnerabilities that an attacker could exploit.',
        'icon' => '🛡️'
    ],
    [
        'name' => 'Cloud Security',
        'category' => 'Infrastructure',
        'demand' => 'Critical',
        'growth' => '+40%',
        'professionals' => '12.2k',
        'description' => 'Security for cloud-based systems, including AWS, Azure, and Google Cloud Platform, focusing on data protection and compliance.',
        'icon' => '☁️'
    ],
    [
        'name' => 'Incident Response',
        'category' => 'Defensive Security',
        'demand' => 'High',
        'growth' => '+15%',
        'professionals' => '15.1k',
        'description' => 'Managing the aftermath of a security breach or cyberattack to limit damage and reduce recovery time and costs.',
        'icon' => '🚨'
    ],
    [
        'name' => 'Zero Trust Architecture',
        'category' => 'Network Security',
        'demand' => 'Rising',
        'growth' => '+55%',
        'professionals' => '8.4k',
        'description' => 'A strategic initiative that prevents data breaches by eliminating the concept of trust from an organization\'s network architecture.',
        'icon' => '🔒'
    ],
    [
        'name' => 'AI Threat Detection',
        'category' => 'Emerging Tech',
        'demand' => 'High',
        'growth' => '+60%',
        'professionals' => '5.2k',
        'description' => 'Using machine learning and artificial intelligence to identify and respond to novel cyber threats in real-time.',
        'icon' => '🤖'
    ],
    [
        'name' => 'GRC & Compliance',
        'category' => 'Governance',
        'demand' => 'Stable',
        'growth' => '+10%',
        'professionals' => '22.8k',
        'description' => 'Ensuring an organization follows all applicable laws, regulations, and ethical standards while managing risk.',
        'icon' => '⚖️'
    ]
];

include 'includes/header.php';
?>

<div class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-8">
            <a href="index.php" class="text-blue-800 hover:underline">Home</a>
            <span>/</span>
            <span class="text-gray-700 font-semibold">In-Demand Skills</span>
        </nav>

        <div class="max-w-5xl mx-auto">
            <div class="mb-12 text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Trending Tech Skills</h1>
                <p class="text-gray-600 text-lg">Stay ahead of the curve with the most sought-after skills in cybersecurity and technology right now.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($inDemandSkills as $skill): ?>
                    <div class="bg-white rounded-2xl shadow-md p-6 hover:shadow-xl transition border border-gray-100 flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">
                                <?php echo $skill['icon']; ?>
                            </div>
                            <span class="text-xs font-bold px-3 py-1 rounded-full <?php 
                                echo $skill['demand'] === 'Critical' ? 'bg-red-100 text-red-800' : 
                                    ($skill['demand'] === 'Very High' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800'); 
                            ?>">
                                <?php echo $skill['demand']; ?> DEMAND
                            </span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($skill['name']); ?></h2>
                        <p class="text-blue-700 text-xs font-semibold mb-4 uppercase tracking-wider"><?php echo htmlspecialchars($skill['category']); ?></p>
                        <p class="text-gray-600 text-sm mb-6 flex-1"><?php echo htmlspecialchars($skill['description']); ?></p>
                        
                        <div class="pt-6 border-t border-gray-50 flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400">Professionals</p>
                                <p class="font-bold text-gray-800"><?php echo $skill['professionals']; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400">Growth</p>
                                <p class="font-bold text-green-600"><?php echo $skill['growth']; ?></p>
                            </div>
                        </div>
                        
                        <a href="curriculum.php?search=<?php echo urlencode(strtolower($skill['name'])); ?>" class="mt-6 block w-full text-center py-2 border border-blue-900 text-blue-900 font-bold rounded-xl hover:bg-blue-900 hover:text-white transition text-sm">
                            Learn this skill
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-16 bg-blue-900 rounded-3xl p-10 text-white text-center relative overflow-hidden">
                <div class="relative z-10">
                    <h2 class="text-3xl font-bold mb-4">Master These Skills</h2>
                    <p class="text-blue-100 mb-8 max-w-2xl mx-auto">Explore our curated curriculum designed by industry experts to help you reach professional proficiency in any of these areas.</p>
                    <a href="curriculum.php" class="inline-block bg-white text-blue-900 font-bold px-8 py-4 rounded-2xl hover:bg-gray-100 transition shadow-lg">
                        Browse Full Curriculum
                    </a>
                </div>
                <div class="absolute top-0 right-0 -mt-20 -mr-20 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-64 h-64 bg-blue-400/20 rounded-full blur-3xl"></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
