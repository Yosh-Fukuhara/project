<?php
require_once 'includes/bootstrap.php';
require_once 'config/database.php';

require_once 'autoload.php';
require_once 'data/products.php';

// ── Idle Timeout (1 minute) ─────────────────────────────────────────────
const IDLE_TIMEOUT = 60; // seconds

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > IDLE_TIMEOUT)) {
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
    header('Location: index.php');
    exit;
}

$_SESSION['last_activity'] = time(); // update last activity time



$pageTitle = 'Shopping Cart - CyberSphere';
$currentPage = 'cart';

// ── Require login to access cart ────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    // Store intended destination so we can redirect back after login
    $_SESSION['redirect_after_login'] = 'cart.php';
    header('Location: login.php');
    exit;
}

// Sanitize: remove any items that aren't proper arrays
$_SESSION['cart'] = array_values(array_filter($_SESSION['cart'] ?? [], fn($item) => is_array($item)));

// ── Sync prices with latest product data ────────────────────────────────
foreach ($_SESSION['cart'] as &$item) {
    foreach ($products as $p) {
        if ($item['id'] === $p['id']) {
            $item['price'] = $p['price']; // Update to latest price
            $item['name'] = $p['name']; // Add product name
            break;
        }
    }
}
unset($item);

// Calculate total FIRST so it's available inside the POST block below
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
}

$checkoutSuccess = false;
$checkoutMessage = '';
$checkoutErrors  = [];

