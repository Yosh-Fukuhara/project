//comment 5/29/26 gerald

<?php
require_once 'includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['index'], $_POST['action'])) {
    $index = (int)$_POST['index'];
    $action = $_POST['action'];

    if (isset($_SESSION['cart'][$index])) {
        if ($action === 'increase') {
            $_SESSION['cart'][$index]['quantity']++;
        } elseif ($action === 'decrease') {
            $_SESSION['cart'][$index]['quantity']--;
            if ($_SESSION['cart'][$index]['quantity'] <= 0) {
                array_splice($_SESSION['cart'], $index, 1);
            }
        }
    }
}

header('Location: cart.php');
exit;
