<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/two-factor-auth.php';
biotern_boot_session($conn);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$project_root = '/';
$project_pos = stripos($script_name, '/BioTern/BioTern/');
if ($project_pos !== false) {
    $project_root = substr($script_name, 0, $project_pos) . '/BioTern/BioTern/';
}
$asset_prefix = $project_root;
$route_prefix = $project_root;

if ((int)($_SESSION['user_id'] ?? 0) > 0) {
    header('Location: ' . $route_prefix . 'homepage.php');
    exit;
}

$favicon_ico_path = dirname(__DIR__) . '/assets/images/favicon.ico';
$favicon_png_path = dirname(__DIR__) . '/assets/images/favicon-rounded.png';
$favicon_logo_path = dirname(__DIR__) . '/assets/images/logo-abbr.png';
$favicon_ico_version = @filemtime($favicon_ico_path);
$favicon_png_version = @filemtime($favicon_png_path);
$favicon_logo_version = @filemtime($favicon_logo_path);
if ($favicon_ico_version === false) {
    $favicon_ico_version = '20260318';
}
if ($favicon_png_version === false) {
    $favicon_png_version = '20260318';
}
if ($favicon_logo_version === false) {
    $favicon_logo_version = '20260318';
}

function biotern_two_factor_page_user(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare('SELECT id, name, username, email, role, is_active, profile_picture FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

function biotern_two_factor_page_log_has_column(mysqli $mysqli, string $column): bool
{
    $safeColumn = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM login_logs LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function biotern_two_factor_page_ensure_login_logs(mysqli $mysqli): bool
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
        'user_id' => 'INT NULL',
        'identifier' => 'VARCHAR(191) NULL',
        'role' => 'VARCHAR(50) NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'failed'",
        'reason' => 'VARCHAR(100) NULL',
        'ip_address' => 'VARCHAR(45) NULL',
        'user_agent' => 'VARCHAR(255) NULL',
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($columnDefinitions as $column => $definition) {
        if (!biotern_two_factor_page_log_has_column($mysqli, $column)) {
            $safeColumn = str_replace('`', '``', $column);
            $mysqli->query("ALTER TABLE login_logs ADD COLUMN `{$safeColumn}` {$definition}");
        }
    }

    $mysqli->query('CREATE INDEX idx_login_logs_user_id ON login_logs (user_id)');
    $mysqli->query('CREATE INDEX idx_login_logs_status_created ON login_logs (status, created_at)');

    $initialized = true;
    return true;
}

function biotern_two_factor_page_log_attempt(mysqli $mysqli, int $userId, string $identifier, string $role, string $status, string $reason): void
{
    if (!biotern_two_factor_page_ensure_login_logs($mysqli)) {
        return;
    }

    $stmt = $mysqli->prepare('INSERT INTO login_logs (user_id, identifier, role, status, reason, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return;
    }

    $uid = $userId > 0 ? $userId : null;
    $identifier = substr($identifier, 0, 191);
    $role = substr($role, 0, 50);
    $status = substr($status, 0, 20);
    $reason = substr($reason, 0, 100);
    $ip = substr(biotern_two_factor_client_ip(), 0, 45);
    $userAgent = substr(biotern_two_factor_user_agent(), 0, 255);

    $stmt->bind_param('issssss', $uid, $identifier, $role, $status, $reason, $ip, $userAgent);
    $stmt->execute();
    $stmt->close();
}

$twoFactorError = '';
$twoFactorNotice = '';
if (!empty($_SESSION['two_factor_notice'])) {
    $twoFactorNotice = trim((string)$_SESSION['two_factor_notice']);
    unset($_SESSION['two_factor_notice']);
}

$pending = biotern_two_factor_get_pending_login();
if (!is_array($pending)) {
    header('Location: ' . $route_prefix . 'auth/auth-login.php');
    exit;
}

$pendingUserId = (int)($pending['user_id'] ?? 0);
if ($pendingUserId <= 0) {
    biotern_two_factor_clear_pending_login();
    header('Location: ' . $route_prefix . 'auth/auth-login.php');
    exit;
}

$pendingUser = biotern_two_factor_page_user($conn, $pendingUserId);
if (!$pendingUser || (int)($pendingUser['is_active'] ?? 0) !== 1) {
    biotern_two_factor_clear_pending_login();
    header('Location: ' . $route_prefix . 'auth/auth-login.php');
    exit;
}

if (!biotern_two_factor_is_enabled($conn, $pendingUserId)) {
    biotern_two_factor_clear_pending_login();
    header('Location: ' . $route_prefix . 'auth/auth-login.php');
    exit;
}

$maskedEmail = biotern_two_factor_mask_email((string)($pendingUser['email'] ?? ''));
$identifier = trim((string)($pending['identifier'] ?? (string)($pendingUser['username'] ?? '')));
$pendingNext = basename((string)($pending['next'] ?? ''));
if ($pendingNext !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $pendingNext) !== 1) {
    $pendingNext = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? 'verify')));

    if ($action === 'cancel') {
        biotern_two_factor_clear_pending_login();
        header('Location: ' . $route_prefix . 'auth/auth-login.php');
        exit;
    }

    if ($action === 'resend') {
        $targetEmail = trim((string)($pendingUser['email'] ?? ''));
        if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $twoFactorError = 'No valid email address is available for this account.';
            biotern_two_factor_page_log_attempt($conn, $pendingUserId, $identifier, (string)($pendingUser['role'] ?? ''), 'failed', 'two_factor_email_invalid');
        } else {
            $mailRef = '';
            $issueResult = biotern_two_factor_issue_login_code($conn, $pendingUserId, $targetEmail, $mailRef);
            if (empty($issueResult['ok'])) {
                $twoFactorError = (string)($issueResult['error'] ?? 'Unable to resend the verification code right now.');
                $issueRef = trim((string)($issueResult['reference'] ?? $mailRef));
                if ($issueRef !== '') {
                    $twoFactorError .= ' Reference: ' . $issueRef;
                }
                biotern_two_factor_page_log_attempt($conn, $pendingUserId, $identifier, (string)($pendingUser['role'] ?? ''), 'failed', 'two_factor_resend_failed');
            } else {
                $maskedEmail = (string)($issueResult['masked_email'] ?? biotern_two_factor_mask_email($targetEmail));
                $twoFactorNotice = 'A new verification code was sent to ' . $maskedEmail . '.';
                biotern_two_factor_prepare_pending_login($pendingUserId, $identifier, $pendingNext, !empty($pending['remember_me']));
                biotern_two_factor_page_log_attempt($conn, $pendingUserId, $identifier, (string)($pendingUser['role'] ?? ''), 'failed', 'two_factor_code_resent');
            }
        }
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '') {
            $digits = [];
            for ($i = 1; $i <= 6; $i++) {
                $digit = trim((string)($_POST['digit' . $i] ?? ''));
                $digits[] = preg_match('/^[0-9]$/', $digit) ? $digit : '';
            }
            $code = implode('', $digits);
        }

        $verifyResult = biotern_two_factor_verify_login_code($conn, $pendingUserId, $code);
        if (empty($verifyResult['ok'])) {
            $twoFactorError = (string)($verifyResult['message'] ?? 'Invalid verification code. Please try again.');
            $reason = 'two_factor_invalid_code';
            if (!empty($verifyResult['reason'])) {
                $reason = 'two_factor_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$verifyResult['reason']));
            }
            biotern_two_factor_page_log_attempt($conn, $pendingUserId, $identifier, (string)($pendingUser['role'] ?? ''), 'failed', $reason);
        } else {
            $freshUser = biotern_two_factor_page_user($conn, $pendingUserId);
            if (!$freshUser || (int)($freshUser['is_active'] ?? 0) !== 1) {
                biotern_two_factor_clear_pending_login();
                header('Location: ' . $route_prefix . 'auth/auth-login.php');
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$freshUser['id'];
            $_SESSION['name'] = (string)$freshUser['name'];
            $_SESSION['username'] = (string)$freshUser['username'];
            $_SESSION['email'] = (string)$freshUser['email'];
            $_SESSION['role'] = (string)$freshUser['role'];
            $_SESSION['profile_picture'] = (string)($freshUser['profile_picture'] ?? '');
            $_SESSION['logged_in'] = true;
            $rememberMe = !empty($pending['remember_me']);
            biotern_set_auth_cookie((int)$freshUser['id'], $rememberMe);
            biotern_login_session_start($conn, (int)$freshUser['id'], $rememberMe);
            biotern_two_factor_clear_pending_login();

            biotern_two_factor_page_log_attempt($conn, (int)$freshUser['id'], $identifier, (string)($freshUser['role'] ?? ''), 'success', 'login_success_2fa');

            $target = $pendingNext !== '' ? ($route_prefix . $pendingNext) : ($route_prefix . 'homepage.php');
            session_write_close();
            header('Location: ' . $target);
            exit;
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
    <title>BioTern || Two-factor Authentication</title>
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png?v=<?php echo rawurlencode((string)$favicon_logo_version); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-rounded.png?v=<?php echo rawurlencode((string)$favicon_png_version); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png?v=<?php echo rawurlencode((string)$favicon_logo_version); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=<?php echo rawurlencode((string)$favicon_ico_version); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=<?php echo rawurlencode((string)$favicon_ico_version); ?>">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/shared/theme-state-core.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/state/notification-skin.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/auth/auth-login-cover.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/auth/auth-recovery-cover.css">
</head>

<body class="auth-login-page auth-recovery-page">
    <div class="login-bg-watermark recovery-bg-watermark" aria-hidden="true"></div>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img auth-login-visual" aria-hidden="true"></div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5 auth-recovery-card">
                    <div class="auth-brand-lockup mb-5">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/ccstlogo.png" alt="Clark College of Science and Technology" class="auth-brand-lockup-school">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-full-header.png" alt="BioTern" class="auth-brand-lockup-app">
                    </div>
                    <h2 class="fs-25 fw-bolder mb-3">Two-factor verification</h2>
                    <h4 class="fs-15 fw-bold mb-2">Enter the 6-digit verification code to finish signing in.</h4>
                    <p class="fs-12 fw-medium text-muted"><span>Code sent to</span> <strong><?php echo htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8'); ?></strong></p>

                    <form method="post" class="w-100 mt-4 pt-2" autocomplete="one-time-code">
                        <input type="hidden" name="action" value="verify">
                        <?php if ($twoFactorError !== ''): ?>
                            <div class="app-theme-notify-inline app-theme-notify-inline--error auth-login-form-error" role="alert" aria-live="assertive">
                                <?php echo htmlspecialchars($twoFactorError, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($twoFactorNotice !== ''): ?>
                            <div class="app-theme-notify-inline app-theme-notify-inline--info auth-login-form-error" role="status" aria-live="polite">
                                <?php echo htmlspecialchars($twoFactorNotice, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <div id="otp" class="auth-otp-grid mt-2">
                            <input name="digit1" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="one-time-code" required>
                            <input name="digit2" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" required>
                            <input name="digit3" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" required>
                            <input name="digit4" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" required>
                            <input name="digit5" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" required>
                            <input name="digit6" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" required>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="btn btn-lg btn-primary w-100">Verify and continue</button>
                        </div>
                    </form>

                    <div class="auth-recovery-links mt-4">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="resend">
                            <button type="submit" class="btn btn-link p-0 fs-12">Resend code</button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-link p-0 fs-12 text-muted">Back to login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var otpWrap = document.getElementById('otp');
            var inputs = Array.prototype.slice.call(document.querySelectorAll('#otp input'));
            if (!otpWrap || !inputs.length) {
                return;
            }

            function focusIndex(index) {
                if (index >= 0 && index < inputs.length) {
                    inputs[index].focus();
                    inputs[index].select();
                }
            }

            function fillFromDigits(raw) {
                var digits = (raw || '').replace(/\D/g, '').slice(0, 6).split('');
                if (!digits.length) {
                    return false;
                }

                for (var i = 0; i < inputs.length; i++) {
                    inputs[i].value = digits[i] || '';
                }

                var nextEmpty = inputs.findIndex(function (el) {
                    return !el.value;
                });
                focusIndex(nextEmpty === -1 ? inputs.length - 1 : nextEmpty);
                return true;
            }

            inputs.forEach(function (input, idx) {
                input.addEventListener('input', function () {
                    var value = (this.value || '').replace(/\D/g, '');
                    if (value.length > 1 && fillFromDigits(value)) {
                        return;
                    }

                    this.value = value ? value.charAt(0) : '';
                    if (this.value && idx < inputs.length - 1) {
                        focusIndex(idx + 1);
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.ctrlKey || event.metaKey) {
                        return;
                    }
                    if (event.key === 'Backspace' && !this.value && idx > 0) {
                        focusIndex(idx - 1);
                    }
                    if (event.key.length === 1 && /\D/.test(event.key)) {
                        event.preventDefault();
                    }
                });

                input.addEventListener('paste', function (event) {
                    var pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
                    if (!pasted) {
                        return;
                    }
                    event.preventDefault();
                    fillFromDigits(pasted);
                });
            });

            function handleGlobalPaste(event) {
                var target = event.target;
                if (!target || !otpWrap.contains(target)) {
                    return;
                }
                var pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
                if (!pasted) {
                    return;
                }
                event.preventDefault();
                fillFromDigits(pasted);
            }

            otpWrap.addEventListener('paste', handleGlobalPaste, true);
            document.addEventListener('paste', handleGlobalPaste, true);
            inputs[0].focus();
        });
    </script>
</body>

</html>
