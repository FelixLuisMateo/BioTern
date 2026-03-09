<?php
require_once dirname(__DIR__) . '/config/db.php';
// Simple admin creation script for BioTern
// Usage: open this file in the browser (http://localhost/BioTern/create_admin.php)

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($name === '' || $username === '' || $email === '' || $password === '') {
        $message = 'All fields are required.';
    } else {
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($mysqli->connect_errno) {
            $message = 'Database connection failed: ' . esc($mysqli->connect_error);
        } else {
            // Check existing username/email
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $message = 'A user with that username or email already exists.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'admin';
                    $is_active = 1;
                    $mysqli->begin_transaction();
                    try {
                        $ins = $mysqli->prepare('INSERT INTO users (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?)');
                        if (!$ins) {
                            throw new Exception('Insert statement preparation failed.');
                        }
                        $ins->bind_param('sssssi', $name, $username, $email, $hashed, $role, $is_active);
                        if (!$ins->execute()) {
                            $err = $ins->error;
                            $ins->close();
                            throw new Exception('Insert failed: ' . $err);
                        }
                        $user_id = (int)$ins->insert_id;
                        $ins->close();

                        // Keep legacy admin profile table in sync when present.
                        $admin_table_exists = $mysqli->query("SHOW TABLES LIKE 'admin'");
                        if ($admin_table_exists && $admin_table_exists->num_rows > 0) {
                            $next_admin_id = 1;
                            $next_id_res = $mysqli->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM admin");
                            if ($next_id_res) {
                                $row = $next_id_res->fetch_assoc();
                                if ($row && isset($row['next_id'])) {
                                    $next_admin_id = (int)$row['next_id'];
                                }
                                $next_id_res->close();
                            }

                            $department_id = 1;
                            $dept_res = $mysqli->query("SELECT id FROM departments ORDER BY id ASC LIMIT 1");
                            if ($dept_res && $dept_res->num_rows > 0) {
                                $dept_row = $dept_res->fetch_assoc();
                                if ($dept_row && isset($dept_row['id'])) {
                                    $department_id = (int)$dept_row['id'];
                                }
                                $dept_res->close();
                            }

                            $admin_level = 'admin';
                            $admin_position = 'Admin';
                            $middle_name = '';
                            $phone_number = '';
                            $stmt_admin = $mysqli->prepare("
                                INSERT INTO admin (
                                    id, user_id, first_name, middle_name, institution_email_address, phone_number,
                                    admin_level, department_id, admin_position, username, password, email
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            if (!$stmt_admin) {
                                throw new Exception('Admin profile statement preparation failed: ' . $mysqli->error);
                            }
                            $stmt_admin->bind_param(
                                'iisssssissss',
                                $next_admin_id,
                                $user_id,
                                $name,
                                $middle_name,
                                $email,
                                $phone_number,
                                $admin_level,
                                $department_id,
                                $admin_position,
                                $username,
                                $hashed,
                                $email
                            );
                            if (!$stmt_admin->execute()) {
                                $err = $stmt_admin->error;
                                $stmt_admin->close();
                                throw new Exception('Admin profile insert failed: ' . $err);
                            }
                            $stmt_admin->close();
                        }

                        $mysqli->commit();
                        $message = 'Admin account created successfully. ID: ' . $user_id;
                    } catch (Throwable $e) {
                        $mysqli->rollback();
                        $message = esc($e->getMessage());
                    }
                }
                $stmt->close();
            } else {
                $message = 'Query preparation failed.';
            }
            $mysqli->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Admin - BioTern</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>body{padding:24px;background:#f7f9fb} .card{max-width:640px;margin:24px auto}</style>
</head>
<body>
    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Create Admin Account</h4>
            <p class="card-text">Use this form to create a single admin account. The script checks for existing username/email.</p>
            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($message !== ''): ?>
                <div class="alert alert-info"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo esc($message); ?></div>
            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
            <form method="post" novalidate>
                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input type="text" name="name" class="form-control" required value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($_POST['name'])?esc($_POST['name']):'Administrator'; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($_POST['username'])?esc($_POST['username']):'admin'; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($_POST['email'])?esc($_POST['email']):'admin@example.com'; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Choose a secure password">
                </div>
                <button type="submit" class="btn btn-primary">Create Admin</button>
            </form>
            <hr>
            <p class="small text-muted">After creating the admin account you can log in via the Sign In page.</p>
        </div>
    </div>
</body>
</html>


