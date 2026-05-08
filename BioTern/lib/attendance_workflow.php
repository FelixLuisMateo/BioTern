<?php

require_once __DIR__ . '/section_schedule.php';
require_once __DIR__ . '/attendance_rules.php';

if (!function_exists('attendance_workflow_now')) {
    function attendance_workflow_now(): DateTimeImmutable
    {
        $tzName = trim((string)(getenv('APP_TIMEZONE') ?: 'Asia/Manila'));
        try {
            $tz = new DateTimeZone($tzName !== '' ? $tzName : 'Asia/Manila');
        } catch (Throwable $e) {
            $tz = new DateTimeZone('Asia/Manila');
        }

        return new DateTimeImmutable('now', $tz);
    }
}

if (!function_exists('attendance_workflow_today')) {
    function attendance_workflow_today(): string
    {
        return attendance_workflow_now()->format('Y-m-d');
    }
}

if (!function_exists('attendance_workflow_default_school_schedule')) {
    function attendance_workflow_default_school_schedule(): array
    {
        return [
            'schedule_time_in' => '08:00',
            'schedule_time_out' => '19:00',
            'late_after_time' => '08:00',
        ];
    }
}

if (!function_exists('attendance_workflow_table_exists')) {
    function attendance_workflow_table_exists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        $exists = ($res instanceof mysqli_result) && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->close();
        }
        return $exists;
    }
}

if (!function_exists('attendance_workflow_column_exists')) {
    function attendance_workflow_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        $exists = ($res instanceof mysqli_result) && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->close();
        }
        return $exists;
    }
}