// Handle successful checkout after OTP verification redirect
if (isset($_GET['checkout_success']) && $_GET['checkout_success'] === 'true') {
    $checkoutSuccess = true;
    $checkoutMessage = $_SESSION['checkout_success_message'] ?? '';
    unset($_SESSION['checkout_success_message']); // Clear message after display
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    // ── Login required ────────────────────────────────────────────────────
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }

    $paymentMethod  = trim($_POST['payment_method'] ?? '');
    $paymentDetails = trim($_POST['payment_details'] ?? '');

    // ── Validate ──────────────────────────────────────────────────────────
    $allowedMethods = ['Credit Card', 'GCash', 'PayMaya'];
    if (empty($paymentMethod) || !in_array($paymentMethod, $allowedMethods)) {
        $checkoutErrors[] = 'Please select a valid payment method.';
    }
    if (empty($paymentDetails)) {
        $checkoutErrors[] = 'Please enter your payment details.';
    }
    if (empty($_SESSION['cart'])) {
        $checkoutErrors[] = 'Your cart is empty.';
    }

    // ── Validate payment details by method ────────────────────────────────
    if (empty($checkoutErrors)) {
        if ($paymentMethod === 'Credit Card') {
            // Expect "Cardholder: ..., Card: XXXX XXXX XXXX XXXX, Expiry: MM/YY, CVV: XXX"
            if (!preg_match('/Card:\s*[\d\s]{14,19}/', $paymentDetails)) {
                $checkoutErrors[] = 'Please enter a valid card number (16 digits).';
            }
            if (!preg_match('/Expiry:\s*\d{2}\/\d{2}/', $paymentDetails)) {
                $checkoutErrors[] = 'Please enter a valid expiry date (MM/YY).';
            }
            if (!preg_match('/CVV:\s*\d{3,4}/', $paymentDetails)) {
                $checkoutErrors[] = 'Please enter a valid CVV (3–4 digits).';
            }
        } else {
            // GCash / PayMaya — extract and validate Philippine mobile number
            if (preg_match('/(\b09\d{9})\b/', $paymentDetails, $matches)) {
                // If a valid number is found, use it for $paymentDetails
                $paymentDetails = $matches[1];
            } else {
                $checkoutErrors[] = 'Please enter a valid mobile number (11 digits starting with 09, e.g., 09171234567).';
            }
        }
    }

    if (empty($checkoutErrors)) {
        $purchaseId = 'PUR-' . strtoupper(substr(uniqid('', true), -8));
        $subtotal = $total;
        $tax = $total * 0.12;
        $grandTotal = $total * 1.12;
        
        // ── Save to Database ──────────────────────────────────────────────
        $pdo = get_db_connection();
        try {
            $pdo->beginTransaction();
            
            // Insert into orders (keep original table name)
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, subtotal, tax, grand_total, payment_method, payment_details)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user']['user_id'],
                $subtotal,
                $tax,
                $grandTotal,
                $paymentMethod,
                $paymentDetails
            ]);
            $dbPurchaseId = $pdo->lastInsertId();
            
            // Insert each cart item into order_items (keep original table name)
            foreach ($_SESSION['cart'] as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $dbPurchaseId,
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity']
                ]);
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $checkoutErrors[] = 'Failed to save purchase to database. Please try again.';
        }
        
        if (empty($checkoutErrors)) {
            $processedItems = [];
            foreach ($_SESSION['cart'] as $item) {
                $processedItems[] = [
                    'id' => $item['id'],
                    'product_name' => $item['name'], // Map 'name' to 'product_name'
                    'unit_price' => $item['price'],
                    'quantity' => $item['quantity']
                ];
            }

            $purchaseRecord = [
                'id'             => $purchaseId,
                'user_id'        => $_SESSION['user']['user_id'],
                'username'       => $_SESSION['user']['username'],
                'items'          => $processedItems,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'grand_total'    => $grandTotal,
                'payment_method' => $paymentMethod,
                'purchased_at'   => date('M j, Y g:i A'),
            ];

            // Save to last_purchase for receipt display
            $_SESSION['last_purchase'] = $purchaseRecord;

            // ── Persist to user purchase history ─────────────────────────────
            if (!isset($_SESSION['purchases']) || !is_array($_SESSION['purchases'])) {
                $_SESSION['purchases'] = [];
            }
            array_unshift($_SESSION['purchases'], $purchaseRecord);

            $checkoutSuccess = true;
            $checkoutMessage = 'Purchase <strong>' . htmlspecialchars($purchaseId) . '</strong> completed successfully!';
            $_SESSION['cart'] = [];
            $total = 0;

            // Store checkout success message in session before redirecting for OTP
            $_SESSION['checkout_success_message'] = $checkoutMessage;

            // Generate OTP for checkout
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expires_at'] = time() + (5 * 60); // OTP valid for 5 minutes
            $_SESSION['otp_user_id'] = $_SESSION['user']['user_id'];
            $_SESSION['otp_phone_number'] = $_SESSION['user']['phone'] ?? ''; // Get phone from user session
            $_SESSION['otp_flow'] = 'checkout'; // Indicate OTP flow for checkout

            // Redirect to OTP verification page
            header('Location: verify_otp.php');
            exit;
        }
    }
}
$paymentMethods = ['Credit Card', 'GCash', 'PayMaya'];
$selectedPayment = '';

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold text-gray-800 mb-8">Your Shopping Cart</h1>
    
    <?php if ($checkoutSuccess): ?>
    <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg mb-6">
        <?php echo $checkoutMessage; /* contains safe HTML with purchase ID */ ?>
        <?php if (isset($_SESSION['last_purchase'])): ?>
        <p class="text-sm mt-1">
            Payment: <strong><?php echo htmlspecialchars($_SESSION['last_purchase']['payment_method']); ?></strong>
            &nbsp;•&nbsp; <?php echo htmlspecialchars($_SESSION['last_purchase']['purchased_at']); ?>
        </p>
        <?php
            $hasAdvancedPenetrationTesting = false;
            foreach ($_SESSION['last_purchase']['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === 'Advanced Penetration Testing') {
                    $hasAdvancedPenetrationTesting = true;
                    break;
                }
            }
            if ($hasAdvancedPenetrationTesting):
        ?>
        <div class="mt-4">
            <a href="Advanced_Penetration_Testing.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go to Advanced Penetration Testing Course
            </a>
        </div>
        <?php endif; ?>
        <?php
            $hasNetworkSecurityFundamentals = false;
            foreach ($_SESSION['last_purchase']['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === 'Network Security Fundamentals') {
                    $hasNetworkSecurityFundamentals = true;
                    break;
                }
            }
            if ($hasNetworkSecurityFundamentals):
        ?>
        <div class="mt-4">
            <a href="Network_Security_Fundamentals.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go to Network Security Fundamentals Book
            </a>
        </div>
        <?php endif; ?>
        <?php
            $hasMalwareAnalysisLabAccess = false;
            foreach ($_SESSION['last_purchase']['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === 'Malware Analysis Lab Access') {
                    $hasMalwareAnalysisLabAccess = true;
                    break;
                }
            }
            if ($hasMalwareAnalysisLabAccess):
        ?>
        <div class="mt-4">
            <a href="Malware_Analysis_Lab_Access.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go to Malware Analysis Lab Access Resource
            </a>
        </div>
        <?php endif; ?>
        <?php
            $hasSOCAnalystBootcamp = false;
            foreach ($_SESSION['last_purchase']['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === 'SOC Analyst Bootcamp') {
                    $hasSOCAnalystBootcamp = true;
                    break;
                }
            }
            if ($hasSOCAnalystBootcamp):
        ?>
        <div class="mt-4">
            <a href="SOC_Analyst_Bootcamp.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go to SOC Analyst Bootcamp Course
            </a>
        </div>
        <?php endif; ?>
        <?php
            $hasPythonForCybersecurity = false;
            foreach ($_SESSION['last_purchase']['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === 'Python for Cybersecurity') {
                    $hasPythonForCybersecurity = true;
                    break;
                }
            }
            if ($hasPythonForCybersecurity):
        ?>
        <div class="mt-4">
            <a href="Python_for_Cybersecurity.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go to Python for Cybersecurity Course
            </a>
        </div>
        <?php endif; ?>
        <?php
            $hasWebApplicationsHackersHandbook = false;
            foreach ($_SESSION['last_purchase']['items'] as $item) {
                if (isset($item['product_name']) && $item['product_name'] === "Web Application Hacker's Handbook") {
                    $hasWebApplicationsHackersHandbook = true;
                    break;
                }
            }
            if ($hasWebApplicationsHackersHandbook):
        ?>
        <div class="mt-4">
            <a href="Web_Application_Hackers_Handbook.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go to Web Application Hacker's Handbook Book
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($checkoutErrors)): ?>
    <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-6">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside text-sm space-y-0.5">
            <?php foreach ($checkoutErrors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <?php if (empty($_SESSION['cart']) && !$checkoutSuccess): ?>
                <div class="bg-white rounded-xl shadow-md p-12 text-center">
                    <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <h2 class="text-2xl font-bold text-gray-700 mb-2">Your cart is empty</h2>
                    <p class="text-gray-500 mb-6">Add some products to get started!</p>
                    <a href="market.php" class="inline-block bg-blue-900 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-800 transition">
                        Browse Marketplace
                    </a>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                    <div class="flex gap-4 p-6 border-b border-gray-200 last:border-0 items-center">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-24 h-24 object-cover rounded-lg flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-bold text-gray-800 truncate"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($item['category']); ?></p>
                                </div>
                                <form method="POST" action="remove_from_cart.php" class="flex-shrink-0">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-600 transition" title="Remove">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                            <p class="text-xl font-bold text-pink-700 mt-2">₱<?php echo number_format($item['price'], 2); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md p-6 sticky top-24">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Purchase Summary</h2>
                
                <?php 
                $summaryItems    = !empty($_SESSION['cart']) ? $_SESSION['cart'] : [];
                $summarySubtotal = $total;
                $summaryTax      = $total * 0.12;
                $summaryGrand    = $total * 1.12;
                $summaryPayment  = '';
                // After checkout, pull from the saved snapshot
                $lastPurchase = $_SESSION['last_purchase'] ?? $_SESSION['last_order'] ?? null;
                if ($checkoutSuccess && $lastPurchase) {
                    $summaryItems    = $lastPurchase['items'];
                    $summarySubtotal = $lastPurchase['subtotal'];
                    $summaryTax      = $lastPurchase['tax'];
                    $summaryGrand    = $lastPurchase['grand_total'];
                    $summaryPayment  = $lastPurchase['payment_method'];
                }
                ?>
                <?php if (!empty($summaryItems) || $checkoutSuccess): ?>
                <div class="space-y-4 mb-6">
                    <?php
                    $displayItems = [];
                    foreach ($summaryItems as $item) {
                        $displayItems[] = [
                            'name' => $item['name'] ?? $item['product_name'] ?? 'Unknown Product',
                            'price' => $item['price'] ?? $item['unit_price'] ?? 0.00
                        ];
                    }
                    ?>
                    <?php foreach ($displayItems as $item): ?>
                    <div class="flex justify-between text-gray-700 text-sm">
                        <span><?php echo htmlspecialchars($item['name']); ?></span>
                        <span>₱<?php echo number_format($item['price'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <div class="flex justify-between text-gray-700">
                            <span>Subtotal</span>
                            <span>₱<?php echo number_format($summarySubtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-gray-700">
                            <span>Tax (12%)</span>
                            <span>₱<?php echo number_format($summaryTax, 2); ?></span>
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-200 pt-4 mb-6">
                    <div class="flex justify-between text-xl font-bold text-gray-800">
                        <span>Total</span>
                        <span>₱<?php echo number_format($summaryGrand, 2); ?></span>
                    </div>
                    <?php if ($summaryPayment): ?>
                    <p class="text-sm text-gray-500 mt-2">Paid via: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($summaryPayment); ?></span></p>
                    <?php endif; ?>
                </div>

                <?php if (!$checkoutSuccess): ?>
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-700 mb-3">Payment Method</h3>
                    <div class="space-y-2">
                        <?php foreach ($paymentMethods as $method): ?>
                        <button 
                            type="button" 
                            class="w-full flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 transition payment-method-btn"
                            data-method="<?php echo htmlspecialchars($method); ?>"
                        >
                            <input type="radio" name="payment_method_radio" value="<?php echo htmlspecialchars($method); ?>" class="w-4 h-4 text-pink-700">
                            <span class="text-gray-700"><?php echo htmlspecialchars($method); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" id="open-checkout-modal" class="w-full bg-pink-700 text-white py-4 rounded-lg font-bold text-xl hover:bg-pink-800 transition">
                    Checkout
                </button>
                <?php endif; ?>
                <?php endif; ?>
                
                <a href="market.php" class="block text-center mt-4 text-blue-800 font-semibold hover:underline">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>

<div id="payment-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Enter Payment Details</h3>
            <button type="button" id="close-modal" class="text-gray-500 hover:text-gray-800">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" id="checkout-form">
            <input type="hidden" name="payment_method" id="payment-method-input">
            
            <div id="credit-card-fields" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Cardholder Name</label>
                    <input type="text" id="cardholder-name" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="John Doe">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Card Number</label>
                    <input type="text" id="card-number" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="1234 5678 9012 3456" maxlength="19">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Expiration Date</label>
                        <input type="text" id="expiry-date" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="MM/YY" maxlength="5">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">CVV</label>
                        <input type="text" id="cvv" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="123" maxlength="4">
                    </div>
                </div>
            </div>

            <div id="mobile-payment-fields" class="space-y-4 hidden">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" id="mobile-number-label">Mobile Number</label>
                    <input type="text" id="mobile-number" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="09XXXXXXXXX">
                </div>
            </div>

            <div class="hidden">
                <textarea name="payment_details" id="payment-details-input" rows="4"></textarea>
            </div>

            <button 
                type="submit" 
                name="checkout" 
                class="w-full bg-pink-700 text-white py-4 rounded-lg font-bold text-xl hover:bg-pink-800 transition mt-6"
            >
                Complete Checkout
            </button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.payment-method-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('border-blue-500', 'bg-blue-50'));
        this.classList.add('border-blue-500', 'bg-blue-50');
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
    });
});

