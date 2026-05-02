<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/section_format.php';
require_once __DIR__ . '/../includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'biotern_db',
        defined('DB_PORT') ? (int)DB_PORT : 3306
    );
    if ($conn->connect_error) {
        ob_end_clean();
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
}

$conn->query("CREATE TABLE IF NOT EXISTS ojt_external (
    student_no VARCHAR(100) NOT NULL,
    user_id INT NULL,
    last_name VARCHAR(150) NOT NULL DEFAULT '',
    first_name VARCHAR(150) NOT NULL DEFAULT '',
    middle_name VARCHAR(150) NOT NULL DEFAULT '',
    course_id INT NULL,
    section_id INT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_no),
    KEY idx_ojt_external_user_id (user_id),
    KEY idx_ojt_external_course_id (course_id),
    KEY idx_ojt_external_section_id (section_id),
    KEY idx_ojt_external_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-link OJT External records to registered students via student number.
$conn->query("UPDATE ojt_external oe
    INNER JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oe.student_no, '')) COLLATE utf8mb4_unicode_ci
    SET oe.user_id = s.user_id
    WHERE (oe.user_id IS NULL OR oe.user_id = 0)
      AND s.user_id IS NOT NULL
      AND s.user_id > 0");

$filterCourseId = (int)($_GET['course_id'] ?? 0);
$filterSectionId = (int)($_GET['section_id'] ?? 0);
$filterSchoolYear = trim((string)($_GET['school_year'] ?? ''));
$filterSemester = trim((string)($_GET['semester'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$semesterOptions = ['1st Semester', '2nd Semester', 'Summer'];

$courses = [];
$courseRes = $conn->query('SELECT id, name FROM courses ORDER BY name ASC');
if ($courseRes instanceof mysqli_result) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
    $courseRes->close();
}

$sections = [];
$sectionRes = $conn->query("SELECT id, course_id, code, name, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($sectionRes instanceof mysqli_result) {
    while ($row = $sectionRes->fetch_assoc()) {
        $row['section_label'] = biotern_format_section_label((string)($row['code'] ?? ''), (string)($row['name'] ?? ''));
        $sections[] = $row;
    }
    $sectionRes->close();
}

$rows = [];
$sql = "
    SELECT
        oe.student_no,
        oe.user_id AS ojt_user_id,
        oe.last_name,
        oe.first_name,
        oe.middle_name,
        oe.email AS ojt_email,
        oe.status AS ojt_status,
        oe.created_at AS ojt_created_at,
        s.id AS student_row_id,
        s.user_id AS student_user_id,
        s.student_id,
        s.status AS students_status,
        COALESCE(NULLIF(TRIM(s.assignment_track), ''), 'external') AS assignment_track,
        COALESCE(NULLIF(TRIM(s.school_year), ''), '') AS school_year,
        COALESCE(NULLIF(TRIM(s.semester), ''), '') AS semester,
        s.created_at AS students_created_at,
        COALESCE(u.name, CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS account_name,
        COALESCE(c1.name, c2.name, 'N/A') AS course_name,
        COALESCE(NULLIF(sec1.code, ''), sec1.name, NULLIF(sec2.code, ''), sec2.name, 'N/A') AS section_name,
        COALESCE(oe.course_id, s.course_id, 0) AS resolved_course_id,
        COALESCE(oe.section_id, s.section_id, 0) AS resolved_section_id
    FROM ojt_external oe
    LEFT JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oe.student_no, '')) COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON u.id = COALESCE(NULLIF(oe.user_id, 0), s.user_id)
    LEFT JOIN courses c1 ON c1.id = oe.course_id
    LEFT JOIN courses c2 ON c2.id = s.course_id
    LEFT JOIN sections sec1 ON sec1.id = oe.section_id
    LEFT JOIN sections sec2 ON sec2.id = s.section_id
    ORDER BY oe.last_name ASC, oe.first_name ASC, oe.student_no ASC
";
$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->close();
}

