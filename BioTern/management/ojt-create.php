<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/offices.php';
biotern_boot_session(isset($conn) ? $conn : null);
biotern_offices_ensure_schema($conn);

$ops_helpers = dirname(__DIR__) . '/lib/ops_helpers.php';
if (file_exists($ops_helpers)) {
    require_once $ops_helpers;
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

if (!function_exists('ojt_create_table_exists')) {
    function ojt_create_table_exists(mysqli $conn, string $table): bool {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('ojt_create_get_columns')) {
    function ojt_create_get_columns(mysqli $conn, string $table): array {
        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM {$table}");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $columns[] = (string)($row['Field'] ?? '');
            }
        }
        return $columns;
    }
}

if (!function_exists('ojt_create_current_school_year_label')) {
    function ojt_create_current_school_year_label(?int $timestamp = null): string {
        $ts = $timestamp !== null ? $timestamp : time();
        $year = (int)date('Y', $ts);
        $month = (int)date('n', $ts);
        $startYear = $month >= 7 ? $year : ($year - 1);
        return sprintf('%d-%d', $startYear, $startYear + 1);
    }
}

if (empty($_SESSION['ojt_create_csrf'])) {
    $_SESSION['ojt_create_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['ojt_create_csrf'];

$message = '';
$message_type = 'info';

$studentOptions = [];
$coordinatorUsers = [];
$supervisorUsers = [];
$schoolYearOptions = [];

$defaultSchoolYear = ojt_create_current_school_year_label();
$baseYear = 2025;
$currentStartYear = (int)substr($defaultSchoolYear, 0, 4);
$maxStartYear = max($baseYear + 4, $currentStartYear + 2);
for ($start = $baseYear; $start <= $maxStartYear; $start++) {
    $schoolYearOptions[] = sprintf('%d-%d', $start, $start + 1);
}
if (!in_array($defaultSchoolYear, $schoolYearOptions, true)) {
    $schoolYearOptions[] = $defaultSchoolYear;
}

if (ojt_create_table_exists($conn, 'school_years')) {
    $syRes = $conn->query("SELECT year FROM school_years ORDER BY year DESC");
    if ($syRes) {
        while ($syRow = $syRes->fetch_assoc()) {
            $year = trim((string)($syRow['year'] ?? ''));
            if ($year !== '' && !in_array($year, $schoolYearOptions, true)) {
                $schoolYearOptions[] = $year;
            }
        }
    }
}
rsort($schoolYearOptions);
$schoolYearOptions = array_values(array_unique($schoolYearOptions));

$internshipJoinSql = '';
$studentCompanySelect = "'' AS current_company_name";
if (ojt_create_table_exists($conn, 'internships')) {
    $internshipCols = ojt_create_get_columns($conn, 'internships');
    $internshipDeletedWhere = in_array('deleted_at', $internshipCols, true) ? 'WHERE deleted_at IS NULL' : '';
    $internshipDeletedOuterWhere = in_array('deleted_at', $internshipCols, true) ? 'WHERE i_full.deleted_at IS NULL' : '';
    $internshipJoinSql = "
    LEFT JOIN (
        SELECT i_full.student_id, i_full.company_name
        FROM internships i_full
        INNER JOIN (
            SELECT student_id, MAX(id) AS latest_id
            FROM internships
            {$internshipDeletedWhere}
            GROUP BY student_id
        ) latest ON latest.latest_id = i_full.id
        {$internshipDeletedOuterWhere}
    ) i ON i.student_id = s.id";
    $studentCompanySelect = "COALESCE(i.company_name, '') AS current_company_name";
}

$studentQuery = "
    SELECT
        s.id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.course_id,
        s.section_id,
        COALESCE(NULLIF(TRIM(s.school_year), ''), '') AS school_year,
        COALESCE(NULLIF(TRIM(s.semester), ''), '') AS semester,
        s.assignment_track,
        COALESCE(NULLIF(s.internal_total_hours, 0), 140) AS internal_total_hours,
        COALESCE(NULLIF(s.external_total_hours, 0), 250) AS external_total_hours,
        COALESCE(NULLIF(c.name, ''), '-') AS course_name,
        COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name,
        COALESCE(u.application_status, 'approved') AS application_status,
        {$studentCompanySelect}
    FROM students s
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    {$internshipJoinSql}
    WHERE COALESCE(u.application_status, 'approved') = 'approved'
      AND COALESCE(s.status, 1) = 1
    ORDER BY s.last_name ASC, s.first_name ASC
";
$studentRes = $conn->query($studentQuery);
if ($studentRes) {
    while ($row = $studentRes->fetch_assoc()) {
        $studentOptions[] = $row;
    }
}

$userCols = ojt_create_table_exists($conn, 'users') ? ojt_create_get_columns($conn, 'users') : [];
$hasActiveCol = in_array('is_active', $userCols, true);
$coordUserQuery = "SELECT id, name, email FROM users WHERE role = 'coordinator'";
if ($hasActiveCol) {
    $coordUserQuery .= " AND (is_active = 1 OR is_active IS NULL)";
}
$coordUserQuery .= " ORDER BY name ASC";
$coordRes = $conn->query($coordUserQuery);
if ($coordRes) {
    while ($row = $coordRes->fetch_assoc()) {
        $coordinatorUsers[] = $row;
    }
}

$superUserQuery = "SELECT id, name, email FROM users WHERE role = 'supervisor'";
if ($hasActiveCol) {
    $superUserQuery .= " AND (is_active = 1 OR is_active IS NULL)";
}
$superUserQuery .= " ORDER BY name ASC";
$superRes = $conn->query($superUserQuery);
if ($superRes) {
    while ($row = $superRes->fetch_assoc()) {
        $supervisorUsers[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ojt'])) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $message = 'Invalid form token. Please reload and try again.';
        $message_type = 'danger';
    } else {
        $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $coordinatorUserId = isset($_POST['coordinator_user_id']) ? (int)$_POST['coordinator_user_id'] : 0;
        $supervisorUserId = isset($_POST['supervisor_user_id']) ? (int)$_POST['supervisor_user_id'] : 0;
        $type = strtolower(trim((string)($_POST['type'] ?? 'internal')));
        $status = strtolower(trim((string)($_POST['status'] ?? 'ongoing')));
        $schoolYear = trim((string)($_POST['school_year'] ?? $defaultSchoolYear));
        $requiredHoursRaw = trim((string)($_POST['required_hours'] ?? '0'));
        $internalHoursRaw = trim((string)($_POST['internal_total_hours'] ?? '140'));
        $externalHoursRaw = trim((string)($_POST['external_total_hours'] ?? '250'));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $companyName = trim((string)($_POST['company_name'] ?? ''));
        $position = trim((string)($_POST['position'] ?? ''));

        $requiredHours = is_numeric($requiredHoursRaw) ? (int)$requiredHoursRaw : -1;
        $internalHours = is_numeric($internalHoursRaw) ? (int)$internalHoursRaw : -1;
        $externalHours = is_numeric($externalHoursRaw) ? (int)$externalHoursRaw : -1;

        if ($studentId <= 0) {
            $message = 'Please select a student.';
            $message_type = 'danger';
        } elseif ($coordinatorUserId <= 0 || $supervisorUserId <= 0) {
            $message = 'Coordinator and supervisor assignments are required.';
            $message_type = 'danger';
        } elseif (!in_array($type, ['internal', 'external'], true)) {
            $message = 'Invalid internship type.';
            $message_type = 'danger';
        } elseif (!in_array($status, ['ongoing', 'completed', 'dropped'], true)) {
            $message = 'Invalid internship status.';
            $message_type = 'danger';
        } elseif ($requiredHours < 0 || $internalHours < 0 || $externalHours < 0) {
            $message = 'Hours must be non-negative numbers.';
            $message_type = 'danger';
        } elseif ($startDate === '') {
            $message = 'Start date is required.';
            $message_type = 'danger';
        } elseif ($endDate !== '' && strtotime($endDate) !== false && strtotime($startDate) !== false && strtotime($endDate) < strtotime($startDate)) {
            $message = 'End date cannot be earlier than start date.';
            $message_type = 'danger';
        } else {
            $existingIntern = $conn->prepare("SELECT id, status FROM internships WHERE student_id = ? ORDER BY id DESC LIMIT 1");
            $existingIntern->bind_param('i', $studentId);
            $existingIntern->execute();
            $existingRow = $existingIntern->get_result()->fetch_assoc();
            $existingIntern->close();

            if ($existingRow) {
                $message = 'This student already has an OJT assignment. Use OJT Edit to modify it.';
                $message_type = 'warning';
            } else {
                $studentLookup = $conn->prepare("SELECT id, user_id, course_id, department_id FROM students WHERE id = ? LIMIT 1");
                $studentLookup->bind_param('i', $studentId);
                $studentLookup->execute();
                $studentRow = $studentLookup->get_result()->fetch_assoc();
                $studentLookup->close();

                if (!$studentRow) {
                    $message = 'Selected student was not found.';
                    $message_type = 'danger';
                } else {
                    $studentCols = ojt_create_get_columns($conn, 'students');
                    $internCols = ojt_create_get_columns($conn, 'internships');

                    $coordName = '';
                    $supName = '';
                    foreach ($coordinatorUsers as $item) {
                        if ((int)$item['id'] === $coordinatorUserId) {
                            $coordName = trim((string)($item['name'] ?? ''));
                            break;
                        }
                    }
                    foreach ($supervisorUsers as $item) {
                        if ((int)$item['id'] === $supervisorUserId) {
                            $supName = trim((string)($item['name'] ?? ''));
                            break;
                        }
                    }

                    $coordRefId = 0;
                    $supRefId = 0;
                    if (ojt_create_table_exists($conn, 'coordinators')) {
                        $mapCoord = $conn->prepare("SELECT id FROM coordinators WHERE user_id = ? LIMIT 1");
                        $mapCoord->bind_param('i', $coordinatorUserId);
                        $mapCoord->execute();
                        $coordRef = $mapCoord->get_result()->fetch_assoc();
                        $mapCoord->close();
                        if ($coordRef) {
                            $coordRefId = (int)$coordRef['id'];
                        }
                    }
                    if (ojt_create_table_exists($conn, 'supervisors')) {
                        $mapSup = $conn->prepare("SELECT id FROM supervisors WHERE user_id = ? LIMIT 1");
                        $mapSup->bind_param('i', $supervisorUserId);
                        $mapSup->execute();
                        $supRef = $mapSup->get_result()->fetch_assoc();
                        $mapSup->close();
                        if ($supRef) {
                            $supRefId = (int)$supRef['id'];
                        }
                    }

                    $computedRequired = $requiredHours;
                    if ($computedRequired === 0) {
                        $computedRequired = $type === 'external' ? $externalHours : $internalHours;
                    }

                    $internalRemaining = $type === 'external' ? 0 : $internalHours;
                    $externalRemaining = $type === 'external' ? $externalHours : 0;

                    $conn->begin_transaction();
                    try {
                        $studentSets = [];
                        $studentTypes = '';
                        $studentVals = [];

                        if (in_array('assignment_track', $studentCols, true)) {
                            $studentSets[] = 'assignment_track = ?';
                            $studentTypes .= 's';
                            $studentVals[] = $type;
                        }
                        if (in_array('internal_total_hours', $studentCols, true)) {
                            $studentSets[] = 'internal_total_hours = ?';
                            $studentTypes .= 'i';
                            $studentVals[] = $internalHours;
                        }
                        if (in_array('external_total_hours', $studentCols, true)) {
                            $studentSets[] = 'external_total_hours = ?';
                            $studentTypes .= 'i';
                            $studentVals[] = $externalHours;
                        }
                        if (in_array('internal_total_hours_remaining', $studentCols, true)) {
                            $studentSets[] = 'internal_total_hours_remaining = ?';
                            $studentTypes .= 'i';
                            $studentVals[] = $internalRemaining;
                        }
                        if (in_array('external_total_hours_remaining', $studentCols, true)) {
                            $studentSets[] = 'external_total_hours_remaining = ?';
                            $studentTypes .= 'i';
                            $studentVals[] = $externalRemaining;
                        }
                        if (in_array('coordinator_name', $studentCols, true)) {
                            $studentSets[] = 'coordinator_name = ?';
                            $studentTypes .= 's';
                            $studentVals[] = $coordName;
                        }
                        if (in_array('supervisor_name', $studentCols, true)) {
                            $studentSets[] = 'supervisor_name = ?';
                            $studentTypes .= 's';
                            $studentVals[] = $supName;
                        }
                        if (in_array('coordinator_id', $studentCols, true) && $coordRefId > 0) {
                            $studentSets[] = 'coordinator_id = ?';
                            $studentTypes .= 'i';
                            $studentVals[] = $coordRefId;
                        }
                        if (in_array('supervisor_id', $studentCols, true) && $supRefId > 0) {
                            $studentSets[] = 'supervisor_id = ?';
                            $studentTypes .= 'i';
                            $studentVals[] = $supRefId;
                        }
                        if (in_array('updated_at', $studentCols, true)) {
                            $studentSets[] = 'updated_at = NOW()';
                        }

                        if (!empty($studentSets)) {
                            $studentSql = 'UPDATE students SET ' . implode(', ', $studentSets) . ' WHERE id = ? LIMIT 1';
                            $studentTypes .= 'i';
                            $studentVals[] = $studentId;
                            $studentStmt = $conn->prepare($studentSql);
                            $studentStmt->bind_param($studentTypes, ...$studentVals);
                            if (!$studentStmt->execute()) {
                                throw new Exception('Failed to update student assignment context.');
                            }
                            $studentStmt->close();
                        }

                        $insertCols = ['student_id'];
                        $insertTypes = 'i';
                        $insertVals = [$studentId];

                        if (in_array('course_id', $internCols, true)) {
                            $insertCols[] = 'course_id';
                            $insertTypes .= 'i';
                            $insertVals[] = (int)($studentRow['course_id'] ?? 0);
                        }
                        if (in_array('department_id', $internCols, true)) {
                            $insertCols[] = 'department_id';
                            $insertTypes .= 'i';
                            $insertVals[] = (int)($studentRow['department_id'] ?? 0);
                        }
                        if (in_array('coordinator_id', $internCols, true)) {
                            $insertCols[] = 'coordinator_id';
                            $insertTypes .= 'i';
                            $insertVals[] = $coordinatorUserId;
                        }
                        if (in_array('supervisor_id', $internCols, true)) {
                            $insertCols[] = 'supervisor_id';
                            $insertTypes .= 'i';
                            $insertVals[] = $supervisorUserId;
                        }
                        $primaryOffice = biotern_supervisor_primary_office($conn, $supervisorUserId);
                        if (in_array('office_id', $internCols, true) && (int)$primaryOffice['id'] > 0) {
                            $insertCols[] = 'office_id';
                            $insertTypes .= 'i';
                            $insertVals[] = (int)$primaryOffice['id'];
                        }
                        if (in_array('type', $internCols, true)) {
                            $insertCols[] = 'type';
                            $insertTypes .= 's';
                            $insertVals[] = $type;
                        }
                        if (in_array('status', $internCols, true)) {
                            $insertCols[] = 'status';
                            $insertTypes .= 's';
                            $insertVals[] = $status;
                        }
                        if (in_array('school_year', $internCols, true)) {
                            $insertCols[] = 'school_year';
                            $insertTypes .= 's';
                            $insertVals[] = $schoolYear;
                        }
                        if (in_array('required_hours', $internCols, true)) {
                            $insertCols[] = 'required_hours';
                            $insertTypes .= 'i';
                            $insertVals[] = $computedRequired;
                        }
                        if (in_array('rendered_hours', $internCols, true)) {
                            $insertCols[] = 'rendered_hours';
                            $insertTypes .= 'd';
                            $insertVals[] = 0.0;
                        }
                        if (in_array('completion_percentage', $internCols, true)) {
                            $insertCols[] = 'completion_percentage';
                            $insertTypes .= 'd';
                            $insertVals[] = 0.0;
                        }
                        if (in_array('start_date', $internCols, true)) {
                            $insertCols[] = 'start_date';
                            $insertTypes .= 's';
                            $insertVals[] = $startDate;
                        }
                        if (in_array('end_date', $internCols, true)) {
                            $insertCols[] = 'end_date';
                            $insertTypes .= 's';
                            $insertVals[] = ($endDate === '' ? null : $endDate);
                        }
                        if (in_array('company_name', $internCols, true)) {
                            $insertCols[] = 'company_name';
                            $insertTypes .= 's';
                            $insertVals[] = $companyName !== '' ? $companyName : (string)$primaryOffice['name'];
                        }
                        if (in_array('position', $internCols, true)) {
                            $insertCols[] = 'position';
                            $insertTypes .= 's';
                            $insertVals[] = $position;
                        }
                        if (in_array('created_at', $internCols, true)) {
                            $insertCols[] = 'created_at';
                        }
                        if (in_array('updated_at', $internCols, true)) {
                            $insertCols[] = 'updated_at';
                        }

                        $placeholders = [];
                        foreach ($insertCols as $columnName) {
                            if ($columnName === 'created_at' || $columnName === 'updated_at') {
                                $placeholders[] = 'NOW()';
                            } else {
                                $placeholders[] = '?';
                            }
                        }

                        $insertSql = 'INSERT INTO internships (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                        $insertStmt = $conn->prepare($insertSql);
                        $insertStmt->bind_param($insertTypes, ...$insertVals);
                        if (!$insertStmt->execute()) {
                            throw new Exception('Failed to create OJT assignment.');
                        }
                        $newInternshipId = (int)$insertStmt->insert_id;
                        $insertStmt->close();

                        $conn->commit();
                        $_SESSION['ojt_create_flash_type'] = 'success';
                        $_SESSION['ojt_create_flash_message'] = 'OJT assignment created successfully.';
                        header('Location: ojt-view.php?id=' . $studentId . '&internship_id=' . $newInternshipId);
                        exit;
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $message = 'Unable to create OJT assignment: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
}

if (isset($_SESSION['ojt_create_flash_message']) && $message === '') {
    $message = (string)$_SESSION['ojt_create_flash_message'];
    $message_type = (string)($_SESSION['ojt_create_flash_type'] ?? 'info');
    unset($_SESSION['ojt_create_flash_message'], $_SESSION['ojt_create_flash_type']);
}

$recentAssignments = [];
$recentSql = "
    SELECT
        i.id,
        i.student_id,
        i.status,
        i.type,
        i.school_year,
        i.required_hours,
        i.start_date,
        s.student_id AS school_id,
        s.first_name,
        s.last_name,
        COALESCE(u_coord.name, s.coordinator_name, '-') AS coordinator_name,
        COALESCE(u_sup.name, s.supervisor_name, '-') AS supervisor_name
    FROM internships i
    INNER JOIN students s ON s.id = i.student_id
    LEFT JOIN users u_coord ON u_coord.id = i.coordinator_id
    LEFT JOIN users u_sup ON u_sup.id = i.supervisor_id
    ORDER BY i.id DESC
    LIMIT 12
";
$recentRes = $conn->query($recentSql);
if ($recentRes) {
    while ($row = $recentRes->fetch_assoc()) {
        $recentAssignments[] = $row;
    }
}

$page_title = 'BioTern || OJT Create';
$page_styles = [
    'assets/css/modules/management/management-ojt-shared.css',
    'assets/css/modules/management/management-ojt-create.css',
];
$page_scripts = [
    'assets/js/modules/management/ojt-create-runtime.js',
    'assets/js/theme-customizer-init.min.js',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">OJT Create</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="ojt.php">OJT</a></li>
            <li class="breadcrumb-item">OJT Create</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-lg-5">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title mb-0">New Assignment Form</h5>
                </div>
                <div class="card-body">
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="" autocomplete="off">
                        <input type="hidden" name="create_ojt" value="1">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label class="form-label">Student *</label>
                            <div class="ojt-create-student-picker">
                                <input type="hidden" name="student_id" id="studentSelect" required>
                                <button type="button" class="form-select text-start ojt-create-student-trigger" id="studentPickerTrigger" data-bs-toggle="modal" data-bs-target="#studentPickerModal">
                                    <span id="selectedStudentLabel">Select student</span>
                                </button>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Track *</label>
                                <select name="type" id="typeSelect" class="form-select" required>
                                    <option value="internal">Internal</option>
                                    <option value="external">External</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="ongoing" selected>Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="dropped">Dropped</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mt-0">
                            <div class="col-md-6">
                                <label class="form-label">School Year *</label>
                                <select name="school_year" class="form-select" required>
                                    <?php foreach ($schoolYearOptions as $yearLabel): ?>
                                        <option value="<?php echo htmlspecialchars($yearLabel, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($yearLabel === $defaultSchoolYear) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($yearLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Required Hours *</label>
                                <input type="number" name="required_hours" id="requiredHoursInput" class="form-control" min="0" value="140" required>
                            </div>
                        </div>

                        <div class="row g-2 mt-0">
                            <div class="col-md-6">
                                <label class="form-label">Internal Total Hours *</label>
                                <input type="number" name="internal_total_hours" id="internalHoursInput" class="form-control" min="0" value="140" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">External Total Hours *</label>
                                <input type="number" name="external_total_hours" id="externalHoursInput" class="form-control" min="0" value="250" required>
                            </div>
                        </div>

                        <div class="row g-2 mt-0">
                            <div class="col-md-6">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3 mt-2">
                            <label class="form-label">Coordinator *</label>
                            <select name="coordinator_user_id" class="form-select" required>
                                <option value="">Select coordinator</option>
                                <?php foreach ($coordinatorUsers as $coordinator): ?>
                                    <option value="<?php echo (int)$coordinator['id']; ?>">
                                        <?php echo htmlspecialchars((string)$coordinator['name'] . ' (' . (string)($coordinator['email'] ?? '-') . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Supervisor *</label>
                            <select name="supervisor_user_id" class="form-select" required>
                                <option value="">Select supervisor</option>
                                <?php foreach ($supervisorUsers as $supervisor): ?>
                                    <option value="<?php echo (int)$supervisor['id']; ?>">
                                        <?php echo htmlspecialchars((string)$supervisor['name'] . ' (' . (string)($supervisor['email'] ?? '-') . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" name="position" class="form-control" placeholder="Optional">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Create OJT Assignment</button>
                            <a href="ojt.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent OJT Assignments</h5>
                    <a href="ojt.php" class="btn btn-sm btn-outline-primary">Open OJT List</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>School Year</th>
                                    <th>Hours</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($recentAssignments)): ?>
                                <?php foreach ($recentAssignments as $index => $assignment): ?>
                                    <?php
                                    $studentLabel = trim((string)($assignment['school_id'] ?? '-') . ' | ' . (string)($assignment['last_name'] ?? '') . ', ' . (string)($assignment['first_name'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><?php echo (int)$index + 1; ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($studentLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted">
                                                C: <?php echo htmlspecialchars((string)($assignment['coordinator_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> |
                                                S: <?php echo htmlspecialchars((string)($assignment['supervisor_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars(ucfirst((string)($assignment['type'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst((string)($assignment['status'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($assignment['school_year'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)($assignment['required_hours'] ?? 0); ?></td>
                                        <td>
                                            <a href="ojt-view.php?id=<?php echo (int)($assignment['student_id'] ?? 0); ?>" class="btn btn-sm btn-light">View</a>
                                            <a href="ojt-edit.php?id=<?php echo (int)($assignment['student_id'] ?? 0); ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No internship assignments found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<div class="modal fade" id="studentPickerModal" tabindex="-1" aria-labelledby="studentPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content ojt-create-student-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="studentPickerModalLabel">Select Student</h5>
                    <p class="text-muted mb-0 small">Choose the student to assign. Filters use each student's current profile information.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small" for="studentPickerSearch">Search</label>
                        <input type="search" class="form-control" id="studentPickerSearch" placeholder="Student no, name">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="studentPickerYear">School Year</label>
                        <select class="form-select" id="studentPickerYear">
                            <option value="">All school years</option>
                            <?php foreach (array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row['school_year'] ?? '')), $studentOptions)))) as $yearOption): ?>
                                <option value="<?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="studentPickerSemester">Semester</label>
                        <select class="form-select" id="studentPickerSemester">
                            <option value="">All semesters</option>
                            <?php foreach (array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row['semester'] ?? '')), $studentOptions)))) as $semesterOption): ?>
                                <option value="<?php echo htmlspecialchars($semesterOption, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($semesterOption, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="studentPickerCourse">Course</label>
                        <select class="form-select" id="studentPickerCourse">
                            <option value="">All courses</option>
                            <?php $coursePickerOptions = []; foreach ($studentOptions as $student) { $cid = (int)($student['course_id'] ?? 0); $cname = trim((string)($student['course_name'] ?? '')); if ($cid > 0 && $cname !== '') { $coursePickerOptions[$cid] = $cname; }} asort($coursePickerOptions, SORT_NATURAL | SORT_FLAG_CASE); foreach ($coursePickerOptions as $cid => $cname): ?>
                                <option value="<?php echo (int)$cid; ?>"><?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="studentPickerSection">Section</label>
                        <select class="form-select" id="studentPickerSection">
                            <option value="">All sections</option>
                            <?php $sectionPickerOptions = []; foreach ($studentOptions as $student) { $sid = (int)($student['section_id'] ?? 0); $sname = biotern_format_section_code((string)($student['section_name'] ?? '')); if ($sid > 0 && $sname !== '') { $sectionPickerOptions[$sid] = $sname; }} asort($sectionPickerOptions, SORT_NATURAL | SORT_FLAG_CASE); foreach ($sectionPickerOptions as $sid => $sname): ?>
                                <option value="<?php echo (int)$sid; ?>"><?php echo htmlspecialchars($sname, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive ojt-create-student-table-wrap">
                    <table class="table table-hover align-middle mb-0" id="studentPickerTable">
                        <thead><tr><th></th><th>Student No.</th><th>Name</th><th>Course / Section</th><th>Track</th><th>School Year</th><th>Semester</th><th>Current Company</th></tr></thead>
                        <tbody>
                        <?php foreach ($studentOptions as $student): ?>
                            <?php
                            $studentPk = (int)($student['id'] ?? 0);
                            $schoolId = trim((string)($student['student_id'] ?? ''));
                            $displayName = trim((string)($student['last_name'] ?? '') . ', ' . (string)($student['first_name'] ?? '') . ' ' . (string)($student['middle_name'] ?? ''));
                            $course = trim((string)($student['course_name'] ?? '-'));
                            $section = biotern_format_section_code((string)($student['section_name'] ?? '-'));
                            $track = ucfirst(strtolower(trim((string)($student['assignment_track'] ?? 'internal'))));
                            $schoolYear = trim((string)($student['school_year'] ?? ''));
                            $semester = trim((string)($student['semester'] ?? ''));
                            $company = trim((string)($student['current_company_name'] ?? ''));
                            $internalDefault = (int)($student['internal_total_hours'] ?? 140);
                            $externalDefault = (int)($student['external_total_hours'] ?? 250);
                            $label = $schoolId . ' | ' . $displayName . ' | ' . $course . ' - ' . $section;
                            ?>
                            <tr data-student-picker-row data-student-id="<?php echo $studentPk; ?>" data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars(strtolower($schoolId . ' ' . $displayName . ' ' . $course . ' ' . $section), ENT_QUOTES, 'UTF-8'); ?>" data-school-year="<?php echo htmlspecialchars($schoolYear, ENT_QUOTES, 'UTF-8'); ?>" data-semester="<?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?>" data-course-id="<?php echo (int)($student['course_id'] ?? 0); ?>" data-section-id="<?php echo (int)($student['section_id'] ?? 0); ?>" data-track="<?php echo htmlspecialchars(strtolower($track), ENT_QUOTES, 'UTF-8'); ?>" data-internal-hours="<?php echo $internalDefault; ?>" data-external-hours="<?php echo $externalDefault; ?>">
                                <td><input class="form-check-input" type="radio" name="student_picker_choice" value="<?php echo $studentPk; ?>"></td>
                                <td><?php echo htmlspecialchars($schoolId !== '' ? $schoolId : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($displayName !== '' ? $displayName : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($course . ' / ' . $section, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($track, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($schoolYear !== '' ? $schoolYear : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($semester !== '' ? $semester : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($company !== '' ? $company : 'No company yet', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-warning mt-3 mb-0 d-none" id="studentPickerEmpty">No students match the current filters.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="studentPickerApply" data-bs-dismiss="modal">Select Student</button>
            </div>
        </div>
    </div>
</div>
<?php
include 'includes/footer.php';
$conn->close();
?>




