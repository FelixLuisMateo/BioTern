<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/attendance_bonus_rules.php';
require_once dirname(__DIR__) . '/lib/attendance_workflow.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
attendance_bonus_rules_ensure_schema($conn);
external_attendance_ensure_schema($conn);

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
    external_attendance_ensure_schema($conn);

    $calendarColumns = [];
    $calendarColumnsRes = $conn->query("SHOW COLUMNS FROM calendar_events");
    if ($calendarColumnsRes instanceof mysqli_result) {
        while ($calendarColumn = $calendarColumnsRes->fetch_assoc()) {
            $calendarColumns[strtolower((string)($calendarColumn['Field'] ?? ''))] = true;
        }
        $calendarColumnsRes->close();
    }
    if (!isset($calendarColumns['attendance_multiplier'])) {
        $conn->query("ALTER TABLE calendar_events ADD COLUMN attendance_multiplier DECIMAL(6,2) NULL AFTER color");
    }
    if (!isset($calendarColumns['apply_when_not_late'])) {
        $conn->query("ALTER TABLE calendar_events ADD COLUMN apply_when_not_late TINYINT(1) NOT NULL DEFAULT 0 AFTER attendance_multiplier");
    }
    if (!isset($calendarColumns['late_grace_minutes'])) {
        $conn->query("ALTER TABLE calendar_events ADD COLUMN late_grace_minutes INT NULL AFTER apply_when_not_late");
    }
    if (!isset($calendarColumns['applies_to_weekday'])) {
        $conn->query("ALTER TABLE calendar_events ADD COLUMN applies_to_weekday VARCHAR(16) NULL AFTER late_grace_minutes");
    }
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

