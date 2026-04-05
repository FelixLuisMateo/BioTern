<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$attendance_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($attendance_role === 'student') {
    require __DIR__ . '/student-dtr.php';
    return;
}
$attendance_user_id = (int)($_SESSION['user_id'] ?? 0);
$attendance_is_supervisor = ($attendance_role === 'supervisor');
$attendance_supervisor_profile_id = 0;

// Database Connection
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    section_schedule_ensure_columns($conn);
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

if ($attendance_is_supervisor && $attendance_user_id > 0) {
    $attendance_scope_stmt = $conn->prepare('SELECT id FROM supervisors WHERE user_id = ? LIMIT 1');
    if ($attendance_scope_stmt) {
        $attendance_scope_stmt->bind_param('i', $attendance_user_id);
        $attendance_scope_stmt->execute();
        $attendance_scope_row = $attendance_scope_stmt->get_result()->fetch_assoc();
        $attendance_supervisor_profile_id = (int)($attendance_scope_row['id'] ?? 0);
        $attendance_scope_stmt->close();
    }
}

function attendance_machine_config_path(): string
{
    return dirname(__DIR__) . '/tools/biometric_machine_config.json';
}

function attendance_load_machine_config(): array
{
    $configPath = attendance_machine_config_path();
    if (!file_exists($configPath)) {
        return [];
    }

    $json = file_get_contents($configPath);
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function attendance_write_machine_config(array $config): void
{
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Failed to encode biometric machine config.');
    }

    file_put_contents(attendance_machine_config_path(), $json . PHP_EOL);
}

function attendance_redirect_self(): void
{
    $target = 'attendance.php';
    $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
    if ($query !== '') {
        $target .= '?' . $query;
    }
    header('Location: ' . $target);
    exit;
}

function attendance_school_hours_config(?array $machineConfig = null): array
{
    $machineConfig = is_array($machineConfig) ? $machineConfig : attendance_load_machine_config();

    $start = section_schedule_format_time_input((string)($machineConfig['attendanceStartTime'] ?? '08:00:00'));
    $end = section_schedule_format_time_input((string)($machineConfig['attendanceEndTime'] ?? '19:00:00'));

    return [
        'schedule_time_in' => $start !== '' ? $start : '08:00',
        'schedule_time_out' => $end !== '' ? $end : '19:00',
        'late_after_time' => $start !== '' ? $start : '08:00',
    ];
}

$machineConfig = attendance_load_machine_config();

// Fetch Attendance Statistics
$stats_query = "
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM attendances
";
$attendance_stats_where = [];
if ($attendance_is_supervisor && $attendance_user_id > 0) {
    $attendance_stats_scope = ["(i.supervisor_id = " . (int)$attendance_user_id . " OR s.supervisor_id = " . (int)$attendance_user_id . ")"];
    if ($attendance_supervisor_profile_id > 0 && $attendance_supervisor_profile_id !== $attendance_user_id) {
        $attendance_stats_scope[] = "(i.supervisor_id = " . (int)$attendance_supervisor_profile_id . " OR s.supervisor_id = " . (int)$attendance_supervisor_profile_id . ")";
    }
    $attendance_stats_where[] = '(' . implode(' OR ', $attendance_stats_scope) . ')';
}
if (!empty($attendance_stats_where)) {
    $stats_query = "
        SELECT 
            SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            COUNT(*) as total_count
        FROM attendances a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
        WHERE " . implode(' AND ', $attendance_stats_where);
}
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
// Prepare filter inputs (defaults: today's date)
$filter_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : '';
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : '';
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$filter_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$filter_school_year = isset($_GET['school_year']) ? trim((string)$_GET['school_year']) : '';
$filter_supervisor = isset($_GET['supervisor']) ? trim($_GET['supervisor']) : '';
$filter_coordinator = isset($_GET['coordinator']) ? trim($_GET['coordinator']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$school_year_options = [];
$school_year_start = 2005;
$current_calendar_month = (int)date('n');
$current_calendar_year = (int)date('Y');
$current_school_year_start = $current_calendar_month >= 7 ? $current_calendar_year : ($current_calendar_year - 1);
$latest_school_year_start = max(2025, $current_school_year_start);
for ($year = $latest_school_year_start; $year >= $school_year_start; $year--) {
    $school_year_options[] = sprintf('%d-%d', $year, $year + 1);
}

// default to today when no date filters provided
if (empty($filter_date) && empty($start_date) && empty($end_date) && empty($filter_status)) {
    $filter_date = date('Y-m-d');
}

// Fetch dropdown lists
$courses = [];
// Determine which column exists for active flag on courses to avoid schema mismatch errors
$db_esc = $conn->real_escape_string($db_name);
$has_is_active = false;
$has_status_col = false;
$col_check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $db_esc . "' AND TABLE_NAME = 'courses' AND COLUMN_NAME IN ('is_active','status')");
if ($col_check && $col_check->num_rows) {
    while ($c = $col_check->fetch_assoc()) {
        if ($c['COLUMN_NAME'] === 'is_active') $has_is_active = true;
        if ($c['COLUMN_NAME'] === 'status') $has_status_col = true;
    }
}

$courses_query = "SELECT id, name FROM courses";
if ($has_is_active) {
    $courses_query .= " WHERE is_active = 1";
} elseif ($has_status_col) {
    $courses_query .= " WHERE status = 1";
}
$courses_query .= " ORDER BY name ASC";

$courses_res = $conn->query($courses_query);
if ($courses_res && $courses_res->num_rows) {
    while ($r = $courses_res->fetch_assoc()) $courses[] = $r;
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res && $dept_res->num_rows) {
    while ($r = $dept_res->fetch_assoc()) $departments[] = $r;
}

$sections = [];
$section_res = $conn->query("SELECT id, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($section_res && $section_res->num_rows) {
    while ($r = $section_res->fetch_assoc()) $sections[] = $r;
}

$supervisors = [];
$sup_res = $conn->query("
    SELECT DISTINCT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS supervisor_name
    FROM supervisors
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY supervisor_name ASC
");
if ($sup_res && $sup_res->num_rows) {
    while ($r = $sup_res->fetch_assoc()) $supervisors[] = $r['supervisor_name'];
}

$coordinators = [];
$coor_res = $conn->query("
    SELECT DISTINCT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS coordinator_name
    FROM coordinators
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY coordinator_name ASC
");
if ($coor_res && $coor_res->num_rows) {
    while ($r = $coor_res->fetch_assoc()) $coordinators[] = $r['coordinator_name'];
}

// Build attendance query filtered by provided inputs. Default shows today's records.
// Build WHERE clauses depending on provided filters
$where = [];
if ($attendance_is_supervisor && $attendance_user_id > 0) {
    $attendanceScopeParts = ["(i.supervisor_id = " . (int)$attendance_user_id . " OR s.supervisor_id = " . (int)$attendance_user_id . ")"];
    if ($attendance_supervisor_profile_id > 0 && $attendance_supervisor_profile_id !== $attendance_user_id) {
        $attendanceScopeParts[] = "(i.supervisor_id = " . (int)$attendance_supervisor_profile_id . " OR s.supervisor_id = " . (int)$attendance_supervisor_profile_id . ")";
    }
    $where[] = '(' . implode(' OR ', $attendanceScopeParts) . ')';
}
if (!empty($start_date) && !empty($end_date)) {
    $where[] = "a.attendance_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
} elseif (!empty($filter_date)) {
    $where[] = "a.attendance_date = '" . $conn->real_escape_string($filter_date) . "'";
}
if (!empty($filter_status)) {
    $allowed = ['approved','pending','rejected'];
    if (in_array($filter_status, $allowed)) {
        $where[] = "a.status = '" . $conn->real_escape_string($filter_status) . "'";
    }
}
if ($filter_course > 0) {
    $where[] = "s.course_id = " . intval($filter_course);
}
if ($filter_department > 0) {
    // join internships table to filter by department assignment
    $where[] = "i.department_id = " . intval($filter_department);
}
if ($filter_section > 0) {
    $where[] = "s.section_id = " . intval($filter_section);
}
if ($filter_school_year !== '' && preg_match('/^\d{4}-\d{4}$/', $filter_school_year) && in_array($filter_school_year, $school_year_options, true)) {
    $esc_school_year = $conn->real_escape_string($filter_school_year);
    $where[] = "s.school_year = '{$esc_school_year}'";
}
if (!empty($filter_supervisor)) {
    $esc_sup = $conn->real_escape_string($filter_supervisor);
    $where[] = "(
        TRIM(CONCAT_WS(' ', sup.first_name, sup.middle_name, sup.last_name)) LIKE '%{$esc_sup}%'
        OR s.supervisor_name LIKE '%{$esc_sup}%'
    )";
}
if (!empty($filter_coordinator)) {
    $esc_coor = $conn->real_escape_string($filter_coordinator);
    $where[] = "(
        TRIM(CONCAT_WS(' ', coor.first_name, coor.middle_name, coor.last_name)) LIKE '%{$esc_coor}%'
        OR s.coordinator_name LIKE '%{$esc_coor}%'
    )";
}

$attendance_query = "
     SELECT 
         a.id,
         a.attendance_date,
         a.morning_time_in,
         a.morning_time_out,
         a.break_time_in,
         a.break_time_out,
         a.afternoon_time_in,
         a.afternoon_time_out,
         a.total_hours,
         a.source,
         a.status,
         a.approved_by,
        a.approved_at,
        a.remarks,
        s.id as student_id,
        COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        s.student_id as student_number,
        s.first_name,
        s.last_name,
         s.email,
         s.section_id,
         s.supervisor_name,
         s.coordinator_name,
         sec.code AS section_code,
         sec.name AS section_name,
         c.name as course_name,
         d.name as department_name,
         sec.attendance_session,
        sec.schedule_time_in,
        sec.schedule_time_out,
        sec.late_after_time,
        sec.weekly_schedule_json,
        u.name as approver_name
    FROM attendances a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN users u_student ON s.user_id = u_student.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    LEFT JOIN supervisors sup ON i.supervisor_id = sup.id
    LEFT JOIN coordinators coor ON i.coordinator_id = coor.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN users u ON a.approved_by = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.attendance_date DESC, a.id DESC, s.last_name ASC
    LIMIT 100
";

$attendance_result = $conn->query($attendance_query);
$attendances = [];
if ($attendance_result && $attendance_result->num_rows > 0) {
    $seen_attendance_ids = [];
    while ($row = $attendance_result->fetch_assoc()) {
        $aid = isset($row['id']) ? (int)$row['id'] : 0;
        if ($aid > 0 && isset($seen_attendance_ids[$aid])) {
            continue;
        }
        if ($aid > 0) {
            $seen_attendance_ids[$aid] = true;
        }
        $attendances[] = $row;
    }
}

// Remove same-day duplicates per student, preferring the row that has actual punches.
if (count($attendances) > 1) {
    $attendance_by_student_date = [];
    foreach ($attendances as $attendance) {
        $student_id_key = isset($attendance['student_id']) ? (string)$attendance['student_id'] : '';
        $attendance_date_key = isset($attendance['attendance_date']) ? (string)$attendance['attendance_date'] : '';
        $dedupe_key = ($student_id_key !== '' && $attendance_date_key !== '')
            ? ($student_id_key . '|' . $attendance_date_key)
            : ('id|' . (string)($attendance['id'] ?? ''));

        if (!isset($attendance_by_student_date[$dedupe_key]) || shouldPreferAttendanceRow($attendance, $attendance_by_student_date[$dedupe_key])) {
            $attendance_by_student_date[$dedupe_key] = $attendance;
        }
    }
    $attendances = array_values($attendance_by_student_date);
}

