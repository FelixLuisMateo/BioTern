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
$page_styles = ['assets/css/modules/auth/page-create-admin.css'];
$page_scripts = ['assets/js/modules/auth/create-admin-page.js'];
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
                        <div class="ca-honeypot" aria-hidden="true">
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
            <div class="card stretch stretch-full border-0 bg-transparent">
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
    <link rel="stylesheet" type="text/css" href="<?php echo esc($asset_prefix); ?>assets/css/modules/auth/auth-login-cover.css">
    <link rel="stylesheet" type="text/css" href="<?php echo esc($asset_prefix); ?>assets/css/modules/auth/page-create-admin.css">
</head>
<body class="auth-login-page create-admin-public" data-ca-post-request="<?php echo $is_post_request ? '1' : '0'; ?>">
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
                        <div class="mt-2"><a href="auth-login.php" class="alert-link fw-semibold">Go to Login &rarr;</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate autocomplete="off">
                <div class="ca-honeypot" aria-hidden="true">
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
            <p class="footer-note">Already have an account? <a href="auth-login.php">Sign in</a></p>

        </div>
    </div>

    <script src="<?php echo esc($asset_prefix); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo esc($asset_prefix); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo esc($asset_prefix); ?>assets/js/theme-customizer-init.min.js"></script>
    <script src="<?php echo esc($asset_prefix); ?>assets/js/modules/auth/create-admin-page.js"></script>
</body>
</html>
<?php endif; ?>
