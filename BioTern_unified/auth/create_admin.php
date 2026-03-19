<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$is_admin_session = ($current_user_id > 0 && $current_role === 'admin');
$is_post_request = ($_SERVER['REQUEST_METHOD'] === 'POST');

if (!function_exists('esc')) {
    function esc($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($name === '' || $username === '' || $email === '' || $password === '') {
        $message = 'All fields are required.';
    } else {
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
        if ($mysqli->connect_errno) {
            $message = 'Database connection failed: ' . esc($mysqli->connect_error);
        } else {
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

                        $admin_table_exists = $mysqli->query("SHOW TABLES LIKE 'admin'");
                        if ($admin_table_exists && $admin_table_exists->num_rows > 0) {

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
                            $stmt_admin = $mysqli->prepare(
                                "INSERT INTO admin (
                                    user_id, first_name, middle_name, institution_email_address, phone_number,
                                    admin_level, department_id, admin_position, username, password, email
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            if (!$stmt_admin) {
                                throw new Exception('Admin profile statement preparation failed: ' . $mysqli->error);
                            }
                            $stmt_admin->bind_param(
                                'isssssissss',
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
<?php if ($is_admin_session): ?>
<?php
$page_title = 'Create Admin';
include 'includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Create Admin</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="users.php">Users</a></li>
            <li class="breadcrumb-item">Create Admin</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather feather-shield me-2 text-primary"></i>New Admin Account
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-12 mb-4">Creates a new administrator entry in both the <code>users</code> and <code>admin</code> tables.</p>

                    <?php if ($message !== ''): ?>
                        <?php $mt = (stripos($message, 'successfully') !== false) ? 'success' : 'danger'; ?>
                        <div class="alert alert-<?php echo $mt; ?> mb-4" role="alert">
                            <?php echo esc($message); ?>
                            <?php if ($mt === 'success'): ?>
                                <div class="mt-1"><a href="users.php" class="alert-link fw-semibold">View Users &rarr;</a></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate class="row g-3" autocomplete="off">
                        <div style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                            <input type="text" name="fake_username" tabindex="-1" autocomplete="username">
                            <input type="password" name="fake_password" tabindex="-1" autocomplete="current-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ca_name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="ca_name" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? esc($_POST['name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ca_username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="ca_username" name="username" class="form-control" autocomplete="off" autocapitalize="off" spellcheck="false" required value="<?php echo isset($_POST['username']) ? esc($_POST['username']) : ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ca_email">Email Address <span class="text-danger">*</span></label>
                            <input type="email" id="ca_email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? esc($_POST['email']) : ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ca_password">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="ca_password" name="password" class="form-control" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password">
                                    <i id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-primary"><i class="feather feather-user-plus me-2"></i>Create Admin</button>
                            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-xl-6">
            <div class="card stretch stretch-full border-0" style="background:transparent">
                <div class="card-body pt-0">
                    <div class="alert alert-soft-warning">
                        <h6 class="alert-heading fw-bold mb-2"><i class="feather feather-info me-2"></i>Notes</h6>
                        <ul class="mb-0 ps-3 fs-12">
                            <li>The new account will have <strong>admin</strong> role and will be active immediately.</li>
                            <li>A matching row is inserted into the <code>admin</code> profile table if it exists.</li>
                            <li>Username and email must be unique across all users.</li>
                            <li>Use a strong password (8+ characters recommended).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var toggle = document.getElementById('togglePassword');
    var user = document.getElementById('ca_username');
    var pwd = document.getElementById('ca_password');
    var icon = document.getElementById('toggleIcon');
    var isPostRequest = <?php echo $is_post_request ? 'true' : 'false'; ?>;
    var eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    var eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"/><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    if (!isPostRequest) {
        if (user) user.value = '';
        if (pwd) pwd.value = '';
    }
    if (icon) icon.innerHTML = eyeSVG;
    if (toggle && pwd) {
        toggle.addEventListener('click', function () {
            var show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            if (icon) icon.innerHTML = show ? eyeOffSVG : eyeSVG;
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }
})();
</script>
<?php include 'includes/footer.php'; ?>

<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Admin - BioTern</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo esc($asset_prefix); ?>assets/images/favicon.ico?v=20260310">
    <script src="<?php echo esc($asset_prefix); ?>assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo esc($asset_prefix); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo esc($asset_prefix); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo esc($asset_prefix); ?>assets/css/theme.min.css">
    <style>
        body { min-height: 100vh; overflow-x: hidden; position: relative; }
        .login-bg-watermark {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: url('building.png');
            background-repeat: no-repeat; background-position: center center;
            background-size: cover; opacity: 90%;
        }
        .create-admin-wrapper {
            position: relative; z-index: 1; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 16px; background-color: rgba(8, 20, 52, 0.82);
        }
        .create-admin-card { width: 100%; max-width: 480px; background: transparent; }
        .badge-setup {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px; padding: 4px 12px; font-size: 11px;
            color: rgba(255,255,255,0.6); letter-spacing: 0.5px;
            text-transform: uppercase; margin-bottom: 20px;
        }
        .badge-setup span.dot {
            width: 7px; height: 7px; border-radius: 50%; background: #4caf50;
            display: inline-block; animation: pulse-dot 1.6s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        .create-admin-card h2 { color: #fff; font-weight: 700; font-size: 28px; margin-bottom: 6px; }
        .create-admin-card .subtitle { color: rgba(255,255,255,0.55); font-size: 13.5px; margin-bottom: 28px; }
        .create-admin-card .form-label { color: rgba(255,255,255,0.75); font-size: 13px; font-weight: 500; margin-bottom: 6px; }
        .create-admin-card .form-control {
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; border-radius: 8px; padding: 10px 14px;
            transition: border-color 0.2s, background 0.2s;
        }
        .create-admin-card .form-control:focus {
            background: rgba(255,255,255,0.11); border-color: rgba(99,132,255,0.7);
            box-shadow: 0 0 0 3px rgba(99,132,255,0.18); color: #fff;
        }
        .create-admin-card .input-group .form-control { border-right: none; }
        .create-admin-card .input-group .btn-outline-secondary {
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15);
            border-left: none; color: rgba(255,255,255,0.55); border-radius: 0 8px 8px 0;
        }
        .create-admin-card .input-group .btn-outline-secondary:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .create-admin-card .btn-primary { border-radius: 8px; padding: 11px; font-size: 15px; font-weight: 600; }
        .create-admin-card .divider { border-top: 1px solid rgba(255,255,255,0.10); margin: 24px 0 16px; }
        .create-admin-card .footer-note { color: rgba(255,255,255,0.38); font-size: 12px; text-align: center; }
        .create-admin-card .footer-note a { color: rgba(255,255,255,0.6); text-decoration: underline; }
        @media (max-width: 575.98px) { .create-admin-wrapper { padding: 24px 12px; } }
    </style>
</head>
<body>
    <div class="login-bg-watermark" aria-hidden="true"></div>
    <div class="create-admin-wrapper">
        <div class="create-admin-card">

            <div class="wd-50 mb-4">
                <img src="<?php echo esc($asset_prefix); ?>assets/images/logo-abbr.png" alt="BioTern" class="img-fluid">
            </div>

            <div class="badge-setup"><span class="dot"></span> Initial Setup</div>

            <h2>Create Admin Account</h2>
            <p class="subtitle">Set up the first administrator account to access the BioTern dashboard.</p>

            <?php if ($message !== ''): ?>
                <?php $mt = (stripos($message, 'successfully') !== false) ? 'success' : 'danger'; ?>
                <div class="alert alert-<?php echo $mt; ?> mb-4" role="alert">
                    <?php echo esc($message); ?>
                    <?php if ($mt === 'success'): ?>
                        <div class="mt-2"><a href="auth-login-cover.php" class="alert-link fw-semibold">Go to Login &rarr;</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate autocomplete="off">
                <div style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                    <input type="text" name="fake_username" tabindex="-1" autocomplete="username">
                    <input type="password" name="fake_password" tabindex="-1" autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ca_name">Full Name</label>
                    <input type="text" id="ca_name" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? esc($_POST['name']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ca_username">Username</label>
                    <input type="text" id="ca_username" name="username" class="form-control" autocomplete="off" autocapitalize="off" spellcheck="false" required value="<?php echo isset($_POST['username']) ? esc($_POST['username']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ca_email">Email Address</label>
                    <input type="email" id="ca_email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? esc($_POST['email']) : ''; ?>">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="ca_password">Password</label>
                    <div class="input-group">
                        <input type="password" id="ca_password" name="password" class="form-control" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password">
                            <i id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-lg btn-primary w-100">Create Admin Account</button>
            </form>

            <div class="divider"></div>
            <p class="footer-note">Already have an account? <a href="auth-login-cover.php">Sign in</a></p>

        </div>
    </div>

    <script src="<?php echo esc($asset_prefix); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo esc($asset_prefix); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo esc($asset_prefix); ?>assets/js/theme-customizer-init.min.js"></script>
    <script>
        (function () {
            var toggle = document.getElementById('togglePassword');
            var user = document.getElementById('ca_username');
            var pwd = document.getElementById('ca_password');
            var icon = document.getElementById('toggleIcon');
            var isPostRequest = <?php echo $is_post_request ? 'true' : 'false'; ?>;
            var eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
            var eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"/><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            if (!isPostRequest) {
                if (user) user.value = '';
                if (pwd) pwd.value = '';
            }
            if (icon) icon.innerHTML = eyeSVG;
            if (toggle && pwd) {
                toggle.addEventListener('click', function () {
                    var show = pwd.type === 'password';
                    pwd.type = show ? 'text' : 'password';
                    if (icon) icon.innerHTML = show ? eyeOffSVG : eyeSVG;
                    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            }
        })();
    </script>
</body>
</html>
<?php endif; ?>