if (!function_exists('attendance_workflow_ensure_correction_schema')) {
    function attendance_workflow_ensure_correction_schema(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS attendance_correction_requests (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                attendance_id INT NOT NULL,
                requested_by INT NOT NULL DEFAULT 0,
                requester_role VARCHAR(50) NOT NULL DEFAULT '',
                correction_reason TEXT NULL,
                requested_changes LONGTEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                reviewed_by INT NULL DEFAULT NULL,
                reviewed_at DATETIME NULL DEFAULT NULL,
                review_remarks TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_attendance_id (attendance_id),
                KEY idx_status (status),
                KEY idx_requested_by (requested_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $columns = [
            'requested_changes' => "ALTER TABLE attendance_correction_requests ADD COLUMN requested_changes LONGTEXT NULL AFTER correction_reason",
            'reviewed_by' => "ALTER TABLE attendance_correction_requests ADD COLUMN reviewed_by INT NULL DEFAULT NULL AFTER status",
            'reviewed_at' => "ALTER TABLE attendance_correction_requests ADD COLUMN reviewed_at DATETIME NULL DEFAULT NULL AFTER reviewed_by",
            'review_remarks' => "ALTER TABLE attendance_correction_requests ADD COLUMN review_remarks TEXT NULL AFTER reviewed_at",
        ];

        foreach ($columns as $column => $sql) {
            if (!attendance_workflow_column_exists($conn, 'attendance_correction_requests', $column)) {
                $conn->query($sql);
            }
        }
    }
}

if (!function_exists('attendance_workflow_fetch_student_schedule')) {
    function attendance_workflow_fetch_student_schedule(mysqli $conn, int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }

        $stmt = $conn->prepare("
            SELECT
                sec.attendance_session,
                sec.schedule_time_in,
                sec.schedule_time_out,
                sec.late_after_time,
                sec.weekly_schedule_json
            FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return $row;
    }
}

if (!function_exists('attendance_workflow_schedule_for_row')) {
    function attendance_workflow_schedule_for_row(mysqli $conn, array $attendance): array
    {
        $scheduleRow = $attendance;
        $hasInlineSchedule = false;
        foreach (['attendance_session', 'schedule_time_in', 'schedule_time_out', 'late_after_time', 'weekly_schedule_json'] as $field) {
            if (array_key_exists($field, $attendance)) {
                $hasInlineSchedule = true;
                break;
            }
        }

        if (!$hasInlineSchedule) {
            $scheduleRow = array_merge(
                $attendance,
                attendance_workflow_fetch_student_schedule($conn, (int)($attendance['student_id'] ?? 0))
            );
        }

        return section_schedule_effective_day(
            section_schedule_from_row($scheduleRow),
            (string)($attendance['attendance_date'] ?? ''),
            attendance_workflow_default_school_schedule()
        );
    }
}

if (!function_exists('attendance_workflow_schedule_bounds')) {
    function attendance_workflow_schedule_bounds(mysqli $conn, array $attendance): array
    {
        $schedule = attendance_workflow_schedule_for_row($conn, $attendance);

        return [
            'schedule' => $schedule,
            'official_start' => section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00',
            'official_end' => section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? '')) ?: '19:00:00',
            'late_after' => section_schedule_normalize_time_input((string)($schedule['late_after_time'] ?? ''))
                ?: (section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00'),
        ];
    }
}

if (!function_exists('attendance_workflow_parse_time')) {
    function attendance_workflow_parse_time(?string $time): ?int
    {
        $value = trim((string)$time);
        if ($value === '' || $value === '00:00:00') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }
}

if (!function_exists('attendance_workflow_clamped_duration_seconds')) {
    function attendance_workflow_clamped_duration_seconds(?int $startTs, ?int $endTs, string $windowStart, string $windowEnd): int
    {
        if ($startTs === null || $endTs === null || $endTs <= $startTs) {
            return 0;
        }

        return max(0, $endTs - $startTs);
    }
}

if (!function_exists('attendance_workflow_calculate_internal_hours')) {
    function attendance_workflow_calculate_internal_hours(mysqli $conn, array $attendance): float
    {
        $bounds = attendance_workflow_schedule_bounds($conn, $attendance);
        $officialStart = (string)($bounds['official_start'] ?? '08:00:00');
        $officialEnd = (string)($bounds['official_end'] ?? '19:00:00');

        $totalSeconds = 0;
        foreach ([['morning_time_in', 'morning_time_out'], ['afternoon_time_in', 'afternoon_time_out']] as $pair) {
            $startTs = attendance_workflow_parse_time($attendance[$pair[0]] ?? null);
            $endTs = attendance_workflow_parse_time($attendance[$pair[1]] ?? null);
            $totalSeconds += attendance_workflow_clamped_duration_seconds($startTs, $endTs, $officialStart, $officialEnd);
        }

        $breakInTs = attendance_workflow_parse_time($attendance['break_time_in'] ?? null);
        $breakOutTs = attendance_workflow_parse_time($attendance['break_time_out'] ?? null);
        $totalSeconds -= attendance_workflow_clamped_duration_seconds($breakInTs, $breakOutTs, $officialStart, $officialEnd);

        return round(max(0, $totalSeconds) / 3600, 2);
    }
}

if (!function_exists('attendance_workflow_open_session_info')) {
    function attendance_workflow_open_session_info(mysqli $conn, array $attendance): array
    {
        $info = [
            'is_open' => false,
            'clocked_in_now' => false,
            'session_key' => null,
            'in_column' => null,
            'out_column' => null,
            'in_time' => null,
            'cutoff_time' => null,
            'cutoff_reached' => false,
            'requires_correction' => false,
            'suggested_manual_out_time' => null,
            'elapsed_preview_seconds' => 0,
            'schedule' => attendance_workflow_schedule_for_row($conn, $attendance),
        ];

        $attendanceDate = trim((string)($attendance['attendance_date'] ?? ''));
        if ($attendanceDate === '') {
            return $info;
        }

        $openSession = null;
        if (trim((string)($attendance['afternoon_time_in'] ?? '')) !== '' && trim((string)($attendance['afternoon_time_out'] ?? '')) === '') {
            $openSession = ['session_key' => 'afternoon', 'in_column' => 'afternoon_time_in', 'out_column' => 'afternoon_time_out'];
        } elseif (trim((string)($attendance['morning_time_in'] ?? '')) !== '' && trim((string)($attendance['morning_time_out'] ?? '')) === '') {
            $openSession = ['session_key' => 'morning', 'in_column' => 'morning_time_in', 'out_column' => 'morning_time_out'];
        }

        if ($openSession === null) {
            return $info;
        }

        $bounds = attendance_workflow_schedule_bounds($conn, $attendance);
        $cutoffTime = (string)($bounds['official_end'] ?? '19:00:00');
        $inTime = trim((string)($attendance[$openSession['in_column']] ?? ''));
        $now = attendance_workflow_now();
        $today = $now->format('Y-m-d');
        $cutoffAt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $attendanceDate . ' ' . $cutoffTime,
            $now->getTimezone()
        );
        if (!$cutoffAt) {
            $cutoffAt = $now;
        }

        $inAt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $attendanceDate . ' ' . $inTime,
            $now->getTimezone()
        );
        if (!$inAt) {
            $inAt = $cutoffAt;
        }

        $cutoffReached = $attendanceDate < $today || $now >= $cutoffAt;
        $previewEnd = $cutoffReached ? $cutoffAt : $now;
        $previewSeconds = max(0, $previewEnd->getTimestamp() - $inAt->getTimestamp());

        $info['is_open'] = true;
        $info['clocked_in_now'] = ($attendanceDate === $today) && !$cutoffReached;
        $info['session_key'] = $openSession['session_key'];
        $info['in_column'] = $openSession['in_column'];
        $info['out_column'] = $openSession['out_column'];
        $info['in_time'] = $inTime !== '' ? $inTime : null;
        $info['cutoff_time'] = $cutoffTime;
        $info['cutoff_reached'] = $cutoffReached;
        $info['requires_correction'] = $cutoffReached;
        $info['suggested_manual_out_time'] = $cutoffReached ? $cutoffTime : null;
        $info['elapsed_preview_seconds'] = $previewSeconds;

        return $info;
    }
}

