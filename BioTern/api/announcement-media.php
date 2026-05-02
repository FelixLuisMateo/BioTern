<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/announcements.php';

biotern_boot_session(isset($conn) ? $conn : null);

$announcementId = (int)($_GET['id'] ?? 0);
if ($announcementId <= 0) {
    http_response_code(404);
    exit;
}

biotern_announcements_ensure_tables($conn);

$stmt = $conn->prepare('SELECT media_blob, media_mime, media_name, media_size FROM announcements WHERE id = ? AND media_blob IS NOT NULL LIMIT 1');
if (!$stmt) {
    http_response_code(404);
    exit;
}

$stmt->bind_param('i', $announcementId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$blob = is_array($row) ? (string)($row['media_blob'] ?? '') : '';
if ($blob === '') {
    http_response_code(404);
    exit;
}

$mime = trim((string)($row['media_mime'] ?? ''));
if ($mime === '') {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($blob));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo $blob;
exit;
