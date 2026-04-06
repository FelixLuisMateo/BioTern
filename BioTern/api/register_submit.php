<?php
// Simple registration handler for demo purposes.
// IMPORTANT: Review and secure before using in production.

require_once dirname(__DIR__) . '/config/db.php';

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

function getPost($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
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

    // Use information_schema so table and column names can be bound safely.
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

function tableColumnIsAutoIncrement($mysqli, $tableName, $columnName) {
    $table = trim((string)$tableName);
    $column = trim((string)$columnName);
    if ($table === '' || $column === '') {
        return false;
    }

    $sql = "
        SELECT EXTRA
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
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    return stripos((string)($row['EXTRA'] ?? ''), 'auto_increment') !== false;
}

function nextTableId($mysqli, $tableName) {
    $table = trim((string)$tableName);
    if ($table === '') {
        return 1;
    }
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return 1;
    }

    $sql = "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM {$safeTable}";
    $res = $mysqli->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        $res->close();
        if ($row && isset($row['next_id'])) {
            return (int)$row['next_id'];
        }
    }
    return 1;
}

function bindDynamicParams($stmt, $types, &$values) {
    if (!($stmt instanceof mysqli_stmt)) {
        return false;
    }
    if (!is_array($values) || $types === '') {
        return true;
    }

    $bind = [$types];
    foreach (array_keys($values) as $idx) {
        $bind[] = &$values[$idx];
    }

    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function ensureSectionId($mysqli, $sectionValue, $courseId, $departmentId) {
    $sectionRaw = trim((string)$sectionValue);
    $courseId = (int)$courseId;
    $departmentId = (int)$departmentId;
    if ($sectionRaw === '' || $courseId <= 0 || $departmentId <= 0) {
        return 0;
    }

    $existing = resolveSectionId($mysqli, $sectionRaw, $courseId);
    if ($existing > 0) {
        return $existing;
    }

    // Some forms generate fallback section codes (e.g. ACT-2A). Create them on-demand.
    $escCode = $mysqli->real_escape_string($sectionRaw);
    $escName = $mysqli->real_escape_string($sectionRaw);

    $columns = ['course_id', 'department_id', 'code', 'name'];
    $values = [(string)$courseId, (string)$departmentId, "'" . $escCode . "'", "'" . $escName . "'"];

    if (tableHasColumn($mysqli, 'sections', 'is_active')) {
        $columns[] = 'is_active';
        $values[] = '1';
    }
    if (tableHasColumn($mysqli, 'sections', 'created_at')) {
        $columns[] = 'created_at';
        $values[] = 'NOW()';
    }
    if (tableHasColumn($mysqli, 'sections', 'updated_at')) {
        $columns[] = 'updated_at';
        $values[] = 'NOW()';
    }

    $insertSql = "INSERT INTO sections (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    $ok = $mysqli->query($insertSql);
    if ($ok) {
        return (int)$mysqli->insert_id;
    }

    // If insert failed due to duplicate or constraints, try resolving again.
    return resolveSectionId($mysqli, $sectionRaw, $courseId);
}

$role = getPost('role');
if (!$role) {
    header('Location: register_submit.php');
    exit;
}

// Create a user record if `users` table exists
function createUser($mysqli, $username, $email, $password, $role, $displayName = null, &$errorCode = null, &$errorMessage = null) {
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

        $manualId = tableHasColumn($mysqli, 'users', 'id') && !tableColumnIsAutoIncrement($mysqli, 'users', 'id');
        $hasCreatedAt = tableHasColumn($mysqli, 'users', 'created_at');
        $name = trim((string)$displayName);
        if ($name === '') {
            $name = (string)$username;
        }

        $columns = [];
        $values = [];
        $types = '';
        $placeholders = [];

        if ($manualId) {
            $columns[] = 'id';
            $values[] = nextTableId($mysqli, 'users');
            $types .= 'i';
            $placeholders[] = '?';
        }

        $columns[] = 'name';
        $values[] = $name;
        $types .= 's';
        $placeholders[] = '?';

        $columns[] = 'username';
        $values[] = (string)$username;
        $types .= 's';
        $placeholders[] = '?';

        $columns[] = 'email';
        $values[] = (string)$email;
        $types .= 's';
        $placeholders[] = '?';

        $columns[] = 'password';
        $values[] = $pwdHash;
        $types .= 's';
        $placeholders[] = '?';

        $columns[] = 'role';
        $values[] = (string)$role;
        $types .= 's';
        $placeholders[] = '?';

        if ($hasIsActive) {
            $columns[] = 'is_active';
            $values[] = 1;
            $types .= 'i';
            $placeholders[] = '?';
        }

        if ($hasAppStatus) {
            $columns[] = 'application_status';
            $values[] = 'approved';
            $types .= 's';
            $placeholders[] = '?';
        }

        if ($hasSubmittedAt) {
            $columns[] = 'application_submitted_at';
            $values[] = date('Y-m-d H:i:s');
            $types .= 's';
            $placeholders[] = '?';
        }

        if ($hasCreatedAt) {
            $columns[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
            $types .= 's';
            $placeholders[] = '?';
        }

        $sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            bindDynamicParams($stmt, $types, $values);
            try {
                $stmt->execute();
                if ($manualId) {
                    $userId = (int)$values[0];
                } else {
                    $userId = (int)$mysqli->insert_id;
                }
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
    // Keep student hour/assignment fields available even on older databases.
    $mysqli->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS department_id VARCHAR(255) NULL");
    $mysqli->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours_remaining INT(11) DEFAULT NULL");
    $mysqli->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours INT(11) DEFAULT NULL");
    $mysqli->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours_remaining INT(11) DEFAULT NULL");
    $mysqli->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'");

    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
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
    $username = getPost('username');
    $account_email = getPost('account_email');
    
    // New fields - now accepting IDs
    $phone = getPost('phone');
    $date_of_birth = getPost('date_of_birth');
    $gender = getPost('gender');
    $supervisor_id_raw = getPost('supervisor_id');
    $coordinator_id_raw = getPost('coordinator_id');
    $supervisor_id = (is_string($supervisor_id_raw) && ctype_digit($supervisor_id_raw)) ? (int)$supervisor_id_raw : 0;
    $coordinator_id = (is_string($coordinator_id_raw) && ctype_digit($coordinator_id_raw)) ? (int)$coordinator_id_raw : 0;
    $internal_total_hours = getPost('internal_total_hours');
    if ($internal_total_hours === null || $internal_total_hours === '') {
        // Backward compatibility for older forms still posting total_hours.
        $internal_total_hours = getPost('total_hours');
    }
    $external_total_hours = getPost('external_total_hours');
    $finished_internal = strtolower((string)(getPost('finished_internal') ?: 'no'));
    $emergency_contact = getPost('emergency_contact');
    $emergency_contact_phone = getPost('emergency_contact_phone');

    // Use account_email if provided, otherwise use email
    $final_email = $account_email ?: $email;
    if ($final_email === null || $final_email === '' || !filter_var($final_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Please provide a valid account email address.'));
        exit;
    }

    if ($username === null || trim((string)$username) === '') {
        $username = generateUniqueUsername($mysqli, $student_id ?: $final_email, 'student');
    }

    $course_id = (int)$course_id;
    $department_id = 0;
    if ($department_id_raw !== null && $department_id_raw !== '' && ctype_digit((string)$department_id_raw)) {
        $department_id = (int)$department_id_raw;
    } else {
        $resolved_department = resolveDepartmentIdByCode($mysqli, $department_code);
        if ($resolved_department !== null) {
            $department_id = (int)$resolved_department;
        }
    }
    $section_id = resolveSectionId($mysqli, $section, $course_id);
    if ($section_id <= 0 && $department_id > 0) {
        $section_id = ensureSectionId($mysqli, $section, $course_id, (int)$department_id);
    }
    if ($section_id > 0 && $department_id <= 0) {
        $sectionDeptWhere = [
            'id = ?',
            'course_id = ?'
        ];
        $sectionDeptWhere = array_merge($sectionDeptWhere, tableWhereActiveClause($mysqli, 'sections'));
        $section_department_stmt = $mysqli->prepare(" 
            SELECT department_id
            FROM sections
            WHERE " . implode(' AND ', $sectionDeptWhere) . "
            LIMIT 1
        ");
        if ($section_department_stmt) {
            $section_department_stmt->bind_param('ii', $section_id, $course_id);
            $section_department_stmt->execute();
            $section_department_row = $section_department_stmt->get_result()->fetch_assoc();
            $section_department_stmt->close();
            if ($section_department_row && isset($section_department_row['department_id'])) {
                $department_id = (int)$section_department_row['department_id'];
            }
        }
    }
    $internal_total_hours = $internal_total_hours ? (int)$internal_total_hours : 0;
    $external_total_hours = $external_total_hours ? (int)$external_total_hours : 0;
    if ($internal_total_hours < 0) $internal_total_hours = 0;
    if ($external_total_hours < 0) $external_total_hours = 0;
    $finished_internal_yes = in_array($finished_internal, ['yes', '1', 'true'], true);
    $internal_total_hours_remaining = $finished_internal_yes ? 0 : $internal_total_hours;
    $external_total_hours_remaining = $finished_internal_yes ? $external_total_hours : 0;
    $assignment_track = $finished_internal_yes ? 'external' : 'internal';
    $coordinator_name = null;
    $supervisor_name = null;

    // Strict server-side integrity checks for tampered submissions.
    if ($course_id <= 0 || $section_id <= 0) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Invalid academic assignment selection. Please choose a valid course and section.'));
        exit;
    }
    if ($department_id <= 0) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Unable to determine department for the selected section.'));
        exit;
    }

    $courseWhere = ['id = ?'];
    $courseWhere = array_merge($courseWhere, tableWhereActiveClause($mysqli, 'courses'));
    $course_check = $mysqli->prepare("SELECT id FROM courses WHERE " . implode(' AND ', $courseWhere) . " LIMIT 1");
    if (!$course_check) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Unable to validate selected course.'));
        exit;
    }
    $course_check->bind_param('i', $course_id);
    $course_check->execute();
    $course_ok = $course_check->get_result()->fetch_assoc();
    $course_check->close();
    if (!$course_ok) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Selected course is invalid.'));
        exit;
    }

    $department_id_int = (int)$department_id;
    $deptWhere = ['id = ?'];
    $deptWhere = array_merge($deptWhere, tableWhereActiveClause($mysqli, 'departments'));
    $dept_check = $mysqli->prepare("SELECT id FROM departments WHERE " . implode(' AND ', $deptWhere) . " LIMIT 1");
    if (!$dept_check) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Unable to validate selected department.'));
        exit;
    }
    $dept_check->bind_param('i', $department_id_int);
    $dept_check->execute();
    $dept_ok = $dept_check->get_result()->fetch_assoc();
    $dept_check->close();
    if (!$dept_ok) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Selected department is invalid.'));
        exit;
    }

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
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Unable to validate selected section.'));
        exit;
    }
    $section_check->bind_param('iii', $section_id, $course_id, $department_id_int);
    $section_check->execute();
    $section_ok = $section_check->get_result()->fetch_assoc();
    $section_check->close();
    if (!$section_ok) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Selected section does not belong to the selected course and department.'));
        exit;
    }

    if ($coordinator_id > 0) {
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
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Unable to validate selected coordinator.'));
            exit;
        }
        $coord_check->bind_param('ii', $coordinator_id, $department_id_int);
        $coord_check->execute();
        $coord_row = $coord_check->get_result()->fetch_assoc();
        $coord_check->close();
        if (!$coord_row) {
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Selected coordinator is not valid for the chosen department.'));
            exit;
        }
        $coordinator_name = isset($coord_row['full_name']) ? (string)$coord_row['full_name'] : null;
    }

    if ($supervisor_id > 0) {
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
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Unable to validate selected supervisor.'));
            exit;
        }
        $sup_check->bind_param('ii', $supervisor_id, $department_id_int);
        $sup_check->execute();
        $sup_row = $sup_check->get_result()->fetch_assoc();
        $sup_check->close();
        if (!$sup_row) {
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Selected supervisor is not valid for the chosen department.'));
            exit;
        }
        $supervisor_name = isset($sup_row['full_name']) ? (string)$sup_row['full_name'] : null;
    }

    // Ensure username is not empty
    if (!$username) {
        $username = $first_name . '.' . $last_name;
    }

    // Hash password (also used in students.password legacy field)
    $pwdHash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);

    // Create a users record first (required for FK constraint)
    $full_name = trim($first_name . ' ' . $last_name);
    $userCreateErrorCode = null;
    $userCreateErrorMessage = null;
    $user_id = createUser(
        $mysqli,
        $username,
        $final_email,
        $password ?: bin2hex(random_bytes(4)),
        'student',
        $full_name,
        $userCreateErrorCode,
        $userCreateErrorMessage
    );
    if (!$user_id) {
        if ((int)$userCreateErrorCode === 1062 || stripos((string)$userCreateErrorMessage, 'Duplicate entry') !== false) {
            header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
            exit;
        }
        $fallbackMessage = $userCreateErrorMessage ? $userCreateErrorMessage : 'Failed to create user account';
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($fallbackMessage));
        exit;
    }

    // Now insert into students table using the user_id (schema-aware for unified DB variants).
    $studentColumns = [
        'user_id',
        'course_id',
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'username',
        'password',
        'email',
        'department_id',
        'section_id',
        'address',
        'phone',
        'date_of_birth',
        'gender',
        'internal_total_hours',
        'internal_total_hours_remaining',
        'external_total_hours',
        'external_total_hours_remaining',
        'assignment_track',
        'emergency_contact'
    ];
    $studentTypes = 'iissssssssissssiiiiss';
    $department_id_for_student = $department_id !== null ? (string)$department_id : '';
    $studentValues = [
        $user_id,
        $course_id,
        $student_id,
        $first_name,
        $last_name,
        $middle_name,
        $username,
        $pwdHash,
        $final_email,
        $department_id_for_student,
        $section_id,
        $address,
        $phone,
        $date_of_birth,
        $gender,
        $internal_total_hours,
        $internal_total_hours_remaining,
        $external_total_hours,
        $external_total_hours_remaining,
        $assignment_track,
        $emergency_contact
    ];

    if ($supervisor_id > 0) {
        $studentColumns[] = 'supervisor_id';
        $studentTypes .= 'i';
        $studentValues[] = $supervisor_id;
        if ($supervisor_name !== null && $supervisor_name !== '') {
            $studentColumns[] = 'supervisor_name';
            $studentTypes .= 's';
            $studentValues[] = $supervisor_name;
        }
    }

    if ($coordinator_id > 0) {
        $studentColumns[] = 'coordinator_id';
        $studentTypes .= 'i';
        $studentValues[] = $coordinator_id;
        if ($coordinator_name !== null && $coordinator_name !== '') {
            $studentColumns[] = 'coordinator_name';
            $studentTypes .= 's';
            $studentValues[] = $coordinator_name;
        }
    }

    // Unified schema: students.bio can be required without default.
    if (tableHasColumn($mysqli, 'students', 'bio')) {
        $studentColumns[] = 'bio';
        $studentTypes .= 's';
        $studentValues[] = '';
    }

    if (tableHasColumn($mysqli, 'students', 'emergency_contact_phone')) {
        $studentColumns[] = 'emergency_contact_phone';
        $studentTypes .= 's';
        $studentValues[] = (string)$emergency_contact_phone;
    }

    $studentPlaceholders = array_fill(0, count($studentColumns), '?');
    if (tableHasColumn($mysqli, 'students', 'created_at')) {
        $studentColumns[] = 'created_at';
        $studentPlaceholders[] = 'NOW()';
    }

    $studentSql = "INSERT INTO students (" . implode(', ', $studentColumns) . ") VALUES (" . implode(', ', $studentPlaceholders) . ")";
    $stmt = $mysqli->prepare($studentSql);
    if (!$stmt) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Database statement error: ' . $mysqli->error));
        exit;
    }

    bindDynamicParams($stmt, $studentTypes, $studentValues);
    
    try {
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Student record error: ' . $error));
            exit;
        }
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Student record error: ' . $e->getMessage()));
        exit;
    }
    
    $new_student_id = (int)$mysqli->insert_id;
    $stmt->close();

    // Best-effort internship row creation so coordinator/supervisor/department linkage is complete.
    if ($new_student_id > 0 && $course_id > 0 && $department_id > 0 && $coordinator_id > 0 && $supervisor_id > 0) {
        $intern_coordinator_user_id = null;
        $intern_supervisor_user_id = null;

        $map_coord = $mysqli->prepare("SELECT user_id FROM coordinators WHERE id = ? LIMIT 1");
        if ($map_coord) {
            $map_coord->bind_param('i', $coordinator_id);
            $map_coord->execute();
            $coord_row = $map_coord->get_result()->fetch_assoc();
            $map_coord->close();
            if ($coord_row && !empty($coord_row['user_id'])) {
                $intern_coordinator_user_id = (int)$coord_row['user_id'];
            }
        }

        $map_sup = $mysqli->prepare("SELECT user_id FROM supervisors WHERE id = ? LIMIT 1");
        if ($map_sup) {
            $map_sup->bind_param('i', $supervisor_id);
            $map_sup->execute();
            $sup_row = $map_sup->get_result()->fetch_assoc();
            $map_sup->close();
            if ($sup_row && !empty($sup_row['user_id'])) {
                $intern_supervisor_user_id = (int)$sup_row['user_id'];
            }
        }

        if ($intern_coordinator_user_id !== null && $intern_supervisor_user_id !== null) {
            $today = date('Y-m-d');
            $year = (int)date('Y');
            $school_year = $year . '-' . ($year + 1);
            $type = $assignment_track === 'external' ? 'external' : 'internal';
            $required_hours = $type === 'external' ? max(0, $external_total_hours) : max(0, $internal_total_hours);
            $rendered_hours = 0;
            $completion_pct = 0;

            $insert_intern = $mysqli->prepare("
                INSERT INTO internships
                (student_id, course_id, department_id, coordinator_id, supervisor_id, type, start_date, status, school_year, required_hours, rendered_hours, completion_percentage, created_at, updated_at)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, 'ongoing', ?, ?, ?, ?, NOW(), NOW())
            ");
            if ($insert_intern) {
                $insert_intern->bind_param(
                    'iiiiisssiid',
                    $new_student_id,
                    $course_id,
                    $department_id,
                    $intern_coordinator_user_id,
                    $intern_supervisor_user_id,
                    $type,
                    $today,
                    $school_year,
                    $required_hours,
                    $rendered_hours,
                    $completion_pct
                );
                $insert_intern->execute();
                $insert_intern->close();
            }
        }
    }

    header('Location: auth-register-creative.php?registered=student');
    exit;
}

