<?php
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
$db_name = 'biotern_db';

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
    FROM students
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Prepare filter inputs
$filter_date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$filter_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$filter_supervisor = isset($_GET['supervisor']) ? trim($_GET['supervisor']) : '';
$filter_coordinator = isset($_GET['coordinator']) ? trim($_GET['coordinator']) : '';
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : -1;

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

// Build WHERE clauses depending on provided filters
$where = [];
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
<?php
$page_title = 'BioTern || Students';
$page_styles = array('assets/css/management-students-page.css');
$page_scripts = array(
    'assets/js/students-page-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
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
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                <i class="feather-bar-chart"></i>
                            </a>
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-filter"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-eye me-3"></i>
                                        <span>All</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-user-check me-3"></i>
                                        <span>Active</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-user-minus me-3"></i>
                                        <span>Inactive</span>
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
            <div class="row mb-3 px-3">
                <div class="col-12">
                    <form method="GET" class="filter-form app-students-filter-form row g-2 align-items-end" id="studentsFilterForm">
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-date">Date</label>
                            <input id="filter-date" type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-course">Course</label>
                            <select id="filter-course" name="course_id" class="form-control">
                                <option value="0">-- All Courses --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-department">Department</label>
                            <select id="filter-department" name="department_id" class="form-control">
                                <option value="0">-- All Departments --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-section">Section</label>
                            <select id="filter-section" name="section_id" class="form-control">
                                <option value="0">-- All Sections --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo (int)$section['id']; ?>" <?php echo $filter_section == $section['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($section['section_label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-supervisor">Supervisor</label>
                            <select id="filter-supervisor" name="supervisor" class="form-control">
                                <option value="">-- Any Supervisor --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup); ?>" <?php echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label" for="filter-coordinator">Coordinator</label>
                            <select id="filter-coordinator" name="coordinator" class="form-control">
                                <option value="">-- Any Coordinator --</option>
                                <?php foreach ($coordinators as $coor): ?>
                                    <option value="<?php echo htmlspecialchars($coor); ?>" <?php echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php echo htmlspecialchars($coor); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label d-block invisible">Actions</label>
                            <div class="d-flex align-items-end gap-1">
                                <a href="students.php" class="btn btn-outline-secondary btn-sm px-3 py-1">Reset</a>
                            </div>
                        </div>
                    </form>
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
                        <div class="card stretch stretch-full app-students-table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover app-students-list-table" id="customerList">
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
                                            <?php if (count($students) > 0): ?>
                                                <?php foreach ($students as $index => $student): ?>
                                                    <tr class="single-item">
                                                        <td>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php echo $student['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php echo $student['id']; ?>"></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a href="students-view.php?id=<?php echo $student['id']; ?>" class="hstack gap-3">
                                                                <div class="avatar-image avatar-md">
                                                                    <?php
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
                                                                    <span class="text-truncate-1-line"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td><a href="students-view.php?id=<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['student_id']); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php echo htmlspecialchars($student['section_name'] ?? '-'); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php echo htmlspecialchars($student['supervisor_name'] ?? '-'); ?></a></td>
                                                        <td><a href="javascript:void(0);"><?php echo htmlspecialchars($student['coordinator_name'] ?? '-'); ?></a></td>
                                                        <td><?php echo formatDate($student['created_at']); ?></td>
                                                        <td><?php echo getStatusBadge($student['live_clock_status']); ?></td>
                                                        <td>
                                                            <div class="hstack gap-2 justify-content-end">
                                                                <a href="students-view.php?id=<?php echo $student['id']; ?>" class="avatar-text avatar-md" title="View">
                                                                    <i class="feather feather-eye"></i>
                                                                </a>
                                                                <?php if (!$is_student_user): ?>
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
                                                                <?php endif; ?>
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
        </div>
<?php include 'includes/footer.php'; ?>


