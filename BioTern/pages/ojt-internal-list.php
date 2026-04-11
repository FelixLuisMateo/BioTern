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

$conn->query("CREATE TABLE IF NOT EXISTS ojt_internal (
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
    KEY idx_ojt_internal_user_id (user_id),
    KEY idx_ojt_internal_course_id (course_id),
    KEY idx_ojt_internal_section_id (section_id),
    KEY idx_ojt_internal_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-link OJT Internal records to registered students via student number.
$conn->query("UPDATE ojt_internal oi
    INNER JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oi.student_no, '')) COLLATE utf8mb4_unicode_ci
    SET oi.user_id = s.user_id
    WHERE (oi.user_id IS NULL OR oi.user_id = 0)
      AND s.user_id IS NOT NULL
      AND s.user_id > 0");

$trackColumnRes = $conn->query("SHOW COLUMNS FROM students LIKE 'assignment_track'");
$hasAssignmentTrack = ($trackColumnRes instanceof mysqli_result) && $trackColumnRes->num_rows > 0;
if ($trackColumnRes instanceof mysqli_result) {
    $trackColumnRes->close();
}

$mapFingerId = (int)($_GET['map_finger_id'] ?? 0);
$filterCourseId = (int)($_GET['course_id'] ?? 0);
$filterSectionId = (int)($_GET['section_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));

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

$where = [];
if ($filterCourseId > 0) {
    $where[] = 'COALESCE(oi.course_id, s.course_id, 0) = ' . (int)$filterCourseId;
}
if ($filterSectionId > 0) {
    $where[] = 'COALESCE(oi.section_id, s.section_id, 0) = ' . (int)$filterSectionId;
}
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $like = "'%" . $safe . "%'";
    $where[] = "(
        oi.student_no LIKE {$like}
        OR oi.last_name LIKE {$like}
        OR oi.first_name LIKE {$like}
        OR oi.email LIKE {$like}
        OR s.student_id LIKE {$like}
        OR u.name LIKE {$like}
    )";
}

$sql = "
    SELECT
        oi.student_no,
        oi.user_id AS ojt_user_id,
        oi.last_name,
        oi.first_name,
        oi.middle_name,
        oi.course_id AS ojt_course_id,
        oi.section_id AS ojt_section_id,
        oi.email AS ojt_email,
        oi.status AS ojt_status,
        oi.created_at AS ojt_created_at,
        s.id AS student_row_id,
        s.user_id AS student_user_id,
        s.student_id,
        s.status AS students_status,
        " . ($hasAssignmentTrack ? "s.assignment_track" : "''") . " AS assignment_track,
        s.created_at AS students_created_at,
        COALESCE(u.name, CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS account_name,
        COALESCE(c1.name, c2.name, 'N/A') AS course_name,
        COALESCE(NULLIF(sec1.code, ''), sec1.name, NULLIF(sec2.code, ''), sec2.name, 'N/A') AS section_name
    FROM ojt_internal oi
    LEFT JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oi.student_no, '')) COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON u.id = COALESCE(NULLIF(oi.user_id, 0), s.user_id)
    LEFT JOIN courses c1 ON c1.id = oi.course_id
    LEFT JOIN courses c2 ON c2.id = s.course_id
    LEFT JOIN sections sec1 ON sec1.id = oi.section_id
    LEFT JOIN sections sec2 ON sec2.id = s.section_id
";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY oi.last_name ASC, oi.first_name ASC, oi.student_no ASC';

$rows = [];
$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->close();
}

$linkedCount = 0;
foreach ($rows as $row) {
    if ((int)($row['student_user_id'] ?? 0) > 0 || (int)($row['ojt_user_id'] ?? 0) > 0) {
        $linkedCount++;
    }
}

$page_title = 'Internal Student List';
$page_body_class = 'page-fingerprint-mapping';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
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
                    <h5 class="m-b-10">Internal Students</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Internal Students</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <div class="page-header-right-items">
                    <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                        <a href="import-ojt-internal.php" class="btn btn-light-brand">Import OJT Internal</a>
                        <a href="import-ojt-external.php" class="btn btn-outline-secondary">Import OJT External</a>
                        <a href="ojt-external-list.php" class="btn btn-outline-secondary">View External List</a>
                        <a href="fingerprint_mapping.php" class="btn btn-outline-secondary">Back To Fingerprints</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
            <?php if ($mapFingerId > 0): ?>
                <div class="alert alert-info py-2">Preparing student mapping workflow for fingerprint ID <strong><?php echo $mapFingerId; ?></strong>. Select a candidate student from this internal list first.</div>
            <?php endif; ?>
            <?php if ($linkedCount === 0): ?>
                <div class="alert alert-warning py-2">No linked internal student accounts yet. Import or update records so <strong>student_no</strong> matches <strong>students.student_id</strong> and has a valid <strong>user_id</strong>.</div>
            <?php endif; ?>

            <div class="card mb-4 bio-console-panel">
                <div class="card-header"><strong>Internal List Filters</strong></div>
                <div class="card-body border-bottom">
                    <form method="get" class="row g-2 align-items-end fingerprint-form">
                        <?php if ($mapFingerId > 0): ?>
                            <input type="hidden" name="map_finger_id" value="<?php echo $mapFingerId; ?>">
                        <?php endif; ?>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="search">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Student no, name, email">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="course_id">Course</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="0">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>" <?php echo ((int)$course['id'] === $filterCourseId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
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
                            <a href="ojt-internal-list.php<?php echo $mapFingerId > 0 ? '?map_finger_id=' . $mapFingerId : ''; ?>" class="btn btn-light">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="card-body py-2">
                    <small class="text-muted">Total internal rows: <?php echo count($rows); ?> | Linked with registered account: <?php echo $linkedCount; ?></small>
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
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No internal students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
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
                                            <?php if ((int)($row['student_user_id'] ?? 0) > 0 || (int)($row['ojt_user_id'] ?? 0) > 0): ?>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string)($row['account_name'] ?? 'Linked Account'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted">User ID: <?php echo (int)($row['student_user_id'] ?: $row['ojt_user_id']); ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-soft-warning text-warning">Not Linked Yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info"><?php echo htmlspecialchars(ucfirst((string)($row['ojt_status'] ?? 'Unknown')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($mapFingerId > 0): ?>
                                                <?php $candidateUserId = (int)($row['student_user_id'] ?: $row['ojt_user_id']); ?>
                                                <?php if ($candidateUserId > 0): ?>
                                                    <form method="post" action="fingerprint_mapping.php" class="d-inline" data-confirm="Map fingerprint <?php echo $mapFingerId; ?> to this student account?">
                                                        <input type="hidden" name="mapping_action" value="save_student">
                                                        <input type="hidden" name="finger_id" value="<?php echo $mapFingerId; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $candidateUserId; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Map Fingerprint Here</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Student account not linked yet</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Select from fingerprint page</span>
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
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>
