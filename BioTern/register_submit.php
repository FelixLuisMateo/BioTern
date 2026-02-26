<?php
// Simple registration handler for demo purposes.
// IMPORTANT: Review and secure before using in production.

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'biotern_db';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
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
    $stmt = $mysqli->prepare("SELECT id FROM departments WHERE code = ? AND deleted_at IS NULL LIMIT 1");
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
    if ($courseId > 0) {
        $stmt = $mysqli->prepare("
            SELECT id
            FROM sections
            WHERE deleted_at IS NULL
              AND course_id = ?
              AND (code = ? OR name = ?)
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

    $stmt = $mysqli->prepare("
        SELECT id
        FROM sections
        WHERE deleted_at IS NULL
          AND (code = ? OR name = ?)
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

$role = getPost('role');
if (!$role) {
    header('Location: register_submit.php');
    exit;
}

// Create a user record if `users` table exists
function createUser($mysqli, $username, $email, $password, $role) {
    // check users table
    $res = $mysqli->query("SHOW TABLES LIKE 'users'");
    $userId = null;
    if ($res && $res->num_rows > 0) {
        $pwdHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
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
    $supervisor_id = getPost('supervisor_id') ? (int)getPost('supervisor_id') : null;
    $coordinator_id = getPost('coordinator_id') ? (int)getPost('coordinator_id') : null;
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
    $course_id = (int)$course_id;
    $section_id = resolveSectionId($mysqli, $section, $course_id);
    $department_id = null;
    if ($department_id_raw !== null && $department_id_raw !== '' && ctype_digit((string)$department_id_raw)) {
        $department_id = (int)$department_id_raw;
    } else {
        $department_id = resolveDepartmentIdByCode($mysqli, $department_code);
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
    if ($course_id <= 0 || empty($department_id) || $section_id <= 0 || empty($coordinator_id) || empty($supervisor_id)) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Invalid academic assignment selection. Please choose course, department, section, coordinator, and supervisor.'));
        exit;
    }

    $course_check = $mysqli->prepare("SELECT id FROM courses WHERE id = ? AND deleted_at IS NULL LIMIT 1");
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
    $dept_check = $mysqli->prepare("SELECT id FROM departments WHERE id = ? AND deleted_at IS NULL LIMIT 1");
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

    $section_check = $mysqli->prepare("
        SELECT id
        FROM sections
        WHERE id = ?
          AND course_id = ?
          AND department_id = ?
          AND is_active = 1
          AND deleted_at IS NULL
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

    $coord_check = $mysqli->prepare("
        SELECT CONCAT(first_name, ' ', last_name) AS full_name
        FROM coordinators
        WHERE id = ?
          AND department_id = ?
          AND is_active = 1
          AND deleted_at IS NULL
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

    $sup_check = $mysqli->prepare("
        SELECT CONCAT(first_name, ' ', last_name) AS full_name
        FROM supervisors
        WHERE id = ?
          AND department_id = ?
          AND is_active = 1
          AND deleted_at IS NULL
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

    // Ensure username is not empty
    if (!$username) {
        $username = $first_name . '.' . $last_name;
    }

    // Hash password
    $pwdHash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);

    // Create a users record first (required for FK constraint)
    $stmt_user = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 'student', 1, NOW())");
    $user_id = null;
    if ($stmt_user) {
        $full_name = $first_name . ' ' . $last_name;
        $stmt_user->bind_param('ssss', $full_name, $username, $final_email, $pwdHash);
        try {
            if ($stmt_user->execute()) {
                $user_id = $mysqli->insert_id;
            }
        } catch (mysqli_sql_exception $e) {
            $code = $e->getCode();
            $msg = $e->getMessage();
            $stmt_user->close();
            // 1062 = duplicate entry
            if ($code === 1062 || strpos($msg, 'Duplicate entry') !== false) {
                header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
                exit;
            }
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($msg));
            exit;
        }
        $stmt_user->close();
    }

    // Validate that user_id was created successfully
    if (!$user_id) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Failed to create user account'));
        exit;
    }

    // Now insert into students table using the user_id
    // Note: If emergency_contact_phone column doesn't exist, store phone in emergency_contact or add the column
    $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, department_id, section_id, address, phone, date_of_birth, gender, supervisor_id, supervisor_name, coordinator_id, coordinator_name, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining, assignment_track, emergency_contact, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    if (!$stmt) {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Database statement error: ' . $mysqli->error));
        exit;
    }
    
    // Combine emergency contact info for storage (Name: Phone format for display)
    $emergency_contact_full = $emergency_contact;
    if ($emergency_contact_phone) {
        $emergency_contact_full = $emergency_contact . ' (' . $emergency_contact_phone . ')';
    }
    
    // types: user_id(i), course_id(i), student_id(s), first_name(s), last_name(s), middle_name(s),
    // username(s), password(s), email(s), department_id(s), section_id(i), address(s), phone(s),
    // date_of_birth(s), gender(s), supervisor_id(i), supervisor_name(s), coordinator_id(i),
    // coordinator_name(s), internal_total_hours(i), internal_total_hours_remaining(i),
    // external_total_hours(i), external_total_hours_remaining(i), assignment_track(s), emergency_contact(s)
    $department_id_for_student = $department_id !== null ? (string)$department_id : null;
    $stmt->bind_param(
        'iissssssssissssisisiiiiss',
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
        $supervisor_id,
        $supervisor_name,
        $coordinator_id,
        $coordinator_name,
        $internal_total_hours,
        $internal_total_hours_remaining,
        $external_total_hours,
        $external_total_hours_remaining,
        $assignment_track,
        $emergency_contact_full
    );
    
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
    if ($new_student_id > 0 && $course_id > 0 && !empty($department_id) && !empty($coordinator_id) && !empty($supervisor_id)) {
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

    // Insert into coordinators table
    $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, NOW())");
    if ($stmt) {
        $stmt->bind_param('isssiis', $userId, $first_name, $last_name, $final_email, $phone, $department_id, $office_location);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($error));
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
    $specialization = getPost('specialization');
    $username = getPost('username');
    $account_email = getPost('account_email');

    $final_email = $account_email ?: $email;
    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $final_email, $password ?: bin2hex(random_bytes(4)), 'supervisor');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
        exit;
    }

    // Insert into supervisors table
    $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?, 1, NOW())");
    if ($stmt) {
        $stmt->bind_param('isssss', $userId, $first_name, $last_name, $final_email, $phone, $specialization);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($error));
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
            header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Admin record error: ' . $error));
            exit;
        }
        $stmt_admin->close();
    } else {
        header('Location: auth-register-creative.php?registered=error&msg=' . urlencode('Admin table statement error: ' . $mysqli->error));
        exit;
    }

    header('Location: auth-register-creative.php?registered=admin');
    exit;
}

// fallback
header('Location: register_submit.php');
exit;