const modal = document.getElementById('payment-modal');
const openBtn = document.getElementById('open-checkout-modal');
const closeBtn = document.getElementById('close-modal');
const paymentMethodInput = document.getElementById('payment-method-input');
const paymentDetailsInput = document.getElementById('payment-details-input');
const creditCardFields = document.getElementById('credit-card-fields');
const mobilePaymentFields = document.getElementById('mobile-payment-fields');
const mobileNumberLabel = document.getElementById('mobile-number-label');
const checkoutForm = document.getElementById('checkout-form');

const cardNumberInput = document.getElementById('card-number');
const expiryDateInput = document.getElementById('expiry-date');
const cvvInput = document.getElementById('cvv');
const mobileNumberInput = document.getElementById('mobile-number');

// Card number formatting
cardNumberInput.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = value;
});

// Expiry date formatting
expiryDateInput.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }
    e.target.value = value;
});

if (openBtn) {
    openBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const selectedPayment = document.querySelector('input[name="payment_method_radio"]:checked');
        if (selectedPayment) {
            paymentMethodInput.value = selectedPayment.value;
            
            const cardholderNameInput = document.getElementById('cardholder-name');
            const cardNumberInputEl = document.getElementById('card-number');
            const expiryDateInputEl = document.getElementById('expiry-date');
            const cvvInputEl = document.getElementById('cvv');
            const mobileNumberInputEl = document.getElementById('mobile-number');
            
            if (selectedPayment.value === 'Credit Card') {
                creditCardFields.classList.remove('hidden');
                mobilePaymentFields.classList.add('hidden');
                
                // Enable required for credit card fields
                cardholderNameInput.required = true;
                cardNumberInputEl.required = true;
                expiryDateInputEl.required = true;
                cvvInputEl.required = true;
                mobileNumberInputEl.required = false;
            } else {
                creditCardFields.classList.add('hidden');
                mobilePaymentFields.classList.remove('hidden');
                mobileNumberLabel.textContent = selectedPayment.value + ' Number';
                document.getElementById('mobile-number').placeholder = 'Enter ' + selectedPayment.value + ' number';
                
                // Disable required for credit card fields, enable for mobile
                cardholderNameInput.required = false;
                cardNumberInputEl.required = false;
                expiryDateInputEl.required = false;
                cvvInputEl.required = false;
                mobileNumberInputEl.required = true;
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } else {
            alert('Please select a payment method first.');
        }
    });
}

