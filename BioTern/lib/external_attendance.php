<?php

require_once __DIR__ . '/section_schedule.php';
require_once __DIR__ . '/attendance_bonus_rules.php';

if (!function_exists('external_attendance_ensure_schema')) {
    function external_attendance_ensure_schema(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS external_attendance (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id INT UNSIGNED NOT NULL,
                attendance_date DATE NOT NULL,
                morning_time_in TIME NULL,
                morning_time_out TIME NULL,
                break_time_in TIME NULL,
                break_time_out TIME NULL,
                afternoon_time_in TIME NULL,
                afternoon_time_out TIME NULL,
                total_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
                multiplier_reason VARCHAR(255) NULL,
                photo_path VARCHAR(255) NULL,
                notes VARCHAR(500) NULL,
                source VARCHAR(20) NOT NULL DEFAULT 'manual',
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_by_user_id INT UNSIGNED NULL,
                reviewed_by INT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_external_attendance_student_date (student_id, attendance_date),
                KEY idx_external_attendance_status (status),
                KEY idx_external_attendance_student_date (student_id, attendance_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM external_attendance");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $columns[strtolower((string)($row['Field'] ?? ''))] = true;
            }
            $res->close();
        }

        $required = [
            'morning_time_in' => "ALTER TABLE external_attendance ADD COLUMN morning_time_in TIME NULL AFTER attendance_date",
            'morning_time_out' => "ALTER TABLE external_attendance ADD COLUMN morning_time_out TIME NULL AFTER morning_time_in",
            'break_time_in' => "ALTER TABLE external_attendance ADD COLUMN break_time_in TIME NULL AFTER morning_time_out",
            'break_time_out' => "ALTER TABLE external_attendance ADD COLUMN break_time_out TIME NULL AFTER break_time_in",
            'afternoon_time_in' => "ALTER TABLE external_attendance ADD COLUMN afternoon_time_in TIME NULL AFTER break_time_out",
            'afternoon_time_out' => "ALTER TABLE external_attendance ADD COLUMN afternoon_time_out TIME NULL AFTER afternoon_time_in",
            'total_hours' => "ALTER TABLE external_attendance ADD COLUMN total_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER afternoon_time_out",
            'multiplier' => "ALTER TABLE external_attendance ADD COLUMN multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00 AFTER total_hours",
            'multiplier_reason' => "ALTER TABLE external_attendance ADD COLUMN multiplier_reason VARCHAR(255) NULL AFTER multiplier",
            'source' => "ALTER TABLE external_attendance ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER notes",
            'status' => "ALTER TABLE external_attendance ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER source",
            'created_by_user_id' => "ALTER TABLE external_attendance ADD COLUMN created_by_user_id INT UNSIGNED NULL AFTER status",
            'reviewed_by' => "ALTER TABLE external_attendance ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER created_by_user_id",
            'reviewed_at' => "ALTER TABLE external_attendance ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by",
            'updated_at' => "ALTER TABLE external_attendance ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];

        foreach ($required as $column => $sql) {
            if (!isset($columns[$column])) {
                $conn->query($sql);
            }
        }
    }
}

if (!function_exists('external_attendance_normalize_time')) {
    function external_attendance_normalize_time(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            $hour = max(0, min(23, (int)$matches[1]));
            $minute = max(0, min(59, (int)$matches[2]));
            return sprintf('%02d:%02d:00', $hour, $minute);
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
}

if (!function_exists('external_attendance_parse_time')) {
    function external_attendance_parse_time(?string $value): ?int
    {
        $normalized = external_attendance_normalize_time($value);
        if ($normalized === null) {
            return null;
        }

        $timestamp = strtotime('1970-01-01 ' . $normalized);
        return $timestamp === false ? null : $timestamp;
    }
}

if (!function_exists('external_attendance_collect_punches')) {
    function external_attendance_collect_punches(array $record): array
    {
        $values = [];
        foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
            $value = external_attendance_normalize_time((string)($record[$column] ?? ''));
            if ($value !== null) {
                $values[] = $value;
            }
        }

        usort($values, static function (string $left, string $right): int {
            return strcmp($left, $right);
        });

        return $values;
    }
}

if (!function_exists('external_attendance_validate_record')) {
    function external_attendance_validate_record(array $record): array
    {
        $pairs = [
            ['morning_time_in', 'morning_time_out', 'Morning'],
            ['break_time_in', 'break_time_out', 'Break'],
            ['afternoon_time_in', 'afternoon_time_out', 'Afternoon'],
        ];

        foreach ($pairs as [$startKey, $endKey, $label]) {
            $start = external_attendance_parse_time($record[$startKey] ?? null);
            $end = external_attendance_parse_time($record[$endKey] ?? null);
            if ($start !== null && $end !== null && $end < $start) {
                return ['ok' => false, 'message' => $label . ' out cannot be earlier than in.'];
            }
        }

        $timeline = [
            $record['morning_time_in'] ?? null,
            $record['morning_time_out'] ?? null,
            $record['break_time_in'] ?? null,
            $record['break_time_out'] ?? null,
            $record['afternoon_time_in'] ?? null,
            $record['afternoon_time_out'] ?? null,
        ];

        $previous = null;
        foreach ($timeline as $time) {
            $current = external_attendance_parse_time($time);
            if ($current === null) {
                continue;
            }
            if ($previous !== null && $current < $previous) {
                return ['ok' => false, 'message' => 'Time entries overlap or are out of order.'];
            }
            $previous = $current;
        }

        return ['ok' => true, 'message' => 'OK'];
    }
}

if (!function_exists('external_attendance_expected_previous')) {
    function external_attendance_expected_previous(string $clockType): ?string
    {
        $order = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
        $index = array_search($clockType, $order, true);
        if ($index === false || $index === 0) {
            return null;
        }

        return $order[$index - 1];
    }
}

if (!function_exists('external_attendance_validate_transition')) {
    function external_attendance_validate_transition(array $record, string $clockType, string $clockTime): array
    {
        $targetColumn = attendance_action_to_column($clockType);
        if ($targetColumn === null || $targetColumn === 'break_time_in' || $targetColumn === 'break_time_out') {
            return ['ok' => false, 'message' => 'Invalid external DTR punch.'];
        }

        if (!empty($record[$targetColumn])) {
            return ['ok' => false, 'message' => ucfirst(str_replace('_', ' ', $clockType)) . ' already recorded.'];
        }

        $order = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
        $currentIndex = array_search($clockType, $order, true);
        if ($currentIndex === false) {
            return ['ok' => false, 'message' => 'Invalid external DTR punch.'];
        }

        for ($i = $currentIndex + 1; $i < count($order); $i++) {
            $laterColumn = attendance_action_to_column($order[$i]);
            if ($laterColumn !== null && !empty($record[$laterColumn])) {
                return ['ok' => false, 'message' => 'Cannot record ' . str_replace('_', ' ', $clockType) . ' after ' . str_replace('_', ' ', $order[$i]) . ' already exists.'];
            }
        }

        $previousAction = external_attendance_expected_previous($clockType);
        if ($previousAction !== null) {
            $previousColumn = attendance_action_to_column($previousAction);
            if ($previousColumn !== null && empty($record[$previousColumn])) {
                return ['ok' => false, 'message' => 'Please record ' . str_replace('_', ' ', $previousAction) . ' before ' . str_replace('_', ' ', $clockType) . '.'];
            }
        }

        $newMinutes = attendance_time_to_minutes($clockTime);
        if ($newMinutes === null) {
            return ['ok' => false, 'message' => 'Invalid clock time format.'];
        }

        $lastRecordedMinutes = null;
        for ($i = 0; $i < count($order); $i++) {
            $column = attendance_action_to_column($order[$i]);
            if ($column !== null && !empty($record[$column])) {
                $lastRecordedMinutes = attendance_time_to_minutes((string)$record[$column]);
            }
        }

        if ($lastRecordedMinutes !== null && $newMinutes < $lastRecordedMinutes) {
            return ['ok' => false, 'message' => 'New punch time cannot be earlier than the latest recorded punch.'];
        }

        return ['ok' => true, 'message' => 'OK'];
    }
}

if (!function_exists('external_attendance_upload_dir')) {
    function external_attendance_upload_dir(): string
    {
        return dirname(__DIR__) . '/uploads/external_attendance';
    }
}

if (!function_exists('external_attendance_cloudinary_config')) {
    function external_attendance_cloudinary_config(): array
    {
        $cloudName = trim((string)getenv('CLOUDINARY_CLOUD_NAME'));
        $apiKey = trim((string)getenv('CLOUDINARY_API_KEY'));
        $apiSecret = trim((string)getenv('CLOUDINARY_API_SECRET'));
        $uploadPreset = trim((string)getenv('CLOUDINARY_UPLOAD_PRESET'));
        $folder = trim((string)getenv('CLOUDINARY_EXTERNAL_ATTENDANCE_FOLDER'));
        if ($folder === '') {
            $folder = 'biotern/external_attendance';
        }

        return [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'upload_preset' => $uploadPreset,
            'folder' => $folder,
        ];
    }
}

if (!function_exists('external_attendance_cloudinary_enabled')) {
    function external_attendance_cloudinary_enabled(): bool
    {
        $config = external_attendance_cloudinary_config();
        if ($config['cloud_name'] === '') {
            return false;
        }

        return ($config['upload_preset'] !== '') || ($config['api_key'] !== '' && $config['api_secret'] !== '');
    }
}

if (!function_exists('external_attendance_cloudinary_signature')) {
    function external_attendance_cloudinary_signature(array $params, string $apiSecret): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null || $key === 'file' || $key === 'api_key' || $key === 'resource_type') {
                continue;
            }
            $pairs[] = $key . '=' . $value;
        }

        return sha1(implode('&', $pairs) . $apiSecret);
    }
}

