<?php
require_once __DIR__ . '/config/db.php';
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: documents/document_endorsement.php' . $qs);
exit;