function attendance_ensure_bridge_status_tables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_profile (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        profile_name VARCHAR(100) NOT NULL DEFAULT 'default',
        bridge_enabled TINYINT(1) NOT NULL DEFAULT 1,
        bridge_token VARCHAR(255) NOT NULL DEFAULT '',
        cloud_base_url VARCHAR(255) NOT NULL DEFAULT '',
        ingest_path VARCHAR(255) NOT NULL DEFAULT '/api/f20h_ingest.php',
        ingest_api_token VARCHAR(255) NOT NULL DEFAULT '',
        poll_seconds INT NOT NULL DEFAULT 30,
        ip_address VARCHAR(100) NOT NULL DEFAULT '',
        gateway VARCHAR(100) NOT NULL DEFAULT '',
        mask VARCHAR(100) NOT NULL DEFAULT '255.255.255.0',
        port INT NOT NULL DEFAULT 5001,
        device_number INT NOT NULL DEFAULT 1,
        communication_password VARCHAR(255) NOT NULL DEFAULT '0',
        output_path VARCHAR(255) NOT NULL DEFAULT '',
        updated_by INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_profile_name (profile_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_heartbeat (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        node_name VARCHAR(120) NOT NULL DEFAULT '',
        status_text VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_node_name (node_name),
        KEY idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_user_cache (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_node VARCHAR(120) NOT NULL DEFAULT '',
        users_json LONGTEXT NOT NULL,
        users_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS biometric_ingest_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        source_ip VARCHAR(64) NOT NULL DEFAULT '',
        source_node VARCHAR(120) NOT NULL DEFAULT '',
        token_status VARCHAR(40) NOT NULL DEFAULT '',
        http_status INT NOT NULL DEFAULT 0,
        events_received INT NOT NULL DEFAULT 0,
        events_accepted INT NOT NULL DEFAULT 0,
        note VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY idx_received_at (received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function attendance_bridge_runtime_status(mysqli $conn): array
{
    try {
        attendance_ensure_bridge_status_tables($conn);
    } catch (Throwable $ignored) {
        return [
            'label' => 'Bridge Unknown',
            'class' => 'secondary',
            'icon' => 'feather-help-circle',
            'detail' => 'Bridge status tables are not available yet.',
            'age_seconds' => null,
        ];
    }

    $profile = [];
    $profileRes = $conn->query("SELECT bridge_enabled, poll_seconds FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
    if ($profileRes instanceof mysqli_result) {
        $profile = $profileRes->fetch_assoc() ?: [];
        $profileRes->close();
    }

    $bridgeEnabled = empty($profile) || !empty($profile['bridge_enabled']);
    $pollSeconds = max(10, (int)($profile['poll_seconds'] ?? 30));

    $latestHeartbeatAt = '';
    $heartbeatStatusText = '';
    $heartbeatRes = $conn->query("SELECT updated_at, status_text FROM biometric_bridge_heartbeat ORDER BY updated_at DESC, id DESC LIMIT 1");
    if ($heartbeatRes instanceof mysqli_result) {
        $row = $heartbeatRes->fetch_assoc() ?: [];
        $latestHeartbeatAt = (string)($row['updated_at'] ?? '');
        $heartbeatStatusText = (string)($row['status_text'] ?? '');
        $heartbeatRes->close();
    }

    $latestCacheAt = '';
    $cacheRes = $conn->query("SELECT created_at FROM biometric_bridge_user_cache ORDER BY id DESC LIMIT 1");
    if ($cacheRes instanceof mysqli_result) {
        $row = $cacheRes->fetch_assoc() ?: [];
        $latestCacheAt = (string)($row['created_at'] ?? '');
        $cacheRes->close();
    }

    $latestIngestAt = '';
    $ingestRes = $conn->query("SELECT received_at FROM biometric_ingest_events ORDER BY id DESC LIMIT 1");
    if ($ingestRes instanceof mysqli_result) {
        $row = $ingestRes->fetch_assoc() ?: [];
        $latestIngestAt = (string)($row['received_at'] ?? '');
        $ingestRes->close();
    }

    $workerLogAt = '';
    $workerLogPath = dirname(__DIR__) . '/tools/bridge-worker.log';
    $workerLogMtime = @filemtime($workerLogPath);
    if ($workerLogMtime !== false && $workerLogMtime > 0) {
        $workerLogAt = date('Y-m-d H:i:s', (int)$workerLogMtime);
    }

    $candidates = [
        'cloud heartbeat' => $latestHeartbeatAt,
        'bridge user cache' => $latestCacheAt,
        'ingest event' => $latestIngestAt,
        'local worker log' => $workerLogAt,
    ];

    $lastSeenAt = '';
    $lastSeenSource = '';
    $lastSeenTs = 0;
    foreach ($candidates as $source => $candidate) {
        if (trim((string)$candidate) === '') {
            continue;
        }
        $ts = strtotime((string)$candidate);
        if ($ts !== false && $ts > $lastSeenTs) {
            $lastSeenTs = $ts;
            $lastSeenAt = (string)$candidate;
            $lastSeenSource = (string)$source;
        }
    }

    if (!$bridgeEnabled) {
        return [
            'label' => 'Bridge Paused',
            'class' => 'warning',
            'icon' => 'feather-pause-circle',
            'detail' => 'Bridge profile is paused. Resume or revive it in Machine Manager.',
            'age_seconds' => null,
        ];
    }

    if ($lastSeenTs <= 0) {
        return [
            'label' => 'Bridge Unknown',
            'class' => 'secondary',
            'icon' => 'feather-help-circle',
            'detail' => 'No bridge heartbeat, user cache, ingest activity, or local worker log has been seen yet.',
            'age_seconds' => null,
        ];
    }

    $ageSeconds = max(0, time() - $lastSeenTs);
    $onlineThreshold = max(600, $pollSeconds * 12);
    $isOnline = $ageSeconds <= $onlineThreshold;
    $pullStatus = strtolower($heartbeatStatusText);
    $pullFailing = $isOnline && (
        strpos($pullStatus, 'f20h pull failed') !== false
        || strpos($pullStatus, 'device connection failed') !== false
        || strpos($pullStatus, 'failed') !== false
    );

    return [
        'label' => $pullFailing ? 'Bridge Online / F20H Pull Failing' : ($isOnline ? 'Bridge Online' : 'Bridge Offline'),
        'class' => $pullFailing ? 'warning' : ($isOnline ? 'success' : 'danger'),
        'icon' => $pullFailing ? 'feather-alert-triangle' : ($isOnline ? 'feather-wifi' : 'feather-wifi-off'),
        'detail' => ($isOnline ? 'Last activity ' : 'Last activity stale: ') . $lastSeenAt
            . ($lastSeenSource !== '' ? ' via ' . $lastSeenSource : '')
            . ' (age ' . $ageSeconds . 's, poll ' . $pollSeconds . 's)'
            . ($heartbeatStatusText !== '' ? '. Heartbeat: ' . $heartbeatStatusText : ''),
        'age_seconds' => $ageSeconds,
        'last_seen_at' => $lastSeenAt,
        'last_seen_source' => $lastSeenSource,
    ];
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
        SUM(CASE WHEN status IN ('pending', 'pending_correction', 'incomplete') THEN 1 ELSE 0 END) as pending_count,
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
            SUM(CASE WHEN a.status IN ('pending', 'pending_correction', 'incomplete') THEN 1 ELSE 0 END) as pending_count,
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
$filter_course = 0;
$filter_department = 0;
$filter_section = 0;
$filter_school_year = '';
$filter_supervisor = '';
$filter_coordinator = '';
$filter_source = isset($_GET['source']) ? trim((string)$_GET['source']) : 'all';
$valid_source_filters = ['all', 'manual', 'biometric', 'external-biometric'];
if (!in_array($filter_source, $valid_source_filters, true)) {
    $filter_source = 'all';
}
$filter_status = strtolower(trim((string)($_GET['status'] ?? 'all')));
$valid_status_filters = ['all', 'early', 'present', 'late', 'absent', 'approved', 'pending_correction'];
if (!in_array($filter_status, $valid_status_filters, true)) {
    $filter_status = 'all';
}
$has_active_status_filter = $filter_status !== 'all';
$filter_reports = isset($_GET['reports']) ? trim((string)$_GET['reports']) : 'all';
$valid_report_filters = ['all', 'internal_dtr', 'external_queue', 'proof'];
$has_valid_report_filter = in_array($filter_reports, $valid_report_filters, true);
if (!$has_valid_report_filter) {
    $filter_reports = 'all';
}

$has_application_status = false;
$col_app = $conn->query("SHOW COLUMNS FROM users LIKE 'application_status'");
if ($col_app instanceof mysqli_result) {
    $has_application_status = $col_app->num_rows > 0;
    $col_app->close();
}

$has_school_year_column = false;
$col_sy = $conn->query("SHOW COLUMNS FROM students LIKE 'school_year'");
if ($col_sy instanceof mysqli_result) {
    $has_school_year_column = $col_sy->num_rows > 0;
    $col_sy->close();
}

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

$has_additional_filters = (
    $has_active_status_filter
    || $filter_reports !== 'all'
    || $filter_source !== 'all'
);

// default to local current date when no date filters provided
if (empty($filter_date) && empty($start_date) && empty($end_date) && !$has_additional_filters) {
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
$section_res = $conn->query("SELECT id, code, name, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($section_res && $section_res->num_rows) {
    while ($r = $section_res->fetch_assoc()) {
        $r['section_label'] = biotern_format_section_label((string)($r['code'] ?? ''), (string)($r['name'] ?? ''));
        $sections[] = $r;
    }
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
if ($filter_source !== 'all') {
    if ($filter_source === 'biometric') {
        $where[] = "(a.source = 'biometric' OR a.source = 'external-biometric')";
    } else {
        $where[] = "a.source = '" . $conn->real_escape_string($filter_source) . "'";
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

$hasManualDtrAttachments = function_exists('table_exists') && table_exists($conn, 'manual_dtr_attachments');
if ($filter_reports === 'proof') {
    $where[] = $hasManualDtrAttachments
        ? "EXISTS (SELECT 1 FROM manual_dtr_attachments mda_filter WHERE mda_filter.attendance_id = a.id AND mda_filter.deleted_at IS NULL)"
        : '1 = 0';
}

$manualProofSelect = 'NULL AS proof_photo_path, NULL AS proof_reason';
$manualProofJoin = '';
if ($hasManualDtrAttachments) {
    $manualProofSelect = "CASE WHEN mda.id IS NOT NULL THEN CONCAT('manual-dtr-proof.php?id=', mda.id) ELSE NULL END AS proof_photo_path, mda.reason AS proof_reason";
    $manualProofJoin = "
    LEFT JOIN manual_dtr_attachments mda ON mda.id = (
        SELECT MIN(mda_inner.id)
        FROM manual_dtr_attachments mda_inner
        WHERE mda_inner.attendance_id = a.id
          AND mda_inner.deleted_at IS NULL
    )";
}

$hasStudentAssistancePrograms = false;
$saTableRes = $conn->query("SHOW TABLES LIKE 'student_assistance_programs'");
if ($saTableRes instanceof mysqli_result) {
    $hasStudentAssistancePrograms = $saTableRes->num_rows > 0;
    $saTableRes->close();
}
$saStudentSelect = $hasStudentAssistancePrograms
    ? "CASE WHEN EXISTS (SELECT 1 FROM student_assistance_programs sap WHERE sap.student_id = s.id AND sap.deleted_at IS NULL AND sap.status = 'active') THEN 1 ELSE 0 END AS is_sa_student,"
    : "0 AS is_sa_student,";

$include_internal_records = in_array($filter_reports, ['all', 'internal_dtr', 'proof'], true);
$include_external_records = in_array($filter_reports, ['all', 'external_queue', 'proof'], true);

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
        'internal' AS record_origin,
        {$manualProofSelect},
        s.id as student_id,
        s.user_id,
        s.department_id,
        {$saStudentSelect}
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
    {$manualProofJoin}
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.attendance_date DESC, a.id DESC, s.last_name ASC
    LIMIT 100
";

$attendances = [];
if ($include_internal_records) {
    $attendance_result = $conn->query($attendance_query);
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
}

$externalWhere = ["ea.status = 'approved'"];
if ($has_application_status) {
    $externalWhere[] = "COALESCE(u_student.application_status, 'approved') = 'approved'";
}
if ($filter_source !== 'all') {
    if ($filter_source === 'biometric') {
        $externalWhere[] = "(ea.source = 'biometric' OR ea.source = 'external-biometric')";
    } else {
        $externalWhere[] = "ea.source = '" . $conn->real_escape_string($filter_source) . "'";
    }
}
if ($filter_reports === 'proof') {
    $externalWhere[] = "(TRIM(COALESCE(ea.photo_path, '')) <> '' OR EXISTS (SELECT 1 FROM external_dtr_attachments eda_filter WHERE eda_filter.external_attendance_id = ea.id AND eda_filter.deleted_at IS NULL))";
}
if ($start_date !== '' && $end_date !== '') {
    $safeStartDate = $conn->real_escape_string($start_date);
    $safeEndDate = $conn->real_escape_string($end_date);
    $externalWhere[] = "ea.attendance_date BETWEEN '{$safeStartDate}' AND '{$safeEndDate}'";
} elseif ($filter_date !== '') {
    $safeDate = $conn->real_escape_string($filter_date);
    $externalWhere[] = "ea.attendance_date = '{$safeDate}'";
}
if ($filter_course > 0) {
    $externalWhere[] = "s.course_id = " . (int)$filter_course;
}
if ($filter_department > 0) {
    $externalWhere[] = "COALESCE(s.department_id, i.department_id) = " . (int)$filter_department;
}
if ($filter_section > 0) {
    $externalWhere[] = "s.section_id = " . (int)$filter_section;
}
if ($has_school_year_column && $filter_school_year !== '' && preg_match('/^\d{4}-\d{4}$/', $filter_school_year) && in_array($filter_school_year, $school_year_options, true)) {
    $externalWhere[] = "s.school_year = '" . $conn->real_escape_string($filter_school_year) . "'";
}
if (!empty($filter_supervisor)) {
    $escSup = $conn->real_escape_string($filter_supervisor);
    $externalWhere[] = "(
        TRIM(CONCAT_WS(' ', sup.first_name, sup.middle_name, sup.last_name)) LIKE '%{$escSup}%'
        OR s.supervisor_name LIKE '%{$escSup}%'
    )";
}
if (!empty($filter_coordinator)) {
    $escCoor = $conn->real_escape_string($filter_coordinator);
    $externalWhere[] = "(
        TRIM(CONCAT_WS(' ', coor.first_name, coor.middle_name, coor.last_name)) LIKE '%{$escCoor}%'
        OR s.coordinator_name LIKE '%{$escCoor}%'
    )";
}
if ($attendance_is_supervisor && $attendance_user_id > 0) {
    $scopeParts = ["(i.supervisor_id = " . (int)$attendance_user_id . " OR s.supervisor_id = " . (int)$attendance_user_id . ")"];
    if ($attendance_supervisor_profile_id > 0 && $attendance_supervisor_profile_id !== $attendance_user_id) {
        $scopeParts[] = "(i.supervisor_id = " . (int)$attendance_supervisor_profile_id . " OR s.supervisor_id = " . (int)$attendance_supervisor_profile_id . ")";
    }
    $externalWhere[] = '(' . implode(' OR ', $scopeParts) . ')';
}

$externalAttendanceQuery = "
     SELECT
         ea.id,
         ea.attendance_date,
         ea.morning_time_in,
         ea.morning_time_out,
         ea.break_time_in,
         ea.break_time_out,
         ea.afternoon_time_in,
         ea.afternoon_time_out,
         ea.total_hours,
         ea.source,
         ea.status,
         ea.reviewed_by AS approved_by,
         ea.reviewed_at AS approved_at,
         ea.notes AS remarks,
         (
            SELECT eda_reason.reason
            FROM external_dtr_attachments eda_reason
            WHERE eda_reason.external_attendance_id = ea.id
              AND eda_reason.deleted_at IS NULL
            ORDER BY eda_reason.id DESC
            LIMIT 1
         ) AS proof_reason,
         'external' AS record_origin,
         " . external_attendance_proof_url_sql('ea') . " AS proof_photo_path,
         s.id AS student_id,
         s.user_id,
         COALESCE(s.department_id, i.department_id) AS department_id,
         {$saStudentSelect}
         COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
         s.student_id AS student_number,
         s.first_name,
         s.last_name,
         s.email,
         s.section_id,
         s.supervisor_name,
         s.coordinator_name,
         sec.code AS section_code,
         sec.name AS section_name,
         c.name AS course_name,
         d.name AS department_name,
         sec.attendance_session,
         sec.schedule_time_in,
         sec.schedule_time_out,
         sec.late_after_time,
         sec.weekly_schedule_json,
         u.name AS approver_name,
         ea.multiplier,
         ea.multiplier_reason
     FROM external_attendance ea
     LEFT JOIN students s ON ea.student_id = s.id
     LEFT JOIN users u_student ON s.user_id = u_student.id
     LEFT JOIN sections sec ON s.section_id = sec.id
     LEFT JOIN courses c ON s.course_id = c.id
     LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
     LEFT JOIN supervisors sup ON i.supervisor_id = sup.id
     LEFT JOIN coordinators coor ON i.coordinator_id = coor.id
     LEFT JOIN departments d ON d.id = COALESCE(s.department_id, i.department_id)
     LEFT JOIN users u ON ea.reviewed_by = u.id
     WHERE " . implode(' AND ', $externalWhere) . "
     ORDER BY ea.attendance_date DESC, ea.id DESC, s.last_name ASC
     LIMIT 100
";

$externalAttendanceResult = null;
if ($include_external_records) {
    $externalAttendanceResult = $conn->query($externalAttendanceQuery);
}
if ($externalAttendanceResult instanceof mysqli_result) {
    while ($row = $externalAttendanceResult->fetch_assoc()) {
        $attendances[] = $row;
    }
    $externalAttendanceResult->close();
}

// Remove same-day duplicates per student, preferring the row that has actual punches.
if (count($attendances) > 1) {
    $attendance_by_student_date = [];
    foreach ($attendances as $attendance) {
        $student_id_key = isset($attendance['student_id']) ? (string)$attendance['student_id'] : '';
        $attendance_date_key = isset($attendance['attendance_date']) ? (string)$attendance['attendance_date'] : '';
        $record_origin_key = trim((string)($attendance['record_origin'] ?? 'internal'));
        $dedupe_key = ($student_id_key !== '' && $attendance_date_key !== '')
            ? ($student_id_key . '|' . $attendance_date_key . '|' . $record_origin_key)
            : ('id|' . (string)($attendance['id'] ?? ''));

        if (!isset($attendance_by_student_date[$dedupe_key]) || shouldPreferAttendanceRow($attendance, $attendance_by_student_date[$dedupe_key])) {
            $attendance_by_student_date[$dedupe_key] = $attendance;
        }
    }
    $attendances = array_values($attendance_by_student_date);
}

foreach ($attendances as &$attendance) {
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        continue;
    }
    attendance_workflow_mark_incomplete_if_needed($conn, $attendance);
}
unset($attendance);

if ($has_active_status_filter) {
    $attendances = array_values(array_filter($attendances, function (array $attendance) use ($filter_status): bool {
        if ($filter_status === 'pending_correction') {
            return strtolower(trim((string)($attendance['status'] ?? ''))) === 'pending_correction';
        }
        return attendance_list_status_key($attendance) === $filter_status;
    }));
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

$attendanceBridgeStatus = attendance_bridge_runtime_status($conn);

// If requested via AJAX, return only the table rows HTML so frontend can replace tbody
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (!empty($attendances)) {
        foreach ($attendances as $idx => $attendance) {
            $checkboxId = 'checkBox_' . $attendance['id'] . '_' . $idx;
            echo '<tr class="single-item">';
            if (attendanceCanReview($attendance)) {
            echo '<td data-label="Select"><div class="item-checkbox ms-1"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input checkbox" id="' . $checkboxId . '" data-attendance-id="' . (int)$attendance['id'] . '"><label class="custom-control-label" for="' . $checkboxId . '"></label></div></div></td>';
            } else {
                echo '<td data-label="Select"><span class="text-muted fs-12" title="Biometric records are auto-verified">Auto</span></td>';
            }
            // build avatar (use uploaded profile picture when available)
            $avatar_html = '<a href="students-internal-dtr.php?id=' . (int)$attendance['student_id'] . '" class="hstack gap-3">';
            $pp_url = resolve_attendance_profile_image_url((string)($attendance['profile_picture'] ?? ''), (int)($attendance['user_id'] ?? 0));
            if ($pp_url !== null) {
                $avatar_html .= '<div class="avatar-image avatar-md"><img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid"></div>';
            } else {
                $initials = strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1));
                $avatar_html .= '<div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . $initials . '</div></div>';
            }
            $saBadge = ((int)($attendance['is_sa_student'] ?? 0) === 1) ? ' <span class="badge bg-soft-primary text-primary ms-1">SA</span>' : '';
            $avatar_html .= '<div><div class="fw-bold">' . htmlspecialchars(($attendance['first_name'] ?? '') . ' ' . ($attendance['last_name'] ?? '')) . $saBadge . '</div><small class="text-muted">' . htmlspecialchars($attendance['student_number'] ?? '') . '</small></div></a>';
            echo '<td data-label="Student Name">' . $avatar_html . '</td>';
            echo '<td data-label="Attendance Date"><span class="badge bg-soft-primary text-primary">' . date('Y-m-d', strtotime($attendance['attendance_date'])) . '</span></td>';
            echo '<td data-label="Morning In">' . attendanceDisplayTimeHtml($attendance, 'morning_time_in', 'bg-soft-success text-success') . '</td>';
            echo '<td data-label="Morning Out">' . attendanceDisplayTimeHtml($attendance, 'morning_time_out', 'bg-soft-success text-success') . '</td>';
            echo '<td data-label="Afternoon In">' . attendanceDisplayTimeHtml($attendance, 'afternoon_time_in', 'bg-soft-warning text-warning') . '</td>';
            echo '<td data-label="Afternoon Out">' . attendanceDisplayTimeHtml($attendance, 'afternoon_time_out', 'bg-soft-warning text-warning') . '</td>';
            echo '<td data-label="Total Hours">' . attendance_hours_cell_html($attendance) . '</td>';
            echo '<td data-label="Status">' . attendance_status_cell_html($attendance) . '</td>';
            echo '<td data-label="Source">' . getSourceBadge($attendance['source'] ?? 'manual', $attendance) . '</td>';
            echo '<td data-label="Reports">' . attendance_reports_cell_html($attendance) . '</td>';
            $student_name = trim((string)($attendance['first_name'] ?? '') . ' ' . (string)($attendance['last_name'] ?? ''));
            $approval_status_label = ucfirst((string)($attendance['status'] ?? 'pending'));
            $morning_in_text = $attendance['morning_time_in'] ? date('h:i A', strtotime($attendance['morning_time_in'])) : '-';
            $morning_out_text = $attendance['morning_time_out'] ? date('h:i A', strtotime($attendance['morning_time_out'])) : '-';
            $afternoon_in_text = $attendance['afternoon_time_in'] ? date('h:i A', strtotime($attendance['afternoon_time_in'])) : '-';
            $afternoon_out_text = $attendance['afternoon_time_out'] ? date('h:i A', strtotime($attendance['afternoon_time_out'])) : '-';
            echo '<td data-label="Actions">' . attendanceActionMenuItems($attendance) . '</td>';
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

function attendanceDateWeekdayKey(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') {
        return '';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    return strtolower((string)date('l', $timestamp));
}

function attendanceScheduleDisplayFallback(array $attendance, string $column): ?string {
    global $conn;

    if ($conn instanceof mysqli) {
        $settings = biotern_attendance_settings($conn);
        if ((string)($settings['scheduled_slot_display'] ?? '1') !== '1') {
            return null;
        }
    }

    if (strtolower(trim((string)($attendance['source'] ?? ''))) !== 'biometric') {
        return null;
    }

    $schedule = attendance_effective_schedule($attendance);
    if (($schedule['window_source'] ?? 'none') === 'none') {
        return null;
    }

    $session = section_schedule_inferred_session($schedule);
    $scheduleIn = section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? ''));
    $scheduleOut = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? ''));

    if ($session === 'morning_only') {
        if ($column === 'morning_time_in') {
            return $scheduleIn;
        }
        if ($column === 'morning_time_out') {
            return $scheduleOut;
        }
        return null;
    }

    if ($session === 'afternoon_only') {
        if ($column === 'afternoon_time_in') {
            return $scheduleIn;
        }
        if ($column === 'afternoon_time_out') {
            return $scheduleOut;
        }
        return null;
    }

    if ($column === 'morning_time_in') {
        return $scheduleIn;
    }
    if ($column === 'afternoon_time_out') {
        return $scheduleOut;
    }

    return null;
}

function attendanceResolvedTime(array $attendance, string $column): array {
    $raw = trim((string)($attendance[$column] ?? ''));
    if ($raw !== '' && $raw !== '00:00:00') {
        return ['time' => $raw, 'is_schedule' => false];
    }

    $fallback = attendanceScheduleDisplayFallback($attendance, $column);
    if ($fallback !== null && $fallback !== '') {
        return ['time' => $fallback, 'is_schedule' => true];
    }

    return ['time' => null, 'is_schedule' => false];
}

function attendanceIsMorningAbsentForWholeDay(array $attendance): bool {
    if (strtolower(trim((string)($attendance['source'] ?? ''))) !== 'biometric') {
        return false;
    }

    $schedule = attendance_effective_schedule($attendance);
    if (($schedule['window_source'] ?? 'none') === 'none') {
        return false;
    }

    $session = section_schedule_inferred_session($schedule);
    if ($session !== 'whole_day') {
        return false;
    }

    $morningIn = trim((string)($attendance['morning_time_in'] ?? ''));
    $morningOut = trim((string)($attendance['morning_time_out'] ?? ''));
    $afternoonIn = trim((string)($attendance['afternoon_time_in'] ?? ''));
    $afternoonOut = trim((string)($attendance['afternoon_time_out'] ?? ''));

    $hasMorning = ($morningIn !== '' && $morningIn !== '00:00:00') || ($morningOut !== '' && $morningOut !== '00:00:00');
    $hasAfternoon = ($afternoonIn !== '' && $afternoonIn !== '00:00:00') || ($afternoonOut !== '' && $afternoonOut !== '00:00:00');

    return !$hasMorning && $hasAfternoon;
}

function attendanceScheduledClassLabel(array $attendance): string
{
    $schedule = attendance_effective_schedule($attendance);
    $scheduleIn = section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? ''));
    $scheduleOut = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? ''));

    if ($scheduleIn === '' || $scheduleOut === '') {
        return 'Class';
    }

    $from = date('g:i A', strtotime($scheduleIn));
    $to = date('g:i A', strtotime($scheduleOut));
    return $from . ' to ' . $to;
}

function attendanceExpectedEndLabel(array $attendance, string $column): string
{
    return '';
}

function attendanceDisplayTimeHtml(array $attendance, string $column, string $badgeClass): string {
    if ($column === 'morning_time_in' && attendanceIsMorningAbsentForWholeDay($attendance)) {
        return '<span class="badge bg-soft-danger text-danger">Absent</span>';
    }

    $resolved = attendanceResolvedTime($attendance, $column);
    if ($resolved['time'] === null) {
        $expectedEnd = attendanceExpectedEndLabel($attendance, $column);
        return '<span class="badge ' . $badgeClass . '">-</span>'
            . ($expectedEnd !== ''
                ? '<div class="fs-11 text-muted mt-1">' . htmlspecialchars($expectedEnd, ENT_QUOTES, 'UTF-8') . '</div>'
                : '');
    }

    $timeLabel = date('h:i A', strtotime((string)$resolved['time']));
    if (!$resolved['is_schedule']) {
        return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $classTimeLabel = date('g:i A', strtotime((string)$resolved['time']));
    return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($classTimeLabel . ' Class', ENT_QUOTES, 'UTF-8') . '</span>';
}

function resolve_attendance_profile_image_url(string $profilePath, int $userId = 0): ?string {
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    return $resolved !== '' ? $resolved : null;
}

// Helper function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'approved':
            return '<span class="badge bg-soft-success text-success">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
        case 'pending_correction':
            return '<span class="badge bg-soft-warning text-warning">Needs Correction</span>';
        case 'incomplete':
            return '<span class="badge bg-soft-warning text-warning">Incomplete</span>';
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
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        $label = strtolower(trim((string)($attendance['status'] ?? 'approved'))) === 'approved'
            ? 'External Approved'
            : 'External Upload';
        return '<span class="badge bg-soft-info text-info">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

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

function attendance_actual_duration_seconds(?int $startTs, ?int $endTs): int
{
    if ($startTs === null || $endTs === null || $endTs <= $startTs) {
        return 0;
    }

    return max(0, $endTs - $startTs);
}

function attendance_credited_seconds(array $attendance, ?array $bounds = null): int
{
    $bounds = is_array($bounds) ? $bounds : attendance_schedule_bounds($attendance);
    $officialStart = (string)($bounds['official_start'] ?? '08:00:00');
    $officialEnd = (string)($bounds['official_end'] ?? '19:00:00');
    $useScheduleCredit = false;
    global $conn;
    if ($conn instanceof mysqli) {
        $track = strtolower(trim((string)($attendance['record_origin'] ?? 'internal')));
        $useScheduleCredit = biotern_attendance_uses_schedule_credit($conn, $track === 'external' ? 'external' : 'internal');
    }

    $totalSeconds = 0;
    $pairs = [
        ['morning_time_in', 'morning_time_out'],
        ['afternoon_time_in', 'afternoon_time_out'],
    ];

    foreach ($pairs as $pair) {
        $startTs = parseAttendanceTime($attendance[$pair[0]] ?? null);
        $endTs = parseAttendanceTime($attendance[$pair[1]] ?? null);
        $totalSeconds += $useScheduleCredit
            ? attendance_clamped_duration_seconds($startTs, $endTs, $officialStart, $officialEnd)
            : attendance_actual_duration_seconds($startTs, $endTs);
    }

    $breakInTs = parseAttendanceTime($attendance['break_time_in'] ?? null);
    $breakOutTs = parseAttendanceTime($attendance['break_time_out'] ?? null);
    $totalSeconds -= $useScheduleCredit
        ? attendance_clamped_duration_seconds($breakInTs, $breakOutTs, $officialStart, $officialEnd)
        : attendance_actual_duration_seconds($breakInTs, $breakOutTs);

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
        'late_after' => $lateAfter,
    ];
}

function attendanceCalendarBonusRulesForDate(string $dateKey): array {
    static $cache = [];
    global $conn;

    if ($dateKey === '') {
        return [];
    }
    if (isset($cache[$dateKey])) {
        return $cache[$dateKey];
    }
    if (!($conn instanceof mysqli)) {
        $cache[$dateKey] = [];
        return $cache[$dateKey];
    }

    $sql = "
        SELECT
            id,
            title,
            attendance_multiplier,
            apply_when_not_late,
            late_grace_minutes,
            applies_to_weekday
        FROM calendar_events
        WHERE deleted_at IS NULL
          AND attendance_multiplier IS NOT NULL
          AND attendance_multiplier > 0
          AND DATE(start_at) <= ?
          AND DATE(end_at) >= ?
        ORDER BY attendance_multiplier DESC, id DESC
    ";

    $rules = [];
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $dateKey, $dateKey);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
        $stmt->close();
    }

    $cache[$dateKey] = $rules;
    return $cache[$dateKey];
}

