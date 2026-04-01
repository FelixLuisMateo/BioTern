<?php

if (!function_exists('section_schedule_columns')) {
    function section_schedule_columns(): array
    {
        return [
            'attendance_session' => "ALTER TABLE sections ADD COLUMN attendance_session VARCHAR(20) NOT NULL DEFAULT 'whole_day' AFTER description",
            'schedule_time_in' => "ALTER TABLE sections ADD COLUMN schedule_time_in TIME NULL AFTER attendance_session",
            'schedule_time_out' => "ALTER TABLE sections ADD COLUMN schedule_time_out TIME NULL AFTER schedule_time_in",
            'late_after_time' => "ALTER TABLE sections ADD COLUMN late_after_time TIME NULL AFTER schedule_time_out",
            'weekly_schedule_json' => "ALTER TABLE sections ADD COLUMN weekly_schedule_json LONGTEXT NULL AFTER late_after_time",
        ];
    }
}

if (!function_exists('section_schedule_ensure_columns')) {
    function section_schedule_ensure_columns(mysqli $conn): void
    {
        $existing = [];
        $res = $conn->query("SHOW COLUMNS FROM sections");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $existing[strtolower((string)($row['Field'] ?? ''))] = true;
            }
            $res->close();
        }

        foreach (section_schedule_columns() as $column => $sql) {
            if (!isset($existing[strtolower($column)])) {
                $conn->query($sql);
            }
        }
    }
}

if (!function_exists('section_schedule_normalize_session')) {
    function section_schedule_normalize_session(?string $value): string
    {
        $value = strtolower(trim((string)$value));
        $allowed = ['whole_day', 'morning_only', 'afternoon_only'];
        return in_array($value, $allowed, true) ? $value : 'whole_day';
    }
}

if (!function_exists('section_schedule_weekday_order')) {
    function section_schedule_weekday_order(): array
    {
        return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    }
}

if (!function_exists('section_schedule_weekday_label')) {
    function section_schedule_weekday_label(string $dayKey): string
    {
        return match ($dayKey) {
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            default => ucfirst($dayKey),
        };
    }
}

if (!function_exists('section_schedule_from_row')) {
    function section_schedule_from_row(array $row): array
    {
        $defaults = [
            'attendance_session' => section_schedule_normalize_session((string)($row['attendance_session'] ?? 'whole_day')),
            'schedule_time_in' => section_schedule_format_time_input((string)($row['schedule_time_in'] ?? '')),
            'schedule_time_out' => section_schedule_format_time_input((string)($row['schedule_time_out'] ?? '')),
            'late_after_time' => section_schedule_format_time_input((string)($row['late_after_time'] ?? '')),
        ];

        return [
            'attendance_session' => $defaults['attendance_session'],
            'schedule_time_in' => $defaults['schedule_time_in'],
            'schedule_time_out' => $defaults['schedule_time_out'],
            'late_after_time' => $defaults['late_after_time'],
            'weekly_schedule' => section_schedule_decode_weekly((string)($row['weekly_schedule_json'] ?? ''), $defaults),
        ];
    }
}

