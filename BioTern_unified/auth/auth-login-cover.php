<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['logout']) && (string)$_GET['logout'] === '1') {
    $logout_conn = @new mysqli($dbHost ?? (defined('DB_HOST') ? DB_HOST : '127.0.0.1'), $dbUser ?? (defined('DB_USER') ? DB_USER : 'root'), $dbPass ?? (defined('DB_PASS') ? DB_PASS : ''), $dbName ?? (defined('DB_NAME') ? DB_NAME : 'biotern_db'), $dbPort ?? (defined('DB_PORT') ? (int)DB_PORT : 3306));
    if ($logout_conn instanceof mysqli && !$logout_conn->connect_errno) {
        biotern_auth_clear_persistent_login($logout_conn);
        $logout_conn->close();
    } else {
        biotern_auth_clear_persistent_login();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
}

$dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$auth_local_prefix = (strpos($script_name, '/auth/') !== false) ? '' : 'auth/';
$auth_building_href = (strpos($script_name, '/auth/') !== false) ? 'building.png' : 'auth/building.png';
$route_prefix = $asset_prefix;
$login_error = '';
$next = isset($_GET['next']) ? basename((string)$_GET['next']) : '';
if ($next !== '' && !preg_match('/^[A-Za-z0-9_-]+\.php$/', $next)) {
    $next = '';
}

if ((int)($_SESSION['user_id'] ?? 0) <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $restore_conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    if (!$restore_conn->connect_errno) {
        if (biotern_auth_restore_session_from_cookie($restore_conn)) {
            $restore_conn->close();
            $target = $next !== '' ? ($route_prefix . $next) : ($route_prefix . 'homepage.php');
            header('Location: ' . $target);
            exit;
        }
        $restore_conn->close();
    }
}

function log_login_attempt($mysqli, $userId, $identifier, $role, $status, $reason, $ip, $userAgent)
{
    if (!$mysqli) {
        return;
    }

    $stmt = $mysqli->prepare("INSERT INTO login_logs (user_id, identifier, role, status, reason, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        return;
    }

    $uid = $userId > 0 ? (int)$userId : null;
    $identifier = (string)$identifier;
    $role = (string)$role;
    $status = (string)$status;
    $reason = (string)$reason;
    $ip = (string)$ip;
    $userAgent = (string)$userAgent;
    $stmt->bind_param('issssss', $uid, $identifier, $role, $status, $reason, $ip, $userAgent);
    $stmt->execute();
    $stmt->close();
}

function auth_login_has_column(mysqli $mysqli, string $table, string $column): bool
{
    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $mysqli->real_escape_string($column);
    $res = $mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

function auth_login_ensure_users_schema(mysqli $mysqli): void
{
    $requiredColumns = [
        'application_status' => "ALTER TABLE users ADD COLUMN application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'",
        'application_submitted_at' => "ALTER TABLE users ADD COLUMN application_submitted_at DATETIME NULL",
        'approved_by' => "ALTER TABLE users ADD COLUMN approved_by INT NULL",
        'approved_at' => "ALTER TABLE users ADD COLUMN approved_at DATETIME NULL",
        'rejected_at' => "ALTER TABLE users ADD COLUMN rejected_at DATETIME NULL",
        'approval_notes' => "ALTER TABLE users ADD COLUMN approval_notes VARCHAR(255) NULL",
    ];

    foreach ($requiredColumns as $column => $sql) {
        try {
            if (!auth_login_has_column($mysqli, 'users', $column)) {
                $mysqli->query($sql);
            }
        } catch (Throwable $e) {
            // Ignore schema sync failures so login can continue on restricted DB accounts.
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['identifier']) ? trim((string)$_POST['identifier']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $client_ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    $client_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    $posted_next = isset($_POST['next']) ? basename((string)$_POST['next']) : '';
    if ($posted_next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $posted_next)) {
        $next = $posted_next;
    }

    if ($identifier === '' || $password === '') {
        $login_error = 'Please enter your username/email and password.';
    } else {
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
        if ($mysqli->connect_errno) {
            $login_error = 'Database connection failed.';
        } else {
            auth_login_ensure_users_schema($mysqli);

            $mysqli->query("CREATE TABLE IF NOT EXISTS login_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                identifier VARCHAR(191) NULL,
                role VARCHAR(50) NULL,
                status VARCHAR(20) NOT NULL,
                reason VARCHAR(100) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_logs_user_id (user_id),
                INDEX idx_login_logs_status_created (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $mysqli->prepare("SELECT id, name, username, email, password, role, is_active, profile_picture, COALESCE(application_status, 'approved') AS application_status FROM users WHERE (username = ? OR email = ?) LIMIT 1");

            if ($stmt) {
                $stmt->bind_param('ss', $identifier, $identifier);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    $login_error = 'Invalid username/email or password.';
                    log_login_attempt($mysqli, 0, $identifier, '', 'failed', 'invalid_credentials', $client_ip, $client_user_agent);
                } elseif ((int)($user['is_active'] ?? 0) !== 1) {
                    $login_error = 'Your account is inactive.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'inactive_account', $client_ip, $client_user_agent);
                } elseif (!password_verify($password, (string)$user['password'])) {
                    $login_error = 'Invalid username/email or password.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'invalid_credentials', $client_ip, $client_user_agent);
                } elseif (strtolower((string)($user['application_status'] ?? 'approved')) === 'pending') {
                    $login_error = 'Your registration is pending approval.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'pending_approval', $client_ip, $client_user_agent);
                } elseif (strtolower((string)($user['application_status'] ?? 'approved')) === 'rejected') {
                    $login_error = 'Your registration was rejected. Please contact an administrator.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'rejected_application', $client_ip, $client_user_agent);
                } else {
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = (string)$user['name'];
                    $_SESSION['username'] = (string)$user['username'];
                    $_SESSION['email'] = (string)$user['email'];
                    $_SESSION['role'] = (string)$user['role'];
                    $_SESSION['profile_picture'] = (string)($user['profile_picture'] ?? '');
                    $_SESSION['logged_in'] = true;

                    biotern_auth_issue_persistent_login($mysqli, (int)$user['id']);

                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'success', 'login_success', $client_ip, $client_user_agent);

                    $target = $next !== '' ? ($route_prefix . $next) : ($route_prefix . 'homepage.php');
                    header('Location: ' . $target);
                    exit;
                }
            } else {
                $login_error = 'Login query preparation failed.';
            }
            $mysqli->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Login Cover</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=20260310">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/theme.min.css">
    <style>
        body {
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        .login-bg-watermark {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: url('<?php echo htmlspecialchars($auth_building_href, ENT_QUOTES, 'UTF-8'); ?>');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            opacity: 90%;
        }

        .auth-cover-wrapper {
            position: relative;
            z-index: 1;
        }

        .auth-cover-sidebar-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px 16px;
        }

        .auth-cover-card-wrapper {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
        }

        .auth-cover-card {
            width: 100%;
            border-radius: 14px;
        }

        .auth-cover-card .wd-50 {
            width: 72px !important;
            margin-bottom: 1rem !important;
        }

        .auth-cover-card .wd-50 img {
            display: block;
            width: 100%;
            height: auto;
        }

        .auth-cover-content-inner,
        .auth-cover-sidebar-inner {
            background-color: rgba(8, 20, 52, 0.86);
        }

        .auth-cover-content-wrapper,
        .auth-img {
            height: 70%;
        }

        .auth-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }
        .auth-cover-wrapper .auth-cover-content-inner .auth-cover-content-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
            padding-top: 80px;
            padding-bottom: 50px;
        }

        @media (max-width: 991.98px) {
            .auth-cover-content-inner {
                display: none !important;
            }

            .auth-cover-wrapper {
                display: block;
            }

            .login-bg-watermark {
                opacity: 0.12;
                background-position: center top;
            }

            .auth-cover-content-wrapper,
            .auth-img {
                min-height: 0;
            }

            .auth-cover-sidebar-inner {
                min-height: 100vh;
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
                background-color: rgba(8, 20, 52, 0.92);
                padding: 16px 10px;
            }

            .auth-cover-card {
                padding: 1.15rem 0.95rem !important;
            }

            .auth-cover-card h2 {
                font-size: 1.35rem;
                margin-bottom: 0.85rem !important;
            }

            .auth-cover-card h4 {
                font-size: 0.85rem;
                line-height: 1.35;
            }

            .auth-cover-card .form-control,
            .auth-cover-card .btn {
                font-size: 0.9rem;
            }

            .auth-cover-card .btn-lg {
                padding-top: 0.6rem;
                padding-bottom: 0.6rem;
            }

            .auth-cover-card .d-flex.align-items-center.justify-content-between {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-bg-watermark" aria-hidden="true"></div>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/auth/auth-cover-login-bg.png" alt="" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5">
                    <div class="wd-50 mb-5">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <h2 class="fs-25 fw-bolder mb-4">Login</h2>
                    <h4 class="fs-15 fw-bold mb-2">Log in to your Clark College of Science and Technology internship account.</h4>

                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($login_error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($login_error); ?></div>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>

                    <form action="auth-login-cover.php" method="post" class="w-100 mt-4 pt-2">
                        <input type="hidden" name="next" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-4">
                            <input type="text" name="identifier" id="identifier" class="form-control" placeholder="Email or Username" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($_POST['identifier']) ? htmlspecialchars((string)$_POST['identifier']) : ''; ?>" required aria-required="true" aria-label="Email or Username" autofocus>
                        </div>
                        <div class="mb-3 input-group">
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required aria-required="true" aria-label="Password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password"><i></i></button>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe">
                                    <label class="form-check-label c-pointer" for="rememberMe">Remember Me</label>
                                </div>
                            </div>
                            <div>
                                <a href="auth-reset-cover.php" class="fs-11 text-primary">Forget password?</a>
                            </div>
                        </div>
                        <div class="mt-5">
                            <button type="submit" id="loginBtn" class="btn btn-lg btn-primary w-100">Login</button>
                        </div>
                    </form>

                    <div class="text-center text-muted my-4">or</div>
                    <a href="auth-register-creative.php?role=student" class="btn btn-lg btn-outline-primary w-100">Apply as Student</a>
                    <div class="text-center text-muted fs-11 mt-2">Student applications only. Staff accounts are created by the school administrator.</div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <script>
        // Minimal show/hide password toggle (kept per request)
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.getElementById('togglePassword');
            var pwd = document.getElementById('passwordInput');
            if (!toggle || !pwd) return;

            const eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

            const icon = toggle.querySelector('i');
            if (icon && !icon.innerHTML.trim()) {
                icon.innerHTML = eyeSVG;
                toggle.setAttribute('title', 'Show password');
                toggle.setAttribute('aria-label', 'Show password');
            }

            toggle.addEventListener('click', function () {
                var wasPassword = pwd.type === 'password';
                pwd.type = wasPassword ? 'text' : 'password';
                const icon = this.querySelector('i');
                if (icon) {
                    icon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
                    this.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');
                    this.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                }
            });
        });
    </script>
</body>
</html>




