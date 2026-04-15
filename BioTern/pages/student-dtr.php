<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentDtrFlash = $_SESSION['student_dtr_flash'] ?? null;
if (isset($_SESSION['student_dtr_flash'])) {
    unset($_SESSION['student_dtr_flash']);
}

function student_dtr_ensure_runtime_dir(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    $parent = dirname($path);
    if (!is_dir($parent) || !is_writable($parent)) {
        return false;
    }

    return @mkdir($path, 0755, true) || is_dir($path);
}

function student_dtr_manual_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/manual_dtr';
}

function student_dtr_manual_upload_web_path(string $relativePath): string
{
    return '../uploads/manual_dtr/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function student_dtr_calculate_hours(array $attendance): float
{
    $segments = [
        ['morning_time_in', 'morning_time_out'],
        ['break_time_in', 'break_time_out'],
        ['afternoon_time_in', 'afternoon_time_out'],
    ];

    $totalSeconds = 0;

    foreach ($segments as [$startKey, $endKey]) {
        $start = trim((string)($attendance[$startKey] ?? ''));
        $end = trim((string)($attendance[$endKey] ?? ''));

        if ($start === '' || $end === '') {
            continue;
        }

        $startTs = strtotime('1970-01-01 ' . $start);
        $endTs = strtotime('1970-01-01 ' . $end);
        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            continue;
        }

        $totalSeconds += ($endTs - $startTs);
    }

    return round($totalSeconds / 3600, 2);
}

function student_dtr_manual_metrics(array $attendance): array
{
    $toSeconds = static function (?string $time): ?int {
        $value = trim((string)$time);
        if ($value === '') {
            return null;
        }

        $ts = strtotime('1970-01-01 ' . $value);
        return $ts === false ? null : (int)$ts;
    };

    $morningIn = $toSeconds($attendance['morning_time_in'] ?? null);
    $morningOut = $toSeconds($attendance['morning_time_out'] ?? null);
    $afternoonIn = $toSeconds($attendance['afternoon_time_in'] ?? null);
    $afternoonOut = $toSeconds($attendance['afternoon_time_out'] ?? null);

    $rawHours = student_dtr_calculate_hours($attendance);
    $lunchDeductionHours = 0.0;

    if ($morningIn !== null && $morningOut !== null && $afternoonIn !== null && $afternoonOut !== null) {
        $breakSeconds = max(0, $afternoonIn - $morningOut);
        if ($breakSeconds < 3600) {
            $lunchDeductionHours = round((3600 - $breakSeconds) / 3600, 2);
        }
    }

    $netHours = max(0.0, round($rawHours - $lunchDeductionHours, 2));
    $overtimeHours = $netHours > 8.0 ? round($netHours - 8.0, 2) : 0.0;

    return [
        'raw_hours' => $rawHours,
        'lunch_deduction_hours' => $lunchDeductionHours,
        'net_hours' => $netHours,
        'overtime_hours' => $overtimeHours,
    ];
}

function student_dtr_format_time(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '00:00:00') {
        return '--';
    }

    $ts = strtotime($raw);
    return $ts !== false ? date('g:i A', $ts) : $raw;
}

function student_dtr_review_meta(array $attendance): array
{
    if (strtolower(trim((string)($attendance['source'] ?? ''))) === 'biometric') {
        return ['label' => 'Auto-Verified', 'class' => 'approved'];
    }

    $status = strtolower(trim((string)($attendance['status'] ?? 'pending')));
    if ($status === 'approved') {
        return ['label' => 'Reviewed', 'class' => 'approved'];
    }
    if ($status === 'rejected') {
        return ['label' => 'Rejected', 'class' => 'rejected'];
    }

    return ['label' => 'Needs Review', 'class' => 'pending'];
}

function student_dtr_time_options(int $stepMinutes = 30, int $startHour = 5, int $endHour = 20): array
{
    $options = [];
    $stepMinutes = max(5, $stepMinutes);
    $startMinutes = max(0, min(23 * 60 + 55, $startHour * 60));
    $endMinutes = max($startMinutes, min(24 * 60 - $stepMinutes, $endHour * 60));

    for ($minutes = $startMinutes; $minutes <= $endMinutes; $minutes += $stepMinutes) {
        $hour = intdiv($minutes, 60);
        $minute = $minutes % 60;
        $value = sprintf('%02d:%02d', $hour, $minute);
        $label = date('g:i A', strtotime($value . ':00'));
        $options[$value] = $label;
    }

    return $options;
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$requestedMonthNumber = isset($_GET['month_num']) ? (int)$_GET['month_num'] : 0;

if ($requestedYear >= 2024 && $requestedMonthNumber >= 1 && $requestedMonthNumber <= 12) {
    $selectedMonth = sprintf('%04d-%02d', $requestedYear, $requestedMonthNumber);
} else {
    $selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}\-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }
}