if (!function_exists('section_schedule_normalize_time_input')) {
    function section_schedule_normalize_time_input(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
}

if (!function_exists('section_schedule_format_time_input')) {
    function section_schedule_format_time_input(?string $value): string
    {
        $normalized = section_schedule_normalize_time_input($value);
        return $normalized !== null ? substr($normalized, 0, 5) : '';
    }
}

if (!function_exists('section_schedule_empty_day')) {
    function section_schedule_empty_day(array $defaults = []): array
    {
        return [
            'attendance_session' => section_schedule_normalize_session((string)($defaults['attendance_session'] ?? 'whole_day')),
            'schedule_time_in' => section_schedule_format_time_input((string)($defaults['schedule_time_in'] ?? '')),
            'schedule_time_out' => section_schedule_format_time_input((string)($defaults['schedule_time_out'] ?? '')),
            'late_after_time' => section_schedule_format_time_input((string)($defaults['late_after_time'] ?? '')),
        ];
    }
}

if (!function_exists('section_schedule_decode_weekly')) {
    function section_schedule_decode_weekly(?string $json, array $defaults = []): array
    {
        $weekly = [];
        $decoded = json_decode((string)$json, true);
        $decoded = is_array($decoded) ? $decoded : [];

        foreach (section_schedule_weekday_order() as $dayKey) {
            $rawDay = isset($decoded[$dayKey]) && is_array($decoded[$dayKey]) ? $decoded[$dayKey] : [];
            $weekly[$dayKey] = [
                'attendance_session' => section_schedule_normalize_session((string)($rawDay['attendance_session'] ?? ($defaults['attendance_session'] ?? 'whole_day'))),
                'schedule_time_in' => section_schedule_format_time_input((string)($rawDay['schedule_time_in'] ?? ($defaults['schedule_time_in'] ?? ''))),
                'schedule_time_out' => section_schedule_format_time_input((string)($rawDay['schedule_time_out'] ?? ($defaults['schedule_time_out'] ?? ''))),
                'late_after_time' => section_schedule_format_time_input((string)($rawDay['late_after_time'] ?? ($defaults['late_after_time'] ?? ''))),
            ];
        }

        return $weekly;
    }
}

if (!function_exists('section_schedule_normalize_weekly_input')) {
    function section_schedule_normalize_weekly_input($rawWeekly, array $defaults = []): array
    {
        $normalized = [];
        $rawWeekly = is_array($rawWeekly) ? $rawWeekly : [];

        foreach (section_schedule_weekday_order() as $dayKey) {
            $rawDay = isset($rawWeekly[$dayKey]) && is_array($rawWeekly[$dayKey]) ? $rawWeekly[$dayKey] : [];
            $normalized[$dayKey] = [
                'attendance_session' => section_schedule_normalize_session((string)($rawDay['attendance_session'] ?? ($defaults['attendance_session'] ?? 'whole_day'))),
                'schedule_time_in' => section_schedule_format_time_input((string)($rawDay['schedule_time_in'] ?? ($defaults['schedule_time_in'] ?? ''))),
                'schedule_time_out' => section_schedule_format_time_input((string)($rawDay['schedule_time_out'] ?? ($defaults['schedule_time_out'] ?? ''))),
                'late_after_time' => section_schedule_format_time_input((string)($rawDay['late_after_time'] ?? ($defaults['late_after_time'] ?? ''))),
            ];
        }

        return $normalized;
    }
}

if (!function_exists('section_schedule_encode_weekly')) {
    function section_schedule_encode_weekly(array $weekly): string
    {
        $payload = [];
        foreach (section_schedule_weekday_order() as $dayKey) {
            $day = isset($weekly[$dayKey]) && is_array($weekly[$dayKey]) ? $weekly[$dayKey] : [];
            $payload[$dayKey] = [
                'attendance_session' => section_schedule_normalize_session((string)($day['attendance_session'] ?? 'whole_day')),
                'schedule_time_in' => section_schedule_normalize_time_input((string)($day['schedule_time_in'] ?? '')),
                'schedule_time_out' => section_schedule_normalize_time_input((string)($day['schedule_time_out'] ?? '')),
                'late_after_time' => section_schedule_normalize_time_input((string)($day['late_after_time'] ?? '')),
            ];
        }

        return (string)json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('section_schedule_day_key_from_date')) {
    function section_schedule_day_key_from_date(?string $date): ?string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return null;
        }

        $lower = strtolower($date);
        if (in_array($lower, array_merge(section_schedule_weekday_order(), ['sunday']), true)) {
            return $lower;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }

        return strtolower((string)date('l', $timestamp));
    }
}

