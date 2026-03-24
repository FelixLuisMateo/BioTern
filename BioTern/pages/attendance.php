<?php
require_once dirname(__DIR__) . '/config/db.php';
// Database Connection
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? DB_PORT : 3306;

if (!isset($conn) || !($conn instanceof mysqli)) {
    try {
        $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    } catch (Exception $e) {
        die("Database Error: " . $e->getMessage());
    }
}

$has_school_year_column = false;
$col_sy = $conn->query("SHOW COLUMNS FROM students LIKE 'school_year'");
if ($col_sy && $col_sy->num_rows > 0) {
    $has_school_year_column = true;
}

// Fetch Attendance Statistics
$stats_query = "
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM attendances
";
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
if ($has_school_year_column && $filter_school_year !== '' && preg_match('/^\d{4}-\d{4}$/', $filter_school_year) && in_array($filter_school_year, $school_year_options, true)) {
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
        s.supervisor_name,
        s.coordinator_name,
        c.name as course_name,
        d.name as department_name,
        u.name as approver_name
    FROM attendances a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN users u_student ON s.user_id = u_student.id
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

// Remove same-day duplicates per student (keep latest by id due ORDER BY a.id DESC).
if (count($attendances) > 1) {
    $seen_student_date = [];
    $unique_attendances = [];
    foreach ($attendances as $attendance) {
        $student_id_key = isset($attendance['student_id']) ? (string)$attendance['student_id'] : '';
        $attendance_date_key = isset($attendance['attendance_date']) ? (string)$attendance['attendance_date'] : '';
        $dedupe_key = ($student_id_key !== '' && $attendance_date_key !== '')
            ? ($student_id_key . '|' . $attendance_date_key)
            : ('id|' . (string)($attendance['id'] ?? ''));

        if (isset($seen_student_date[$dedupe_key])) {
            continue;
        }

        $seen_student_date[$dedupe_key] = true;
        $unique_attendances[] = $attendance;
    }
    $attendances = $unique_attendances;
}

// If requested via AJAX, return only the table rows HTML so frontend can replace tbody
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (!empty($attendances)) {
        foreach ($attendances as $idx => $attendance) {
            $checkboxId = 'checkBox_' . $attendance['id'] . '_' . $idx;
            echo '<tr class="single-item">';
            echo '<td><div class="item-checkbox ms-1"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input checkbox" id="' . $checkboxId . '" data-attendance-id="' . (int)$attendance['id'] . '"><label class="custom-control-label" for="' . $checkboxId . '"></label></div></div></td>';
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
            echo '<td><span class="badge bg-soft-info text-info">' . ( $attendance['break_time_in'] ? date('h:i A', strtotime($attendance['break_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-info text-info">' . ( $attendance['break_time_out'] ? date('h:i A', strtotime($attendance['break_time_out'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . ( $attendance['afternoon_time_in'] ? date('h:i A', strtotime($attendance['afternoon_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . ( $attendance['afternoon_time_out'] ? date('h:i A', strtotime($attendance['afternoon_time_out'])) : '-' ) . '</span></td>';
            $total_hours = calculateTotalHours($attendance['morning_time_in'], $attendance['morning_time_out'], $attendance['afternoon_time_in'], $attendance['afternoon_time_out']);
            echo '<td><span class="badge bg-soft-secondary text-secondary">' . $total_hours . 'h</span></td>';
            // attendance status
            $att_status = getAttendanceStatus($attendance['morning_time_in']);
            if ($att_status === 'present') {
                $status_html = '<span class="badge bg-soft-success text-success">Present</span>';
                $att_status_label = 'Present';
            } elseif ($att_status === 'late') {
                $status_html = '<span class="badge bg-soft-warning text-warning">Late</span>';
                $att_status_label = 'Late';
            } else {
                $status_html = '<span class="badge bg-soft-danger text-danger">Absent</span>';
                $att_status_label = 'Absent';
            }
            echo '<td>' . $status_html . '</td>';
            echo '<td>' . getStatusBadge($attendance['status']) . '</td>';
            $student_name = trim((string)($attendance['first_name'] ?? '') . ' ' . (string)($attendance['last_name'] ?? ''));
            $approval_status_label = ucfirst((string)($attendance['status'] ?? 'pending'));
            $morning_in_text = $attendance['morning_time_in'] ? date('h:i A', strtotime($attendance['morning_time_in'])) : '-';
            $morning_out_text = $attendance['morning_time_out'] ? date('h:i A', strtotime($attendance['morning_time_out'])) : '-';
            $break_in_text = $attendance['break_time_in'] ? date('h:i A', strtotime($attendance['break_time_in'])) : '-';
            $break_out_text = $attendance['break_time_out'] ? date('h:i A', strtotime($attendance['break_time_out'])) : '-';
            $afternoon_in_text = $attendance['afternoon_time_in'] ? date('h:i A', strtotime($attendance['afternoon_time_in'])) : '-';
            $afternoon_out_text = $attendance['afternoon_time_out'] ? date('h:i A', strtotime($attendance['afternoon_time_out'])) : '-';
            // actions (keep minimal for AJAX)
            echo '<td><div class="hstack gap-2 justify-content-end"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Details" data-attendance-action="view-details" data-student-id="' . intval($attendance['student_id']) . '"><i class="feather feather-eye"></i></a><div class="dropdown"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21"><i class="feather feather-more-horizontal"></i></a><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="javascript:void(0)" data-attendance-action="approve-individual" data-attendance-id="' . intval($attendance['id']) . '"><i class="feather feather-check-circle me-3"></i><span>Approve</span></a></li><li><a class="dropdown-item" href="javascript:void(0)" data-attendance-action="reject-individual" data-attendance-id="' . intval($attendance['id']) . '"><i class="feather feather-x-circle me-3"></i><span>Reject</span></a></li><li><a class="dropdown-item" href="javascript:void(0)" data-attendance-action="edit-attendance" data-attendance-id="' . intval($attendance['id']) . '"><i class="feather feather-edit-3 me-3"></i><span>Edit</span></a></li><li><a class="dropdown-item printBTN" href="javascript:void(0)" data-attendance-action="print-attendance" data-attendance-id="' . intval($attendance['id']) . '"><i class="feather feather-printer me-3"></i><span>Print</span></a></li><li><a class="dropdown-item" href="javascript:void(0)" data-attendance-action="send-notification" data-attendance-id="' . intval($attendance['id']) . '"><i class="feather feather-mail me-3"></i><span>Send Notification</span></a></li><li class="dropdown-divider"></li><li><a class="dropdown-item" href="javascript:void(0)" data-attendance-action="delete-individual" data-attendance-id="' . intval($attendance['id']) . '"><i class="feather feather-trash-2 me-3"></i><span>Delete</span></a></li></ul></div></div></td>';
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

// Determine attendance status based on morning_time_in
function getAttendanceStatus($morning_time_in) {
    if (!$morning_time_in) {
        return 'absent';
    }
    
    $time = strtotime($morning_time_in);
    $expected_time = strtotime('08:00 AM');
    
    if ($time <= $expected_time) {
        return 'present';
    } else {
        return 'late';
    }
}
?>
<?php
$page_title = 'BioTern || Student Attendance';
$page_body_class = 'page-attendance';
$page_styles = array(
    'assets/css/layout/page_shell.css',
    'assets/css/pages/pages-attendance-page.css'
);
$page_scripts = array(
    'assets/js/theme-customizer-init.min.js',
    'assets/js/pages/pages-attendance-runtime.js',
);
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
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">Attendance DTR</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex d-md-none">
                            <a href="javascript:void(0)" class="page-header-right-close-toggle">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back</span>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseAttendanceStats">
                                <i class="feather-bar-chart"></i>
                            </a>
                            <button type="button" class="btn filter-toggle-btn" data-bs-toggle="collapse" data-bs-target="#attendanceFilterCollapse" aria-expanded="false" aria-controls="attendanceFilterCollapse">
                                <i class="feather-filter me-2"></i>
                                <span>Filters</span>
                            </button>
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-filter"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter app-attendance-filter-link" data-type="period" data-value="today">
                                        <i class="feather-calendar me-3"></i>
                                        <span>Today</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter app-attendance-filter-link" data-type="period" data-value="week">
                                        <i class="feather-calendar me-3"></i>
                                        <span>This Week</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter app-attendance-filter-link" data-type="period" data-value="month">
                                        <i class="feather-calendar me-3"></i>
                                        <span>This Month</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter app-attendance-filter-link" data-type="status" data-value="approved">
                                        <i class="feather-check-circle me-3"></i>
                                        <span>Approved</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter app-attendance-filter-link" data-type="status" data-value="pending">
                                        <i class="feather-clock me-3"></i>
                                        <span>Pending</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter app-attendance-filter-link" data-type="status" data-value="rejected">
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
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
                    </div>
                </div>
            </div>

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
                            <form method="GET" class="filter-form app-attendance-filter-form row g-2 align-items-end" id="attendanceFilterForm">
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-school-year">School Year</label>
                                    <select id="filter-school-year" name="school_year" class="form-control">
                                        <option value="">-- All School Years --</option>
                                        <?php foreach ($school_year_options as $school_year): ?>
                                            <option value="<?php echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_school_year === $school_year ? 'selected' : ''; ?>><?php echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-date">Date</label>
                                    <input id="filter-date" type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($_GET['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-course">Course</label>
                                    <select id="filter-course" name="course_id" class="form-control">
                                        <option value="0">-- All Courses --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo (int)$course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-department">Department</label>
                                    <select id="filter-department" name="department_id" class="form-control">
                                        <option value="0">-- All Departments --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo (int)$dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-section">Section</label>
                                    <select id="filter-section" name="section_id" class="form-control">
                                        <option value="0">-- All Sections --</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo (int)$section['id']; ?>" <?php echo $filter_section == $section['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($section['section_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-supervisor">Supervisor</label>
                                    <select id="filter-supervisor" name="supervisor" class="form-control">
                                        <option value="">-- Any Supervisor --</option>
                                        <?php foreach ($supervisors as $sup): ?>
                                            <option value="<?php echo htmlspecialchars($sup, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label" for="filter-coordinator">Coordinator</label>
                                    <select id="filter-coordinator" name="coordinator" class="form-control">
                                        <option value="">-- Any Coordinator --</option>
                                        <?php foreach ($coordinators as $coor): ?>
                                            <option value="<?php echo htmlspecialchars($coor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php echo htmlspecialchars($coor, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['approved_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-success text-success">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php echo $stats['total_count'] > 0 ? round(($stats['approved_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['pending_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-warning text-warning">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php echo $stats['total_count'] > 0 ? round(($stats['pending_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['rejected_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-danger text-danger">
                                            <i class="feather-arrow-down fs-10 me-1"></i>
                                            <span><?php echo $stats['total_count'] > 0 ? round(($stats['rejected_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['total_count'] ?? 0; ?></span>
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
            <div class="row mb-2 px-3 attendance-bulk-toolbar-hidden app-attendance-bulk-toolbar-hidden" id="bulkActionsToolbar">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bulk-toolbar app-attendance-bulk-toolbar">
                        <span class="fs-6 attendance-selected-label app-attendance-selected-label">
                            <i class="feather feather-check me-1 attendance-selected-icon app-attendance-selected-icon"></i>
                            <strong id="selectedCount" class="attendance-selected-count app-attendance-selected-count">0</strong> record(s) selected
                        </span>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-success py-1 px-2" data-attendance-action="bulk-action" data-bulk-action="approve" title="Approve selected">
                                <i class="feather feather-check fs-8 me-1"></i> Approve
                            </button>
                            <button type="button" class="btn btn-sm btn-warning py-1 px-2" data-attendance-action="bulk-action" data-bulk-action="reject" title="Reject selected">
                                <i class="feather feather-x fs-8 me-1"></i> Reject
                            </button>
                            <button type="button" class="btn btn-sm btn-danger py-1 px-2" data-attendance-action="bulk-action" data-bulk-action="delete" title="Delete selected">
                                <i class="feather feather-trash-2 fs-8 me-1"></i> Delete
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" data-attendance-action="clear-selection">
                                <i class="feather feather-x fs-8 me-1"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="main-content app-attendance-main-content">
                <div class="row">
                    <div class="col-lg-12">

                        <div class="card stretch stretch-full attendance-table-card app-attendance-table-card">
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
                                                <th>Break In</th>
                                                <th>Break Out</th>
                                                <th>Afternoon In</th>
                                                <th>Afternoon Out</th>
                                                <th>Total Hours</th>
                                                <th>Status</th>
                                                <th>Approval Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($attendances)): ?>
                                                <?php foreach ($attendances as $index => $attendance): ?>
                                                    <tr class="single-item">
                                                        <td>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php echo (int)$attendance['id']; ?>_<?php echo (int)$index; ?>" data-attendance-id="<?php echo (int)$attendance['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php echo (int)$attendance['id']; ?>_<?php echo (int)$index; ?>"></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a href="students-view.php?id=<?php echo $attendance['student_id']; ?>" class="hstack gap-3">
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
                                                                    <span class="text-truncate-1-line fw-bold"><?php echo ($attendance['first_name'] ?? 'N/A') . ' ' . ($attendance['last_name'] ?? 'N/A'); ?></span>
                                                                    <span class="fs-12 text-muted d-block"><?php echo $attendance['student_number'] ?? 'N/A'; ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td><span class="badge bg-soft-primary text-primary"><?php echo date('Y-m-d', strtotime($attendance['attendance_date'])); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php echo formatTime($attendance['morning_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php echo formatTime($attendance['morning_time_out']); ?></span></td>
                                                        <td><span class="badge bg-soft-info text-info"><?php echo formatTime($attendance['break_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-info text-info"><?php echo formatTime($attendance['break_time_out']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php echo formatTime($attendance['afternoon_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php echo formatTime($attendance['afternoon_time_out']); ?></span></td>
                                                        <td>
                                                            <span class="badge bg-soft-secondary text-secondary">
                                                                <?php echo calculateTotalHours($attendance['morning_time_in'], $attendance['morning_time_out'], $attendance['afternoon_time_in'], $attendance['afternoon_time_out']); ?>h
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                $att_status = getAttendanceStatus($attendance['morning_time_in']);
                                                                if ($att_status === 'present') {
                                                                    echo '<span class="badge bg-soft-success text-success">Present</span>';
                                                                } elseif ($att_status === 'late') {
                                                                    echo '<span class="badge bg-soft-warning text-warning">Late</span>';
                                                                } else {
                                                                    echo '<span class="badge bg-soft-danger text-danger">Absent</span>';
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo getStatusBadge($attendance['status']); ?></td>
                                                        <td>
                                                            <div class="hstack gap-2 justify-content-end">
                                                                <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Details" data-attendance-action="view-details" data-student-id="<?php echo (int)$attendance['student_id']; ?>">
                                                                    <i class="feather feather-eye"></i>
                                                                </a>
                                                                <div class="dropdown">
                                                                    <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                                        <i class="feather feather-more-horizontal"></i>
                                                                    </a>
                                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" data-attendance-action="approve-individual" data-attendance-id="<?php echo intval($attendance['id']); ?>">
                                                                                <i class="feather feather-check-circle me-3"></i>
                                                                                <span>Approve</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" data-attendance-action="reject-individual" data-attendance-id="<?php echo intval($attendance['id']); ?>">
                                                                                <i class="feather feather-x-circle me-3"></i>
                                                                                <span>Reject</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" data-attendance-action="edit-attendance" data-attendance-id="<?php echo $attendance['id']; ?>">
                                                                                <i class="feather feather-edit-3 me-3"></i>
                                                                                <span>Edit</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item printBTN" href="javascript:void(0)" data-attendance-action="print-attendance" data-attendance-id="<?php echo $attendance['id']; ?>">
                                                                                <i class="feather feather-printer me-3"></i>
                                                                                <span>Print</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" data-attendance-action="send-notification" data-attendance-id="<?php echo $attendance['id']; ?>">
                                                                                <i class="feather feather-mail me-3"></i>
                                                                                <span>Send Notification</span>
                                                                            </a>
                                                                        </li>
                                                                        <li class="dropdown-divider"></li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" data-attendance-action="delete-individual" data-attendance-id="<?php echo intval($attendance['id']); ?>">
                                                                                <i class="feather feather-trash-2 me-3"></i>
                                                                                <span>Delete</span>
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
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
                        <div class="col-md-2"><small class="text-muted d-block">Morning In</small><strong id="view_morning_in">-</strong></div>
                        <div class="col-md-2"><small class="text-muted d-block">Morning Out</small><strong id="view_morning_out">-</strong></div>
                        <div class="col-md-2"><small class="text-muted d-block">Break In</small><strong id="view_break_in">-</strong></div>
                        <div class="col-md-2"><small class="text-muted d-block">Break Out</small><strong id="view_break_out">-</strong></div>
                        <div class="col-md-2"><small class="text-muted d-block">Afternoon In</small><strong id="view_afternoon_in">-</strong></div>
                        <div class="col-md-2"><small class="text-muted d-block">Afternoon Out</small><strong id="view_afternoon_out">-</strong></div>
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
                    <div class="confirm-remarks-wrap app-remarks-hidden">
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
    <!-- Dark/light toggle runtime moved to assets/js/theme-preferences-runtime.js -->
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>