if ($role === 'coordinator') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $office_location = getPost('office_location');
    $department_code = getPost('department_code');
    $department_id = resolveDepartmentIdByCode($mysqli, $department_code);
    $username = getPost('username');
    $account_email = getPost('account_email');

    if (!$department_id) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Department code not found. Please create it first in Departments page.'));
        exit;
    }

    $final_email = $account_email ?: $email;
    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $final_email, $password ?: bin2hex(random_bytes(4)), 'coordinator');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
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
                header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($error));
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            if ((int)$e->getCode() === 1062) {
                header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('Coordinator record already exists for this account.'));
                exit;
            }
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($e->getMessage()));
            exit;
        }
        $stmt->close();
    }

    header('Location: auth-register-creative.php?registered=coordinator');
    exit;
}

if ($role === 'supervisor') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $officeOrSpecialization = getPost('specialization');
    if ($officeOrSpecialization === null || $officeOrSpecialization === '') {
        $officeOrSpecialization = getPost('office');
    }
    if ($officeOrSpecialization === null || $officeOrSpecialization === '') {
        $officeOrSpecialization = getPost('office_location');
    }
    $username = getPost('username');
    $account_email = getPost('account_email');

    $final_email = $account_email ?: $email;
    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $final_email, $password ?: bin2hex(random_bytes(4)), 'supervisor');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
        exit;
    }

    // Insert into supervisors table (schema-aware profile field and created_at)
    $supervisorProfileColumn = null;
    if (tableHasColumn($mysqli, 'supervisors', 'specialization')) {
        $supervisorProfileColumn = 'specialization';
    } elseif (tableHasColumn($mysqli, 'supervisors', 'office')) {
        $supervisorProfileColumn = 'office';
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
                header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($error));
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            if ((int)$e->getCode() === 1062) {
                header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('Supervisor record already exists for this account.'));
                exit;
            }
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($e->getMessage()));
            exit;
        }
        $stmt->close();
    }

    header('Location: auth-register-creative.php?registered=supervisor');
    exit;
}