if (!function_exists('section_schedule_for_date')) {
    function section_schedule_for_date(array $schedule, ?string $date = null): array
    {
        $resolved = [
            'attendance_session' => section_schedule_normalize_session((string)($schedule['attendance_session'] ?? 'whole_day')),
            'schedule_time_in' => section_schedule_format_time_input((string)($schedule['schedule_time_in'] ?? '')),
            'schedule_time_out' => section_schedule_format_time_input((string)($schedule['schedule_time_out'] ?? '')),
            'late_after_time' => section_schedule_format_time_input((string)($schedule['late_after_time'] ?? '')),
        ];

        $dayKey = section_schedule_day_key_from_date($date);
        $weekly = isset($schedule['weekly_schedule']) && is_array($schedule['weekly_schedule']) ? $schedule['weekly_schedule'] : [];
        if ($dayKey !== null && isset($weekly[$dayKey]) && is_array($weekly[$dayKey])) {
            $day = $weekly[$dayKey];
            $resolved['attendance_session'] = section_schedule_normalize_session((string)($day['attendance_session'] ?? $resolved['attendance_session']));
            foreach (['schedule_time_in', 'schedule_time_out', 'late_after_time'] as $field) {
                $dayValue = section_schedule_format_time_input((string)($day[$field] ?? ''));
                if ($dayValue !== '') {
                    $resolved[$field] = $dayValue;
                }
            }
        }

        return $resolved;
    }
}

if (!function_exists('section_schedule_has_configured_day')) {
    function section_schedule_has_configured_day(array $schedule, ?string $date = null): bool
    {
        $resolved = section_schedule_for_date($schedule, $date);
        if (trim((string)($resolved['schedule_time_in'] ?? '')) !== '') {
            return true;
        }
        if (trim((string)($resolved['schedule_time_out'] ?? '')) !== '') {
            return true;
        }
        if (trim((string)($resolved['late_after_time'] ?? '')) !== '') {
            return true;
        }

        return section_schedule_normalize_session((string)($resolved['attendance_session'] ?? 'whole_day')) !== 'whole_day';
    }
}

if (!function_exists('section_schedule_effective_day')) {
    function section_schedule_effective_day(array $schedule, ?string $date = null, array $fallback = []): array
    {
        $resolved = section_schedule_for_date($schedule, $date);
        $hasSectionWindow = section_schedule_has_configured_day($schedule, $date);

        $fallbackIn = section_schedule_format_time_input((string)($fallback['schedule_time_in'] ?? ''));
        $fallbackOut = section_schedule_format_time_input((string)($fallback['schedule_time_out'] ?? ''));
        $fallbackLate = section_schedule_format_time_input((string)($fallback['late_after_time'] ?? ''));

        if ($resolved['schedule_time_in'] === '' && $fallbackIn !== '') {
            $resolved['schedule_time_in'] = $fallbackIn;
        }
        if ($resolved['schedule_time_out'] === '' && $fallbackOut !== '') {
            $resolved['schedule_time_out'] = $fallbackOut;
        }
        if ($resolved['late_after_time'] === '') {
            if ($fallbackLate !== '') {
                $resolved['late_after_time'] = $fallbackLate;
            } elseif ($resolved['schedule_time_in'] !== '') {
                $resolved['late_after_time'] = $resolved['schedule_time_in'];
            }
        }

        if ($hasSectionWindow) {
            $resolved['window_source'] = 'section';
        } elseif ($resolved['schedule_time_in'] !== '' || $resolved['schedule_time_out'] !== '') {
            $resolved['window_source'] = 'school';
        } else {
            $resolved['window_source'] = 'none';
        }

        return $resolved;
    }
}

if (!function_exists('section_schedule_allows_punch_time')) {
    function section_schedule_allows_punch_time(array $schedule, ?string $date, string $time): bool
    {
        $resolved = section_schedule_for_date($schedule, $date);
        $time = section_schedule_normalize_time_input($time);
        $scheduleIn = section_schedule_normalize_time_input((string)($resolved['schedule_time_in'] ?? ''));
        $scheduleOut = section_schedule_normalize_time_input((string)($resolved['schedule_time_out'] ?? ''));

        if ($time === null || $scheduleIn === null || $scheduleOut === null) {
            return true;
        }

        if ($scheduleIn <= $scheduleOut) {
            return $time >= $scheduleIn && $time <= $scheduleOut;
        }

        return $time >= $scheduleIn || $time <= $scheduleOut;
    }
}