$selectedYear = (int)substr($selectedMonth, 0, 4);
$selectedMonthNumber = (int)substr($selectedMonth, 5, 2);
$availableYears = [];
for ($year = max((int)date('Y') + 1, $selectedYear); $year >= 2024; $year--) {
    $availableYears[] = $year;
}
$monthOptions = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];
$manualTimeOptions = student_dtr_time_options(30, 5, 20);

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));

$user = null;
$student = null;
$internship = null;
$attendanceRows = [];
$attendanceSummary = [
    'total_logs' => 0,
    'approved_logs' => 0,
    'pending_logs' => 0,
    'rejected_logs' => 0,
    'total_hours' => 0.0,
];
$attendanceInsights = [
    'approved_hours' => 0.0,
    'pending_hours' => 0.0,
    'rejected_hours' => 0.0,
    'biometric_logs' => 0,
    'manual_logs' => 0,
    'days_present' => 0,
    'average_hours' => 0.0,
    'last_recorded_date' => '',
];

$userStmt = $conn->prepare('SELECT id, name, email, profile_picture FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: null;
    $userStmt->close();
}

$studentLookupSql = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.assignment_track,
        s.internal_total_hours, s.internal_total_hours_remaining, s.external_total_hours, s.external_total_hours_remaining,
        c.name AS course_name, sec.code AS section_code, sec.name AS section_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE s.user_id = ?
    LIMIT 1";
$studentStmt = $conn->prepare($studentLookupSql);
if ($studentStmt) {
    $studentStmt->bind_param('i', $currentUserId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
}

if (!$student && $user) {
    $fallbackEmail = trim((string)($user['email'] ?? ''));
    $fallbackName = trim((string)($user['name'] ?? ''));
    $fallbackStmt = $conn->prepare(
        "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.assignment_track,
                s.internal_total_hours, s.internal_total_hours_remaining, s.external_total_hours, s.external_total_hours_remaining,
                c.name AS course_name, sec.code AS section_code, sec.name AS section_name
         FROM students s
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN sections sec ON sec.id = s.section_id
         WHERE ((? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
             OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)))
         ORDER BY s.id DESC
         LIMIT 1"
    );
    if ($fallbackStmt) {
        $fallbackStmt->bind_param('ssss', $fallbackEmail, $fallbackEmail, $fallbackName, $fallbackName);
        $fallbackStmt->execute();
        $student = $fallbackStmt->get_result()->fetch_assoc() ?: null;
        $fallbackStmt->close();
    }
}

