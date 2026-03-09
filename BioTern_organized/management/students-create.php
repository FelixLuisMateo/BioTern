<?php
require_once dirname(__DIR__) . '/config/db.php';
// Fallback for missing students-create page: redirect to friendly 404
header('Location: idnotfound-404.php?source=students-create');
exit;
?>

