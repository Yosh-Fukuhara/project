<?php
require_once 'includes/bootstrap.php';

require_once 'autoload.php';
require_once 'data/products.php';

$pageTitle = 'Curriculum - CyberSphere';
$currentPage = 'market';

$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filteredProducts = $products;

if ($category !== 'all') {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($category) {
        return strcasecmp($product['category'], $category) === 0;
    });
}

if ($search !== '') {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($search) {
        $searchLower = strtolower($search);
        return str_contains(strtolower($product['name']), $searchLower) || 
               str_contains(strtolower($product['description']), $searchLower) ||
               str_contains(strtolower($product['category']), $searchLower);
    });
}

$message = '';
$showMessage = false;
if (isset($_SESSION['market_message'])) {
    $message = $_SESSION['market_message'];
    $showMessage = true;
    unset($_SESSION['market_message']);
}

$categories = ['All', 'Courses', 'Books', 'Resources'];

// Load curriculum data from database
$curriculumData = [];
try {
    $pdo = get_db_connection();
    foreach ($products as $product) {
        $productId = $product['id'];
        $curriculumData[$productId] = [
            'modules' => [],
            'objectives' => [],
            'assessments' => []
        ];
        
        // Get objectives
        $stmtObj = $pdo->prepare("SELECT objective FROM product_objectives WHERE product_id = ? ORDER BY sort_order ASC");
        $stmtObj->execute([$productId]);
        $curriculumData[$productId]['objectives'] = $stmtObj->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Get modules
        $stmtMod = $pdo->prepare("SELECT title FROM product_modules WHERE product_id = ? ORDER BY sort_order ASC");
        $stmtMod->execute([$productId]);
        $modules = $stmtMod->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($modules as $title) {
            $curriculumData[$productId]['modules'][] = [
                'name' => $title,
                'lessons' => rand(5, 20),
                'duration' => (rand(1, 5) . ' weeks')
            ];
        }
        
        // Get assessments
        $stmtAssess = $pdo->prepare("SELECT title FROM product_assessments WHERE product_id = ? ORDER BY sort_order ASC");
        $stmtAssess->execute([$productId]);
        $curriculumData[$productId]['assessments'] = $stmtAssess->fetchAll(PDO::FETCH_COLUMN, 0);
    }
} catch (Exception $e) {
    // Fallback to basic array if DB fails
}

include 'includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-gray-800 mb-2">Full Curriculum</h1>
        <p class="text-gray-600 mb-8">Explore complete learning paths for all courses, books, and resources</p>

        <div class="mb-8">
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search curriculum..." class="flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <button type="submit" class="bg-blue-900 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-800 transition">Search</button>
            </form>
            <?php if ($search): ?>
                <p class="mt-2 text-sm text-gray-500">
                    Showing results for "<?php echo htmlspecialchars($search); ?>" 
                    <a href="curriculum.php?category=<?php echo urlencode($category); ?>" class="text-blue-700 hover:underline ml-1">Clear search</a>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($showMessage): ?>
        <div class="bg-blue-100 border border-blue-300 text-blue-800 px-4 py-3 rounded-lg mb-8">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-4 mb-8 overflow-x-auto pb-2">
            <?php foreach ($categories as $cat): ?>
            <a href="?category=<?php echo strtolower($cat); ?>" 
               class="px-6 py-2 rounded-full font-semibold transition whitespace-nowrap <?php echo (strtolower($cat) === $category || ($cat === 'All' && $category === 'all')) ? 'bg-blue-900 text-white' : 'bg-white text-gray-700 hover:bg-blue-50'; ?>">
                <?php echo $cat; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="space-y-8">
            <?php 
            $purchasedIds = [];
            if (isset($_SESSION['purchases'])) {
                foreach ($_SESSION['purchases'] as $order) {
                    foreach ($order['items'] as $item) {
                        $purchasedIds[] = $item['id'];
                    }
                }
            }
            $inCartIds = array_column($_SESSION['cart'] ?? [], 'id');

            foreach ($filteredProducts as $product): 
                $isPurchased = in_array($product['id'], $purchasedIds);
                $isInCart = in_array($product['id'], $inCartIds);
                $typeLabel = ($product['category'] === 'Books') ? 'Book' : (($product['category'] === 'Courses') ? 'Course' : 'Resource');
            ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden <?php echo $isPurchased ? 'ring-2 ring-green-500' : ''; ?>">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
                    <div class="lg:col-span-1 relative">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-64 lg:h-full object-cover <?php echo $isPurchased ? 'grayscale' : ''; ?>">
                        <?php if ($isPurchased): ?>
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                            <span class="bg-green-600 text-white font-bold px-4 py-2 rounded-lg shadow-lg">
                                PURCHASED <?php echo strtoupper($typeLabel); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="lg:col-span-2 p-8">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full mr-2">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                                <?php if ($isPurchased): ?>
                                    <span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full">✓ IN LIBRARY</span>
                                <?php elseif (isset($product['badge'])): ?>
                                    <span class="bg-pink-100 text-pink-800 text-xs font-semibold px-3 py-1 rounded-full">
                                        <?php echo htmlspecialchars($product['badge']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-2xl font-bold text-gray-800">₱<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        
                        <h2 class="text-2xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($product['name']); ?></h2>
                        <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($product['description']); ?></p>

                        <?php if (!empty($curriculumData[$product['id']]['objectives']) || !empty($curriculumData[$product['id']]['modules']) || !empty($curriculumData[$product['id']]['assessments'])): ?>
                        <?php if (!empty($curriculumData[$product['id']]['objectives'])): ?>
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
                        <?php endif; ?>

                        <?php if (!empty($curriculumData[$product['id']]['modules'])): ?>
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
                        <?php endif; ?>

                        <?php if (!empty($curriculumData[$product['id']]['assessments'])): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Assessments</h3>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($curriculumData[$product['id']]['assessments'] as $assessment): ?>
                                <span class="bg-gray-100 text-gray-700 px-4 py-2 rounded-full text-sm font-medium"><?php echo htmlspecialchars($assessment); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="mt-8 flex items-center justify-between">
                            <a href="curriculum_detail.php?id=<?php echo $product['id']; ?>" class="text-blue-900 font-bold hover:text-pink-700 transition flex items-center gap-2">
                                View Full Path
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                            
                            <?php if ($isPurchased): ?>
                                <span class="text-green-600 font-bold flex items-center gap-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Owned
                                </span>
                            <?php elseif ($isInCart): ?>
                                <a href="cart.php" class="bg-blue-100 text-blue-800 px-6 py-3 rounded-xl font-bold hover:bg-blue-200 transition">
                                    Added in cart
                                </a>
                            <?php else: ?>
                                <form method="POST" action="add_to_cart.php">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="redirect" value="curriculum.php">
                                    <button type="submit" class="bg-pink-700 text-white px-6 py-3 rounded-xl font-bold hover:bg-pink-800 transition shadow-lg flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        Add to Cart
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
