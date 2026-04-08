<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_submitted_at DATETIME NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approval_notes VARCHAR(255) NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS disciplinary_remark VARCHAR(255) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS gender VARCHAR(30) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(255) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(50) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS school_year VARCHAR(16) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS semester VARCHAR(30) NULL");

$departmentOptions = [];
$courseOptions = [];
$sectionOptions = [];
$coordinatorOptions = [];
$supervisorOptions = [];

$courseResult = $conn->query("SELECT id, code, name FROM courses ORDER BY name ASC");
if ($courseResult) {
    while ($course = $courseResult->fetch_assoc()) {
        $courseOptions[] = [
            'id' => (int)($course['id'] ?? 0),
            'code' => trim((string)($course['code'] ?? '')),
            'name' => trim((string)($course['name'] ?? '')),
        ];
    }
}

$sectionResult = $conn->query("SELECT id, course_id, code, name FROM sections ORDER BY code ASC, name ASC");
if ($sectionResult) {
    while ($sec = $sectionResult->fetch_assoc()) {
        $sectionOptions[] = [
            'id' => (int)($sec['id'] ?? 0),
            'course_id' => (int)($sec['course_id'] ?? 0),
            'code' => trim((string)($sec['code'] ?? '')),
            'name' => trim((string)($sec['name'] ?? '')),
        ];
    }
}

$departmentResult = $conn->query("SELECT id, code, name FROM departments ORDER BY name ASC");
if ($departmentResult) {
    while ($dep = $departmentResult->fetch_assoc()) {
        $departmentOptions[] = [
            'id' => (int)($dep['id'] ?? 0),
            'code' => trim((string)($dep['code'] ?? '')),
            'name' => trim((string)($dep['name'] ?? '')),
        ];
    }
}

