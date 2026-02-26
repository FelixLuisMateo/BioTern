<?php
require_once __DIR__ . '/../lib/attendance_rules.php';
require_once __DIR__ . '/../lib/ops_helpers.php';

$conn = new mysqli('localhost', 'root', '', 'biotern_db');
if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

if (!table_exists($conn, 'biometric_event_queue')) {
    fwrite(STDERR, "Missing biometric_event_queue table\n");
    exit(1);
}

$maxBatch = 100;
$maxRetries = 5;
$q = $conn->prepare("
    SELECT id, student_id, attendance_date, clock_type, clock_time
    FROM biometric_event_queue
    WHERE status IN ('pending','failed') AND retries < ?
    ORDER BY id ASC
    LIMIT ?
");
$q->bind_param('ii', $maxRetries, $maxBatch);
$q->execute();
$rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

$processed = 0;
$failed = 0;

foreach ($rows as $row) {
    $queueId = (int)$row['id'];
    $studentId = (int)$row['student_id'];
    $date = $row['attendance_date'];
    $clockType = $row['clock_type'];
    $clockTime = $row['clock_time'];
    $column = attendance_action_to_column($clockType);
    if ($column === null) {
        markFailed($conn, $queueId, 'Invalid clock type');
        $failed++;
        continue;
    }

    $sel = $conn->prepare("
        SELECT id, morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out
        FROM attendances
        WHERE student_id = ? AND attendance_date = ?
        LIMIT 1
    ");
    $sel->bind_param('is', $studentId, $date);
    $sel->execute();
    $att = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$att) {
        $ins = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, $column, status, created_at, updated_at) VALUES (?, ?, ?, 'pending', NOW(), NOW())");
        $ins->bind_param('iss', $studentId, $date, $clockTime);
        if (!$ins->execute()) {
            $ins->close();
            markFailed($conn, $queueId, 'Insert failed');
            $failed++;
            continue;
        }
        $ins->close();
        markProcessed($conn, $queueId);
        $processed++;
        continue;
    }

    $validation = attendance_validate_transition($att, $clockType, $clockTime);
    if (!$validation['ok']) {
        markFailed($conn, $queueId, $validation['message']);
        $failed++;
        continue;
    }

    $upd = $conn->prepare("UPDATE attendances SET $column = ?, updated_at = NOW() WHERE id = ?");
    $attendanceId = (int)$att['id'];
    $upd->bind_param('si', $clockTime, $attendanceId);
    if (!$upd->execute()) {
        $upd->close();
        markFailed($conn, $queueId, 'Update failed');
        $failed++;
        continue;
    }
    $upd->close();
    markProcessed($conn, $queueId);
    $processed++;
}

echo "Processed: {$processed}, Failed: {$failed}\n";

function markProcessed(mysqli $conn, int $queueId): void
{
    $stmt = $conn->prepare("UPDATE biometric_event_queue SET status = 'processed', processed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $stmt->close();
}

function markFailed(mysqli $conn, int $queueId, string $error): void
{
    $stmt = $conn->prepare("
        UPDATE biometric_event_queue
        SET status = 'failed', retries = retries + 1, last_error = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('si', $error, $queueId);
    $stmt->execute();
    $stmt->close();
}

