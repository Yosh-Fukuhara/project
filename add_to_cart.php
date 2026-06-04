<?php
require_once 'includes/bootstrap.php';

require_once 'data/products.php';

// Guests cannot add to cart — redirect to login
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'market.php';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    $redirectTo = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'market.php';

    $foundProduct = null;
    foreach ($products as $product) {
        if ($product['id'] === $productId) {
            $foundProduct = $product;
            break;
        }
    }

    if ($foundProduct) {
        // Check if already in cart
        $foundInCart = false;
        foreach ($_SESSION['cart'] as $item) {
            if ($item['id'] === $foundProduct['id']) {
                $foundInCart = true;
                break;
            }
        }

        if (!$foundInCart) {
            $_SESSION['cart'][] = [
                'id' => $foundProduct['id'],
                'name' => $foundProduct['name'],
                'price' => $foundProduct['price'],
                'category' => $foundProduct['category'],
                'image' => $foundProduct['image'],
                'quantity' => 1
            ];
            $_SESSION['market_message'] = "Added " . htmlspecialchars($foundProduct['name']) . " to your cart!";
        } else {
            $_SESSION['market_message'] = "Item is already in your cart!";
        }
    }
    $redirect = $_POST['redirect'] ?? 'market.php';
    header('Location: ' . $redirect);
    exit;
} else {
    header('Location: market.php');
}