function attendanceMultiplierContext(array $attendance, ?array $metrics = null): array {
    global $conn;
    $dateKey = substr((string)($attendance['attendance_date'] ?? ''), 0, 10);
    $rules = attendanceCalendarBonusRulesForDate($dateKey);
    $customRules = [];
    if ($conn instanceof mysqli) {
        $customRules = attendance_bonus_rules_for_context(
            $conn,
            $dateKey,
            'internal',
            (int)($attendance['section_id'] ?? 0),
            (int)($attendance['department_id'] ?? 0)
        );
    }

    $metrics = is_array($metrics) ? $metrics : attendance_window_metrics($attendance);
    $weekday = attendanceDateWeekdayKey($dateKey);
    $best = ['multiplier' => 1.0, 'rule' => null];

    foreach ($customRules as $rule) {
        $multiplier = (float)($rule['multiplier'] ?? 1);
        if ($multiplier <= $best['multiplier']) {
            continue;
        }
        $best = [
            'multiplier' => $multiplier,
            'rule' => ['title' => 'Rule: ' . (string)($rule['title'] ?? 'Attendance bonus')],
        ];
    }

    foreach ($rules as $rule) {
        $ruleWeekday = strtolower(trim((string)($rule['applies_to_weekday'] ?? '')));
        if ($ruleWeekday !== '' && $ruleWeekday !== $weekday) {
            continue;
        }

        $requiresNotLate = (int)($rule['apply_when_not_late'] ?? 0) === 1;
        if ($requiresNotLate) {
            $firstPunch = trim((string)($metrics['first_punch'] ?? ''));
            if ($firstPunch === '') {
                continue;
            }

            $lateAfter = trim((string)($metrics['late_after'] ?? ''));
            if ($lateAfter === '') {
                continue;
            }

            $lateSeconds = strtotime($firstPunch) - strtotime($lateAfter);
            $lateMinutes = $lateSeconds > 0 ? (int)floor($lateSeconds / 60) : 0;
            $graceMinutes = max(0, (int)($rule['late_grace_minutes'] ?? 0));
            if ($lateMinutes > $graceMinutes) {
                continue;
            }
        }

        $multiplier = (float)($rule['attendance_multiplier'] ?? 1);
        if ($multiplier <= 0) {
            continue;
        }

        if ($multiplier > (float)$best['multiplier']) {
            $best = [
                'multiplier' => $multiplier,
                'rule' => $rule,
            ];
        }
    }

    return $best;
}

