<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/mailer.php';
require_once dirname(__DIR__) . '/lib/student-registration-verification.php';
biotern_boot_session($conn);

$logoutRequested = isset($_GET['logout']) && (string)$_GET['logout'] === '1';

$dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$project_root = '/';
$project_pos = stripos($script_name, '/BioTern/BioTern/');
if ($project_pos !== false) {
    $project_root = substr($script_name, 0, $project_pos) . '/BioTern/BioTern/';
}
$asset_prefix = $project_root;
$route_prefix = $project_root;
$favicon_ico_path = dirname(__DIR__) . '/assets/images/favicon.ico';
$favicon_png_path = dirname(__DIR__) . '/assets/images/favicon-rounded.png';
$favicon_logo_path = dirname(__DIR__) . '/assets/images/logo-abbr.png';
$favicon_ico_version = @filemtime($favicon_ico_path);
$favicon_png_version = @filemtime($favicon_png_path);
$favicon_logo_version = @filemtime($favicon_logo_path);
$smacss_css_path = dirname(__DIR__) . '/assets/css/smacss.css';
$auth_login_css_path = dirname(__DIR__) . '/assets/css/modules/auth/auth-login-cover.css';
$smacss_css_version = @filemtime($smacss_css_path);
$auth_login_css_version = @filemtime($auth_login_css_path);
if ($favicon_ico_version === false) {
    $favicon_ico_version = '20260318';
}
if ($favicon_png_version === false) {
    $favicon_png_version = '20260318';
}
if ($favicon_logo_version === false) {
    $favicon_logo_version = '20260318';
}
if ($smacss_css_version === false) {
    $smacss_css_version = '20260325';
}
if ($auth_login_css_version === false) {
    $auth_login_css_version = '20260325';
}
$login_error = '';
$login_notice = '';
$next = isset($_GET['next']) ? basename((string)$_GET['next']) : '';
if ($next !== '' && !preg_match('/^[A-Za-z0-9_-]+\.php$/', $next)) {
    $next = '';
}

if (isset($_GET['verified']) && (string)$_GET['verified'] === '1') {
    $login_notice = 'Your email is verified. You can log in now.';
}

if (isset($_GET['verify_sent']) && (string)$_GET['verify_sent'] === '1') {
    $login_notice = 'We sent a verification email to your inbox. Please click the button there before logging in.';
}

function log_login_attempt($mysqli, $userId, $identifier, $role, $status, $reason, $ip, $userAgent)
{
    if (!$mysqli) {
        return;
    }

    if (!ensure_login_logs_schema($mysqli)) {
        return;
    }

    $stmt = $mysqli->prepare("INSERT INTO login_logs (user_id, identifier, role, status, reason, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        return;
    }

    $uid = $userId > 0 ? (int)$userId : null;
    $identifier = substr((string)$identifier, 0, 191);
    $role = substr((string)$role, 0, 50);
    $status = substr((string)$status, 0, 20);
    $reason = substr((string)$reason, 0, 100);
    $ip = substr((string)$ip, 0, 45);
    $userAgent = substr((string)$userAgent, 0, 255);
    $stmt->bind_param('issssss', $uid, $identifier, $role, $status, $reason, $ip, $userAgent);
    $stmt->execute();
    $stmt->close();
}

