<?php
require_once dirname(__DIR__) . '/config/db.php';

if (!function_exists('biometric_shared_db')) {
    function biometric_shared_db(bool $reuseExisting = false): mysqli
    {
        global $conn;

        if ($reuseExisting && isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
            return $conn;
        }

        $db = new mysqli(
            defined('DB_HOST') ? DB_HOST : '127.0.0.1',
            defined('DB_USER') ? DB_USER : 'root',
            defined('DB_PASS') ? DB_PASS : '',
            defined('DB_NAME') ? DB_NAME : 'biotern_db',
            defined('DB_PORT') ? (int) DB_PORT : 3306
        );

        if ($db->connect_error) {
            throw new RuntimeException('Database connection failed: ' . $db->connect_error);
        }

        $db->set_charset('utf8mb4');
        return $db;
    }
}
