<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$projectRoot = '/';
$projectPos = stripos($scriptName, '/BioTern/BioTern/');
if ($projectPos !== false) {
    $projectRoot = substr($scriptName, 0, $projectPos) . '/BioTern/BioTern/';
}

header('Location: ' . $projectRoot . 'homepage.php', true, 302);
exit;
