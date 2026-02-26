<?php
require_once __DIR__ . '/lib/attendance_rules.php';
require_once __DIR__ . '/lib/ops_helpers.php';

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'biotern_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$token = isset($_SERVER['HTTP_X_API_TOKEN']) ? trim((string)$_SERVER['HTTP_X_API_TOKEN']) : '';
$expected = getenv('BIOTERN_API_TOKEN');
if ($expected !== false && $expected !== '' && !hash_equals($expected, $token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized token']);
    exit;
}

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$attendance_date = isset($_POST['attendance_date']) ? trim((string)$_POST['attendance_date']) : date('Y-m-d');
$clock_type = isset($_POST['clock_type']) ? trim((string)$_POST['clock_type']) : '';
$clock_time = isset($_POST['clock_time']) ? trim((string)$_POST['clock_time']) : date('H:i:s');
$source = isset($_POST['source']) ? trim((string)$_POST['source']) : 'device';

$column = attendance_action_to_column($clock_type);
if ($student_id <= 0 || $column === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

if (!table_exists($conn, 'biometric_event_queue')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Queue table missing. Run db_updates_operations.sql']);
    exit;
}

$ins = $conn->prepare("
    INSERT INTO biometric_event_queue
    (student_id, attendance_date, clock_type, clock_time, event_source, status, retries, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 'pending', 0, NOW(), NOW())
");
$ins->bind_param('issss', $student_id, $attendance_date, $clock_type, $clock_time, $source);
$ok = $ins->execute();
$queue_id = $ins->insert_id;
$ins->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to queue event']);
    exit;
}

insert_audit_log(
    $conn,
    get_current_user_id_or_zero(),
    'biometric_event_queued',
    'biometric_event_queue',
    (int)$queue_id,
    [],
    ['student_id' => $student_id, 'attendance_date' => $attendance_date, 'clock_type' => $clock_type, 'clock_time' => $clock_time],
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
);

echo json_encode(['success' => true, 'queue_id' => $queue_id, 'message' => 'Biometric event queued']);

