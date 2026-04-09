<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    exit;
}

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS user_profile_pictures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    image_mime VARCHAR(64) NOT NULL,
    image_data LONGBLOB NOT NULL,
    image_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_profile_picture (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("SELECT image_mime, image_data, updated_at FROM user_profile_pictures WHERE user_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit;
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($mime, $blob, $updatedAt);
$hasRow = $stmt->fetch();
$stmt->close();

if (!$hasRow || !is_string($blob) || $blob === '') {
    $fallback = __DIR__ . '/../assets/images/avatar/' . (($userId % 5) + 1) . '.png';
    if (is_file($fallback)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=300');
        readfile($fallback);
        exit;
    }
    http_response_code(404);
    exit;
}

$mime = is_string($mime) && $mime !== '' ? $mime : 'image/png';
$ts = strtotime((string)$updatedAt);
if ($ts !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $ts) . ' GMT');
}
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=300');
echo $blob;
