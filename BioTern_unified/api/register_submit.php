<?php
require_once dirname(__DIR__) . '/config/db.php';
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

function studentApplicationRedirect(string $status, string $message): void {
    header('Location: auth-register-creative.php?registered=' . rawurlencode($status) . '&msg=' . urlencode($message));
    exit;
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
function createUser($mysqli, $username, $email, $password, $role) {
    ensureUsersTable($mysqli);

    // check users table
    $res = $mysqli->query("SHOW TABLES LIKE 'users'");
    $userId = null;
    if ($res && $res->num_rows > 0) {
        $pwdHash = password_hash($password, PASSWORD_DEFAULT);
        $hasIsActive = tableHasColumn($mysqli, 'users', 'is_active');
        $hasAppStatus = tableHasColumn($mysqli, 'users', 'application_status');
        $hasSubmittedAt = tableHasColumn($mysqli, 'users', 'application_submitted_at');
        if ($hasIsActive && $hasAppStatus && $hasSubmittedAt) {
            $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, application_status, application_submitted_at, created_at) VALUES (?, ?, ?, ?, ?, 1, 'approved', NOW(), NOW())");
        } elseif ($hasIsActive && $hasAppStatus) {
            $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, application_status, created_at) VALUES (?, ?, ?, ?, ?, 1, 'approved', NOW())");
        } elseif ($hasIsActive) {
            $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        } else {
            $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        }
        if ($stmt) {
            $name = $username;
            $stmt->bind_param('sssss', $name, $username, $email, $pwdHash, $role);
            try {
                $stmt->execute();
                $userId = $mysqli->insert_id;
            } catch (mysqli_sql_exception $e) {
                $code = $e->getCode();
                $stmt->close();
                // Duplicate entry (1062) or other SQL error - return null so callers can handle it
                return null;
            }
            $stmt->close();
        }
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
    $date_of_birth = getPost('date_of_birth');
    $gender = getPost('gender');
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

    // Hash password
    $pwdHash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);

    // Create a users record first (required for FK constraint)
    $full_name = trim($first_name . ' ' . $last_name);
    $user_id = createUser(
        $mysqli,
        $username,
        $final_email,
        $password ?: bin2hex(random_bytes(4)),
        'student'
    );
    if ($user_id) {
        $updates = [];
        if (tableHasColumn($mysqli, 'users', 'is_active')) {
            $updates[] = 'is_active = 0';
        }
        if (tableHasColumn($mysqli, 'users', 'application_status')) {
            $updates[] = "application_status = 'pending'";
        }
        if (tableHasColumn($mysqli, 'users', 'application_submitted_at')) {
            $updates[] = 'application_submitted_at = NOW()';
        }
        if ($updates !== []) {
            $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1';
            $u = $mysqli->prepare($sql);
            if ($u) {
                $u->bind_param('i', $user_id);
                $u->execute();
                $u->close();
            }
        }
    }

    if (!$user_id) {
        studentApplicationRedirect('exists', 'An application or account already exists for that email address.');
    }

    // Validate that user_id was created successfully
    if (!$user_id) {
        studentApplicationRedirect('error', 'We could not finish your application submission. Please try again.');
    }

    // Now insert into students table using the user_id
    // Note: If emergency_contact_phone column doesn't exist, store phone in emergency_contact or add the column
    $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, password, email, department_id, section_id, semester, address, phone, date_of_birth, gender, supervisor_id, supervisor_name, coordinator_id, coordinator_name, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining, assignment_track, emergency_contact, school_year, application_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, ''), ?, ?, ?, ?, NULLIF(?, 0), ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    
    if (!$stmt) {
        studentApplicationRedirect('error', 'We could not prepare your student application record. Please try again.');
    }
    
    // Combine emergency contact info for storage (Name: Phone format for display)
    $emergency_contact_full = $emergency_contact;
    if ($emergency_contact_phone) {
        $emergency_contact_full = $emergency_contact . ' (' . $emergency_contact_phone . ')';
    }
    
    // types: user_id(i), course_id(i), student_id(s), first_name(s), last_name(s), middle_name(s),
    // password(s), email(s), department_id(s), section_id(i), semester(s), address(s), phone(s),
    // date_of_birth(s), gender(s), supervisor_id(i), supervisor_name(s), coordinator_id(i),
    // coordinator_name(s), internal_total_hours(i), internal_total_hours_remaining(i),
    // external_total_hours(i), external_total_hours_remaining(i), assignment_track(s), emergency_contact(s), school_year(s)
    $department_id_for_student = $department_id !== null ? (string)$department_id : null;
    $section_id_for_insert = !empty($section_id) ? (int)$section_id : 0;
    $supervisor_id_for_insert = !empty($supervisor_id) ? (int)$supervisor_id : 0;
    $coordinator_id_for_insert = !empty($coordinator_id) ? (int)$coordinator_id : 0;

    $stmt->bind_param(
        'iisssssssisssssisisiiiisss',
        $user_id,
        $course_id,
        $student_id,
        $first_name,
        $last_name,
        $middle_name,
        $pwdHash,
        $final_email,
        $department_id_for_student,
        $section_id_for_insert,
        $semester,
        $address,
        $phone,
        $date_of_birth,
        $gender,
        $supervisor_id_for_insert,
        $supervisor_name,
        $coordinator_id_for_insert,
        $coordinator_name,
        $internal_total_hours,
        $internal_total_hours_remaining,
        $external_total_hours,
        $external_total_hours_remaining,
        $assignment_track,
        $emergency_contact_full,
        $school_year
    );
    
    try {
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            studentApplicationRedirect('error', 'Your account was created, but the student application record could not be saved. Please contact the administrator.');
        }
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        studentApplicationRedirect('error', 'Your account was created, but the student application record could not be completed. Please contact the administrator.');
    }
    
    $new_student_id = (int)$mysqli->insert_id;
    $stmt->close();

    if (tableHasColumn($mysqli, 'students', 'status')) {
        $statusStmt = $mysqli->prepare("UPDATE students SET status = 0 WHERE id = ? LIMIT 1");
        if ($statusStmt) {
            $statusStmt->bind_param('i', $new_student_id);
            $statusStmt->execute();
            $statusStmt->close();
        }
    }

    // Internship record is created only after the application is approved.

    studentApplicationRedirect('pending', 'Application sent successfully. Please wait for approval from the admin, coordinator, or supervisor.');
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
    $account_email = getPost('account_email');

    if (!$department_id) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Department code not found. Please create it first in Departments page.'));
        exit;
    }

    $final_email = $account_email ?: $email;
    $coordinator_username = generateUniqueUsername($mysqli, $final_email ?: ($first_name . '.' . $last_name), 'coordinator');
    $userId = createUser($mysqli, $coordinator_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'coordinator');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email already exists'));
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
    $office = getPost('office');
    $username = getPost('username');
    $account_email = getPost('account_email');

    $final_email = $account_email ?: $email;
    $supervisor_username = generateUniqueUsername($mysqli, $final_email ?: ($first_name . '.' . $last_name), 'supervisor');
    $userId = createUser($mysqli, $supervisor_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'supervisor');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email already exists'));
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
    $admin_username = generateUniqueUsername($mysqli, $final_email ?: ($first_name . '.' . $last_name), 'admin');
    $userId = createUser($mysqli, $admin_username, $final_email, $password ?: bin2hex(random_bytes(4)), 'admin');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email already exists'));
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