function login_logs_has_column(mysqli $mysqli, string $column): bool
{
    $safeColumn = $mysqli->real_escape_string($column);
    $res = $mysqli->query("SHOW COLUMNS FROM login_logs LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function ensure_login_logs_schema(mysqli $mysqli): bool
{
    static $initialized = false;
    if ($initialized) {
        return true;
    }

    $created = $mysqli->query("CREATE TABLE IF NOT EXISTS login_logs (
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

    if (!$created) {
        return false;
    }

    $columnDefinitions = [
        'user_id' => "INT NULL",
        'identifier' => "VARCHAR(191) NULL",
        'role' => "VARCHAR(50) NULL",
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'failed'",
        'reason' => "VARCHAR(100) NULL",
        'ip_address' => "VARCHAR(45) NULL",
        'user_agent' => "VARCHAR(255) NULL",
        'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];

    foreach ($columnDefinitions as $column => $definition) {
        if (!login_logs_has_column($mysqli, $column)) {
            $safeColumn = str_replace('`', '``', $column);
            $mysqli->query("ALTER TABLE login_logs ADD COLUMN `{$safeColumn}` {$definition}");
        }
    }

    $mysqli->query("CREATE INDEX idx_login_logs_user_id ON login_logs (user_id)");
    $mysqli->query("CREATE INDEX idx_login_logs_status_created ON login_logs (status, created_at)");

    $initialized = true;
    return true;
}

function biotern_auth_client_ip(): string
{
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        return trim((string)($parts[0] ?? ''));
    }

    return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
}

function biotern_send_login_verification_email(mysqli $mysqli, int $userId, string $targetEmail, string $verifyToken, int $expiresAt): array
{
    if ($userId <= 0 || trim($targetEmail) === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'reference' => '', 'error' => 'Invalid email verification request.'];
    }

    $appBaseUrl = biotern_mail_asset_base();
    $verifyPath = 'auth-register-verify.php?login_token=' . rawurlencode($verifyToken) . '&approve=1';
    $verifyUrl = $appBaseUrl !== '' ? ($appBaseUrl . '/' . ltrim($verifyPath, '/')) : $verifyPath;
    $safeEmail = htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8');
    $safeVerifyUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
    $minutesLeft = max(1, (int)ceil(max(0, $expiresAt - time()) / 60));

    $subject = 'Verify your BioTern account email';
    $text = "Verify your BioTern account email by opening this link:\n{$verifyUrl}\n\nThis verification link expires in {$minutesLeft} minute(s).";

    $logoHtml = '';
    if ($appBaseUrl !== '') {
        $logoUrl = $appBaseUrl . '/assets/images/ccstlogo.png';
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="School logo" width="40" height="40" style="display:block;border-radius:8px;">';
    }

    $html = '
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b1220;padding:24px 0;font-family:Segoe UI,Arial,sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#111a2e;border:1px solid #1f2a44;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:20px 24px;background:linear-gradient(135deg,#162447,#111a2e);color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="font-size:18px;font-weight:700;">BioTern</div>
                                        <div style="font-size:13px;color:#a3b3cc;">Email Verification</div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">' . $logoHtml . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;color:#e5e7eb;">
                            <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Verify your email before logging in</div>
                            <div style="font-size:14px;color:#94a3b8;margin-bottom:18px;">
                                Before we let you access BioTern, please verify <strong style="color:#e5e7eb;">' . $safeEmail . '</strong>.
                            </div>
                            <div style="text-align:center;margin:24px 0;">
                                <a href="' . $safeVerifyUrl . '" style="display:inline-block;padding:14px 24px;border-radius:12px;background:#3454d1;border:1px solid #5d7df6;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">
                                    Verify Email
                                </a>
                            </div>
                            <div style="font-size:13px;color:#94a3b8;">
                                This verification link expires in ' . $minutesLeft . ' minute(s). If the button does not work, copy and open this link:<br>
                                <span style="display:inline-block;margin-top:8px;color:#cbd5e1;word-break:break-all;">' . $safeVerifyUrl . '</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';

    $mailRef = null;
    if (biotern_send_mail($mysqli, $targetEmail, $subject, $text, $html, $mailRef)) {
        return ['ok' => true, 'reference' => '', 'error' => ''];
    }

    return [
        'ok' => false,
        'reference' => (string)$mailRef,
        'error' => 'Unable to send the verification email right now.'
    ];
}

if ($logoutRequested) {
    $mysqli = $conn;
    if ($mysqli instanceof mysqli && !$mysqli->connect_errno) {
        ensure_login_logs_schema($mysqli);

        $logoutUserId = (int)($_SESSION['user_id'] ?? 0);
        $logoutIdentifier = trim((string)($_SESSION['username'] ?? $_SESSION['email'] ?? ''));
        $logoutRole = trim((string)($_SESSION['role'] ?? ''));
        $logoutIp = biotern_auth_client_ip();
        $logoutUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        log_login_attempt($mysqli, $logoutUserId, $logoutIdentifier, $logoutRole, 'success', 'logout_success', $logoutIp, $logoutUserAgent);
        biotern_login_session_revoke_current($mysqli, $logoutUserId, 'logout');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    biotern_clear_auth_cookie();
}

if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
    biotern_boot_session($conn);
}

if ((int)($_SESSION['user_id'] ?? 0) > 0 && !$logoutRequested) {
    header('Location: ' . $route_prefix . 'homepage.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['identifier']) ? trim((string)$_POST['identifier']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $client_ip = biotern_auth_client_ip();
    $client_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    $posted_next = isset($_POST['next']) ? basename((string)$_POST['next']) : '';
    if ($posted_next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $posted_next)) {
        $next = $posted_next;
    }

    if ($identifier === '' || $password === '') {
        $login_error = 'Please enter your Student ID Number or Username and password.';
    } else {
        $mysqli = $conn;

        if ($mysqli->connect_errno) {
            $login_error = 'Database connection failed.';
            error_log('BioTern login DB connect failed. host=' . $dbHost . ' db=' . $dbName . ' port=' . $dbPort . ' error=' . $mysqli->connect_error);
        } else {
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_submitted_at DATETIME NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approval_notes VARCHAR(255) NULL");
            $mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL");

            ensure_login_logs_schema($mysqli);
            biotern_login_email_verify_ensure_table($mysqli);

            $stmt = $mysqli->prepare("SELECT
                    u.id,
                    u.name,
                    u.username,
                    u.email,
                    u.password,
                    u.role,
                    u.is_active,
                    u.profile_picture,
                    u.email_verified_at,
                    COALESCE(u.application_status, 'approved') AS application_status
                FROM users u
                LEFT JOIN students s ON s.user_id = u.id
                WHERE
                    (u.role = 'student' AND s.student_id = ?)
                    OR (
                        u.role = 'student'
                        AND EXISTS (
                            SELECT 1
                            FROM students sx
                            WHERE sx.student_id = ?
                              AND (
                                  sx.user_id = u.id
                                  OR (sx.email IS NOT NULL AND sx.email <> '' AND sx.email = u.email)
                                  OR (u.username = sx.student_id)
                              )
                            LIMIT 1
                        )
                    )
                    OR (u.role = 'student' AND u.username = ?)
                    OR ((u.role = 'admin' OR u.role = 'coordinator' OR u.role = 'supervisor') AND u.username = ?)
                ORDER BY
                    CASE
                        WHEN u.role = 'student' AND s.student_id = ? THEN 0
                        WHEN u.role = 'student' AND u.username = ? THEN 1
                        WHEN u.role = 'student' THEN 2
                        ELSE 3
                    END,
                    u.id ASC
                LIMIT 1");

            if ($stmt) {
                $stmt->bind_param('ssssss', $identifier, $identifier, $identifier, $identifier, $identifier, $identifier);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    $stageStmt = $mysqli->prepare("SELECT status FROM student_applications WHERE student_id = ? ORDER BY submitted_at DESC LIMIT 1");
                    $stageStatus = '';
                    if ($stageStmt) {
                        $stageStmt->bind_param('s', $identifier);
                        $stageStmt->execute();
                        $stageRow = $stageStmt->get_result()->fetch_assoc();
                        $stageStmt->close();
                        if ($stageRow && isset($stageRow['status'])) {
                            $stageStatus = strtolower((string)$stageRow['status']);
                        }
                    }

                    if ($stageStatus === 'pending') {
                        $login_error = 'Your registration is pending approval.';
                        log_login_attempt($mysqli, 0, $identifier, 'student', 'failed', 'pending_approval', $client_ip, $client_user_agent);
                    } elseif ($stageStatus === 'rejected') {
                        $login_error = 'Your registration was rejected. Please contact an administrator.';
                        log_login_attempt($mysqli, 0, $identifier, 'student', 'failed', 'rejected_application', $client_ip, $client_user_agent);
                    } elseif ($stageStatus === 'approved') {
                        $login_error = 'Your account is pending activation. Please contact an administrator.';
                        log_login_attempt($mysqli, 0, $identifier, 'student', 'failed', 'pending_activation', $client_ip, $client_user_agent);
                    } else {
                        $login_error = 'Invalid Student ID Number, Username, or password.';
                        log_login_attempt($mysqli, 0, $identifier, '', 'failed', 'invalid_credentials', $client_ip, $client_user_agent);
                    }
                } elseif ((int)($user['is_active'] ?? 0) !== 1) {
                    $login_error = 'Your account is inactive.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'inactive_account', $client_ip, $client_user_agent);
                } else {
                    $storedPassword = (string)($user['password'] ?? '');
                    $passwordMatches = password_verify($password, $storedPassword);
                    $usedLegacyPlaintext = false;

                    if (!$passwordMatches && $storedPassword !== '' && hash_equals($storedPassword, $password)) {
                        $passwordMatches = true;
                        $usedLegacyPlaintext = true;
                    }

                    if ($passwordMatches && $usedLegacyPlaintext) {
                        $rehash = password_hash($password, PASSWORD_DEFAULT);
                        if ($rehash !== false) {
                            $rehashStmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
                            if ($rehashStmt) {
                                $rehashUserId = (int)$user['id'];
                                $rehashStmt->bind_param('si', $rehash, $rehashUserId);
                                $rehashStmt->execute();
                                $rehashStmt->close();
                            }
                        }
                    }

                    if (!$passwordMatches) {
                    $login_error = 'Invalid Student ID Number, Username, or password.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'invalid_credentials', $client_ip, $client_user_agent);
                } elseif (strtolower((string)($user['application_status'] ?? 'approved')) === 'pending') {
                    $login_error = 'Your registration is pending approval.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'pending_approval', $client_ip, $client_user_agent);
                } elseif (strtolower((string)($user['application_status'] ?? 'approved')) === 'rejected') {
                    $login_error = 'Your registration was rejected. Please contact an administrator.';
                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'rejected_application', $client_ip, $client_user_agent);
                } elseif ((string)($user['role'] ?? '') === 'student' && trim((string)($user['email_verified_at'] ?? '')) === '') {
                    $verifyToken = biotern_student_reg_generate_token();
                    $verifyExpiresAt = time() + 900;
                    $targetEmail = trim((string)($user['email'] ?? ''));
                    $verifyStored = biotern_login_email_verify_store($mysqli, $verifyToken, (int)$user['id'], $targetEmail, $verifyExpiresAt);
                    if (!$verifyStored) {
                        $login_error = 'We could not prepare email verification right now. Please try again.';
                        log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'email_verify_store_failed', $client_ip, $client_user_agent);
                    } else {
                        $sendResult = biotern_send_login_verification_email($mysqli, (int)$user['id'], $targetEmail, $verifyToken, $verifyExpiresAt);
                        if (empty($sendResult['ok'])) {
                            $login_error = 'Unable to send the verification email right now. Please try again.';
                            if (!empty($sendResult['reference'])) {
                                $login_error .= ' Reference: ' . $sendResult['reference'];
                            }
                            log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'email_verify_send_failed', $client_ip, $client_user_agent);
                        } else {
                            log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'failed', 'email_verification_required', $client_ip, $client_user_agent);
                            header('Location: ' . $route_prefix . 'auth/auth-register-verify.php?login_token=' . rawurlencode($verifyToken));
                            exit;
                        }
                    }
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = (string)$user['name'];
                    $_SESSION['username'] = (string)$user['username'];
                    $_SESSION['email'] = (string)$user['email'];
                    $_SESSION['role'] = (string)$user['role'];
                    $_SESSION['profile_picture'] = (string)($user['profile_picture'] ?? '');
                    $_SESSION['logged_in'] = true;
                    biotern_set_auth_cookie((int)$user['id']);
                    biotern_login_session_start($mysqli, (int)$user['id']);

                    log_login_attempt($mysqli, (int)$user['id'], $identifier, (string)($user['role'] ?? ''), 'success', 'login_success', $client_ip, $client_user_agent);

                    $target = $next !== '' ? ($route_prefix . $next) : ($route_prefix . 'homepage.php');
                    session_write_close();
                    header('Location: ' . $target);
                    exit;
                }
                }
            } else {
                $login_error = 'Login query preparation failed.';
            }
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
    <title>BioTern || Login</title>
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png?v=<?php echo rawurlencode((string)$favicon_logo_version); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-rounded.png?v=<?php echo rawurlencode((string)$favicon_png_version); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png?v=<?php echo rawurlencode((string)$favicon_logo_version); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=<?php echo rawurlencode((string)$favicon_ico_version); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=<?php echo rawurlencode((string)$favicon_ico_version); ?>">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/shared/theme-state-core.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css?v=<?php echo rawurlencode((string)$smacss_css_version); ?>">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/state/notification-skin.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/auth/auth-login-cover.css?v=<?php echo rawurlencode((string)$auth_login_css_version); ?>">
</head>
<body class="auth-login-page">
    <div class="login-bg-watermark" aria-hidden="true"></div>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img auth-login-visual" aria-hidden="true"></div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5">
                    <div class="auth-brand-lockup mb-5">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/ccstlogo.png" alt="Clark College of Science and Technology" class="auth-brand-lockup-school">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-full-header.png" alt="BioTern" class="auth-brand-lockup-app">
                    </div>
                    <h2 class="fs-25 fw-bolder mb-4">Login</h2>
                    <h4 class="fs-15 fw-bold mb-2">Log in to your Clark College of Science and Technology internship account.</h4>

                    <form id="authLoginForm" action="<?php echo htmlspecialchars($route_prefix . 'auth/auth-login.php', ENT_QUOTES, 'UTF-8'); ?>" method="post" class="w-100 mt-4 pt-2" novalidate>
                        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($login_error !== ''): ?>
                        <div class="app-theme-notify-inline app-theme-notify-inline--error auth-login-form-error" role="alert" aria-live="assertive">
                            <?php echo htmlspecialchars((string)$login_error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($login_notice !== ''): ?>
                        <div class="app-theme-notify-inline app-theme-notify-inline--success auth-login-form-error" role="status" aria-live="polite">
                            <?php echo htmlspecialchars((string)$login_notice, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="mb-4">
                            <input type="text" name="identifier" id="identifier" class="form-control" placeholder="Student ID Number or Username" value="<?php echo isset($_POST['identifier']) ? htmlspecialchars((string)$_POST['identifier']) : ''; ?>" required aria-required="true" aria-label="Student ID Number or Username" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus>
                        </div>
                        <div class="mb-3">
                            <div class="input-group">
                                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required aria-required="true" aria-label="Password" autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password"><i></i></button>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me" value="1"<?php echo !empty($_POST['remember_me']) ? ' checked' : ''; ?>>
                                    <label class="form-check-label c-pointer" for="rememberMe">Remember Me</label>
                                </div>
                            </div>
                            <div>
                                <a href="<?php echo htmlspecialchars($route_prefix . 'auth/auth-reset-cover.php', ENT_QUOTES, 'UTF-8'); ?>" class="fs-11 text-primary">Forget password?</a>
                            </div>
                        </div>
                        <div class="mt-5">
                            <button type="submit" id="loginBtn" class="btn btn-lg btn-primary w-100">Login</button>
                        </div>
                    </form>

                    <div class="text-center text-muted my-4">or</div>
                    <a href="<?php echo htmlspecialchars($route_prefix . 'auth/auth-register.php?role=student', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-lg btn-outline-primary w-100">Apply as Student</a>
                    <div class="text-center text-muted fs-11 mt-2">Student applications only. Staff accounts are created by the school administrator.</div>
                    <ul class="text-start text-muted fs-12 mt-3 mb-0 auth-login-guidance">
                        <li>Students: Login using Student ID Number and password only.</li>
                        <li>Admin, Coordinator, Supervisor: Login using Username and password only.</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/auth/auth-login-cover.js"></script>
</body>
</html>
