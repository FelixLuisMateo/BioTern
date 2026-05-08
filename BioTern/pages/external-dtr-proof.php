<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';

biotern_boot_session(isset($conn) ? $conn : null);
external_attendance_ensure_schema($conn);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$attachmentId = (int)($_GET['id'] ?? 0);

if ($currentUserId <= 0 || $attachmentId <= 0) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $conn->prepare("
    SELECT a.id, a.student_id, a.file_path, a.file_name, a.file_type, a.storage_driver, a.file_blob, s.user_id AS student_user_id
    FROM external_dtr_attachments a
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
$filePath = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
if ($storageDriver !== 'database') {
    if ($filePath === '' || str_contains($filePath, '..')) {
        http_response_code(404);
        exit('Not found');
    }

    if (preg_match('/^https?:\/\//i', $filePath)) {
        header('Location: ' . $filePath);
        exit;
    }

    header('Location: ../' . $filePath);
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

$fileName = trim((string)($row['file_name'] ?? 'external-dtr-proof'));
if ($fileName === '') {
    $fileName = 'external-dtr-proof';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($blob));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
echo $blob;
