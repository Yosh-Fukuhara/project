<?php
require_once 'includes/bootstrap.php';

require_once 'autoload.php';
require_once 'data/products.php';

$pageTitle = 'Marketplace - CyberSphere';
$currentPage = 'market';

$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$filteredProducts = $products;

if ($category !== 'all') {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($category) {
        return strcasecmp($product['category'], $category) === 0;
    });
}

if (!empty($search)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($search) {
        return strpos(strtolower($product['name']), strtolower($search)) !== false || 
               strpos(strtolower($product['description']), strtolower($search)) !== false;
    });
}

$categories = ['All', 'Courses', 'Books', 'Resources'];

$message = '';
$showMessage = false;

if (isset($_SESSION['market_message'])) {
    $message = $_SESSION['market_message'];
    $showMessage = true;
    unset($_SESSION['market_message']);
}

// 2. Dynamic Current Date
$xFormattedDate = date("F j, Y");

// 3. Meaningful Syntax Implementations
// Generate a unique cart session token
if (!isset($_SESSION['cart_token'])) {
    do {
        $token = bin2hex(random_bytes(4));
    } while (isset($_SESSION['existing_tokens'][$token])); 
    
    $_SESSION['cart_token'] = $token;
    $_SESSION['existing_tokens'][$token] = true;
}

// Validate product image data safely
function isValidImage($filePath) {
    if (!file_exists($filePath)) return false;
    $imageData = file_get_contents($filePath);
    $image = @imagecreatefromstring($imageData);
    
    if ($image !== false) {
        imagedestroy($image); // Free up memory
        return true;
    }
    return false;
}

$xSort = isset($_GET['sort']) ? $_GET['sort'] : 'relevant';
switch ($xSort) {
    case 'price_low':
        usort($filteredProducts, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        break;
    case 'price_high':
        usort($filteredProducts, function($a, $b) {
            return $b['price'] <=> $a['price'];
        });
        break;
    case 'name':
        usort($filteredProducts, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        break;
    default:
        break;
}

include 'includes/header.php';
?>

<div class="bg-gray-100 min-h-screen">
    <div class="bg-white">
        <div class="container mx-auto px-4 py-8">
            <div class="relative">
                <img src="https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1400&h=300&fit=crop" alt="Marketplace Banner" class="w-full h-64 object-cover rounded-lg">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-900/80 to-transparent flex items-center">
                    <div class="pl-8 md:pl-12">
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">CyberSphere Marketplace</h1>
                        <p class="text-blue-100 text-xl mb-6">Level up your cybersecurity skills</p>
                        <a href="curriculum.php" class="bg-pink-700 text-white px-8 py-3 rounded-lg font-semibold hover:bg-pink-800 transition inline-block">
                            Explore Curriculum
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showMessage): ?>
    <div class="container mx-auto px-4 py-4">
        <div class="bg-blue-100 border border-blue-300 text-blue-800 px-4 py-3 rounded-lg">
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-2 text-sm text-gray-500 text-right">
            <span id="live-clock"><?php echo $xFormattedDate; ?></span>
        </div>

        <div class="mb-8 flex justify-end px-4 sm:px-0">
            <form method="GET" action="#products-section" class="w-full sm:w-auto">
                <div class="flex gap-2 w-full">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search products..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="flex-1 sm:w-80 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm min-w-0"
                    >
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <button type="submit" class="bg-blue-900 text-white px-5 py-2 rounded-lg font-semibold hover:bg-blue-800 transition text-sm flex-shrink-0">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <div id="products-section" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl shadow-md p-6 sticky top-24">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filters
                    </h3>

                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-700 mb-3 text-sm">Category</h4>
                        <div class="space-y-2">
                            <?php foreach ($categories as $cat): ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="radio" 
                                    name="category_radio" 
                                    value="<?php echo strtolower($cat); ?>"
                                    <?php echo (strtolower($cat) === $category || ($cat === 'All' && $category === 'all')) ? 'checked' : ''; ?>
                                    onchange="window.location.href='?category=<?php echo strtolower($cat); ?>&search=<?php echo urlencode($search); ?>#products-section'"
                                    class="w-4 h-4 text-pink-700"
                                >
                                <span class="text-gray-700"><?php echo $cat; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <a href="market.php#products-section" class="w-full border border-gray-700 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-50 transition text-sm block text-center">
                        Clear All
                    </a>
                </div>
            </div>

            <div class="lg:col-span-9">
                <div class="flex justify-end mb-6">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-600 text-sm">Sort by:</span>
                        <select onchange="window.location.href='?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=' + this.value + '#products-section'" class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="relevant" <?php echo $xSort === 'relevant' ? 'selected' : ''; ?>>Most Relevant</option>
                            <option value="price_low" <?php echo $xSort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $xSort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name" <?php echo $xSort === 'name' ? 'selected' : ''; ?>>Name</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
                    <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition group">
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-48 object-cover <?php echo $isPurchased ? 'grayscale' : ''; ?>">
                            <?php if (isset($product['badge']) && !$isPurchased): ?>
                            <div class="absolute top-3 left-3">
                                <span class="bg-blue-900 text-white text-xs font-bold px-3 py-1 rounded">
                                    <?php echo htmlspecialchars($product['badge']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($isPurchased): ?>
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                <span class="bg-green-600 text-white font-bold px-4 py-2 rounded-lg shadow-lg">
                                    PURCHASED <?php echo strtoupper($typeLabel); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-2">
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                                <div class="flex text-yellow-500 text-sm">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                    <span>★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <h3 class="font-bold text-lg text-blue-900 mb-1 group-hover:text-pink-700 transition">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            <p class="text-gray-500 text-sm mb-4 line-clamp-2">
                                <?php echo htmlspecialchars($product['description']); ?>
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-xl font-bold text-gray-800">
                                    ₱<?php echo number_format($product['price'], 2); ?>
                                </span>
                                <?php if ($isPurchased): ?>
                                    <span class="text-green-600 font-bold text-sm flex items-center gap-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        In Library
                                    </span>
                                <?php elseif ($isInCart): ?>
                                    <a href="cart.php" class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg font-semibold hover:bg-blue-200 transition text-sm">
                                        Added to cart
                                    </a>
                                <?php else: ?>
                                    <form method="POST" action="add_to_cart.php">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="bg-pink-700 text-white px-4 py-2 rounded-lg font-semibold hover:bg-pink-800 transition flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            Add
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit' };
    document.getElementById('live-clock').textContent = now.toLocaleDateString('en-US', options);
}
setInterval(updateClock, 1000);
updateClock();
</script>

<?php include 'includes/footer.php'; ?>
