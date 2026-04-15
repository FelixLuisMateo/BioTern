<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/mailer.php';
require_once dirname(__DIR__) . '/lib/student-registration-verification.php';
// Simple registration handler for demo purposes.
// IMPORTANT: Review and secure before using in production.

$dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "DB connection failed: " . $mysqli->connect_error;
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getPost($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

function getCurrentSchoolYearLabel($timestamp = null) {
    $ts = $timestamp !== null ? (int)$timestamp : time();
    $year = (int)date('Y', $ts);
    $month = (int)date('n', $ts);
    $startYear = $month >= 7 ? $year : ($year - 1);
    return sprintf('%d-%d', $startYear, $startYear + 1);
}

function parseStudentDateOfBirthToSql(?string $rawDate): ?string {
    $value = trim((string)$rawDate);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'm/d/Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
            if (!$hasErrors) {
                return $dt->format('Y-m-d');
            }
        }
    }

    return null;
}

function normalizeStudentGender(?string $rawValue): ?string {
    $value = strtolower(trim((string)$rawValue));
    if ($value === '') {
        return null;
    }

    if (in_array($value, ['m', 'male'], true)) {
        return 'male';
    }
    if (in_array($value, ['f', 'female'], true)) {
        return 'female';
    }

    return null;
}

function calculateAgeFromSqlDate(string $dateValue): int {
    try {
        $dob = new DateTime($dateValue);
        $today = new DateTime('today');
        return (int)$dob->diff($today)->y;
    } catch (Throwable $e) {
        return -1;
    }
}

function studentApplicationRedirect(string $status, string $message): void {
    header('Location: auth-register.php?registered=' . rawurlencode($status) . '&msg=' . urlencode($message));
    exit;
}

function sendStudentApplicationReceivedEmail(mysqli $mysqli, string $targetEmail, string $studentName, string $studentId, string $schoolYear, string $semester): void
{
    $safeEmail = trim($targetEmail);
    if ($safeEmail === '' || !filter_var($safeEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $displayName = trim($studentName) !== '' ? trim($studentName) : 'Student';
    $appBaseUrl = biotern_mail_asset_base();
    $subject = 'BioTern application received';
    $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    $safeStudentId = htmlspecialchars(trim($studentId), ENT_QUOTES, 'UTF-8');
    $safeSchoolYear = htmlspecialchars(trim($schoolYear), ENT_QUOTES, 'UTF-8');
    $safeSemester = htmlspecialchars(trim($semester), ENT_QUOTES, 'UTF-8');

    $text = "Hello {$displayName},\n\nWe successfully received your BioTern student application."
        . ($studentId !== '' ? "\nStudent ID: {$studentId}" : '')
        . ($schoolYear !== '' ? "\nSchool Year: {$schoolYear}" : '')
        . ($semester !== '' ? "\nSemester: {$semester}" : '')
        . "\n\nYour application is now pending approval. Please wait for an administrator, coordinator, or supervisor to review it.\n\n"
        . "You will verify your email when you log in after your application is approved.";

    $logoHtml = '';
    if ($appBaseUrl !== '') {
        $logoUrl = $appBaseUrl . '/assets/images/ccstlogo.png';
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="School logo" width="40" height="40" style="display:block;border-radius:8px;">';
    }

    $html = '
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
                                        <div style="font-size:13px;color:#a3b3cc;">Student Application</div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">' . $logoHtml . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;color:#e5e7eb;">
                            <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Application received</div>
                            <div style="font-size:14px;color:#94a3b8;line-height:1.7;">
                                Hello <strong style="color:#ffffff;">' . $safeName . '</strong>, we successfully received your student application.
                            </div>
                            <div style="margin-top:18px;padding:16px 18px;border-radius:14px;background:#0f172a;border:1px solid #26334d;">
                                <div style="font-size:13px;color:#94a3b8;">Student ID</div>
                                <div style="font-size:15px;font-weight:700;color:#ffffff;margin-bottom:10px;">' . ($safeStudentId !== '' ? $safeStudentId : 'Not provided') . '</div>
                                <div style="font-size:13px;color:#94a3b8;">School Year</div>
                                <div style="font-size:15px;font-weight:700;color:#ffffff;margin-bottom:10px;">' . ($safeSchoolYear !== '' ? $safeSchoolYear : 'Not set') . '</div>
                                <div style="font-size:13px;color:#94a3b8;">Semester</div>
                                <div style="font-size:15px;font-weight:700;color:#ffffff;">' . ($safeSemester !== '' ? $safeSemester : 'Not set') . '</div>
                            </div>
                            <div style="margin-top:18px;font-size:14px;color:#cbd5e1;line-height:1.7;">
                                Your application is now pending approval. Please wait for an administrator, coordinator, or supervisor to review it.
                                Once approved, we will ask you to verify your email before you can access the system.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';

    $mailRef = null;
    biotern_send_mail($mysqli, $safeEmail, $subject, $text, $html, $mailRef);
}

function studentIdAlreadyUsed(mysqli $mysqli, string $studentId): bool {
    $value = trim($studentId);
    if ($value === '') {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return true;
        }
    }

    if (ensureStudentApplicationsTable($mysqli)) {
        $stmt = $mysqli->prepare('SELECT id FROM student_applications WHERE student_id = ? AND status IN (\'pending\', \'approved\') LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return true;
            }
        }
    }

    return false;
}

