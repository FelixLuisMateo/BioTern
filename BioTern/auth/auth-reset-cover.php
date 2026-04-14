<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/mailer.php';

$composer_missing = false;
$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer_autoload)) {
    require_once $composer_autoload;
} else {
    $manualMailerPath = dirname(__DIR__) . '/lib/phpmailer/PHPMailer.php';
    $manualExceptionPath = dirname(__DIR__) . '/lib/phpmailer/Exception.php';
    $manualSmtpPath = dirname(__DIR__) . '/lib/phpmailer/SMTP.php';
    if (is_file($manualMailerPath) && is_file($manualExceptionPath) && is_file($manualSmtpPath)) {
        require_once $manualExceptionPath;
        require_once $manualSmtpPath;
        require_once $manualMailerPath;
    } else {
        $composer_missing = true;
    }
}

$reset_message = '';
$reset_error = '';
$reset_toast_type = '';
$reset_toast_message = '';
$identifier_value = '';
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;

function logOtpMailError(string $reason): string
{
    try {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    } catch (Throwable $e) {
        $suffix = strtoupper(substr((string)mt_rand(100000, 999999), 0, 6));
    }

    $ref = 'OTP-' . date('Ymd-His') . '-' . $suffix;
    $logDir = __DIR__ . '/storage/logs';
    $logFile = $logDir . '/otp_mail.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [' . $ref . '] ' . $reason . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);

    $_SESSION['password_reset_last_error_ref'] = $ref;
    return $ref;
}

function generateResetCode(): string
{
    try {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function sendResetCodeEmail(string $email, string $code): bool
{
    global $composer_missing, $conn;
    $subject = 'Your password reset verification code';
    $textBody = "Your verification code is: {$code}\n\nIf you did not request this, please ignore this message.";
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $appBaseUrl = biotern_mail_asset_base();
    $logoHtml = '';
    if ($appBaseUrl !== '') {
        $logoUrl = $appBaseUrl . '/assets/images/ccstlogo.png';
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="School logo" width="40" height="40" style="display:block;border-radius:8px;">';
    }
    $htmlBody = '
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
                                        <div style="font-size:13px;color:#a3b3cc;">Account Security</div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">' . $logoHtml . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;color:#e5e7eb;">
                            <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Reset your password</div>
                            <div style="font-size:14px;color:#94a3b8;margin-bottom:18px;">
                                We received a reset request for <strong style="color:#e5e7eb;">' . $safeEmail . '</strong>.
                            </div>
                            <div style="text-align:center;margin:20px 0;">
                                <div style="display:inline-block;padding:12px 20px;border-radius:12px;background:#0f172a;border:1px solid #263453;font-size:24px;letter-spacing:6px;font-weight:700;color:#ffffff;">
                                    ' . $safeCode . '
                                </div>
                            </div>
                            <div style="font-size:13px;color:#94a3b8;">
                                Enter this code to continue. If you did not request this, you can ignore this email.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px;border-top:1px solid #1f2a44;color:#6b7a99;font-size:12px;">
                            For security, do not share this code with anyone.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';
    $errorRef = null;
    $sent = biotern_send_mail($conn, $email, $subject, $textBody, $htmlBody, $errorRef);
    if ($sent) {
        unset($_SESSION['password_reset_last_error_ref']);
        return true;
    }
    if ($errorRef) {
        $_SESSION['password_reset_last_error_ref'] = $errorRef;
    }
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        $composer_missing = true;
    }
    return false;
}

function storeResetSession(string $email, int $userId, string $code, bool $sent): void
{
    $_SESSION['password_reset_contact'] = $email;
    $_SESSION['password_reset_user_id'] = $userId;
    $_SESSION['password_reset_code'] = $code;
    $_SESSION['password_reset_code_sent_at'] = time();
    $_SESSION['password_reset_verified'] = false;
    $_SESSION['password_reset_last_sent_ok'] = $sent ? 1 : 0;
}

if (isset($_GET['resend']) && intval($_GET['resend']) === 1) {
    $email = isset($_SESSION['password_reset_contact']) ? trim((string)$_SESSION['password_reset_contact']) : '';
    $userId = isset($_SESSION['password_reset_user_id']) ? (int)$_SESSION['password_reset_user_id'] : 0;

    if ($email === '') {
        $reset_error = 'No active password reset request found to resend.';
    } else {
        $code = generateResetCode();
        $sentEmail = sendResetCodeEmail($email, $code);
        if ($sentEmail) {
            storeResetSession($email, $userId, $code, true);
            header('Location: auth-verify-cover.php?resent=1');
            exit;
        }
        if ($composer_missing) {
            $reset_error = 'Email system is not installed. Please run composer install on the server.';
            $reset_toast_type = 'error';
            $reset_toast_message = $reset_error;
            unset($_SESSION['password_reset_last_error_ref']);
            header('Location: auth-verify-cover.php?send_error=1');
            exit;
        }
        header('Location: auth-verify-cover.php?send_error=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifier'])) {
    $identifier = trim((string)$_POST['identifier']);
    $identifier_value = $identifier;

    if ($identifier === '') {
        $reset_error = 'Please provide your email.';
    } else {
        $mysqli = $conn;
        if ($mysqli->connect_errno) {
            $reset_error = 'Database connection failed.';
        } else {
            $stmt = $mysqli->prepare('SELECT id, email, is_active FROM users WHERE email = ? LIMIT 1');
            if (!$stmt) {
                $reset_error = 'Unable to process reset request right now.';
            } else {
                $stmt->bind_param('s', $identifier);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user || empty($user['email'])) {
                    $reset_error = 'No account found for that email.';
                } elseif ((int)($user['is_active'] ?? 0) !== 1) {
                    $reset_error = 'This account is inactive.';
                } else {
                    $code = generateResetCode();
                    $email = (string)$user['email'];
                    $userId = (int)$user['id'];

                    $sentEmail = sendResetCodeEmail($email, $code);
                    if ($sentEmail) {
                        storeResetSession($email, $userId, $code, true);
                        header('Location: auth-verify-cover.php?sent=1');
                        exit;
                    }
                    if ($composer_missing) {
                        $reset_error = 'Email system is not installed. Please run composer install on the server.';
                    } else {
                    $errorRef = isset($_SESSION['password_reset_last_error_ref']) ? (string)$_SESSION['password_reset_last_error_ref'] : '';
                    $reset_error = 'Unable to send verification code. Check SMTP settings and Gmail App Password.'
                        . ($errorRef !== '' ? ' (Ref: ' . $errorRef . ')' : '');
                }
            }
        }
    }
    }
}

