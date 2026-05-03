<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';

biotern_boot_session(isset($conn) ? $conn : null);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$studentId = 0;
$stmt = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $currentUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $studentId = (int)($row['id'] ?? 0);
}

if ($studentId <= 0) {
    header('Location: homepage.php');
    exit;
}

$_GET['id'] = $studentId;
require dirname(__DIR__) . '/management/students-dtr.php';
