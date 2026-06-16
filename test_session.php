<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Hello world!<br>";
if (session_status() === PHP_SESSION_NONE) {
    echo "Starting session...<br>";
    session_start();
}

$_SESSION['test'] = time();
var_dump($_SESSION);
?>
