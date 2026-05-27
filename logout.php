<?php
require_once 'includes/bootstrap.php';

$_SESSION = [];
session_destroy();

if (isset($_COOKIE['last_login'])) {
    setcookie('last_login', '', time() - 3600, '/');
}

header('Location: login.php');
exit;
