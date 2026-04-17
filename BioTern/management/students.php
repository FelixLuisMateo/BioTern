<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    die('Database connection is not available.');
}
/** @var mysqli $db */
$db = $conn;
$db_name = defined('DB_NAME') ? (string)DB_NAME : 'biotern_db';

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

$has_application_status = false;
$col_app = $db->query("SHOW COLUMNS FROM users LIKE 'application_status'");
if ($col_app && $col_app->num_rows > 0) {
    $has_application_status = true;
}

$has_school_year_column = false;
$col_sy = $db->query("SHOW COLUMNS FROM students LIKE 'school_year'");
if ($col_sy && $col_sy->num_rows > 0) {
    $has_school_year_column = true;
}

$has_fingerprint_user_map = false;
$map_tbl = $db->query("SHOW TABLES LIKE 'fingerprint_user_map'");
if ($map_tbl && $map_tbl->num_rows > 0) {
    $has_fingerprint_user_map = true;
}

$biometric_ready_condition = "s.biometric_registered = 1";
if ($has_fingerprint_user_map) {
    $biometric_ready_condition = "(s.biometric_registered = 1 OR EXISTS (SELECT 1 FROM fingerprint_user_map fum WHERE fum.user_id = s.user_id))";
}

// Fetch Students Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_students,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_students,
        SUM(CASE WHEN {$biometric_ready_condition} THEN 1 ELSE 0 END) as biometric_registered
    FROM students s
";
if ($has_application_status) {
    $stats_query .= "
    LEFT JOIN users u ON u.id = s.user_id
    WHERE COALESCE(u.application_status, 'approved') = 'approved'
    ";
}
$stats = [
    'total_students' => 0,
    'active_students' => 0,
    'inactive_students' => 0,
    'biometric_registered' => 0,
];
$stats_result = $db->query($stats_query);
if ($stats_result) {
    $stats_row = $stats_result->fetch_assoc();
    if (is_array($stats_row)) {
        $stats = array_merge($stats, $stats_row);
    }
}

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

// Fetch dropdown lists
$courses = [];
// Determine which column exists for active flag on courses to avoid schema mismatch errors
$db_esc = $db->real_escape_string($db_name);
$has_is_active = false;
$has_status_col = false;
$col_check = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $db_esc . "' AND TABLE_NAME = 'courses' AND COLUMN_NAME IN ('is_active','status')");
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

$courses_res = $db->query($courses_query);
if ($courses_res && $courses_res->num_rows) {
    while ($r = $courses_res->fetch_assoc()) $courses[] = $r;
}

$departments = [];
$dept_res = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res && $dept_res->num_rows) {
    while ($r = $dept_res->fetch_assoc()) $departments[] = $r;
}

$sections = [];
$section_res = $db->query("SELECT id, code, name, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($section_res && $section_res->num_rows) {
    while ($r = $section_res->fetch_assoc()) {
        $r['section_label'] = biotern_format_section_label((string)($r['code'] ?? ''), (string)($r['name'] ?? ''));
        $sections[] = $r;
    }
}