if (!function_exists('external_attendance_cloudinary_upload')) {
    function external_attendance_cloudinary_upload(string $tmpPath, string $mime, int $studentId, string $dateValue): array
    {
        $config = external_attendance_cloudinary_config();
        if ($config['cloud_name'] === '') {
            return ['ok' => false, 'message' => 'Cloudinary is not configured.', 'path' => ''];
        }

        $safeDate = preg_replace('/[^0-9]/', '', $dateValue) ?: date('Ymd');
        $publicId = sprintf('%s/external_%d_%s_%s', trim($config['folder'], '/'), $studentId, $safeDate, bin2hex(random_bytes(4)));
        $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($config['cloud_name']) . '/image/upload';
        $timestamp = time();

        $fields = [
            'folder' => $config['folder'],
            'public_id' => $publicId,
            'timestamp' => (string)$timestamp,
            'resource_type' => 'image',
        ];

        if ($config['upload_preset'] !== '') {
            $fields['upload_preset'] = $config['upload_preset'];
            unset($fields['timestamp']);
        } else {
            $fields['api_key'] = $config['api_key'];
            $fields['signature'] = external_attendance_cloudinary_signature([
                'folder' => $config['folder'],
                'public_id' => $publicId,
                'timestamp' => (string)$timestamp,
            ], $config['api_secret']);
        }

        if (function_exists('curl_init')) {
            $postFields = $fields;
            $postFields['file'] = new CURLFile($tmpPath, $mime, basename($tmpPath));

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!is_string($response) || $response === '') {
                return ['ok' => false, 'message' => $curlError !== '' ? ('Cloud upload failed: ' . $curlError) : 'Cloud upload failed.', 'path' => ''];
            }

            $decoded = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !empty($decoded['secure_url'])) {
                return ['ok' => true, 'message' => 'OK', 'path' => (string)$decoded['secure_url']];
            }

            $errorMessage = is_array($decoded) && isset($decoded['error']['message']) ? (string)$decoded['error']['message'] : 'Cloud upload failed.';
            return ['ok' => false, 'message' => $errorMessage, 'path' => ''];
        }

        return ['ok' => false, 'message' => 'cURL is required for Cloudinary uploads.', 'path' => ''];
    }
}

