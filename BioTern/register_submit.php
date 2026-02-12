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
    header('Location: auth-register-creative.html');
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
    $student_id = getPost('student_id');
    $first_name = getPost('first_name');
    $middle_name = getPost('middle_name');
    $last_name = getPost('last_name');
    $address = getPost('address');
    $email = getPost('email');
    $course_id = getPost('course_id');
    $department_id = getPost('department_id');
    $section = getPost('section');
    $account_email = getPost('account_email');

    // Use account_email if provided, otherwise use email
    $final_email = $account_email ?: $email;
    $course_id = (int)$course_id;
    $user_id = null;  // Explicitly set to NULL so students don't require a users row

    // Insert directly into `students` table (do not create a `users` row).
    // user_id is set to NULL (ensure db_adjustments.sql has been run to allow this).
    $stmt = $mysqli->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, middle_name, email, address, course_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param('issssssi', $user_id, $student_id, $first_name, $last_name, $middle_name, $final_email, $address, $course_id);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: auth-register-creative.html?registered=student');
    exit;
}

if ($role === 'coordinator') {
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $office_location = getPost('office_location');
    $department_id = getPost('department_id');
    $position = getPost('position');
    $username = getPost('username');
    $account_email = getPost('account_email');
    $password = getPost('password');

    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'coordinator');

    $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, bio, profile_picture, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NULL, NULL, 1, NOW())");
    if ($stmt) {
        $u = $userId ?: null;
        $stmt->bind_param('isssiss', $u, $first_name, $last_name, $account_email ?: $email, $phone, $department_id, $office_location);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: auth-register-creative.html?registered=coordinator');
    exit;
}

if ($role === 'supervisor') {
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
    $password = getPost('password');

    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'supervisor');

    $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, bio, profile_picture, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?, NULL, NULL, 1, NOW())");
    if ($stmt) {
        $u = $userId ?: null;
        $stmt->bind_param('isssss', $u, $first_name, $last_name, $account_email ?: $email, $phone, $specialization);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: auth-register-creative.html?registered=supervisor');
    exit;
}

if ($role === 'admin') {
    $first_name = getPost('first_name');
    $last_name = getPost('last_name');
    $email = getPost('email');
    $phone = getPost('phone');
    $admin_level = getPost('admin_level');
    $department_id = getPost('department_id');
    $position = getPost('position');
    $username = getPost('username');
    $account_email = getPost('account_email');
    $password = getPost('password');

    $userId = createUser($mysqli, $username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'admin');

    // Admins are usually stored in users + roles; additional admin metadata can be stored in an admins table if present.

    header('Location: auth-register-creative.html?registered=admin');
    exit;
}

// fallback
header('Location: auth-register-creative.html');
exit;