function studentAccountAlreadyCreated(mysqli $mysqli, ?string $studentId, ?string $email): bool
{
    $studentId = trim((string)$studentId);
    $email = trim((string)$email);

    if ($studentId !== '') {
        $stmt = $mysqli->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return true;
            }
        }
    }

    if ($email !== '') {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND role = 'student' AND COALESCE(application_status, 'approved') = 'approved' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return true;
            }
        }
    }

    return false;
}

function generateRegistrationCode(): string
{
    try {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function ensureStudentApplicationsTable(mysqli $mysqli): bool {
    $ok = $mysqli->query("CREATE TABLE IF NOT EXISTS student_applications (
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

function resolveDepartmentIdByCode($mysqli, $departmentCode) {
    $code = trim((string)$departmentCode);
    if ($code === '') {
        return null;
    }
    $conditions = ['code = ?'];
    $conditions = array_merge($conditions, tableWhereActiveClause($mysqli, 'departments'));
    $stmt = $mysqli->prepare("SELECT id FROM departments WHERE " . implode(' AND ', $conditions) . " LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

function resolveSectionId($mysqli, $sectionValue, $courseId = 0) {
    $sectionRaw = trim((string)$sectionValue);
    if ($sectionRaw === '') {
        return 0;
    }

    if (ctype_digit($sectionRaw)) {
        return (int)$sectionRaw;
    }

    $courseId = (int)$courseId;
    $sectionWhereActive = tableWhereActiveClause($mysqli, 'sections');
    if ($courseId > 0) {
        $where = [
            'course_id = ?',
            '(code = ? OR name = ?)'
        ];
        $where = array_merge($where, $sectionWhereActive);
        $stmt = $mysqli->prepare(" 
            SELECT id
            FROM sections
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('iss', $courseId, $sectionRaw, $sectionRaw);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && isset($row['id'])) {
                return (int)$row['id'];
            }
        }
    }

    $where = ['(code = ? OR name = ?)'];
    $where = array_merge($where, $sectionWhereActive);
    $stmt = $mysqli->prepare(" 
        SELECT id
        FROM sections
        WHERE " . implode(' AND ', $where) . "
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('ss', $sectionRaw, $sectionRaw);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
    }

    return 0;
}

function tableHasColumn($mysqli, $tableName, $columnName) {
    $table = trim((string)$tableName);
    $column = trim((string)$columnName);
    if ($table === '' || $column === '') {
        return false;
    }

    $sql = "
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $has = ($res && $res->num_rows > 0);
    $stmt->close();
    return $has;
}

if (!function_exists('biotern_db_has_column')) {
    function biotern_db_has_column(mysqli $mysqli, string $table, string $column): bool
    {
        return tableHasColumn($mysqli, $table, $column);
    }
}

if (!function_exists('biotern_db_add_column_if_missing')) {
    function biotern_db_add_column_if_missing(mysqli $mysqli, string $table, string $column, string $columnDefinition): bool
    {
        if (biotern_db_has_column($mysqli, $table, $column)) {
            return true;
        }

        $safeTable = str_replace('`', '``', $table);
        try {
            return (bool)$mysqli->query("ALTER TABLE `{$safeTable}` ADD COLUMN {$columnDefinition}");
        } catch (Throwable $e) {
            return false;
        }
    }
}

function tableWhereActiveClause($mysqli, $tableName, $alias = '') {
    $prefix = trim((string)$alias) !== '' ? (rtrim((string)$alias, '.') . '.') : '';
    $parts = [];

    if (tableHasColumn($mysqli, $tableName, 'is_active')) {
        $parts[] = $prefix . 'is_active = 1';
    }
    if (tableHasColumn($mysqli, $tableName, 'deleted_at')) {
        $parts[] = $prefix . 'deleted_at IS NULL';
    }

    return $parts;
}

function sanitizeUsernameBase($value, $fallback = 'user') {
    $base = strtolower((string)$value);
    if (strpos($base, '@') !== false) {
        $base = explode('@', $base, 2)[0];
    }
    $base = preg_replace('/[^a-z0-9._-]+/', '.', $base);
    $base = preg_replace('/[._-]{2,}/', '.', $base);
    $base = trim((string)$base, '._-');
    if ($base === '') {
        $base = strtolower((string)$fallback);
    }
    if (strlen($base) < 4) {
        $base = str_pad($base, 4, '0');
    }
    if (strlen($base) > 30) {
        $base = substr($base, 0, 30);
    }
    return $base;
}

function generateUniqueUsername($mysqli, $primarySeed, $fallbackSeed = 'user') {
    $base = sanitizeUsernameBase($primarySeed, $fallbackSeed);

    $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        return $base;
    }

    $candidate = $base;
    $suffix = 1;
    while (true) {
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        if (!$exists) {
            $stmt->close();
            return $candidate;
        }

        $suffixText = (string)$suffix;
        $maxBaseLen = 30 - strlen($suffixText);
        if ($maxBaseLen < 1) {
            $maxBaseLen = 1;
        }
        $candidate = substr($base, 0, $maxBaseLen) . $suffixText;
        $suffix++;
    }
}

function ensureUsersTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL DEFAULT '',
        username VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'student',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        application_status VARCHAR(20) NOT NULL DEFAULT 'approved',
        application_submitted_at DATETIME NULL,
        created_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_username (username),
        UNIQUE KEY uq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$registerUserSchemaColumns = [
    'application_status' => "application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'",
    'application_submitted_at' => "application_submitted_at DATETIME NULL",
    'approved_by' => "approved_by INT NULL",
    'approved_at' => "approved_at DATETIME NULL",
    'rejected_at' => "rejected_at DATETIME NULL",
    'approval_notes' => "approval_notes VARCHAR(255) NULL",
];
foreach ($registerUserSchemaColumns as $column => $definition) {
    biotern_db_add_column_if_missing($mysqli, 'users', $column, $definition);
}

$role = getPost('role');
if (!$role) {
    header('Location: register_submit.php');
    exit;
}

// Create a user record if `users` table exists
function createUser($mysqli, $username, $email, $password, $role, &$errorCode = null, &$errorMessage = null) {
    $errorCode = null;
    $errorMessage = null;
    ensureUsersTable($mysqli);

    // check users table
    $res = $mysqli->query("SHOW TABLES LIKE 'users'");
    $userId = null;
    if ($res && $res->num_rows > 0) {
        $pwdHash = password_hash($password, PASSWORD_DEFAULT);
        $hasIsActive = tableHasColumn($mysqli, 'users', 'is_active');
        $hasAppStatus = tableHasColumn($mysqli, 'users', 'application_status');
        $hasSubmittedAt = tableHasColumn($mysqli, 'users', 'application_submitted_at');
        $hasProfilePicture = tableHasColumn($mysqli, 'users', 'profile_picture');
        $hasCreatedAt = tableHasColumn($mysqli, 'users', 'created_at');

        $name = $username;
        $columns = ['name', 'username', 'email', 'password', 'role'];
        $values = ['?', '?', '?', '?', '?'];
        $bindTypes = 'sssss';
        $bindValues = [$name, $username, $email, $pwdHash, $role];

        if ($hasIsActive) {
            $columns[] = 'is_active';
            $values[] = '1';
        }
        if ($hasAppStatus) {
            $columns[] = 'application_status';
            $values[] = "'approved'";
        }
        if ($hasSubmittedAt) {
            $columns[] = 'application_submitted_at';
            $values[] = 'NOW()';
        }
        if ($hasProfilePicture) {
            $profilePicture = '';
            $columns[] = 'profile_picture';
            $values[] = '?';
            $bindTypes .= 's';
            $bindValues[] = $profilePicture;
        }
        if ($hasCreatedAt) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        $insertSql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = $mysqli->prepare($insertSql);
        if ($stmt) {
            $bindParams = [$bindTypes];
            foreach ($bindValues as $idx => $value) {
                $bindParams[] = &$bindValues[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            try {
                $executed = $stmt->execute();
                if (!$executed) {
                    $errorCode = (int)($stmt->errno ?: $mysqli->errno);
                    $errorMessage = trim((string)$stmt->error);
                    if ($errorMessage === '') {
                        $errorMessage = trim((string)$mysqli->error);
                    }
                    if ($errorMessage === '') {
                        $errorMessage = 'User insert failed during execute().';
                    }
                    $stmt->close();
                    return null;
                }
                $userId = $mysqli->insert_id;
            } catch (mysqli_sql_exception $e) {
                $errorCode = (int)$e->getCode();
                $errorMessage = (string)$e->getMessage();
                $stmt->close();
                // Duplicate entry (1062) or other SQL error - return null so callers can handle it
                return null;
            }
            $stmt->close();
        } else {
            $errorCode = -1;
            $errorMessage = (string)$mysqli->error;
            return null;
        }
    } else {
        $errorCode = -2;
        $errorMessage = $mysqli->error !== ''
            ? ('Unable to access users table: ' . $mysqli->error)
            : 'Unable to access users table.';
    }
    return $userId;
}

if ($role === 'student') {
    $default_internal_hours = 140;
    $default_external_hours = 250;

    // Keep student hour/assignment fields available even on older databases.
    $registerStudentSchemaColumns = [
        'department_id' => "department_id VARCHAR(255) NULL",
        'internal_total_hours_remaining' => "internal_total_hours_remaining INT(11) DEFAULT NULL",
        'external_total_hours' => "external_total_hours INT(11) DEFAULT NULL",
        'external_total_hours_remaining' => "external_total_hours_remaining INT(11) DEFAULT NULL",
        'assignment_track' => "assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'",
        'school_year' => "school_year VARCHAR(9) NULL",
        'semester' => "semester VARCHAR(30) NULL",
        'application_status' => "application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
        'status' => "status TINYINT(1) NOT NULL DEFAULT 1",
    ];
    foreach ($registerStudentSchemaColumns as $column => $definition) {
        biotern_db_add_column_if_missing($mysqli, 'students', $column, $definition);
    }

    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        studentApplicationRedirect('error', 'Passwords do not match.');
    }
    
    $student_id = getPost('student_id');
    $first_name = getPost('first_name');
    $middle_name = getPost('middle_name');
    $last_name = getPost('last_name');
    $address = getPost('address');
    $email = getPost('email');
    $course_id = getPost('course_id');
    $section = getPost('section');
    $department_id_raw = getPost('department_id');
    $department_code = getPost('department_code');
    $account_email = getPost('account_email');
    $semester = getPost('semester');
    $school_year_posted = getPost('school_year');
    
    // New fields - now accepting IDs
    $phone = getPost('phone');
    $date_of_birth_raw = getPost('date_of_birth');
    $gender = normalizeStudentGender(getPost('gender'));
    $supervisor_id = getPost('supervisor_id') ? (int)getPost('supervisor_id') : null;
    $coordinator_id = getPost('coordinator_id') ? (int)getPost('coordinator_id') : null;
    if ($supervisor_id !== null && $supervisor_id <= 0) {
        $supervisor_id = null;
    }
    if ($coordinator_id !== null && $coordinator_id <= 0) {
        $coordinator_id = null;
    }
    $internal_total_hours = getPost('internal_total_hours');
    if ($internal_total_hours === null || $internal_total_hours === '') {
        // Backward compatibility for older forms still posting total_hours.
        $internal_total_hours = getPost('total_hours');
    }
    $external_total_hours = getPost('external_total_hours');
    $finished_internal = strtolower((string)(getPost('finished_internal') ?: 'no'));
    $emergency_contact = getPost('emergency_contact');
    $emergency_contact_phone = getPost('emergency_contact_phone');

    if (studentAccountAlreadyCreated($mysqli, $student_id, $account_email ?: $email)) {
        studentApplicationRedirect('exists', 'Your account was already created by the school. Please use your Student ID Number to log in or contact the administrator.');
    }

    if ($student_id !== null && $student_id !== '' && !preg_match('/^05-\d{4,5}$/', (string)$student_id)) {
        studentApplicationRedirect('error', 'School ID Number must follow the format 05-1234 or 05-12345.');
    }
    if ($student_id !== null && $student_id !== '' && studentIdAlreadyUsed($mysqli, (string)$student_id)) {
        studentApplicationRedirect('exists', 'An application or account already exists for that School ID Number.');
    }

    $date_of_birth = parseStudentDateOfBirthToSql($date_of_birth_raw);
    if ($date_of_birth === null) {
        studentApplicationRedirect('error', 'Please enter Date of Birth using mm/dd/yyyy.');
    }
    $age = calculateAgeFromSqlDate($date_of_birth);
    if ($age < 17) {
        studentApplicationRedirect('error', 'Student applicants must be at least 17 years old.');
    }
    if ($gender === null) {
        studentApplicationRedirect('error', 'Please select a valid gender.');
    }

    // Use account_email if provided, otherwise use email
    $final_email = $account_email ?: $email;
    if ($final_email === null || $final_email === '' || !filter_var($final_email, FILTER_VALIDATE_EMAIL)) {
        studentApplicationRedirect('error', 'Please provide a valid account email address.');
    }

    $username_seed = $student_id ?: ($final_email ?: ($first_name . '.' . $last_name));
    $username = generateUniqueUsername($mysqli, $username_seed, 'student');
    $course_id = (int)$course_id;
    $department_id = null;
    if ($department_id_raw !== null && $department_id_raw !== '' && ctype_digit((string)$department_id_raw)) {
        $parsed_department_id = (int)$department_id_raw;
        $department_id = $parsed_department_id > 0 ? $parsed_department_id : null;
    } else {
        $department_id = resolveDepartmentIdByCode($mysqli, $department_code);
    }
    $section_id = resolveSectionId($mysqli, $section, $course_id);
    $internal_total_hours = ($internal_total_hours === null || $internal_total_hours === '')
        ? $default_internal_hours
        : (int)$internal_total_hours;
    $external_total_hours = ($external_total_hours === null || $external_total_hours === '')
        ? $default_external_hours
        : (int)$external_total_hours;
    if ($internal_total_hours < 0) $internal_total_hours = 0;
    if ($external_total_hours < 0) $external_total_hours = 0;
    $finished_internal_yes = in_array($finished_internal, ['yes', '1', 'true'], true);
    $internal_total_hours_remaining = $finished_internal_yes ? 0 : $internal_total_hours;
    $external_total_hours_remaining = $finished_internal_yes ? $external_total_hours : 0;
    $assignment_track = $finished_internal_yes ? 'external' : 'internal';
    $school_year = $school_year_posted && preg_match('/^\d{4}-\d{4}$/', (string)$school_year_posted)
        ? (string)$school_year_posted
        : getCurrentSchoolYearLabel();
    $semester = trim((string)$semester);
    if (!in_array($semester, ['1st Semester', '2nd Semester', 'Summer'], true)) {
        $semester = '';
    }
    $coordinator_name = null;
    $supervisor_name = null;

    // Strict server-side integrity checks for tampered submissions.
    if ($course_id <= 0) {
        studentApplicationRedirect('error', 'Please choose your course before submitting your application.');
    }
    if ($section_id <= 0) {
        studentApplicationRedirect('error', 'Please choose a valid section before submitting your application.');
    }

    $courseWhere = ['id = ?'];
    $courseWhere = array_merge($courseWhere, tableWhereActiveClause($mysqli, 'courses'));
    $course_check = $mysqli->prepare("SELECT id FROM courses WHERE " . implode(' AND ', $courseWhere) . " LIMIT 1");
    if (!$course_check) {
        studentApplicationRedirect('error', 'We could not validate your selected course right now. Please try again.');
    }
    $course_check->bind_param('i', $course_id);
    $course_check->execute();
    $course_ok = $course_check->get_result()->fetch_assoc();
    $course_check->close();
    if (!$course_ok) {
        studentApplicationRedirect('error', 'The selected course is invalid. Please choose another one.');
    }

    $department_id_int = !empty($department_id) ? (int)$department_id : 0;
    if ($department_id_int > 0) {
        $deptWhere = ['id = ?'];
        $deptWhere = array_merge($deptWhere, tableWhereActiveClause($mysqli, 'departments'));
        $dept_check = $mysqli->prepare("SELECT id FROM departments WHERE " . implode(' AND ', $deptWhere) . " LIMIT 1");
        if (!$dept_check) {
            studentApplicationRedirect('error', 'We could not validate your selected department right now. Please try again.');
        }
        $dept_check->bind_param('i', $department_id_int);
        $dept_check->execute();
        $dept_ok = $dept_check->get_result()->fetch_assoc();
        $dept_check->close();
        if (!$dept_ok) {
            studentApplicationRedirect('error', 'The selected department is invalid. Please choose another one.');
        }
    }

    if ($section_id > 0) {
        if ($department_id_int > 0) {
            $sectionWhere = [
                'id = ?',
                'course_id = ?',
                'department_id = ?'
            ];
            $sectionWhere = array_merge($sectionWhere, tableWhereActiveClause($mysqli, 'sections'));
            $section_check = $mysqli->prepare(" 
                SELECT id
                FROM sections
                WHERE " . implode(' AND ', $sectionWhere) . "
                LIMIT 1
            ");
            if (!$section_check) {
                studentApplicationRedirect('error', 'We could not validate your selected section right now. Please try again.');
            }
            $section_check->bind_param('iii', $section_id, $course_id, $department_id_int);
            $section_check->execute();
            $section_ok = $section_check->get_result()->fetch_assoc();
            $section_check->close();
            if (!$section_ok) {
                studentApplicationRedirect('error', 'The selected section does not match your chosen course and department.');
            }
        } else {
            $sectionWhere = [
                'id = ?',
                'course_id = ?'
            ];
            $sectionWhere = array_merge($sectionWhere, tableWhereActiveClause($mysqli, 'sections'));
            $section_check = $mysqli->prepare(" 
                SELECT id, department_id
                FROM sections
                WHERE " . implode(' AND ', $sectionWhere) . "
                LIMIT 1
            ");
            if (!$section_check) {
                studentApplicationRedirect('error', 'We could not validate your selected section right now. Please try again.');
            }
            $section_check->bind_param('ii', $section_id, $course_id);
            $section_check->execute();
            $section_row = $section_check->get_result()->fetch_assoc();
            $section_check->close();
            if (!$section_row) {
                studentApplicationRedirect('error', 'The selected section does not match your chosen course.');
            }
            if (!empty($section_row['department_id'])) {
                $department_id_int = (int)$section_row['department_id'];
                $department_id = $department_id_int;
            }
        }
    }

    $sectionCodeSnapshot = '';
    $sectionNameSnapshot = '';
    if ($section_id > 0) {
        $sectionSnapshotStmt = $mysqli->prepare("SELECT code, name FROM sections WHERE id = ? LIMIT 1");
        if ($sectionSnapshotStmt) {
            $sectionSnapshotStmt->bind_param('i', $section_id);
            $sectionSnapshotStmt->execute();
            $sectionSnapshotRow = $sectionSnapshotStmt->get_result()->fetch_assoc();
            $sectionSnapshotStmt->close();
            if ($sectionSnapshotRow) {
                $sectionCodeSnapshot = trim((string)($sectionSnapshotRow['code'] ?? ''));
                $sectionNameSnapshot = trim((string)($sectionSnapshotRow['name'] ?? ''));
            }
        }
    }

    if (!empty($coordinator_id) && $department_id_int > 0) {
        $coordWhere = [
            'id = ?',
            'department_id = ?'
        ];
        $coordWhere = array_merge($coordWhere, tableWhereActiveClause($mysqli, 'coordinators'));
        $coord_check = $mysqli->prepare(" 
            SELECT CONCAT(first_name, ' ', last_name) AS full_name
            FROM coordinators
            WHERE " . implode(' AND ', $coordWhere) . "
            LIMIT 1
        ");
        if (!$coord_check) {
            studentApplicationRedirect('error', 'We could not validate the selected coordinator right now. Please try again.');
        }
        $coord_check->bind_param('ii', $coordinator_id, $department_id_int);
        $coord_check->execute();
        $coord_row = $coord_check->get_result()->fetch_assoc();
        $coord_check->close();
        if (!$coord_row) {
            studentApplicationRedirect('error', 'The selected coordinator is not valid for the chosen department.');
        }
        $coordinator_name = isset($coord_row['full_name']) ? (string)$coord_row['full_name'] : null;
    } elseif (!empty($coordinator_id)) {
        $coordWhere = ['id = ?'];
        $coordWhere = array_merge($coordWhere, tableWhereActiveClause($mysqli, 'coordinators'));
        $coord_check = $mysqli->prepare(" 
            SELECT CONCAT(first_name, ' ', last_name) AS full_name
            FROM coordinators
            WHERE " . implode(' AND ', $coordWhere) . "
            LIMIT 1
        ");
        if (!$coord_check) {
            studentApplicationRedirect('error', 'We could not validate the selected coordinator right now. Please try again.');
        }
        $coord_check->bind_param('i', $coordinator_id);
        $coord_check->execute();
        $coord_row = $coord_check->get_result()->fetch_assoc();
        $coord_check->close();
        if (!$coord_row) {
            studentApplicationRedirect('error', 'The selected coordinator is invalid.');
        }
        $coordinator_name = isset($coord_row['full_name']) ? (string)$coord_row['full_name'] : null;
    }

    if (!empty($supervisor_id) && $department_id_int > 0) {
        $supWhere = [
            'id = ?',
            'department_id = ?'
        ];
        $supWhere = array_merge($supWhere, tableWhereActiveClause($mysqli, 'supervisors'));
        $sup_check = $mysqli->prepare(" 
            SELECT CONCAT(first_name, ' ', last_name) AS full_name
            FROM supervisors
            WHERE " . implode(' AND ', $supWhere) . "
            LIMIT 1
        ");
        if (!$sup_check) {
            studentApplicationRedirect('error', 'We could not validate the selected supervisor right now. Please try again.');
        }
        $sup_check->bind_param('ii', $supervisor_id, $department_id_int);
        $sup_check->execute();
        $sup_row = $sup_check->get_result()->fetch_assoc();
        $sup_check->close();
        if (!$sup_row) {
            studentApplicationRedirect('error', 'The selected supervisor is not valid for the chosen department.');
        }
        $supervisor_name = isset($sup_row['full_name']) ? (string)$sup_row['full_name'] : null;
    } elseif (!empty($supervisor_id)) {
        $supWhere = ['id = ?'];
        $supWhere = array_merge($supWhere, tableWhereActiveClause($mysqli, 'supervisors'));
        $sup_check = $mysqli->prepare(" 
            SELECT CONCAT(first_name, ' ', last_name) AS full_name
            FROM supervisors
            WHERE " . implode(' AND ', $supWhere) . "
            LIMIT 1
        ");
        if (!$sup_check) {
            studentApplicationRedirect('error', 'We could not validate the selected supervisor right now. Please try again.');
        }
        $sup_check->bind_param('i', $supervisor_id);
        $sup_check->execute();
        $sup_row = $sup_check->get_result()->fetch_assoc();
        $sup_check->close();
        if (!$sup_row) {
            studentApplicationRedirect('error', 'The selected supervisor is invalid.');
        }
        $supervisor_name = isset($sup_row['full_name']) ? (string)$sup_row['full_name'] : null;
    }

    // Hash password for staging; user account is created only after approval.
    $pwdHash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
    $user_id = 0;

    $existingUserStmt = $mysqli->prepare("SELECT id, COALESCE(application_status, 'approved') AS application_status FROM users WHERE email = ? LIMIT 1");
    if ($existingUserStmt) {
        $existingUserStmt->bind_param('s', $final_email);
        $existingUserStmt->execute();
        $existingUserRow = $existingUserStmt->get_result()->fetch_assoc();
        $existingUserStmt->close();
        if ($existingUserRow) {
            $existingStatus = strtolower(trim((string)($existingUserRow['application_status'] ?? 'approved')));
            if (in_array($existingStatus, ['pending', 'approved'], true)) {
                studentApplicationRedirect('exists', 'An application or account already exists for that email address.');
            }
        }
    }

    if (!ensureStudentApplicationsTable($mysqli)) {
        studentApplicationRedirect('error', 'Unable to access the student applications table.');
    }

    $stageSql = "
        INSERT INTO student_applications (
            user_id, username, email, password_hash, student_id,
            first_name, middle_name, last_name,
            course_id, department_id, section_id, section_code_snapshot, section_name_snapshot, semester, school_year,
            address, phone, date_of_birth, gender,
            supervisor_id, supervisor_name, coordinator_id, coordinator_name,
            internal_total_hours, external_total_hours, assignment_track,
            emergency_contact, emergency_contact_phone,
            status, submitted_at, reviewed_at, reviewed_by,
            approval_notes, disciplinary_remark, created_student_user_id
        ) VALUES (
            NULLIF(?, 0), ?, ?, ?, ?,
            ?, ?, ?,
            ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
            NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
            NULLIF(?, 0), NULLIF(?, ''), NULLIF(?, 0), NULLIF(?, ''),
            ?, ?, ?,
            NULLIF(?, ''), NULLIF(?, ''),
            'pending', NOW(), NULL, NULL,
            NULL, NULL, NULL
        )
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            password_hash = VALUES(password_hash),
            student_id = VALUES(student_id),
            first_name = VALUES(first_name),
            middle_name = VALUES(middle_name),
            last_name = VALUES(last_name),
            course_id = VALUES(course_id),
            department_id = VALUES(department_id),
            section_id = VALUES(section_id),
            section_code_snapshot = VALUES(section_code_snapshot),
            section_name_snapshot = VALUES(section_name_snapshot),
            semester = VALUES(semester),
            school_year = VALUES(school_year),
            address = VALUES(address),
            phone = VALUES(phone),
            date_of_birth = VALUES(date_of_birth),
            gender = VALUES(gender),
            supervisor_id = VALUES(supervisor_id),
            supervisor_name = VALUES(supervisor_name),
            coordinator_id = VALUES(coordinator_id),
            coordinator_name = VALUES(coordinator_name),
            internal_total_hours = VALUES(internal_total_hours),
            external_total_hours = VALUES(external_total_hours),
            assignment_track = VALUES(assignment_track),
            emergency_contact = VALUES(emergency_contact),
            emergency_contact_phone = VALUES(emergency_contact_phone),
            status = 'pending',
            submitted_at = NOW(),
            reviewed_at = NULL,
            reviewed_by = NULL,
            approval_notes = NULL,
            disciplinary_remark = NULL,
                created_student_user_id = NULL,
                user_id = COALESCE(student_applications.user_id, VALUES(user_id))
    ";

    $stageStmt = $mysqli->prepare($stageSql);
    if (!$stageStmt) {
        studentApplicationRedirect('error', 'Unable to queue the student application for review.');
    }

    $departmentIdForApp = !empty($department_id) ? (int)$department_id : 0;
    $sectionIdForApp = !empty($section_id) ? (int)$section_id : 0;
    $supervisorIdForApp = !empty($supervisor_id) ? (int)$supervisor_id : 0;
    $coordinatorIdForApp = !empty($coordinator_id) ? (int)$coordinator_id : 0;
    $stageStmt->bind_param(
        'isssssssiiissssssssisisiisss',
        $user_id,
        $username,
        $final_email,
        $pwdHash,
        $student_id,
        $first_name,
        $middle_name,
        $last_name,
        $course_id,
        $departmentIdForApp,
        $sectionIdForApp,
        $sectionCodeSnapshot,
        $sectionNameSnapshot,
        $semester,
        $school_year,
        $address,
        $phone,
        $date_of_birth,
        $gender,
        $supervisorIdForApp,
        $supervisor_name,
        $coordinatorIdForApp,
        $coordinator_name,
        $internal_total_hours,
        $external_total_hours,
        $assignment_track,
        $emergency_contact,
        $emergency_contact_phone
    );

    if (!$stageStmt->execute()) {
        $stageStmt->close();
        studentApplicationRedirect('error', 'Unable to queue the student application for review.');
    }
    $stageStmt->close();

    $studentDisplayName = trim($first_name . ' ' . $last_name);
    sendStudentApplicationReceivedEmail($mysqli, $final_email, $studentDisplayName, (string)$student_id, (string)$school_year, (string)$semester);

    studentApplicationRedirect('pending', 'Application received. Please wait for approval from an administrator, coordinator, or supervisor.');
}

if ($role === 'coordinator') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $office_location = getPost('office_location');
    $department_code = getPost('department_code');
    $department_id = resolveDepartmentIdByCode($mysqli, $department_code);
    $account_email = getPost('account_email');

    if (!$department_id) {
        header('Location: auth-register.php?registered=error&msg=' . urlencode('Department code not found. Please create it first in Departments page.'));
        exit;
    }

    $final_email = $account_email ?: $email;
    $coordinator_username = generateUniqueUsername($mysqli, $final_email ?: ($first_name . '.' . $last_name), 'coordinator');
    $createUserErrorCode = null;
    $createUserErrorMessage = null;
    $userId = createUser($mysqli, $coordinator_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'coordinator', $createUserErrorCode, $createUserErrorMessage);
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        if ((int)$createUserErrorCode === 1062) {
            header('Location: auth-register.php?registered=exists&msg=' . urlencode('An account with that email already exists'));
            exit;
        }

        $message = 'Failed to create user account.';
        if (!empty($createUserErrorMessage)) {
            $message .= ' ' . $createUserErrorMessage;
        }
        header('Location: auth-register.php?registered=error&msg=' . urlencode($message));
        exit;
    }

    // Insert into coordinators table (schema-aware profile field and created_at)
    $coordinatorProfileColumn = null;
    if (tableHasColumn($mysqli, 'coordinators', 'office_location')) {
        $coordinatorProfileColumn = 'office_location';
    } elseif (tableHasColumn($mysqli, 'coordinators', 'office')) {
        $coordinatorProfileColumn = 'office';
    } elseif (tableHasColumn($mysqli, 'coordinators', 'specialization')) {
        $coordinatorProfileColumn = 'specialization';
    }

    $coordinatorHasCreatedAt = tableHasColumn($mysqli, 'coordinators', 'created_at');
    if ($coordinatorProfileColumn !== null && $coordinatorHasCreatedAt) {
        $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, {$coordinatorProfileColumn}, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, NOW())");
    } elseif ($coordinatorProfileColumn !== null) {
        $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, {$coordinatorProfileColumn}, is_active) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1)");
    } elseif ($coordinatorHasCreatedAt) {
        $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, 1, NOW())");
    } else {
        $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, is_active) VALUES (?, ?, ?, NULL, ?, ?, ?, 1)");
    }

    if ($stmt) {
        if ($coordinatorProfileColumn !== null) {
            $stmt->bind_param('isssiis', $userId, $first_name, $last_name, $final_email, $phone, $department_id, $office_location);
        } else {
            $stmt->bind_param('isssii', $userId, $first_name, $last_name, $final_email, $phone, $department_id);
        }

        try {
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                header('Location: auth-register.php?registered=error&msg=' . urlencode($error));
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            if ((int)$e->getCode() === 1062) {
                header('Location: auth-register.php?registered=exists&msg=' . urlencode('Coordinator record already exists for this account.'));
                exit;
            }
            header('Location: auth-register.php?registered=error&msg=' . urlencode($e->getMessage()));
            exit;
        }
        $stmt->close();
    }

    header('Location: auth-register.php?registered=coordinator');
    exit;
}

if ($role === 'supervisor') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $office = getPost('office');
    $username = getPost('username');
    $account_email = getPost('account_email');
    $officeOrSpecialization = $office ?: '';

    $final_email = $account_email ?: $email;
    $supervisor_username = generateUniqueUsername($mysqli, $final_email ?: ($first_name . '.' . $last_name), 'supervisor');
    $createUserErrorCode = null;
    $createUserErrorMessage = null;
    $userId = createUser($mysqli, $supervisor_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'supervisor', $createUserErrorCode, $createUserErrorMessage);
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        if ((int)$createUserErrorCode === 1062) {
            header('Location: auth-register.php?registered=exists&msg=' . urlencode('An account with that email already exists'));
            exit;
        }

        $message = 'Failed to create user account.';
        if (!empty($createUserErrorMessage)) {
            $message .= ' ' . $createUserErrorMessage;
        }
        header('Location: auth-register.php?registered=error&msg=' . urlencode($message));
        exit;
    }

    // Insert into supervisors table (schema-aware profile field and created_at)
    $supervisorProfileColumn = null;
    if (tableHasColumn($mysqli, 'supervisors', 'office')) {
        $supervisorProfileColumn = 'office';
    } elseif (tableHasColumn($mysqli, 'supervisors', 'specialization')) {
        $supervisorProfileColumn = 'specialization';
    } elseif (tableHasColumn($mysqli, 'supervisors', 'office_location')) {
        $supervisorProfileColumn = 'office_location';
    }

    $supervisorHasCreatedAt = tableHasColumn($mysqli, 'supervisors', 'created_at');
    if ($supervisorProfileColumn !== null && $supervisorHasCreatedAt) {
        $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, {$supervisorProfileColumn}, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?, 1, NOW())");
    } elseif ($supervisorProfileColumn !== null) {
        $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, {$supervisorProfileColumn}, is_active) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?, 1)");
    } elseif ($supervisorHasCreatedAt) {
        $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, NULL, 1, NOW())");
    } else {
        $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, is_active) VALUES (?, ?, ?, NULL, ?, ?, NULL, 1)");
    }

    if ($stmt) {
        if ($supervisorProfileColumn !== null) {
            $stmt->bind_param('isssss', $userId, $first_name, $last_name, $final_email, $phone, $officeOrSpecialization);
        } else {
            $stmt->bind_param('issss', $userId, $first_name, $last_name, $final_email, $phone);
        }

        try {
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                header('Location: auth-register.php?registered=error&msg=' . urlencode($error));
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            if ((int)$e->getCode() === 1062) {
                header('Location: auth-register.php?registered=exists&msg=' . urlencode('Supervisor record already exists for this account.'));
                exit;
            }
            header('Location: auth-register.php?registered=error&msg=' . urlencode($e->getMessage()));
            exit;
        }
        $stmt->close();
    }

    header('Location: auth-register.php?registered=supervisor');
    exit;
}