if ($role === 'admin') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $username = getPost('username');
    $account_email = getPost('account_email');
    $admin_level = getPost('admin_level');
    $department_code = getPost('department_code');
    $department_id = resolveDepartmentIdByCode($mysqli, $department_code);
    $admin_position = getPost('admin_position');
    $middle_name = getPost('middle_name') ?: '';

    if (!$department_id) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Department code not found. Please create it first in Departments page.'));
        exit;
    }

    $final_email = $account_email ?: $email;
    $admin_username = $username ?: ($first_name . ' ' . $last_name);
    $userId = createUser($mysqli, $admin_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'admin');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
        exit;
    }

    // Save admin profile in `admin` table (from admin.sql), while auth stays in `users` table.
    $admin_pwd_hash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
    $institution_email = $email ?: $final_email;
    $admin_phone = $phone ?: '';
    $admin_level = $admin_level ?: 'admin';
    $admin_position = $admin_position ?: 'Admin';

    $next_admin_id = 1;
    $next_id_res = $mysqli->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM admin");
    if ($next_id_res) {
        $next_id_row = $next_id_res->fetch_assoc();
        if ($next_id_row && isset($next_id_row['next_id'])) {
            $next_admin_id = (int)$next_id_row['next_id'];
        }
        $next_id_res->close();
    }

    $stmt_admin = $mysqli->prepare("
        INSERT INTO admin (
            id, user_id, first_name, middle_name, institution_email_address, phone_number,
            admin_level, department_id, admin_position, username, password, email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt_admin) {
        $stmt_admin->bind_param(
            'iisssssissss',
            $next_admin_id,
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
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Admin record error: ' . $error));
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
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Admin table statement error: ' . $mysqli->error));
        exit;
    }

    header('Location: auth-register-creative.php?registered=admin');
    exit;
}

// fallback
header('Location: register_submit.php');
exit;


