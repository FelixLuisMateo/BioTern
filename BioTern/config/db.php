<?php
mysqli_report(MYSQLI_REPORT_OFF);

$biotern_project_root = dirname(__DIR__);
$biotern_include_candidates = [
    $biotern_project_root,
    $biotern_project_root . DIRECTORY_SEPARATOR . 'includes',
    $biotern_project_root . DIRECTORY_SEPARATOR . 'config',
];

$biotern_include_paths = [];
foreach ($biotern_include_candidates as $candidate) {
    if (is_dir($candidate)) {
        $real_candidate = realpath($candidate);
        $biotern_include_paths[] = $real_candidate !== false ? $real_candidate : $candidate;
    }
}

$existing_include_path = get_include_path();
if ($existing_include_path !== false && $existing_include_path !== '') {
    $biotern_include_paths[] = $existing_include_path;
}

set_include_path(implode(PATH_SEPARATOR, array_values(array_unique($biotern_include_paths))));

if (!function_exists('biotern_env_pick')) {
    function biotern_env_pick(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return (string)$value;
            }
        }
        return $default;
    }
}

$databaseUrl = biotern_env_pick(['DATABASE_URL', 'MYSQL_URL', 'DB_URL'], '');

$resolvedHost = '';
$resolvedUser = '';
$resolvedPass = '';
$resolvedName = '';
$resolvedPort = '';

if ($databaseUrl !== '') {
    $parsed = parse_url($databaseUrl);
    if (is_array($parsed)) {
        $resolvedHost = isset($parsed['host']) ? (string)$parsed['host'] : '';
        $resolvedUser = isset($parsed['user']) ? (string)$parsed['user'] : '';
        $resolvedPass = isset($parsed['pass']) ? (string)$parsed['pass'] : '';
        $resolvedName = isset($parsed['path']) ? ltrim((string)$parsed['path'], '/') : '';
        $resolvedPort = isset($parsed['port']) ? (string)$parsed['port'] : '';
    }
}

if ($resolvedHost === '') {
    $resolvedHost = biotern_env_pick(['DB_HOST', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST'], '127.0.0.1');
}
if ($resolvedUser === '') {
    $resolvedUser = biotern_env_pick(['DB_USER', 'MYSQLUSER', 'RAILWAY_MYSQL_USER'], 'root');
}
if ($resolvedPass === '') {
    $resolvedPass = biotern_env_pick(['DB_PASS', 'MYSQLPASSWORD', 'RAILWAY_MYSQL_PASSWORD'], '');
}
if ($resolvedName === '') {
    $resolvedName = biotern_env_pick(['DB_NAME', 'MYSQLDATABASE', 'RAILWAY_MYSQL_DATABASE'], 'biotern_db');
}
if ($resolvedPort === '') {
    $resolvedPort = biotern_env_pick(['DB_PORT', 'MYSQLPORT', 'RAILWAY_MYSQL_PORT'], '3306');
}

$resolvedPortInt = (int)$resolvedPort;
if ($resolvedPortInt <= 0) {
    $resolvedPortInt = 3306;
}

if (!defined('DB_HOST')) {
    define('DB_HOST', $resolvedHost);
}
if (!defined('DB_USER')) {
    define('DB_USER', $resolvedUser);
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $resolvedPass);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $resolvedName);
}
if (!defined('DB_PORT')) {
    define('DB_PORT', $resolvedPortInt);
}

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if ($conn->connect_errno) {
    $safeHost = DB_HOST !== '' ? DB_HOST : 'unknown-host';
    $safeDb = DB_NAME !== '' ? DB_NAME : 'unknown-db';
    die('Database connection failed. Please verify DB env variables (DB_HOST/DB_USER/DB_PASS/DB_NAME/DB_PORT or Railway MYSQL* vars). Current host=' . $safeHost . ', db=' . $safeDb . '. Error: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');



