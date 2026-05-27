<?php
require_once 'includes/bootstrap.php';

require_once 'data/products.php';

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
        $foundInCart = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] === $foundProduct['id']) {
                $item['quantity']++;
                $foundInCart = true;
                break;
            }
        }
        unset($item);

        if (!$foundInCart) {
            $_SESSION['cart'][] = [
                'id' => $foundProduct['id'],
                'name' => $foundProduct['name'],
                'price' => $foundProduct['price'],
                'category' => $foundProduct['category'],
                'image' => $foundProduct['image'],
                'quantity' => 1
            ];
        }
    }
}

$fragment = $redirectTo === 'market.php' ? '#products-section' : '';
header('Location: ' . $redirectTo . $fragment);
exit;
