<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/mailer.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

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
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_start_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_track");
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

function formatGenderLabel($rawValue): string
{
    $value = strtolower(trim((string)$rawValue));
    if ($value === '') {
        return '-';
    }

    if (in_array($value, ['m', 'male'], true)) {
        return 'Male';
    }
    if (in_array($value, ['f', 'female'], true)) {
        return 'Female';
    }
    if (in_array($value, ['n', 'nb', 'nonbinary', 'non-binary'], true)) {
        return 'Non-binary';
    }
    if (in_array($value, ['prefer not to say', 'prefer_not_to_say', 'na', 'n/a'], true)) {
        return 'Prefer not to say';
    }

    return ucwords(str_replace(['_', '-'], ' ', $value));
}

function review_application_recipient(mysqli $conn, ?array $stagedApplication, int $userId): array
{
    $email = $stagedApplication ? trim((string)($stagedApplication['email'] ?? '')) : '';
    $name = '';
    if ($stagedApplication) {
        $name = trim((string)($stagedApplication['first_name'] ?? '') . ' ' . (string)($stagedApplication['last_name'] ?? ''));
    }

    if ($email === '' && $userId > 0) {
        $stmt = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $email = trim((string)($row['email'] ?? ''));
                if ($name === '') {
                    $name = trim((string)($row['name'] ?? ''));
                }
            }
        }
    }

    return [$email, $name !== '' ? $name : 'Student'];
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

    if ($ok) {
        return true;
    }

    // Some production DB users may not have CREATE privilege; treat existing table as usable.
    $existsRes = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_applications' LIMIT 1");
    return (bool)($existsRes && $existsRes->num_rows > 0);
}