if ($student) {
    $studentId = (int)($student['id'] ?? 0);
    student_dtr_ensure_runtime_dir(student_dtr_manual_upload_dir());

    $internshipStmt = $conn->prepare(
        "SELECT company_name, position, status, start_date, end_date, required_hours, rendered_hours, completion_percentage
         FROM internships
         WHERE student_id = ? AND deleted_at IS NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT 1"
    );
    if ($internshipStmt) {
        $internshipStmt->bind_param('i', $studentId);
        $internshipStmt->execute();
        $internship = $internshipStmt->get_result()->fetch_assoc() ?: null;
        $internshipStmt->close();
    }

    $attendanceStmt = $conn->prepare(
        "SELECT id, attendance_date, morning_time_in, morning_time_out, break_time_in, break_time_out,
                afternoon_time_in, afternoon_time_out, total_hours, status, remarks, source
         FROM attendances
         WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
         ORDER BY attendance_date DESC, id DESC"
    );
    if ($attendanceStmt) {
        $attendanceStmt->bind_param('iss', $studentId, $monthStart, $monthEnd);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result();
        while ($attendanceResult && ($row = $attendanceResult->fetch_assoc())) {
            $computedHours = isset($row['total_hours']) && $row['total_hours'] !== null && $row['total_hours'] !== ''
                ? (float)$row['total_hours']
                : student_dtr_calculate_hours($row);

            $row['display_hours'] = $computedHours;
            $attendanceRows[] = $row;

            $attendanceSummary['total_logs']++;
            $attendanceSummary['total_hours'] += $computedHours;
            $attendanceInsights['days_present']++;

            if ($attendanceInsights['last_recorded_date'] === '') {
                $attendanceInsights['last_recorded_date'] = (string)($row['attendance_date'] ?? '');
            }

            $statusKey = strtolower(trim((string)($row['status'] ?? 'pending')));
            if ($statusKey === 'approved') {
                $attendanceSummary['approved_logs']++;
                $attendanceInsights['approved_hours'] += $computedHours;
            } elseif ($statusKey === 'rejected') {
                $attendanceSummary['rejected_logs']++;
                $attendanceInsights['rejected_hours'] += $computedHours;
            } else {
                $attendanceSummary['pending_logs']++;
                $attendanceInsights['pending_hours'] += $computedHours;
            }

            $sourceKey = strtolower(trim((string)($row['source'] ?? 'manual')));
            if ($sourceKey === 'biometric') {
                $attendanceInsights['biometric_logs']++;
            } else {
                $attendanceInsights['manual_logs']++;
            }
        }
        $attendanceStmt->close();
    }

    if ($attendanceRows !== []) {
        $attendanceIds = [];
        foreach ($attendanceRows as $attendanceRow) {
            $attendanceId = (int)($attendanceRow['id'] ?? 0);
            if ($attendanceId > 0) {
                $attendanceIds[] = $attendanceId;
            }
        }

        if ($attendanceIds !== []) {
            $idList = implode(',', array_map('intval', array_unique($attendanceIds)));
            $proofMap = [];
            $proofResult = $conn->query("
                SELECT attendance_id, file_path, file_name
                FROM manual_dtr_attachments
                WHERE deleted_at IS NULL
                  AND attendance_id IN ($idList)
                ORDER BY id ASC
            ");
            if ($proofResult) {
                while ($proofRow = $proofResult->fetch_assoc()) {
                    $attendanceId = (int)($proofRow['attendance_id'] ?? 0);
                    if ($attendanceId <= 0 || isset($proofMap[$attendanceId])) {
                        continue;
                    }

                    $proofMap[$attendanceId] = [
                        'file_path' => (string)($proofRow['file_path'] ?? ''),
                        'file_name' => (string)($proofRow['file_name'] ?? ''),
                    ];
                }
            }

            foreach ($attendanceRows as &$attendanceRow) {
                $attendanceId = (int)($attendanceRow['id'] ?? 0);
                $attendanceRow['proof_attachment'] = $proofMap[$attendanceId] ?? null;
            }
            unset($attendanceRow);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_action']) && $_POST['student_action'] === 'submit_machine_down' && !empty($student)) {
    require_once dirname(__DIR__) . '/lib/attendance_rules.php';

    $fallbackMode = strtolower(trim((string)($_POST['fallback_mode'] ?? 'daily')));
    if (!in_array($fallbackMode, ['daily', 'weekly'], true)) {
        $fallbackMode = 'daily';
    }

    $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
    $attendanceEndDate = trim((string)($_POST['attendance_end_date'] ?? ''));
    $morningIn = trim((string)($_POST['morning_time_in'] ?? ''));
    $morningOut = trim((string)($_POST['morning_time_out'] ?? ''));
    $afternoonIn = trim((string)($_POST['afternoon_time_in'] ?? ''));
    $afternoonOut = trim((string)($_POST['afternoon_time_out'] ?? ''));
    $fallbackReason = trim((string)($_POST['fallback_reason'] ?? ''));
    $proofClockTime = trim((string)($_POST['proof_clock_time'] ?? ''));
    $proofFile = $_FILES['proof_image'] ?? null;

    $normalize = static function (string $value): ?string {
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
    };

    $payload = [
        'morning_time_in' => $normalize($morningIn),
        'morning_time_out' => $normalize($morningOut),
        'break_time_in' => null,
        'break_time_out' => null,
        'afternoon_time_in' => $normalize($afternoonIn),
        'afternoon_time_out' => $normalize($afternoonOut),
    ];

    $trackKey = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
    $studentRemainingHours = $trackKey === 'external'
        ? (int)($student['external_total_hours_remaining'] ?? 0)
        : (int)($student['internal_total_hours_remaining'] ?? 0);

    $errors = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
        $errors[] = 'Valid attendance date is required.';
    }
    if ($fallbackMode === 'weekly' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceEndDate)) {
        $errors[] = 'Valid week end date is required for weekly fallback.';
    }
    if ($fallbackReason === '') {
        $errors[] = 'Reason/details are required.';
    }
    if ($studentRemainingHours <= 0) {
        $errors[] = 'Your required internship hours are already completed. Please contact your coordinator for corrections.';
    }
    if (!is_array($proofFile) || (int)($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Proof image is required.';
    }
    if ($payload['morning_time_in'] === null && $payload['morning_time_out'] === null && $payload['afternoon_time_in'] === null && $payload['afternoon_time_out'] === null) {
        $errors[] = 'Enter at least one time.';
    }
    $validation = attendance_validate_full_record($payload);
    if (!($validation['ok'] ?? false)) {
        $errors[] = (string)($validation['message'] ?? 'Invalid attendance values.');
    }

    $targetDates = [];
    if ($errors === []) {
        $startTs = strtotime($attendanceDate);
        if ($startTs === false) {
            $errors[] = 'Invalid start date.';
        } else {
            $endTs = $fallbackMode === 'weekly' ? strtotime($attendanceEndDate) : $startTs;
            if ($endTs === false) {
                $errors[] = 'Invalid end date.';
            } elseif ($endTs < $startTs) {
                $errors[] = 'Week end date must be the same as or later than start date.';
            } elseif ($fallbackMode === 'weekly' && (($endTs - $startTs) / 86400) > 6) {
                $errors[] = 'Weekly fallback can cover up to 7 days only.';
            } else {
                $today = strtotime(date('Y-m-d'));
                for ($cursor = $startTs; $cursor <= $endTs; $cursor += 86400) {
                    if ($cursor > $today) {
                        continue;
                    }
                    $dayOfWeek = (int)date('N', $cursor);
                    if ($dayOfWeek >= 6) {
                        continue;
                    }
                    $targetDates[] = date('Y-m-d', $cursor);
                }

                if ($targetDates === []) {
                    $errors[] = 'No valid weekday dates found in the selected range.';
                }
            }
        }
    }

    if ($errors === []) {
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $fileError = (int)($proofFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'Proof image upload failed.';
        } else {
            $tmpPath = (string)($proofFile['tmp_name'] ?? '');
            $fileSize = (int)($proofFile['size'] ?? 0);
            $detectedMime = $tmpPath !== '' && function_exists('mime_content_type') ? (string)@mime_content_type($tmpPath) : '';
            if (!isset($allowedMimeTypes[$detectedMime])) {
                $errors[] = 'Proof image must be JPG, PNG, or WEBP.';
            }
            if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                $errors[] = 'Proof image must be 5MB or smaller.';
            }
        }
    }

    if ($errors === []) {
        $existingStmt = $conn->prepare("SELECT id, source FROM attendances WHERE student_id = ? AND attendance_date = ? ORDER BY id DESC LIMIT 1");
        if ($existingStmt) {
            foreach ($targetDates as $targetDate) {
                $existingStmt->bind_param('is', $studentId, $targetDate);
                $existingStmt->execute();
                $existing = $existingStmt->get_result()->fetch_assoc() ?: null;
                if ($existing) {
                    $existingSource = strtolower(trim((string)($existing['source'] ?? 'manual')));
                    $errors[] = $existingSource === 'biometric'
                        ? 'Biometric attendance already exists for ' . $targetDate . '. Please request a correction instead.'
                        : 'Manual fallback entry already exists for ' . $targetDate . '.';
                    break;
                }
            }
            $existingStmt->close();
        }
    }

    if ($errors === []) {
        $metrics = student_dtr_manual_metrics($payload);
        $baseReason = $fallbackReason;
        $notes = [];
        if ($metrics['lunch_deduction_hours'] > 0) {
            $notes[] = 'Lunch deduction: ' . number_format((float)$metrics['lunch_deduction_hours'], 2) . ' hr';
        }
        if ($metrics['overtime_hours'] > 0) {
            $notes[] = 'Overtime: ' . number_format((float)$metrics['overtime_hours'], 2) . ' hr';
        }
        if ($notes !== []) {
            $baseReason .= ' | ' . implode(' | ', $notes);
        }

        $uploadDir = student_dtr_manual_upload_dir();
        $proofRelativePath = '';
        $proofOriginalName = trim((string)($proofFile['name'] ?? 'proof-image'));
        $proofMime = (string)@mime_content_type((string)$proofFile['tmp_name']);
        $proofExtension = $allowedMimeTypes[$proofMime] ?? 'jpg';
        $safeDatePart = preg_replace('/[^0-9]/', '', $attendanceDate) ?: date('Ymd');
        $targetFileName = sprintf('student_%d_%s_%s.%s', $studentId, $safeDatePart, date('His'), $proofExtension);
        $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $targetFileName;

        if (!student_dtr_ensure_runtime_dir($uploadDir) || !@move_uploaded_file((string)$proofFile['tmp_name'], $targetPath)) {
            $_SESSION['student_dtr_flash'] = [
                'type' => 'danger',
                'message' => 'Could not save the proof image.',
            ];
            $redirectMonth = preg_match('/^\d{4}\-\d{2}$/', $selectedMonth) ? $selectedMonth : date('Y-m');
            header('Location: student-dtr.php?month=' . urlencode($redirectMonth));
            exit;
        }

        $proofRelativePath = $targetFileName;
        $insert = $conn->prepare("
            INSERT INTO attendances (
                student_id, attendance_date, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out,
                total_hours, source, status, remarks, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', 'pending', ?, NOW(), NOW())
        ");
        if ($insert) {
            $attendanceIds = [];
            $successCount = 0;
            foreach ($targetDates as $targetDate) {
                $netHours = (float)$metrics['net_hours'];
                $remarks = $baseReason;
                $insert->bind_param(
                    'isssssds',
                    $studentId,
                    $targetDate,
                    $payload['morning_time_in'],
                    $payload['morning_time_out'],
                    $payload['afternoon_time_in'],
                    $payload['afternoon_time_out'],
                    $netHours,
                    $remarks
                );
                $insert->execute();
                if ($insert->affected_rows > 0) {
                    $successCount++;
                    $attendanceIds[] = (int)$insert->insert_id;
                }
            }
            $insert->close();

            if ($attendanceIds !== []) {
                $attachmentReason = 'Machine-down fallback proof';
                if ($proofClockTime !== '') {
                    $attachmentReason .= ' | submitted at ' . $proofClockTime;
                }
                if ($fallbackMode === 'weekly') {
                    $attachmentReason .= ' | weekly range';
                }

                $attachmentStmt = $conn->prepare("
                    INSERT INTO manual_dtr_attachments (
                        student_id, attendance_id, attendance_date, file_path, file_name, file_type, file_size, reason, uploaded_by, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                if ($attachmentStmt) {
                    $fileSize = (int)($proofFile['size'] ?? 0);
                    $uploadedBy = $currentUserId;
                    foreach ($attendanceIds as $index => $attendanceId) {
                        $attendanceDateForAttachment = $targetDates[$index] ?? $attendanceDate;
                        $attachmentStmt->bind_param(
                            'iissssisi',
                            $studentId,
                            $attendanceId,
                            $attendanceDateForAttachment,
                            $proofRelativePath,
                            $proofOriginalName,
                            $proofMime,
                            $fileSize,
                            $attachmentReason,
                            $uploadedBy
                        );
                        $attachmentStmt->execute();
                    }
                    $attachmentStmt->close();
                }
            }

            $success = $successCount > 0;
            $_SESSION['student_dtr_flash'] = [
                'type' => $success ? 'success' : 'danger',
                'message' => $success
                    ? ($fallbackMode === 'weekly'
                        ? ('Machine-down fallback attendance submitted for review for ' . $successCount . ' day(s).')
                        : 'Machine-down fallback attendance submitted for review.')
                    : 'Could not submit the fallback attendance.',
            ];
        } else {
            $_SESSION['student_dtr_flash'] = [
                'type' => 'danger',
                'message' => 'Could not prepare fallback attendance submission.',
            ];
        }
    } else {
        $_SESSION['student_dtr_flash'] = [
            'type' => 'danger',
            'message' => implode(' ', $errors),
        ];
    }

    $redirectMonth = preg_match('/^\d{4}\-\d{2}$/', $selectedMonth) ? $selectedMonth : date('Y-m');
    header('Location: student-dtr.php?month=' . urlencode($redirectMonth));
    exit;
}

if ($attendanceSummary['total_logs'] > 0) {
    $attendanceInsights['average_hours'] = round($attendanceSummary['total_hours'] / $attendanceSummary['total_logs'], 2);
}

$displayName = trim((string)($user['name'] ?? ''));
if ($displayName === '' && $student) {
    $displayName = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
}
if ($displayName === '') {
    $displayName = 'Student User';
}

$courseSection = array_filter([
    trim((string)($student['course_name'] ?? '')),
    biotern_format_section_code((string)($student['section_code'] ?? '')),
    trim((string)($student['section_name'] ?? '')),
]);
$avatarSrc = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $currentUserId);
$requiredHours = (float)($internship['required_hours'] ?? 0);
$renderedHours = (float)($internship['rendered_hours'] ?? 0);
$completionPercentage = (float)($internship['completion_percentage'] ?? 0);
$track = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
$remainingHours = $track === 'external'
    ? (int)($student['external_total_hours_remaining'] ?? 0)
    : (int)($student['internal_total_hours_remaining'] ?? 0);
$lastRecordedDateText = $attendanceInsights['last_recorded_date'] !== ''
    ? date('M d, Y', strtotime($attendanceInsights['last_recorded_date']))
    : 'No entries yet';

$page_title = 'BioTern || My DTR';
$page_styles = [
    'assets/css/homepage-student.css',
    'assets/css/student-dtr.css',
];
$page_scripts = [
    'assets/js/modules/pages/student-dtr-proof-clock.js',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="main-content">
            <div class="student-home-shell student-dtr-shell">
                <section class="card student-home-hero border-0 student-dtr-hero-card">
            <div class="card-body">
                <div class="student-dtr-hero">
                    <div>
                        <span class="student-home-eyebrow">Daily Time Record</span>
                        <h2><?php echo htmlspecialchars($displayName !== '' ? $displayName : 'Student User', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p>Track your attendance logs for <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>, review approval status, and submit a fallback record only when the biometric machine is unavailable.</p>
                        <div class="student-home-meta">
                            <?php if (!empty($courseSection)): ?>
                            <span><?php echo htmlspecialchars(implode(' | ', $courseSection), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($track === 'external' ? 'External Assignment' : 'Internal Assignment', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?php echo number_format($attendanceSummary['total_logs']); ?> logs this month</span>
                        </div>
                    </div>
                    <form method="get" class="student-dtr-filter">
                        <div>
                            <label class="form-label" for="studentDtrMonthSelect">Month</label>
                            <select id="studentDtrMonthSelect" name="month_num" class="form-select">
                                <?php foreach ($monthOptions as $monthNumber => $monthLabelOption): ?>
                                <option value="<?php echo $monthNumber; ?>" <?php echo $monthNumber === $selectedMonthNumber ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($monthLabelOption, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="studentDtrYearSelect">Year</label>
                            <select id="studentDtrYearSelect" name="year" class="form-select">
                                <?php foreach ($availableYears as $yearOption): ?>
                                <option value="<?php echo $yearOption; ?>" <?php echo $yearOption === $selectedYear ? 'selected' : ''; ?>>
                                    <?php echo $yearOption; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <?php if (is_array($studentDtrFlash) && !empty($studentDtrFlash['message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)($studentDtrFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?> mt-4">
            <?php echo htmlspecialchars((string)$studentDtrFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <div class="student-dtr-metrics">
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Total Logs</div>
                <strong><?php echo (int)$attendanceSummary['total_logs']; ?></strong>
                <small>Recorded entries for <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>.</small>
            </article>
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Approved</div>
                <strong><?php echo (int)$attendanceSummary['approved_logs']; ?></strong>
                <small><?php echo number_format((float)$attendanceInsights['approved_hours'], 2); ?> approved hours.</small>
            </article>
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Pending</div>
                <strong><?php echo (int)$attendanceSummary['pending_logs']; ?></strong>
                <small><?php echo number_format((float)$attendanceInsights['pending_hours'], 2); ?> hours waiting.</small>
            </article>
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Logged Hours</div>
                <strong><?php echo number_format((float)$attendanceSummary['total_hours'], 2); ?></strong>
                <small>Average <?php echo number_format((float)$attendanceInsights['average_hours'], 2); ?> hours per entry.</small>
            </article>
        </div>

        <section class="card student-panel mt-4 student-dtr-fallback-card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <span class="student-metric-label">Machine Down Fallback</span>
                        <h3 class="mb-1">Submit Manual DTR</h3>
                        <div class="student-dtr-meta">Use this only when the biometric machine is unavailable. Manual fallback entries stay in review until checked by the school.</div>
                    </div>
                </div>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="student_action" value="submit_machine_down">
                    <input type="hidden" name="proof_clock_time" id="proofClockTime" value="">
                    <div class="col-md-4">
                        <label class="form-label" for="fallbackMode">Mode</label>
                        <select class="form-select" id="fallbackMode" name="fallback_mode">
                            <option value="daily" selected>Daily (single date)</option>
                            <option value="weekly">Weekly (Mon-Fri range)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fallbackAttendanceDate">Start Date</label>
                        <input type="date" class="form-control" id="fallbackAttendanceDate" name="attendance_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fallbackAttendanceEndDate">End Date (weekly only)</label>
                        <input type="date" class="form-control" id="fallbackAttendanceEndDate" name="attendance_end_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Proof Clock</label>
                        <div class="form-control d-flex align-items-center justify-content-between student-dtr-proof-clock">
                            <strong id="proofClockDisplay">--:--:--</strong>
                            <span class="text-muted small" id="proofClockDate">--</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="proofImage">Proof Image</label>
                        <input type="file" class="form-control" id="proofImage" name="proof_image" accept="image/png,image/jpeg,image/webp" capture="environment" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="fallbackMorningIn">Morning In</label>
                        <select class="form-select student-dtr-time-select" id="fallbackMorningIn" name="morning_time_in">
                            <option value="">Select time</option>
                            <?php foreach ($manualTimeOptions as $timeValue => $timeLabel): ?>
                            <option value="<?php echo htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="fallbackMorningOut">Morning Out</label>
                        <select class="form-select student-dtr-time-select" id="fallbackMorningOut" name="morning_time_out">
                            <option value="">Select time</option>
                            <?php foreach ($manualTimeOptions as $timeValue => $timeLabel): ?>
                            <option value="<?php echo htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="fallbackAfternoonIn">Afternoon In</label>
                        <select class="form-select student-dtr-time-select" id="fallbackAfternoonIn" name="afternoon_time_in">
                            <option value="">Select time</option>
                            <?php foreach ($manualTimeOptions as $timeValue => $timeLabel): ?>
                            <option value="<?php echo htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="fallbackAfternoonOut">Afternoon Out</label>
                        <select class="form-select student-dtr-time-select" id="fallbackAfternoonOut" name="afternoon_time_out">
                            <option value="">Select time</option>
                            <?php foreach ($manualTimeOptions as $timeValue => $timeLabel): ?>
                            <option value="<?php echo htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="fallbackReason">Reason / What happened</label>
                        <textarea class="form-control" id="fallbackReason" name="fallback_reason" rows="3" placeholder="Example: Biometric machine was offline from 8:00 AM to 5:00 PM. Supervisor confirmed my time in/out."></textarea>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-warning" <?php echo max(0, $remainingHours) <= 0 ? 'disabled' : ''; ?>>Submit Fallback Attendance</button>
                        <small class="text-muted">Weekly mode submits weekdays only. Lunch is auto-deducted to at least 1 hour when needed, and overtime is noted in remarks.</small>
                    </div>
                </form>
            </div>
        </section>

        <div class="row g-4 align-items-start mt-0">
            <div class="col-12 col-xl-8">
                <section class="card student-panel">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <span class="student-metric-label">Attendance Logs</span>
                                <h3 class="mb-1">My DTR Entries</h3>
                                <div class="student-dtr-meta">Daily attendance records for the selected month.</div>
                            </div>
                            <a href="student-profile.php" class="btn btn-outline-primary">Back to Profile</a>
                        </div>

                        <?php if (!empty($attendanceRows)): ?>
                        <div class="student-dtr-table-wrap">
                            <table class="student-dtr-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Morning In</th>
                                        <th>Morning Out</th>
                                        <th>Break In</th>
                                        <th>Break Out</th>
                                        <th>Afternoon In</th>
                                        <th>Afternoon Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRows as $row): ?>
                                    <?php $reviewMeta = student_dtr_review_meta($row); ?>
                                    <tr>
                                        <td data-label="Date">
                                            <strong><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$row['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (trim((string)($row['remarks'] ?? '')) !== ''): ?>
                                            <div class="student-dtr-cell-note mt-1"><?php echo htmlspecialchars((string)$row['remarks'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['proof_attachment']['file_path'])): ?>
                                            <div class="student-dtr-cell-note mt-1">
                                                <a href="<?php echo htmlspecialchars(student_dtr_manual_upload_web_path((string)$row['proof_attachment']['file_path']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View proof image</a>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Morning In"><?php echo htmlspecialchars(student_dtr_format_time($row['morning_time_in'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Morning Out"><?php echo htmlspecialchars(student_dtr_format_time($row['morning_time_out'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Break In"><?php echo htmlspecialchars(student_dtr_format_time($row['break_time_in'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Break Out"><?php echo htmlspecialchars(student_dtr_format_time($row['break_time_out'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Afternoon In"><?php echo htmlspecialchars(student_dtr_format_time($row['afternoon_time_in'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Afternoon Out"><?php echo htmlspecialchars(student_dtr_format_time($row['afternoon_time_out'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Hours"><strong><?php echo number_format((float)($row['display_hours'] ?? 0), 2); ?>h</strong></td>
                                        <td data-label="Status"><span class="student-dtr-status <?php echo htmlspecialchars((string)$reviewMeta['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$reviewMeta['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-label="Source"><?php echo htmlspecialchars(ucfirst((string)($row['source'] ?? 'manual')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="student-dtr-empty">No DTR entries found for <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?> yet.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="card student-panel student-dtr-side-card">
                    <div class="card-body">
                        <span class="student-metric-label">Internship</span>
                        <h3 class="mb-3">Progress</h3>
                        <div class="student-detail-list student-dtr-side-list">
                            <div>
                                <span>Company</span>
                                <strong><?php echo htmlspecialchars(trim((string)($internship['company_name'] ?? '')) !== '' ? (string)$internship['company_name'] : 'No company assigned yet', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Position</span>
                                <strong><?php echo htmlspecialchars(trim((string)($internship['position'] ?? '')) !== '' ? (string)$internship['position'] : 'No position assigned yet', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Current status</span>
                                <strong><?php echo htmlspecialchars(trim((string)($internship['status'] ?? '')) !== '' ? ucfirst((string)$internship['status']) : 'Not started', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Rendered vs required</span>
                                <strong><?php echo number_format($renderedHours, 0); ?> / <?php echo number_format($requiredHours, 0); ?> hrs</strong>
                            </div>
                            <div>
                                <span>Completion</span>
                                <strong><?php echo number_format($completionPercentage, 0); ?>%</strong>
                            </div>
                            <div>
                                <span>Remaining on record</span>
                                <strong><?php echo number_format(max(0, $remainingHours), 0); ?> hrs</strong>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4 student-dtr-side-card">
                    <div class="card-body">
                        <span class="student-metric-label">Month Snapshot</span>
                        <h3 class="mb-3">Attendance Insight</h3>
                        <div class="student-detail-list student-dtr-side-list">
                            <div>
                                <span>Days with logs</span>
                                <strong><?php echo (int)$attendanceInsights['days_present']; ?></strong>
                            </div>
                            <div>
                                <span>Biometric entries</span>
                                <strong><?php echo (int)$attendanceInsights['biometric_logs']; ?></strong>
                            </div>
                            <div>
                                <span>Manual entries</span>
                                <strong><?php echo (int)$attendanceInsights['manual_logs']; ?></strong>
                            </div>
                            <div>
                                <span>Rejected logs</span>
                                <strong><?php echo (int)$attendanceSummary['rejected_logs']; ?></strong>
                            </div>
                            <div>
                                <span>Last recorded day</span>
                                <strong><?php echo htmlspecialchars($lastRecordedDateText, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <span class="student-metric-label">Quick Links</span>
                        <div class="d-grid gap-2">
                            <a href="student-profile.php" class="btn btn-outline-primary">My Profile</a>
                            <a href="document_application.php" class="btn btn-outline-secondary">My Documents</a>
                            <a href="apps-calendar.php" class="btn btn-outline-secondary">Calendar</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>

