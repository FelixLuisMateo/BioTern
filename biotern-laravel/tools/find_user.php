<?php
$mysqli = new mysqli('127.0.0.1','root','','biotern_db');
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR'.PHP_EOL;
    exit(1);
}
$search = isset($argv[1]) ? $argv[1] : 'MagiToxic';
$esc = $mysqli->real_escape_string($search);
$sql = "SELECT id, name, email, username, password, role, created_at FROM users WHERE username='{$esc}' OR email='{$esc}' OR name='{$esc}' LIMIT 1";
$res = $mysqli->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;
} else {
    echo 'NOT_FOUND'.PHP_EOL;
}
$mysqli->close();
