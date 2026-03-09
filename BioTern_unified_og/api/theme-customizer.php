<?php

require_once dirname(__DIR__) . '/includes/theme-preferences.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'preferences' => biotern_theme_preferences(),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);
    exit;
}

$existing = biotern_theme_preferences();
$payload = [];

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (empty($payload) && !empty($_POST)) {
    $payload = $_POST;
}

$merged = array_merge($existing, array_intersect_key($payload, [
    'skin' => true,
    'menu' => true,
    'font' => true,
    'navigation' => true,
    'header' => true,
]));
$saved = biotern_save_theme_preferences($merged);

echo json_encode([
    'success' => true,
    'preferences' => $saved,
]);