synchronizeAttendanceProgress($conn, $attendances);

$missingScheduleAttendances = [];
foreach ($attendances as $attendance) {
    if (strtolower(trim((string)($attendance['source'] ?? ''))) !== 'biometric') {
        continue;
    }

    $schedule = attendance_effective_schedule($attendance);
    if (($schedule['window_source'] ?? 'none') === 'none') {
        $missingScheduleAttendances[] = $attendance;
    }
}

// If requested via AJAX, return only the table rows HTML so frontend can replace tbody
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (!empty($attendances)) {
        foreach ($attendances as $idx => $attendance) {
            $checkboxId = 'checkBox_' . $attendance['id'] . '_' . $idx;
            echo '<tr class="single-item">';
            if (attendanceCanReview($attendance)) {
                echo '<td><div class="item-checkbox ms-1"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input checkbox" id="' . $checkboxId . '" data-attendance-id="' . (int)$attendance['id'] . '"><label class="custom-control-label" for="' . $checkboxId . '"></label></div></div></td>';
            } else {
                echo '<td><span class="text-muted fs-12" title="Biometric records are auto-verified">Auto</span></td>';
            }
            // build avatar (use uploaded profile picture when available)
            $avatar_html = '<a href="students-view.php?id=' . $attendance['student_id'] . '" class="hstack gap-3">';
            $pp_url = resolve_attendance_profile_image_url((string)($attendance['profile_picture'] ?? ''));
            if ($pp_url !== null) {
                $avatar_html .= '<div class="avatar-image avatar-md"><img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid"></div>';
            } else {
                $initials = strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1));
                $avatar_html .= '<div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . $initials . '</div></div>';
            }
            $avatar_html .= '<div><div class="fw-bold">' . htmlspecialchars(($attendance['first_name'] ?? '') . ' ' . ($attendance['last_name'] ?? '')) . '</div><small class="text-muted">' . htmlspecialchars($attendance['student_number'] ?? '') . '</small></div></a>';
            echo '<td>' . $avatar_html . '</td>';
            echo '<td><span class="badge bg-soft-primary text-primary">' . date('Y-m-d', strtotime($attendance['attendance_date'])) . '</span></td>';
            echo '<td><span class="badge bg-soft-success text-success">' . ( $attendance['morning_time_in'] ? date('h:i A', strtotime($attendance['morning_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-success text-success">' . ( $attendance['morning_time_out'] ? date('h:i A', strtotime($attendance['morning_time_out'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . ( $attendance['afternoon_time_in'] ? date('h:i A', strtotime($attendance['afternoon_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . ( $attendance['afternoon_time_out'] ? date('h:i A', strtotime($attendance['afternoon_time_out'])) : '-' ) . '</span></td>';
            echo '<td>' . attendance_hours_cell_html($attendance) . '</td>';
            echo '<td>' . attendance_status_cell_html($attendance) . '</td>';
            echo '<td>' . getSourceBadge($attendance['source'] ?? 'manual', $attendance) . '</td>';
            echo '<td>' . getReviewBadge($attendance) . '</td>';
            $student_name = trim((string)($attendance['first_name'] ?? '') . ' ' . (string)($attendance['last_name'] ?? ''));
            $approval_status_label = ucfirst((string)($attendance['status'] ?? 'pending'));
            $morning_in_text = $attendance['morning_time_in'] ? date('h:i A', strtotime($attendance['morning_time_in'])) : '-';
            $morning_out_text = $attendance['morning_time_out'] ? date('h:i A', strtotime($attendance['morning_time_out'])) : '-';
            $afternoon_in_text = $attendance['afternoon_time_in'] ? date('h:i A', strtotime($attendance['afternoon_time_in'])) : '-';
            $afternoon_out_text = $attendance['afternoon_time_out'] ? date('h:i A', strtotime($attendance['afternoon_time_out'])) : '-';
            echo '<td>' . attendanceActionMenuItems($attendance) . '</td>';
            echo '</tr>';
        }
    }
    exit;
}

// Helper function to format time
function formatTime($time) {
    if ($time) {
        return date('h:i A', strtotime($time));
    }
    return '-';
}

function resolve_attendance_profile_image_url(string $profilePath): ?string {
    $clean = ltrim(str_replace('\\', '/', trim($profilePath)), '/');
    if ($clean === '') {
        return null;
    }
    $rootPath = dirname(__DIR__) . '/' . $clean;
    if (!file_exists($rootPath)) {
        return null;
    }
    $mtime = @filemtime($rootPath);
    return $clean . ($mtime ? ('?v=' . $mtime) : '');
}

// Helper function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'approved':
            return '<span class="badge bg-soft-success text-success">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
        case 'pending':
            return '<span class="badge bg-soft-warning text-warning">Pending</span>';
        default:
            return '<span class="badge bg-soft-secondary text-secondary">Unknown</span>';
    }
}

function attendancePlacementContextLabel(array $attendance): string {
    if (strtolower(trim((string)($attendance['source'] ?? ''))) !== 'biometric') {
        return '';
    }

    $schedule = attendance_effective_schedule($attendance);
    if (($schedule['window_source'] ?? '') === 'section') {
        return 'Placed using the student section schedule for this day.';
    }

    if (($schedule['window_source'] ?? '') === 'school') {
        return 'Placed using school open hours because the student section has no schedule for this day.';
    }

    return 'No attendance window is configured for this student or the school hours fallback.';
}

function attendancePlacementContextShortLabel(array $attendance): string {
    if (strtolower(trim((string)($attendance['source'] ?? ''))) !== 'biometric') {
        return '';
    }

    $schedule = attendance_effective_schedule($attendance);
    if (($schedule['window_source'] ?? '') === 'section') {
        return 'Section Schedule';
    }
    if (($schedule['window_source'] ?? '') === 'school') {
        return 'School Hours';
    }

    return 'No Window';
}

function getSourceBadge($source, array $attendance = []) {
    $placementLabel = attendancePlacementContextLabel($attendance);
    $placementShortLabel = attendancePlacementContextShortLabel($attendance);
    $titleAttr = $placementLabel !== '' ? (' title="' . htmlspecialchars($placementLabel, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip"') : '';

    switch (strtolower(trim((string)$source))) {
        case 'biometric':
            $html = '<span class="badge bg-soft-primary text-primary"' . $titleAttr . '>Biometric</span>';
            if ($placementShortLabel !== '') {
                $html .= '<div class="fs-11 text-muted mt-1">' . htmlspecialchars($placementShortLabel, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            return $html;
        case 'uploaded':
            return '<span class="badge bg-soft-info text-info">Uploaded</span>';
        default:
            return '<span class="badge bg-soft-secondary text-secondary">Manual</span>';
    }
}

// Helper function to calculate total hours
function calculateTotalHours($morning_in, $morning_out, $afternoon_in, $afternoon_out) {
    $total = 0;
    
    if ($morning_in && $morning_out) {
        $morning_time = strtotime($morning_out) - strtotime($morning_in);
        $total += $morning_time / 3600;
    }
    
    if ($afternoon_in && $afternoon_out) {
        $afternoon_time = strtotime($afternoon_out) - strtotime($afternoon_in);
        $total += $afternoon_time / 3600;
    }
    
    return round($total, 2);
}

function parseAttendanceTime($time) {
    $value = trim((string)$time);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : $timestamp;
}

function attendance_effective_schedule(array $attendance): array {
    global $machineConfig;
    return section_schedule_effective_day(
        section_schedule_from_row($attendance),
        (string)($attendance['attendance_date'] ?? ''),
        attendance_school_hours_config($machineConfig ?? [])
    );
}

function attendance_collect_punch_values(array $attendance): array {
    $values = [];
    foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
        $value = trim((string)($attendance[$column] ?? ''));
        if ($value === '' || $value === '00:00:00') {
            continue;
        }
        $values[] = $value;
    }

    usort($values, static function (string $left, string $right): int {
        return strcmp($left, $right);
    });

    return $values;
}

function attendance_window_metrics(array $attendance): array {
    $schedule = attendance_effective_schedule($attendance);
    $punches = attendance_collect_punch_values($attendance);
    $firstPunch = $punches[0] ?? null;
    $lastPunch = $punches !== [] ? $punches[count($punches) - 1] : null;
    $hasCompletedRange = $firstPunch !== null && $lastPunch !== null && strcmp($lastPunch, $firstPunch) > 0;

    $officialStart = section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00';
    $officialEnd = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? '')) ?: '19:00:00';
    $lateAfter = section_schedule_normalize_time_input((string)($schedule['late_after_time'] ?? '')) ?: $officialStart;

    $earlyHours = 0.0;
    $officialHours = 0.0;
    $overtimeHours = 0.0;

    if ($firstPunch !== null && strcmp($firstPunch, $officialStart) < 0) {
        $earlySeconds = max(0, strtotime($officialStart) - strtotime($firstPunch));
        $earlyHours = round($earlySeconds / 3600, 2);
    }

    if ($hasCompletedRange) {
        $rangeStart = max(strtotime($firstPunch), strtotime($officialStart));
        $rangeEnd = min(strtotime($lastPunch), strtotime($officialEnd));
        if ($rangeEnd > $rangeStart) {
            $officialHours = round(($rangeEnd - $rangeStart) / 3600, 2);
        }

        if (strcmp($lastPunch, $officialEnd) > 0) {
            $overtimeSeconds = max(0, strtotime($lastPunch) - strtotime($officialEnd));
            $overtimeHours = round($overtimeSeconds / 3600, 2);
        }
    }

    $arrivalStatus = 'absent';
    if ($firstPunch !== null) {
        if (strcmp($firstPunch, $officialStart) < 0) {
            $arrivalStatus = 'early';
        } elseif (strcmp($firstPunch, $lateAfter) > 0) {
            $arrivalStatus = 'late';
        } else {
            $arrivalStatus = 'present';
        }
    }

    return [
        'schedule' => $schedule,
        'first_punch' => $firstPunch,
        'last_punch' => $lastPunch,
        'has_completed_range' => $hasCompletedRange,
        'arrival_status' => $arrivalStatus,
        'official_hours' => $officialHours,
        'early_hours' => $earlyHours,
        'overtime_hours' => $overtimeHours,
    ];
}

function attendance_format_hours_label(float $hours): string {
    return number_format($hours, 2) . 'h';
}

function attendance_hours_cell_html(array $attendance): string {
    $computedHours = isset($attendance['computed_total_hours'])
        ? (float)$attendance['computed_total_hours']
        : calculateAttendanceRowHours($attendance);
    $metrics = attendance_window_metrics($attendance);

    $meta = ['Scheduled ' . attendance_format_hours_label((float)$metrics['official_hours'])];
    if ((float)$metrics['early_hours'] > 0) {
        $meta[] = 'Early ' . attendance_format_hours_label((float)$metrics['early_hours']);
    }
    if ((float)$metrics['overtime_hours'] > 0) {
        $meta[] = 'OT ' . attendance_format_hours_label((float)$metrics['overtime_hours']);
    }

    return '<span class="badge bg-soft-secondary text-secondary">' . attendance_format_hours_label($computedHours) . '</span>'
        . '<div class="fs-11 text-muted mt-1">' . htmlspecialchars(implode(' | ', $meta), ENT_QUOTES, 'UTF-8') . '</div>';
}

function attendance_status_cell_html(array $attendance): string {
    $metrics = attendance_window_metrics($attendance);
    $status = (string)($metrics['arrival_status'] ?? 'absent');

    if ($status === 'early') {
        $badge = '<span class="badge bg-soft-info text-info">Early</span>';
    } elseif ($status === 'present') {
        $badge = '<span class="badge bg-soft-success text-success">Present</span>';
    } elseif ($status === 'late') {
        $badge = '<span class="badge bg-soft-warning text-warning">Late</span>';
    } else {
        $badge = '<span class="badge bg-soft-danger text-danger">Absent</span>';
    }

    $notes = [];
    if ((($metrics['schedule']['window_source'] ?? '') === 'school')) {
        $notes[] = 'School hours';
    } elseif ((($metrics['schedule']['window_source'] ?? '') === 'section')) {
        $notes[] = 'Section schedule';
    }

    return $badge . ($notes !== []
        ? '<div class="fs-11 text-muted mt-1">' . htmlspecialchars(implode(' | ', $notes), ENT_QUOTES, 'UTF-8') . '</div>'
        : '');
}

function calculateAttendanceRowHours(array $attendance): float {
    $totalSeconds = 0;
    $pairs = [
        ['morning_time_in', 'morning_time_out'],
        ['afternoon_time_in', 'afternoon_time_out'],
    ];

    foreach ($pairs as $pair) {
        $startTs = parseAttendanceTime($attendance[$pair[0]] ?? null);
        $endTs = parseAttendanceTime($attendance[$pair[1]] ?? null);
        if ($startTs === null || $endTs === null || $endTs <= $startTs) {
            continue;
        }

        $totalSeconds += ($endTs - $startTs);
    }

    $breakInTs = parseAttendanceTime($attendance['break_time_in'] ?? null);
    $breakOutTs = parseAttendanceTime($attendance['break_time_out'] ?? null);
    if ($breakInTs !== null && $breakOutTs !== null && $breakOutTs > $breakInTs) {
        $totalSeconds -= ($breakOutTs - $breakInTs);
    }

    if ($totalSeconds < 0) {
        $totalSeconds = 0;
    }

    return round($totalSeconds / 3600, 2);
}

function synchronizeAttendanceProgress(mysqli $conn, array &$attendances): void {
    if (empty($attendances)) {
        return;
    }

    $studentIds = [];
    $updateAttendanceStmt = $conn->prepare("UPDATE attendances SET total_hours = ?, updated_at = NOW() WHERE id = ?");

    foreach ($attendances as &$attendance) {
        $computedHours = calculateAttendanceRowHours($attendance);
        $attendance['computed_total_hours'] = $computedHours;

        $studentId = (int)($attendance['student_id'] ?? 0);
        if ($studentId > 0) {
            $studentIds[$studentId] = true;
        }

        $attendanceId = (int)($attendance['id'] ?? 0);
        $storedHours = isset($attendance['total_hours']) && $attendance['total_hours'] !== null && $attendance['total_hours'] !== ''
            ? (float)$attendance['total_hours']
            : null;

        if ($attendanceId > 0 && $updateAttendanceStmt && ($storedHours === null || abs($storedHours - $computedHours) > 0.009)) {
            $updateAttendanceStmt->bind_param('di', $computedHours, $attendanceId);
            $updateAttendanceStmt->execute();
            $attendance['total_hours'] = $computedHours;
        }
    }
    unset($attendance);

    if ($updateAttendanceStmt) {
        $updateAttendanceStmt->close();
    }

    if (empty($studentIds)) {
        return;
    }

    $sumStmt = $conn->prepare("
        SELECT COALESCE(SUM(total_hours), 0) AS rendered
        FROM attendances
        WHERE student_id = ? AND (status IS NULL OR status <> 'rejected')
    ");
    $internshipLookupStmt = $conn->prepare("
        SELECT id, required_hours
        FROM internships
        WHERE student_id = ? AND status = 'ongoing'
        ORDER BY id DESC
        LIMIT 1
    ");
    $internshipUpdateStmt = $conn->prepare("
        UPDATE internships
        SET rendered_hours = ?, completion_percentage = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $studentLookupStmt = $conn->prepare("
        SELECT assignment_track, internal_total_hours, external_total_hours
        FROM students
        WHERE id = ?
        LIMIT 1
    ");
    $studentInternalUpdateStmt = $conn->prepare("
        UPDATE students
        SET internal_total_hours_remaining = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $studentExternalUpdateStmt = $conn->prepare("
        UPDATE students
        SET external_total_hours_remaining = ?, updated_at = NOW()
        WHERE id = ?
    ");

    foreach (array_keys($studentIds) as $studentId) {
        if (!$sumStmt) {
            break;
        }

        $sumStmt->bind_param('i', $studentId);
        $sumStmt->execute();
        $sumRow = $sumStmt->get_result()->fetch_assoc();
        $rendered = isset($sumRow['rendered']) ? (float)$sumRow['rendered'] : 0.0;

        if ($internshipLookupStmt && $internshipUpdateStmt) {
            $internshipLookupStmt->bind_param('i', $studentId);
            $internshipLookupStmt->execute();
            $internship = $internshipLookupStmt->get_result()->fetch_assoc();

            if ($internship) {
                $required = max(0, (int)($internship['required_hours'] ?? 0));
                $percentage = $required > 0 ? round(($rendered / $required) * 100, 2) : 0.0;
                if ($percentage > 100) {
                    $percentage = 100.0;
                }

                $internshipId = (int)$internship['id'];
                $internshipUpdateStmt->bind_param('ddi', $rendered, $percentage, $internshipId);
                $internshipUpdateStmt->execute();
            }
        }

        if ($studentLookupStmt) {
            $studentLookupStmt->bind_param('i', $studentId);
            $studentLookupStmt->execute();
            $student = $studentLookupStmt->get_result()->fetch_assoc();

            if ($student) {
                $track = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
                $roundedRendered = (int)floor($rendered);

                if ($track === 'external' && $studentExternalUpdateStmt) {
                    $externalTotal = max(0, (int)($student['external_total_hours'] ?? 0));
                    $externalRemaining = max(0, $externalTotal - $roundedRendered);
                    $studentExternalUpdateStmt->bind_param('ii', $externalRemaining, $studentId);
                    $studentExternalUpdateStmt->execute();
                } elseif ($studentInternalUpdateStmt) {
                    $internalTotal = max(0, (int)($student['internal_total_hours'] ?? 0));
                    $internalRemaining = max(0, $internalTotal - $roundedRendered);
                    $studentInternalUpdateStmt->bind_param('ii', $internalRemaining, $studentId);
                    $studentInternalUpdateStmt->execute();
                }
            }
        }
    }

    if ($sumStmt) {
        $sumStmt->close();
    }
    if ($internshipLookupStmt) {
        $internshipLookupStmt->close();
    }
    if ($internshipUpdateStmt) {
        $internshipUpdateStmt->close();
    }
    if ($studentLookupStmt) {
        $studentLookupStmt->close();
    }
    if ($studentInternalUpdateStmt) {
        $studentInternalUpdateStmt->close();
    }
    if ($studentExternalUpdateStmt) {
        $studentExternalUpdateStmt->close();
    }
}

function attendanceIsBiometricRecord(array $attendance): bool
{
    return strtolower(trim((string)($attendance['source'] ?? ''))) === 'biometric';
}

function getReviewBadge(array $attendance): string
{
    if (attendanceIsBiometricRecord($attendance)) {
        return '<span class="badge bg-soft-success text-success">Auto-Verified</span>';
    }

    return getStatusBadge(strtolower(trim((string)($attendance['status'] ?? 'pending'))));
}

function attendanceCanReview(array $attendance): bool
{
    return !attendanceIsBiometricRecord($attendance);
}

function attendanceActionMenuItems(array $attendance): string
{
    $attendanceId = (int)($attendance['id'] ?? 0);
    $studentId = (int)($attendance['student_id'] ?? 0);
    $items = [];

    if (attendanceCanReview($attendance)) {
        $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="approveAttendanceIndividual(' . $attendanceId . ')"><i class="feather feather-check-circle me-3"></i><span>Approve</span></a></li>';
        $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="rejectAttendanceIndividual(' . $attendanceId . ')"><i class="feather feather-x-circle me-3"></i><span>Reject</span></a></li>';
    } else {
        $items[] = '<li><span class="dropdown-item-text text-muted"><i class="feather feather-shield me-3"></i><span>Auto-verified by machine</span></span></li>';
    }

    $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="editAttendance(' . $attendanceId . ')"><i class="feather feather-edit-3 me-3"></i><span>Edit</span></a></li>';
    $items[] = '<li><a class="dropdown-item printBTN" href="javascript:void(0)" onclick="printAttendance(' . $attendanceId . ')"><i class="feather feather-printer me-3"></i><span>Print</span></a></li>';
    $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="sendNotification(' . $attendanceId . ')"><i class="feather feather-mail me-3"></i><span>Send Notification</span></a></li>';
    $items[] = '<li class="dropdown-divider"></li>';
    $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="deleteAttendanceIndividual(' . $attendanceId . ')"><i class="feather feather-trash-2 me-3"></i><span>Delete</span></a></li>';

    return '<div class="hstack gap-2 justify-content-end"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Details" onclick="viewDetails(' . $studentId . ')"><i class="feather feather-eye"></i></a><div class="dropdown"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21"><i class="feather feather-more-horizontal"></i></a><ul class="dropdown-menu dropdown-menu-end">' . implode('', $items) . '</ul></div></div>';
}

// Determine attendance status based on the section schedule.
function getAttendanceStatus(array $attendance) {
    return (string)(attendance_window_metrics($attendance)['arrival_status'] ?? 'absent');
}

function attendanceFilledSlotScore(array $attendance): int {
    $score = 0;
    foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
        $value = trim((string)($attendance[$column] ?? ''));
        if ($value !== '' && $value !== '00:00:00') {
            $score++;
        }
    }
    return $score;
}

function shouldPreferAttendanceRow(array $candidate, array $existing): bool {
    $candidateScore = attendanceFilledSlotScore($candidate);
    $existingScore = attendanceFilledSlotScore($existing);

    if ($candidateScore !== $existingScore) {
        return $candidateScore > $existingScore;
    }

    return (int)($candidate['id'] ?? 0) > (int)($existing['id'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Student Attendance</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="/BioTern/BioTern_unified/assets/images/favicon.ico?v=20260310">
    <script>
        (function () {
            try {
                var appSkin = localStorage.getItem('app-skin');
                var appSkinAlt = localStorage.getItem('app_skin');
                var theme = localStorage.getItem('theme');
                var legacy = localStorage.getItem('app-skin-dark');
                var raw = '';

                if (appSkin !== null && appSkin !== '') raw = appSkin;
                else if (appSkinAlt !== null && appSkinAlt !== '') raw = appSkinAlt;
                else if (theme !== null && theme !== '') raw = theme;
                else if (legacy !== null && legacy !== '') raw = legacy;

                if (typeof raw === 'string' && raw.indexOf('dark') !== -1) {
                    document.documentElement.classList.add('app-skin-dark');
                } else {
                    document.documentElement.classList.remove('app-skin-dark');
                }
            } catch (e) {}
        })();
    </script>
    <script src="assets/js/theme-preload-init.min.js"></script>
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/dataTables.bs5.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/datepicker.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/layout-shared-overrides.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datepicker-global.css">
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main.nxl-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        div.nxl-content {
            flex: 1;
            padding-top: 12px;
            padding-bottom: 18px;
        }
        footer.footer {
            margin-top: auto;
        }

        .nxl-content > .page-header,
        .nxl-content > .attendance-toolbar,
        .nxl-content > .collapse,
        .nxl-content > .main-content {
            margin-bottom: 14px !important;
        }

        .main-content {
            margin-top: 0 !important;
            padding: 0 !important;
        }

        .main-content > .row {
            --bs-gutter-x: 0;
            margin-left: 0;
            margin-right: 0;
        }

        .main-content > .row > [class*="col-"] {
            padding-left: 0;
            padding-right: 0;
        }
        
        /* Bulk toolbar adapts to theme */
        .bulk-toolbar {
            background-color: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .bulk-toolbar span {
            color: var(--bs-body-color);
        }
        /* dark theme override when app-skin-dark class present */
        html.app-skin-dark .bulk-toolbar {
            background-color: #0f172a;
            border-color: #1b2436;
        }
        html.app-skin-dark .bulk-toolbar span {
            color: #f0f0f0;
        }
        /* enlarge toolbar buttons and adjust spacing */
        .bulk-toolbar .btn {
            font-size: 0.95rem;
            padding: 0.45rem 0.75rem;
            font-weight: 600;
            border-radius: 0.35rem;
            min-width: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bulk-toolbar .btn i {
            margin-right: 0.25rem;
        }
        /* custom colours to match app theme closely */
        .bulk-toolbar .btn-success {
            background: #28a745;
            border-color: #28a745;
            color: #fff;
        }
        .bulk-toolbar .btn-success:hover {
            background: #218838;
            border-color: #1e7e34;
        }
        .bulk-toolbar .btn-warning {
            background: #fd7e14;
            border-color: #fd7e14;
            color: #fff;
        }
        .bulk-toolbar .btn-warning:hover {
            background: #e8590c;
            border-color: #d9480f;
        }
        .bulk-toolbar .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        .bulk-toolbar .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        .bulk-toolbar .btn-outline-secondary {
            background: transparent;
            border-color: var(--bs-border-color);
            color: var(--bs-body-color);
        }
        .bulk-toolbar .btn-outline-secondary:hover {
            background: var(--bs-border-color);
            color: var(--bs-body-color);
        }
        /* Dark mode select and Select2 styling */
        select.form-control,
        select.form-select,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            color: #333 !important;
            background-color: #ffffff !important;
        }
        
        /* Dark mode support for Select2 - using app-skin-dark class */
        html.app-skin-dark .select2-container--default .select2-selection--single,
        html.app-skin-dark .select2-container--default .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-container--default.select2-container--focus .select2-selection--single,
        html.app-skin-dark .select2-container--default.select2-container--focus .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #ffffff !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection__placeholder {
            color: #ffffff !important;
        }
        
        /* Dark mode dropdown menu */
        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-results {
            background-color: #0f172a !important;
        }
        
        html.app-skin-dark .select2-results__option {
            color: #ffffff !important;
            background-color: #0f172a !important;
        }
        
        html.app-skin-dark .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea !important;
            color: #ffffff !important;
        }
        
        html.app-skin-dark .select2-container--default {
            background-color: #0f172a !important;
        }
        
        html.app-skin-dark select.form-control,
        html.app-skin-dark select.form-select {
            color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark select.form-control option,
        html.app-skin-dark select.form-select option {
            color: #ffffff !important;
            background-color: #2d3748 !important;
        }

        .filter-form {
            display: grid !important;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.55rem;
            align-items: end;
            margin-left: 0 !important;
            margin-right: 0 !important;
            --bs-gutter-x: 0;
            --bs-gutter-y: 0;
        }

        .filter-form > [class*="col-"] {
            width: 100%;
            max-width: 100%;
            padding-right: 0;
            padding-left: 0;
            position: relative;
            overflow: visible;
        }

        .filter-form .form-control,
        .filter-form .form-select,
        .filter-form .select2-container .select2-selection--single {
            min-height: 42px;
        }

        .filter-form .form-select,
        .filter-form .select2-container--default .select2-selection--single {
            display: flex;
            align-items: center;
        }

        .filter-panel {
            border: 1px solid #dfe7f3;
            border-radius: 14px;
            padding: 0.85rem 0.9rem 0.3rem;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
            transition: padding-bottom 0.2s ease;
            overflow: visible;
        }
        .filter-panel .datepicker-dropdown {
            z-index: 30010 !important;
        }
        .filter-panel .datepicker-picker {
            position: relative;
            z-index: 30011 !important;
        }
        .filter-panel.attendance-datepicker-open {
            padding-bottom: 16rem;
        }
        .filter-panel.attendance-select-open {
            padding-bottom: 13rem;
        }

        .filter-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.55rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5edf7;
        }

        .filter-panel-head-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .page-header .page-header-title {
            border-right: 0 !important;
            padding-right: 0 !important;
            margin-right: 0 !important;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .page-header .page-header-title h5 {
            margin: 0;
        }

        .page-header .breadcrumb {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.15rem;
        }

        .filter-panel-label {
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #1e3a8a;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0;
        }

        .filter-panel-sub {
            font-size: 0.74rem;
            color: #64748b;
            margin: 0;
        }

        .filter-toggle-btn {
            border-color: #d5deed;
            color: #1e293b;
            background: #f8fbff;
        }

        .filter-toggle-btn:hover,
        .filter-toggle-btn:focus {
            background-color: #eef4ff;
            color: #0f172a;
            border-color: #b8c7e2;
        }

        .filter-form select.form-control {
            text-align: left;
            text-align-last: left;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            text-align: left;
            line-height: 40px;
            padding-left: 0.75rem;
            padding-right: 1.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        /* Filter border styling to match header filter look */
        .filter-form input.form-control,
        .filter-form select.form-control,
        .filter-form .select2-container--bootstrap-5 .select2-selection,
        .filter-form .select2-container--default .select2-selection--single {
            border-color: #4e6283 !important;
            border-width: 1px !important;
        }

        .filter-form input.form-control:focus,
        .filter-form select.form-control:focus,
        .filter-form .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .filter-form .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #7f9ecf !important;
            box-shadow: 0 0 0 0.15rem rgba(127, 158, 207, 0.22) !important;
        }

        /* Light mode: keep Select2 text readable on white backgrounds */
        .filter-form .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .filter-form .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered,
        .filter-form .select2-container--bootstrap-5 .select2-selection__placeholder {
            color: #283c50 !important;
            -webkit-text-fill-color: #283c50 !important;
            opacity: 1 !important;
        }

        .filter-form .select2-container--bootstrap-5 .select2-dropdown .select2-results__option {
            color: #283c50 !important;
        }

        /* Dark mode: use white text for Select2 Bootstrap-5 theme */
        html.app-skin-dark .filter-form .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        html.app-skin-dark .filter-form .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered,
        html.app-skin-dark .filter-form .select2-container--bootstrap-5 .select2-selection__placeholder {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
        }

        html.app-skin-dark .filter-form .select2-container--bootstrap-5 .select2-dropdown .select2-results__option {
            color: #ffffff !important;
        }

        html.app-skin-dark .filter-panel {
            border-color: #243246;
            background: linear-gradient(180deg, #0f172a 0%, #111d33 100%);
            box-shadow: 0 10px 24px rgba(2, 8, 23, 0.5);
        }

        html.app-skin-dark .filter-panel-head {
            border-bottom-color: #243246;
        }

        html.app-skin-dark .filter-panel-label {
            color: #dbeafe;
        }

        html.app-skin-dark .filter-panel-sub {
            color: #94a3b8;
        }

        html.app-skin-dark .filter-toggle-btn {
            background-color: #0f172a;
            color: #e2e8f0;
            border-color: #334155;
        }

        html.app-skin-dark .filter-toggle-btn:hover,
        html.app-skin-dark .filter-toggle-btn:focus {
            background-color: #1e293b;
            color: #f8fafc;
            border-color: #475569;
        }

        .attendance-toolbar {
            margin-bottom: 12px;
            padding: 12px 14px;
            border: 1px solid #e4ebf7;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
            display: block;
        }

        .attendance-toolbar-left {
            min-width: 0;
            max-width: none;
        }

        .attendance-toolbar .page-subtitle {
            font-size: 12px;
            color: #6c7a92;
            line-height: 1.4;
            margin: 0;
            max-width: 72ch;
        }

        .attendance-toolbar-right {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            align-content: flex-end;
            flex-wrap: wrap;
            row-gap: 6px;
            column-gap: 8px !important;
            width: 100%;
            margin-top: 10px;
        }

        .attendance-toolbar-right .btn,
        .attendance-toolbar-right a.btn,
        .attendance-toolbar-right .dropdown {
            flex: 0 0 auto;
        }

        .attendance-toolbar-right .btn,
        .attendance-toolbar-right a.btn {
            min-height: 32px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .01em;
            padding: 0.32rem 0.62rem;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        html.app-skin-dark .attendance-toolbar {
            border-color: #253252;
            background: #111a2e;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        }

        @media (max-width: 1599.98px) {
            .filter-form {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 1399.98px) {
            .filter-form {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .page-header {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .page-header-right {
                width: 100%;
                margin-left: 0 !important;
            }

            .page-header-right-items {
                width: 100%;
            }

            .page-header-right-items-wrapper {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .page-header-right-items-wrapper > .btn,
            .page-header-right-items-wrapper > .dropdown {
                flex: 0 0 auto;
            }
        }

        @media (max-width: 1199.98px) {
            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .attendance-toolbar-right {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 8px !important;
                justify-content: stretch;
            }
            .attendance-toolbar-right .btn,
            .attendance-toolbar-right a.btn,
            .attendance-toolbar-right .dropdown,
            .attendance-toolbar-right .dropdown > .btn {
                width: 100%;
            }
            .filter-panel.attendance-datepicker-open {
                padding-bottom: 0.4rem;
            }
            .filter-panel.attendance-select-open {
                padding-bottom: 0.4rem;
            }
        }

        @media (max-width: 767.98px) {
            .filter-form {
                grid-template-columns: 1fr;
            }

            .attendance-toolbar-right {
                grid-template-columns: 1fr;
            }

            .filter-panel {
                padding: 0.85rem 0.75rem 0.25rem;
            }

            .filter-panel-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-panel-head-actions {
                width: 100%;
            }

            .filter-panel-head-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 1024px) {
            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-panel-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-panel-head-actions {
                width: 100%;
            }

            .filter-panel-head-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 1366px) {
            .nxl-navigation .nxl-navbar .nxl-item.nxl-hasmenu {
                position: relative;
            }

            .nxl-navigation .nxl-navbar .nxl-item.nxl-hasmenu > .nxl-submenu {
                position: static !important;
                left: auto !important;
                right: auto !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border: 0 !important;
                margin-top: 0.25rem;
                background: transparent !important;
            }

            .nxl-navigation .nxl-navbar .nxl-item.nxl-hasmenu > .nxl-submenu .nxl-link {
                white-space: normal;
                overflow-wrap: anywhere;
            }
        }

        @media (max-width: 575.98px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php
require_once dirname(__DIR__) . '/config/db.php';
include_once dirname(__DIR__) . '/includes/navigation.php'; ?>
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <!--! [Start] Header Left !-->
            <div class="header-left d-flex align-items-center gap-4">
                <!--! [Start] nxl-head-mobile-toggler !-->
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <!--! [Start] nxl-head-mobile-toggler !-->
                <!--! [Start] nxl-navigation-toggle !-->
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" class="hidden-inline-toggle">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
            <!--! [End] Header Left !-->
            <!--! [Start] Header Right !-->
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item nxl-header-search">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                            <div class="input-group search-form">
                                <span class="input-group-text">
                                    <i class="feather-search fs-6 text-muted"></i>
                                </span>
                                <input type="text" id="headerSearchInput" name="header_search" class="form-control search-input-field" placeholder="Search....">
                                <span class="input-group-text">
                                    <button type="button" class="btn-close"></button>
                                </span>
                            </div>
                            <div class="dropdown-divider mt-0"></div>
                            <!--! search coding for database !-->
                        </div>
                    </div>
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" data-action="toggle-fullscreen" aria-label="Toggle fullscreen">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <span class="badge bg-danger nxl-h-badge">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="d-flex justify-content-between align-items-center notifications-head">
                                <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                                <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto" data-bs-toggle="tooltip" title="Make as Read">
                                    <i class="feather-check"></i>
                                    <span>Make as Read</span>
                                </a>
                            </div>
                            <div class="notifications-item">
                                <img src="assets/images/avatar/2.png" alt="" class="rounded me-3 border">
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line"> <span class="fw-semibold text-dark">Malanie Hanvey</span> We should talk about that at lunch!<[...]
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed">2 minutes ago</div>
                                        <div class="d-flex align-items-center float-end gap-2">
                                            <a href="javascript:void(0);" class="d-block wd-8 ht-8 rounded-circle bg-gray-300" data-bs-toggle="tooltip" title="Make as Read"></a>
                                            <a href="javascript:void(0);" class="text-danger" data-bs-toggle="tooltip" title="Remove">
                                                <i class="feather-x fs-12"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center notifications-footer">
                                <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">All Notifications</a>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((isset($_SESSION['profile_picture']) && trim((string)$_SESSION['profile_picture']) !== '' ? ltrim(str_replace('\\', '/', trim((string)$_SESSION['profile_picture'])), '/') : ('assets/images/avatar/' . (((int)($_SESSION['user_id'] ?? 0) % 5) + 1) . '.png')), ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((isset($_SESSION['profile_picture']) && trim((string)$_SESSION['profile_picture']) !== '' ? ltrim(str_replace('\\', '/', trim((string)$_SESSION['profile_picture'])), '/') : ('assets/images/avatar/' . (((int)($_SESSION['user_id'] ?? 0) % 5) + 1) . '.png')), ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                    <div>
                                        <h6 class="text-dark mb-0"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'), ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <span class="fs-12 fw-medium text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($_SESSION['email'] ?? 'admin@biotern.local'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="dropdown">
                                    <span class="hstack">
                                        <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                        <span>Active</span>
                                    </span>
                                    <i class="feather-chevron-right ms-auto me-0"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="wd-10 ht-10 border border-2 border-gray-1 bg-warning rounded-circle me-2"></i>
                                            <span>Always</span>
                                        </span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                            <span>Active</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="users.php" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <a href="analytics.php" class="dropdown-item">
                                <i class="feather-activity"></i>
                                <span>Activity Feed</span>
                            </a>
                            <a href="analytics.php" class="dropdown-item">
                                <i class="feather-bell"></i>
                                <span>Notifications</span>
                            </a>
                            <a href="settings-general.php" class="dropdown-item">
                                <i class="feather-settings"></i>
                                <span>Account Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="./auth-login-cover.php?logout=1" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!--! [End] Header Right !-->
        </div>
    </header>
    <!--! ================================================================ !-->
    <!--! [End] Header !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Attendance DTR</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                            <li class="breadcrumb-item">Attendance DTR</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="attendance-toolbar">
                <div class="attendance-toolbar-left">
                    <div class="page-subtitle">Review student attendance records, machine sync activity, and approval status in one place.</div>
                </div>
                <div class="attendance-toolbar-right">
                    <a href="javascript:void(0);" class="btn btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseAttendanceStats">
                        <i class="feather-bar-chart"></i>
                    </a>
                    <button type="button" class="btn filter-toggle-btn" data-bs-toggle="collapse" data-bs-target="#attendanceFilterCollapse" aria-expanded="false" aria-controls="attendanceFilterCollapse">
                        <i class="feather-filter me-2"></i>
                        <span>Filters</span>
                    </button>
                    <a href="legacy_router.php?file=biometric-machine.php" class="btn btn-light-brand">
                        <i class="feather-cpu me-2"></i>
                        <span>Machine Manager</span>
                    </a>
                    <button type="button" class="btn btn-primary" id="manualSyncMachineButton">
                        <i class="feather-refresh-cw me-2"></i>
                        <span>Sync Machine</span>
                    </button>
                    <div class="dropdown">
                        <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                            <i class="feather-filter"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="period" data-value="today">
                                <i class="feather-calendar me-3"></i>
                                <span>Today</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="period" data-value="week">
                                <i class="feather-calendar me-3"></i>
                                <span>This Week</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="period" data-value="month">
                                <i class="feather-calendar me-3"></i>
                                <span>This Month</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="approved">
                                <i class="feather-check-circle me-3"></i>
                                <span>Approved</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="pending">
                                <i class="feather-clock me-3"></i>
                                <span>Pending</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="rejected">
                                <i class="feather-x-circle me-3"></i>
                                <span>Rejected</span>
                            </a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                            <i class="feather-paperclip"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="bi bi-filetype-pdf me-3"></i>
                                <span>PDF</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="bi bi-filetype-csv me-3"></i>
                                <span>CSV</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="bi bi-filetype-xml me-3"></i>
                                <span>XML</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="bi bi-printer me-3"></i>
                                <span>Print</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $attendance_sync_flash = $_SESSION['attendance_sync_flash'] ?? null;
            unset($_SESSION['attendance_sync_flash']);
            if (is_array($attendance_sync_flash) && !empty($attendance_sync_flash['message'])):
            ?>
                <div class="alert alert-<?php echo htmlspecialchars((string)($attendance_sync_flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show mx-3" role="alert" id="attendanceSyncAlert">
                    <?php echo nl2br(htmlspecialchars((string)$attendance_sync_flash['message'], ENT_QUOTES, 'UTF-8')); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($missingScheduleAttendances)): ?>
                <?php
                $missingScheduleLabels = [];
                foreach ($missingScheduleAttendances as $attendance) {
                    $studentLabel = trim((string)($attendance['first_name'] ?? '') . ' ' . (string)($attendance['last_name'] ?? ''));
                    $sectionLabel = trim((string)($attendance['section_code'] ?? ''));
                    if ($sectionLabel === '') {
                        $sectionLabel = trim((string)($attendance['section_name'] ?? ''));
                    }
                    $missingScheduleLabels[] = trim($studentLabel . ($sectionLabel !== '' ? (' [' . $sectionLabel . ']') : ''));
                }
                $missingScheduleLabels = array_values(array_unique(array_filter($missingScheduleLabels)));
                ?>
                <div class="alert alert-warning alert-dismissible fade show mx-3" role="alert">
                    Section schedule is missing for some biometric rows on this page. Review the section schedule before trusting their slot placement.
                    <?php if ($missingScheduleLabels !== []): ?>
                        <div class="mt-2 small"><?php echo htmlspecialchars(implode(', ', array_slice($missingScheduleLabels, 0, 8)), ENT_QUOTES, 'UTF-8'); ?><?php echo count($missingScheduleLabels) > 8 ? '...' : ''; ?></div>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div id="attendanceSyncAlertHost" class="mx-3"></div>

            <!-- Filters -->
            <div class="collapse" id="attendanceFilterCollapse">
                <div class="row mb-3 px-3">
                    <div class="col-12">
                        <div class="filter-panel">
                            <div class="filter-panel-head">
                                <div>
                                    <div class="filter-panel-label">
                                        <i class="feather-sliders"></i>
                                        <span>Filter Attendance</span>
                                    </div>
                                    <p class="filter-panel-sub">Narrow down results by school year, date, course, section, supervisor, and coordinator.</p>
                                </div>
                                <div class="filter-panel-head-actions">
                                    <a href="attendance.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                                </div>
                            </div>
                            <form method="GET" class="filter-form row g-2 align-items-end" id="attendanceFilterForm">
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-school-year">School Year</label>
                            <select id="filter-school-year" name="school_year" class="form-control">
                                <option value="">-- All School Years --</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($school_year_options as $school_year): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $filter_school_year === $school_year ? 'selected' : ''; ?>><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-date">Date</label>
                            <input id="filter-date" type="date" name="date" class="form-control" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-course">Course</label>
                            <select id="filter-course" name="course_id" class="form-control">
                                <option value="0">-- All Courses --</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($courses as $course): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $course['id']; ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($course['name']); ?></option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-department">Department</label>
                            <select id="filter-department" name="department_id" class="form-control">
                                <option value="0">-- All Departments --</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departments as $dept): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $dept['id']; ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($dept['name']); ?></option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-section">Section</label>
                            <select id="filter-section" name="section_id" class="form-control">
                                <option value="0">-- All Sections --</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($sections as $section): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$section['id']; ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $filter_section == $section['id'] ? 'selected' : ''; ?>><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($section['section_label']); ?></option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-supervisor">Supervisor</label>
                            <select id="filter-supervisor" name="supervisor" class="form-control">
                                <option value="">-- Any Supervisor --</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($supervisors as $sup): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($sup); ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($sup); ?></option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-coordinator">Coordinator</label>
                            <select id="filter-coordinator" name="coordinator" class="form-control">
                                <option value="">-- Any Coordinator --</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($coordinators as $coor): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($coor); ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($coor); ?></option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!--! attendance statistics database !-->
            <div id="collapseAttendanceStats" class="accordion-collapse collapse page-header-collapse">
                <div class="accordion-body pb-2">
                    <div class="row">
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-check-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Total Approved</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['approved_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-success text-success">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['total_count'] > 0 ? round(($stats['approved_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-clock"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Pending Approval</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['pending_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-warning text-warning">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['total_count'] > 0 ? round(($stats['pending_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-x-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Rejected</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['rejected_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-danger text-danger">
                                            <i class="feather-arrow-down fs-10 me-1"></i>
                                            <span><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['total_count'] > 0 ? round(($stats['rejected_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-alert-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Total Records</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['total_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-info text-info">
                                            <i class="feather-info fs-10 me-1"></i>
                                            <span>100%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--! end of attendance statistics database !-->

            <!-- Bulk Actions Toolbar -->
            <div class="row mb-2 px-3" id="bulkActionsToolbar" style="display: none;">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bulk-toolbar">
                        <span class="fs-6" style="font-weight: 600;">
                            <i class="feather feather-check me-1" style="font-size: 16px;"></i>
                            <strong id="selectedCount" style="font-size:1.1rem;">0</strong> record(s) selected
                        </span>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-success py-1 px-2" onclick="performBulkAction('approve')" title="Approve selected">
                                <i class="feather feather-check fs-8 me-1"></i> Approve
                            </button>
                            <button type="button" class="btn btn-sm btn-warning py-1 px-2" onclick="performBulkAction('reject')" title="Reject selected">
                                <i class="feather feather-x fs-8 me-1"></i> Reject
                            </button>
                            <button type="button" class="btn btn-sm btn-danger py-1 px-2" onclick="performBulkAction('delete')" title="Delete selected">
                                <i class="feather feather-trash-2 fs-8 me-1"></i> Delete
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="clearSelection()">
                                <i class="feather feather-x fs-8 me-1"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">

                        <div class="card stretch stretch-full attendance-table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="attendanceList">
                                        <thead>
                                            <tr>
                                                <th class="wd-30">
                                                    <div class="btn-group mb-1">
                                                        <div class="custom-control custom-checkbox ms-1">
                                                            <input type="checkbox" class="custom-control-input" id="checkAllAttendance">
                                                            <label class="custom-control-label" for="checkAllAttendance"></label>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th>Student Name</th>
                                                <th>Attendance Date</th>
                                                <th>Morning In</th>
                                                <th>Morning Out</th>
                                                <th>Afternoon In</th>
                                                <th>Afternoon Out</th>
                                                <th>Total<br>Hours</th>
                                                <th>Status</th>
                                                <th>Source</th>
                                                <th>Review</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if (!empty($attendances)): ?>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($attendances as $index => $attendance): ?>
                                                    <tr class="single-item">
                                                        <td>
                                                            <?php if (attendanceCanReview($attendance)): ?>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$attendance['id']; ?>_<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$index; ?>" data-attendance-id="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$attendance['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$attendance['id']; ?>_<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$index; ?>"></label>
                                                                </div>
                                                            </div>
                                                            <?php else: ?>
                                                            <span class="text-muted fs-12" title="Biometric records are auto-verified">Auto</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="students-view.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance['student_id']; ?>" class="hstack gap-3">
                                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
$pp = $attendance['profile_picture'] ?? '';
                                                                $pp_url = resolve_attendance_profile_image_url((string)$pp);
                                                                if ($pp_url !== null) {
                                                                    echo '<div class="avatar-image avatar-md"><img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid"></div>';
                                                                } else {
                                                                    echo '<div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1)) . '</div></div>';
                                                                }
                                                                ?>
                                                                <div>
                                                                    <span class="text-truncate-1-line fw-bold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($attendance['first_name'] ?? 'N/A') . ' ' . ($attendance['last_name'] ?? 'N/A'); ?></span>
                                                                    <span class="fs-12 text-muted d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance['student_number'] ?? 'N/A'; ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td><span class="badge bg-soft-primary text-primary"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo date('Y-m-d', strtotime($attendance['attendance_date'])); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatTime($attendance['morning_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatTime($attendance['morning_time_out']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatTime($attendance['afternoon_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatTime($attendance['afternoon_time_out']); ?></span></td>
                                                        <td>
                                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
echo attendance_hours_cell_html($attendance); ?>
                                                        </td>
                                                        <td>
                                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
echo attendance_status_cell_html($attendance);
                                                            ?>
                                                        </td>
                                                        <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo getSourceBadge($attendance['source'] ?? 'manual', $attendance); ?></td>
                                                        <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo getReviewBadge($attendance); ?></td>
                                                        <td>
                                                            <?php echo attendanceActionMenuItems($attendance); ?>
                                                        </td>
                                                    </tr>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Main Content ] end -->
        </div>
        <!-- [ Footer ] start -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p class="footer-meta fs-12 mb-0"><span>By: <a href="javascript:void(0);">ACT 2A</a></span> <span>Distributed by: <a href="javascript:void(0);">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
        <!-- [ Footer ] end -->
    </main>

    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->

    <!--! ================================================================ !-->
    <!--! BEGIN: Downloading Toast !-->
    <!--! ================================================================ !-->
    <div class="position-fixed" style="right: 5px; bottom: 5px; z-index: 999999">
        <div id="toast" class="toast bg-black hide" data-bs-delay="3000" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header px-3 bg-transparent d-flex align-items-center justify-content-between border-bottom border-light border-opacity-10">
                <div class="text-white mb-0 mr-auto">Downloading...</div>
                <a href="javascript:void(0)" class="ms-2 mb-1 close fw-normal" data-bs-dismiss="toast" aria-label="Close">
                    <span class="text-white">&times;</span>
                </a>
            </div>
            <div class="toast-body p-3 text-white">
                <h6 class="fs-13 text-white">Attendance.zip</h6>
                <span class="text-light fs-11">4.2mb of 5.5mb</span>
            </div>
            <div class="toast-footer p-3 pt-0 border-top border-light border-opacity-10">
                <div class="progress mt-3" style="height: 5px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated w-75 bg-dark" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <style>
        /* Keep header menus above content */
        .page-header .dropdown-menu,
        .page-header-right .dropdown-menu {
            z-index: 99999 !important;
        }
        .page-header,
        .page-header-right {
            overflow: visible !important;
        }

        /* Attendance actions menu should not be clipped by wrappers */
        .attendance-table-card,
        .attendance-table-card .card-body,
        .attendance-table-card .table-responsive,
        .attendance-table-card .table,
        .attendance-table-card .table-hover tbody tr,
        .attendance-table-card td {
            overflow: visible !important;
        }

        .attendance-table-card .table-responsive {
            overflow-x: hidden !important;
            overflow-y: visible !important;
        }

        #attendanceList {
            width: 100%;
            min-width: 100%;
            table-layout: fixed;
        }

        #attendanceList th,
        #attendanceList td {
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        #attendanceList th {
            white-space: normal;
            word-break: normal;
            overflow-wrap: normal;
            line-height: 1.15;
            text-overflow: clip;
            text-align: center;
            vertical-align: middle;
            overflow: visible;
            padding-right: 1.15rem !important;
        }

        #attendanceList th.text-end {
            text-align: center !important;
        }

        #attendanceList thead th.sorting,
        #attendanceList thead th.sorting_asc,
        #attendanceList thead th.sorting_desc {
            position: relative;
            padding-right: 1.35rem !important;
        }

        #attendanceList th:first-child,
        #attendanceList td:first-child {
            width: 3%;
            white-space: nowrap;
        }

        #attendanceList th:nth-child(2),
        #attendanceList td:nth-child(2) {
            width: 15%;
        }

        #attendanceList th:nth-child(3),
        #attendanceList td:nth-child(3) {
            width: 11%;
        }

        #attendanceList th:nth-child(4),
        #attendanceList td:nth-child(4),
        #attendanceList th:nth-child(5),
        #attendanceList td:nth-child(5),
        #attendanceList th:nth-child(6),
        #attendanceList td:nth-child(6) {
            width: 7%;
        }

        #attendanceList th:nth-child(7),
        #attendanceList td:nth-child(7) {
            width: 9%;
        }

        #attendanceList th:nth-child(8),
        #attendanceList td:nth-child(8),
        #attendanceList th:nth-child(9),
        #attendanceList td:nth-child(9),
        #attendanceList th:nth-child(10),
        #attendanceList td:nth-child(10),
        #attendanceList th:nth-child(11),
        #attendanceList td:nth-child(11) {
            width: 6.5%;
            white-space: nowrap;
        }

        #attendanceList th:nth-child(8) {
            padding-right: 1.7rem !important;
        }

        #attendanceList th:last-child,
        #attendanceList td:last-child {
            width: 9%;
            white-space: nowrap;
        }

        @media (min-width: 1025px) and (max-width: 1399.98px) {
            #attendanceList th,
            #attendanceList td {
                font-size: 10.5px;
                padding: 0.48rem 0.32rem;
            }
        }

        @media (min-width: 1025px) and (max-width: 1399.98px) {
            html:not(.minimenu) #attendanceList th,
            html:not(.minimenu) #attendanceList td,
            html.minimenu .nxl-navigation:hover ~ .nxl-container #attendanceList th,
            html.minimenu .nxl-navigation:hover ~ .nxl-container #attendanceList td {
                font-size: 10px;
                padding: 0.42rem 0.24rem;
            }
        }

        #attendanceList td[colspan] {
            text-align: center;
            white-space: normal;
        }

        .attendance-table-card .dropdown {
            position: relative !important;
        }

        .attendance-table-card .dropdown-menu {
            z-index: 20000 !important;
        }
    </style>

    <script src="assets/vendors/js/dataTables.min.js"></script>
    <script src="assets/vendors/js/dataTables.bs5.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <script src="assets/vendors/js/datepicker.min.js"></script>
    <script src="assets/js/global-datepicker-init.js"></script>
    <!--! END: Vendors JS !-->
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var dateInput = document.getElementById('filter-date');
                var filterPanel = document.querySelector('.filter-panel');
                if (!dateInput || !filterPanel) {
                    return;
                }

                function openPanelSpace() {
                    filterPanel.classList.add('attendance-datepicker-open');
                }

                function closePanelSpace() {
                    filterPanel.classList.remove('attendance-datepicker-open');
                }

                function openSelectSpace() {
                    filterPanel.classList.add('attendance-select-open');
                }

                function closeSelectSpace() {
                    filterPanel.classList.remove('attendance-select-open');
                }

                dateInput.addEventListener('focus', openPanelSpace);
                dateInput.addEventListener('click', openPanelSpace);
                dateInput.addEventListener('change', closePanelSpace);
                dateInput.addEventListener('blur', function () {
                    setTimeout(closePanelSpace, 200);
                });

                document.addEventListener('click', function (event) {
                    if (event.target === dateInput) {
                        return;
                    }
                    if (event.target.closest('.datepicker-dropdown')) {
                        return;
                    }
                    closePanelSpace();
                });

                if (window.jQuery) {
                    var $filterSelects = jQuery('#filter-course, #filter-department, #filter-section, #filter-school-year, #filter-supervisor, #filter-coordinator');
                    if ($filterSelects.length) {
                        $filterSelects.on('select2:open', openSelectSpace);
                        $filterSelects.on('select2:close select2:select select2:clear', closeSelectSpace);
                    }
                }
            });
        })();
    </script>
    
    <style>
        .attendance-table-card .dropdown-menu {
            margin-top: 5px;
        }

        /* Keep the DataTable footer controls aligned without forcing huge fake spacing */
        .attendanceList_wrapper .row:last-child {
            margin-top: 0;
            padding-bottom: 6px;
        }
    </style>
    
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/global-ui-helpers.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/customers-init.min.js"></script>
    <!--! END: Apps Init !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>

    <script>
        function initAttendanceDataTable() {
            return $('#attendanceList').DataTable({
                "pageLength": 10,
                "ordering": true,
                "searching": true,
                "bLengthChange": true,
                "info": true,
                "paging": true,
                "autoWidth": false,
                "order": [[2, "desc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [0, 11] }
                ],
                "language": {
                    "emptyTable": "No attendance records found"
                }
            });
        }

        var biometricAutoSyncInFlight = false;
        var biometricAutoSyncIntervalMs = 60000;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showAttendanceSyncAlert(message, type) {
            var host = document.getElementById('attendanceSyncAlertHost');
            if (!host) {
                return;
            }

            host.innerHTML = [
                '<div class="alert alert-', escapeHtml(type || 'success'), ' alert-dismissible fade show" role="alert">',
                escapeHtml(message || ''),
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
                '</div>'
            ].join('');
        }

        function setManualSyncButtonBusy(isBusy) {
            var button = document.getElementById('manualSyncMachineButton');
            if (!button) {
                return;
            }

            button.disabled = !!isBusy;
            if (isBusy) {
                button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
                button.innerHTML = '<i class="feather-loader me-2"></i><span>Syncing...</span>';
            } else if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }

        function runBiometricAutoSync(options) {
            options = options || {};
            var manual = !!options.manual;
            var showToastOnError = !!options.showToastOnError;
            if (biometricAutoSyncInFlight || document.hidden) {
                return;
            }

            biometricAutoSyncInFlight = true;
            if (manual) {
                setManualSyncButtonBusy(true);
            }
            $.ajax({
                url: 'legacy_router.php?file=biometric_machine_sync.php&format=json',
                type: 'GET',
                dataType: 'json'
            }).done(function(response) {
                if (response && response.success) {
                    refreshAttendanceTable();
                    if (manual) {
                        showAttendanceSyncAlert('Machine sync complete.', 'success');
                    }
                } else if (showToastOnError) {
                    showToast((response && response.message) ? response.message : 'Machine sync failed.', 'danger');
                    if (manual) {
                        showAttendanceSyncAlert((response && response.message) ? response.message : 'Machine sync failed.', 'danger');
                    }
                }
            }).fail(function(xhr) {
                if (showToastOnError) {
                    showToast(manual ? 'Machine sync failed.' : 'Automatic machine sync failed.', 'danger');
                }
                if (manual) {
                    showAttendanceSyncAlert('Machine sync failed.', 'danger');
                }
            }).always(function() {
                biometricAutoSyncInFlight = false;
                if (manual) {
                    setManualSyncButtonBusy(false);
                }
            });
        }

        // Initialize DataTable
        $(document).ready(function() {
            var attendanceTableInstance = initAttendanceDataTable();

            function refreshAttendanceTableLayout() {
                try {
                    if ($.fn.DataTable.isDataTable('#attendanceList')) {
                        $('#attendanceList').DataTable().columns.adjust().draw(false);
                    }
                } catch (error) {
                    // Keep quiet; redraw fallback below still helps.
                }

                var wrapper = document.querySelector('.attendance-table-card .table-responsive');
                if (wrapper) {
                    wrapper.scrollLeft = 0;
                }
            }

            function queueAttendanceTableRefresh() {
                [0, 160, 320, 480].forEach(function(delay) {
                    window.setTimeout(refreshAttendanceTableLayout, delay);
                });
            }

            ['menu-mini-button', 'menu-expend-button', 'mobile-collapse'].forEach(function(id) {
                var trigger = document.getElementById(id);
                if (trigger) {
                    trigger.addEventListener('click', queueAttendanceTableRefresh);
                }
            });

            window.addEventListener('resize', queueAttendanceTableRefresh);

            var attendanceNav = document.querySelector('.nxl-navigation');
            if (attendanceNav) {
                attendanceNav.addEventListener('mouseenter', function() {
                    if (document.documentElement.classList.contains('minimenu')) {
                        queueAttendanceTableRefresh();
                    }
                });
                attendanceNav.addEventListener('mouseleave', function() {
                    if (document.documentElement.classList.contains('minimenu')) {
                        queueAttendanceTableRefresh();
                    }
                });
                attendanceNav.addEventListener('transitionend', function() {
                    queueAttendanceTableRefresh();
                });
            }

            if (window.MutationObserver && document.documentElement) {
                var attendanceSidebarObserver = new MutationObserver(function(mutations) {
                    var shouldRefresh = mutations.some(function(mutation) {
                        return mutation.type === 'attributes' && mutation.attributeName === 'class';
                    });
                    if (shouldRefresh) {
                        queueAttendanceTableRefresh();
                    }
                });
                attendanceSidebarObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            }

            queueAttendanceTableRefresh();

            var $attendanceFilterForm = $('#attendanceFilterForm');
            ['#filter-course', '#filter-department', '#filter-section', '#filter-school-year'].forEach(function (selector) {
                if ($(selector).length) {
                    var $el = $(selector);
                    var $dropdownParent = $el.closest('[class*="col-"]');
                    $el.select2({
                        width: '100%',
                        allowClear: false,
                        dropdownAutoWidth: false,
                        minimumResultsForSearch: Infinity,
                        dropdownParent: $dropdownParent.length ? $dropdownParent : $attendanceFilterForm
                    });
                }
            });
            ['#filter-supervisor', '#filter-coordinator'].forEach(function (selector) {
                if ($(selector).length) {
                    var $el = $(selector);
                    var $dropdownParent = $el.closest('[class*="col-"]');
                    $el.select2({
                        width: '100%',
                        allowClear: false,
                        dropdownAutoWidth: false,
                        dropdownParent: $dropdownParent.length ? $dropdownParent : $attendanceFilterForm
                    });
                }
            });

            // Auto-submit attendance filters on change.
            var isSubmittingFilters = false;
            function submitAttendanceFilters() {
                if (isSubmittingFilters) return;
                var form = document.getElementById('attendanceFilterForm');
                if (!form) return;
                isSubmittingFilters = true;
                form.submit();
            }

            $('#attendanceFilterForm').on('change', 'input[name="date"], select[name="school_year"], select[name="course_id"], select[name="department_id"], select[name="section_id"], select[name="supervisor"], select[name="coordinator"]', function() {
                submitAttendanceFilters();
            });

            $('#filter-course, #filter-department, #filter-section, #filter-school-year, #filter-supervisor, #filter-coordinator').on('select2:select select2:clear', function() {
                submitAttendanceFilters();
            });

            // Header quick-filters (Today / This Week / This Month / status)
            // Use delegated binding so dynamically shown menu items are caught
            $(document).on('click', '.attendance-filter', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                var value = $(this).data('value');
                var params = new URLSearchParams(window.location.search);

                // Remove pagination or unrelated params
                params.delete('page');

                if (type === 'period') {
                    var today = new Date();
                    var yyyy = today.getFullYear();
                    var mm = String(today.getMonth() + 1).padStart(2, '0');
                    var dd = String(today.getDate()).padStart(2, '0');
                    if (value === 'today') {
                        params.set('date', yyyy + '-' + mm + '-' + dd);
                        params.delete('start_date');
                        params.delete('end_date');
                        params.delete('status');
                    } else if (value === 'week') {
                        // start of week (Monday)
                        var curr = new Date();
                        var first = new Date(curr.setDate(curr.getDate() - (curr.getDay() || 7) + 1));
                        var last = new Date();
                        var s_yyyy = first.getFullYear();
                        var s_mm = String(first.getMonth() + 1).padStart(2, '0');
                        var s_dd = String(first.getDate()).padStart(2, '0');
                        var e_yyyy = last.getFullYear();
                        var e_mm = String(last.getMonth() + 1).padStart(2, '0');
                        var e_dd = String(last.getDate()).padStart(2, '0');
                        params.set('start_date', s_yyyy + '-' + s_mm + '-' + s_dd);
                        params.set('end_date', e_yyyy + '-' + e_mm + '-' + e_dd);
                        params.delete('date');
                        params.delete('status');
                    } else if (value === 'month') {
                        var now = new Date();
                        var s_yyyy = now.getFullYear();
                        var s_mm = String(now.getMonth() + 1).padStart(2, '0');
                        params.set('start_date', s_yyyy + '-' + s_mm + '-01');
                        // last day of month
                        var lastDay = new Date(now.getFullYear(), now.getMonth()+1, 0);
                        var e_yyyy = lastDay.getFullYear();
                        var e_mm = String(lastDay.getMonth() + 1).padStart(2, '0');
                        var e_dd = String(lastDay.getDate()).padStart(2, '0');
                        params.set('end_date', e_yyyy + '-' + e_mm + '-' + e_dd);
                        params.delete('date');
                        params.delete('status');
                    }
                } else if (type === 'status') {
                    params.set('status', value);
                    // clear specific date range so status can apply broadly
                    params.delete('date');
                    params.delete('start_date');
                    params.delete('end_date');
                }

                // navigate via AJAX: fetch rows and replace table body without full reload
                var qs = params.toString();
                var fetchUrl = window.location.pathname + (qs ? ('?' + qs) : '') + (qs ? '&ajax=1' : '?ajax=1');
                // request rows
                $.get(fetchUrl, function(html) {
                    // destroy and reinit DataTable while replacing rows
                    if ($.fn.DataTable.isDataTable('#attendanceList')) {
                        $('#attendanceList').DataTable().clear().destroy();
                    }
                    $('#attendanceList tbody').html(html);
                    // re-init DataTable
                    initAttendanceDataTable();
                    // re-init tooltips
                    $('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
                    // re-init dropdowns
                    var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                    dropdownElements.forEach(function(element) {
                        new bootstrap.Dropdown(element);
                    });
                }).fail(function() {
                    // fallback to full reload on error
                    window.location.href = window.location.pathname + (qs ? ('?' + qs) : '');
                });
            });

            // Handle Check All
            $('#checkAllAttendance').on('change', function() {
                $('.checkbox').prop('checked', this.checked);
                updateBulkActionsToolbar();
            });

            // Handle individual checkbox changes
            $(document).on('change', '.checkbox', function() {
                updateBulkActionsToolbar();
            });

            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });

            setTimeout(function() {
                runBiometricAutoSync({ showToastOnError: false });
            }, 1500);

            setInterval(function() {
                runBiometricAutoSync({ showToastOnError: false });
            }, biometricAutoSyncIntervalMs);

            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    runBiometricAutoSync({ showToastOnError: false });
                }
            });

            $('#manualSyncMachineButton').on('click', function() {
                runBiometricAutoSync({
                    manual: true,
                    showToastOnError: true
                });
            });
        });

        // View Details function
        function viewDetails(studentId) {
            var sid = parseInt(studentId, 10);
            if (!sid || sid <= 0) {
                showToast('Invalid student record', 'danger');
                return;
            }
            window.location.href = 'students-dtr.php?id=' + sid;
        }

        // Update bulk actions toolbar visibility and count
        function updateBulkActionsToolbar() {
            var selectedCount = $('.checkbox:checked').length;
            $('#selectedCount').text(selectedCount);
            
            // Show bulk toolbar only when multiple rows are selected.
            if (selectedCount > 1) {
                $('#bulkActionsToolbar').slideDown(200);
            } else {
                $('#bulkActionsToolbar').slideUp(200);
                if (selectedCount === 0) {
                    $('#checkAllAttendance').prop('checked', false);
                }
            }
        }

        // Clear selection
        function clearSelection() {
            $('.checkbox').prop('checked', false);
            $('#checkAllAttendance').prop('checked', false);
            updateBulkActionsToolbar();
        }

        // Helper function to get selected IDs
        function getSelectedIds() {
            var ids = [];
            $('.checkbox:checked').each(function() {
                var id = parseInt($(this).data('attendance-id'), 10);
                if (!isNaN(id)) {
                    ids.push(id);
                }
            });
            return [...new Set(ids)];
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            // Remove existing toasts
            $('.toast-notification').remove();
            
            var toastHtml = '<div class="toast-notification alert alert-' + type + ' alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 99999; max-width: 400px;">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>';
            
            $('body').append(toastHtml);
            
            setTimeout(function() {
                $('.toast-notification').fadeOut(function() {
                    $(this).remove();
                });
            }, 4000);
        }

        function submitAttendanceAction(action, id, remarks) {
            var ids = Array.isArray(id) ? id : [id];
            ids = ids.filter(function(v) { return !!v; });
            var payload = {
                action: action,
                id: ids
            };
            if (typeof remarks === 'string') {
                payload.remarks = remarks;
            }

            $.ajax({
                type: 'POST',
                url: 'process_attendance.php',
                data: payload,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        showToast(response.message || 'Action completed successfully.', 'success');
                        refreshAttendanceTable();
                    } else {
                        showToast((response && response.message) ? response.message : 'Unable to complete the action.', 'danger');
                    }
                },
                error: function() {
                    showToast('Error processing request', 'danger');
                }
            });
        }

        function showConfirmModal(options) {
            var modalEl = document.getElementById('confirmModal');
            if (!modalEl) return;

            var modalTitle = modalEl.querySelector('.modal-title');
            var modalBody = modalEl.querySelector('.modal-body .confirm-message');
            var remarksWrap = modalEl.querySelector('.modal-body .confirm-remarks-wrap');
            var remarksInput = modalEl.querySelector('#confirmRemarks');
            var okBtn = modalEl.querySelector('#confirmModalOk');

            modalTitle.textContent = options.title || 'Confirm';
            modalBody.textContent = options.message || '';
            if (options.showRemarks) {
                remarksWrap.style.display = 'block';
                remarksInput.value = options.defaultRemarks || '';
            } else {
                remarksWrap.style.display = 'none';
                remarksInput.value = '';
            }

            okBtn.replaceWith(okBtn.cloneNode(true));
            okBtn = modalEl.querySelector('#confirmModalOk');

            okBtn.addEventListener('click', function() {
                var remarks = (remarksInput.value || '').trim();

                var instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) instance.hide();

                if (typeof options.onConfirm === 'function') {
                    options.onConfirm(remarks);
                }
            });

            var modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            modal.show();
        }

        // Refresh table after action
        function refreshAttendanceTable() {
            var currentUrl = window.location.href;
            $.get(currentUrl, function(html) {
                if ($.fn.DataTable.isDataTable('#attendanceList')) {
                    $('#attendanceList').DataTable().destroy();
                }
                var newTbody = $(html).find('#attendanceList tbody').html();
                $('#attendanceList tbody').html(newTbody);
                initAttendanceDataTable();
                // Reinitialize tooltips
                $('[data-bs-toggle="tooltip"]').each(function() {
                    new bootstrap.Tooltip(this);
                });
                // Reinitialize dropdowns - Bootstrap 5
                var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                dropdownElements.forEach(function(element) {
                    new bootstrap.Dropdown(element);
                });
                $('#checkAllAttendance').prop('checked', false);
                updateBulkActionsToolbar();
            });
        }

        // Individual record approval
        function approveAttendanceIndividual(id) {
            if (!id || id === 0) {
                showToast('Invalid attendance record', 'danger');
                return;
            }
            showConfirmModal({
                title: 'Approve Attendance',
                message: 'Are you sure you want to approve this attendance record?',
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('approve', [id]);
                }
            });
        }

        // Bulk approval (from checkboxes)
        function approveAttendance() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to approve', 'warning');
                return;
            }
            showConfirmModal({
                title: 'Approve Attendance',
                message: ids.length === 1 ? 'Are you sure you want to approve this attendance?' : ('Are you sure you want to approve ' + ids.length + ' attendance record(s)?'),
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('approve', ids);
                }
            });
        }

        // Individual record rejection
        function rejectAttendanceIndividual(id) {
            if (!id || id === 0) {
                showToast('Invalid attendance record', 'danger');
                return;
            }
            showConfirmModal({
                title: 'Reject Attendance',
                message: 'Provide a reason for rejection (required):',
                showRemarks: true,
                onConfirm: function(remarks) {
                    if (!remarks) {
                        setTimeout(function() {
                            showConfirmModal({
                                title: 'Reject Attendance',
                                message: 'Rejection reason is required.',
                                showRemarks: true,
                                onConfirm: function(r) {
                                    if (!r) return;
                                    rejectAttendanceIndividual(id);
                                }
                            });
                        }, 250);
                        return;
                    }
                    submitAttendanceAction('reject', [id], remarks);
                }
            });
        }

        // Bulk rejection (from checkboxes)
        function rejectAttendance() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to reject', 'warning');
                return;
            }
            showConfirmModal({
                title: 'Reject Attendance',
                message: 'Provide a reason for rejection (required):',
                showRemarks: true,
                onConfirm: function(remarks) {
                    if (!remarks) {
                        setTimeout(function() {
                            showConfirmModal({
                                title: 'Reject Attendance',
                                message: 'Rejection reason is required.',
                                showRemarks: true,
                                onConfirm: function(r) {
                                    if (!r) return;
                                    rejectAttendance();
                                }
                            });
                        }, 250);
                        return;
                    }
                    submitAttendanceAction('reject', ids, remarks);
                }
            });
        }

        // Edit attendance function (redirects to edit page)
        function editAttendance(id) {
            window.location.href = 'edit_attendance.php?id=' + id;
        }

        // Print attendance function
        function printAttendance(id) {
            window.open('print_attendance.php?id=' + id, 'Print', 'height=600,width=800');
        }

        // Send notification function
        function sendNotification(id) {
            alert('Sending notification for Attendance ID: ' + id);
            // Implement your notification logic here
        }

        // Individual record deletion
        function deleteAttendanceIndividual(id) {
            if (!id || id === 0) {
                showToast('Invalid attendance record', 'danger');
                return;
            }
            showConfirmModal({
                title: 'Delete Attendance',
                message: 'Are you sure you want to delete this attendance record? This action cannot be undone.',
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('delete', [id]);
                }
            });
        }

        // Bulk deletion (from checkboxes)
        function deleteAttendance() {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to delete', 'warning');
                return;
            }
            showConfirmModal({
                title: 'Delete Attendance',
                message: ids.length === 1 ? 'Are you sure you want to delete this attendance record? This action cannot be undone.' : ('Are you sure you want to delete ' + ids.length + ' attendance record(s)? This action cannot be undone.'),
                showRemarks: false,
                onConfirm: function() {
                    submitAttendanceAction('delete', ids);
                }
            });
        }

        // Bulk action handler
        function performBulkAction(action) {
            var ids = getSelectedIds();
            
            if (ids.length === 0) {
                showToast('Please select at least one attendance record', 'warning');
                return;
            }

            if (action === 'approve') {
                approveAttendance();
            } else if (action === 'reject') {
                rejectAttendance();
            } else if (action === 'delete') {
                deleteAttendance();
            }
        }

        // Edit status inline via AJAX
        function changeStatus(id, newStatus) {
            if (confirm('Change status to ' + newStatus + '?')) {
                $.ajax({
                    type: 'POST',
                    url: 'process_attendance.php',
                    data: {
                        action: 'edit_status',
                        id: [id],
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error processing request', 'danger');
                    }
                });
            }
        }
    </script>
    <div class="modal fade" id="viewAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><small class="text-muted d-block">Attendance ID</small><strong id="view_attendance_id">-</strong></div>
                        <div class="col-md-4"><small class="text-muted d-block">Date</small><strong id="view_date">-</strong></div>
                        <div class="col-md-4"><small class="text-muted d-block">Student No.</small><strong id="view_student_number">-</strong></div>
                        <div class="col-md-6"><small class="text-muted d-block">Student</small><strong id="view_student_name">-</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Course</small><strong id="view_course">-</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Department</small><strong id="view_department">-</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Morning In</small><strong id="view_morning_in">-</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Morning Out</small><strong id="view_morning_out">-</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Afternoon In</small><strong id="view_afternoon_in">-</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Afternoon Out</small><strong id="view_afternoon_out">-</strong></div>
                        <div class="col-md-4"><small class="text-muted d-block">Total Hours</small><strong id="view_total_hours">-</strong></div>
                        <div class="col-md-4"><small class="text-muted d-block">Attendance Status</small><strong id="view_attendance_status">-</strong></div>
                        <div class="col-md-4"><small class="text-muted d-block">Approval Status</small><strong id="view_approval_status">-</strong></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="confirm-message"></p>
                    <div class="confirm-remarks-wrap" style="display:none; margin-top:10px;">
                        <label for="confirmRemarks" class="form-label">Remarks</label>
                        <textarea id="confirmRemarks" class="form-control" rows="3" placeholder="Enter remarks here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="confirmModalCancel" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmModalOk" class="btn btn-primary">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var darkBtn = document.querySelector('.dark-button');
                var lightBtn = document.querySelector('.light-button');

                function setDark(isDark) {
                    if (isDark) {
                        document.documentElement.classList.add('app-skin-dark');
                        try {
                            localStorage.setItem('app-skin', 'app-skin-dark');
                            localStorage.setItem('app_skin', 'app-skin-dark');
                            localStorage.setItem('theme', 'dark');
                            localStorage.setItem('app-skin-dark', 'app-skin-dark');
                        } catch (e) {}
                        if (darkBtn) darkBtn.style.display = 'none';
                        if (lightBtn) lightBtn.style.display = '';
                    } else {
                        document.documentElement.classList.remove('app-skin-dark');
                        try {
                            localStorage.setItem('app-skin', '');
                            localStorage.setItem('app_skin', '');
                            localStorage.setItem('theme', 'light');
                            localStorage.removeItem('app-skin-dark');
                        } catch (e) {}
                        if (darkBtn) darkBtn.style.display = '';
                        if (lightBtn) lightBtn.style.display = 'none';
                    }
                }

                var skin = '';
                try {
                    var appSkin = localStorage.getItem('app-skin');
                    var appSkinAlt = localStorage.getItem('app_skin');
                    var theme = localStorage.getItem('theme');
                    var legacy = localStorage.getItem('app-skin-dark');
                    if (appSkin !== null) skin = appSkin;
                    else if (appSkinAlt !== null) skin = appSkinAlt;
                    else if (theme !== null) skin = theme;
                    else if (legacy !== null) skin = legacy;
                } catch (e) {}
                setDark((typeof skin === 'string' && skin.indexOf('dark') !== -1) || document.documentElement.classList.contains('app-skin-dark'));

                if (darkBtn) darkBtn.addEventListener('click', function (e) { e.preventDefault(); setDark(true); });
                if (lightBtn) lightBtn.addEventListener('click', function (e) { e.preventDefault(); setDark(false); });
            });
        })();
    </script>
</body>

</html>






