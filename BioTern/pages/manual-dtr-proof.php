<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$attachmentId = (int)($_GET['id'] ?? 0);

if ($currentUserId <= 0 || $attachmentId <= 0) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $conn->prepare("
    SELECT a.id, a.student_id, a.file_path, a.file_name, a.file_type, a.storage_driver, a.file_blob, s.user_id AS student_user_id
    FROM manual_dtr_attachments a
    LEFT JOIN students s ON s.id = a.student_id
    WHERE a.id = ? AND a.deleted_at IS NULL
    LIMIT 1
");
if (!$stmt) {
    http_response_code(404);
    exit('Not found');
}

$stmt->bind_param('i', $attachmentId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$isOwner = $currentRole === 'student' && (int)($row['student_user_id'] ?? 0) === $currentUserId;
$isReviewer = in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true);
if (!$isOwner && !$isReviewer) {
    http_response_code(403);
    exit('Forbidden');
}

$storageDriver = strtolower(trim((string)($row['storage_driver'] ?? 'filesystem')));
if ($storageDriver !== 'database') {
    $filePath = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
    if ($filePath === '' || str_contains($filePath, '..')) {
        http_response_code(404);
        exit('Not found');
    }

    $absolutePath = dirname(__DIR__) . '/uploads/manual_dtr/' . $filePath;
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        http_response_code(404);
        exit('Not found');
    }

    $mime = trim((string)($row['file_type'] ?? ''));
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $mime = 'application/octet-stream';
    }

    $fileName = trim((string)($row['file_name'] ?? 'manual-dtr-proof'));
    if ($fileName === '') {
        $fileName = 'manual-dtr-proof';
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($absolutePath));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
    readfile($absolutePath);
    exit;
}

$blob = (string)($row['file_blob'] ?? '');
if ($blob === '') {
    http_response_code(404);
    exit('Not found');
}

$mime = trim((string)($row['file_type'] ?? ''));
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    $mime = 'application/octet-stream';
}

$fileName = trim((string)($row['file_name'] ?? 'manual-dtr-proof'));
if ($fileName === '') {
    $fileName = 'manual-dtr-proof';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($blob));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
echo $blob;
