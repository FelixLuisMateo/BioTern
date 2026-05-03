<?php
ob_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/ojt_masterlist_import.php';
require_once __DIR__ . '/excel-workbook-reader.php';
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator'], true)) {
    header('Location: homepage.php');
    exit;
}
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$coordinatorAllowedCourseIds = $role === 'coordinator'
    ? coordinator_course_ids($conn, $currentUserId)
    : [];

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


$flashType = '';
$flashMessage = '';
$flashDetail = '';
$previewRows = [];
$previewHeaders = [];
$previewDuplicates = [];

$normalizeValue = static function (string $value): string {
    return strtolower(trim($value));
};

$headerCandidates = [
    'student_no' => ['student_no', 'studentno', 'student_number', 'student_num', 'student_id'],
    'user_id' => ['user_id', 'userid'],
    'last_name' => ['last_name', 'lastname', 'surname'],
    'first_name' => ['first_name', 'firstname', 'given_name'],
    'middle_name' => ['middle_name', 'middlename', 'middle_initial'],
    'course_id' => ['course_id', 'courseid'],
    'section_id' => ['section_id', 'sectionid'],
    'email' => ['email', 'email_address'],
    'password' => ['password', 'pass'],
    'status' => ['status', 'state'],
    'created_at' => ['created_at', 'created'],
    'updated_at' => ['updated_at', 'update_at', 'updated'],
];

function ojt_internal_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

function ojt_internal_add_column_if_missing(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!ojt_internal_column_exists($conn, $table, $column)) {
        $safeTable = str_replace('`', '``', $table);
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN {$definition}");
    }
}

