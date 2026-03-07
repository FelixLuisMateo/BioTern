<?php
// Check Laravel migration status
echo "=== Laravel Migration Status ===\n\n";

$projectPath = __DIR__;
chdir($projectPath);

// Check if composer packages are installed
if (!file_exists('vendor/autoload.php')) {
    echo "! Composer packages not installed. Installing...\n";
    exec('composer install 2>&1', $output);
    foreach ($output as $line) {
        echo $line . "\n";
    }
}

// Now run artisan with better error handling
echo "\n=== Running Migrations ===\n";
exec('php artisan migrate:status', $status_output, $status_code);

if ($status_code !== 0) {
    echo "Error running migrations:\n";
    foreach ($status_output as $line) {
        echo $line . "\n";
    }
} else {
    echo "Migration status:\n";
    foreach ($status_output as $line) {
        echo $line . "\n";
    }
}

// Now actually run the migrate command
echo "\n=== Executing migrate:fresh ===\n";
exec('php artisan migrate:fresh 2>&1', $migrate_output, $migrate_code);

echo "Exit code: $migrate_code\n";
if (!empty($migrate_output)) {
    foreach ($migrate_output as $line) {
        echo $line . "\n";
    }
}

// Check database connection
echo "\n=== Database Connection Check ===\n";
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'biotern_db';

try {
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $tables = $conn->query("SHOW TABLES");
    if ($tables) {
        echo "Tables in database: " . $tables->num_rows . "\n";
        while($row = $tables->fetch_assoc()) {
            echo "  - " . implode(",", $row) . "\n";
        }
    }
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
