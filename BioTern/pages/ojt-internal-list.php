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

function ojt_internal_table_exists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

function ojt_internal_column_exists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

function ojt_internal_list_status_key(array $row): string {
    $internStatus = strtolower(trim((string)($row['internship_status'] ?? '')));
    if ($internStatus === 'ongoing') {
        return 'ongoing';
    }
    if (in_array($internStatus, ['completed', 'finished', 'done', 'dropped', 'cancelled'], true)) {
        return 'finished';
    }
    if (in_array($internStatus, ['pending', 'applied', 'accepted', 'endorsed'], true)) {
        return 'not_started';
    }

    $legacyStatus = strtolower(trim((string)($row['ojt_status'] ?? '')));
    if ($legacyStatus === 'ongoing') {
        return 'ongoing';
    }
    if (in_array($legacyStatus, ['finished', 'completed', 'done', 'closed', 'inactive'], true)) {
        return 'finished';
    }

    return 'not_started';
}

function ojt_internal_list_status_label(string $status): string {
    $map = [
        'ongoing' => 'Ongoing',
        'finished' => 'Finished',
        'not_started' => 'Not Started',
    ];
    return $map[$status] ?? 'Not Started';
}

function ojt_internal_list_status_badge_class(string $status): string {
    $map = [
        'ongoing' => 'app-ojt-internal-status app-ojt-internal-status-ongoing',
        'finished' => 'app-ojt-internal-status app-ojt-internal-status-finished',
        'not_started' => 'app-ojt-internal-status app-ojt-internal-status-not-started',
    ];
    return $map[$status] ?? 'app-ojt-internal-status';
}

