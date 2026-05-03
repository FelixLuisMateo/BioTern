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
    $sessionUsername = trim((string)($_SESSION['username'] ?? ''));
    $sessionEmail = trim((string)($_SESSION['email'] ?? ''));
    $fallbackStmt = $conn->prepare(
        "SELECT id, user_id
         FROM students
         WHERE ((? <> '' AND LOWER(COALESCE(student_id, '')) = LOWER(?))
            OR (? <> '' AND LOWER(COALESCE(username, '')) = LOWER(?))
            OR (? <> '' AND LOWER(COALESCE(email, '')) = LOWER(?)))
         ORDER BY id DESC
         LIMIT 1"
    );
    if ($fallbackStmt) {
        $fallbackStmt->bind_param(
            'ssssss',
            $sessionUsername,
            $sessionUsername,
            $sessionUsername,
            $sessionUsername,
            $sessionEmail,
            $sessionEmail
        );
        $fallbackStmt->execute();
        $fallbackRow = $fallbackStmt->get_result()->fetch_assoc() ?: [];
        $fallbackStmt->close();

        $studentId = (int)($fallbackRow['id'] ?? 0);
        $mappedUserId = (int)($fallbackRow['user_id'] ?? 0);
        if ($studentId > 0 && $mappedUserId !== $currentUserId) {
            $relinkStmt = $conn->prepare('UPDATE students SET user_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            if ($relinkStmt) {
                $relinkStmt->bind_param('ii', $currentUserId, $studentId);
                $relinkStmt->execute();
                $relinkStmt->close();
            }
        }
    }
}

if ($studentId <= 0) {
    header('Location: homepage.php');
    exit;
}

$_GET['id'] = $studentId;
require dirname(__DIR__) . '/management/students-dtr.php';