if (!function_exists('external_attendance_store_photo')) {
    function external_attendance_store_photo(array $file, int $studentId, string $dateValue): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Photo upload failed.', 'path' => ''];
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        $mime = $tmpPath !== '' && function_exists('mime_content_type') ? (string)@mime_content_type($tmpPath) : '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'message' => 'Photo must be JPG, PNG, or WEBP.', 'path' => ''];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 6 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Photo must be 6MB or smaller.', 'path' => ''];
        }

        if (external_attendance_cloudinary_enabled()) {
            $cloudUpload = external_attendance_cloudinary_upload($tmpPath, $mime, $studentId, $dateValue);
            if (!empty($cloudUpload['ok'])) {
                return $cloudUpload;
            }
        }

        $dir = external_attendance_upload_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'Could not create upload directory.', 'path' => ''];
        }

        $safeDate = preg_replace('/[^0-9]/', '', $dateValue) ?: date('Ymd');
        $fileName = sprintf('external_%d_%s_%s.%s', $studentId, $safeDate, bin2hex(random_bytes(4)), $allowed[$mime]);
        $destination = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        if (!@move_uploaded_file($tmpPath, $destination)) {
            return ['ok' => false, 'message' => 'Could not save the uploaded photo.', 'path' => ''];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'path' => 'uploads/external_attendance/' . $fileName,
        ];
    }
}

