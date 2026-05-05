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

function fingerprint_unmapped_column_exists(mysqli $conn, string $table, string $column): bool
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    $exists = ($res instanceof mysqli_result) && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

$conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (
    finger_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (finger_id),
    UNIQUE KEY uniq_fingerprint_user_map_user_id (user_id),
    KEY idx_fingerprint_user_map_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mapFingerId = (int)($_GET['map_finger_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$filterCourseId = (int)($_GET['course_id'] ?? 0);
$filterSectionId = (int)($_GET['section_id'] ?? 0);
$filterSchoolYear = trim((string)($_GET['school_year'] ?? ''));

$hasAssignmentTrack = fingerprint_unmapped_column_exists($conn, 'students', 'assignment_track');
$hasSchoolYear = fingerprint_unmapped_column_exists($conn, 'students', 'school_year');

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

$where = [
    's.user_id IS NOT NULL',
    's.user_id > 0',
    'm.finger_id IS NULL',
];
if ($hasAssignmentTrack) {
    $where[] = "LOWER(TRIM(COALESCE(s.assignment_track, 'internal'))) = 'internal'";
}
if ($filterCourseId > 0) {
    $where[] = 's.course_id = ' . (int)$filterCourseId;
}
if ($filterSectionId > 0) {
    $where[] = 's.section_id = ' . (int)$filterSectionId;
}
if ($hasSchoolYear && $filterSchoolYear !== '') {
    $where[] = "s.school_year = '" . $conn->real_escape_string($filterSchoolYear) . "'";
}
if ($search !== '') {
    $safeSearch = '%' . $conn->real_escape_string($search) . '%';
    $where[] = "(
        s.student_id LIKE '{$safeSearch}'
        OR s.first_name LIKE '{$safeSearch}'
        OR s.last_name LIKE '{$safeSearch}'
        OR s.email LIKE '{$safeSearch}'
        OR u.name LIKE '{$safeSearch}'
    )";
}

$students = [];
$sql = "
    SELECT
        s.id,
        s.user_id,
        COALESCE(NULLIF(s.student_id, ''), '') AS student_no,
        COALESCE(s.first_name, '') AS first_name,
        COALESCE(s.middle_name, '') AS middle_name,
        COALESCE(s.last_name, '') AS last_name,
        COALESCE(s.email, '') AS email,
        " . ($hasSchoolYear ? "COALESCE(NULLIF(s.school_year, ''), '')" : "''") . " AS school_year,
        COALESCE(u.name, CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS account_name,
        COALESCE(c.name, 'N/A') AS course_name,
        COALESCE(NULLIF(sec.code, ''), sec.name, 'N/A') AS section_name
    FROM students s
    INNER JOIN users u ON u.id = s.user_id
    LEFT JOIN fingerprint_user_map m ON m.user_id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.last_name ASC, s.first_name ASC, s.student_id ASC
    LIMIT 300
";
$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $res->close();
}

$page_title = 'Unmapped Internal Students';
$page_body_class = 'page-fingerprint-mapping page-ojt-internal-list mobile-bottom-nav';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
    'assets/css/modules/pages/page-ojt-internal-list.css',
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
                    <h5 class="m-b-10">Unmapped Internal Students</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="fingerprint_mapping.php">Fingerprint Mapping</a></li>
                    <li class="breadcrumb-item">Unmapped Internal Students</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <div class="page-header-right-items-wrapper d-flex gap-2">
                    <a href="fingerprint_mapping.php" class="btn btn-outline-secondary">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back To Fingerprints</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
            <?php if ($mapFingerId > 0): ?>
                <div class="alert alert-info py-2">Mapping fingerprint ID <strong><?php echo (int)$mapFingerId; ?></strong>. This list only shows internal students without an existing fingerprint.</div>
            <?php else: ?>
                <div class="alert alert-warning py-2">Open this page from an unmapped fingerprint to select a student target.</div>
            <?php endif; ?>

            <div class="card mb-4 bio-console-panel">
                <div class="card-header"><strong>Find Unmapped Internal Student</strong></div>
                <div class="card-body border-bottom">
                    <form method="get" class="row g-2 align-items-end fingerprint-form">
                        <?php if ($mapFingerId > 0): ?>
                            <input type="hidden" name="map_finger_id" value="<?php echo (int)$mapFingerId; ?>">
                        <?php endif; ?>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="search">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Student no, name, email">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="school_year">School Year</label>
                            <input type="text" class="form-control" id="school_year" name="school_year" value="<?php echo htmlspecialchars($filterSchoolYear, ENT_QUOTES, 'UTF-8'); ?>" placeholder="2025-2026">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="course_id">Course</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="0">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>" <?php echo (int)$course['id'] === $filterCourseId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="section_id">Section</label>
                            <select class="form-select" id="section_id" name="section_id">
                                <option value="0">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo (int)$section['id']; ?>" <?php echo (int)$section['id'] === $filterSectionId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$section['section_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="fingerprint-unmapped-internal.php<?php echo $mapFingerId > 0 ? '?map_finger_id=' . (int)$mapFingerId : ''; ?>" class="btn btn-light">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="card-body py-2">
                    <small class="text-muted"><?php echo count($students); ?> unmapped internal student(s) found.</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 bio-console-table">
                            <thead>
                                <tr>
                                    <th>Student No</th>
                                    <th>Name</th>
                                    <th>Course / Section</th>
                                    <th>Linked Account</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No unmapped internal students match your filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($student['student_no'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars(trim((string)$student['last_name'] . ', ' . (string)$student['first_name'] . ' ' . (string)$student['middle_name']), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars((string)$student['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars((string)$student['course_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars(biotern_format_section_code((string)$student['section_name']), ENT_QUOTES, 'UTF-8'); ?><?php echo trim((string)$student['school_year']) !== '' ? ' / ' . htmlspecialchars((string)$student['school_year'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$student['account_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted">User ID: <?php echo (int)$student['user_id']; ?></small>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($mapFingerId > 0): ?>
                                                <form method="post" action="fingerprint_mapping.php" class="d-inline" data-confirm="Map fingerprint <?php echo (int)$mapFingerId; ?> to this student?">
                                                    <input type="hidden" name="mapping_action" value="save_student">
                                                    <input type="hidden" name="finger_id" value="<?php echo (int)$mapFingerId; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$student['user_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary">Map Fingerprint Here</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">Select a fingerprint first</span>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