$coordinatorResult = $conn->query("SELECT id, department_id, CONCAT(first_name, ' ', last_name) AS full_name FROM coordinators WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
if ($coordinatorResult) {
    while ($coor = $coordinatorResult->fetch_assoc()) {
        $coordinatorOptions[] = [
            'id' => (int)($coor['id'] ?? 0),
            'department_id' => (int)($coor['department_id'] ?? 0),
            'full_name' => trim((string)($coor['full_name'] ?? '')),
        ];
    }
}

$supervisorResult = $conn->query("SELECT id, department_id, CONCAT(first_name, ' ', last_name) AS full_name FROM supervisors WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
if ($supervisorResult) {
    while ($sup = $supervisorResult->fetch_assoc()) {
        $supervisorOptions[] = [
            'id' => (int)($sup['id'] ?? 0),
            'department_id' => (int)($sup['department_id'] ?? 0),
            'full_name' => trim((string)($sup['full_name'] ?? '')),
        ];
    }
}

$coordinatorNameMap = [];
foreach ($coordinatorOptions as $item) {
    $coordinatorNameMap[(int)$item['id']] = (string)$item['full_name'];
}

$supervisorNameMap = [];
foreach ($supervisorOptions as $item) {
    $supervisorNameMap[(int)$item['id']] = (string)$item['full_name'];
}

function formatDisplayDateTime($rawValue)
{
    $value = trim((string)$rawValue);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('M d, Y h:i A', $timestamp);
}

function ensureApplicationsStagingTable(mysqli $conn)
{
    $ok = $conn->query("CREATE TABLE IF NOT EXISTS student_applications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        username VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        student_id VARCHAR(80) NULL,
        first_name VARCHAR(120) NOT NULL,
        middle_name VARCHAR(120) NULL,
        last_name VARCHAR(120) NOT NULL,
        course_id INT NULL,
        department_id INT NULL,
        section_id INT NULL,
        section_code_snapshot VARCHAR(80) NULL,
        section_name_snapshot VARCHAR(120) NULL,
        semester VARCHAR(30) NULL,
        school_year VARCHAR(16) NULL,
        address VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        date_of_birth DATE NULL,
        gender VARCHAR(30) NULL,
        supervisor_id INT NULL,
        supervisor_name VARCHAR(255) NULL,
        coordinator_id INT NULL,
        coordinator_name VARCHAR(255) NULL,
        internal_total_hours INT NULL,
        external_total_hours INT NULL,
        assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal',
        emergency_contact VARCHAR(255) NULL,
        emergency_contact_phone VARCHAR(50) NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        reviewed_by INT NULL,
        approval_notes VARCHAR(255) NULL,
        disciplinary_remark VARCHAR(255) NULL,
        created_student_user_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_student_app_user_id (user_id),
        UNIQUE KEY uq_student_app_email (email),
        KEY idx_student_app_status (status),
        KEY idx_student_app_submitted (submitted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    return (bool)$ok;
}

function reviewTableHasColumn(mysqli $conn, $table, $column)
{
    $safeTable = str_replace('`', '``', (string)$table);
    $safeColumn = $conn->real_escape_string((string)$column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function reviewBindDynamicParams(mysqli_stmt $stmt, $types, &$values)
{
    if (!is_array($values) || $types === '') {
        return true;
    }
    $bind = [$types];
    foreach (array_keys($values) as $idx) {
        $bind[] = &$values[$idx];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

$applicationsStageTable = ensureApplicationsStagingTable($conn) ? '`student_applications`' : '';

$flashType = '';
$flashMessage = '';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = (string)$_SESSION['flash_message'];
    $flashType = isset($_SESSION['flash_type']) ? (string)$_SESSION['flash_type'] : 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
    $notes = trim((string)($_POST['approval_notes'] ?? ''));
    $disciplinaryRemark = trim((string)($_POST['disciplinary_remark'] ?? ''));
    $internalHoursRaw = isset($_POST['internal_total_hours']) ? trim((string)$_POST['internal_total_hours']) : '140';
    $externalHoursRaw = isset($_POST['external_total_hours']) ? trim((string)$_POST['external_total_hours']) : '250';
    $departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $coordinatorId = isset($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : 0;
    $supervisorId = isset($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : 0;

    $internalHours = is_numeric($internalHoursRaw) ? (int)$internalHoursRaw : -1;
    $externalHours = is_numeric($externalHoursRaw) ? (int)$externalHoursRaw : -1;
    $coordinatorName = $coordinatorId > 0 && isset($coordinatorNameMap[$coordinatorId]) ? $coordinatorNameMap[$coordinatorId] : null;
    $supervisorName = $supervisorId > 0 && isset($supervisorNameMap[$supervisorId]) ? $supervisorNameMap[$supervisorId] : null;
    $stagedApplication = null;
    if ($applicationsStageTable !== '' && $userId > 0) {
        $stagedStmt = $conn->prepare("SELECT * FROM {$applicationsStageTable} WHERE user_id = ? LIMIT 1");
        if ($stagedStmt) {
            $stagedStmt->bind_param('i', $userId);
            $stagedStmt->execute();
            $stagedApplication = $stagedStmt->get_result()->fetch_assoc();
            $stagedStmt->close();
        }
    }
    $stagedDateOfBirth = $stagedApplication ? trim((string)($stagedApplication['date_of_birth'] ?? '')) : '';
    $stagedGender = $stagedApplication ? trim((string)($stagedApplication['gender'] ?? '')) : '';

    if ($userId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        $flashType = 'danger';
        $flashMessage = 'Invalid request.';
    } elseif ($internalHours < 0 || $externalHours < 0) {
        $flashType = 'danger';
        $flashMessage = 'Hours must be valid non-negative numbers.';
    } else {
        if ($decision === 'approve') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE users SET application_status = 'approved', is_active = 1, approved_by = ?, approved_at = NOW(), rejected_at = NULL, approval_notes = ?, disciplinary_remark = ? WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception('Unable to update application status.');
                }
                $stmt->bind_param('issi', $currentUserId, $notes, $disciplinaryRemark, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception('Unable to approve application.');
                }
                $stmt->close();

                $studentStmt = $conn->prepare("UPDATE students SET department_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), coordinator_name = ?, supervisor_id = NULLIF(?, 0), supervisor_name = ?, internal_total_hours = ?, external_total_hours = ?, internal_total_hours_remaining = CASE WHEN assignment_track = 'external' THEN 0 ELSE ? END, external_total_hours_remaining = CASE WHEN assignment_track = 'external' THEN ? ELSE 0 END, date_of_birth = COALESCE(NULLIF(?, ''), date_of_birth), gender = COALESCE(NULLIF(?, ''), gender) WHERE user_id = ? LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception('Unable to update student hour settings.');
                }
                $studentStmt->bind_param('iisisiiiissi', $departmentId, $coordinatorId, $coordinatorName, $supervisorId, $supervisorName, $internalHours, $externalHours, $internalHours, $externalHours, $stagedDateOfBirth, $stagedGender, $userId);
                if (!$studentStmt->execute()) {
                    $studentStmt->close();
                    throw new Exception('Unable to save updated assignment and hour settings.');
                }
                $studentUpdatedRows = (int)$studentStmt->affected_rows;
                $studentStmt->close();

                if ($studentUpdatedRows === 0 && $stagedApplication) {
                    $assignmentTrack = strtolower((string)($stagedApplication['assignment_track'] ?? 'internal'));
                    if (!in_array($assignmentTrack, ['internal', 'external'], true)) {
                        $assignmentTrack = 'internal';
                    }

                    $studentColumns = [
                        'user_id', 'course_id', 'student_id', 'first_name', 'last_name', 'middle_name', 'username',
                        'password', 'email', 'department_id', 'section_id', 'address', 'phone', 'date_of_birth', 'gender',
                        'supervisor_id', 'supervisor_name', 'coordinator_id', 'coordinator_name',
                        'internal_total_hours', 'internal_total_hours_remaining',
                        'external_total_hours', 'external_total_hours_remaining',
                        'assignment_track', 'emergency_contact'
                    ];
                    $studentTypes = str_repeat('s', 25);
                    $studentValues = [
                        $userId,
                        (int)($stagedApplication['course_id'] ?? 0),
                        (string)($stagedApplication['student_id'] ?? ''),
                        (string)($stagedApplication['first_name'] ?? ''),
                        (string)($stagedApplication['last_name'] ?? ''),
                        (string)($stagedApplication['middle_name'] ?? ''),
                        (string)($stagedApplication['username'] ?? ''),
                        (string)($stagedApplication['password_hash'] ?? ''),
                        (string)($stagedApplication['email'] ?? ''),
                        $departmentId,
                        (int)($stagedApplication['section_id'] ?? 0),
                        (string)($stagedApplication['address'] ?? ''),
                        (string)($stagedApplication['phone'] ?? ''),
                        (string)($stagedApplication['date_of_birth'] ?? ''),
                        (string)($stagedApplication['gender'] ?? ''),
                        $supervisorId,
                        (string)($supervisorName ?? ''),
                        $coordinatorId,
                        (string)($coordinatorName ?? ''),
                        $internalHours,
                        ($assignmentTrack === 'external' ? 0 : $internalHours),
                        $externalHours,
                        ($assignmentTrack === 'external' ? $externalHours : 0),
                        $assignmentTrack,
                        (string)($stagedApplication['emergency_contact'] ?? '')
                    ];

                    if (reviewTableHasColumn($conn, 'students', 'bio')) {
                        $studentColumns[] = 'bio';
                        $studentTypes .= 's';
                        $studentValues[] = '';
                    }

                    if (reviewTableHasColumn($conn, 'students', 'emergency_contact_phone')) {
                        $studentColumns[] = 'emergency_contact_phone';
                        $studentTypes .= 's';
                        $studentValues[] = (string)($stagedApplication['emergency_contact_phone'] ?? '');
                    }
                    if (reviewTableHasColumn($conn, 'students', 'school_year')) {
                        $studentColumns[] = 'school_year';
                        $studentTypes .= 's';
                        $studentValues[] = (string)($stagedApplication['school_year'] ?? '');
                    }
                    if (reviewTableHasColumn($conn, 'students', 'semester')) {
                        $studentColumns[] = 'semester';
                        $studentTypes .= 's';
                        $studentValues[] = (string)($stagedApplication['semester'] ?? '');
                    }
                    if (reviewTableHasColumn($conn, 'students', 'application_status')) {
                        $studentColumns[] = 'application_status';
                        $studentTypes .= 's';
                        $studentValues[] = 'approved';
                    }
                    if (reviewTableHasColumn($conn, 'students', 'status')) {
                        $studentColumns[] = 'status';
                        $studentTypes .= 's';
                        $studentValues[] = '1';
                    }

                    $studentPlaceholders = array_fill(0, count($studentColumns), '?');
                    if (reviewTableHasColumn($conn, 'students', 'created_at')) {
                        $studentColumns[] = 'created_at';
                        $studentPlaceholders[] = 'NOW()';
                    }

                    $insertStudentSql = 'INSERT INTO students (' . implode(', ', $studentColumns) . ') VALUES (' . implode(', ', $studentPlaceholders) . ')';
                    $insertStudentStmt = $conn->prepare($insertStudentSql);
                    if (!$insertStudentStmt) {
                        throw new Exception('Unable to create approved student record.');
                    }
                    reviewBindDynamicParams($insertStudentStmt, $studentTypes, $studentValues);
                    if (!$insertStudentStmt->execute()) {
                        $insertStudentStmt->close();
                        throw new Exception('Unable to create approved student record.');
                    }
                    $insertStudentStmt->close();
                }

                // Create internship record on approval (if not already created).
                $studentRow = null;
                $studentLookup = $conn->prepare("SELECT id, course_id, department_id, coordinator_id, supervisor_id, assignment_track, internal_total_hours, external_total_hours FROM students WHERE user_id = ? LIMIT 1");
                if ($studentLookup) {
                    $studentLookup->bind_param('i', $userId);
                    $studentLookup->execute();
                    $studentRow = $studentLookup->get_result()->fetch_assoc();
                    $studentLookup->close();
                }

                if ($applicationsStageTable !== '') {
                    $stageApproveStmt = $conn->prepare("UPDATE {$applicationsStageTable} SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?, approval_notes = ?, disciplinary_remark = ?, created_student_user_id = ? WHERE user_id = ?");
                    if ($stageApproveStmt) {
                        $stageApproveStmt->bind_param('issii', $currentUserId, $notes, $disciplinaryRemark, $userId, $userId);
                        $stageApproveStmt->execute();
                        $stageApproveStmt->close();
                    }
                }

                if ($studentRow && !empty($studentRow['id'])) {
                    $studentId = (int)$studentRow['id'];
                    $courseId = (int)($studentRow['course_id'] ?? 0);
                    $departmentId = (int)($studentRow['department_id'] ?? 0);
                    $coordinatorId = (int)($studentRow['coordinator_id'] ?? 0);
                    $supervisorId = (int)($studentRow['supervisor_id'] ?? 0);
                    $assignmentTrack = strtolower((string)($studentRow['assignment_track'] ?? 'internal'));
                    $internalHours = (int)($studentRow['internal_total_hours'] ?? 0);
                    $externalHours = (int)($studentRow['external_total_hours'] ?? 0);

                    if ($studentId > 0 && $courseId > 0 && $departmentId > 0 && $coordinatorId > 0 && $supervisorId > 0) {
                        $existsIntern = $conn->prepare("SELECT id FROM internships WHERE student_id = ? LIMIT 1");
                        $hasIntern = false;
                        if ($existsIntern) {
                            $existsIntern->bind_param('i', $studentId);
                            $existsIntern->execute();
                            $resIntern = $existsIntern->get_result();
                            $hasIntern = ($resIntern && $resIntern->num_rows > 0);
                            $existsIntern->close();
                        }

                        if (!$hasIntern) {
                            $internCoordinatorUserId = 0;
                            $internSupervisorUserId = 0;

                            $mapCoord = $conn->prepare("SELECT user_id FROM coordinators WHERE id = ? LIMIT 1");
                            if ($mapCoord) {
                                $mapCoord->bind_param('i', $coordinatorId);
                                $mapCoord->execute();
                                $coordRow = $mapCoord->get_result()->fetch_assoc();
                                $mapCoord->close();
                                if ($coordRow && !empty($coordRow['user_id'])) {
                                    $internCoordinatorUserId = (int)$coordRow['user_id'];
                                }
                            }

                            $mapSup = $conn->prepare("SELECT user_id FROM supervisors WHERE id = ? LIMIT 1");
                            if ($mapSup) {
                                $mapSup->bind_param('i', $supervisorId);
                                $mapSup->execute();
                                $supRow = $mapSup->get_result()->fetch_assoc();
                                $mapSup->close();
                                if ($supRow && !empty($supRow['user_id'])) {
                                    $internSupervisorUserId = (int)$supRow['user_id'];
                                }
                            }

                            if ($internCoordinatorUserId > 0 && $internSupervisorUserId > 0) {
                                $today = date('Y-m-d');
                                $year = (int)date('Y');
                                $schoolYear = $year . '-' . ($year + 1);
                                $type = $assignmentTrack === 'external' ? 'external' : 'internal';
                                $requiredHours = $type === 'external' ? max(0, $externalHours) : max(0, $internalHours);
                                $renderedHours = 0;
                                $completionPct = 0;

                                $insertIntern = $conn->prepare("
                                    INSERT INTO internships
                                    (student_id, course_id, department_id, coordinator_id, supervisor_id, type, start_date, status, school_year, required_hours, rendered_hours, completion_percentage, created_at, updated_at)
                                    VALUES
                                    (?, ?, ?, ?, ?, ?, ?, 'ongoing', ?, ?, ?, ?, NOW(), NOW())
                                ");
                                if ($insertIntern) {
                                    $insertIntern->bind_param(
                                        'iiiiisssiid',
                                        $studentId,
                                        $courseId,
                                        $departmentId,
                                        $internCoordinatorUserId,
                                        $internSupervisorUserId,
                                        $type,
                                        $today,
                                        $schoolYear,
                                        $requiredHours,
                                        $renderedHours,
                                        $completionPct
                                    );
                                    $insertIntern->execute();
                                    $insertIntern->close();
                                }
                            }
                        }
                    }
                }

                $conn->commit();
                $flashType = 'success';
                $flashMessage = 'Application approved and student hours updated.';
            } catch (Throwable $e) {
                $conn->rollback();
                $flashType = 'danger';
                $flashMessage = 'Unable to process approval: ' . $e->getMessage();
            }
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE users SET application_status = 'rejected', is_active = 0, approved_by = ?, approved_at = NULL, rejected_at = NOW(), approval_notes = ?, disciplinary_remark = ? WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception('Unable to reject application.');
                }
                $stmt->bind_param('issi', $currentUserId, $notes, $disciplinaryRemark, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception('Unable to reject application.');
                }

                $stmt->close();

                $studentStmt = $conn->prepare("UPDATE students SET department_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), coordinator_name = ?, supervisor_id = NULLIF(?, 0), supervisor_name = ? WHERE user_id = ? LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception('Unable to update student assignments.');
                }
                $studentStmt->bind_param('iisisi', $departmentId, $coordinatorId, $coordinatorName, $supervisorId, $supervisorName, $userId);
                if (!$studentStmt->execute()) {
                    $studentStmt->close();
                    throw new Exception('Unable to save updated assignments.');
                }

                $studentStmt->close();

                if ($applicationsStageTable !== '') {
                    $stageRejectStmt = $conn->prepare("UPDATE {$applicationsStageTable} SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, approval_notes = ?, disciplinary_remark = ?, created_student_user_id = NULL WHERE user_id = ?");
                    if ($stageRejectStmt) {
                        $stageRejectStmt->bind_param('issi', $currentUserId, $notes, $disciplinaryRemark, $userId);
                        $stageRejectStmt->execute();
                        $stageRejectStmt->close();
                    }
                }

                $conn->commit();
                $flashType = 'warning';
                $flashMessage = 'Application rejected and assignments updated.';
            } catch (Throwable $e) {
                $conn->rollback();
                $flashType = 'danger';
                $flashMessage = 'Unable to process rejection: ' . $e->getMessage();
            }
        }

        if ($flashMessage === '') {
            $flashType = 'danger';
            $flashMessage = 'Unable to process this application.';
        }
    }

    if ($flashMessage !== '') {
        $_SESSION['flash_type'] = $flashType;
        $_SESSION['flash_message'] = $flashMessage;
        $redirect = 'applications-review.php';
        $qs = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
        if ($qs !== '') {
            $redirect .= '?' . $qs;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

$courseFilter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$sectionFilter = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$coordinatorFilter = isset($_GET['coordinator_id']) ? (int)$_GET['coordinator_id'] : 0;
$supervisorFilter = isset($_GET['supervisor_id']) ? (int)$_GET['supervisor_id'] : 0;

$stagedJoinSql = $applicationsStageTable !== ''
    ? " LEFT JOIN {$applicationsStageTable} sa ON sa.user_id = u.id "
    : " LEFT JOIN (SELECT NULL AS user_id) sa ON 1 = 0 ";
$effectiveStatusSql = "COALESCE(sa.status, u.application_status)";

$sql = "
    SELECT
        u.id AS user_id,
        u.username,
        u.email,
        u.role,
        {$effectiveStatusSql} AS application_status,
        COALESCE(sa.submitted_at, u.application_submitted_at) AS application_submitted_at,
        u.approved_at,
        u.rejected_at,
        COALESCE(sa.approval_notes, u.approval_notes) AS approval_notes,
        COALESCE(sa.disciplinary_remark, u.disciplinary_remark) AS disciplinary_remark,
        COALESCE(s.student_id, sa.student_id) AS student_id,
        COALESCE(s.first_name, sa.first_name) AS first_name,
        COALESCE(s.middle_name, sa.middle_name) AS middle_name,
        COALESCE(s.last_name, sa.last_name) AS last_name,
        COALESCE(s.address, sa.address) AS address,
        COALESCE(s.phone, sa.phone) AS phone,
        COALESCE(s.date_of_birth, sa.date_of_birth) AS date_of_birth,
        COALESCE(s.gender, sa.gender) AS gender,
        COALESCE(s.emergency_contact, sa.emergency_contact) AS emergency_contact,
        COALESCE(s.emergency_contact_phone, sa.emergency_contact_phone) AS emergency_contact_phone,
        COALESCE(s.department_id, sa.department_id) AS department_id,
        COALESCE(s.coordinator_id, sa.coordinator_id) AS coordinator_id,
        COALESCE(s.supervisor_id, sa.supervisor_id) AS supervisor_id,
        COALESCE(s.coordinator_name, sa.coordinator_name) AS coordinator_name,
        COALESCE(s.supervisor_name, sa.supervisor_name) AS supervisor_name,
        COALESCE(s.internal_total_hours, sa.internal_total_hours) AS internal_total_hours,
        COALESCE(s.external_total_hours, sa.external_total_hours) AS external_total_hours,
        COALESCE(s.school_year, sa.school_year) AS school_year,
        COALESCE(s.semester, sa.semester) AS semester,
        c.name AS course_name,
        d.name AS department_name,
        COALESCE(sec.code, sa.section_code_snapshot) AS section_code,
        COALESCE(sec.name, sa.section_name_snapshot) AS section_name
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    {$stagedJoinSql}
    LEFT JOIN courses c ON c.id = COALESCE(s.course_id, sa.course_id)
    LEFT JOIN departments d ON d.id = COALESCE(s.department_id, sa.department_id)
    LEFT JOIN sections sec ON sec.id = COALESCE(s.section_id, sa.section_id)
    WHERE u.role = 'student'
";

if ($statusFilter !== 'all') {
    $sql .= " AND {$effectiveStatusSql} = '" . $conn->real_escape_string($statusFilter) . "'";
}

if ($courseFilter > 0) {
    $sql .= " AND COALESCE(s.course_id, sa.course_id) = " . (int)$courseFilter;
}
if ($sectionFilter > 0) {
    $sql .= " AND COALESCE(s.section_id, sa.section_id) = " . (int)$sectionFilter;
}
if ($coordinatorFilter > 0) {
    $sql .= " AND COALESCE(s.coordinator_id, sa.coordinator_id) = " . (int)$coordinatorFilter;
}
if ($supervisorFilter > 0) {
    $sql .= " AND COALESCE(s.supervisor_id, sa.supervisor_id) = " . (int)$supervisorFilter;
}

$sql .= " ORDER BY COALESCE(sa.submitted_at, u.application_submitted_at, u.created_at) DESC, u.id DESC";
$applications = $conn->query($sql);

$page_title = 'BioTern || Student Applications';
$page_styles = array('assets/css/modules/management/management-applications-review.css');
$page_scripts = array('assets/js/modules/management/applications-review-runtime.js');
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="apps-review-shell">
        <header class="page-header app-applications-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Applications Review</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                    <li class="breadcrumb-item">Applications Review</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto app-applications-header-actions">
                <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="applicationsReviewActionsMenu">
                    <i class="feather-grid me-1"></i>
                    <span>Actions</span>
                </button>
                <div class="page-header-actions app-applications-actions-panel" id="applicationsReviewActionsMenu">
                    <div class="dashboard-actions-panel">
                        <div class="dashboard-actions-meta">
                            <span class="text-muted fs-12">Quick Actions</span>
                        </div>
                        <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <button type="submit" form="applicationsReviewFilters" class="btn btn-sm btn-primary">Apply</button>
                            <a href="applications-review.php" class="btn btn-sm btn-light-brand">Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="main-content">
            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> alert-auto-dismiss" role="alert">
                    <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="card apps-review-card">
                <div class="card-body">
                    <form method="get" id="applicationsReviewFilters" class="apps-review-toolbar filter-form">
                        <div>
                            <label class="form-label" for="app-review-filter-status">Status</label>
                            <select id="app-review-filter-status" class="form-control" name="status" data-ui-select="custom">
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="app-review-filter-course">Course</label>
                            <select id="app-review-filter-course" class="form-control" name="course_id" data-ui-select="custom">
                                <option value="0">All Courses</option>
                                <?php foreach ($courseOptions as $course): ?>
                                    <?php
                                        $courseId = (int)($course['id'] ?? 0);
                                        $courseCode = trim((string)($course['code'] ?? ''));
                                        $courseName = trim((string)($course['name'] ?? ''));
                                        $courseLabel = $courseCode !== '' ? ($courseCode . ($courseName !== '' ? (' - ' . $courseName) : '')) : $courseName;
                                    ?>
                                    <option value="<?php echo $courseId; ?>" <?php echo $courseFilter === $courseId ? 'selected' : ''; ?>><?php echo htmlspecialchars($courseLabel !== '' ? $courseLabel : ('Course #' . $courseId), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="app-review-filter-section">Section</label>
                            <select id="app-review-filter-section" class="form-control" name="section_id" data-ui-select="custom">
                                <option value="0">All Sections</option>
                                <?php foreach ($sectionOptions as $sec): ?>
                                    <?php
                                        $secId = (int)($sec['id'] ?? 0);
                                        $secCode = trim((string)($sec['code'] ?? ''));
                                        $secName = trim((string)($sec['name'] ?? ''));
                                        $secLabel = $secCode !== '' && $secName !== '' ? ($secCode . ' - ' . $secName) : ($secCode !== '' ? $secCode : $secName);
                                    ?>
                                    <option value="<?php echo $secId; ?>" <?php echo $sectionFilter === $secId ? 'selected' : ''; ?>><?php echo htmlspecialchars($secLabel !== '' ? $secLabel : ('Section #' . $secId), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="app-review-filter-coordinator">Coordinator</label>
                            <select id="app-review-filter-coordinator" class="form-control" name="coordinator_id" data-ui-select="custom">
                                <option value="0">All Coordinators</option>
                                <?php foreach ($coordinatorOptions as $coor): ?>
                                    <?php $coorId = (int)($coor['id'] ?? 0); ?>
                                    <option value="<?php echo $coorId; ?>" <?php echo $coordinatorFilter === $coorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($coor['full_name'] ?? ('Coordinator #' . $coorId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="app-review-filter-supervisor">Supervisor</label>
                            <select id="app-review-filter-supervisor" class="form-control" name="supervisor_id" data-ui-select="custom">
                                <option value="0">All Supervisors</option>
                                <?php foreach ($supervisorOptions as $sup): ?>
                                    <?php $supId = (int)($sup['id'] ?? 0); ?>
                                    <option value="<?php echo $supId; ?>" <?php echo $supervisorFilter === $supId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($sup['full_name'] ?? ('Supervisor #' . $supId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle apps-review-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Hours (Int/Ext)</th>
                                    <th>Term</th>
                                    <th>Submitted</th>
                                    <th class="apps-review-col-review">Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($applications && $applications->num_rows > 0): ?>
                                    <?php $rowIndex = 0; ?>
                                    <?php while ($row = $applications->fetch_assoc()): ?>
                                        <?php
                                            $rowIndex++;
                                            $status = strtolower((string)($row['application_status'] ?? 'approved'));
                                            $badge = 'secondary';
                                            if ($status === 'pending') $badge = 'warning';
                                            if ($status === 'approved') $badge = 'success';
                                            if ($status === 'rejected') $badge = 'danger';

                                            $courseName = trim((string)($row['course_name'] ?? ''));
                                            $middleName = trim((string)($row['middle_name'] ?? ''));
                                            $departmentName = trim((string)($row['department_name'] ?? ''));
                                            $sectionCode = trim((string)($row['section_code'] ?? ''));
                                            $sectionName = trim((string)($row['section_name'] ?? ''));
                                            $semesterValue = trim((string)($row['semester'] ?? ''));
                                            $schoolYearValue = trim((string)($row['school_year'] ?? ''));
                                            $coordinatorName = trim((string)($row['coordinator_name'] ?? ''));
                                            $supervisorName = trim((string)($row['supervisor_name'] ?? ''));
                                            $addressValue = trim((string)($row['address'] ?? ''));
                                            $phoneValue = trim((string)($row['phone'] ?? ''));
                                            $dateOfBirthValue = trim((string)($row['date_of_birth'] ?? ''));
                                            $genderValue = trim((string)($row['gender'] ?? ''));
                                            $emergencyContactValue = trim((string)($row['emergency_contact'] ?? ''));
                                            $emergencyContactPhoneValue = trim((string)($row['emergency_contact_phone'] ?? ''));

                                            $emergencyContactNameOnly = $emergencyContactValue;
                                            $emergencyPhoneFromContact = '';
                                            if ($emergencyContactValue !== '' && preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $emergencyContactValue, $contactParts)) {
                                                $emergencyContactNameOnly = trim((string)($contactParts[1] ?? ''));
                                                $emergencyPhoneFromContact = trim((string)($contactParts[2] ?? ''));
                                            }
                                            if ($emergencyContactPhoneValue === '' && $emergencyPhoneFromContact !== '') {
                                                $emergencyContactPhoneValue = $emergencyPhoneFromContact;
                                            }
                                            $selectedDepartmentId = (int)($row['department_id'] ?? 0);
                                            $selectedCoordinatorId = (int)($row['coordinator_id'] ?? 0);
                                            $selectedSupervisorId = (int)($row['supervisor_id'] ?? 0);

                                            $courseLabel = $courseName !== '' ? $courseName : 'To be assigned';
                                            $departmentLabel = $departmentName !== '' ? $departmentName : 'To be assigned';
                                            $sectionLabel = 'To be assigned';
                                            if ($sectionCode !== '' && $sectionName !== '') {
                                                $sectionLabel = $sectionCode . ' - ' . $sectionName;
                                            } elseif ($sectionCode !== '') {
                                                $sectionLabel = $sectionCode;
                                            } elseif ($sectionName !== '') {
                                                $sectionLabel = $sectionName;
                                            }
                                            $semesterLabel = $semesterValue !== '' ? $semesterValue : 'Not set';
                                            $schoolYearLabel = $schoolYearValue !== '' ? $schoolYearValue : 'Not set';
                                            $coordinatorLabel = $coordinatorName !== '' ? $coordinatorName : 'To be assigned';
                                            $supervisorLabel = $supervisorName !== '' ? $supervisorName : 'To be assigned';
                                            $addressLabel = $addressValue !== '' ? $addressValue : '-';
                                            $phoneLabel = $phoneValue !== '' ? $phoneValue : '-';
                                            $dateOfBirthLabel = $dateOfBirthValue !== '' ? $dateOfBirthValue : '-';
                                            $genderLabel = $genderValue !== '' ? ucfirst(strtolower($genderValue)) : '-';
                                            $emergencyContactLabel = $emergencyContactNameOnly !== '' ? $emergencyContactNameOnly : '-';
                                            $emergencyContactPhoneLabel = $emergencyContactPhoneValue !== '' ? $emergencyContactPhoneValue : '-';

                                            $submittedAt = formatDisplayDateTime($row['application_submitted_at'] ?? '');
                                            $approvedAt = formatDisplayDateTime($row['approved_at'] ?? '');
                                            $rejectedAt = formatDisplayDateTime($row['rejected_at'] ?? '');
                                            $firstInitial = strtoupper(substr(trim((string)($row['first_name'] ?? '')), 0, 1));
                                            $lastInitial = strtoupper(substr(trim((string)($row['last_name'] ?? '')), 0, 1));
                                            $initials = trim($firstInitial . $lastInitial);
                                            if ($initials === '') {
                                                $initials = 'ST';
                                            }

                                            $collapseId = 'applicationDetail_' . (int)$row['user_id'] . '_' . $rowIndex;
                                        ?>
                                        <tr class="summary-row">
                                            <td data-label="Student">
                                                <div class="student-block">
                                                    <span class="student-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars(trim((string)($row['first_name'] . ' ' . $row['last_name'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <small class="text-muted d-block">ID: <?php echo htmlspecialchars((string)($row['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Course">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($courseLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted d-block">Section: <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                                <small class="text-muted d-block">Semester: <?php echo htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8'); ?> | SY: <?php echo htmlspecialchars($schoolYearLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td data-label="Status">
                                                <span class="badge bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td data-label="Hours (Int/Ext)">
                                                <span class="hours-pill"><?php echo (int)($row['internal_total_hours'] ?? 140); ?> / <?php echo (int)($row['external_total_hours'] ?? 250); ?></span>
                                            </td>
                                            <td data-label="Term"><?php echo htmlspecialchars($schoolYearLabel . ' / ' . $semesterLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-label="Submitted"><?php echo htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-label="Review" class="text-center">
                                                <button class="btn btn-outline-primary btn-sm expand-btn application-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>" data-expand-text="Details" data-collapse-text="Hide">Details</button>
                                            </td>
                                        </tr>
                                        <tr class="application-detail-row">
                                            <td colspan="7">
                                                <div id="<?php echo $collapseId; ?>" class="collapse application-detail-box">
                                                    <div class="detail-grid">
                                                        <div class="detail-meta">
                                                            <div class="line"><strong>Full Name:</strong> <?php echo htmlspecialchars(trim((string)($row['first_name'] . ' ' . $middleName . ' ' . $row['last_name'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Username:</strong> <?php echo htmlspecialchars((string)($row['username'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Email:</strong> <?php echo htmlspecialchars((string)($row['email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Student ID:</strong> <?php echo htmlspecialchars((string)($row['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Address:</strong> <?php echo htmlspecialchars($addressLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Phone:</strong> <?php echo htmlspecialchars($phoneLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Date of Birth:</strong> <?php echo htmlspecialchars($dateOfBirthLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Gender:</strong> <?php echo htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Parent/Emergency Contact:</strong> <?php echo htmlspecialchars($emergencyContactLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Parent/Emergency Phone:</strong> <?php echo htmlspecialchars($emergencyContactPhoneLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Department:</strong> <?php echo htmlspecialchars($departmentLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Section:</strong> <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Semester:</strong> <?php echo htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>School Year:</strong> <?php echo htmlspecialchars($schoolYearLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Coordinator:</strong> <?php echo htmlspecialchars($coordinatorLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Supervisor:</strong> <?php echo htmlspecialchars($supervisorLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Submitted:</strong> <?php echo htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Approval Note:</strong> <?php echo htmlspecialchars((string)($row['approval_notes'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Disciplinary:</strong> <?php echo htmlspecialchars(trim((string)($row['disciplinary_remark'] ?? '')) !== '' ? (string)$row['disciplinary_remark'] : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php if ($status === 'approved' && $approvedAt !== '-'): ?>
                                                                <div class="line"><strong>Approved:</strong> <?php echo htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php elseif ($status === 'rejected' && $rejectedAt !== '-'): ?>
                                                                <div class="line"><strong>Rejected:</strong> <?php echo htmlspecialchars($rejectedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <form method="post" class="action-form">
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>">
                                                            <div class="field-wrap wide-field">
                                                                <label class="field-label">Department</label>
                                                                <select class="form-control form-control-sm" name="department_id" title="Department">
                                                                    <option value="0">Unassigned</option>
                                                                    <?php foreach ($departmentOptions as $dep): ?>
                                                                        <?php
                                                                            $depId = (int)($dep['id'] ?? 0);
                                                                            $depLabel = trim((string)($dep['name'] ?? ''));
                                                                            $depCode = trim((string)($dep['code'] ?? ''));
                                                                            if ($depCode !== '') {
                                                                                $depLabel = $depCode . ($depLabel !== '' ? (' - ' . $depLabel) : '');
                                                                            }
                                                                        ?>
                                                                        <option value="<?php echo $depId; ?>" <?php echo $depId === $selectedDepartmentId ? 'selected' : ''; ?>><?php echo htmlspecialchars($depLabel !== '' ? $depLabel : ('Department #' . $depId), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <select class="form-control form-control-sm" name="coordinator_id" title="Coordinator">
                                                                <option value="0">Coordinator: Unassigned</option>
                                                                <?php foreach ($coordinatorOptions as $coor): ?>
                                                                    <?php $coorId = (int)($coor['id'] ?? 0); ?>
                                                                    <option value="<?php echo $coorId; ?>" <?php echo $coorId === $selectedCoordinatorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($coor['full_name'] ?? ('Coordinator #' . $coorId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <select class="form-control form-control-sm" name="supervisor_id" title="Supervisor">
                                                                <option value="0">Supervisor: Unassigned</option>
                                                                <?php foreach ($supervisorOptions as $sup): ?>
                                                                    <?php $supId = (int)($sup['id'] ?? 0); ?>
                                                                    <option value="<?php echo $supId; ?>" <?php echo $supId === $selectedSupervisorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($sup['full_name'] ?? ('Supervisor #' . $supId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <div class="field-wrap">
                                                                <label class="field-label">Internal OJT Hours</label>
                                                                <input type="number" class="form-control form-control-sm" name="internal_total_hours" min="0" required value="<?php echo (int)($row['internal_total_hours'] ?? 140); ?>" title="Internal OJT Hours">
                                                            </div>
                                                            <div class="field-wrap">
                                                                <label class="field-label">External OJT Hours</label>
                                                                <input type="number" class="form-control form-control-sm" name="external_total_hours" min="0" required value="<?php echo (int)($row['external_total_hours'] ?? 250); ?>" title="External OJT Hours">
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm approval-note" name="approval_notes" placeholder="Add note (optional)">
                                                            <input type="text" class="form-control form-control-sm disciplinary-note" name="disciplinary_remark" placeholder="Disciplinary remark (if misconduct)">
                                                            <div class="action-buttons">
                                                                <button type="submit" name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                                                                <button type="submit" name="decision" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No applications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>