if (!function_exists('attendance_workflow_append_note')) {
    function attendance_workflow_append_note(?string $remarks, string $note): string
    {
        $remarks = trim((string)$remarks);
        $note = trim($note);
        if ($note === '') {
            return $remarks;
        }
        if ($remarks === '') {
            return $note;
        }
        if (stripos($remarks, $note) !== false) {
            return $remarks;
        }
        return $remarks . "\n" . $note;
    }
}

if (!function_exists('attendance_workflow_mark_incomplete_if_needed')) {
    function attendance_workflow_mark_incomplete_if_needed(mysqli $conn, array &$attendance): array
    {
        $info = attendance_workflow_open_session_info($conn, $attendance);
        if (!$info['requires_correction']) {
            return $info;
        }

        $currentStatus = strtolower(trim((string)($attendance['status'] ?? 'pending')));
        $note = 'Missing ' . str_replace('_', ' ', (string)$info['out_column']) . '. Submit a correction request with the manual clock-out time before hours can be credited.';
        $remarks = attendance_workflow_append_note((string)($attendance['remarks'] ?? ''), $note);

        if ($currentStatus !== 'rejected' && $currentStatus !== 'pending_correction' && (int)($attendance['id'] ?? 0) > 0) {
            $stmt = $conn->prepare("
                UPDATE attendances
                SET status = 'pending_correction', total_hours = 0, remarks = ?, updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt) {
                $attendanceId = (int)$attendance['id'];
                $stmt->bind_param('si', $remarks, $attendanceId);
                $stmt->execute();
                $stmt->close();
                attendance_workflow_sync_student_progress($conn, (int)($attendance['student_id'] ?? 0));
            }
        }

        if ($currentStatus !== 'rejected') {
            $attendance['status'] = 'pending_correction';
        }
        $attendance['total_hours'] = 0;
        $attendance['remarks'] = $remarks;
        return $info;
    }
}

