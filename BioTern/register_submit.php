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
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.html?registered=error&msg=' . urlencode('Passwords do not match'));
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
    $emergency_contact = getPost('emergency_contact');
    $emergency_contact_phone = getPost('emergency_contact_phone');

    // Use account_email if provided, otherwise use email
    $final_email = $account_email ?: $email;
    $course_id = (int)$course_id;
    $section_id = is_numeric($section) ? (int)$section : 0;
    $internal_total_hours = $internal_total_hours ? (int)$internal_total_hours : null;
    
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

    // Look up supervisor and coordinator names from their IDs
    $supervisor_name = null;
    $coordinator_name = null;
    
    if ($supervisor_id) {
        $stmt_sup = $mysqli->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM supervisors WHERE id = ? LIMIT 1");
        if ($stmt_sup) {
            $stmt_sup->bind_param('i', $supervisor_id);
            $stmt_sup->execute();
            $stmt_sup->bind_result($supervisor_name);
            $stmt_sup->fetch();
            $stmt_sup->close();
        }
    }
    
    if ($coordinator_id) {
        $stmt_coord = $mysqli->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM coordinators WHERE id = ? LIMIT 1");
        if ($stmt_coord) {
            $stmt_coord->bind_param('i', $coordinator_id);
            $stmt_coord->execute();
            $stmt_coord->bind_result($coordinator_name);
            $stmt_coord->fetch();
            $stmt_coord->close();
        }
    }

    // Now insert into students table using the user_id
    // Note: If emergency_contact_phone column doesn't exist, store phone in emergency_contact or add the column
    $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, section_id, address, phone, date_of_birth, gender, supervisor_id, supervisor_name, coordinator_id, coordinator_name, internal_total_hours, emergency_contact, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
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
    // username(s), password(s), email(s), section_id(i), address(s), phone(s), date_of_birth(s),
    // gender(s), supervisor_id(i), supervisor_name(s), coordinator_id(i), coordinator_name(s), internal_total_hours(i), emergency_contact(s)
    $stmt->bind_param('iisssssssissssisisis', $user_id, $course_id, $student_id, $first_name, $last_name, $middle_name, $username, $pwdHash, $final_email, $section_id, $address, $phone, $date_of_birth, $gender, $supervisor_id, $supervisor_name, $coordinator_id, $coordinator_name, $internal_total_hours, $emergency_contact_full);
    
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
    
    $stmt->close();

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
    $department_id = getPost('department_id') ? (int)getPost('department_id') : null;
    $username = getPost('username');
    $account_email = getPost('account_email');

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

    $final_email = $account_email ?: $email;
    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $final_email, $password ?: bin2hex(random_bytes(4)), 'admin');
    
    // if createUser() returned null (possible duplicate/email exists), warn and stop
    if (!$userId) {
        header('Location: auth-register-creative.php?registered=exists&msg=' . urlencode('An account with that email or username already exists'));
        exit;
    }

    // If you have an admins table, insert here. Otherwise admin data is stored in users table with role='admin'
    // Example if admins table exists:
    // $stmt = $mysqli->prepare("INSERT INTO admins (user_id, first_name, last_name, email, phone, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    // if ($stmt) {
    //     $stmt->bind_param('issss', $userId, $first_name, $last_name, $final_email, $phone);
    //     if (!$stmt->execute()) {
    //         $error = $stmt->error;
    //         $stmt->close();
    //         header('Location: auth-register-creative.php?registered=error&msg=' . urlencode($error));
    //         exit;
    //     }
    //     $stmt->close();
    // }

    header('Location: auth-register-creative.php?registered=admin');
    exit;
}

// fallback
header('Location: register_submit.php');
exit;
