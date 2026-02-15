<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'biotern_db';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    echo "CONNECT_ERR: " . $conn->connect_error . "\n";
    exit(1);
}

// Check database exists
$res = $conn->query("SHOW DATABASES LIKE '" . $db . "'");
if (!$res || $res->num_rows == 0) {
    echo "DB_MISSING: $db not found\n";
    exit(0);
}

$conn->select_db($db);
$tables = ['students', 'attendances'];
foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '" . $t . "'");
    if (!$r || $r->num_rows == 0) {
        echo "TABLE_MISSING: $t not found in $db\n";
    } else {
        $c = $conn->query("SELECT COUNT(*) as cnt FROM `$t`");
        if ($c) {
            $row = $c->fetch_assoc();
            echo "TABLE_OK: $t count=" . ($row['cnt'] ?? '0') . "\n";
        } else {
            echo "TABLE_ERR: Could not count rows in $t: " . $conn->error . "\n";
        }
    }
}

$conn->close();
