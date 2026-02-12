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
    header('Location: auth-register-creative.php');
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
            $stmt->execute();
            $userId = $mysqli->insert_id;
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
    $total_hours = getPost('total_hours');
    $emergency_contact = getPost('emergency_contact');

    // Use account_email if provided, otherwise use email
    $final_email = $account_email ?: $email;
    $course_id = (int)$course_id;
    $section_id = is_numeric($section) ? (int)$section : 0;
    $total_hours = $total_hours ? (int)$total_hours : null;
    
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
        if ($stmt_user->execute()) {
            $user_id = $mysqli->insert_id;
        } else {
            // Check if it's a duplicate email error
            $error = $stmt_user->error;
            $stmt_user->close();
            header('Location: auth-register-creative.html?registered=error&msg=' . urlencode($error));
            exit;
        }
        $stmt_user->close();
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
    if ($user_id) {
        $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, section_id, address, phone, date_of_birth, gender, supervisor_name, coordinator_name, total_hours, emergency_contact, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('iisssssssissssssi', $user_id, $course_id, $student_id, $first_name, $last_name, $middle_name, $username, $pwdHash, $final_email, $section_id, $address, $phone, $date_of_birth, $gender, $supervisor_name, $coordinator_name, $total_hours, $emergency_contact);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                header('Location: auth-register-creative.html?registered=error&msg=' . urlencode($error));
                exit;
            }
            $stmt->close();
        }
    }

    header('Location: auth-register-creative.html?registered=student');
    exit;
}

if ($role === 'coordinator') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.html?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $office_location = getPost('office_location');
    $department_id = getPost('department_id');
    $position = getPost('position');
    $username = getPost('username');
    $account_email = getPost('account_email');

    $final_email = $account_email ?: $email;
    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $final_email, $password ?: bin2hex(random_bytes(4)), 'coordinator');

    $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, bio, profile_picture, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NULL, NULL, 1, NOW())");
    if ($stmt) {
        $u = $userId ?: null;
        $stmt->bind_param('isssiss', $u, $first_name, $last_name, $final_email, $phone, $department_id, $office_location);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: auth-register-creative.html?registered=coordinator');
    exit;
}

if ($role === 'supervisor') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.html?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $company_name = getPost('company_name');
    $job_position = getPost('job_position');
    $department = getPost('department');
    $specialization = getPost('specialization');
    $company_address = getPost('company_address');
    $username = getPost('username');
    $account_email = getPost('account_email');

    $final_email = $account_email ?: $email;
    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $final_email, $password ?: bin2hex(random_bytes(4)), 'supervisor');

    $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, bio, profile_picture, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?, NULL, NULL, 1, NOW())");
    if ($stmt) {
        $u = $userId ?: null;
        $stmt->bind_param('isssss', $u, $first_name, $last_name, $final_email, $phone, $specialization);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: auth-register-creative.html?registered=supervisor');
    exit;
}

if ($role === 'admin') {
    // Validate password matches confirm_password
    $password = getPost('password');
    $confirm_password = getPost('confirm_password');
    if ($password !== $confirm_password) {
        header('Location: auth-register-creative.html?registered=error&msg=' . urlencode('Passwords do not match'));
        exit;
    }
    
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $admin_level = getPost('admin_level');
    $department_id = getPost('department_id');
    $position = getPost('position');
    $username = getPost('username');
    $account_email = getPost('account_email');

    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'admin');

    // Admins are usually stored in users + roles; additional admin metadata can be stored in an admins table if present.

    header('Location: auth-register-creative.html?registered=admin');
    exit;
}

// fallback
header('Location: auth-register-creative.php');
exit;
