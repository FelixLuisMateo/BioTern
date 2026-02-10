<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'biotern_db';

try {
    // Create connection
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "✓ Connected to MySQL successfully\n";
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Database '$database' created or already exists\n";
    } else {
        echo "✗ Error creating database: " . $conn->error . "\n";
    }
    
    // Select database
    $conn->select_db($database);
    
    // Check tables
    $tables = $conn->query("SHOW TABLES");
    if ($tables->num_rows > 0) {
        echo "✓ Database has " . $tables->num_rows . " table(s)\n";
        while($row = $tables->fetch_assoc()) {
            echo "  - " . implode(", ", $row) . "\n";
        }
    } else {
        echo "! Database is empty. You need to run: php artisan migrate\n";
    }
    
    $conn->close();
    echo "\n✓ Setup check complete!\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
