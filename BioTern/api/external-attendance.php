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
if (!in_array($action, ['clock', 'range', 'manual_table'], true)) {
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
    $photoUpload = null;
    if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = external_attendance_store_photo($_FILES['photo'], (int)$student['id'], $clockDate);
        if (!($upload['ok'] ?? false)) {
            echo json_encode(['ok' => false, 'message' => (string)$upload['message']]);
            exit;
        }
        $photoPath = (string)$upload['path'];
        $photoUpload = $upload;
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
    if (!empty($saved['ok']) && is_array($photoUpload) && (int)($saved['attendance_id'] ?? 0) > 0) {
        external_attendance_insert_attachment(
            $conn,
            (int)$student['id'],
            (int)$saved['attendance_id'],
            $clockDate,
            $photoUpload,
            $notes !== '' ? $notes : 'External quick DTR proof',
            $userId
        );
    }
    echo json_encode($saved);
    exit;
}

if ($action === 'manual_table') {
    $dates = isset($_POST['dates']) && is_array($_POST['dates']) ? $_POST['dates'] : [];
    $morningIn = isset($_POST['morning_time_in']) && is_array($_POST['morning_time_in']) ? $_POST['morning_time_in'] : [];
    $morningOut = isset($_POST['morning_time_out']) && is_array($_POST['morning_time_out']) ? $_POST['morning_time_out'] : [];
    $afternoonIn = isset($_POST['afternoon_time_in']) && is_array($_POST['afternoon_time_in']) ? $_POST['afternoon_time_in'] : [];
    $afternoonOut = isset($_POST['afternoon_time_out']) && is_array($_POST['afternoon_time_out']) ? $_POST['afternoon_time_out'] : [];
    $notes = trim((string)($_POST['notes'] ?? ''));

    $savedCount = 0;
    $lastError = '';
    $photoPath = null;
    $proofUpload = null;
    $hasProofUpload = isset($_FILES['proof_image'])
        && is_array($_FILES['proof_image'])
        && (int)($_FILES['proof_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasSubmittableRow = false;
    $firstValidDate = '';

    foreach ($dates as $index => $dateCandidate) {
        $dateCandidate = trim((string)$dateCandidate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCandidate)) {
            continue;
        }
        if ($firstValidDate === '') {
            $firstValidDate = $dateCandidate;
        }
        foreach ([$morningIn, $morningOut, $afternoonIn, $afternoonOut] as $timeGroup) {
            if (external_attendance_normalize_time((string)($timeGroup[$index] ?? '')) !== null) {
                $hasSubmittableRow = true;
                break 2;
            }
        }
    }

    if ($firstValidDate === '') {
        echo json_encode(['ok' => false, 'message' => 'Choose at least one valid date before submitting external DTR.']);
        exit;
    }

    if (!$hasSubmittableRow && !$hasProofUpload) {
        echo json_encode(['ok' => false, 'message' => 'No manual external DTR rows were saved. Fill at least one row or upload proof for an existing row first.']);
        exit;
    }

    if ($hasProofUpload) {
        $upload = external_attendance_store_photo($_FILES['proof_image'], (int)$student['id'], $firstValidDate);
        if (empty($upload['ok'])) {
            echo json_encode(['ok' => false, 'message' => (string)($upload['message'] ?? 'Could not upload proof image.')]);
            exit;
        }
        $photoPath = (string)($upload['path'] ?? '');
        $proofUpload = $upload;
    }

    foreach ($dates as $index => $dateValue) {
        $dateValue = trim((string)$dateValue);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            continue;
        }

        $payload = [
            'morning_time_in' => external_attendance_normalize_time((string)($morningIn[$index] ?? '')),
            'morning_time_out' => external_attendance_normalize_time((string)($morningOut[$index] ?? '')),
            'afternoon_time_in' => external_attendance_normalize_time((string)($afternoonIn[$index] ?? '')),
            'afternoon_time_out' => external_attendance_normalize_time((string)($afternoonOut[$index] ?? '')),
        ];

        $hasPunch = false;
        foreach ($payload as $value) {
            if ($value !== null && $value !== '') {
                $hasPunch = true;
                break;
            }
        }

        if (!$hasPunch) {
            if (is_array($proofUpload)) {
                $existingExternalRecord = external_attendance_student_record($conn, (int)$student['id'], $dateValue);
                if ($existingExternalRecord && (int)($existingExternalRecord['id'] ?? 0) > 0) {
                    $proofNotes = $notes !== '' ? $notes : (string)($existingExternalRecord['notes'] ?? '');
                    $updateProof = $conn->prepare("
                        UPDATE external_attendance
                        SET photo_path = ?,
                            notes = CASE WHEN ? <> '' THEN ? ELSE notes END,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($updateProof) {
                        $existingAttendanceId = (int)$existingExternalRecord['id'];
                        $updateProof->bind_param('sssi', $photoPath, $proofNotes, $proofNotes, $existingAttendanceId);
                        $updateProof->execute();
                        $updateProof->close();
                        external_attendance_insert_attachment(
                            $conn,
                            (int)$student['id'],
                            $existingAttendanceId,
                            $dateValue,
                            $proofUpload,
                            $notes !== '' ? $notes : 'External manual DTR proof',
                            $userId
                        );
                        $savedCount++;
                    }
                }
            }
            continue;
        }

        $saved = external_attendance_upsert_day(
            $conn,
            $student,
            $dateValue,
            $payload,
            $photoPath,
            $notes,
            $userId,
            false,
            'manual'
        );

        if (!empty($saved['ok'])) {
            if (is_array($proofUpload) && (int)($saved['attendance_id'] ?? 0) > 0) {
                external_attendance_insert_attachment(
                    $conn,
                    (int)$student['id'],
                    (int)$saved['attendance_id'],
                    $dateValue,
                    $proofUpload,
                    $notes !== '' ? $notes : 'External manual DTR proof',
                    $userId
                );
            }
            $savedCount++;
        } else {
            $lastError = trim((string)($saved['message'] ?? ''));
        }
    }

    if ($savedCount > 0) {
        echo json_encode([
            'ok' => true,
            'saved_count' => $savedCount,
            'message' => 'Manual external DTR saved for ' . $savedCount . ' day(s).',
        ]);
        exit;
    }

    echo json_encode([
        'ok' => false,
        'saved_count' => 0,
        'message' => $lastError !== '' ? $lastError : 'No manual external DTR rows were saved. Fill at least one row first.',
    ]);
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
        if ((int)($saved['attendance_id'] ?? 0) > 0) {
            external_attendance_insert_attachment(
                $conn,
                (int)$student['id'],
                (int)$saved['attendance_id'],
                $targetDate,
                $upload,
                $notes !== '' ? $notes : 'External range DTR proof',
                $userId
            );
        }
        $savedCount++;
    }
}

echo json_encode([
    'ok' => $savedCount > 0,
    'message' => $savedCount > 0
        ? ('External DTR range submitted for ' . $savedCount . ' day(s).')
        : 'No external attendance dates were saved.',
]);