function ojt_internal_ensure_account_schema(mysqli $conn): void
{
    ojt_internal_add_column_if_missing($conn, 'users', 'application_status', "application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
    ojt_internal_add_column_if_missing($conn, 'users', 'application_submitted_at', 'application_submitted_at DATETIME NULL');
    ojt_internal_add_column_if_missing($conn, 'users', 'approved_by', 'approved_by INT NULL');
    ojt_internal_add_column_if_missing($conn, 'users', 'approved_at', 'approved_at DATETIME NULL');
    ojt_internal_add_column_if_missing($conn, 'users', 'email_verified_at', 'email_verified_at DATETIME NULL');
    ojt_internal_add_column_if_missing($conn, 'students', 'semester', 'semester VARCHAR(30) DEFAULT NULL');
    ojt_internal_add_column_if_missing($conn, 'students', 'assignment_track', "assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'");
    ojt_internal_add_column_if_missing($conn, 'students', 'application_status', "application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
}

function ojt_internal_password_hash(string $password): string
{
    $password = trim($password);
    if ($password === '') {
        return '';
    }
    $info = password_get_info($password);
    if (!empty($info['algo'])) {
        return $password;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return $hash !== false ? $hash : $password;
}

function ojt_internal_school_year(): string
{
    $year = (int)date('Y');
    $month = (int)date('n');
    if ($month >= 8) {
        return $year . '-' . ($year + 1);
    }
    return ($year - 1) . '-' . $year;
}

function ojt_internal_find_student(mysqli $conn, string $studentNo, string $email): ?array
{
    if ($studentNo !== '') {
        $stmt = $conn->prepare('SELECT id, user_id FROM students WHERE student_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $studentNo);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    if ($email !== '') {
        $stmt = $conn->prepare('SELECT id, user_id FROM students WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    return null;
}

function ojt_internal_find_student_user(mysqli $conn, int $preferredUserId, string $email, string $studentNo): int
{
    if ($preferredUserId > 0) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $preferredUserId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return (int)$row['id'];
            }
        }
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'student' AND (email = ? OR username = ?) ORDER BY id ASC LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ss', $email, $studentNo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function ojt_internal_create_or_sync_account(mysqli $conn, array $row, int $currentUserId, string &$message): int
{
    $message = '';
    $studentNo = trim((string)($row['student_no'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    $firstName = trim((string)($row['first_name'] ?? ''));
    $middleName = trim((string)($row['middle_name'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $password = trim((string)($row['password'] ?? ''));
    $courseId = (int)($row['course_id'] ?? 0);
    $sectionId = (int)($row['section_id'] ?? 0);
    $preferredUserId = (int)($row['user_id'] ?? 0);
    $status = trim((string)($row['status'] ?? 'active'));
    $studentStatus = strtolower($status) === 'inactive' || $status === '0' ? '0' : '1';

    if ($studentNo === '' || $lastName === '' || $firstName === '') {
        $message = 'missing required student fields';
        return 0;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'missing valid account email';
        return 0;
    }

    $student = ojt_internal_find_student($conn, $studentNo, $email);
    if ($student && (int)($student['user_id'] ?? 0) > 0) {
        $preferredUserId = (int)$student['user_id'];
    }

    $userId = ojt_internal_find_student_user($conn, $preferredUserId, $email, $studentNo);
    $passwordHash = ojt_internal_password_hash($password);
    $fullName = trim($firstName . ' ' . ($middleName !== '' ? $middleName . ' ' : '') . $lastName);
    $profilePicture = '';

    if ($userId <= 0) {
        if ($passwordHash === '') {
            $message = 'password blank; account not created';
            return 0;
        }
        $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, role, is_active, application_status, application_submitted_at, approved_by, approved_at, profile_picture, created_at, updated_at) VALUES (?, ?, ?, ?, 'student', 1, 'approved', NOW(), NULLIF(?, 0), NOW(), ?, NOW(), NOW())");
        if (!$stmt) {
            $message = 'unable to prepare user account';
            return 0;
        }
        $stmt->bind_param('ssssis', $fullName, $studentNo, $email, $passwordHash, $currentUserId, $profilePicture);
        if (!$stmt->execute()) {
            $message = 'unable to create user account: ' . $stmt->error;
            $stmt->close();
            return 0;
        }
        $userId = (int)$conn->insert_id;
        $stmt->close();
    } else {
        if ($passwordHash !== '') {
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email_verified_at = CASE WHEN email <> ? THEN NULL ELSE email_verified_at END, email = ?, password = ?, role = 'student', is_active = 1, application_status = 'approved', approved_by = COALESCE(approved_by, NULLIF(?, 0)), approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('sssssii', $fullName, $studentNo, $email, $email, $passwordHash, $currentUserId, $userId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email_verified_at = CASE WHEN email <> ? THEN NULL ELSE email_verified_at END, email = ?, role = 'student', is_active = 1, application_status = 'approved', approved_by = COALESCE(approved_by, NULLIF(?, 0)), approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ssssii', $fullName, $studentNo, $email, $email, $currentUserId, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($userId <= 0) {
        $message = 'unable to resolve user account';
        return 0;
    }

    if ($student) {
        $studentPk = (int)($student['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE students SET user_id = ?, course_id = CASE WHEN ? > 0 THEN ? ELSE course_id END, student_id = ?, first_name = ?, last_name = ?, middle_name = NULLIF(?, ''), username = ?, password = COALESCE(NULLIF(?, ''), password), email = ?, section_id = CASE WHEN ? > 0 THEN ? ELSE section_id END, status = ?, school_year = COALESCE(NULLIF(school_year, ''), ?), assignment_track = 'internal', application_status = 'approved', updated_at = NOW() WHERE id = ? LIMIT 1");
        if ($stmt) {
            $schoolYear = ojt_internal_school_year();
            $studentPasswordHash = $passwordHash !== '' ? $passwordHash : '';
            $stmt->bind_param('iiisssssssiissi', $userId, $courseId, $courseId, $studentNo, $firstName, $lastName, $middleName, $studentNo, $studentPasswordHash, $email, $sectionId, $sectionId, $studentStatus, $schoolYear, $studentPk);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $schoolYear = ojt_internal_school_year();
        $empty = '';
        $departmentId = '0';
        $zero = 0;
        $studentPasswordHash = $passwordHash !== '' ? $passwordHash : ojt_internal_password_hash(bin2hex(random_bytes(4)));
        $stmt = $conn->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, bio, department_id, section_id, supervisor_name, coordinator_name, supervisor_id, coordinator_id, phone, date_of_birth, gender, address, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining, emergency_contact, profile_picture, status, school_year, assignment_track, application_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?, ?, ?, ?, NULL, ?, ?, ?, 'internal', 'approved', NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param('iisssssssssiiiiisss', $userId, $courseId, $studentNo, $firstName, $lastName, $middleName, $studentNo, $studentPasswordHash, $email, $empty, $departmentId, $sectionId, $zero, $zero, $zero, $zero, $profilePicture, $studentStatus, $schoolYear);
            if (!$stmt->execute()) {
                $message = 'account created but student profile was not created: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    $stmt = $conn->prepare("UPDATE ojt_internal SET user_id = ?, updated_at = NOW() WHERE student_no = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('is', $userId, $studentNo);
        $stmt->execute();
        $stmt->close();
    }

    return $userId;
}


// Step 1: Preview (file upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $tmp = (string)($_FILES['excel_file']['tmp_name'] ?? '');
        $originalName = trim((string)($_FILES['excel_file']['name'] ?? 'uploaded-workbook.xlsx'));
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }
        $workbookReadError = '';
        $rows = ojt_import_load_workbook_rows($tmp, $originalName, $workbookReadError);
        if ($rows === []) {
            throw new RuntimeException($workbookReadError !== '' ? $workbookReadError : 'Unable to read uploaded workbook.');
        }
        if (biotern_ojt_masterlist_header_present($rows)) {
            $masterlistErrors = [];
            $imported = biotern_ojt_masterlist_import_rows($conn, $rows, $originalName, 'internal', $masterlistErrors);
            $flashType = $imported > 0 ? 'success' : 'warning';
            $flashMessage = 'Internal masterlist import finished. Rows saved: ' . $imported . '.';
            $flashDetail = $masterlistErrors !== [] ? implode(' ', array_slice($masterlistErrors, 0, 5)) : 'Teacher-provided details will merge with registered student accounts by student_no.';
            unset($_SESSION['ojt_internal_preview']);
            throw new RuntimeException('__BIOTERN_MASTERLIST_IMPORTED__');
        }
        $normalizedHeader = array_keys($rows[0]);
        $resolved = [];
        foreach ($headerCandidates as $target => $candidates) {
            $resolved[$target] = null;
            foreach ($normalizedHeader as $normalized) {
                if (in_array($normalized, $candidates, true)) {
                    $resolved[$target] = (string)$normalized;
                    break;
                }
            }
        }
        if ($resolved['student_no'] === null || $resolved['last_name'] === null || $resolved['first_name'] === null) {
            throw new RuntimeException('Workbook must include Student No, Last Name, and First Name columns.');
        }
        $existingStudentNos = [];
        $existingRes = $conn->query('SELECT student_no FROM ojt_internal');
        if ($existingRes instanceof mysqli_result) {
            while ($existing = $existingRes->fetch_assoc()) {
                $existingNo = trim((string)($existing['student_no'] ?? ''));
                if ($existingNo !== '') {
                    $existingStudentNos[$normalizeValue($existingNo)] = true;
                }
            }
            $existingRes->close();
        }
        $seenStudentNos = [];
        $previewRows = [];
        $previewDuplicates = [];
        foreach ($rows as $row) {
            $studentNo = trim((string)($row[$resolved['student_no']] ?? ''));
            $normalizedStudentNo = $normalizeValue($studentNo);
            if ($studentNo === '') continue;
            if (isset($existingStudentNos[$normalizedStudentNo]) || isset($seenStudentNos[$normalizedStudentNo])) {
                $previewDuplicates[$studentNo] = true;
            }
            $seenStudentNos[$normalizedStudentNo] = true;
            $previewRows[] = $row;
        }
        $previewHeaders = $normalizedHeader;
        $_SESSION['ojt_internal_preview'] = [
            'rows' => $previewRows,
            'headers' => $previewHeaders,
            'duplicates' => $previewDuplicates,
            'resolved' => $resolved
        ];
    } catch (Throwable $e) {
        if ($e->getMessage() !== '__BIOTERN_MASTERLIST_IMPORTED__') {
            $flashType = 'danger';
            $flashMessage = $e->getMessage();
            $flashDetail = '';
        }
    }
}

// Step 2: Confirm Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import']) && isset($_SESSION['ojt_internal_preview'])) {
    try {
        $preview = $_SESSION['ojt_internal_preview'];
        $rows = $preview['rows'];
        $resolved = $preview['resolved'];
        $stmt = $conn->prepare("INSERT INTO ojt_internal
            (student_no, user_id, last_name, first_name, middle_name, course_id, section_id, email, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = CASE
                    WHEN ojt_internal.user_id IS NULL OR ojt_internal.user_id = 0
                        THEN COALESCE(NULLIF(VALUES(user_id), 0), ojt_internal.user_id)
                    ELSE ojt_internal.user_id
                END,
                last_name = VALUES(last_name),
                first_name = VALUES(first_name),
                middle_name = VALUES(middle_name),
                course_id = COALESCE(NULLIF(VALUES(course_id), 0), ojt_internal.course_id),
                section_id = COALESCE(NULLIF(VALUES(section_id), 0), ojt_internal.section_id),
                email = COALESCE(NULLIF(VALUES(email), ''), ojt_internal.email),
                password = CASE
                    WHEN ojt_internal.user_id IS NOT NULL AND ojt_internal.user_id > 0
                        THEN ojt_internal.password
                    WHEN NULLIF(VALUES(password), '') IS NULL
                        THEN ojt_internal.password
                    ELSE VALUES(password)
                END,
                status = COALESCE(NULLIF(VALUES(status), ''), ojt_internal.status),
                updated_at = CURRENT_TIMESTAMP");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare import query.');
        }
        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $duplicateStudentNos = [];
        $existingStudentNos = [];
        $existingRes = $conn->query('SELECT student_no FROM ojt_internal');
        if ($existingRes instanceof mysqli_result) {
            while ($existing = $existingRes->fetch_assoc()) {
                $existingNo = trim((string)($existing['student_no'] ?? ''));
                if ($existingNo !== '') {
                    $existingStudentNos[$normalizeValue($existingNo)] = true;
                }
            }
            $existingRes->close();
        }
        $seenStudentNos = [];
        $invalidSkipped = 0;
        $accountsCreatedOrSynced = 0;
        $accountSkipped = 0;
        $accountWarnings = [];
        ojt_internal_ensure_account_schema($conn);
        foreach ($rows as $row) {
            $studentNo = trim((string)($row[$resolved['student_no']] ?? ''));
            if ($studentNo === '') {
                $invalidSkipped++;
                continue;
            }
            $userIdRaw = $resolved['user_id'] !== null ? trim((string)($row[$resolved['user_id']] ?? '')) : '';
            $courseIdRaw = $resolved['course_id'] !== null ? trim((string)($row[$resolved['course_id']] ?? '')) : '';
            $sectionIdRaw = $resolved['section_id'] !== null ? trim((string)($row[$resolved['section_id']] ?? '')) : '';
            $userId = (ctype_digit($userIdRaw) && (int)$userIdRaw > 0) ? (int)$userIdRaw : null;
            $courseId = (ctype_digit($courseIdRaw) && (int)$courseIdRaw > 0) ? (int)$courseIdRaw : null;
            $sectionId = (ctype_digit($sectionIdRaw) && (int)$sectionIdRaw > 0) ? (int)$sectionIdRaw : null;
            if ($role === 'coordinator' && ($courseId === null || !in_array($courseId, $coordinatorAllowedCourseIds, true))) {
                $invalidSkipped++;
                continue;
            }
            $lastName = trim((string)($row[$resolved['last_name']] ?? ''));
            $firstName = trim((string)($row[$resolved['first_name']] ?? ''));
            $middleName = $resolved['middle_name'] !== null ? trim((string)($row[$resolved['middle_name']] ?? '')) : '';
            $email = $resolved['email'] !== null ? trim((string)($row[$resolved['email']] ?? '')) : '';
            $password = $resolved['password'] !== null ? trim((string)($row[$resolved['password']] ?? '')) : '';
            $status = $resolved['status'] !== null ? trim((string)($row[$resolved['status']] ?? '')) : 'active';
            if ($status === '') $status = 'active';
            if ($lastName === '' || $firstName === '') {
                $invalidSkipped++;
                continue;
            }
            $normalizedStudentNo = $normalizeValue($studentNo);
            if (isset($existingStudentNos[$normalizedStudentNo]) || isset($seenStudentNos[$normalizedStudentNo])) {
                $duplicateStudentNos[$studentNo] = true;
            }
            $seenStudentNos[$normalizedStudentNo] = true;
            $stmt->bind_param(
                'sisssiisss',
                $studentNo,
                $userId,
                $lastName,
                $firstName,
                $middleName,
                $courseId,
                $sectionId,
                $email,
                $password,
                $status
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Import failed for Student No ' . $studentNo . ': ' . $stmt->error);
            }
            if ((int)$stmt->affected_rows === 1) {
                $inserted++;
            } else {
                $updated++;
            }
            $processed++;

            $accountRow = [
                'student_no' => $studentNo,
                'user_id' => $userId !== null ? $userId : 0,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'course_id' => $courseId !== null ? $courseId : 0,
                'section_id' => $sectionId !== null ? $sectionId : 0,
                'email' => $email,
                'password' => $password,
                'status' => $status,
            ];
            $accountMessage = '';
            $syncedUserId = ojt_internal_create_or_sync_account($conn, $accountRow, $currentUserId, $accountMessage);
            if ($syncedUserId > 0) {
                $accountsCreatedOrSynced++;
            } else {
                $accountSkipped++;
                if ($accountMessage !== '' && count($accountWarnings) < 5) {
                    $accountWarnings[] = $studentNo . ': ' . $accountMessage;
                }
            }
        }
        $stmt->close();
        // Auto-link imported internal data to students table via student number.
        $conn->query("UPDATE ojt_internal oi
                        INNER JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oi.student_no, '')) COLLATE utf8mb4_unicode_ci
            SET oi.user_id = s.user_id
            WHERE (oi.user_id IS NULL OR oi.user_id = 0)
              AND s.user_id IS NOT NULL
              AND s.user_id > 0");
        $duplicateList = array_keys($duplicateStudentNos);
        $flashType = 'success';
        if (!empty($duplicateList)) {
            $flashMessage = 'Import completed. Duplicate Student Numbers were updated (info only).';
        } else {
            $flashMessage = 'Import completed successfully.';
        }
        $flashDetail = 'Processed: ' . $processed . ' | New: ' . $inserted . ' | Replaced: ' . $updated . ' | Accounts created/linked: ' . $accountsCreatedOrSynced . ' | Account rows skipped: ' . $accountSkipped . ' | Duplicate Student No detected: ' . count($duplicateList) . ' | Invalid rows skipped: ' . $invalidSkipped;
        if ($accountWarnings !== []) {
            $flashDetail .= ' | Account notes: ' . implode('; ', $accountWarnings);
        }
        unset($_SESSION['ojt_internal_preview']);
    } catch (Throwable $e) {
        $flashType = 'danger';
        $flashMessage = $e->getMessage();
        $flashDetail = '';
    }
}

$page_title = 'Import OJT Internal';
$page_body_class = 'page-fingerprint-mapping';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
];
$base_href = '';
include __DIR__ . '/../includes/header.php';
ob_end_flush();
?>
<style>
.import-uploader-box { border: 1px dashed rgba(120, 148, 255, 0.45); border-radius: 14px; padding: 16px; background: rgba(13, 32, 82, 0.25); }
.import-kpi { border: 1px solid rgba(120, 148, 255, 0.25); border-radius: 12px; padding: 10px 12px; background: rgba(6, 20, 52, 0.4); }
.import-kpi-label { font-size: 12px; color: #9eb6ff; display: block; }
.import-kpi-value { font-size: 18px; font-weight: 700; color: #ffffff; }
.import-preview-card { margin-top: 1.5rem; }
.import-preview-scroll {
    max-width: 100%;
    max-height: min(72vh, 760px);
    overflow: auto;
    border-radius: 0 0 12px 12px;
}
.import-preview-scroll table {
    min-width: 1200px;
    width: max-content;
}
.import-preview-scroll th,
.import-preview-scroll td {
    white-space: nowrap;
}
.biotern-toast { position: fixed; right: 18px; top: 78px; z-index: 2050; min-width: 320px; max-width: 460px; padding: 12px 14px; border-radius: 10px; color: #fff; opacity: 0; transform: translateY(-10px); transition: all .25s ease; box-shadow: 0 12px 28px rgba(0,0,0,.28); }
.biotern-toast.show { opacity: 1; transform: translateY(0); }
.biotern-toast-success { background: linear-gradient(135deg, #0e8c5a, #15a66d); }
.biotern-toast-danger { background: linear-gradient(135deg, #a02846, #cf3d63); }
</style>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header" data-phc-skip="1">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Import OJT Internal</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Import OJT Internal</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <div class="page-header-right-items">
                    <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                        <a href="ojt-internal-list.php" class="btn btn-light-brand">Internal List</a>
                        <a href="import-ojt-external.php" class="btn btn-outline-secondary">Import OJT External</a>
                        <a href="ojt-external-list.php" class="btn btn-outline-secondary">External List</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
            <div class="card mb-4 bio-console-panel">
                <div class="card-header"><strong>Excel Upload</strong></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="import-kpi">
                                <span class="import-kpi-label">Duplicate Rule</span>
                                <span class="import-kpi-value">Student No (Unique Key)</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <p class="text-muted mb-0">Template columns: student_no, last_name, first_name, middle_name, email, course_id, section_id, password. Rows with a valid email and teacher-provided password create an approved student account; the student still verifies the email on first login.</p>
                        </div>
                    </div>

                    <div class="import-uploader-box">
                        <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="excel_file">OJT Internal Excel File</label>
                                <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required>
                            </div>
                            <div class="col-12 col-md-4 fm-actions d-flex flex-column gap-2 align-items-stretch">
                                <button type="submit" class="btn btn-primary mb-2">Import Excel</button>
                                <a href="ojt-internal-list.php" class="btn btn-light mb-2">View Internal List</a>
                                <a href="download-internal-template.php" class="btn btn-outline-info">
                                    <i class="bi bi-download"></i> Download Template
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!-- Preview Table -->
        <?php
        if (!empty($_SESSION['ojt_internal_preview']['rows'])) {
            $preview = $_SESSION['ojt_internal_preview'];
            $headers = $preview['headers'];
            $rows = $preview['rows'];
            $duplicates = $preview['duplicates'];
            echo '<div class="card import-preview-card"><div class="card-header"><strong>Preview Import Data</strong></div><div class="card-body p-0">';
            echo '<div class="table-responsive import-preview-scroll"><table class="table table-bordered table-sm align-middle mb-0">';
            echo '<thead><tr>';
            foreach ($headers as $h) {
                echo '<th>' . htmlspecialchars($h) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $studentNo = $row[$preview['resolved']['student_no']] ?? '';
                $isDup = isset($duplicates[$studentNo]);
                echo '<tr' . ($isDup ? ' style="background:#ffeaea"' : '') . '>';
                foreach ($headers as $h) {
                    echo '<td>' . htmlspecialchars($row[$h] ?? '') . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            if (!empty($duplicates)) {
                echo '<div class="alert alert-warning mt-2">Duplicate Student No detected: ' . implode(', ', array_keys($duplicates)) . '. These rows will update student information only while keeping linked account/user_id data safe.</div>';
            }
            echo '<form method="post"><button type="submit" name="confirm_import" value="1" class="btn btn-success">Confirm Import</button></form>';
            echo '</div></div>';
        }
        ?>
</main>
<?php if ($flashMessage !== ''): ?>
    <div id="import-toast" class="biotern-toast biotern-toast-<?php echo $flashType === 'success' ? 'success' : 'danger'; ?>">
        <div class="fw-semibold"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if ($flashDetail !== ''): ?>
            <div class="small mt-1"><?php echo htmlspecialchars($flashDetail, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var toast = document.getElementById('import-toast');
        if (!toast) {
            return;
        }
        setTimeout(function () { toast.classList.add('show'); }, 80);
        setTimeout(function () { toast.classList.remove('show'); }, 5500);
    })();
    </script>
<?php endif; ?>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>
