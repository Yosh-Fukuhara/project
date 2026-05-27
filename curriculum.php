<?php
require_once 'includes/bootstrap.php';

require_once 'autoload.php';
require_once 'data/products.php';

$pageTitle = 'Curriculum - CyberSphere';
$currentPage = 'market';

$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$filteredProducts = $products;

if ($category !== 'all') {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($category) {
        return strcasecmp($product['category'], $category) === 0;
    });
}

$categories = ['All', 'Courses', 'Books', 'Resources'];

$curriculumData = [
    1 => [
        'modules' => [
            ['name' => 'Introduction to Penetration Testing', 'lessons' => 8, 'duration' => '2 weeks'],
            ['name' => 'Reconnaissance & Footprinting', 'lessons' => 12, 'duration' => '3 weeks'],
            ['name' => 'Scanning & Enumeration', 'lessons' => 10, 'duration' => '2 weeks'],
            ['name' => 'Exploitation Fundamentals', 'lessons' => 15, 'duration' => '4 weeks'],
            ['name' => 'Post-Exploitation Techniques', 'lessons' => 11, 'duration' => '3 weeks'],
            ['name' => 'Web Application Penetration Testing', 'lessons' => 18, 'duration' => '5 weeks'],
        ],
        'objectives' => ['Master ethical hacking methodologies', 'Perform real-world penetration tests', 'Create professional security reports'],
        'assessments' => ['Mid-term practical exam', 'Final capstone project', 'Written certification exam']
    ],
    2 => [
        'modules' => [
            ['name' => 'Networking Basics', 'lessons' => 6, 'duration' => '1 week'],
            ['name' => 'TCP/IP Protocol Suite', 'lessons' => 10, 'duration' => '2 weeks'],
            ['name' => 'Firewalls & IDS/IPS', 'lessons' => 8, 'duration' => '2 weeks'],
            ['name' => 'VPN & Encryption', 'lessons' => 7, 'duration' => '1.5 weeks'],
        ],
        'objectives' => ['Understand core network security concepts', 'Implement network security controls', 'Troubleshoot security issues'],
        'assessments' => ['Chapter quizzes', 'Practical lab assignments', 'Final comprehensive exam']
    ],
    3 => [
        'modules' => [
            ['name' => 'Sandbox Environment Setup', 'lessons' => 3, 'duration' => '1 day'],
            ['name' => 'Malware Classification', 'lessons' => 5, 'duration' => '3 days'],
            ['name' => 'Static Analysis', 'lessons' => 8, 'duration' => '1 week'],
            ['name' => 'Dynamic Analysis', 'lessons' => 10, 'duration' => '1.5 weeks'],
        ],
        'objectives' => ['Set up a safe analysis environment', 'Analyze malware samples', 'Generate detailed analysis reports'],
        'assessments' => ['Lab practicals', 'Analysis report submissions']
    ],
    4 => [
        'modules' => [
            ['name' => 'Security Operations Center Overview', 'lessons' => 5, 'duration' => '1 week'],
            ['name' => 'SIEM Implementation', 'lessons' => 12, 'duration' => '3 weeks'],
            ['name' => 'Incident Response', 'lessons' => 15, 'duration' => '4 weeks'],
            ['name' => 'Threat Intelligence', 'lessons' => 10, 'duration' => '2.5 weeks'],
        ],
        'objectives' => ['Operate a SOC effectively', 'Respond to security incidents', 'Implement threat intelligence'],
        'assessments' => ['Simulated incident response drills', 'Capstone project', 'Certification prep']
    ],
    5 => [
        'modules' => [
            ['name' => 'Python for Security', 'lessons' => 8, 'duration' => '2 weeks'],
            ['name' => 'Automating Scanning', 'lessons' => 10, 'duration' => '2.5 weeks'],
            ['name' => 'Building Security Tools', 'lessons' => 12, 'duration' => '3 weeks'],
        ],
        'objectives' => ['Write Python security scripts', 'Automate security tasks', 'Develop custom tools'],
        'assessments' => ['Scripting assignments', 'Tool development project']
    ],
    6 => [
        'modules' => [
            ['name' => 'Web Application Security', 'lessons' => 10, 'duration' => '2.5 weeks'],
            ['name' => 'OWASP Top 10', 'lessons' => 15, 'duration' => '4 weeks'],
            ['name' => 'Advanced Exploitation', 'lessons' => 12, 'duration' => '3 weeks'],
        ],
        'objectives' => ['Identify web vulnerabilities', 'Exploit common flaws', 'Secure web applications'],
        'assessments' => ['Hands-on labs', 'Capture-the-Flag challenges', 'Final practical exam']
    ]
];

include 'includes/header.php';
?>

<div class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-8">
            <a href="market.php" class="text-blue-800 hover:underline">Marketplace</a>
            <span>/</span>
            <span class="text-gray-700 font-semibold">Curriculum</span>
        </nav>
        
        <h1 class="text-4xl font-bold text-gray-800 mb-2">Full Curriculum</h1>
        <p class="text-gray-600 mb-8">Explore complete learning paths for all courses, books, and resources</p>

        <div class="flex gap-4 mb-8 overflow-x-auto pb-2">
            <?php foreach ($categories as $cat): ?>
            <a href="?category=<?php echo strtolower($cat); ?>" 
               class="px-6 py-2 rounded-full font-semibold transition whitespace-nowrap <?php echo (strtolower($cat) === $category || ($cat === 'All' && $category === 'all')) ? 'bg-blue-900 text-white' : 'bg-white text-gray-700 hover:bg-blue-50'; ?>">
                <?php echo $cat; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="space-y-8">
            <?php foreach ($filteredProducts as $product): ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
                    <div class="lg:col-span-1">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-64 lg:h-full object-cover">
                    </div>
                    <div class="lg:col-span-2 p-8">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full mr-2">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                                <?php if (isset($product['badge'])): ?>
                                <span class="bg-pink-100 text-pink-800 text-xs font-semibold px-3 py-1 rounded-full">
                                    <?php echo htmlspecialchars($product['badge']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-2xl font-bold text-gray-800">₱<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        
                        <h2 class="text-2xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($product['name']); ?></h2>
                        <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($product['description']); ?></p>

                        <?php if (isset($curriculumData[$product['id']])): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Learning Objectives</h3>
                            <ul class="space-y-2">
                                <?php foreach ($curriculumData[$product['id']]['objectives'] as $obj): ?>
                                <li class="flex items-start gap-2 text-gray-700">
                                    <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($obj); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Course Modules</h3>
                            <div class="space-y-3">
                                <?php foreach ($curriculumData[$product['id']]['modules'] as $module): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($module['name']); ?></h4>
                                        <div class="flex items-center gap-4 text-sm text-gray-500">
                                            <span><?php echo $module['lessons']; ?> lessons</span>
                                            <span>•</span>
                                            <span><?php echo $module['duration']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Assessments</h3>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($curriculumData[$product['id']]['assessments'] as $assessment): ?>
                                <span class="bg-gray-100 text-gray-700 px-4 py-2 rounded-full text-sm font-medium"><?php echo htmlspecialchars($assessment); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mt-8 flex gap-4">
                            <form method="POST" action="add_to_cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="redirect_to" value="curriculum.php">
                                <button type="submit" class="bg-pink-700 text-white px-8 py-3 rounded-lg font-semibold hover:bg-pink-800 transition">
                                    Add to Cart
                                </button>
                            </form>
                            <a href="market.php" class="border border-gray-700 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-50 transition">
                                Back to Marketplace
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
