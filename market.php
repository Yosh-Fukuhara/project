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
    <!-- Hero Banner -->
    <div class="bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
            <div class="relative overflow-hidden rounded-2xl">
                <img src="https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1400&h=300&fit=crop" alt="Marketplace Banner" class="w-full h-48 sm:h-64 lg:h-72 object-cover">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-900/90 to-cyan-900/60 flex items-center">
                    <div class="px-6 sm:px-10 lg:px-16 w-full sm:w-3/4">
                        <h1 class="text-2xl sm:text-4xl lg:text-5xl font-bold text-white mb-2 sm:mb-4">CyberSphere Marketplace</h1>
                        <p class="text-blue-100 text-sm sm:text-xl mb-4 sm:mb-6">Level up your cybersecurity skills</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <?php if ($showMessage): ?>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="bg-blue-100 border border-blue-300 text-blue-800 px-4 py-3 rounded-xl text-sm sm:text-base">
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
        <!-- Live Clock -->
        <div class="mb-4 sm:mb-6 text-xs sm:text-sm text-gray-500 text-right">
            <span id="live-clock"><?php echo $xFormattedDate; ?></span>
        </div>

        <!-- Search Bar -->
        <div class="mb-6 sm:mb-10">
            <form method="GET" action="#products-section" class="w-full">
                <div class="flex gap-2 w-full">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search products..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="flex-1 px-4 py-2.5 sm:py-3 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <button type="submit" class="bg-blue-900 text-white px-5 sm:px-8 py-2.5 sm:py-3 rounded-xl font-semibold hover:bg-blue-800 transition text-sm sm:text-base flex-shrink-0">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Section -->
        <div id="products-section" class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10">
            <!-- Filters Sidebar -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-20 lg:top-24">
                    <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filters
                    </h3>

                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-700 mb-3 text-sm">Category</h4>
                        <div class="space-y-2">
                            <?php foreach ($categories as $cat): ?>
                            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 transition">
                                <input 
                                    type="radio" 
                                    name="category_radio" 
                                    value="<?php echo strtolower($cat); ?>"
                                    <?php echo (strtolower($cat) === $category || ($cat === 'All' && $category === 'all')) ? 'checked' : ''; ?>
                                    onchange="window.location.href='?category=<?php echo strtolower($cat); ?>&search=<?php echo urlencode($search); ?>#products-section'"
                                    class="w-4 h-4 text-pink-700">
                                <span class="text-gray-700 text-sm"><?php echo $cat; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <a href="market.php#products-section" class="w-full border border-gray-300 text-gray-700 py-2.5 rounded-xl font-medium hover:bg-gray-50 transition text-sm block text-center">
                        Clear All
                    </a>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="lg:col-span-9">
                <!-- Sort -->
                <div class="flex justify-end mb-4 sm:mb-6">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <span class="text-gray-600 text-xs sm:text-sm">Sort by:</span>
                        <select onchange="window.location.href='?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=' + this.value + '#products-section'" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="relevant" <?php echo $xSort === 'relevant' ? 'selected' : ''; ?>>Most Relevant</option>
                            <option value="price_low" <?php echo $xSort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $xSort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name" <?php echo $xSort === 'name' ? 'selected' : ''; ?>>Name</option>
                        </select>
                    </div>
                </div>

                <!-- Product Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <?php 
                    $hasAdvancedPenetrationTesting = false;
                    $hasNetworkSecurityFundamentals = false;
                    $hasMalwareAnalysisLabAccess = false;
                    $hasSOCAnalystBootcamp = false;
                    $hasPythonForCybersecurity = false;
                    if (isset($_SESSION['purchases'])) {
                        foreach ($_SESSION['purchases'] as $purchase) {
                            foreach ($purchase['items'] as $item) {
                                // Check if the purchased item is 'Advanced Penetration Testing' and its ID is 1
                                if (isset($item['product_name']) && $item['product_name'] === 'Advanced Penetration Testing' && $item['id'] === 1) {
                                    $hasAdvancedPenetrationTesting = true;
                                } elseif (isset($item['product_name']) && $item['product_name'] === 'Network Security Fundamentals' && $item['id'] === 2) {
                                    $hasNetworkSecurityFundamentals = true;
                                } elseif (isset($item['product_name']) && $item['product_name'] === 'Malware Analysis Lab Access' && $item['id'] === 3) {
                                    $hasMalwareAnalysisLabAccess = true;
                                } elseif (isset($item['product_name']) && $item['product_name'] === 'SOC Analyst Bootcamp' && $item['id'] === 4) {
                                    $hasSOCAnalystBootcamp = true;
                                } elseif (isset($item['product_name']) && $item['product_name'] === 'Python for Cybersecurity' && $item['id'] === 5) {
                                    $hasPythonForCybersecurity = true;
                                }
                                // If all five are found, break early
                                if ($hasAdvancedPenetrationTesting && $hasNetworkSecurityFundamentals && $hasMalwareAnalysisLabAccess && $hasSOCAnalystBootcamp && $hasPythonForCybersecurity) {
                                    break 2;
                                }
                            }
                        }
                    }
                    $inCartIds = array_column($_SESSION['cart'] ?? [], 'id');
                    
                    foreach ($filteredProducts as $product): 
                        $isPurchased = (($product['id'] === 1 && $hasAdvancedPenetrationTesting) || 
                                        ($product['id'] === 2 && $hasNetworkSecurityFundamentals) || 
                                        ($product['id'] === 3 && $hasMalwareAnalysisLabAccess) || 
                                        ($product['id'] === 4 && $hasSOCAnalystBootcamp) || 
                                        ($product['id'] === 5 && $hasPythonForCybersecurity)); // Specific check for all products
                        $isInCart = in_array($product['id'], $inCartIds);
                        $typeLabel = ($product['category'] === 'Books') ? 'Book' : (($product['category'] === 'Courses') ? 'Course' : 'Resource');
                        
                        $productLink = '';
                        if ($product['id'] === 1) {
                            $productLink = 'Advanced_Penetration_Testing.php';
                        } elseif ($product['id'] === 2) {
                            $productLink = 'Network_Security_Fundamentals.php';
                        } elseif ($product['id'] === 3) {
                            $productLink = 'Malware_Analysis_Lab_Access.php';
                        } elseif ($product['id'] === 4) {
                            $productLink = 'SOC_Analyst_Bootcamp.php';
                        } elseif ($product['id'] === 5) {
                            $productLink = 'Python_for_Cybersecurity.php';
                        }
                    ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition group cursor-pointer" onclick="openProductModal(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)">
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-48 object-cover <?php echo $isPurchased ? 'grayscale' : ''; ?>">
                            <?php if ($isPurchased && $productLink): ?>
                            <a href="<?php echo $productLink; ?>" class="absolute top-3 left-3">
                                <span class="bg-green-600 text-white text-xs font-bold px-3 py-1 rounded">
                                    In Library
                                </span>
                            </a>
                            <?php elseif (isset($product['badge']) && !$isPurchased): ?>
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
                                <?php if ($isPurchased && $productLink): ?>
                                    <a href="<?php echo $productLink; ?>" class="bg-green-600 text-white px-4 py-2 rounded-xl font-semibold hover:bg-green-700 transition">In Library</a>
                                <?php elseif ($isInCart): ?>
                                    <a href="cart.php" class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg font-semibold hover:bg-blue-200 transition text-sm">
                                        Added to cart
                                    </a>
                                <?php else: ?>
                                    <form method="POST" action="add_to_cart.php" onclick="event.stopPropagation()">
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