if (!function_exists('attendance_workflow_sync_student_progress')) {
    function attendance_workflow_sync_student_progress(mysqli $conn, int $studentId): void
    {
        if ($studentId <= 0) {
            return;
        }

        $sumStmt = $conn->prepare("
            SELECT COALESCE(SUM(total_hours), 0) AS rendered
            FROM attendances
            WHERE student_id = ? AND LOWER(COALESCE(status, 'pending')) = 'approved'
        ");
        if (!$sumStmt) {
            return;
        }

        $sumStmt->bind_param('i', $studentId);
        $sumStmt->execute();
        $sumRow = $sumStmt->get_result()->fetch_assoc() ?: [];
        $sumStmt->close();
        $rendered = isset($sumRow['rendered']) ? (float)$sumRow['rendered'] : 0.0;

        $internshipLookupStmt = $conn->prepare("
            SELECT id, required_hours
            FROM internships
            WHERE student_id = ? AND status = 'ongoing'
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($internshipLookupStmt) {
            $internshipLookupStmt->bind_param('i', $studentId);
            $internshipLookupStmt->execute();
            $internship = $internshipLookupStmt->get_result()->fetch_assoc() ?: null;
            $internshipLookupStmt->close();

            if ($internship) {
                $required = max(0, (int)($internship['required_hours'] ?? 0));
                $percentage = $required > 0 ? round(($rendered / $required) * 100, 2) : 0.0;
                if ($percentage > 100) {
                    $percentage = 100.0;
                }

                $updateInternship = $conn->prepare("
                    UPDATE internships
                    SET rendered_hours = ?, completion_percentage = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                if ($updateInternship) {
                    $internshipId = (int)$internship['id'];
                    $updateInternship->bind_param('ddi', $rendered, $percentage, $internshipId);
                    $updateInternship->execute();
                    $updateInternship->close();
                }
            }
        }

        $studentLookupStmt = $conn->prepare("
            SELECT assignment_track, internal_total_hours, external_total_hours
            FROM students
            WHERE id = ?
            LIMIT 1
        ");
        if (!$studentLookupStmt) {
            return;
        }

        $studentLookupStmt->bind_param('i', $studentId);
        $studentLookupStmt->execute();
        $student = $studentLookupStmt->get_result()->fetch_assoc() ?: null;
        $studentLookupStmt->close();
        if (!$student) {
            return;
        }

        $track = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
        $roundedRendered = (int)floor($rendered);
        if ($track === 'external') {
            $total = max(0, (int)($student['external_total_hours'] ?? 0));
            $remaining = max(0, $total - $roundedRendered);
            $stmt = $conn->prepare("UPDATE students SET external_total_hours_remaining = ?, updated_at = NOW() WHERE id = ?");
        } else {
            $total = max(0, (int)($student['internal_total_hours'] ?? 0));
            $remaining = max(0, $total - $roundedRendered);
            $stmt = $conn->prepare("UPDATE students SET internal_total_hours_remaining = ?, updated_at = NOW() WHERE id = ?");
        }

        if ($stmt) {
            $stmt->bind_param('ii', $remaining, $studentId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('attendance_workflow_normalize_request_changes')) {
    function attendance_workflow_normalize_request_changes(array $changes): array
    {
        $normalized = [];
        $timeFields = [
            'morning_time_in',
            'morning_time_out',
            'break_time_in',
            'break_time_out',
            'afternoon_time_in',
            'afternoon_time_out',
        ];

        foreach ($timeFields as $field) {
            if (!array_key_exists($field, $changes)) {
                continue;
            }
            $value = trim((string)$changes[$field]);
            $normalized[$field] = $value === '' ? null : $value;
        }

        foreach (['remarks', 'status'] as $field) {
            if (array_key_exists($field, $changes)) {
                $normalized[$field] = trim((string)$changes[$field]);
            }
        }

        return $normalized;
    }
}

if (!function_exists('attendance_workflow_apply_approved_correction')) {
    function attendance_workflow_apply_approved_correction(mysqli $conn, array $request, int $reviewerId, string $reviewRemarks = ''): array
    {
        $attendanceId = (int)($request['attendance_id'] ?? 0);
        if ($attendanceId <= 0) {
            throw new RuntimeException('Correction request is missing an attendance record.');
        }

        $attendanceStmt = $conn->prepare("
            SELECT a.*, s.section_id, sec.attendance_session, sec.schedule_time_in, sec.schedule_time_out, sec.late_after_time, sec.weekly_schedule_json
            FROM attendances a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE a.id = ?
            LIMIT 1
        ");
        if (!$attendanceStmt) {
            throw new RuntimeException('Unable to load the referenced attendance record.');
        }

        $attendanceStmt->bind_param('i', $attendanceId);
        $attendanceStmt->execute();
        $attendance = $attendanceStmt->get_result()->fetch_assoc() ?: null;
        $attendanceStmt->close();
        if (!$attendance) {
            throw new RuntimeException('Attendance record no longer exists.');
        }

        $changesRaw = trim((string)($request['requested_changes'] ?? ''));
        if ($changesRaw === '') {
            throw new RuntimeException('The correction request has no manual clock-out details to approve.');
        }

        $decoded = json_decode($changesRaw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('The correction request payload is invalid.');
        }

        $changes = attendance_workflow_normalize_request_changes($decoded);
        $candidate = array_merge($attendance, $changes);

        $validation = attendance_validate_full_record($candidate);
        if (empty($validation['ok'])) {
            throw new RuntimeException((string)($validation['message'] ?? 'Attendance changes are invalid.'));
        }

        $totalHours = attendance_workflow_calculate_internal_hours($conn, $candidate);
        $remarksNote = 'Manual correction approved from request #' . (int)($request['id'] ?? 0) . '.';
        if (trim($reviewRemarks) !== '') {
            $remarksNote .= ' Review note: ' . trim($reviewRemarks);
        }
        $remarks = attendance_workflow_append_note((string)($candidate['remarks'] ?? ''), $remarksNote);
        $status = trim((string)($changes['status'] ?? 'approved'));
        if ($status === '' || strtolower($status) === 'pending_correction') {
            $status = 'approved';
        }

        $update = $conn->prepare("
            UPDATE attendances
            SET morning_time_in = ?, morning_time_out = ?, break_time_in = ?, break_time_out = ?,
                afternoon_time_in = ?, afternoon_time_out = ?, total_hours = ?, status = ?, remarks = ?,
                approved_by = ?, approved_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        if (!$update) {
            throw new RuntimeException('Unable to apply the approved correction.');
        }

        $morningIn = $candidate['morning_time_in'] ?? null;
        $morningOut = $candidate['morning_time_out'] ?? null;
        $breakIn = $candidate['break_time_in'] ?? null;
        $breakOut = $candidate['break_time_out'] ?? null;
        $afternoonIn = $candidate['afternoon_time_in'] ?? null;
        $afternoonOut = $candidate['afternoon_time_out'] ?? null;
        $attendancePk = (int)$attendance['id'];
        $update->bind_param(
            'ssssssdssii',
            $morningIn,
            $morningOut,
            $breakIn,
            $breakOut,
            $afternoonIn,
            $afternoonOut,
            $totalHours,
            $status,
            $remarks,
            $reviewerId,
            $attendancePk
        );
        $update->execute();
        $update->close();

        attendance_workflow_sync_student_progress($conn, (int)($attendance['student_id'] ?? 0));

        return [
            'attendance_id' => $attendancePk,
            'student_id' => (int)($attendance['student_id'] ?? 0),
            'total_hours' => $totalHours,
            'status' => $status,
        ];
    }
}