if (!function_exists('external_attendance_student_context_select_sql')) {
    function external_attendance_student_context_select_sql(): string
    {
        return "
            SELECT
                s.id,
                s.user_id,
                s.student_id,
                s.first_name,
                s.last_name,
                s.department_id,
                s.section_id,
                s.assignment_track,
                s.external_total_hours,
                s.external_total_hours_remaining,
                s.internal_total_hours,
                sec.name AS section_name,
                sec.code AS section_code,
                sec.attendance_session,
                sec.schedule_time_in,
                sec.schedule_time_out,
                sec.late_after_time,
                sec.weekly_schedule_json,
                d.name AS department_name,
                c.name AS course_name
            FROM students s
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN departments d ON d.id = s.department_id
            LEFT JOIN courses c ON c.id = s.course_id
        ";
    }
}

if (!function_exists('external_attendance_student_context_by_student_id')) {
    function external_attendance_student_context_by_student_id(mysqli $conn, int $studentId): ?array
    {
        section_schedule_ensure_columns($conn);
        external_attendance_ensure_schema($conn);
        attendance_bonus_rules_ensure_schema($conn);

        if ($studentId <= 0) {
            return null;
        }

        $stmt = $conn->prepare(external_attendance_student_context_select_sql() . " WHERE s.id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('external_attendance_student_context')) {
    function external_attendance_student_context(mysqli $conn, int $userId): ?array
    {
        section_schedule_ensure_columns($conn);
        external_attendance_ensure_schema($conn);
        attendance_bonus_rules_ensure_schema($conn);

        $sql = external_attendance_student_context_select_sql();

        $stmt = $conn->prepare($sql . " WHERE s.user_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        if ($row) {
            return $row;
        }

        // Fallback 1: some deployments map students.id directly to session user_id.
        $idRow = external_attendance_student_context_by_student_id($conn, $userId);
        if ($idRow) {
            return $idRow;
        }

        // Fallback 2: match by linked user profile identity (student number/email/name).
        $userStmt = $conn->prepare("SELECT username, email, name FROM users WHERE id = ? LIMIT 1");
        if (!$userStmt) {
            return null;
        }
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userRow = $userStmt->get_result()->fetch_assoc() ?: null;
        $userStmt->close();
        if (!$userRow) {
            return null;
        }

        $username = trim((string)($userRow['username'] ?? ''));
        $email = trim((string)($userRow['email'] ?? ''));
        $name = trim((string)($userRow['name'] ?? ''));
        if ($username === '' && $email === '' && $name === '') {
            return null;
        }

        $fallbackStmt = $conn->prepare(
            $sql . "
            WHERE ((? <> '' AND LOWER(COALESCE(s.student_id, '')) = LOWER(?))
                OR (? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
                OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)))
            ORDER BY
                CASE
                    WHEN (? <> '' AND LOWER(COALESCE(s.student_id, '')) = LOWER(?)) THEN 0
                    WHEN (? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?)) THEN 1
                    WHEN (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)) THEN 2
                    ELSE 3
                END,
                s.id DESC
            LIMIT 1"
        );
        if (!$fallbackStmt) {
            return null;
        }
        $fallbackStmt->bind_param(
            'ssssssssssss',
            $username,
            $username,
            $email,
            $email,
            $name,
            $name,
            $username,
            $username,
            $email,
            $email,
            $name,
            $name
        );
        $fallbackStmt->execute();
        $fallbackRow = $fallbackStmt->get_result()->fetch_assoc() ?: null;
        $fallbackStmt->close();

        return $fallbackRow ?: null;
    }
}

