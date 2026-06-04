<?php
require_once 'includes/bootstrap.php';
require_once 'autoload.php';
require_once 'data/products.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

foreach ($products as $p) {
    if ($p['id'] === $id) {
        $product = $p;
        break;
    }
}

if (!$product) {
    header('Location: curriculum.php');
    exit;
}

$pageTitle = $product['name'] . ' - Curriculum Detail';
$currentPage = 'market';

// Re-using the curriculum data structure from curriculum.php
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

$purchasedIds = [];
if (isset($_SESSION['purchases'])) {
    foreach ($_SESSION['purchases'] as $order) {
        foreach ($order['items'] as $item) {
            $purchasedIds[] = $item['id'];
        }
    }
}
$inCartIds = array_column($_SESSION['cart'] ?? [], 'id');
$isPurchased = in_array($product['id'], $purchasedIds);
$isInCart = in_array($product['id'], $inCartIds);
$typeLabel = ($product['category'] === 'Books') ? 'Book' : (($product['category'] === 'Courses') ? 'Course' : 'Resource');

include 'includes/header.php';
?>

<div class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-12">
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-8">
            <a href="market.php" class="hover:text-blue-900 transition">Marketplace</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            <a href="curriculum.php" class="hover:text-blue-900 transition">Curriculum</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            <!-- Left Column: Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="relative h-80">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                        <div class="absolute bottom-8 left-8">
                            <span class="bg-blue-900 text-white text-xs font-bold px-3 py-1 rounded-full mb-3 inline-block">
                                <?php echo htmlspecialchars($product['category']); ?>
                            </span>
                            <h1 class="text-4xl font-bold text-white"><?php echo htmlspecialchars($product['name']); ?></h1>
                        </div>
                    </div>

                    <div class="p-8">
                        <div class="prose prose-blue max-w-none">
                            <h2 class="text-2xl font-bold text-gray-900 mb-4">About this <?php echo strtolower($typeLabel); ?></h2>
                            <p class="text-gray-600 text-lg leading-relaxed mb-8">
                                <?php echo htmlspecialchars($product['description']); ?>
                            </p>

                            <?php if (isset($curriculumData[$product['id']])): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900 mb-4">What you'll learn</h3>
                                        <ul class="space-y-3">
                                            <?php foreach ($curriculumData[$product['id']]['objectives'] as $obj): ?>
                                            <li class="flex items-start gap-3 text-gray-700">
                                                <div class="mt-1 flex-shrink-0 w-5 h-5 bg-green-100 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span><?php echo htmlspecialchars($obj); ?></span>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900 mb-4">Assessment Methods</h3>
                                        <ul class="space-y-3">
                                            <?php foreach ($curriculumData[$product['id']]['assessments'] as $assess): ?>
                                            <li class="flex items-start gap-3 text-gray-700">
                                                <div class="mt-1 flex-shrink-0 w-5 h-5 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <span><?php echo htmlspecialchars($assess); ?></span>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>

                                <h3 class="text-2xl font-bold text-gray-900 mb-6">Full Path Curriculum</h3>
                                <div class="space-y-4">
                                    <?php foreach ($curriculumData[$product['id']]['modules'] as $index => $module): ?>
                                    <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 hover:border-blue-200 transition">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-4">
                                                <span class="w-10 h-10 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center font-bold text-blue-900">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <div>
                                                    <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($module['name']); ?></h4>
                                                    <p class="text-sm text-gray-500"><?php echo $module['lessons']; ?> lessons • <?php echo $module['duration']; ?></p>
                                                </div>
                                            </div>
                                            <button class="text-gray-400 hover:text-blue-900 transition">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Checkout Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8 sticky top-24">
                    <div class="text-center mb-8">
                        <span class="text-gray-500 text-sm block mb-1">Lifetime Access</span>
                        <div class="text-5xl font-black text-gray-900">₱<?php echo number_format($product['price'], 2); ?></div>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-center gap-3 text-gray-700">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <span>Secure Digital Delivery</span>
                        </div>
                        <div class="flex items-center gap-3 text-gray-700">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Certification included</span>
                        </div>
                        <div class="flex items-center gap-3 text-gray-700">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span>Self-paced learning</span>
                        </div>
                    </div>

                    <?php if ($isPurchased): ?>
                        <div class="bg-green-50 rounded-2xl p-6 border border-green-100 text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-green-900 mb-1">Item Owned</h4>
                            <p class="text-sm text-green-700 mb-4">This <?php echo strtolower($typeLabel); ?> is already in your library.</p>
                            <a href="assessment.php" class="block w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition">Start Learning</a>
                        </div>
                    <?php elseif ($isInCart): ?>
                        <a href="cart.php" class="block w-full bg-blue-900 text-white text-center font-bold py-4 rounded-2xl hover:bg-blue-800 transition shadow-lg shadow-blue-900/20 mb-4">
                            Go to Cart
                        </a>
                        <p class="text-center text-sm text-gray-500">Item already in your cart</p>
                    <?php else: ?>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="redirect" value="curriculum_detail.php?id=<?php echo $product['id']; ?>">
                            <button type="submit" class="w-full bg-pink-700 text-white font-bold py-4 rounded-2xl hover:bg-pink-800 transition shadow-lg shadow-pink-700/20 mb-4 flex items-center justify-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Add to Cart
                            </button>
                        </form>
                        <p class="text-center text-xs text-gray-400">30-Day Money Back Guarantee</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
