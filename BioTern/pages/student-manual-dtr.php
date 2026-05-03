<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';

biotern_boot_session(isset($conn) ? $conn : null);
external_attendance_ensure_schema($conn);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$assignmentTrack = 'internal';
$studentContext = external_attendance_student_context($conn, $currentUserId);
if ($studentContext) {
    $assignmentTrack = strtolower(trim((string)($studentContext['assignment_track'] ?? 'internal')));
}

if ($assignmentTrack === 'external') {
    header('Location: external-biometric.php#manual-dtr');
    exit;
}

define('BIOTERN_STUDENT_MANUAL_DTR_PAGE', true);
require __DIR__ . '/student-dtr.php';