function attendance_format_hours_label(float $hours): string {
    return number_format($hours, 2) . 'h';
}

function attendance_hours_cell_html(array $attendance): string {
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        $hours = (float)($attendance['total_hours'] ?? 0);
        $html = '<span class="badge bg-soft-info text-info">' . attendance_format_hours_label($hours) . '</span>';
        $multiplierReason = trim((string)($attendance['multiplier_reason'] ?? ''));
        if ($multiplierReason !== '') {
            $html .= '<div class="fs-11 text-muted mt-1">' . htmlspecialchars($multiplierReason, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return $html;
    }

    $computedHours = isset($attendance['computed_total_hours'])
        ? (float)$attendance['computed_total_hours']
        : calculateAttendanceRowHours($attendance);

    return '<span class="badge bg-soft-secondary text-secondary">' . attendance_format_hours_label($computedHours) . '</span>';
}

function attendance_list_status_key(array $attendance): string {
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        return 'approved';
    }

    $metrics = attendance_window_metrics($attendance);
    $status = strtolower(trim((string)($metrics['arrival_status'] ?? 'absent')));

    return in_array($status, ['early', 'present', 'late', 'absent'], true) ? $status : 'absent';
}

function attendance_status_cell_html(array $attendance): string {
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        return '<span class="badge bg-soft-success text-success">Approved</span>'
            . '<div class="fs-11 text-muted mt-1">Teacher-reviewed external DTR</div>';
    }

    $status = attendance_list_status_key($attendance);

    if ($status === 'early') {
        $badge = '<span class="badge bg-soft-info text-info">Early</span>';
    } elseif ($status === 'present') {
        $badge = '<span class="badge bg-soft-success text-success">Present</span>';
    } elseif ($status === 'late') {
        $badge = '<span class="badge bg-soft-warning text-warning">Late</span>';
    } else {
        $badge = '<span class="badge bg-soft-danger text-danger">Absent</span>';
    }

    $metrics = attendance_window_metrics($attendance);

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
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        return round((float)($attendance['total_hours'] ?? 0), 2);
    }

    $baseHours = round(attendance_credited_seconds($attendance) / 3600, 2);
    if ($baseHours <= 0) {
        return 0.0;
    }

    $bonus = attendanceMultiplierContext($attendance);
    $multiplier = max(1.0, (float)($bonus['multiplier'] ?? 1));
    return round($baseHours * $multiplier, 2);
}