if (!function_exists('external_attendance_schedule_bounds')) {
    function external_attendance_schedule_bounds(array $student, string $dateValue): array
    {
        $schedule = section_schedule_effective_day(
            section_schedule_from_row($student),
            $dateValue,
            [
                'schedule_time_in' => '08:00',
                'schedule_time_out' => '17:00',
                'late_after_time' => '08:00',
            ]
        );

        return [
            'schedule' => $schedule,
            'official_start' => section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00',
            'official_end' => section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? '')) ?: '17:00:00',
            'late_after' => section_schedule_normalize_time_input((string)($schedule['late_after_time'] ?? ''))
                ?: (section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00'),
        ];
    }
}

if (!function_exists('external_attendance_clamped_duration_seconds')) {
    function external_attendance_clamped_duration_seconds(?int $startTs, ?int $endTs, string $windowStart, string $windowEnd): int
    {
        if ($startTs === null || $endTs === null || $endTs <= $startTs) {
            return 0;
        }

        $windowStartTs = strtotime('1970-01-01 ' . $windowStart);
        $windowEndTs = strtotime('1970-01-01 ' . $windowEnd);
        if ($windowStartTs === false || $windowEndTs === false) {
            return max(0, $endTs - $startTs);
        }

        $clampedStart = max($startTs, $windowStartTs);
        $clampedEnd = min($endTs, $windowEndTs);
        return max(0, $clampedEnd - $clampedStart);
    }
}

if (!function_exists('external_attendance_credited_seconds')) {
    function external_attendance_credited_seconds(array $record, array $bounds): int
    {
        $officialStart = (string)($bounds['official_start'] ?? '08:00:00');
        $officialEnd = (string)($bounds['official_end'] ?? '17:00:00');
        $totalSeconds = 0;

        foreach ([['morning_time_in', 'morning_time_out'], ['afternoon_time_in', 'afternoon_time_out']] as $pair) {
            $startTs = external_attendance_parse_time($record[$pair[0]] ?? null);
            $endTs = external_attendance_parse_time($record[$pair[1]] ?? null);
            $totalSeconds += external_attendance_clamped_duration_seconds($startTs, $endTs, $officialStart, $officialEnd);
        }

        $breakStart = external_attendance_parse_time($record['break_time_in'] ?? null);
        $breakEnd = external_attendance_parse_time($record['break_time_out'] ?? null);
        $totalSeconds -= external_attendance_clamped_duration_seconds($breakStart, $breakEnd, $officialStart, $officialEnd);

        return max(0, $totalSeconds);
    }
}

