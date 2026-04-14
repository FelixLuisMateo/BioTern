<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$messageId = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;

if ($sessionUserId <= 0 || $messageId <= 0) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    exit;
}

$messageColumns = [];
$columnRes = $conn->query("SHOW COLUMNS FROM messages");
if ($columnRes instanceof mysqli_result) {
    while ($row = $columnRes->fetch_assoc()) {
        $field = strtolower((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $messageColumns[$field] = true;
        }
    }
    $columnRes->free();
}

$senderCol = isset($messageColumns['from_user_id']) ? 'from_user_id' : (isset($messageColumns['sender_id']) ? 'sender_id' : '');
$recipientCol = isset($messageColumns['to_user_id']) ? 'to_user_id' : (isset($messageColumns['recipient_id']) ? 'recipient_id' : '');
$messageIdCol = isset($messageColumns['id']) ? 'id' : '';

if ($senderCol === '' || $recipientCol === '' || $messageIdCol === '') {
    http_response_code(500);
    exit;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS chat_message_media (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id BIGINT UNSIGNED NOT NULL,
        original_name VARCHAR(255) NOT NULL DEFAULT '',
        media_mime VARCHAR(64) NOT NULL,
        media_data LONGBLOB NOT NULL,
        media_size INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_chat_message_media_message (message_id),
        INDEX idx_chat_message_media_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$stmt = $conn->prepare(
    "SELECT cmm.media_mime, cmm.media_data, cmm.updated_at
     FROM chat_message_media cmm
     INNER JOIN messages m ON m." . $messageIdCol . " = cmm.message_id
     WHERE cmm.message_id = ?
       AND (m." . $senderCol . " = ? OR m." . $recipientCol . " = ?)
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit;
}

$stmt->bind_param('iii', $messageId, $sessionUserId, $sessionUserId);
$stmt->execute();
$stmt->bind_result($mime, $blob, $updatedAt);
$hasRow = $stmt->fetch();
$stmt->close();

if (!$hasRow || !is_string($blob) || $blob === '') {
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