<!-- Product Detail Modal -->
<div id="product-modal" class="fixed inset-0 bg-black/60 hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="relative">
            <button type="button" id="close-modal-btn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-2xl z-10">&times;</button>
            <img id="modal-image" src="" alt="" class="w-full h-64 object-cover rounded-t-2xl">
        </div>
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span id="modal-category" class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full mb-2 inline-block"></span>
                    <h2 id="modal-name" class="text-2xl font-bold text-blue-900"></h2>
                </div>
                <span id="modal-price" class="text-2xl font-bold text-gray-800"></span>
            </div>
            <p id="modal-description" class="text-gray-600 mb-6"></p>
            
            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 mb-3 text-lg">What's Included</h3>
                <ul id="modal-included" class="space-y-2">
                </ul>
            </div>
            
            <form id="modal-add-to-cart-form" method="POST" action="add_to_cart.php">
                <input type="hidden" id="modal-product-id" name="product_id">
                <button type="submit" id="modal-add-to-cart-btn" class="w-full bg-pink-700 text-white py-3 rounded-xl font-semibold hover:bg-pink-800 transition text-lg">
                    Add to Cart
                </button>
            </form>
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

// Product Modal Functions
function openProductModal(product) {
    // Populate modal content
    document.getElementById('modal-image').src = product.image;
    document.getElementById('modal-image').alt = product.name;
    document.getElementById('modal-name').textContent = product.name + ' (' + product.category.slice(0, -1) + ')';
    document.getElementById('modal-category').textContent = product.category;
    document.getElementById('modal-price').textContent = '₱' + Number(product.price).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('modal-description').textContent = product.description;
    
    // What's Included
    const includedList = document.getElementById('modal-included');
    includedList.innerHTML = '';
    if (product.whats_included && product.whats_included.length > 0) {
        product.whats_included.forEach(item => {
            const li = document.createElement('li');
            li.className = 'flex items-start gap-2 text-gray-700';
            li.innerHTML = `
                <svg class="w-5 h-5 text-pink-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>${item}</span>
            `;
            includedList.appendChild(li);
        });
    }
    
    // Add to Cart
    document.getElementById('modal-product-id').value = product.id;
    
    // Check if already in cart or purchased
    <?php 
    $hasAdvancedPenetrationTestingInModal = false;
    $hasNetworkSecurityFundamentalsInModal = false;
    $hasMalwareAnalysisLabAccessInModal = false;
    $hasSOCAnalystBootcampInModal = false;
    $hasPythonForCybersecurityInModal = false;
    $hasWebApplicationsHackersHandbookInModal = false; // Added for current task
    if (isset($_SESSION['purchases'])) {
        foreach ($_SESSION['purchases'] as $purchase) {
            foreach ($purchase['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === 'Advanced Penetration Testing' && $item['id'] === 1) {
                    $hasAdvancedPenetrationTestingInModal = true;
                } elseif (isset($item['product_name']) && $item['product_name'] === 'Network Security Fundamentals' && $item['id'] === 2) {
                    $hasNetworkSecurityFundamentalsInModal = true;
                } elseif (isset($item['product_name']) && $item['product_name'] === 'Malware Analysis Lab Access' && $item['id'] === 3) {
                    $hasMalwareAnalysisLabAccessInModal = true;
                } elseif (isset($item['product_name']) && $item['product_name'] === 'SOC Analyst Bootcamp' && $item['id'] === 4) {
                    $hasSOCAnalystBootcampInModal = true;
                } elseif (isset($item['product_name']) && $item['product_name'] === 'Python for Cybersecurity' && $item['id'] === 5) {
                    $hasPythonForCybersecurityInModal = true;
                } elseif (isset($item['product_name']) && $item['product_name'] === "Web Application Hacker's Handbook" && $item['id'] === 6) {
                    $hasWebApplicationsHackersHandbookInModal = true;
                }
                if ($hasAdvancedPenetrationTestingInModal && $hasNetworkSecurityFundamentalsInModal && $hasMalwareAnalysisLabAccessInModal && $hasSOCAnalystBootcampInModal && $hasPythonForCybersecurityInModal && $hasWebApplicationsHackersHandbookInModal) {
                    break 2;
                }
            }
        }
    }
    $inCartIds = array_column($_SESSION['cart'] ?? [], 'id');
    ?>
    const inCartIds = <?php echo json_encode($inCartIds); ?>;
    const isAdvancedPenetrationTestingPurchasedInModal = <?php echo json_encode($hasAdvancedPenetrationTestingInModal); ?>;
    const isNetworkSecurityFundamentalsPurchasedInModal = <?php echo json_encode($hasNetworkSecurityFundamentalsInModal); ?>;
    const isMalwareAnalysisLabAccessPurchasedInModal = <?php echo json_encode($hasMalwareAnalysisLabAccessInModal); ?>;
    const isSOCAnalystBootcampPurchasedInModal = <?php echo json_encode($hasSOCAnalystBootcampInModal); ?>;
    const isPythonForCybersecurityPurchasedInModal = <?php echo json_encode($hasPythonForCybersecurityInModal); ?>;
    const isWebApplicationsHackersHandbookPurchasedInModal = <?php echo json_encode($hasWebApplicationsHackersHandbookInModal); ?>; // Added for current task
    const addBtn = document.getElementById('modal-add-to-cart-btn');
    const addForm = document.getElementById('modal-add-to-cart-form');
    
    if (product.id === 1 && isAdvancedPenetrationTestingPurchasedInModal) {
        addBtn.textContent = 'In Library';
        addBtn.className = 'w-full bg-green-600 text-white py-3 rounded-xl font-semibold transition text-lg cursor-not-allowed';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'Advanced_Penetration_Testing.php'; // Redirect to the course page
        };
    } else if (product.id === 2 && isNetworkSecurityFundamentalsPurchasedInModal) {
        addBtn.textContent = 'In Library';
        addBtn.className = 'w-full bg-green-600 text-white py-3 rounded-xl font-semibold transition text-lg cursor-not-allowed';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'Network_Security_Fundamentals.php'; // Redirect to the book page
        };
    } else if (product.id === 3 && isMalwareAnalysisLabAccessPurchasedInModal) {
        addBtn.textContent = 'In Library';
        addBtn.className = 'w-full bg-green-600 text-white py-3 rounded-xl font-semibold transition text-lg cursor-not-allowed';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'Malware_Analysis_Lab_Access.php'; // Redirect to the resource page
        };
    } else if (product.id === 4 && isSOCAnalystBootcampPurchasedInModal) {
        addBtn.textContent = 'In Library';
        addBtn.className = 'w-full bg-green-600 text-white py-3 rounded-xl font-semibold transition text-lg cursor-not-allowed';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'SOC_Analyst_Bootcamp.php'; // Redirect to the course page
        };
    } else if (product.id === 5 && isPythonForCybersecurityPurchasedInModal) {
        addBtn.textContent = 'In Library';
        addBtn.className = 'w-full bg-green-600 text-white py-3 rounded-xl font-semibold transition text-lg cursor-not-allowed';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'Python_for_Cybersecurity.php'; // Redirect to the course page
        };
    } else if (product.id === 6 && isWebApplicationsHackersHandbookPurchasedInModal) { // Added for current task
        addBtn.textContent = 'In Library';
        addBtn.className = 'w-full bg-green-600 text-white py-3 rounded-xl font-semibold transition text-lg cursor-not-allowed';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'Web_Application_Hackers_Handbook.php'; // Redirect to the book page
        };
    } else if (inCartIds.includes(product.id)) {
        addBtn.textContent = 'Added to Cart - Go to Cart';
        addBtn.className = 'w-full bg-blue-100 text-blue-800 py-3 rounded-xl font-semibold hover:bg-blue-200 transition text-lg';
        addForm.onsubmit = (e) => {
            e.preventDefault();
            window.location.href = 'cart.php';
        };
    } else {
        addBtn.textContent = 'Add to Cart';
        addBtn.className = 'w-full bg-pink-700 text-white py-3 rounded-xl font-semibold hover:bg-pink-800 transition text-lg';
        addForm.onsubmit = null;
    }
    
    // Show modal
    document.getElementById('product-modal').classList.remove('hidden');
    document.getElementById('product-modal').classList.add('flex');
}

// Close modal
function closeProductModal() {
    document.getElementById('product-modal').classList.add('hidden');
    document.getElementById('product-modal').classList.remove('flex');
}

// Event listeners
document.getElementById('close-modal-btn').addEventListener('click', closeProductModal);
document.getElementById('product-modal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('product-modal')) {
        closeProductModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>