if ($role === 'admin') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $account_email = getPost('account_email');
    $admin_level = getPost('admin_level');
    $department_code = getPost('department_code');
    $department_id = resolveDepartmentIdByCode($mysqli, $department_code);
    $admin_position = getPost('admin_position');
    $middle_name = getPost('middle_name') ?: '';

    if (!$department_id) {
        header('Location: auth-register.php?registered=error&msg=' . urlencode('Department code not found. Please create it first in Departments page.'));
        exit;
    }

    $final_email = $account_email ?: $email;
    $admin_username = generateUniqueUsername($mysqli, $final_email ?: ($first_name . '.' . $last_name), 'admin');
    $createUserErrorCode = null;
    $createUserErrorMessage = null;
    $userId = createUser($mysqli, $admin_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'admin', $createUserErrorCode, $createUserErrorMessage);
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        if ((int)$createUserErrorCode === 1062) {
            header('Location: auth-register.php?registered=exists&msg=' . urlencode('An account with that email already exists'));
            exit;
        }

        $message = 'Failed to create user account.';
        if (!empty($createUserErrorMessage)) {
            $message .= ' ' . $createUserErrorMessage;
        }
        header('Location: auth-register.php?registered=error&msg=' . urlencode($message));
        exit;
    }

    // Save admin profile in `admin` table (from admin.sql), while auth stays in `users` table.
    $admin_pwd_hash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
    $institution_email = $email ?: $final_email;
    $admin_phone = $phone ?: '';
    $admin_level = $admin_level ?: 'admin';
    $admin_position = $admin_position ?: 'Admin';

    $stmt_admin = $mysqli->prepare("
        INSERT INTO admin (
            user_id, first_name, middle_name, institution_email_address, phone_number,
            admin_level, department_id, admin_position, username, password, email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt_admin) {
        $stmt_admin->bind_param(
            'isssssissss',
            $userId,
            $first_name,
            $middle_name,
            $institution_email,
            $admin_phone,
            $admin_level,
            $department_id,
            $admin_position,
            $admin_username,
            $admin_pwd_hash,
            $final_email
        );
        if (!$stmt_admin->execute()) {
            $error = $stmt_admin->error;
            $stmt_admin->close();
            $cleanup = $mysqli->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
            if ($cleanup) {
                $cleanup->bind_param('i', $userId);
                $cleanup->execute();
                $cleanup->close();
            }
            header('Location: auth-register.php?registered=error&msg=' . urlencode('Admin record error: ' . $error));
            exit;
        }
        $stmt_admin->close();
    } else {
        $cleanup = $mysqli->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
        if ($cleanup) {
            $cleanup->bind_param('i', $userId);
            $cleanup->execute();
            $cleanup->close();
        }
        header('Location: auth-register.php?registered=error&msg=' . urlencode('Admin table statement error: ' . $mysqli->error));
        exit;
    }

    header('Location: auth-register.php?registered=admin');
    exit;
}

// fallback
header('Location: register_submit.php');
exit;