function synchronizeAttendanceProgress(mysqli $conn, array &$attendances): void {
    if (empty($attendances)) {
        return;
    }

    $studentIds = [];
    $updateAttendanceStmt = $conn->prepare("UPDATE attendances SET total_hours = ?, updated_at = NOW() WHERE id = ?");

    foreach ($attendances as &$attendance) {
        if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
            continue;
        }

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
        WHERE student_id = ? AND LOWER(COALESCE(status, 'pending')) = 'approved'
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
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        return '<span class="badge bg-soft-success text-success">Teacher Approved</span>';
    }

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

    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        $proofPath = trim((string)($attendance['proof_photo_path'] ?? ''));
        $externalUrl = 'external-attendance.php?status=approved';
        $html = '<div class="d-flex flex-wrap gap-1">'
            . '<a class="badge bg-soft-info text-info" href="' . htmlspecialchars($externalUrl, ENT_QUOTES, 'UTF-8') . '">External Queue</a>';
        if ($proofPath !== '') {
            $html .= '<a class="badge bg-soft-secondary text-secondary" href="' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Proof</a>';
        }
        return $html . '</div>';
    }

    $dtrUrl = 'students-internal-dtr.php?id=' . $studentId;
    $printUrl = 'print_attendance.php?id=' . $attendanceId;
    $proofPath = trim((string)($attendance['proof_photo_path'] ?? ''));
    $proofLink = $proofPath !== ''
        ? '<a class="badge bg-soft-info text-info" href="' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Proof</a>'
        : '';

    return '<div class="d-flex flex-wrap gap-1">'
        . '<a class="badge bg-soft-primary text-primary" href="' . htmlspecialchars($dtrUrl, ENT_QUOTES, 'UTF-8') . '">Internal DTR</a>'
        . '<a class="badge bg-soft-secondary text-secondary" href="' . htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Print</a>'
        . $proofLink
        . '</div>';
}