if (!function_exists('section_schedule_summary_lines')) {
    function section_schedule_summary_lines(array $schedule): array
    {
        $lines = [];
        foreach (section_schedule_weekday_order() as $dayKey) {
            $resolved = section_schedule_for_date($schedule, $dayKey);
            $sessionLabel = match (section_schedule_inferred_session($resolved)) {
                'morning_only' => 'Morning',
                'afternoon_only' => 'Afternoon',
                default => 'Whole day',
            };

            $parts = [section_schedule_weekday_label($dayKey), $sessionLabel];
            if ($resolved['schedule_time_in'] !== '') {
                $parts[] = 'In ' . $resolved['schedule_time_in'];
            }
            if ($resolved['late_after_time'] !== '') {
                $parts[] = 'Late ' . $resolved['late_after_time'];
            }
            if ($resolved['schedule_time_out'] !== '') {
                $parts[] = 'Out ' . $resolved['schedule_time_out'];
            }
            $lines[] = implode(' | ', $parts);
        }

        if ($lines === []) {
            $lines[] = 'No weekly schedule';
        }

        return $lines;
    }
}

if (!function_exists('section_schedule_inferred_session')) {
    function section_schedule_inferred_session(array $schedule): string
    {
        $session = section_schedule_normalize_session((string)($schedule['attendance_session'] ?? 'whole_day'));
        if ($session !== 'whole_day') {
            return $session;
        }

        $scheduledIn = section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? ''));
        $scheduledOut = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? ''));
        if ($scheduledIn !== null && $scheduledOut !== null) {
            if (strcmp($scheduledOut, '12:00:00') <= 0) {
                return 'morning_only';
            }
            if (strcmp($scheduledIn, '12:00:00') >= 0) {
                return 'afternoon_only';
            }
        }

        return $session;
    }
}

if (!function_exists('section_schedule_prefers_afternoon_entry')) {
    function section_schedule_prefers_afternoon_entry(array $schedule): bool
    {
        $session = section_schedule_inferred_session($schedule);
        if ($session === 'afternoon_only') {
            return true;
        }

        $scheduledIn = section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? ''));
        return $scheduledIn !== null && strcmp($scheduledIn, '12:00:00') >= 0;
    }
}

if (!function_exists('section_schedule_first_in_column')) {
    function section_schedule_first_in_column(array $schedule): string
    {
        return section_schedule_prefers_afternoon_entry($schedule)
            ? 'afternoon_time_in'
            : 'morning_time_in';
    }
}

if (!function_exists('section_schedule_entry_time')) {
    function section_schedule_entry_time(array $attendanceRow, array $schedule): ?string
    {
        $schedule = section_schedule_for_date($schedule, (string)($attendanceRow['attendance_date'] ?? ''));
        $primaryColumn = section_schedule_first_in_column($schedule);
        $primaryValue = trim((string)($attendanceRow[$primaryColumn] ?? ''));
        if ($primaryValue !== '' && $primaryValue !== '00:00:00') {
            return $primaryValue;
        }

        foreach (['morning_time_in', 'afternoon_time_in'] as $fallbackColumn) {
            $value = trim((string)($attendanceRow[$fallbackColumn] ?? ''));
            if ($value !== '' && $value !== '00:00:00') {
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('section_schedule_status')) {
    function section_schedule_status(array $attendanceRow, array $schedule): string
    {
        $schedule = section_schedule_for_date($schedule, (string)($attendanceRow['attendance_date'] ?? ''));
        $entryTime = section_schedule_entry_time($attendanceRow, $schedule);
        if ($entryTime === null) {
            return 'absent';
        }

        $entryTs = strtotime($entryTime);
        if ($entryTs === false) {
            return 'absent';
        }

        $lateAfter = trim((string)($schedule['late_after_time'] ?? ''));
        if ($lateAfter === '') {
            $lateAfter = trim((string)($schedule['schedule_time_in'] ?? ''));
        }
        if ($lateAfter === '') {
            $lateAfter = '08:00:00';
        }

        $lateTs = strtotime($lateAfter);
        if ($lateTs === false) {
            $lateTs = strtotime('08:00:00');
        }

        return $entryTs <= $lateTs ? 'present' : 'late';
    }
}
