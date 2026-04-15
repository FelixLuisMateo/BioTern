<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$verify_error = '';
$verify_message = '';
$verify_toast_type = '';
$verify_toast_message = '';
$otpTtlSeconds = 600;
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;

$contactRaw = isset($_SESSION['password_reset_contact']) ? trim((string)$_SESSION['password_reset_contact']) : '';
$expected = isset($_SESSION['password_reset_code']) ? (string)$_SESSION['password_reset_code'] : '';
$sentAt = isset($_SESSION['password_reset_code_sent_at']) ? (int)$_SESSION['password_reset_code_sent_at'] : 0;
$lastSentOk = isset($_SESSION['password_reset_last_sent_ok']) ? (int)$_SESSION['password_reset_last_sent_ok'] : 0;

if ($contactRaw !== '' && filter_var($contactRaw, FILTER_VALIDATE_EMAIL)) {
    $parts = explode('@', $contactRaw, 2);
    $local = $parts[0];
    $domain = $parts[1] ?? '';
    $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 2, 1)) . substr($local, -1);
    $masked_contact = htmlspecialchars($maskedLocal . '@' . $domain, ENT_QUOTES, 'UTF-8');
} elseif ($contactRaw !== '') {
    $masked_contact = htmlspecialchars(substr($contactRaw, 0, 1) . str_repeat('*', max(strlen($contactRaw) - 2, 1)) . substr($contactRaw, -1), ENT_QUOTES, 'UTF-8');
} else {
    $masked_contact = 'your registered email';
}

if (isset($_GET['send_error']) && (int)$_GET['send_error'] === 1) {
    $errorRef = isset($_SESSION['password_reset_last_error_ref']) ? (string)$_SESSION['password_reset_last_error_ref'] : '';
    $verify_error = 'Unable to send verification code. Please check SMTP settings and try again.'
        . ($errorRef !== '' ? ' (Ref: ' . $errorRef . ')' : '');
} elseif (isset($_GET['sent']) || isset($_GET['resent'])) {
    if ($expected === '' || $sentAt <= 0) {
        $verify_error = 'No active password reset request found. Please request a password reset first.';
    } elseif ($lastSentOk === 1) {
        $verify_message = isset($_GET['resent'])
            ? 'A new verification code has been sent to your email.'
            : 'A verification code has been sent to your email.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digits = [];
    for ($i = 1; $i <= 6; $i++) {
        $k = 'digit' . $i;
        $val = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
        $digits[] = preg_match('/^[0-9]$/', $val) ? $val : '';
    }
    $code = implode('', $digits);

    if ($expected === '' || $sentAt <= 0) {
        $verify_error = 'No active password reset request found. Please request a password reset first.';
    } elseif (strlen($code) !== 6 || !preg_match('/^[0-9]{6}$/', $code)) {
        $verify_error = 'Please enter the 6-digit verification code.';
    } elseif ((time() - $sentAt) > $otpTtlSeconds) {
        $verify_error = 'Verification code expired. Please click Resend to get a new code.';
    } elseif (hash_equals($expected, $code)) {
        $_SESSION['password_reset_verified'] = true;
        unset($_SESSION['password_reset_code']);
        unset($_SESSION['password_reset_code_sent_at']);
        unset($_SESSION['password_reset_last_sent_ok']);

        header('Location: auth-resetting-minimal.php');
        exit;
    } else {
        $verify_error = 'Invalid verification code. Please try again.';
    }
}

if ($verify_error !== '') {
    $verify_toast_type = 'error';
    $verify_toast_message = $verify_error;
} elseif ($verify_message !== '') {
    $verify_toast_type = 'info';
    $verify_toast_message = $verify_message;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Verify Code</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico">
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
                    <h2 class="fs-25 fw-bolder mb-3">Verify Code</h2>
                    <h4 class="fs-15 fw-bold mb-2">Enter the 6-digit verification code sent to your account.</h4>
                    <p class="fs-12 fw-medium text-muted"><span>Code sent to</span> <strong><?php echo $masked_contact; ?></strong></p>

                    <form method="post" class="w-100 mt-4 pt-2" autocomplete="one-time-code">
                        <div id="otp" class="auth-otp-grid mt-2">
                            <input name="digit1" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit1" maxlength="1" autocomplete="one-time-code" required>
                            <input name="digit2" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit2" maxlength="1" required>
                            <input name="digit3" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit3" maxlength="1" required>
                            <input name="digit4" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit4" maxlength="1" required>
                            <input name="digit5" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit5" maxlength="1" required>
                            <input name="digit6" class="text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit6" maxlength="1" required>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="btn btn-lg btn-primary w-100">Validate Code</button>
                        </div>
                        <div class="auth-recovery-links mt-4">
                            <span class="text-muted">Didn't get the code?</span>
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-reset-cover.php?resend=1">Resend</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <?php if ($verify_toast_message !== ''): ?>
    <script>
    (function () {
        var payload = {
            type: <?php echo json_encode($verify_toast_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            message: <?php echo json_encode($verify_toast_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
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
        toast.id = 'authVerifyToast';
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var otpWrap = document.getElementById('otp');
            var inputs = Array.prototype.slice.call(document.querySelectorAll('#otp input'));
            if (!inputs.length) return;

            function fillFromDigits(raw) {
                var digits = (raw || '').replace(/\D/g, '').slice(0, 6).split('');
                if (!digits.length) return false;

                for (var i = 0; i < inputs.length; i++) {
                    inputs[i].value = digits[i] || '';
                }

                var nextEmpty = inputs.findIndex(function (el) { return !el.value; });
                focusIndex(nextEmpty === -1 ? inputs.length - 1 : nextEmpty);
                return true;
            }

            function focusIndex(idx) {
                if (idx >= 0 && idx < inputs.length) {
                    inputs[idx].focus();
                    inputs[idx].select();
                }
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
                    if (!pasted) return;

                    event.preventDefault();
                    fillFromDigits(pasted);
                });
            });

            // Capture paste at container/document level for browsers that skip per-input handlers with maxlength.
            function handleGlobalPaste(event) {
                var target = event.target;
                if (!otpWrap || !target || !otpWrap.contains(target)) return;
                var pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
                if (!pasted) return;
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



