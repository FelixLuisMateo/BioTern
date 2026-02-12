<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RegisterSubmitController extends Controller
{
    public function handle(Request $request)
    {
        if (!$request->isMethod('post')) {
            return view('register_submit');
        }

        $db = config('database.connections.mysql');
        $dbHost = $db['host'] ?? '127.0.0.1';
        $dbUser = $db['username'] ?? 'root';
        $dbPass = $db['password'] ?? '';
        $dbName = $db['database'] ?? env('DB_DATABASE', 'biotern_db');

        $mysqli = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($mysqli->connect_errno) {
            return response('DB connection failed: ' . $mysqli->connect_error, 500);
        }

        $input = function ($key) use ($request) {
            $v = $request->input($key);
            return is_string($v) ? trim($v) : $v;
        };

        $role = $input('role');
        if (!$role) {
            return redirect('/register_submit');
        }

        $createUser = function ($username, $email, $password, $role) use ($mysqli) {
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
        };

        if ($role === 'student') {
            $student_id = $input('student_id');
            $first_name = $input('first_name');
            $middle_name = $input('middle_name');
            $last_name = $input('last_name');
            $address = $input('address');
            $email = $input('email');
            $course_id = $input('course_id');
            $section = $input('section');
            $username = $input('username');
            $account_email = $input('account_email');
            $password = $input('password');

            $final_email = $account_email ?: $email;
            $course_id = (int)$course_id;
            $section_id = is_numeric($section) ? (int)$section : 0;

            if (!$username) {
                $username = $first_name . '.' . $last_name;
            }

            $pwdHash = password_hash($password ?: bin2hex(random_bytes(4)), PASSWORD_DEFAULT);

            $stmt_user = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 'student', 1, NOW())");
            $user_id = null;
            if ($stmt_user) {
                $full_name = $first_name . ' ' . $last_name;
                $stmt_user->bind_param('ssss', $full_name, $username, $final_email, $pwdHash);
                if ($stmt_user->execute()) {
                    $user_id = $mysqli->insert_id;
                } else {
                    $error = $stmt_user->error;
                    $stmt_user->close();
                    return redirect('/register_submit?registered=error&msg=' . urlencode($error));
                }
                $stmt_user->close();
            }

            if ($user_id) {
                $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, section_id, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param('iisssssssis', $user_id, $course_id, $student_id, $first_name, $last_name, $middle_name, $username, $pwdHash, $final_email, $section_id, $address);
                    if (!$stmt->execute()) {
                        $error = $stmt->error;
                        $stmt->close();
                        return redirect('/register_submit?registered=error&msg=' . urlencode($error));
                    }
                    $stmt->close();
                }
            }

            return redirect('/register_submit?registered=student');
        }

        if ($role === 'coordinator') {
            $first_name = $input('first_name');
            $last_name = $input('last_name');
            $email = $input('email');
            $phone = $input('phone');
            $office_location = $input('office_location');
            $department_id = $input('department_id');
            $position = $input('position');
            $username = $input('username');
            $account_email = $input('account_email');
            $password = $input('password');

            $userId = $createUser($username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'coordinator');

            $stmt = $mysqli->prepare("INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, bio, profile_picture, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NULL, NULL, 1, NOW())");
            if ($stmt) {
                $u = $userId ?: null;
                $stmt->bind_param('isssiss', $u, $first_name, $last_name, $account_email ?: $email, $phone, $department_id, $office_location);
                $stmt->execute();
                $stmt->close();
            }

            return redirect('/register_submit?registered=coordinator');
        }

        if ($role === 'supervisor') {
            $first_name = $input('first_name');
            $last_name = $input('last_name');
            $email = $input('email');
            $phone = $input('phone');
            $company_name = $input('company_name');
            $job_position = $input('job_position');
            $department = $input('department');
            $specialization = $input('specialization');
            $company_address = $input('company_address');
            $username = $input('username');
            $account_email = $input('account_email');
            $password = $input('password');

            $userId = $createUser($username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'supervisor');

            $stmt = $mysqli->prepare("INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, bio, profile_picture, is_active, created_at) VALUES (?, ?, ?, NULL, ?, ?, NULL, ?, NULL, NULL, 1, NOW())");
            if ($stmt) {
                $u = $userId ?: null;
                $stmt->bind_param('isssss', $u, $first_name, $last_name, $account_email ?: $email, $phone, $specialization);
                $stmt->execute();
                $stmt->close();
            }

            return redirect('/register_submit?registered=supervisor');
        }

        if ($role === 'admin') {
            $first_name = $input('first_name');
            $last_name = $input('last_name');
            $email = $input('email');
            $phone = $input('phone');
            $admin_level = $input('admin_level');
            $department_id = $input('department_id');
            $position = $input('position');
            $username = $input('username');
            $account_email = $input('account_email');
            $password = $input('password');

            $userId = $createUser($username ?: ($first_name . ' ' . $last_name), $account_email ?: $email, $password ?: bin2hex(random_bytes(4)), 'admin');

            return redirect('/register_submit?registered=admin');
        }

        return redirect('/register_submit');
    }
}
