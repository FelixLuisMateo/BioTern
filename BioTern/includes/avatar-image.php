<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('biotern_avatar_image_output_default')) {
    function biotern_avatar_image_output_default(int $userId = 0): void
    {
        $fallback = __DIR__ . '/../assets/images/avatar/' . (($userId % 5) + 1) . '.png';
        if (!is_file($fallback)) {
            $fallback = __DIR__ . '/../assets/images/avatar/1.png';
        }
        if (is_file($fallback)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=300');
            readfile($fallback);
            exit;
        }
        http_response_code(404);
        exit;
    }
}

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$userId = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
if ($userId <= 0) {
    biotern_avatar_image_output_default(1);
}

if ($sessionUserId <= 0) {
    biotern_avatar_image_output_default($userId);
}

require_once __DIR__ . '/../config/db.php';

if (!($conn instanceof mysqli)) {
    biotern_avatar_image_output_default($userId);
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
    biotern_avatar_image_output_default($userId);
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($mime, $blob, $updatedAt);
$hasRow = $stmt->fetch();
$stmt->close();

if (!$hasRow || !is_string($blob) || $blob === '') {
    biotern_avatar_image_output_default($userId);
}

$mime = is_string($mime) && $mime !== '' ? $mime : 'image/png';
$ts = strtotime((string)$updatedAt);
if ($ts !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $ts) . ' GMT');
}
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=300');
echo $blob;
