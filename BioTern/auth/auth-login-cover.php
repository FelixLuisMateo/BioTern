<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['logout']) && (string)$_GET['logout'] === '1') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;
$login_error = '';
$next = isset($_GET['next']) ? basename((string)$_GET['next']) : '';
if ($next !== '' && !preg_match('/^[A-Za-z0-9_-]+\.php$/', $next)) {
    $next = '';
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
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($mysqli->connect_errno) {
            $login_error = 'Database connection failed.';
        } else {
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_submitted_at DATETIME NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approval_notes VARCHAR(255) NULL");

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
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/auth-login-cover.css">
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

                    <?php if ($login_error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>

                    <form action="auth-login-cover.php" method="post" class="w-100 mt-4 pt-2">
                        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-4">
                            <input type="text" name="identifier" id="identifier" class="form-control" placeholder="Email or Username" value="<?php echo isset($_POST['identifier']) ? htmlspecialchars((string)$_POST['identifier']) : ''; ?>" required aria-required="true" aria-label="Email or Username" autofocus>
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
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/auth-login-cover.js"></script>
</body>
</html>




