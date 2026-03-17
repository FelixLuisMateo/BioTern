<?php
// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') !== false ? (string)getenv('DB_HOST') : '127.0.0.1');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') !== false ? (string)getenv('DB_USER') : 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') !== false ? (string)getenv('DB_NAME') : 'biotern_db');
}
if (!defined('DB_PORT')) {
    $dbPort = getenv('DB_PORT');
    define('DB_PORT', $dbPort !== false ? (int)$dbPort : 3306);
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
