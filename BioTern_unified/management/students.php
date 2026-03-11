<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$current_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
$current_user_role_badge = trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$current_profile_rel = ltrim(str_replace('\\', '/', trim((string)($_SESSION['profile_picture'] ?? ''))), '/');
$current_profile_img = 'assets/images/avatar/' . (($current_user_id > 0 ? ($current_user_id % 5) : 0) + 1) . '.png';
if ($current_profile_rel !== '' && file_exists(dirname(__DIR__) . '/' . $current_profile_rel)) {
    $current_profile_img = $current_profile_rel;
}

$current_role = strtolower(trim((string) (
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    $_SESSION['user_type'] ??
    $_SESSION['type'] ??
    ''
)));
$is_student_user = ($current_role === 'student');

// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch Students Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_students,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_students,
        SUM(CASE WHEN biometric_registered = 1 THEN 1 ELSE 0 END) as biometric_registered
    FROM students s
    LEFT JOIN users u ON u.id = s.user_id
    WHERE COALESCE(u.application_status, 'approved') = 'approved'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Prepare filter inputs
$filter_date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$filter_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$filter_school_year = isset($_GET['school_year']) ? trim((string)$_GET['school_year']) : '';
$filter_supervisor = isset($_GET['supervisor']) ? trim($_GET['supervisor']) : '';
$filter_coordinator = isset($_GET['coordinator']) ? trim($_GET['coordinator']) : '';
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : -1;

$school_year_options = [];
$school_year_start = 2005;
$current_calendar_month = (int)date('n');
$current_calendar_year = (int)date('Y');
$current_school_year_start = $current_calendar_month >= 7 ? $current_calendar_year : ($current_calendar_year - 1);
$latest_school_year_start = max(2025, $current_school_year_start);
for ($year = $latest_school_year_start; $year >= $school_year_start; $year--) {
    $school_year_options[] = sprintf('%d-%d', $year, $year + 1);
}

$db_esc = $conn->real_escape_string($db_name);

// Fetch dropdown lists
$courses = [];
// Determine which column exists for active flag on courses to avoid schema mismatch errors
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

// Build WHERE clauses depending on provided filters
$where = [];
$where[] = "COALESCE(u_student.application_status, 'approved') = 'approved'";
if ($filter_date !== '') {
    // Filter students that have attendance logs on the selected date.
    $safe_date = $conn->real_escape_string($filter_date);
    $where[] = "EXISTS (
        SELECT 1 FROM attendances a_date
        WHERE a_date.student_id = s.id
          AND a_date.attendance_date = '{$safe_date}'
    )";
}
if ($filter_course > 0) {
    $where[] = "s.course_id = " . intval($filter_course);
}
if ($filter_department > 0) {
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
if ($filter_status >= 0) {
    if (intval($filter_status) === 1) {
        $where[] = "EXISTS (
            SELECT 1 FROM attendances a_live
            WHERE a_live.student_id = s.id
              AND a_live.attendance_date = CURDATE()
              AND (
                    (a_live.morning_time_in IS NOT NULL AND a_live.morning_time_out IS NULL)
                 OR (a_live.afternoon_time_in IS NOT NULL AND a_live.afternoon_time_out IS NULL)
              )
        )";
    } else {
        $where[] = "NOT EXISTS (
            SELECT 1 FROM attendances a_live
            WHERE a_live.student_id = s.id
              AND a_live.attendance_date = CURDATE()
              AND (
                    (a_live.morning_time_in IS NOT NULL AND a_live.morning_time_out IS NULL)
                 OR (a_live.afternoon_time_in IS NOT NULL AND a_live.afternoon_time_out IS NULL)
              )
        )";
    }
}