if (!function_exists('external_attendance_calendar_multiplier')) {
    function external_attendance_calendar_multiplier(mysqli $conn, string $dateValue, array $record, array $bounds): array
    {
        $sql = "
            SELECT id, title, attendance_multiplier, apply_when_not_late, late_grace_minutes, applies_to_weekday
            FROM calendar_events
            WHERE deleted_at IS NULL
              AND attendance_multiplier IS NOT NULL
              AND attendance_multiplier > 0
              AND DATE(start_at) <= ?
              AND DATE(end_at) >= ?
            ORDER BY attendance_multiplier DESC, id DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['multiplier' => 1.0, 'reason' => ''];
        }

        $firstPunches = external_attendance_collect_punches($record);
        $firstPunch = $firstPunches[0] ?? '';
        $weekdayKey = attendance_bonus_rules_weekday_key($dateValue);
        $lateAfterTs = strtotime('1970-01-01 ' . (string)($bounds['late_after'] ?? '08:00:00'));

        $stmt->bind_param('ss', $dateValue, $dateValue);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $eventWeekday = strtolower(trim((string)($row['applies_to_weekday'] ?? '')));
            if ($eventWeekday !== '' && $eventWeekday !== $weekdayKey) {
                continue;
            }

            if ((int)($row['apply_when_not_late'] ?? 0) === 1) {
                if ($firstPunch === '' || $lateAfterTs === false) {
                    continue;
                }

                $firstPunchTs = strtotime('1970-01-01 ' . $firstPunch);
                if ($firstPunchTs === false) {
                    continue;
                }

                $graceMinutes = max(0, (int)($row['late_grace_minutes'] ?? 0));
                if ($firstPunchTs > ($lateAfterTs + ($graceMinutes * 60))) {
                    continue;
                }
            }

            return [
                'multiplier' => max(1.0, (float)($row['attendance_multiplier'] ?? 1)),
                'reason' => 'Calendar event: ' . trim((string)($row['title'] ?? 'Attendance bonus')),
            ];
        }
        $stmt->close();

        return ['multiplier' => 1.0, 'reason' => ''];
    }
}

if (!function_exists('external_attendance_multiplier_context')) {
    function external_attendance_multiplier_context(mysqli $conn, array $student, string $dateValue, array $record, array $bounds): array
    {
        return external_attendance_calendar_multiplier($conn, $dateValue, $record, $bounds);
    }
}

if (!function_exists('external_attendance_calculate_totals')) {
    function external_attendance_calculate_totals(mysqli $conn, array $student, string $dateValue, array $record): array
    {
        $bounds = external_attendance_schedule_bounds($student, $dateValue);
        $baseHours = round(external_attendance_credited_seconds($record, $bounds) / 3600, 2);
        $context = external_attendance_multiplier_context($conn, $student, $dateValue, $record, $bounds);
        $multiplier = max(1.0, (float)($context['multiplier'] ?? 1));

        return [
            'base_hours' => $baseHours,
            'multiplier' => $multiplier,
            'multiplier_reason' => trim((string)($context['reason'] ?? '')),
            'total_hours' => $baseHours > 0 ? round($baseHours * $multiplier, 2) : 0.0,
        ];
    }
}