function reviewTableHasColumn(mysqli $conn, $table, $column)
{
    $safeTable = str_replace('`', '``', (string)$table);
    $safeColumn = $conn->real_escape_string((string)$column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function reviewStudentSettingValue(mysqli $conn, string $key, string $fallback): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if ($tableCheck instanceof mysqli_result && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT `key`, `value` FROM system_settings WHERE category = 'students'");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $cache[(string)($row['key'] ?? '')] = trim((string)($row['value'] ?? ''));
                }
                $stmt->close();
            }
        }
    }

    $value = trim((string)($cache[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function reviewHoursValue($value, int $fallback): int
{
    if ($value === null || $value === '' || !is_numeric($value) || (int)$value <= 0) {
        return $fallback;
    }
    return (int)$value;
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

function reviewResolveProfileImageUrl(string $profilePath): ?string
{
    $clean = ltrim(str_replace('\\', '/', trim($profilePath)), '/');
    if ($clean === '') {
        return null;
    }
    $absolutePath = dirname(__DIR__) . '/' . $clean;
    if (!file_exists($absolutePath)) {
        return null;
    }
    $mtime = @filemtime($absolutePath);
    return $clean . ($mtime ? ('?v=' . $mtime) : '');
}

$applicationsStageTable = ensureApplicationsStagingTable($conn) ? '`student_applications`' : '';
$reviewDefaultInternalHours = max(0, (int)reviewStudentSettingValue($conn, 'default_internal_hours', '140'));
$reviewDefaultExternalHours = max(0, (int)reviewStudentSettingValue($conn, 'default_external_hours', '250'));

$flashType = '';
$flashMessage = '';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = (string)$_SESSION['flash_message'];
    $flashType = isset($_SESSION['flash_type']) ? (string)$_SESSION['flash_type'] : 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
    $notes = trim((string)($_POST['approval_notes'] ?? ''));
    $disciplinaryRemark = trim((string)($_POST['disciplinary_remark'] ?? ''));
    $internalHoursRaw = isset($_POST['internal_total_hours']) ? trim((string)$_POST['internal_total_hours']) : (string)$reviewDefaultInternalHours;
    $externalHoursRaw = isset($_POST['external_total_hours']) ? trim((string)$_POST['external_total_hours']) : (string)$reviewDefaultExternalHours;
    $departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $coordinatorId = isset($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : 0;
    $supervisorId = isset($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : 0;

    $internalHours = is_numeric($internalHoursRaw) ? (int)$internalHoursRaw : -1;
    $externalHours = is_numeric($externalHoursRaw) ? (int)$externalHoursRaw : -1;
    $coordinatorName = $coordinatorId > 0 && isset($coordinatorNameMap[$coordinatorId]) ? $coordinatorNameMap[$coordinatorId] : null;
    $supervisorName = $supervisorId > 0 && isset($supervisorNameMap[$supervisorId]) ? $supervisorNameMap[$supervisorId] : null;
    $stagedApplication = null;
    if ($applicationsStageTable !== '' && $applicationId > 0) {
        $stagedStmt = $conn->prepare("SELECT * FROM {$applicationsStageTable} WHERE id = ? LIMIT 1");
        if ($stagedStmt) {
            $stagedStmt->bind_param('i', $applicationId);
            $stagedStmt->execute();
            $stagedApplication = $stagedStmt->get_result()->fetch_assoc();
            $stagedStmt->close();
        }
    }
    if ($userId <= 0 && $stagedApplication && !empty($stagedApplication['user_id'])) {
        $userId = (int)$stagedApplication['user_id'];
    }
    $stagedStudentNo = $stagedApplication ? trim((string)($stagedApplication['student_id'] ?? '')) : '';
    $stagedDateOfBirth = $stagedApplication ? trim((string)($stagedApplication['date_of_birth'] ?? '')) : '';
    $stagedGender = $stagedApplication ? trim((string)($stagedApplication['gender'] ?? '')) : '';

    $isLegacyMode = ($applicationsStageTable === '');
    if (
        !in_array($decision, ['approve', 'reject'], true)
        || ($isLegacyMode ? ($userId <= 0) : ($applicationId <= 0 || !$stagedApplication))
    ) {
        $flashType = 'danger';
        $flashMessage = 'Invalid request.';
    } elseif ($internalHours < 0 || $externalHours < 0) {
        $flashType = 'danger';
        $flashMessage = 'Hours must be valid non-negative numbers.';
    } else {
        if ($internalHours <= 0) {
            $internalHours = $reviewDefaultInternalHours;
        }
        if ($externalHours <= 0) {
            $externalHours = $reviewDefaultExternalHours;
        }
        if ($decision === 'approve') {
            $conn->begin_transaction();
            try {
                if ($userId <= 0 && $stagedApplication) {
                    $stagedEmail = trim((string)($stagedApplication['email'] ?? ''));
                    $stagedUsername = trim((string)($stagedApplication['username'] ?? ''));
                    $stagedPasswordHash = trim((string)($stagedApplication['password_hash'] ?? ''));
                    $stagedFirstName = trim((string)($stagedApplication['first_name'] ?? ''));
                    $stagedLastName = trim((string)($stagedApplication['last_name'] ?? ''));
                    $stagedFullName = trim($stagedFirstName . ' ' . $stagedLastName);

                    if ($stagedEmail === '' || $stagedUsername === '' || $stagedPasswordHash === '') {
                        throw new Exception('Pending application is missing required account fields.');
                    }

                    $existingUserStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
                    if ($existingUserStmt) {
                        $existingUserStmt->bind_param('ss', $stagedEmail, $stagedUsername);
                        $existingUserStmt->execute();
                        $existingUserRow = $existingUserStmt->get_result()->fetch_assoc();
                        $existingUserStmt->close();
                        if ($existingUserRow) {
                            $userId = (int)($existingUserRow['id'] ?? 0);
                        }
                    }

                    if ($userId <= 0) {
                        $defaultProfilePicture = '';
                        $insertUserStmt = $conn->prepare("INSERT INTO users (name, username, email, password, role, is_active, profile_picture, created_at, updated_at) VALUES (?, ?, ?, ?, 'student', 1, ?, NOW(), NOW())");
                        if (!$insertUserStmt) {
                            throw new Exception('Unable to create user account for approved application. ' . (string)$conn->error);
                        }
                        $insertUserStmt->bind_param('sssss', $stagedFullName, $stagedUsername, $stagedEmail, $stagedPasswordHash, $defaultProfilePicture);
                        if (!$insertUserStmt->execute()) {
                            $insertUserError = (string)$insertUserStmt->error;
                            $insertUserStmt->close();
                            throw new Exception('Unable to create user account for approved application. ' . $insertUserError);
                        }
                        $userId = (int)$conn->insert_id;
                        $insertUserStmt->close();
                    }
                }

                if ($userId <= 0) {
                    throw new Exception('Unable to resolve user account for this application.');
                }

                if ($stagedStudentNo !== '') {
                    $linkInternalStmt = $conn->prepare("
                        UPDATE ojt_internal
                        SET user_id = NULLIF(?, 0), updated_at = NOW()
                        WHERE TRIM(COALESCE(student_no, '')) COLLATE utf8mb4_unicode_ci = TRIM(?) COLLATE utf8mb4_unicode_ci
                          AND (user_id IS NULL OR user_id = 0 OR user_id = ?)
                    ");
                    if ($linkInternalStmt) {
                        $linkInternalStmt->bind_param('isi', $userId, $stagedStudentNo, $userId);
                        $linkInternalStmt->execute();
                        $linkInternalStmt->close();
                    }
                }

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

                $studentStmt = $conn->prepare("UPDATE students SET department_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), coordinator_name = ?, supervisor_id = NULLIF(?, 0), supervisor_name = ?, internal_total_hours = ?, external_total_hours = ?, internal_total_hours_remaining = ?, external_total_hours_remaining = ?, external_start_allowed = CASE WHEN assignment_track = 'external' THEN 1 ELSE external_start_allowed END, date_of_birth = COALESCE(NULLIF(?, ''), date_of_birth), gender = COALESCE(NULLIF(?, ''), gender) WHERE user_id = ? LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception('Unable to update student hour settings.');
                }

                $studentExistsForUser = false;
                $studentExistsStmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                if ($studentExistsStmt) {
                    $studentExistsStmt->bind_param('i', $userId);
                    $studentExistsStmt->execute();
                    $studentExistsForUser = (bool)$studentExistsStmt->get_result()->fetch_assoc();
                    $studentExistsStmt->close();
                }

                $studentStmt->bind_param('iisisiiiissi', $departmentId, $coordinatorId, $coordinatorName, $supervisorId, $supervisorName, $internalHours, $externalHours, $internalHours, $externalHours, $stagedDateOfBirth, $stagedGender, $userId);
                if (!$studentStmt->execute()) {
                    $studentError = (string)$studentStmt->error;
                    $studentStmt->close();
                    throw new Exception('Unable to save updated assignment and hour settings. ' . $studentError);
                }
                $studentUpdatedRows = (int)$studentStmt->affected_rows;
                $studentStmt->close();

                if (!$studentExistsForUser && $stagedApplication) {
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
                        $internalHours,
                        $externalHours,
                        $externalHours,
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

                    // Legacy-approval compatibility: if a student row already exists by student_id/email,
                    // update that record instead of forcing a fresh insert.
                    $existingStudentId = 0;
                    $stagedStudentCode = trim((string)($stagedApplication['student_id'] ?? ''));
                    $stagedEmail = trim((string)($stagedApplication['email'] ?? ''));

                    if ($stagedStudentCode !== '' && reviewTableHasColumn($conn, 'students', 'student_id')) {
                        $existingByCodeStmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
                        if ($existingByCodeStmt) {
                            $existingByCodeStmt->bind_param('s', $stagedStudentCode);
                            $existingByCodeStmt->execute();
                            $existingByCodeRow = $existingByCodeStmt->get_result()->fetch_assoc();
                            $existingByCodeStmt->close();
                            if ($existingByCodeRow && !empty($existingByCodeRow['id'])) {
                                $existingStudentId = (int)$existingByCodeRow['id'];
                            }
                        }
                    }

                    if ($existingStudentId <= 0 && $stagedEmail !== '' && reviewTableHasColumn($conn, 'students', 'email')) {
                        $existingByEmailStmt = $conn->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
                        if ($existingByEmailStmt) {
                            $existingByEmailStmt->bind_param('s', $stagedEmail);
                            $existingByEmailStmt->execute();
                            $existingByEmailRow = $existingByEmailStmt->get_result()->fetch_assoc();
                            $existingByEmailStmt->close();
                            if ($existingByEmailRow && !empty($existingByEmailRow['id'])) {
                                $existingStudentId = (int)$existingByEmailRow['id'];
                            }
                        }
                    }

                    if ($existingStudentId > 0) {
                        $updateAssignments = [];
                        foreach ($studentColumns as $columnName) {
                            if ($columnName === 'created_at') {
                                continue;
                            }
                            $updateAssignments[] = $columnName . ' = ?';
                        }
                        if (reviewTableHasColumn($conn, 'students', 'updated_at')) {
                            $updateAssignments[] = 'updated_at = NOW()';
                        }

                        $updateStudentSql = 'UPDATE students SET ' . implode(', ', $updateAssignments) . ' WHERE id = ? LIMIT 1';
                        $updateStudentStmt = $conn->prepare($updateStudentSql);
                        if ($updateStudentStmt) {
                            $updateTypes = '';
                            $updateValues = [];
                            foreach ($studentColumns as $idx => $columnName) {
                                if ($columnName === 'created_at') {
                                    continue;
                                }
                                $updateTypes .= 's';
                                $updateValues[] = $studentValues[$idx] ?? '';
                            }
                            $updateTypes .= 'i';
                            $updateValues[] = $existingStudentId;

                            if (!reviewBindDynamicParams($updateStudentStmt, $updateTypes, $updateValues) || !$updateStudentStmt->execute()) {
                                $updateStudentErr = (string)$updateStudentStmt->error;
                                $updateStudentStmt->close();
                                throw new Exception('Unable to update existing student record for approval. ' . $updateStudentErr);
                            }
                            $updateStudentStmt->close();
                            $studentExistsForUser = true;
                        }
                    }

                    $upsertAssignments = [];
                    foreach ($studentColumns as $columnName) {
                        if ($columnName === 'created_at') {
                            continue;
                        }
                        $upsertAssignments[] = $columnName . ' = VALUES(' . $columnName . ')';
                    }
                    if (reviewTableHasColumn($conn, 'students', 'updated_at')) {
                        $upsertAssignments[] = 'updated_at = NOW()';
                    }

                    $insertStudentSql = 'INSERT INTO students (' . implode(', ', $studentColumns) . ') VALUES (' . implode(', ', $studentPlaceholders) . ')';
                    if (!empty($upsertAssignments)) {
                        $insertStudentSql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $upsertAssignments);
                    }
                    $insertStudentStmt = $conn->prepare($insertStudentSql);
                    if (!$insertStudentStmt) {
                        throw new Exception('Unable to create approved student record. ' . (string)$conn->error);
                    }
                    reviewBindDynamicParams($insertStudentStmt, $studentTypes, $studentValues);
                    if (!$insertStudentStmt->execute()) {
                        $insertStudentError = (string)$insertStudentStmt->error;
                        $insertStudentStmt->close();
                        throw new Exception('Unable to create approved student record. ' . $insertStudentError);
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
                    $stageApproveStmt = $conn->prepare("UPDATE {$applicationsStageTable} SET user_id = NULLIF(?, 0), status = 'approved', reviewed_at = NOW(), reviewed_by = ?, approval_notes = ?, disciplinary_remark = ?, created_student_user_id = ? WHERE id = ?");
                    if ($stageApproveStmt) {
                        $stageApproveStmt->bind_param('iissii', $userId, $currentUserId, $notes, $disciplinaryRemark, $userId, $applicationId);
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

                $settings = biotern_mail_settings($conn);
                if ((string)($settings['send_application_updates'] ?? '1') === '1') {
                    [$email, $displayName] = review_application_recipient($conn, $stagedApplication, $userId);
                    if ($email !== '') {
                        $subject = 'Your BioTern application was approved';
                        $appBaseUrl = biotern_mail_public_base($conn);
                        $studentManualUrl = $appBaseUrl !== '' ? $appBaseUrl . '/user-manual.php?role=student' : '';
                        $manualText = $studentManualUrl !== ''
                            ? "\n\nStudent User Manual:\n{$studentManualUrl}"
                            : "\n\nAfter logging in, open Help > User Manual to view the student guide.";
                        $textBody = "Hi {$displayName},\n\nYour BioTern student application has been approved. You can now log in using your Student ID Number and password.{$manualText}\n\nThank you.";
                        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
                        $safeManualUrl = htmlspecialchars($studentManualUrl, ENT_QUOTES, 'UTF-8');
                        $logoHtml = '';
                        if ($appBaseUrl !== '') {
                            $logoUrl = $appBaseUrl . '/assets/images/ccstlogo.png';
                            $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="School logo" width="40" height="40" style="display:block;border-radius:8px;">';
                        }
                        $htmlBody = '
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b1220;padding:24px 0;font-family:Segoe UI,Arial,sans-serif;">
                            <tr>
                                <td align="center">
                                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#111a2e;border:1px solid #1f2a44;border-radius:16px;overflow:hidden;">
                                        <tr>
                                            <td style="padding:20px 24px;background:linear-gradient(135deg,#162447,#111a2e);color:#ffffff;">
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td>
                                                            <div style="font-size:18px;font-weight:700;">BioTern</div>
                                                            <div style="font-size:13px;color:#a3b3cc;">Application Status</div>
                                                        </td>
                                                        <td align="right" style="vertical-align:middle;">' . $logoHtml . '</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:24px;color:#e5e7eb;">
                                                <div style="font-size:18px;font-weight:700;margin-bottom:8px;">You are approved</div>
                                                <div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">Hi ' . $safeName . ',</div>
                                                <div style="font-size:14px;color:#e5e7eb;line-height:1.5;">
                                                    Your BioTern student application has been approved. You can now log in using your Student ID Number and password.
                                                </div>
                                                <div style="margin:18px 0 0;padding:14px;border:1px solid #263653;border-radius:12px;background:#0f172a;">
                                                    <div style="font-size:14px;font-weight:700;color:#ffffff;margin-bottom:6px;">Start here</div>
                                                    <div style="font-size:13px;color:#a3b3cc;line-height:1.5;margin-bottom:12px;">
                                                        Read the student user manual to learn how to use My Profile, Internal DTR, External DTR, documents, chat, email, and account settings.
                                                    </div>
                                                    ' . ($studentManualUrl !== '' ? '<a href="' . $safeManualUrl . '" style="display:inline-block;background:#3454d1;color:#ffffff;text-decoration:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;">Open Student User Manual</a>' : '<div style="font-size:13px;color:#e5e7eb;">After logging in, open <strong>Help &gt; User Manual</strong> from the BioTern menu.</div>') . '
                                                </div>
                                                <div style="margin:20px 0 4px;color:#94a3b8;font-size:13px;">
                                                    If you have questions, reply to this email and our team will help.
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:18px 24px;border-top:1px solid #1f2a44;color:#6b7a99;font-size:12px;">
                                                Thank you for using BioTern.
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>';
                        $mailRef = null;
                        if (!biotern_send_mail($conn, $email, $subject, $textBody, $htmlBody, $mailRef) && $mailRef) {
                            $flashMessage .= ' Email notification could not be sent. Ref: ' . $mailRef;
                        }
                    }
                }
            } catch (Throwable $e) {
                $conn->rollback();
                $flashType = 'danger';
                $flashMessage = 'Unable to process approval: ' . $e->getMessage();
            }
        } else {
            $conn->begin_transaction();
            try {
                if ($userId > 0) {
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
                }

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
                    $stageRejectStmt = $conn->prepare("UPDATE {$applicationsStageTable} SET user_id = NULLIF(?, 0), status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, approval_notes = ?, disciplinary_remark = ?, created_student_user_id = NULL WHERE id = ?");
                    if ($stageRejectStmt) {
                        $stageRejectStmt->bind_param('iissi', $userId, $currentUserId, $notes, $disciplinaryRemark, $applicationId);
                        $stageRejectStmt->execute();
                        $stageRejectStmt->close();
                    }
                }

                $conn->commit();
                $flashType = 'warning';
                $flashMessage = 'Application rejected and assignments updated.';

                $settings = biotern_mail_settings($conn);
                if ((string)($settings['send_application_updates'] ?? '1') === '1') {
                    [$email, $displayName] = review_application_recipient($conn, $stagedApplication, $userId);
                    if ($email !== '') {
                        $subject = 'Your BioTern application was rejected';
                        $textBody = "Hi {$displayName},\n\nYour BioTern student application was rejected. Please contact the school administrator for guidance.\n\nThank you.";
                        $htmlBody = '<p>Hi ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
                            . '<p>Your BioTern student application was rejected. Please contact the school administrator for guidance.</p>'
                            . '<p>Thank you.</p>';
                        $mailRef = null;
                        if (!biotern_send_mail($conn, $email, $subject, $textBody, $htmlBody, $mailRef) && $mailRef) {
                            $flashMessage .= ' Email notification could not be sent. Ref: ' . $mailRef;
                        }
                    }
                }
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

if ($applicationsStageTable !== '') {
    $effectiveStatusSql = "sa.status";
    $sql = "
        SELECT
            sa.id AS application_id,
            COALESCE(u.id, 0) AS user_id,
            COALESCE(u.username, sa.username) AS username,
            COALESCE(u.email, sa.email) AS email,
            COALESCE(u.role, 'student') AS role,
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
            COALESCE(NULLIF(TRIM(s.gender), ''), NULLIF(TRIM(sa.gender), '')) AS gender,
            COALESCE(s.emergency_contact, sa.emergency_contact) AS emergency_contact,
            COALESCE(s.emergency_contact_phone, sa.emergency_contact_phone) AS emergency_contact_phone,
            COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
            COALESCE(s.department_id, sa.department_id) AS department_id,
            COALESCE(s.coordinator_id, sa.coordinator_id) AS coordinator_id,
            COALESCE(s.supervisor_id, sa.supervisor_id) AS supervisor_id,
            COALESCE(s.coordinator_name, sa.coordinator_name) AS coordinator_name,
            COALESCE(s.supervisor_name, sa.supervisor_name) AS supervisor_name,
            COALESCE(s.internal_total_hours, sa.internal_total_hours) AS internal_total_hours,
            COALESCE(s.external_total_hours, sa.external_total_hours) AS external_total_hours,
            COALESCE(NULLIF(s.assignment_track, ''), NULLIF(sa.assignment_track, ''), 'internal') AS assignment_track,
            COALESCE(s.school_year, sa.school_year) AS school_year,
            COALESCE(s.semester, sa.semester) AS semester,
            c.name AS course_name,
            d.name AS department_name,
            COALESCE(sec.code, sa.section_code_snapshot) AS section_code,
            COALESCE(sec.name, sa.section_name_snapshot) AS section_name
        FROM {$applicationsStageTable} sa
        LEFT JOIN users u ON u.id = sa.user_id
        LEFT JOIN students s ON s.user_id = u.id
        LEFT JOIN courses c ON c.id = COALESCE(s.course_id, sa.course_id)
        LEFT JOIN departments d ON d.id = COALESCE(s.department_id, sa.department_id)
        LEFT JOIN sections sec ON sec.id = COALESCE(s.section_id, sa.section_id)
        WHERE 1 = 1
    ";
} else {
    $effectiveStatusSql = "COALESCE(u.application_status, 'approved')";
    $sql = "
        SELECT
            u.id AS application_id,
            u.id AS user_id,
            u.username,
            u.email,
            u.role,
            {$effectiveStatusSql} AS application_status,
            u.application_submitted_at,
            u.approved_at,
            u.rejected_at,
            u.approval_notes,
            u.disciplinary_remark,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.address,
            s.phone,
            s.date_of_birth,
            NULLIF(TRIM(s.gender), '') AS gender,
            s.emergency_contact,
            s.emergency_contact_phone,
            COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
            s.department_id,
            s.coordinator_id,
            s.supervisor_id,
            s.coordinator_name,
            s.supervisor_name,
            s.internal_total_hours,
            s.external_total_hours,
            COALESCE(NULLIF(s.assignment_track, ''), 'internal') AS assignment_track,
            s.school_year,
            s.semester,
            c.name AS course_name,
            d.name AS department_name,
            sec.code AS section_code,
            sec.name AS section_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        WHERE u.role = 'student'
    ";
}

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

$sql .= $applicationsStageTable !== ''
    ? " ORDER BY COALESCE(sa.submitted_at, sa.created_at) DESC, sa.id DESC"
    : " ORDER BY COALESCE(u.application_submitted_at, u.created_at) DESC, u.id DESC";
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
                <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" data-bs-toggle="collapse" data-bs-target="#applicationsReviewActionsMenu" aria-expanded="false" aria-controls="applicationsReviewActionsMenu">
                    <i class="feather-grid me-1"></i>
                    <span>Actions</span>
                </button>
                <div class="page-header-actions app-applications-actions-panel collapse" id="applicationsReviewActionsMenu">
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
                                        $secLabel = biotern_format_section_label($secCode, $secName);
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
                                            $sectionLabel = biotern_format_section_label($sectionCode, $sectionName);
                                            if ($sectionLabel === '') {
                                                $sectionLabel = 'To be assigned';
                                            }
                                            $semesterLabel = $semesterValue !== '' ? $semesterValue : 'Not set';
                                            $schoolYearLabel = $schoolYearValue !== '' ? $schoolYearValue : 'Not set';
                                            $coordinatorLabel = $coordinatorName !== '' ? $coordinatorName : 'To be assigned';
                                            $supervisorLabel = $supervisorName !== '' ? $supervisorName : 'To be assigned';
                                            $addressLabel = $addressValue !== '' ? $addressValue : '-';
                                            $phoneLabel = $phoneValue !== '' ? $phoneValue : '-';
                                            $dateOfBirthLabel = $dateOfBirthValue !== '' ? $dateOfBirthValue : '-';
                                            $genderLabel = formatGenderLabel($genderValue);
                                            $emergencyContactLabel = $emergencyContactNameOnly !== '' ? $emergencyContactNameOnly : '-';
                                            $emergencyContactPhoneLabel = $emergencyContactPhoneValue !== '' ? $emergencyContactPhoneValue : '-';

                                            $submittedAt = formatDisplayDateTime($row['application_submitted_at'] ?? '');
                                            $approvedAt = formatDisplayDateTime($row['approved_at'] ?? '');
                                            $rejectedAt = formatDisplayDateTime($row['rejected_at'] ?? '');
                                            $assignmentTrack = strtolower(trim((string)($row['assignment_track'] ?? 'internal')));
                                            if (!in_array($assignmentTrack, ['internal', 'external'], true)) {
                                                $assignmentTrack = 'internal';
                                            }
                                            $assignmentTrackLabel = ucfirst($assignmentTrack);
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
                                                    <?php $studentProfile = reviewResolveProfileImageUrl((string)($row['profile_picture'] ?? '')); ?>
                                                    <?php if ($studentProfile !== null): ?>
                                                        <span class="student-avatar"><img src="<?php echo htmlspecialchars($studentProfile, ENT_QUOTES, 'UTF-8'); ?>" alt="Student" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></span>
                                                    <?php else: ?>
                                                        <span class="student-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
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
                                                <div class="apps-review-hours-stack">
                                                    <span class="hours-pill">
                                                        <span class="hours-pill-value"><?php echo reviewHoursValue($row['internal_total_hours'] ?? null, $reviewDefaultInternalHours); ?> / <?php echo reviewHoursValue($row['external_total_hours'] ?? null, $reviewDefaultExternalHours); ?></span>
                                                    </span>
                                                    <small class="apps-review-hours-track">Track: <?php echo htmlspecialchars($assignmentTrackLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                                </div>
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
                                                            <div class="line line-wide"><strong>Address:</strong> <?php echo htmlspecialchars($addressLabel, ENT_QUOTES, 'UTF-8'); ?></div>
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
                                                            <div class="line"><strong>Assignment Track:</strong> <?php echo htmlspecialchars($assignmentTrackLabel, ENT_QUOTES, 'UTF-8'); ?></div>
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
                                                            <input type="hidden" name="application_id" value="<?php echo (int)($row['application_id'] ?? 0); ?>">
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
                                                            <div class="field-wrap">
                                                                <label class="field-label">Coordinator</label>
                                                                <select class="form-control form-control-sm" name="coordinator_id" title="Coordinator">
                                                                    <option value="0">Unassigned</option>
                                                                    <?php foreach ($coordinatorOptions as $coor): ?>
                                                                        <?php $coorId = (int)($coor['id'] ?? 0); ?>
                                                                        <option value="<?php echo $coorId; ?>" <?php echo $coorId === $selectedCoordinatorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($coor['full_name'] ?? ('Coordinator #' . $coorId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="field-wrap">
                                                                <label class="field-label">Supervisor</label>
                                                                <select class="form-control form-control-sm" name="supervisor_id" title="Supervisor">
                                                                    <option value="0">Unassigned</option>
                                                                    <?php foreach ($supervisorOptions as $sup): ?>
                                                                        <?php $supId = (int)($sup['id'] ?? 0); ?>
                                                                        <option value="<?php echo $supId; ?>" <?php echo $supId === $selectedSupervisorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($sup['full_name'] ?? ('Supervisor #' . $supId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="field-wrap">
                                                                <label class="field-label">Internal OJT Hours</label>
                                                                <input type="number" class="form-control form-control-sm" name="internal_total_hours" min="0" required value="<?php echo reviewHoursValue($row['internal_total_hours'] ?? null, $reviewDefaultInternalHours); ?>" title="Internal OJT Hours">
                                                            </div>
                                                            <div class="field-wrap">
                                                                <label class="field-label">External OJT Hours</label>
                                                                <input type="number" class="form-control form-control-sm" name="external_total_hours" min="0" required value="<?php echo reviewHoursValue($row['external_total_hours'] ?? null, $reviewDefaultExternalHours); ?>" title="External OJT Hours">
                                                            </div>
                                                            <div class="field-wrap approval-note">
                                                                <label class="field-label">Approval Note</label>
                                                                <input type="text" class="form-control form-control-sm" name="approval_notes" placeholder="Add note (optional)">
                                                            </div>
                                                            <div class="field-wrap disciplinary-note">
                                                                <label class="field-label">Disciplinary Remark</label>
                                                                <input type="text" class="form-control form-control-sm" name="disciplinary_remark" placeholder="Disciplinary remark (if misconduct)">
                                                            </div>
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





