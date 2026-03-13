<?php
// Messaging handler for supervisor, coordinator, student
require_once '../config/db.php';
require_once '../lib/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id BIGINT UNSIGNED NOT NULL,
    to_user_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(255) NULL,
    message LONGTEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_messages_pair (from_user_id, to_user_id),
    INDEX idx_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : (isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim((string)$_POST['message']);
    if ($msg !== '' && $receiver_id > 0) {
        $subject = 'OJT Message';
        $stmt = $conn->prepare("INSERT INTO messages (from_user_id, to_user_id, subject, message, is_read, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param('iiss', $current_user_id, $receiver_id, $subject, $msg);
            $stmt->execute();
            $stmt->close();
        }

        $sender_display = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'Someone'));
        biotern_notify(
            $conn,
            $receiver_id,
            'New message',
            $sender_display . ' sent you a message.',
            'message'
        );
    } elseif ($msg !== '' && $receiver_id <= 0) {
        $messages[] = [
            'from_user_id' => 0,
            'message' => 'Select a recipient first before sending a message.',
            'created_at' => date('Y-m-d H:i:s'),
            '_system_notice' => 1,
        ];
    }
}

$messages = [];
if ($receiver_id > 0 && $current_user_id > 0) {
    $stmt_list = $conn->prepare("SELECT from_user_id, message, created_at FROM messages WHERE ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)) AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 50");
    if ($stmt_list) {
        $stmt_list->bind_param('iiii', $current_user_id, $receiver_id, $receiver_id, $current_user_id);
        $stmt_list->execute();
        $res = $stmt_list->get_result();
        while ($row = $res->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt_list->close();
    }
}

?>
<div class="messaging-box">
    <h3>Messages</h3>
    <form method="POST" action="?receiver_id=<?php echo (int)$receiver_id; ?>">
        <input type="hidden" name="receiver_id" value="<?php echo (int)$receiver_id; ?>">
        <textarea name="message" placeholder="Type your message..." required></textarea>
        <button type="submit" class="btn btn-primary mt-2">Send</button>
    </form>
    <?php if ($receiver_id <= 0): ?>
        <div class="alert alert-info mt-2 mb-2">Choose a recipient above to start a conversation.</div>
    <?php endif; ?>
    <ul class="messages-list">
        <?php if (empty($messages) && $receiver_id > 0): ?>
            <li class="text-muted">No messages yet. Start the conversation by sending the first message.</li>
        <?php endif; ?>
        <?php foreach ($messages as $msg): ?>
        <li><strong><?php echo ((int)($msg['from_user_id'] ?? 0) === $current_user_id) ? 'You' : (((int)($msg['_system_notice'] ?? 0) === 1) ? 'System' : 'User'); ?>:</strong> <?= htmlspecialchars($msg['message']) ?> <span class="msg-date"><?= htmlspecialchars($msg['created_at']) ?></span></li>
        <?php endforeach; ?>
    </ul>
</div>