// Fetch Students with Related Information
$students_query = "
    SELECT 
        s.id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.email,
        s.phone,
        s.status,
        CASE
            WHEN EXISTS (
                SELECT 1 FROM attendances a_live
                WHERE a_live.student_id = s.id
                  AND a_live.attendance_date = CURDATE()
                  AND (
                        (a_live.morning_time_in IS NOT NULL AND a_live.morning_time_out IS NULL)
                     OR (a_live.afternoon_time_in IS NOT NULL AND a_live.afternoon_time_out IS NULL)
                  )
            ) THEN 1
            ELSE 0
        END as live_clock_status,
        s.biometric_registered,
        s.created_at,
        COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        c.name as course_name,
        COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name,
        c.id as course_id,
        i.supervisor_id,
        i.coordinator_id,
        COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', sup.first_name, sup.middle_name, sup.last_name)), ''),
            NULLIF(TRIM(s.supervisor_name), ''),
            '-'
        ) AS supervisor_name,
        COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', coor.first_name, coor.middle_name, coor.last_name)), ''),
            NULLIF(TRIM(s.coordinator_name), ''),
            '-'
        ) AS coordinator_name
    FROM students s
    LEFT JOIN users u_student ON s.user_id = u_student.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    LEFT JOIN supervisors sup ON i.supervisor_id = sup.id
    LEFT JOIN coordinators coor ON i.coordinator_id = coor.id
    " . (count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "") . "
    ORDER BY s.first_name ASC
    LIMIT 100
