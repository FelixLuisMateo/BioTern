<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/attendance_rules.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
biotern_boot_session(isset($conn) ? $conn : null);
external_attendance_ensure_schema($conn);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($userId <= 0 || $role !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized.']);
    exit;
}

$student = external_attendance_student_context($conn, $userId);
if (!$student) {
    echo json_encode(['ok' => false, 'message' => 'Student record not found.']);
    exit;
}

$action = strtolower(trim((string)($_POST['action'] ?? 'clock')));
if (!in_array($action, ['clock', 'range'], true)) {
    $action = 'clock';
}

if ($action === 'clock') {
    $clockDate = trim((string)($_POST['attendance_date'] ?? $_POST['clock_date'] ?? ''));
    $clockType = trim((string)($_POST['clock_type'] ?? ''));
    $clockTime = external_attendance_normalize_time((string)($_POST['clock_time'] ?? $_POST['time'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $column = attendance_action_to_column($clockType);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clockDate) || $column === null || $clockTime === null) {
        echo json_encode(['ok' => false, 'message' => 'Valid date, clock type, and clock time are required.']);
        exit;
    }

    $existing = external_attendance_student_record($conn, (int)$student['id'], $clockDate);
    $photoPath = '';
    if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = external_attendance_store_photo($_FILES['photo'], (int)$student['id'], $clockDate);
        if (!($upload['ok'] ?? false)) {
            echo json_encode(['ok' => false, 'message' => (string)$upload['message']]);
            exit;
        }
        $photoPath = (string)$upload['path'];
    } elseif (!$existing || trim((string)($existing['photo_path'] ?? '')) === '') {
        echo json_encode(['ok' => false, 'message' => 'A verification photo is required the first time you submit for this day.']);
        exit;
    }

    $payload = [
        'morning_time_in' => null,
        'morning_time_out' => null,
        'break_time_in' => null,
        'break_time_out' => null,
        'afternoon_time_in' => null,
        'afternoon_time_out' => null,
    ];
    $payload[$column] = $clockTime;

    $saved = external_attendance_upsert_day($conn, $student, $clockDate, $payload, $photoPath, $notes, $userId);
    echo json_encode($saved);
    exit;
}

$startDate = trim((string)($_POST['attendance_date'] ?? ''));
$endDate = trim((string)($_POST['attendance_end_date'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$payload = [
    'morning_time_in' => external_attendance_normalize_time((string)($_POST['morning_time_in'] ?? '')),
    'morning_time_out' => external_attendance_normalize_time((string)($_POST['morning_time_out'] ?? '')),
    'break_time_in' => external_attendance_normalize_time((string)($_POST['break_time_in'] ?? '')),
    'break_time_out' => external_attendance_normalize_time((string)($_POST['break_time_out'] ?? '')),
    'afternoon_time_in' => external_attendance_normalize_time((string)($_POST['afternoon_time_in'] ?? '')),
    'afternoon_time_out' => external_attendance_normalize_time((string)($_POST['afternoon_time_out'] ?? '')),
];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    echo json_encode(['ok' => false, 'message' => 'Valid start and end dates are required.']);
    exit;
}
if (!isset($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'message' => 'A verification photo is required.']);
    exit;
}

$validation = external_attendance_validate_record($payload);
if (!($validation['ok'] ?? false)) {
    echo json_encode(['ok' => false, 'message' => (string)$validation['message']]);
    exit;
}

$upload = external_attendance_store_photo($_FILES['photo'], (int)$student['id'], $startDate);
if (!($upload['ok'] ?? false)) {
    echo json_encode(['ok' => false, 'message' => (string)$upload['message']]);
    exit;
}

$startTs = strtotime($startDate);
$endTs = strtotime($endDate);
if ($startTs === false || $endTs === false || $endTs < $startTs) {
    echo json_encode(['ok' => false, 'message' => 'End date must be the same as or later than start date.']);
    exit;
}

$savedCount = 0;
for ($cursor = $startTs; $cursor <= $endTs; $cursor += 86400) {
    $targetDate = date('Y-m-d', $cursor);
    $saved = external_attendance_upsert_day($conn, $student, $targetDate, $payload, (string)$upload['path'], $notes, $userId, true);
    if (!empty($saved['ok'])) {
        $savedCount++;
    }
}

echo json_encode([
    'ok' => $savedCount > 0,
    'message' => $savedCount > 0
        ? ('External DTR range submitted for ' . $savedCount . ' day(s).')
        : 'No external attendance dates were saved.',
]);
