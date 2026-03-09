<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : ''; 
$database = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$sqlFile = __DIR__ . '/biotern_db.sql';

// Connect to MySQL
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("âŒ Connection failed: " . $conn->connect_error . "\n");
}

echo "âœ“ Connected to MySQL\n";

// Create database
$conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($database);
echo "âœ“ Database selected/created\n";

// Read and execute SQL file
if (!file_exists($sqlFile)) {
    die("âŒ SQL file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));
$count = 0;

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if (!$conn->query($statement)) {
            echo "âŒ Error executing statement: " . $conn->error . "\n";
            echo "Statement: " . substr($statement, 0, 50) . "...\n";
        } else {
            $count++;
        }
    }
}

echo "\nâœ“ Successfully executed $count SQL statements\n\n";

// Verify tables were created
echo "=== Database Tables Created ===\n";
$result = $conn->query("SHOW TABLES");
$tableCount = 0;

while ($row = $result->fetch_assoc()) {
    echo "  âœ“ " . implode(", ", $row) . "\n";
    $tableCount++;
}

echo "\nâœ“ Total tables created: $tableCount\n";
echo "\n=== Database Setup Complete! ===\n";
echo "Database: $database\n";
echo "Host: $host\n";
echo "Username: $username\n";
echo "\nâœ“ Your BioTern database is ready to use!\n";

$conn->close();
?>

