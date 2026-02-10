<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'biotern_db';
$sqlFile = __DIR__ . '/biotern_db.sql';

// Connect to MySQL
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . "\n");
}

echo "✓ Connected to MySQL\n";

// Create database
$conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($database);
echo "✓ Database selected/created\n";

// Read and execute SQL file
if (!file_exists($sqlFile)) {
    die("❌ SQL file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));
$count = 0;

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if (!$conn->query($statement)) {
            echo "❌ Error executing statement: " . $conn->error . "\n";
            echo "Statement: " . substr($statement, 0, 50) . "...\n";
        } else {
            $count++;
        }
    }
}

echo "\n✓ Successfully executed $count SQL statements\n\n";

// Verify tables were created
echo "=== Database Tables Created ===\n";
$result = $conn->query("SHOW TABLES");
$tableCount = 0;

while ($row = $result->fetch_assoc()) {
    echo "  ✓ " . implode(", ", $row) . "\n";
    $tableCount++;
}

echo "\n✓ Total tables created: $tableCount\n";
echo "\n=== Database Setup Complete! ===\n";
echo "Database: $database\n";
echo "Host: $host\n";
echo "Username: $username\n";
echo "\n✓ Your BioTern database is ready to use!\n";

$conn->close();
?>
