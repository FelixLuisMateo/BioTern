<?php
$mysqli = new mysqli('127.0.0.1','root','','biotern_db');
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR'.PHP_EOL;
    exit(1);
}
$email = 'autotester@example.com';
$res = $mysqli->query("SELECT id,email,username,role FROM users WHERE email='".$mysqli->real_escape_string($email)."' LIMIT 1");
if ($res && $row=$res->fetch_assoc()) {
    echo json_encode($row).PHP_EOL;
} else {
    echo 'NOT_FOUND'.PHP_EOL;
}
$mysqli->close();