if ($reset_error !== '') {
    $reset_toast_type = 'error';
    $reset_toast_message = $reset_error;
} elseif ($reset_message !== '') {
    $reset_toast_type = 'success';
    $reset_toast_message = $reset_message;
}
?>
<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Reset Cover</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/state/notification-skin.css">
</head>

<body>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/auth/auth-cover-reset-bg.svg" alt="" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5">
                    <div class="wd-50 mb-5">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <h2 class="fs-20 fw-bolder mb-4">Reset</h2>
                    <h4 class="fs-13 fw-bold mb-2">Reset your password</h4>
                    <p class="fs-12 fw-medium text-muted">Enter your email and we'll send a verification code to your registered email.</p>

                    <form method="post" action="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-reset-cover.php" class="w-100 mt-4 pt-2">
                        <div class="mb-4">
                            <input name="identifier" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($identifier_value, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="btn btn-lg btn-primary w-100">Reset Now</button>
                        </div>
                    </form>
                    <div class="mt-5 text-muted">
                        <span>Remembered your password?</span>
                        <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-login.php" class="fw-bold">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <?php if ($reset_toast_message !== ''): ?>
    <script>
    (function () {
        var payload = {
            type: <?php echo json_encode($reset_toast_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            message: <?php echo json_encode($reset_toast_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        };
        if (!payload.message) {
            return;
        }

        var variantMap = { success: 'success', info: 'info', warning: 'warning', danger: 'error', error: 'error' };
        var iconMap = {
            success: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 7 9 18l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 10v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 7h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M15 9 9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
        };

        var variant = variantMap[payload.type] || 'info';
        var root = document.body || document.documentElement;
        if (!root) {
            return;
        }

        var toast = document.createElement('div');
        toast.id = 'authResetCoverToast';
        toast.className = 'app-theme-toast-static app-theme-toast-static--' + variant;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        var iconWrap = document.createElement('span');
        iconWrap.className = 'app-theme-toast-static-icon';
        var iconEl = document.createElement('span');
        iconEl.className = 'app-theme-toast-static-icon-glyph';
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.innerHTML = iconMap[variant] || iconMap.info;
        iconWrap.appendChild(iconEl);

        var textWrap = document.createElement('span');
        textWrap.className = 'app-theme-toast-static-text';
        textWrap.textContent = String(payload.message);

        toast.appendChild(iconWrap);
        toast.appendChild(textWrap);
        root.appendChild(toast);

        window.setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5200);
    })();
    </script>
    <?php endif; ?>
</body>

</html>



