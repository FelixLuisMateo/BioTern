<?php
require_once dirname(__DIR__) . '/config/db.php';
// Database configuration
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';
$database = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

try {
    // Create connection
    $conn = new mysqli($host, $username, $password, '', $db_port);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "âœ“ Connected to MySQL successfully\n";
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        echo "âœ“ Database '$database' created or already exists\n";
    } else {
        echo "âœ— Error creating database: " . $conn->error . "\n";
    }
    
    // Select database
    $conn->select_db($database);
    
    // Check tables
    $tables = $conn->query("SHOW TABLES");
    if ($tables->num_rows > 0) {
        echo "âœ“ Database has " . $tables->num_rows . " table(s)\n";
        while($row = $tables->fetch_assoc()) {
            echo "  - " . implode(", ", $row) . "\n";
        }
    } else {
        echo "! Database is empty. You need to run: php artisan migrate\n";
    }
    
    $conn->close();
    echo "\nâœ“ Setup check complete!\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>