function attendance_photo_cell_html(array $attendance): string
{
    $proofPath = trim((string)($attendance['proof_photo_path'] ?? ''));
    if ($proofPath === '') {
        return '<span class="text-muted">-</span>';
    }

    return '<a class="attendance-proof-thumb" href="' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" title="Open proof image">'
        . '<img src="' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" alt="Proof image">'
        . '</a>';
}

function attendance_notes_cell_html(array $attendance): string
{
    $parts = [];
    foreach (['remarks', 'proof_reason'] as $key) {
        $value = trim((string)($attendance[$key] ?? ''));
        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    if ($parts === []) {
        return '<span class="text-muted fs-12">No notes</span>';
    }

    return '<div class="attendance-notes-cell">' . htmlspecialchars(implode(' | ', $parts), ENT_QUOTES, 'UTF-8') . '</div>';
}

function attendanceCanReview(array $attendance): bool
{
    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        return false;
    }

    return !attendanceIsBiometricRecord($attendance);
}

function attendance_review_cell_html(array $attendance): string
{
    $attendanceId = (int)($attendance['id'] ?? 0);
    $status = strtolower(trim((string)($attendance['status'] ?? 'pending')));

    if (!attendanceCanReview($attendance) || $attendanceId <= 0) {
        return '<span class="text-muted fs-12">No manual review needed</span>';
    }

    $approveSelected = $status === 'rejected' ? '' : ' selected';
    $rejectSelected = $status === 'rejected' ? ' selected' : '';

    return '<form class="attendance-review-form" data-attendance-review-form data-attendance-id="' . $attendanceId . '">'
        . '<label class="visually-hidden" for="attendanceReviewAction' . $attendanceId . '">Review action</label>'
        . '<select id="attendanceReviewAction' . $attendanceId . '" class="form-select form-select-sm" name="review_action">'
        . '<option value="approve"' . $approveSelected . '>Approve</option>'
        . '<option value="reject"' . $rejectSelected . '>Reject</option>'
        . '</select>'
        . '<label class="visually-hidden" for="attendanceReviewNote' . $attendanceId . '">Review note</label>'
        . '<input id="attendanceReviewNote' . $attendanceId . '" class="form-control form-control-sm" type="text" name="review_note" placeholder="Review note (optional)" value="">'
        . '<button type="button" class="btn btn-sm btn-primary" data-attendance-review-save>Save</button>'
        . '</form>';
}

