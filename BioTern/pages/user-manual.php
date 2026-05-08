<?php
$manualRole = strtolower(trim((string)($_GET['role'] ?? 'student')));
$manualFiles = [
    'admin' => 'admin-manual.pdf',
    'coordinator' => 'coordinator-manual.pdf',
    'supervisor' => 'supervisor-manual.pdf',
    'student' => 'student-manual.pdf',
];

if (!isset($manualFiles[$manualRole])) {
    $manualRole = 'student';
}

$manualPath = dirname(__DIR__) . '/uploads/manuals/' . $manualFiles[$manualRole];
if (!is_file($manualPath) || !is_readable($manualPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Manual not found.';
    exit;
}

$downloadName = $manualFiles[$manualRole];
header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($manualPath));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($manualPath);
