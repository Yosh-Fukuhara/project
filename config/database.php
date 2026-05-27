<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'cybersphere');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}