$supervisors = [];
$sup_res = $db->query("
    SELECT DISTINCT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS supervisor_name
    FROM supervisors
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY supervisor_name ASC
");
if ($sup_res && $sup_res->num_rows) {
    while ($r = $sup_res->fetch_assoc()) $supervisors[] = $r['supervisor_name'];
}

$coordinators = [];
$coor_res = $db->query("
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
if ($has_application_status) {
    $where[] = "COALESCE(u_student.application_status, 'approved') = 'approved'";
}
if ($filter_date !== '') {
    // Filter students that have attendance logs on the selected date.
    $safe_date = $db->real_escape_string($filter_date);
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
if ($has_school_year_column && $filter_school_year !== '' && preg_match('/^\\d{4}-\\d{4}$/', $filter_school_year) && in_array($filter_school_year, $school_year_options, true)) {
    $esc_school_year = $db->real_escape_string($filter_school_year);
    $where[] = "s.school_year = '{$esc_school_year}'";
}
if (!empty($filter_supervisor)) {
    $esc_sup = $db->real_escape_string($filter_supervisor);
    $where[] = "(
        TRIM(CONCAT_WS(' ', sup.first_name, sup.middle_name, sup.last_name)) LIKE '%{$esc_sup}%'
        OR s.supervisor_name LIKE '%{$esc_sup}%'
    )";
}
if (!empty($filter_coordinator)) {
    $esc_coor = $db->real_escape_string($filter_coordinator);
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
        s.user_id,
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
        CASE
            WHEN {$biometric_ready_condition} THEN 1
            ELSE 0
        END as biometric_ready,
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
$students_result = $db->query($students_query);
$students = [];
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Helper function to get status badge
function getStatusBadge($status) {
    $is_active = ((int)$status === 1);
    $label = $is_active ? 'Active' : 'Inactive';
    $class = $is_active ? 'is-active' : 'is-inactive';
    return '<span class="app-students-status-pill ' . $class . '">' . $label . '</span>';
}

// Helper function to format date
function formatDate($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return '-';
}

function resolve_profile_image_url(string $profilePath, int $userId = 0): ?string {
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    if ($resolved === '') {
        return null;
    }
    return $resolved;
}

$selected_section_label = 'ALL';
if ($filter_section > 0) {
    foreach ($sections as $sec) {
        if ((int)$sec['id'] === (int)$filter_section) {
            $selected_section_label = biotern_format_section_label((string)($sec['code'] ?? ''), (string)($sec['name'] ?? ''));
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
<?php
$page_title = 'BioTern || Students';
$page_styles = array(
    'assets/css/layout/page_shell.css',
    'assets/css/modules/management/management-filters.css',
    'assets/css/modules/app-ui-lists-tables.css',
    'assets/css/modules/management/management-students-shared.css',
    'assets/css/modules/management/management-students.css'
);
$page_scripts = array(
    'assets/js/modules/management/students-page-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
    <section class="student-list-print-sheet app-students-print-sheet">
        <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
        <div class="header">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
            <div class="tel">Telefax No.: (045) 624-0215</div>
        </div>
        <div class="print-title">STUDENT SECTION LIST</div>
        <div class="print-meta"><strong>SECTION:</strong> <?php echo htmlspecialchars($selected_section_label); ?></div>
        <div class="print-meta"><strong>ADVISER:</strong> <?php echo htmlspecialchars($selected_adviser); ?></div>
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
                <?php if (!empty($print_students)): ?>
                    <?php foreach ($print_students as $i => $student): ?>
                        <tr>
                            <td class="col-index"><?php echo (int)$i + 1; ?></td>
                            <td><?php echo htmlspecialchars((string)($student['student_id'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($student['last_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($student['first_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($student['middle_name'] ?? '')); ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="col-index">1</td>
                        <td colspan="5">No students found for current filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>


            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Students</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">Students</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto app-students-header-actions">
                    <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="studentsActionsMenu">
                        <i class="feather-grid me-1"></i>
                        <span>Actions</span>
                    </button>
                    <div class="page-header-actions app-students-actions-panel" id="studentsActionsMenu">
                        <div class="dashboard-actions-panel">
                            <div class="dashboard-actions-meta">
                                <span class="text-muted fs-12">Quick Actions</span>
                            </div>
                            <div class="dashboard-actions-grid page-header-right-items-wrapper">
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
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="pdf">
                                        <i class="bi bi-filetype-pdf me-3"></i>
                                        <span>PDF</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="csv">
                                        <i class="bi bi-filetype-csv me-3"></i>
                                        <span>CSV</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="xml">
                                        <i class="bi bi-filetype-xml me-3"></i>
                                        <span>XML</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="txt">
                                        <i class="bi bi-filetype-txt me-3"></i>
                                        <span>Text</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="excel">
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
                            <button type="button" class="btn btn-light d-none js-print-selected" id="printSelectedStudents" aria-hidden="true">
                                <i class="feather-printer me-2"></i>
                                <span>Print Selected</span>
                            </button>
                            <a href="students-create.php" class="btn btn-primary">
                                <i class="feather-plus me-2"></i>
                                <span>Create Students</span>
                            </a>
                            </div>
                        </div>
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
                            <form method="GET" class="filter-form app-students-filter-form row g-2 align-items-end" id="studentsFilterForm">
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-school-year">School Year</label>
                                    <select id="filter-school-year" name="school_year" class="form-control" data-ui-select="custom">
                                        <option value="">-- All School Years --</option>
                                        <?php foreach ($school_year_options as $school_year): ?>
                                            <option value="<?php echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_school_year === $school_year ? 'selected' : ''; ?>><?php echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-date">Date</label>
                                    <input id="filter-date" type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-course">Course</label>
                                    <select id="filter-course" name="course_id" class="form-control" data-ui-select="custom">
                                        <option value="0">-- All Courses --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo (int)$course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-department">Department</label>
                                    <select id="filter-department" name="department_id" class="form-control" data-ui-select="custom">
                                        <option value="0">-- All Departments --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo (int)$dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-section">Section</label>
                                    <select id="filter-section" name="section_id" class="form-control" data-ui-select="custom">
                                        <option value="0">-- All Sections --</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo (int)$section['id']; ?>" <?php echo $filter_section == $section['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($section['section_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-supervisor">Supervisor</label>
                                    <select id="filter-supervisor" name="supervisor" class="form-control" data-ui-select="custom">
                                        <option value="">-- Any Supervisor --</option>
                                        <?php foreach ($supervisors as $sup): ?>
                                            <option value="<?php echo htmlspecialchars($sup, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-coordinator">Coordinator</label>
                                    <select id="filter-coordinator" name="coordinator" class="form-control" data-ui-select="custom">
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['total_students'] ? $stats['total_students'] : '0'; ?></span>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['active_students'] ? $stats['active_students'] : '0'; ?></span>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['inactive_students'] ? $stats['inactive_students'] : '0'; ?></span>
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
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['biometric_registered'] ? $stats['biometric_registered'] : '0'; ?></span>
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
            <div class="main-content app-students-main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full app-students-table-card app-data-card app-data-toolbar">
                            <div class="card-body p-0">
                                <div class="table-responsive students-table-wrap app-data-table-wrap">
                                    <table class="table table-hover app-students-list-table app-data-table" id="customerList">
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
                                                <th>Student</th>
                                                <th>Academic</th>
                                                <th>Mentors</th>
                                                <th>Activity</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($students) > 0): ?>
                                                <?php foreach ($students as $index => $student): ?>
                                                    <?php
                                                    $student_name = trim((string)($student['first_name'] . ' ' . $student['last_name']));
                                                    $student_id_label = (string)($student['student_id'] ?? '-');
                                                    $course_name = (string)($student['course_name'] ?? 'N/A');
                                                    $section_name = biotern_format_section_code((string)($student['section_name'] ?? '-'));
                                                    $supervisor_name = (string)($student['supervisor_name'] ?? '-');
                                                    $coordinator_name = (string)($student['coordinator_name'] ?? '-');
                                                    $last_logged = formatDate($student['created_at']);
                                                    $email_value = trim((string)($student['email'] ?? ''));
                                                    $phone_value = trim((string)($student['phone'] ?? ''));
                                                    $biometric_ready = ((int)($student['biometric_ready'] ?? 0) === 1);
                                                    ?>
                                                    <tr
                                                        class="single-item app-students-table-row"
                                                        data-export-name="<?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-student-id="<?php echo htmlspecialchars($student_id_label, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-course="<?php echo htmlspecialchars($course_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-section="<?php echo htmlspecialchars($section_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-supervisor="<?php echo htmlspecialchars($supervisor_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-coordinator="<?php echo htmlspecialchars($coordinator_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-last-logged="<?php echo htmlspecialchars($last_logged, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-status="<?php echo ((int)($student['live_clock_status'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?>"
                                                        data-print-student-id="<?php echo htmlspecialchars($student_id_label, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-last-name="<?php echo htmlspecialchars((string)($student['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-first-name="<?php echo htmlspecialchars((string)($student['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-middle-name="<?php echo htmlspecialchars((string)($student['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        <td>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php echo $student['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php echo $student['id']; ?>"></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td data-label="Student">
                                                            <a href="students-view.php?id=<?php echo $student['id']; ?>" class="app-students-student-block">
                                                                <div class="avatar-image avatar-md">
                                                                    <?php
                                                                    $pp = $student['profile_picture'] ?? '';
                                                                    $pp_url = resolve_profile_image_url($pp, (int)($student['user_id'] ?? 0));
                                                                    if ($pp_url !== null) {
                                                                        echo '<img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid">';
                                                                    } else {
                                                                        echo '<img src="assets/images/avatar/' . (($index % 5) + 1) . '.png" alt="" class="img-fluid">';
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div class="app-students-student-copy">
                                                                    <span class="app-students-student-name"><?php echo htmlspecialchars($student_name); ?></span>
                                                                    <span class="app-students-student-meta"><?php echo htmlspecialchars($student_id_label); ?></span>
                                                                    <span class="app-students-student-submeta"><?php echo htmlspecialchars($course_name); ?></span>
                                                                </div>
                                                            </a>
                                                            <div class="collapse app-students-inline-collapse" id="studentRowDetails<?php echo (int)$student['id']; ?>">
                                                                <div class="app-students-inline-details">
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Section</span>
                                                                        <span class="app-students-section-pill"><?php echo htmlspecialchars($section_name); ?></span>
                                                                    </div>
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Email</span>
                                                                        <span class="app-students-inline-detail-value"><?php echo htmlspecialchars($email_value !== '' ? $email_value : '-'); ?></span>
                                                                    </div>
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Phone</span>
                                                                        <span class="app-students-inline-detail-value"><?php echo htmlspecialchars($phone_value !== '' ? $phone_value : '-'); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td data-label="Academic">
                                                            <div class="app-students-cell-stack">
                                                                <span class="app-students-cell-title">Course</span>
                                                                <span class="app-students-cell-value"><?php echo htmlspecialchars($course_name); ?></span>
                                                                <span class="app-students-cell-meta">Section <?php echo htmlspecialchars($section_name); ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Mentors">
                                                            <div class="app-students-cell-stack">
                                                                <span class="app-students-cell-title">Supervisor</span>
                                                                <span class="app-students-cell-value"><?php echo htmlspecialchars($supervisor_name); ?></span>
                                                                <span class="app-students-cell-meta">Coordinator <?php echo htmlspecialchars($coordinator_name); ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Activity">
                                                            <div class="app-students-cell-stack">
                                                                <span class="app-students-cell-title">Last Logged</span>
                                                                <span class="app-students-cell-value"><?php echo htmlspecialchars($last_logged); ?></span>
                                                                <span class="app-students-cell-meta"><?php echo $biometric_ready ? 'Biometric registered' : 'Biometric not registered'; ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Status">
                                                            <div class="app-students-status-block">
                                                                <?php echo getStatusBadge($student['live_clock_status']); ?>
                                                                <span class="app-students-biometric-pill <?php echo $biometric_ready ? 'is-ready' : 'is-missing'; ?>">
                                                                    <?php echo $biometric_ready ? 'Biometric Ready' : 'Biometric Missing'; ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Actions">
                                                            <div class="app-students-row-actions">
                                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#studentRowDetails<?php echo (int)$student['id']; ?>" aria-expanded="false" aria-controls="studentRowDetails<?php echo (int)$student['id']; ?>">
                                                                    Details
                                                                </button>
                                                                <?php if (!$is_student_user): ?>
                                                                    <div class="dropdown students-action-dropdown">
                                                                        <a href="javascript:void(0)" class="btn btn-sm btn-light app-students-menu-toggle" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-label="More actions">
                                                                            <i class="feather feather-more-horizontal"></i>
                                                                        </a>
                                                                        <ul class="dropdown-menu">
                                                                            <li>
                                                                                <a class="dropdown-item" href="students-edit.php?id=<?php echo (int)$student['id']; ?>">
                                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                                    <span>Edit</span>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="students-view.php?id=<?php echo (int)$student['id']; ?>" target="_blank" rel="noopener noreferrer">
                                                                                    <i class="feather feather-printer me-3"></i>
                                                                                    <span>Print</span>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="mailto:<?php echo rawurlencode((string)($student['email'] ?? '')); ?>?subject=<?php echo rawurlencode('Reminder from BioTern'); ?>">
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
                                                                                <form method="post" action="students-view.php?id=<?php echo (int)$student['id']; ?>" onsubmit="return confirm('Delete this student and linked user account permanently?');" class="m-0">
                                                                                    <input type="hidden" name="action" value="delete_student">
                                                                                    <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                                                                                    <button type="submit" class="dropdown-item">
                                                                                        <i class="feather feather-trash-2 me-3"></i>
                                                                                        <span>Delete</span>
                                                                                    </button>
                                                                                </form>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="app-students-mobile-list app-mobile-list">
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $index => $student): ?>
                                            <?php
                                            $status_raw = strtolower(trim((string)($student['live_clock_status'] ?? '')));
                                            $status_class = 'status-unknown';
                                            if (in_array($status_raw, ['1', 'active', 'present', 'online'], true) || strpos($status_raw, 'active') !== false) {
                                                $status_class = 'status-active';
                                            } elseif (in_array($status_raw, ['0', 'inactive', 'offline', 'absent'], true) || strpos($status_raw, 'inactive') !== false) {
                                                $status_class = 'status-inactive';
                                            }
                                            ?>
                                            <details class="app-student-mobile-item app-mobile-item">
                                                <summary class="app-student-mobile-summary app-mobile-summary">
                                                    <div class="app-student-mobile-summary-main app-mobile-summary-main">
                                                        <div class="avatar-image avatar-md">
                                                            <?php
                                                            $pp = $student['profile_picture'] ?? '';
                                                            $pp_url = resolve_profile_image_url($pp, (int)($student['user_id'] ?? 0));
                                                            if ($pp_url !== null) {
                                                                echo '<img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid">';
                                                            } else {
                                                                echo '<img src="assets/images/avatar/' . (($index % 5) + 1) . '.png" alt="" class="img-fluid">';
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="app-student-mobile-summary-text app-mobile-summary-text">
                                                            <span class="app-student-mobile-name app-mobile-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                                            <span class="app-student-mobile-subtext app-mobile-subtext">ID: <?php echo htmlspecialchars((string)$student['student_id']); ?> &middot; <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></span>
                                                        </div>
                                                    </div>
                                                    <span class="app-student-mobile-status-dot <?php echo htmlspecialchars($status_class); ?>" aria-hidden="true"></span>
                                                </summary>
                                                <div class="app-student-mobile-details app-mobile-details">
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Student ID</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars((string)$student['student_id']); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Course</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Section</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['section_name'] ?? '-'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Supervisor</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['supervisor_name'] ?? '-'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Coordinator</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['coordinator_name'] ?? '-'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Last Logged</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo formatDate($student['created_at']); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Status</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo getStatusBadge($student['live_clock_status']); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-actions">
                                                        <a href="students-view.php?id=<?php echo (int)$student['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                                        <?php if (!$is_student_user): ?>
                                                            <a href="students-edit.php?id=<?php echo (int)$student['id']; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                            <div class="dropdown students-action-dropdown">
                                                                <a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm app-students-menu-toggle" data-bs-toggle="dropdown" data-bs-offset="0,21">More</a>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item" href="students-edit.php?id=<?php echo (int)$student['id']; ?>">
                                                                            <i class="feather feather-edit-3 me-3"></i>
                                                                            <span>Edit</span>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="students-view.php?id=<?php echo (int)$student['id']; ?>" target="_blank" rel="noopener noreferrer">
                                                                            <i class="feather feather-printer me-3"></i>
                                                                            <span>Print</span>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="mailto:<?php echo rawurlencode((string)($student['email'] ?? '')); ?>?subject=<?php echo rawurlencode('Reminder from BioTern'); ?>">
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
                                                                        <form method="post" action="students-view.php?id=<?php echo (int)$student['id']; ?>" onsubmit="return confirm('Delete this student and linked user account permanently?');" class="m-0">
                                                                            <input type="hidden" name="action" value="delete_student">
                                                                            <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <i class="feather feather-trash-2 me-3"></i>
                                                                                <span>Delete</span>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="app-students-mobile-empty text-muted">No students found.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>








