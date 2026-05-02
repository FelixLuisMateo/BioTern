<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/announcements.php';

biotern_boot_session(isset($conn) ? $conn : null);

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$announcementId = (int)($_POST['announcement_id'] ?? 0);

if ($action !== 'dismiss' || $announcementId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid announcement action.']);
    exit;
}

$ok = biotern_announcements_dismiss($conn, $announcementId, $userId);
echo json_encode(['ok' => $ok]);
