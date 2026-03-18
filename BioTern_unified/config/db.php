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

if (!function_exists('biotern_mysql_url_parts')) {
    function biotern_mysql_url_parts(): array
    {
        static $parts = null;
        if ($parts !== null) {
            return $parts;
        }

        $parts = [
            'host' => null,
            'port' => null,
            'database' => null,
            'user' => null,
            'pass' => null,
        ];

        $url = biotern_env_first(['MYSQL_URL'], '');
        if (!is_string($url) || trim($url) === '') {
            return $parts;
        }

        $parsed = @parse_url($url);
        if (!is_array($parsed)) {
            return $parts;
        }

        if (!empty($parsed['host'])) {
            $parts['host'] = (string)$parsed['host'];
        }
        if (!empty($parsed['port'])) {
            $parts['port'] = (int)$parsed['port'];
        }
        if (!empty($parsed['user'])) {
            $parts['user'] = (string)$parsed['user'];
        }
        if (array_key_exists('pass', $parsed)) {
            $parts['pass'] = (string)$parsed['pass'];
        }
        if (!empty($parsed['path'])) {
            $db = ltrim((string)$parsed['path'], '/');
            if ($db !== '') {
                $parts['database'] = $db;
            }
        }

        return $parts;
    }
}

$biotern_mysql_url = biotern_mysql_url_parts();

if (!defined('DB_HOST')) {
    define('DB_HOST', (string)biotern_env_first(['DB_HOST', 'DB_HOST_ONLINE'], $biotern_mysql_url['host'] ?? '127.0.0.1'));
}
if (!defined('DB_USER')) {
    define('DB_USER', (string)biotern_env_first(['DB_USER', 'DB_USERNAME', 'DB_USERNAME_ONLINE'], $biotern_mysql_url['user'] ?? 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string)biotern_env_first(['DB_PASS', 'DB_PASSWORD', 'DB_PASSWORD_ONLINE'], $biotern_mysql_url['pass'] ?? ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string)biotern_env_first(['DB_NAME', 'DB_DATABASE', 'DB_DATABASE_ONLINE'], $biotern_mysql_url['database'] ?? 'biotern_db'));
}
if (!defined('DB_PORT')) {
    $dbPort = biotern_env_first(['DB_PORT', 'DB_PORT_ONLINE'], $biotern_mysql_url['port'] ?? 3306);
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