if (!function_exists('external_attendance_sync_student_hours')) {
    function external_attendance_sync_student_hours(mysqli $conn, int $studentId): void
    {
        external_attendance_ensure_schema($conn);
        $sumStmt = $conn->prepare("
            SELECT COALESCE(SUM(total_hours), 0) AS rendered
            FROM external_attendance
            WHERE student_id = ? AND LOWER(COALESCE(status, 'pending')) = 'approved'
        ");
        if (!$sumStmt) {
            return;
        }
        $sumStmt->bind_param('i', $studentId);
        $sumStmt->execute();
        $row = $sumStmt->get_result()->fetch_assoc() ?: ['rendered' => 0];
        $sumStmt->close();

        $rendered = (float)($row['rendered'] ?? 0);
        $studentStmt = $conn->prepare("
            SELECT external_total_hours
            FROM students
            WHERE id = ?
            LIMIT 1
        ");
        if (!$studentStmt) {
            return;
        }
        $studentStmt->bind_param('i', $studentId);
        $studentStmt->execute();
        $student = $studentStmt->get_result()->fetch_assoc() ?: null;
        $studentStmt->close();
        if (!$student) {
            return;
        }

        $remaining = max(0, (int)($student['external_total_hours'] ?? 0) - (int)floor($rendered));
        $updateStmt = $conn->prepare("
            UPDATE students
            SET external_total_hours_remaining = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$updateStmt) {
            return;
        }
        $updateStmt->bind_param('ii', $remaining, $studentId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

if (!function_exists('external_attendance_student_record')) {
    function external_attendance_student_record(mysqli $conn, int $studentId, string $dateValue): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM external_attendance WHERE student_id = ? AND attendance_date = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $studentId, $dateValue);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('external_attendance_upsert_day')) {
    function external_attendance_upsert_day(
        mysqli $conn,
        array $student,
        string $dateValue,
        array $payload,
        ?string $photoPath,
        string $notes,
        int $createdByUserId,
        bool $preserveExisting = false,
        string $source = 'manual'
    ): array {
        $existing = external_attendance_student_record($conn, (int)$student['id'], $dateValue);
        $record = [
            'morning_time_in' => $existing['morning_time_in'] ?? null,
            'morning_time_out' => $existing['morning_time_out'] ?? null,
            'break_time_in' => $existing['break_time_in'] ?? null,
            'break_time_out' => $existing['break_time_out'] ?? null,
            'afternoon_time_in' => $existing['afternoon_time_in'] ?? null,
            'afternoon_time_out' => $existing['afternoon_time_out'] ?? null,
        ];

        foreach ($payload as $key => $value) {
            if (!array_key_exists($key, $record)) {
                continue;
            }
            if ($preserveExisting && $record[$key] !== null && $record[$key] !== '') {
                continue;
            }
            $record[$key] = $value;
        }

        $validation = external_attendance_validate_record($record);
        if (!($validation['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)$validation['message']];
        }

        $totals = external_attendance_calculate_totals($conn, $student, $dateValue, $record);
        $photoFinal = $photoPath ?: (string)($existing['photo_path'] ?? '');
        $notesFinal = trim($notes);
        $sourceFinal = trim($source) !== '' ? trim($source) : 'manual';
        if ($notesFinal === '') {
            $notesFinal = (string)($existing['notes'] ?? '');
        }

        if ($existing) {
            $stmt = $conn->prepare("
                UPDATE external_attendance
                SET morning_time_in = ?, morning_time_out = ?, break_time_in = ?, break_time_out = ?,
                    afternoon_time_in = ?, afternoon_time_out = ?, total_hours = ?, multiplier = ?,
                    multiplier_reason = ?, photo_path = ?, notes = ?, source = ?,
                    status = CASE WHEN status = 'approved' THEN 'approved' ELSE 'pending' END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not update external attendance.'];
            }
            $stmt->bind_param(
                'ssssssddssssi',
                $record['morning_time_in'],
                $record['morning_time_out'],
                $record['break_time_in'],
                $record['break_time_out'],
                $record['afternoon_time_in'],
                $record['afternoon_time_out'],
                $totals['total_hours'],
                $totals['multiplier'],
                $totals['multiplier_reason'],
                $photoFinal,
                $notesFinal,
                $sourceFinal,
                $existing['id']
            );
            $ok = $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO external_attendance (
                    student_id, attendance_date, morning_time_in, morning_time_out, break_time_in, break_time_out,
                    afternoon_time_in, afternoon_time_out, total_hours, multiplier, multiplier_reason, photo_path, notes,
                    source, status, created_by_user_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
            ");
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not save external attendance.'];
            }
            $stmt->bind_param(
                'isssssssddssssi',
                $student['id'],
                $dateValue,
                $record['morning_time_in'],
                $record['morning_time_out'],
                $record['break_time_in'],
                $record['break_time_out'],
                $record['afternoon_time_in'],
                $record['afternoon_time_out'],
                $totals['total_hours'],
                $totals['multiplier'],
                $totals['multiplier_reason'],
                $photoFinal,
                $notesFinal,
                $sourceFinal,
                $createdByUserId
            );
            $ok = $stmt->execute();
            $stmt->close();
        }

        if (!$ok) {
            return ['ok' => false, 'message' => 'Database save failed for external attendance.'];
        }

        external_attendance_sync_student_hours($conn, (int)$student['id']);
        return [
            'ok' => true,
            'message' => 'External attendance saved.',
            'total_hours' => (float)$totals['total_hours'],
            'multiplier' => (float)$totals['multiplier'],
            'reason' => (string)$totals['multiplier_reason'],
        ];
    }
}