$studentsOnlySql = "
    SELECT
        COALESCE(NULLIF(s.student_id, ''), '') AS student_no,
        NULL AS ojt_user_id,
        COALESCE(s.last_name, '') AS last_name,
        COALESCE(s.first_name, '') AS first_name,
        COALESCE(s.middle_name, '') AS middle_name,
        COALESCE(s.email, '') AS ojt_email,
        'external' AS ojt_status,
        NULL AS ojt_created_at,
        s.id AS student_row_id,
        s.user_id AS student_user_id,
        s.student_id,
        s.status AS students_status,
        COALESCE(NULLIF(TRIM(s.assignment_track), ''), 'external') AS assignment_track,
        COALESCE(NULLIF(TRIM(s.school_year), ''), '') AS school_year,
        COALESCE(NULLIF(TRIM(s.semester), ''), '') AS semester,
        s.created_at AS students_created_at,
        COALESCE(u.name, CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS account_name,
        COALESCE(c.name, 'N/A') AS course_name,
        COALESCE(NULLIF(sec.code, ''), sec.name, 'N/A') AS section_name,
        COALESCE(s.course_id, 0) AS resolved_course_id,
        COALESCE(s.section_id, 0) AS resolved_section_id
    FROM students s
    LEFT JOIN ojt_external oe_match ON TRIM(COALESCE(oe_match.student_no, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE oe_match.student_no IS NULL
      AND LOWER(TRIM(COALESCE(s.assignment_track, 'internal'))) = 'external'
    ORDER BY s.last_name ASC, s.first_name ASC, s.student_id ASC
";
$studentsOnlyRes = $conn->query($studentsOnlySql);
if ($studentsOnlyRes instanceof mysqli_result) {
    while ($row = $studentsOnlyRes->fetch_assoc()) {
        $rows[] = $row;
    }
    $studentsOnlyRes->close();
}

$normalizedRows = [];
$seenRowKeys = [];
foreach ($rows as $row) {
    $rowKey = strtolower(trim((string)($row['student_no'] ?? ''))) . '|' . (int)($row['student_row_id'] ?? 0);
    if (isset($seenRowKeys[$rowKey])) {
        continue;
    }
    $seenRowKeys[$rowKey] = true;
    if ($filterCourseId > 0 && (int)($row['resolved_course_id'] ?? 0) !== $filterCourseId) {
        continue;
    }
    if ($filterSectionId > 0 && (int)($row['resolved_section_id'] ?? 0) !== $filterSectionId) {
        continue;
    }
    if ($filterSchoolYear !== '' && strcasecmp(trim((string)($row['school_year'] ?? '')), $filterSchoolYear) !== 0) {
        continue;
    }
    if ($filterSemester !== '' && strcasecmp(trim((string)($row['semester'] ?? '')), $filterSemester) !== 0) {
        continue;
    }
    if ($search !== '') {
        $haystack = strtolower(trim(implode(' ', [
            (string)($row['student_no'] ?? ''),
            (string)($row['last_name'] ?? ''),
            (string)($row['first_name'] ?? ''),
            (string)($row['middle_name'] ?? ''),
            (string)($row['ojt_email'] ?? ''),
            (string)($row['account_name'] ?? ''),
        ])));
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }
    $normalizedRows[] = $row;
}
$rows = $normalizedRows;

$linkedCount = 0;
foreach ($rows as $row) {
    if ((int)($row['student_user_id'] ?? 0) > 0 || (int)($row['ojt_user_id'] ?? 0) > 0) {
        $linkedCount++;
    }
}

$exportQuery = array_filter([
    'type' => 'external',
    'school_year' => $filterSchoolYear,
    'semester' => $filterSemester,
    'course_id' => $filterCourseId > 0 ? (string)$filterCourseId : '',
    'section_id' => $filterSectionId > 0 ? (string)$filterSectionId : '',
    'search' => $search,
], static fn($value): bool => $value !== '' && $value !== null);
$exportUrl = 'export-ojt-list.php?' . http_build_query($exportQuery);

$page_title = 'External Student List';
$page_body_class = 'page-fingerprint-mapping page-ojt-external-list';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
    'assets/css/modules/management/management-students.css',
];
$page_scripts = [
    'assets/js/modules/pages/ojt-list-select.js',
    'assets/js/modules/pages/ojt-list-print.js',
    'assets/js/modules/pages/ojt-external-actions.js',
    'assets/js/modules/pages/ojt-row-link.js',
];
$base_href = '';
include __DIR__ . '/../includes/header.php';
ob_end_flush();
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">External Students</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">External Students</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="ojtExternalActionsMenu">
                    <i class="feather-grid me-1"></i>
                    <span>Actions</span>
                </button>
                <div class="page-header-actions app-ojt-actions-panel" id="ojtExternalActionsMenu">
                    <div class="dashboard-actions-panel">
                        <div class="dashboard-actions-meta">
                            <span class="text-muted fs-12">Quick Actions</span>
                        </div>
                        <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <a href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>" class="action-tile">
                                <i class="feather-upload-cloud"></i>
                                <span>Export External List</span>
                            </a>
                            <button type="button" class="action-tile" data-ojt-print-full="ojtExternalListTable">
                                <i class="feather-printer"></i>
                                <span>Print List</span>
                            </button>
                            <button type="button" class="action-tile" data-ojt-print-selected="ojtExternalListTable">
                                <i class="feather-printer"></i>
                                <span>Print Selected</span>
                            </button>
                            <a href="ojt-internal-list.php" class="action-tile">
                                <i class="feather-list"></i>
                                <span>View Internal List</span>
                            </a>
                            <a href="fingerprint_mapping.php" class="action-tile">
                                <i class="feather-hash"></i>
                                <span>Back To Fingerprints</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
            <?php if ($linkedCount === 0): ?>
                <div class="alert alert-warning py-2">No linked external student accounts yet. Import or update records so <strong>student_no</strong> matches <strong>students.student_id</strong> and has a valid <strong>user_id</strong>.</div>
            <?php endif; ?>

            <div class="card mb-4 bio-console-panel">
                <div class="card-header"><strong>External List Filters</strong></div>
                <div class="card-body border-bottom">
                    <form method="get" class="row g-2 align-items-end fingerprint-form">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="search">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Student no, name, email">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="school_year">School Year</label>
                            <input type="text" class="form-control" id="school_year" name="school_year" value="<?php echo htmlspecialchars($filterSchoolYear, ENT_QUOTES, 'UTF-8'); ?>" placeholder="2025-2026">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="semester">Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesterOptions as $semesterOption): ?>
                                    <option value="<?php echo htmlspecialchars($semesterOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterSemester === $semesterOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($semesterOption, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="course_id">Course</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="0">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>" <?php echo ((int)$course['id'] === $filterCourseId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="section_id">Section</label>
                            <select class="form-select" id="section_id" name="section_id">
                                <option value="0">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo (int)$section['id']; ?>" <?php echo ((int)$section['id'] === $filterSectionId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$section['section_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 fm-actions">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="ojt-external-list.php" class="btn btn-light">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="card-body py-2 border-bottom">
                        <small class="text-muted">Total external rows: <?php echo count($rows); ?> | Linked with registered account: <?php echo $linkedCount; ?><?php echo $filterSchoolYear !== '' ? ' | SY: ' . htmlspecialchars($filterSchoolYear, ENT_QUOTES, 'UTF-8') : ''; ?><?php echo $filterSemester !== '' ? ' | ' . htmlspecialchars($filterSemester, ENT_QUOTES, 'UTF-8') : ''; ?></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 bio-console-table" id="ojtExternalListTable" data-ojt-select-table data-print-mode="student-section" data-print-title="External Student List" data-print-subtitle="<?php echo htmlspecialchars(trim(($filterSchoolYear !== '' ? $filterSchoolYear . ' / ' : '') . ($filterSemester !== '' ? $filterSemester : 'Filtered external list')), ENT_QUOTES, 'UTF-8'); ?>">
                            <thead>
                                <tr>
                                    <th class="app-ojt-select-column">
                                        <div class="form-check app-ojt-select-check">
                                            <input class="form-check-input" type="checkbox" data-ojt-select-all aria-label="Select all external students">
                                        </div>
                                    </th>
                                    <th>Student No</th>
                                    <th>Name</th>
                            <th>Course / Section</th>
                            <th>Account</th>
                            <th>Status</th>
                            <th class="text-end" data-print-exclude="1">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4" data-print-exclude="1">No external students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr data-ojt-external-row-id="<?php echo (int)($row['student_row_id'] ?? 0); ?>" data-ojt-external-row-no="<?php echo htmlspecialchars((string)($row['student_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-ojt-external-row-label="<?php echo htmlspecialchars(trim((string)($row['last_name'] ?? '') . ', ' . (string)($row['first_name'] ?? '') . ' ' . (string)($row['middle_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-ojt-external-row-course="<?php echo htmlspecialchars((string)($row['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>" data-ojt-external-row-section="<?php echo htmlspecialchars(biotern_format_section_code((string)($row['section_name'] ?? 'N/A')), ENT_QUOTES, 'UTF-8'); ?>" data-print-student-no="<?php echo htmlspecialchars((string)($row['student_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-print-last-name="<?php echo htmlspecialchars((string)($row['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-print-first-name="<?php echo htmlspecialchars((string)($row['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-print-middle-name="<?php echo htmlspecialchars((string)($row['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-row-href="ojt-external-view.php?id=<?php echo (int)($row['student_row_id'] ?? 0); ?>">
                                        <td class="app-ojt-select-column" data-print-exclude="1">
                                            <div class="form-check app-ojt-select-check">
                                                <input class="form-check-input" type="checkbox" data-ojt-row-select aria-label="Select student <?php echo htmlspecialchars((string)$row['student_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)$row['student_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars(trim((string)$row['last_name'] . ', ' . (string)$row['first_name'] . ' ' . (string)$row['middle_name']), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars((string)($row['ojt_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars((string)($row['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars(biotern_format_section_code((string)($row['section_name'] ?? 'N/A')), ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars((string)($row['account_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php $externalUserId = (int)($row['student_user_id'] ?: $row['ojt_user_id']); ?>
                                            <small class="text-muted">User ID: <?php echo $externalUserId; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info"><?php echo htmlspecialchars(ucfirst((string)($row['ojt_status'] ?? 'External')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </td>
                                        <td class="text-end" data-print-exclude="1">
                                            <?php if ((int)($row['student_row_id'] ?? 0) > 0): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-light"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#ojtExternalActionModal"
                                                    data-ojt-external-action-trigger
                                                    data-ojt-row-href="ojt-external-view.php?id=<?php echo (int)($row['student_row_id'] ?? 0); ?>"
                                                    data-ojt-student-label="<?php echo htmlspecialchars(trim((string)($row['last_name'] ?? '') . ', ' . (string)($row['first_name'] ?? '') . ' ' . (string)($row['middle_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    Actions
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">No linked record</span>
                                            <?php endif; ?>
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
</main>
<div class="modal fade biotern-popup-modal" id="ojtExternalActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">External Student Actions</h5>
                    <div class="text-muted small" data-ojt-external-action-summary>Choose an action for this student.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted border rounded p-2 mb-3" data-ojt-external-action-current>Student details will show here.</div>
                <div class="list-group">
                    <a class="list-group-item list-group-item-action" href="#" data-ojt-external-action-view>
                        <i class="feather feather-eye me-3"></i><span>View Student</span>
                    </a>
                    <button type="button" class="list-group-item list-group-item-action" data-ojt-print-full="ojtExternalListTable">
                        <i class="feather feather-printer me-3"></i><span>Print List</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" data-ojt-print-selected="ojtExternalListTable">
                        <i class="feather feather-check-square me-3"></i><span>Print Selected</span>
                    </button>
                    <a class="list-group-item list-group-item-action" href="fingerprint_mapping.php">
                        <i class="feather feather-hash me-3"></i><span>Back To Fingerprints</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<section class="student-list-print-sheet app-students-print-sheet app-ojt-selected-print-sheet" data-ojt-print-sheet="ojtExternalListTable" aria-hidden="true">
    <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title" data-ojt-print-title>EXTERNAL STUDENT LIST</div>
    <div class="print-meta"><strong>FILTER:</strong> <span data-ojt-print-subtitle><?php echo htmlspecialchars(trim(($filterSchoolYear !== '' ? $filterSchoolYear . ' / ' : '') . ($filterSemester !== '' ? $filterSemester : 'Filtered external list')), ENT_QUOTES, 'UTF-8'); ?></span></div>
    <table>
        <thead>
            <tr></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>
