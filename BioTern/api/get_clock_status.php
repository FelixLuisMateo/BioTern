<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_db.php';
require_once dirname(__DIR__) . '/lib/attendance_workflow.php';

header('Content-Type: application/json');

try {
    $conn = biometric_shared_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get student ID from request
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id == 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

$today = date('Y-m-d');

// Check if student is currently clocked in
$query = $conn->prepare("
    SELECT 
        a.id,
        a.student_id,
        a.attendance_date,
        a.status,
        a.remarks,
        a.morning_time_in,
        a.morning_time_out,
        a.afternoon_time_in,
        a.afternoon_time_out,
        sec.attendance_session,
        sec.schedule_time_in,
        sec.schedule_time_out,
        sec.late_after_time,
        sec.weekly_schedule_json
    FROM attendances 
    a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE a.student_id = ? AND a.attendance_date = ?
    LIMIT 1
");

$query->bind_param("is", $student_id, $today);
$query->execute();
$result = $query->get_result();
$record = $result->fetch_assoc();

$is_clocked_in = false;

if ($record) {
    $openInfo = attendance_workflow_mark_incomplete_if_needed($conn, $record);
    $is_clocked_in = !empty($openInfo['clocked_in_now']);
}

echo json_encode(['is_clocked_in' => $is_clocked_in]);

$conn->close();
?>


