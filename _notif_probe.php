<?php
require 'c:/xampp/htdocs/BioTern/BioTern_unified/config/db.php';
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME,DB_PORT);
if($conn->connect_error){echo 'CONNECT_FAIL: '.$conn->connect_error; exit(1);} 
$res=$conn->query("SELECT n.id, n.user_id, u.name AS user_name, n.title, n.message, n.created_at FROM notifications n LEFT JOIN users u ON u.id = n.user_id ORDER BY n.id DESC LIMIT 20");
while($row=$res->fetch_assoc()){ echo json_encode($row, JSON_UNESCAPED_SLASHES).PHP_EOL; }
?>
