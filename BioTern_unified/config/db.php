<?php
// Database configuration
if (!function_exists('biotern_env_first')) {
    function biotern_env_first(array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== null && $value !== '') {
                return $value;
            }
        }
        return $default;
    }
}

if (!defined('DB_HOST')) {
    define('DB_HOST', (string)biotern_env_first(['DB_HOST'], '127.0.0.1'));
}
if (!defined('DB_USER')) {
    define('DB_USER', (string)biotern_env_first(['DB_USER', 'DB_USERNAME'], 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string)biotern_env_first(['DB_PASS', 'DB_PASSWORD'], ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string)biotern_env_first(['DB_NAME', 'DB_DATABASE'], 'biotern_db'));
}
if (!defined('DB_PORT')) {
    $dbPort = biotern_env_first(['DB_PORT'], 3306);
    define('DB_PORT', (int)$dbPort);
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
