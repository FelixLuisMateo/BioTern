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

$assignmentTrack = 'internal';
$studentStmt = $conn->prepare('SELECT assignment_track FROM students WHERE user_id = ? LIMIT 1');
if ($studentStmt) {
    $studentStmt->bind_param('i', $currentUserId);
    $studentStmt->execute();
    $studentRow = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
    $assignmentTrack = strtolower(trim((string)($studentRow['assignment_track'] ?? 'internal')));
}

if ($assignmentTrack === 'external') {
    header('Location: external-attendance-manual.php');
    exit;
}

header('Location: student-internal-dtr.php#manual-dtr');
exit;