if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
        const selectedPayment = paymentMethodInput.value;
        let details = '';
        
        if (selectedPayment === 'Credit Card') {
            const cardholder = document.getElementById('cardholder-name').value;
            const cardNumber = cardNumberInput.value;
            const expiry = expiryDateInput.value;
            const cvv = cvvInput.value;
            details = `Cardholder: ${cardholder}, Card: ${cardNumber}, Expiry: ${expiry}, CVV: ${cvv}`;
        } else {
            details = `${selectedPayment} Number: ${mobileNumberInput.value}`;
        }
        
        paymentDetailsInput.value = details;
    });
}

if (closeBtn) {
    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
}

modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
});

// Idle timeout: Redirect to login after 1 minute of inactivity
let idleTimer;
const IDLE_TIMEOUT = 60000; // 1 minute in milliseconds

function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(redirectToLogin, IDLE_TIMEOUT);
}

function redirectToLogin() {
    window.location.href = 'index.php';
}

// Reset timer on user activity
document.addEventListener('mousemove', resetIdleTimer);
document.addEventListener('mousedown', resetIdleTimer);
document.addEventListener('keypress', resetIdleTimer);
document.addEventListener('scroll', resetIdleTimer);
document.addEventListener('touchmove', resetIdleTimer);
document.addEventListener('touchstart', resetIdleTimer);

// Initialize the timer when page loads
resetIdleTimer();
</script>

<?php include 'includes/footer.php'; ?>