function ojt_internal_list_matches_search(array $row, string $search): bool {
    $needle = strtolower(trim($search));
    if ($needle === '') {
        return true;
    }
    $haystack = strtolower(implode(' ', [
        (string)($row['student_no'] ?? ''),
        (string)($row['last_name'] ?? ''),
        (string)($row['first_name'] ?? ''),
        (string)($row['middle_name'] ?? ''),
        (string)($row['ojt_email'] ?? ''),
        (string)($row['student_id'] ?? ''),
        (string)($row['account_name'] ?? ''),
        (string)($row['course_name'] ?? ''),
        (string)($row['section_name'] ?? ''),
    ]));
    return strpos($haystack, $needle) !== false;
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
$filterOjtStatus = strtolower(trim((string)($_GET['ojt_status'] ?? 'all')));
if (!in_array($filterOjtStatus, ['all', 'ongoing', 'finished', 'not_started'], true)) {
    $filterOjtStatus = 'all';
}

$internshipsTableExists = ojt_internal_table_exists($conn, 'internships');
$internshipsHasTypeColumn = $internshipsTableExists && ojt_internal_column_exists($conn, 'internships', 'type');
$internshipsHasStartDateColumn = $internshipsTableExists && ojt_internal_column_exists($conn, 'internships', 'start_date');
$internshipsHasStatusColumn = $internshipsTableExists && ojt_internal_column_exists($conn, 'internships', 'status');
$internshipsHasRequiredHoursColumn = $internshipsTableExists && ojt_internal_column_exists($conn, 'internships', 'required_hours');
$internshipsHasStudentIdColumn = $internshipsTableExists && ojt_internal_column_exists($conn, 'internships', 'student_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['internal_action'] ?? '') === 'start_internal') {
    $targetStudentId = (int)($_POST['student_id'] ?? 0);
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $redirectQuery = [];
    if ($filterCourseId > 0) {
        $redirectQuery['course_id'] = $filterCourseId;
    }
    if ($filterSectionId > 0) {
        $redirectQuery['section_id'] = $filterSectionId;
    }
    if ($search !== '') {
        $redirectQuery['search'] = $search;
    }
    if ($filterOjtStatus !== 'all') {
        $redirectQuery['ojt_status'] = $filterOjtStatus;
    }
    $redirectTarget = 'ojt-internal-list.php' . ($redirectQuery !== [] ? ('?' . http_build_query($redirectQuery)) : '');

    if (!$internshipsTableExists || !$internshipsHasStudentIdColumn) {
        $_SESSION['ojt_internal_flash_type'] = 'danger';
        $_SESSION['ojt_internal_flash_message'] = 'Internship table is not ready for internal start dates.';
        header('Location: ' . $redirectTarget);
        exit;
    }

    if ($targetStudentId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $_SESSION['ojt_internal_flash_type'] = 'danger';
        $_SESSION['ojt_internal_flash_message'] = 'A valid internal start date is required.';
        header('Location: ' . $redirectTarget);
        exit;
    }

    $studentLookup = $conn->prepare("
        SELECT id, internal_total_hours, assignment_track
        FROM students
        WHERE id = ?
        LIMIT 1
    ");
    $studentRow = null;
    if ($studentLookup) {
        $studentLookup->bind_param('i', $targetStudentId);
        $studentLookup->execute();
        $studentRow = $studentLookup->get_result()->fetch_assoc() ?: null;
        $studentLookup->close();
    }

    if (!$studentRow) {
        $_SESSION['ojt_internal_flash_type'] = 'danger';
        $_SESSION['ojt_internal_flash_message'] = 'Student record was not found.';
        header('Location: ' . $redirectTarget);
        exit;
    }

    $latestInternalSql = "
        SELECT id
        FROM internships
        WHERE student_id = ?
    ";
    if ($internshipsHasTypeColumn) {
        $latestInternalSql .= " AND LOWER(TRIM(COALESCE(type, 'internal'))) = 'internal'";
    }
    $latestInternalSql .= " ORDER BY id DESC LIMIT 1";
    $latestInternalStmt = $conn->prepare($latestInternalSql);
    $latestInternal = null;
    if ($latestInternalStmt) {
        $latestInternalStmt->bind_param('i', $targetStudentId);
        $latestInternalStmt->execute();
        $latestInternal = $latestInternalStmt->get_result()->fetch_assoc() ?: null;
        $latestInternalStmt->close();
    }

    $requiredHours = max(0, (int)($studentRow['internal_total_hours'] ?? 0));

    if ($latestInternal) {
        $updates = [];
        $types = '';
        $values = [];

        if ($internshipsHasStatusColumn) {
            $updates[] = 'status = ?';
            $types .= 's';
            $values[] = 'ongoing';
        }
        if ($internshipsHasStartDateColumn) {
            $updates[] = 'start_date = ?';
            $types .= 's';
            $values[] = $startDate;
        }
        if ($internshipsHasRequiredHoursColumn) {
            $updates[] = 'required_hours = ?';
            $types .= 'i';
            $values[] = $requiredHours;
        }

        if ($updates !== []) {
            $sql = 'UPDATE internships SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?';
            $types .= 'i';
            $values[] = (int)$latestInternal['id'];
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
            }
        }
    } else {
        $insertCols = ['student_id'];
        $insertVals = [$targetStudentId];
        $insertTypes = 'i';

        if ($internshipsHasTypeColumn) {
            $insertCols[] = 'type';
            $insertVals[] = 'internal';
            $insertTypes .= 's';
        }
        if ($internshipsHasStatusColumn) {
            $insertCols[] = 'status';
            $insertVals[] = 'ongoing';
            $insertTypes .= 's';
        }
        if ($internshipsHasStartDateColumn) {
            $insertCols[] = 'start_date';
            $insertVals[] = $startDate;
            $insertTypes .= 's';
        }
        if ($internshipsHasRequiredHoursColumn) {
            $insertCols[] = 'required_hours';
            $insertVals[] = $requiredHours;
            $insertTypes .= 'i';
        }
        if (ojt_internal_column_exists($conn, 'internships', 'rendered_hours')) {
            $insertCols[] = 'rendered_hours';
            $insertVals[] = 0.0;
            $insertTypes .= 'd';
        }
        if (ojt_internal_column_exists($conn, 'internships', 'completion_percentage')) {
            $insertCols[] = 'completion_percentage';
            $insertVals[] = 0.0;
            $insertTypes .= 'd';
        }
        if (ojt_internal_column_exists($conn, 'internships', 'created_at')) {
            $insertCols[] = 'created_at';
        }
        if (ojt_internal_column_exists($conn, 'internships', 'updated_at')) {
            $insertCols[] = 'updated_at';
        }

        $placeholders = [];
        foreach ($insertCols as $column) {
            $placeholders[] = in_array($column, ['created_at', 'updated_at'], true) ? 'NOW()' : '?';
        }
        $insertStmt = $conn->prepare('INSERT INTO internships (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')');
        if ($insertStmt) {
            $insertStmt->bind_param($insertTypes, ...$insertVals);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }

    if ($hasAssignmentTrack) {
        $studentTrackStmt = $conn->prepare("UPDATE students SET assignment_track = 'internal', updated_at = NOW() WHERE id = ?");
        if ($studentTrackStmt) {
            $studentTrackStmt->bind_param('i', $targetStudentId);
            $studentTrackStmt->execute();
            $studentTrackStmt->close();
        }
    }

    $_SESSION['ojt_internal_flash_type'] = 'success';
    $_SESSION['ojt_internal_flash_message'] = 'Internal start date saved successfully.';
    header('Location: ' . $redirectTarget);
    exit;
}

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

$internshipJoinSql = '';
$internshipStatusSelect = "'' AS internship_status";
if ($internshipsTableExists) {
    $internshipTypeFilter = '';
    if ($internshipsHasTypeColumn) {
        $internshipTypeFilter = "WHERE LOWER(TRIM(COALESCE(type, 'internal'))) = 'internal'";
    }
    $internshipJoinSql = "
    LEFT JOIN (
        SELECT i_full.student_id, COALESCE(i_full.status, '') AS status
        FROM internships i_full
        INNER JOIN (
            SELECT student_id, MAX(id) AS latest_id
            FROM internships
            {$internshipTypeFilter}
            GROUP BY student_id
        ) i_latest ON i_latest.latest_id = i_full.id
    ) intern ON intern.student_id = s.id
    ";
    $internshipStatusSelect = "COALESCE(intern.status, '') AS internship_status";
}

$rows = [];
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
        COALESCE(oi.course_id, s.course_id, 0) AS resolved_course_id,
        COALESCE(oi.section_id, s.section_id, 0) AS resolved_section_id,
        {$internshipStatusSelect},
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
    {$internshipJoinSql}
    ORDER BY oi.last_name ASC, oi.first_name ASC, oi.student_no ASC
";
$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->close();
}

$studentTrackFilter = '';
if ($hasAssignmentTrack) {
    $studentTrackFilter = "AND LOWER(TRIM(COALESCE(s.assignment_track, 'internal'))) = 'internal'";
}

$studentsOnlySql = "
    SELECT
        COALESCE(NULLIF(s.student_id, ''), '') AS student_no,
        NULL AS ojt_user_id,
        COALESCE(s.last_name, '') AS last_name,
        COALESCE(s.first_name, '') AS first_name,
        COALESCE(s.middle_name, '') AS middle_name,
        NULL AS ojt_course_id,
        NULL AS ojt_section_id,
        COALESCE(s.email, '') AS ojt_email,
        '' AS ojt_status,
        NULL AS ojt_created_at,
        s.id AS student_row_id,
        s.user_id AS student_user_id,
        s.student_id,
        s.status AS students_status,
        " . ($hasAssignmentTrack ? "s.assignment_track" : "'internal'") . " AS assignment_track,
        s.created_at AS students_created_at,
        COALESCE(s.course_id, 0) AS resolved_course_id,
        COALESCE(s.section_id, 0) AS resolved_section_id,
        {$internshipStatusSelect},
        COALESCE(u.name, CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS account_name,
        COALESCE(c.name, 'N/A') AS course_name,
        COALESCE(NULLIF(sec.code, ''), sec.name, 'N/A') AS section_name
    FROM students s
    LEFT JOIN ojt_internal oi_match ON TRIM(COALESCE(oi_match.student_no, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    {$internshipJoinSql}
    WHERE oi_match.student_no IS NULL
    {$studentTrackFilter}
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

    $row['resolved_course_id'] = (int)($row['resolved_course_id'] ?? 0);
    $row['resolved_section_id'] = (int)($row['resolved_section_id'] ?? 0);
    $statusKey = ojt_internal_list_status_key($row);
    $row['internal_status_key'] = $statusKey;
    $row['internal_status_label'] = ojt_internal_list_status_label($statusKey);
    $row['internal_status_badge_class'] = ojt_internal_list_status_badge_class($statusKey);

    if ($filterCourseId > 0 && $row['resolved_course_id'] !== $filterCourseId) {
        continue;
    }
    if ($filterSectionId > 0 && $row['resolved_section_id'] !== $filterSectionId) {
        continue;
    }
    if ($filterOjtStatus !== 'all' && $statusKey !== $filterOjtStatus) {
        continue;
    }
    if (!ojt_internal_list_matches_search($row, $search)) {
        continue;
    }

    $normalizedRows[] = $row;
}
$rows = $normalizedRows;

$linkedCount = 0;
$statusCounts = [
    'ongoing' => 0,
    'finished' => 0,
    'not_started' => 0,
];
foreach ($rows as $row) {
    if ((int)($row['student_user_id'] ?? 0) > 0 || (int)($row['ojt_user_id'] ?? 0) > 0) {
        $linkedCount++;
    }
    $statusKey = (string)($row['internal_status_key'] ?? 'not_started');
    if (array_key_exists($statusKey, $statusCounts)) {
        $statusCounts[$statusKey]++;
    }
}

$page_title = 'Internal Student List';
$page_body_class = 'page-fingerprint-mapping page-ojt-internal-list';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
    'assets/css/modules/pages/page-ojt-internal-list.css',
];
$page_scripts = [
    'assets/js/modules/pages/ojt-list-select.js',
    'assets/js/modules/pages/ojt-list-print.js',
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
                <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="ojtInternalActionsMenu">
                    <i class="feather-grid me-1"></i>
                    <span>Actions</span>
                </button>
                <div class="page-header-actions app-ojt-actions-panel" id="ojtInternalActionsMenu">
                    <div class="dashboard-actions-panel">
                        <div class="dashboard-actions-meta">
                            <span class="text-muted fs-12">Quick Actions</span>
                        </div>
                        <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <a href="import-ojt-internal.php" class="action-tile action-tile-primary" data-action-priority="1">
                                <i class="feather-download"></i>
                                <span>Import OJT Internal</span>
                            </a>
                            <a href="import-ojt-external.php" class="action-tile">
                                <i class="feather-download-cloud"></i>
                                <span>Import OJT External</span>
                            </a>
                            <button type="button" class="action-tile" data-ojt-print-selected="ojtInternalListTable">
                                <i class="feather-printer"></i>
                                <span>Print Selected</span>
                            </button>
                            <a href="ojt-external-list.php" class="action-tile">
                                <i class="feather-list"></i>
                                <span>View External List</span>
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
            <?php if (!empty($_SESSION['ojt_internal_flash_message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string)($_SESSION['ojt_internal_flash_type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?> py-2">
                    <?php echo htmlspecialchars((string)$_SESSION['ojt_internal_flash_message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php unset($_SESSION['ojt_internal_flash_message'], $_SESSION['ojt_internal_flash_type']); ?>
            <?php endif; ?>
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
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="search">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Student no, name, email">
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
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="ojt_status">OJT Status</label>
                            <select class="form-select" id="ojt_status" name="ojt_status">
                                <option value="all" <?php echo $filterOjtStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="ongoing" <?php echo $filterOjtStatus === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="finished" <?php echo $filterOjtStatus === 'finished' ? 'selected' : ''; ?>>Finished</option>
                                <option value="not_started" <?php echo $filterOjtStatus === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 fm-actions">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="ojt-internal-list.php<?php echo $mapFingerId > 0 ? '?map_finger_id=' . $mapFingerId : ''; ?>" class="btn btn-light">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <small class="text-muted">Total internal rows: <?php echo count($rows); ?> | Linked with registered account: <?php echo $linkedCount; ?> | Ongoing: <?php echo (int)$statusCounts['ongoing']; ?> | Finished: <?php echo (int)$statusCounts['finished']; ?> | Not Started: <?php echo (int)$statusCounts['not_started']; ?></small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 bio-console-table" id="ojtInternalListTable" data-ojt-select-table data-print-title="Internal Student List" data-print-subtitle="<?php echo htmlspecialchars($mapFingerId > 0 ? 'Fingerprint mapping mode' : 'Filtered internal list', ENT_QUOTES, 'UTF-8'); ?>">
                            <thead>
                                <tr>
                                    <th class="app-ojt-select-column">
                                        <div class="form-check app-ojt-select-check">
                                            <input class="form-check-input" type="checkbox" data-ojt-select-all aria-label="Select all internal students">
                                        </div>
                                    </th>
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
                                    <td colspan="7" class="text-center text-muted py-4" data-print-exclude="1">No internal students found for the selected filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="app-ojt-select-column" data-print-exclude="1">
                                            <div class="form-check app-ojt-select-check">
                                                <input class="form-check-input" type="checkbox" data-ojt-row-select aria-label="Select student <?php echo htmlspecialchars((string)$row['student_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars(trim((string)($row['student_no'] ?? '')) !== '' ? (string)$row['student_no'] : 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
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
                                            <span class="badge <?php echo htmlspecialchars((string)($row['internal_status_badge_class'] ?? 'bg-soft-secondary text-muted'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($row['internal_status_label'] ?? 'Not Started'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (trim((string)($row['internship_status'] ?? '')) !== ''): ?>
                                                <div><small class="text-muted">Internship: <?php echo htmlspecialchars(ucfirst((string)$row['internship_status']), ENT_QUOTES, 'UTF-8'); ?></small></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" data-print-exclude="1">
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
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px;">
                                                        <?php if ((int)($row['student_row_id'] ?? 0) > 0): ?>
                                                            <a class="dropdown-item mb-2" href="students-view.php?id=<?php echo (int)$row['student_row_id']; ?>">
                                                                View Student
                                                            </a>
                                                            <form method="post" class="d-grid gap-2">
                                                                <input type="hidden" name="internal_action" value="start_internal">
                                                                <input type="hidden" name="student_id" value="<?php echo (int)$row['student_row_id']; ?>">
                                                                <label class="form-label small mb-0">Start Internal Date</label>
                                                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                                <button type="submit" class="btn btn-sm btn-primary">Start Internal</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Linked student record not available yet.</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
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
<section class="student-list-print-sheet app-students-print-sheet app-ojt-selected-print-sheet" data-ojt-print-sheet="ojtInternalListTable" aria-hidden="true">
    <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title" data-ojt-print-title>INTERNAL STUDENT LIST</div>
    <div class="print-meta"><strong>FILTER:</strong> <span data-ojt-print-subtitle><?php echo htmlspecialchars($mapFingerId > 0 ? 'Fingerprint mapping mode' : 'Filtered internal list', ENT_QUOTES, 'UTF-8'); ?></span></div>
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
