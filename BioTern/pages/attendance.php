<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';
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

function attendance_local_today(): string
{
    $tzName = trim((string)(getenv('APP_TIMEZONE') ?: 'Asia/Manila'));
    try {
        $tz = new DateTimeZone($tzName !== '' ? $tzName : 'Asia/Manila');
    } catch (Throwable $e) {
        $tz = new DateTimeZone('Asia/Manila');
    }

    $now = new DateTimeImmutable('now', $tz);
    return $now->format('Y-m-d');
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
$valid_approval_statuses = ['approved', 'pending', 'rejected'];
$has_valid_approval_status_filter = in_array($filter_status, $valid_approval_statuses, true);

$school_year_options = [];
$school_year_start = 2005;
$attendance_today_local = attendance_local_today();
$current_calendar_month = (int)date('n', strtotime($attendance_today_local));
$current_calendar_year = (int)date('Y', strtotime($attendance_today_local));
$current_school_year_start = $current_calendar_month >= 7 ? $current_calendar_year : ($current_calendar_year - 1);
$latest_school_year_start = max(2025, $current_school_year_start);
for ($year = $latest_school_year_start; $year >= $school_year_start; $year--) {
    $school_year_options[] = sprintf('%d-%d', $year, $year + 1);
}

// default to local current date when no date filters provided
if (empty($filter_date) && empty($start_date) && empty($end_date) && !$has_valid_approval_status_filter) {
    $filter_date = $attendance_today_local;
}

// Safety net: process any pending raw biometric logs so Attendance stays current
// even if ingest accepted events while auto-import was temporarily disabled.
try {
    $pendingRaw = 0;
    $pendingRes = $conn->query('SELECT COUNT(*) AS pending_count FROM biometric_raw_logs WHERE processed = 0');
    if ($pendingRes instanceof mysqli_result) {
        $pendingRow = $pendingRes->fetch_assoc();
        $pendingRaw = (int)($pendingRow['pending_count'] ?? 0);
        $pendingRes->close();
    }

    if ($pendingRaw > 0) {
        run_biometric_auto_import_stats();
    }
} catch (Throwable $ignored) {
    // Keep attendance page load resilient if biometric sync tables are unavailable.
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
    if ($has_valid_approval_status_filter) {
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
            $avatar_html = '<a href="students-dtr.php?id=' . (int)$attendance['student_id'] . '" class="hstack gap-3">';
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
            echo '<td>' . attendance_reports_cell_html($attendance) . '</td>';
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

function attendance_schedule_bounds(array $attendance): array {
    $schedule = attendance_effective_schedule($attendance);

    return [
        'schedule' => $schedule,
        'official_start' => section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00',
        'official_end' => section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? '')) ?: '19:00:00',
        'late_after' => section_schedule_normalize_time_input((string)($schedule['late_after_time'] ?? ''))
            ?: (section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00'),
    ];
}

function attendance_clamped_duration_seconds(?int $startTs, ?int $endTs, string $windowStart, string $windowEnd): int
{
    if ($startTs === null || $endTs === null || $endTs <= $startTs) {
        return 0;
    }

    $windowStartTs = strtotime($windowStart);
    $windowEndTs = strtotime($windowEnd);
    if ($windowStartTs === false || $windowEndTs === false) {
        return max(0, $endTs - $startTs);
    }

    $clampedStart = max($startTs, $windowStartTs);
    $clampedEnd = min($endTs, $windowEndTs);
    return max(0, $clampedEnd - $clampedStart);
}

function attendance_credited_seconds(array $attendance, ?array $bounds = null): int
{
    $bounds = is_array($bounds) ? $bounds : attendance_schedule_bounds($attendance);
    $officialStart = (string)($bounds['official_start'] ?? '08:00:00');
    $officialEnd = (string)($bounds['official_end'] ?? '19:00:00');

    $totalSeconds = 0;
    $pairs = [
        ['morning_time_in', 'morning_time_out'],
        ['afternoon_time_in', 'afternoon_time_out'],
    ];

    foreach ($pairs as $pair) {
        $startTs = parseAttendanceTime($attendance[$pair[0]] ?? null);
        $endTs = parseAttendanceTime($attendance[$pair[1]] ?? null);
        $totalSeconds += attendance_clamped_duration_seconds($startTs, $endTs, $officialStart, $officialEnd);
    }

    $breakInTs = parseAttendanceTime($attendance['break_time_in'] ?? null);
    $breakOutTs = parseAttendanceTime($attendance['break_time_out'] ?? null);
    $totalSeconds -= attendance_clamped_duration_seconds($breakInTs, $breakOutTs, $officialStart, $officialEnd);

    return max(0, $totalSeconds);
}

function attendance_window_metrics(array $attendance): array {
    $bounds = attendance_schedule_bounds($attendance);
    $schedule = $bounds['schedule'];
    $punches = attendance_collect_punch_values($attendance);
    $firstPunch = $punches[0] ?? null;
    $lastPunch = $punches !== [] ? $punches[count($punches) - 1] : null;
    $hasCompletedRange = $firstPunch !== null && $lastPunch !== null && strcmp($lastPunch, $firstPunch) > 0;

    $officialStart = (string)$bounds['official_start'];
    $officialEnd = (string)$bounds['official_end'];
    $lateAfter = (string)$bounds['late_after'];

    $earlyHours = 0.0;
    $officialHours = round(attendance_credited_seconds($attendance, $bounds) / 3600, 2);
    $overtimeHours = 0.0;

    if ($firstPunch !== null && strcmp($firstPunch, $officialStart) < 0) {
        $earlySeconds = max(0, strtotime($officialStart) - strtotime($firstPunch));
        $earlyHours = round($earlySeconds / 3600, 2);
    }

    if ($hasCompletedRange) {
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
    return round(attendance_credited_seconds($attendance) / 3600, 2);
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

function attendance_reports_cell_html(array $attendance): string
{
    $studentId = (int)($attendance['student_id'] ?? 0);
    $attendanceId = (int)($attendance['id'] ?? 0);

    if ($studentId <= 0 || $attendanceId <= 0) {
        return '<span class="text-muted">N/A</span>';
    }

    $dtrUrl = 'students-dtr.php?id=' . $studentId;
    $printUrl = 'print_attendance.php?id=' . $attendanceId;

    return '<div class="d-flex flex-wrap gap-1">'
        . '<a class="badge bg-soft-primary text-primary" href="' . htmlspecialchars($dtrUrl, ENT_QUOTES, 'UTF-8') . '">DTR</a>'
        . '<a class="badge bg-soft-secondary text-secondary" href="' . htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Print</a>'
        . '</div>';
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
<?php
$page_title = 'BioTern || Student Attendance';
$page_body_class = 'attendance-page';
$page_styles = ['assets/css/modules/pages/page-attendance.css'];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
    'assets/js/modules/pages/pages-attendance-runtime.js',
];
include 'includes/header.php';
?>
<main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Attendance DTR</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">Attendance DTR</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="attendanceActionsMenu">
                        <i class="feather-grid me-1"></i>
                        <span>Actions</span>
                    </button>
                    <div class="page-header-actions" id="attendanceActionsMenu">
                        <div class="dashboard-actions-panel">
                            <div class="dashboard-actions-meta">
                                <span class="text-muted fs-12">Quick Actions</span>
                            </div>
                            <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <a href="javascript:void(0);" class="btn btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseAttendanceStats">
                                <i class="feather-bar-chart me-2"></i>
                                <span>Statistics</span>
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
                                <a class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-sliders me-2"></i>
                                    <span>Quick Filter</span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
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
                                <a class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-paperclip me-2"></i>
                                    <span>Export</span>
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
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
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
foreach ($school_year_options as $school_year): ?>
                                    <option value="<?php
echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?>" <?php
echo $filter_school_year === $school_year ? 'selected' : ''; ?>><?php
echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-date">Date</label>
                            <input id="filter-date" type="date" name="date" class="form-control" value="<?php
echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-course">Course</label>
                            <select id="filter-course" name="course_id" class="form-control">
                                <option value="0">-- All Courses --</option>
                                <?php
foreach ($courses as $course): ?>
                                    <option value="<?php
echo $course['id']; ?>" <?php
echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php
echo htmlspecialchars($course['name']); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-department">Department</label>
                            <select id="filter-department" name="department_id" class="form-control">
                                <option value="0">-- All Departments --</option>
                                <?php
foreach ($departments as $dept): ?>
                                    <option value="<?php
echo $dept['id']; ?>" <?php
echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php
echo htmlspecialchars($dept['name']); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-section">Section</label>
                            <select id="filter-section" name="section_id" class="form-control">
                                <option value="0">-- All Sections --</option>
                                <?php
foreach ($sections as $section): ?>
                                    <option value="<?php
echo (int)$section['id']; ?>" <?php
echo $filter_section == $section['id'] ? 'selected' : ''; ?>><?php
echo htmlspecialchars($section['section_label']); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-supervisor">Supervisor</label>
                            <select id="filter-supervisor" name="supervisor" class="form-control">
                                <option value="">-- Any Supervisor --</option>
                                <?php
foreach ($supervisors as $sup): ?>
                                    <option value="<?php
echo htmlspecialchars($sup); ?>" <?php
echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php
echo htmlspecialchars($sup); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-coordinator">Coordinator</label>
                            <select id="filter-coordinator" name="coordinator" class="form-control">
                                <option value="">-- Any Coordinator --</option>
                                <?php
foreach ($coordinators as $coor): ?>
                                    <option value="<?php
echo htmlspecialchars($coor); ?>" <?php
echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php
echo htmlspecialchars($coor); ?></option>
                                <?php
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
echo $stats['approved_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-success text-success">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php
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
echo $stats['pending_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-warning text-warning">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php
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
echo $stats['rejected_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-danger text-danger">
                                            <i class="feather-arrow-down fs-10 me-1"></i>
                                            <span><?php
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
                                                <th>Total Hours</th>
                                                <th>Status</th>
                                                <th>Source</th>
                                                <th>Reports</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
if (!empty($attendances)): ?>
                                                <?php
foreach ($attendances as $index => $attendance): ?>
                                                    <tr class="single-item">
                                                        <td>
                                                            <?php if (attendanceCanReview($attendance)): ?>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php
echo (int)$attendance['id']; ?>_<?php
echo (int)$index; ?>" data-attendance-id="<?php
echo (int)$attendance['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php
echo (int)$attendance['id']; ?>_<?php
echo (int)$index; ?>"></label>
                                                                </div>
                                                            </div>
                                                            <?php else: ?>
                                                            <span class="text-muted fs-12" title="Biometric records are auto-verified">Auto</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="students-dtr.php?id=<?php
echo $attendance['student_id']; ?>" class="hstack gap-3">
                                                                <?php
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
echo ($attendance['first_name'] ?? 'N/A') . ' ' . ($attendance['last_name'] ?? 'N/A'); ?></span>
                                                                    <span class="fs-12 text-muted d-block"><?php
echo $attendance['student_number'] ?? 'N/A'; ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td><span class="badge bg-soft-primary text-primary"><?php
echo date('Y-m-d', strtotime($attendance['attendance_date'])); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php
echo formatTime($attendance['morning_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php
echo formatTime($attendance['morning_time_out']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php
echo formatTime($attendance['afternoon_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php
echo formatTime($attendance['afternoon_time_out']); ?></span></td>
                                                        <td>
                                                            <?php
echo attendance_hours_cell_html($attendance); ?>
                                                        </td>
                                                        <td>
                                                            <?php
echo attendance_status_cell_html($attendance);
                                                            ?>
                                                        </td>
                                                        <td><?php
echo getSourceBadge($attendance['source'] ?? 'manual', $attendance); ?></td>
                                                        <td><?php
echo attendance_reports_cell_html($attendance); ?></td>
                                                        <td>
                                                            <?php echo attendanceActionMenuItems($attendance); ?>
                                                        </td>
                                                    </tr>
                                                <?php
endforeach; ?>
                                            <?php
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
                        <div class="col-md-4"><small class="text-muted d-block">Record Status</small><strong id="view_approval_status">-</strong></div>
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
    <?php include 'includes/footer.php'; ?>