";
$students_result = $conn->query($students_query);
$students = [];
if ($students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Helper function to get status badge
function getStatusBadge($status) {
    if ($status == 1) {
        return '<span class="badge bg-soft-success text-success">Active</span>';
    } else {
        return '<span class="badge bg-soft-danger text-danger">Inactive</span>';
    }
}

// Helper function to format date
function formatDate($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return '-';
}

function resolve_profile_image_url(string $profilePath): ?string {
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

$selected_section_label = 'ALL';
if ($filter_section > 0) {
    foreach ($sections as $sec) {
        if ((int)$sec['id'] === (int)$filter_section) {
            $selected_section_label = (string)$sec['section_label'];
            break;
        }
    }
}

$selected_adviser = 'N/A';
if ($filter_supervisor !== '') {
    $selected_adviser = $filter_supervisor;
} else {
    foreach ($students as $srow) {
        $candidate = trim((string)($srow['supervisor_name'] ?? ''));
        if ($candidate !== '' && $candidate !== '-') {
            $selected_adviser = $candidate;
            break;
        }
    }
}

$print_students = $students;
usort($print_students, function ($a, $b) {
    $a_last = strtolower((string)($a['last_name'] ?? ''));
    $b_last = strtolower((string)($b['last_name'] ?? ''));
    if ($a_last === $b_last) {
        return strcasecmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? ''));
    }
    return strcmp($a_last, $b_last);
});

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
    <title>BioTern || Students</title>
    <link rel="shortcut icon" type="image/x-icon" href="/BioTern/BioTern_unified/assets/images/favicon.ico?v=20260310">
    <script src="assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/dataTables.bs5.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/layout-shared-overrides.css">
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
        }
        footer.footer {
            margin-top: auto;
        }
        
        /* Dark mode select and Select2 styling */
        select.form-control,
        select.form-select,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            color: #333;
            background-color: #ffffff;
        }
        
        /* Dark mode support for Select2 - using app-skin-dark class */
        html.app-skin-dark .select2-container--default .select2-selection--single,
        html.app-skin-dark .select2-container--default .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0 !important;
        }
        
        /* Dark mode dropdown menu */
        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-results__option {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
        }
        
        html.app-skin-dark .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea !important;
            color: #ffffff !important;
        }
        
        html.app-skin-dark select.form-control,
        html.app-skin-dark select.form-select {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark select.form-control option,
        html.app-skin-dark select.form-select option {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
        }

        /* Filter row alignment */
        .filter-form {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 0.65rem;
            align-items: end;
        }

        .filter-form > [class*="col-"] {
            width: 100%;
            max-width: 100%;
            padding-right: 0;
            padding-left: 0;
        }

        .filter-form .form-label {
            margin-bottom: 0.35rem;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #334155;
        }

        .filter-form .form-control,
        .filter-form .form-select,
        .filter-form .select2-container .select2-selection--single {
            min-height: 42px;
        }

        /* Calendar input design */
        .filter-form input[type="date"].form-control {
            min-height: 42px;
            border-radius: 8px;
            padding-right: 2.25rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-form input[type="date"].form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.2);
        }

        html.app-skin-dark .filter-form input[type="date"].form-control {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-calendar-picker-indicator {
            filter: invert(1) brightness(1.2);
            opacity: 0.9;
            cursor: pointer;
        }

        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-text,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-month-field,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-day-field,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-year-field {
            color: #f0f0f0;
        }

        .filter-form .select2-container .select2-selection--single {
            min-height: 42px;
            display: flex;
            align-items: center;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
        }

        .filter-form select.form-control {
            text-align: left;
            text-align-last: left;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            text-align: left;
            padding-left: 0.15rem;
            padding-right: 1.75rem;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .filter-form .filter-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.55rem;
        }

        .filter-form .filter-actions .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .filter-panel {
            border: 1px solid #dfe7f3;
            border-radius: 14px;
            padding: 1rem 1rem 0.4rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .filter-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid #e5edf7;
        }

        .filter-panel-head-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .filter-panel-label {
            font-size: 0.78rem;
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
            font-size: 0.78rem;
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

        html.app-skin-dark .filter-form .form-label {
            color: #cbd5e1;
        }

        @media (max-width: 767.98px) {
            .filter-panel {
                padding: 0.85rem 0.75rem 0.25rem;
            }

            .filter-form {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

        @media (max-width: 575.98px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        /* Match ALL filter dropboxes with date picker color */
        html.app-skin-dark .filter-form select.form-control,
        html.app-skin-dark .filter-form select.form-select,
        html.app-skin-dark .filter-form .select2-container--default .select2-selection--single {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0 !important;
        }

        html.app-skin-dark .filter-form .select2-container--default.select2-container--open .select2-dropdown,
        html.app-skin-dark .filter-form .select2-results__option {
            background-color: #2d3748 !important;
            color: #f0f0f0 !important;
            border-color: #4a5568 !important;
        }

        /* Keep all filter dropdown layers behind sticky page walls while scrolling */
        .nxl-header {
            z-index: 3000 !important;
        }

        .page-header {
            z-index: 2900 !important;
        }

        .filter-form,
        .filter-form .select2-container,
        .filter-form .select2-container--open,
        .filter-form .select2-container--open .select2-dropdown {
            z-index: 900 !important;
        }

        .filter-form .select2-container--open .select2-dropdown--above {
            top: calc(100% - 1px) !important;
            bottom: auto !important;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }

        .filter-form .select2-container--open.select2-container--above .select2-selection--single,
        .filter-form .select2-container--open.select2-container--above .select2-selection--multiple {
            border-top-left-radius: 0.5rem !important;
            border-top-right-radius: 0.5rem !important;
            border-bottom-left-radius: 0.5rem !important;
            border-bottom-right-radius: 0.5rem !important;
        }

        /* Keep filter/select controls below sidebar when mobile nav is open */
        @media (max-width: 1024px) {
            .nxl-navigation,
            .nxl-navigation.mob-navigation-active {
                z-index: 4000 !important;
            }

            .select2-container,
            .select2-container--open,
            .select2-dropdown,
            .filter-form,
            .nxl-content .row.mb-3 {
                z-index: 1 !important;
                position: relative;
            }
        }

        /* Footer layout fix */
        .footer {
            flex-wrap: wrap;
            row-gap: 0.5rem;
            column-gap: 1rem;
        }

        .footer .footer-meta {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .footer .footer-links {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        @media (max-width: 575.98px) {
            .footer {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .student-list-print-sheet {
            display: none;
        }

        @media print {
            body * {
                visibility: hidden !important;
            }

            .student-list-print-sheet,
            .student-list-print-sheet * {
                visibility: visible !important;
            }

            .student-list-print-sheet {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #ffffff;
                color: #111111;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px;
                padding: 18px 24px;
            }

            .student-list-print-sheet .header {
                position: relative;
                min-height: 0.9in;
                text-align: center;
                border-bottom: 1px solid #8ab0e6;
                padding: 0.08in 0 0.06in 0;
                margin-bottom: 14px;
            }

            .student-list-print-sheet .crest {
                position: absolute;
                top: 0.22in;
                left: 0.22in;
                width: 0.77in;
                height: 0.76in;
                object-fit: contain;
            }

            .student-list-print-sheet .header h2 {
                font-family: Calibri, Arial, sans-serif;
                color: #1b4f9c;
                font-size: 14pt;
                margin: 6px 0 2px 0;
                font-weight: 700;
                text-transform: uppercase;
            }

            .student-list-print-sheet .header .meta {
                font-family: Calibri, Arial, sans-serif;
                color: #1b4f9c;
                font-size: 10pt;
            }

            .student-list-print-sheet .header .tel {
                font-family: Calibri, Arial, sans-serif;
                color: #1b4f9c;
                font-size: 12pt;
            }

            .student-list-print-sheet .print-title {
                text-align: center;
                font-size: 34px;
                letter-spacing: 1px;
                font-weight: 700;
                margin: 26px 0 22px;
            }

            .student-list-print-sheet .print-meta {
                margin-bottom: 14px;
                font-size: 13px;
            }

            .student-list-print-sheet .print-meta strong {
                min-width: 76px;
                display: inline-block;
            }

            .student-list-print-sheet table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }

            .student-list-print-sheet th,
            .student-list-print-sheet td {
                border: 1px solid #d9d9d9;
                padding: 8px 8px;
                text-align: left;
            }

            .student-list-print-sheet th {
                text-transform: uppercase;
                font-weight: 700;
                background: #f8f8f8;
            }

            .student-list-print-sheet td.col-index,
            .student-list-print-sheet th.col-index {
                width: 46px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <section class="student-list-print-sheet">
        <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
        <div class="header">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
            <div class="tel">Telefax No.: (045) 624-0215</div>
        </div>
        <div class="print-title">STUDENT SECTION LIST</div>
        <div class="print-meta"><strong>SECTION:</strong> <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($selected_section_label); ?></div>
        <div class="print-meta"><strong>ADVISER:</strong> <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($selected_adviser); ?></div>
        <table>
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th>Student No.</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
if (!empty($print_students)): ?>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($print_students as $i => $student): ?>
                        <tr>
                            <td class="col-index"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$i + 1; ?></td>
                            <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($student['student_id'] ?? '')); ?></td>
                            <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($student['last_name'] ?? '')); ?></td>
                            <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($student['first_name'] ?? '')); ?></td>
                            <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($student['middle_name'] ?? '')); ?></td>
                            <td></td>
                        </tr>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                    <tr>
                        <td class="col-index">1</td>
                        <td colspan="5">No students found for current filter.</td>
                    </tr>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
            </tbody>
        </table>
    </section>

    <?php
require_once dirname(__DIR__) . '/config/db.php';
include_once dirname(__DIR__) . '/includes/navigation.php'; ?>

    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
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
                                <input type="text" class="form-control search-input-field" placeholder="Search....">
                                <span class="input-group-text">
                                    <button type="button" class="btn-close"></button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
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
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($current_profile_img, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($current_profile_img, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                    <div>
                                        <h6 class="text-dark mb-0">
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($current_user_name, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($current_user_role_badge !== ''): ?>
                                                <span class="badge bg-soft-success text-success ms-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(ucfirst($current_user_role_badge), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </h6>
                                        <span class="fs-12 fw-medium text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($current_user_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
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
        </div>
    </header>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Students</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">Students</li>
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
                            <button type="button" class="btn btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                <i class="feather-bar-chart me-2"></i>
                                <span>Statistics</span>
                            </button>
                            <button type="button" class="btn filter-toggle-btn" data-bs-toggle="collapse" data-bs-target="#studentsFilterCollapse" aria-expanded="false" aria-controls="studentsFilterCollapse">
                                <i class="feather-filter me-2"></i>
                                <span>Filters</span>
                            </button>
                            <div class="dropdown">
                                <a class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside" role="button" aria-label="Export options">
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
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="bi bi-filetype-txt me-3"></i>
                                        <span>Text</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="bi bi-filetype-exe me-3"></i>
                                        <span>Excel</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item js-print-page">
                                        <i class="bi bi-printer me-3"></i>
                                        <span>Print</span>
                                    </a>
                                </div>
                            </div>
                            <button type="button" class="btn btn-light js-print-page">
                                <i class="feather-printer me-2"></i>
                                <span>Print List</span>
                            </button>
                            <a href="students-create.php" class="btn btn-primary">
                                <i class="feather-plus me-2"></i>
                                <span>Create Students</span>
                            </a>
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
            <div class="collapse" id="studentsFilterCollapse">
                <div class="row mb-3 px-3">
                    <div class="col-12">
                        <div class="filter-panel">
                            <div class="filter-panel-head">
                                <div>
                                    <div class="filter-panel-label">
                                        <i class="feather-sliders"></i>
                                        <span>Filter Students</span>
                                    </div>
                                    <p class="filter-panel-sub">Narrow down results by school year, date, course, section, supervisor, and coordinator.</p>
                                </div>
                                <div class="filter-panel-head-actions">
                                    <a href="students.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                                </div>
                            </div>
                            <form method="GET" class="filter-form row g-2 align-items-end" id="studentsFilterForm">
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
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
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-date">Date</label>
                            <input id="filter-date" type="date" name="date" class="form-control" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
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
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
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
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
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
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
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
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
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

            <!--! Statistics !-->
            <div id="collapseOne" class="accordion-collapse collapse page-header-collapse">
                <div class="accordion-body pb-2">
                    <div class="row">
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-users"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Total Students</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['total_students'] ? $stats['total_students'] : '0'; ?></span>
                                            </a>
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
                                                <i class="feather-user-check"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Active Students</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['active_students'] ? $stats['active_students'] : '0'; ?></span>
                                            </a>
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
                                                <i class="feather-user-minus"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Inactive Students</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['inactive_students'] ? $stats['inactive_students'] : '0'; ?></span>
                                            </a>
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
                                                <i class="feather-check-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Biometric Registered</span>
                                                <span class="fs-24 fw-bolder d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $stats['biometric_registered'] ? $stats['biometric_registered'] : '0'; ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="customerList">
                                        <thead>
                                            <tr>
                                                <th class="wd-30">
                                                    <div class="btn-group mb-1">
                                                        <div class="custom-control custom-checkbox ms-1">
                                                            <input type="checkbox" class="custom-control-input" id="checkAllStudent">
                                                            <label class="custom-control-label" for="checkAllStudent"></label>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th>Name</th>
                                                <th>Student ID</th>
                                                <th>Course</th>
                                                <th>Section</th>
                                                <th>Supervisor</th>
                                                <th>Coordinator</th>
                                                <th>Last Logged</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if (count($students) > 0): ?>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($students as $index => $student): ?>
                                                    <tr class="single-item">
                                                        <td>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>"></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a href="students-view.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>" class="hstack gap-3">
                                                                <div class="avatar-image avatar-md">
                                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
$pp = $student['profile_picture'] ?? '';
                                                                    $pp_url = resolve_profile_image_url($pp);
                                                                    if ($pp_url !== null) {
                                                                        echo '<img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid">';
                                                                    } else {
                                                                        echo '<img src="assets/images/avatar/' . (($index % 5) + 1) . '.png" alt="" class="img-fluid">';
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div>
                                                                    <span class="text-truncate-1-line"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td><a href="students-view.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['student_id']); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['section_name'] ?? '-'); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['supervisor_name'] ?? '-'); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['coordinator_name'] ?? '-'); ?></a></td>
                                                        <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatDate($student['created_at']); ?></td>
                                                        <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo getStatusBadge($student['live_clock_status']); ?></td>
                                                        <td>
                                                            <div class="hstack gap-2 justify-content-end">
                                                                <a href="students-view.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>" class="avatar-text avatar-md" title="View">
                                                                    <i class="feather feather-eye"></i>
                                                                </a>
                                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if (!$is_student_user): ?>
                                                                    <div class="dropdown">
                                                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                                            <i class="feather feather-more-horizontal"></i>
                                                                        </a>
                                                                        <ul class="dropdown-menu">
                                                                            <li>
                                                                                <a class="dropdown-item" href="javascript:void(0)">
                                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                                    <span>Edit</span>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item printBTN" href="javascript:void(0)">
                                                                                    <i class="feather feather-printer me-3"></i>
                                                                                    <span>Print</span>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="javascript:void(0)">
                                                                                    <i class="feather feather-clock me-3"></i>
                                                                                    <span>Remind</span>
                                                                                </a>
                                                                            </li>
                                                                            <li class="dropdown-divider"></li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="javascript:void(0)">
                                                                                    <i class="feather feather-archive me-3"></i>
                                                                                    <span>Archive</span>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="javascript:void(0)">
                                                                                    <i class="feather feather-alert-octagon me-3"></i>
                                                                                    <span>Report Spam</span>
                                                                                </a>
                                                                            </li>
                                                                            <li class="dropdown-divider"></li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="javascript:void(0)">
                                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                                    <span>Delete</span>
                                                                                </a>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                                            </div>
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
        </div>
        <!-- [ Footer ] start -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p class="footer-meta fs-12 mb-0">
                <span>By: <a href="javascript:void(0);">ACT 2A</a></span>
                <span>Distributed by: <a href="javascript:void(0);">Group 5</a></span>
            </p>
            <div class="footer-links">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
        <!-- [ Footer ] end -->
    </main>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/dataTables.min.js"></script>
    <script src="assets/vendors/js/dataTables.bs5.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/customers-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Do not reinitialize DataTable here; `customers-init.min.js` handles it.
            if (window.jQuery) {
                var $filterForm = $('#studentsFilterForm');
                ['#filter-course', '#filter-department', '#filter-section', '#filter-school-year'].forEach(function (selector) {
                    if ($(selector).length) {
                        $(selector).select2({
                            width: '100%',
                            allowClear: false,
                            dropdownAutoWidth: false,
                            minimumResultsForSearch: Infinity,
                            dropdownParent: $filterForm
                        });
                    }
                });

                ['#filter-supervisor', '#filter-coordinator'].forEach(function (selector) {
                    if ($(selector).length) {
                        $(selector).select2({
                            width: '100%',
                            allowClear: false,
                            dropdownAutoWidth: false,
                            dropdownParent: $filterForm
                        });
                    }
                });

                ['#filter-course', '#filter-department', '#filter-section', '#filter-school-year', '#filter-supervisor', '#filter-coordinator'].forEach(function (selector) {
                    if ($(selector).length) {
                        $(selector).on('select2:open', function () {
                            var select2Instance = $(this).data('select2');
                            if (!select2Instance || !select2Instance.$container) return;
                            select2Instance.$container
                                .removeClass('select2-container--above')
                                .addClass('select2-container--below');
                            if (select2Instance.$dropdown) {
                                select2Instance.$dropdown
                                    .removeClass('select2-dropdown--above')
                                    .addClass('select2-dropdown--below');
                            }
                        });
                    }
                });
            }

            var filterForm = document.getElementById('studentsFilterForm');
            function submitFilters() {
                if (filterForm) filterForm.submit();
            }
            ['filter-date', 'filter-course', 'filter-department', 'filter-section', 'filter-school-year', 'filter-supervisor', 'filter-coordinator'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', submitFilters);
            });
            if (window.jQuery) {
                ['#filter-course', '#filter-department', '#filter-section', '#filter-school-year', '#filter-supervisor', '#filter-coordinator'].forEach(function (selector) {
                    if ($(selector).length) {
                        $(selector).on('select2:select select2:clear', submitFilters);
                    }
                });
            }

            document.querySelectorAll('.js-print-page').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.print();
                });
            });
        });
    </script>
</body>
</html>