function attendanceActionMenuItems(array $attendance): string
{
    $attendanceId = (int)($attendance['id'] ?? 0);
    $studentId = (int)($attendance['student_id'] ?? 0);

    if (strtolower(trim((string)($attendance['record_origin'] ?? 'internal'))) === 'external') {
        $items = [];
        $proofPath = trim((string)($attendance['proof_photo_path'] ?? ''));
        $items[] = '<li><a class="dropdown-item" href="external-attendance.php?status=approved"><i class="feather feather-briefcase me-3"></i><span>Open External Queue</span></a></li>';
        if ($proofPath !== '') {
            $items[] = '<li><a class="dropdown-item" href="' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"><i class="feather feather-image me-3"></i><span>Open Proof</span></a></li>';
        }
        return '<div class="hstack gap-2 justify-content-end"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Details" onclick="viewDetails(' . $studentId . ')"><i class="feather feather-eye"></i></a><div class="dropdown"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21"><i class="feather feather-more-horizontal"></i></a><ul class="dropdown-menu dropdown-menu-end">' . implode('', $items) . '</ul></div></div>';
    }

    $items = [];
    $proofPath = trim((string)($attendance['proof_photo_path'] ?? ''));

    if (attendanceCanReview($attendance)) {
        $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="approveAttendanceIndividual(' . $attendanceId . ')"><i class="feather feather-check-circle me-3"></i><span>Approve</span></a></li>';
        $items[] = '<li><a class="dropdown-item" href="javascript:void(0)" onclick="rejectAttendanceIndividual(' . $attendanceId . ')"><i class="feather feather-x-circle me-3"></i><span>Reject</span></a></li>';
    } else {
        $items[] = '<li><span class="dropdown-item-text text-muted"><i class="feather feather-shield me-3"></i><span>Auto-verified by machine</span></span></li>';
    }

    if ($proofPath !== '') {
        $items[] = '<li><a class="dropdown-item" href="' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"><i class="feather feather-image me-3"></i><span>Open Proof</span></a></li>';
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

if (trim((string)($_GET['print'] ?? '')) === 'list') {
    $printRange = $start_date !== '' && $end_date !== ''
        ? ($start_date . ' to ' . $end_date)
        : ($filter_date !== '' ? $filter_date : 'Current filtered list');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BioTern || Attendance Print Preview</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111827; background: #fff; }
        .screen-actions { max-width: 1120px; margin: 18px auto 0; display: flex; justify-content: flex-end; gap: 10px; }
        .screen-actions button { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 8px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
        .screen-actions .primary { background: #0ea5e9; border-color: #0ea5e9; color: #fff; }
        .paper { max-width: 1120px; margin: 0 auto; padding: 8mm 8mm 10mm; box-sizing: border-box; }
        .print-header { display: grid; grid-template-columns: 78px minmax(0,1fr) 78px; align-items: center; gap: 12px; border-bottom: 2px solid #2f5fb3; padding-bottom: 9px; }
        .print-header img { width: 68px; height: 68px; object-fit: contain; }
        .print-header-copy { text-align: center; line-height: 1.25; }
        .print-school { margin: 0; font-size: 24px; font-weight: 800; }
        .print-meta { margin: 0; font-size: 13px; color: #1f4e9f; font-weight: 600; }
        .print-title { text-align: center; font-size: 20px; font-weight: 800; margin: 18px 0 10px; text-transform: uppercase; }
        .filter-line { font-size: 12px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 7px; text-align: left; vertical-align: top; }
        thead th { background: #f8fafc; font-weight: 800; text-transform: uppercase; font-size: 10px; }
        .text-center { text-align: center; }
        @media print { .screen-actions { display: none; } .paper { max-width: none; padding: 0; } }
    </style>
</head>
<body>
    <div class="screen-actions"><button type="button" onclick="window.close()">Close</button><button type="button" class="primary" onclick="window.print()">Print</button></div>
    <main class="paper">
        <header class="print-header">
            <img src="assets/images/ccstlogo.png" alt="CCST">
            <div class="print-header-copy">
                <h1 class="print-school">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h1>
                <p class="print-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</p>
                <p class="print-meta">Telefax No.: (045) 624-0215</p>
            </div>
            <div aria-hidden="true"></div>
        </header>
        <div class="print-title">Attendance List</div>
        <div class="filter-line"><strong>FILTER:</strong> <?php echo htmlspecialchars($printRange, ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($filter_reports === 'all' ? 'All reports' : ucwords(str_replace('_', ' ', $filter_reports)), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($filter_status === 'all' ? 'All statuses' : ucwords(str_replace('_', ' ', $filter_status)), ENT_QUOTES, 'UTF-8'); ?></div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Student No.</th>
                    <th>Name</th>
                    <th>Course / Section</th>
                    <th>AM In</th>
                    <th>AM Out</th>
                    <th>PM In</th>
                    <th>PM Out</th>
                    <th>Total Hours</th>
                    <th>Status</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendances)): ?>
                    <tr><td colspan="12" class="text-center">No attendance records matched the current filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendances as $index => $attendance): ?>
                        <tr>
                            <td><?php echo (int)$index + 1; ?></td>
                            <td><?php echo htmlspecialchars((string)($attendance['attendance_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($attendance['student_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(trim((string)($attendance['last_name'] ?? '') . ', ' . (string)($attendance['first_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(trim((string)($attendance['course_name'] ?? '-') . ' / ' . biotern_format_section_label((string)($attendance['section_code'] ?? ''), (string)($attendance['section_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($attendance['morning_time_in']) ? date('h:i A', strtotime((string)$attendance['morning_time_in'])) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($attendance['morning_time_out']) ? date('h:i A', strtotime((string)$attendance['morning_time_out'])) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($attendance['afternoon_time_in']) ? date('h:i A', strtotime((string)$attendance['afternoon_time_in'])) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($attendance['afternoon_time_out']) ? date('h:i A', strtotime((string)$attendance['afternoon_time_out'])) : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float)($attendance['total_hours'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', attendance_list_status_key($attendance))), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string)($attendance['record_origin'] ?? 'internal')), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
    <?php
    exit;
}
?>
<?php
$page_title = !empty($biotern_attendance_sandbox) ? 'BioTern || Test Attendance' : 'BioTern || Internal Attendance';
$page_body_class = 'attendance-page';
$page_styles = ['assets/css/modules/pages/page-attendance.css?v=20260509c'];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
    'assets/js/modules/pages/pages-attendance-runtime.js?v=20260509c',
];
include 'includes/header.php';
?>
<main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Internal Attendance</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">Internal Attendance</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <a href="legacy_router.php?file=biometric-machine.php" class="attendance-bridge-status attendance-bridge-status-<?php echo htmlspecialchars((string)($attendanceBridgeStatus['class'] ?? 'secondary'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars((string)($attendanceBridgeStatus['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="<?php echo htmlspecialchars((string)($attendanceBridgeStatus['icon'] ?? 'feather-help-circle'), ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span><?php echo htmlspecialchars((string)($attendanceBridgeStatus['label'] ?? 'Bridge Unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (isset($attendanceBridgeStatus['age_seconds']) && $attendanceBridgeStatus['age_seconds'] !== null): ?>
                            <small><?php echo (int)$attendanceBridgeStatus['age_seconds']; ?>s</small>
                        <?php endif; ?>
                    </a>
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
                            <a href="external-attendance.php" class="btn btn-light-brand">
                                <i class="feather-briefcase me-2"></i>
                                <span>External Attendance</span>
                            </a>
                            <button type="button" class="btn btn-primary" id="manualSyncMachineButton">
                                <i class="feather-refresh-cw me-2"></i>
                                <span>Sync Machine</span>
                            </button>
                            <form method="post" action="legacy_router.php?file=f20h_offline_excel_import.php" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2">
                                <label class="btn btn-light-brand mb-0" for="f20hOfflineReportFile">
                                    <i class="feather-upload-cloud me-2"></i>
                                    <span>Import F20H Excel</span>
                                </label>
                                <input type="file" id="f20hOfflineReportFile" name="f20h_report_file" class="d-none" accept=".xls,application/vnd.ms-excel" onchange="if (this.files && this.files.length) { this.form.submit(); }">
                            </form>
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
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="present">
                                        <i class="feather-check-square me-3"></i>
                                        <span>Present</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="late">
                                        <i class="feather-clock me-3"></i>
                                        <span>Late</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="absent">
                                        <i class="feather-x-circle me-3"></i>
                                        <span>Absent</span>
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
                                    <?php $attendancePrintQuery = http_build_query(array_merge($_GET, ['print' => 'list'])); ?>
                                    <a href="attendance.php?<?php echo htmlspecialchars($attendancePrintQuery, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="dropdown-item">
                                        <i class="bi bi-printer me-3"></i>
                                        <span>Print</span>
                                    </a>
                                </div>
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
            <?php if (!empty($biotern_attendance_sandbox)): ?>
                <div class="alert alert-info mx-3" role="alert">
                    Test Attendance sandbox. This page uses the same data as Internal Attendance so display changes can be reviewed here before changing the original workflow.
                </div>
            <?php endif; ?>
            <?php if (!empty($missingScheduleAttendances)): ?>
                <?php
                $missingScheduleLabels = [];
                foreach ($missingScheduleAttendances as $attendance) {
                    $studentLabel = trim((string)($attendance['first_name'] ?? '') . ' ' . (string)($attendance['last_name'] ?? ''));
                    $sectionLabel = biotern_format_section_label(
                        (string)($attendance['section_code'] ?? ''),
                        (string)($attendance['section_name'] ?? '')
                    );
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
            <div class="collapse show" id="attendanceFilterCollapse">
                <div class="row mb-3 px-3">
                    <div class="col-12">
                        <div class="filter-panel filter-panel-compact">
                            <form method="GET" action="attendance.php" class="filter-form row g-2 align-items-end" id="attendanceFilterForm">
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-date">Date</label>
                            <input id="filter-date" type="date" name="date" class="form-control" value="<?php
echo htmlspecialchars((string)$filter_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-status">Status</label>
                            <select id="filter-status" name="status" class="form-control">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="early" <?php echo $filter_status === 'early' ? 'selected' : ''; ?>>Early</option>
                                <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending_correction" <?php echo $filter_status === 'pending_correction' ? 'selected' : ''; ?>>Needs Correction</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-source">Source</label>
                            <select id="filter-source" name="source" class="form-control">
                                <option value="all" <?php echo $filter_source === 'all' ? 'selected' : ''; ?>>All Sources</option>
                                <option value="manual" <?php echo $filter_source === 'manual' ? 'selected' : ''; ?>>Manual</option>
                                <option value="biometric" <?php echo $filter_source === 'biometric' ? 'selected' : ''; ?>>Biometric</option>
                                <option value="external-biometric" <?php echo $filter_source === 'external-biometric' ? 'selected' : ''; ?>>External Biometric</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-reports">Reports</label>
                            <select id="filter-reports" name="reports" class="form-control">
                                <option value="all" <?php echo $filter_reports === 'all' ? 'selected' : ''; ?>>All Reports</option>
                                <option value="internal_dtr" <?php echo $filter_reports === 'internal_dtr' ? 'selected' : ''; ?>>Internal DTR / Print</option>
                                <option value="external_queue" <?php echo $filter_reports === 'external_queue' ? 'selected' : ''; ?>>External Queue</option>
                                <option value="proof" <?php echo $filter_reports === 'proof' ? 'selected' : ''; ?>>With Proof</option>
                            </select>
                        </div>
                        <div class="col-sm-2 filter-form-actions">
                            <a href="attendance.php" class="btn btn-outline-secondary">Reset</a>
                            <button type="submit" class="btn btn-primary">Apply</button>
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

                        <div class="card stretch stretch-full attendance-table-card app-data-card app-data-toolbar">
                            <div class="card-body p-0">
                                <div class="table-responsive app-data-table-wrap">
                                    <table class="table table-hover app-data-table" id="attendanceList">
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
                                                <th>Photo</th>
                                                <th>Reports</th>
                                                <th>Notes</th>
                                                <th>Review</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
if (!empty($attendances)): ?>
                                                <?php
foreach ($attendances as $index => $attendance): ?>
                                                    <tr class="single-item">
                                                        <td data-label="Select">
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
                                                        <td data-label="Student Name">
                                                            <a href="students-internal-dtr.php?id=<?php
echo $attendance['student_id']; ?>" class="hstack gap-3">
                                                                <?php
$pp = $attendance['profile_picture'] ?? '';
                                                                $pp_url = resolve_attendance_profile_image_url((string)$pp, (int)($attendance['user_id'] ?? 0));
                                                                if ($pp_url !== null) {
                                                                    echo '<div class="avatar-image avatar-md"><img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid"></div>';
                                                                } else {
                                                                    echo '<div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1)) . '</div></div>';
                                                                }
                                                                ?>
                                                                <div>
                                                                    <span class="text-truncate-1-line fw-bold"><?php
echo ($attendance['first_name'] ?? 'N/A') . ' ' . ($attendance['last_name'] ?? 'N/A'); ?><?php if ((int)($attendance['is_sa_student'] ?? 0) === 1): ?> <span class="badge bg-soft-primary text-primary ms-1">SA</span><?php endif; ?></span>
                                                                    <span class="fs-12 text-muted d-block"><?php
echo $attendance['student_number'] ?? 'N/A'; ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td data-label="Attendance Date"><span class="badge bg-soft-primary text-primary"><?php
echo date('Y-m-d', strtotime($attendance['attendance_date'])); ?></span></td>
                                                        <td data-label="Morning In"><?php echo attendanceDisplayTimeHtml($attendance, 'morning_time_in', 'bg-soft-success text-success'); ?></td>
                                                        <td data-label="Morning Out"><?php echo attendanceDisplayTimeHtml($attendance, 'morning_time_out', 'bg-soft-success text-success'); ?></td>
                                                        <td data-label="Afternoon In"><?php echo attendanceDisplayTimeHtml($attendance, 'afternoon_time_in', 'bg-soft-warning text-warning'); ?></td>
                                                        <td data-label="Afternoon Out"><?php echo attendanceDisplayTimeHtml($attendance, 'afternoon_time_out', 'bg-soft-warning text-warning'); ?></td>
                                                        <td data-label="Total Hours">
                                                            <?php
echo attendance_hours_cell_html($attendance); ?>
                                                        </td>
                                                        <td data-label="Status">
                                                            <?php
echo attendance_status_cell_html($attendance);
                                                            ?>
                                                        </td>
                                                        <td data-label="Source"><?php
echo getSourceBadge($attendance['source'] ?? 'manual', $attendance); ?></td>
                                                        <td data-label="Photo"><?php
echo attendance_photo_cell_html($attendance); ?></td>
                                                        <td data-label="Reports"><?php
echo attendance_reports_cell_html($attendance); ?></td>
                                                        <td data-label="Notes"><?php
echo attendance_notes_cell_html($attendance); ?></td>
                                                        <td data-label="Review">
                                                            <?php echo attendance_review_cell_html($attendance); ?>
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
