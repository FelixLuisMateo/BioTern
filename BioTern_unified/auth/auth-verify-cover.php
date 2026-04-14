<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';

$verify_error = '';
$verify_message = '';
$otpTtlSeconds = 600;

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
    <title>BioTern || Verify Cover</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=20260310">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/theme.min.css">
    <style>
        .verify-card {
            background: rgba(10, 20, 45, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            box-shadow: 0 28px 60px rgba(2, 8, 23, 0.45);
            color: #e2e8f0;
        }

        .verify-logo {
            width: 52px;
        }

        .verify-title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.85rem;
            color: #f8fafc;
        }

        .verify-copy {
            font-size: 1.05rem;
            line-height: 1.65;
            color: #cbd5e1;
            margin-bottom: 1.6rem;
        }

        .verify-copy strong {
            color: #ffffff;
            font-weight: 700;
        }

        .verify-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #94a3b8;
            margin-bottom: 0.7rem;
        }

        .verify-otp {
            gap: 0.55rem;
            margin-top: 0.25rem !important;
        }

        .verify-otp input {
            width: 3.4rem;
            min-width: 3.4rem;
            height: 4rem;
            margin: 0 !important;
            border-radius: 14px !important;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: rgba(15, 23, 42, 0.88);
            color: #e2e8f0;
            font-size: 1.4rem;
            font-weight: 800;
            box-shadow: none;
        }

        .verify-otp input:focus {
            border-color: rgba(96, 165, 250, 0.85);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
            background: rgba(15, 23, 42, 0.96);
            color: #ffffff;
        }

        .verify-submit {
            min-height: 3.4rem;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.28);
        }

        .verify-resend {
            margin-top: 1.45rem !important;
            text-align: center;
            font-size: 0.95rem;
            color: #cbd5e1 !important;
        }

        .verify-resend a {
            color: #dbeafe !important;
            font-weight: 700;
            text-decoration: none;
            margin-left: 0.25rem;
            opacity: 1 !important;
        }

        .verify-resend a:visited {
            color: #dbeafe !important;
        }

        .verify-resend a:hover,
        .verify-resend a:focus {
            color: #ffffff !important;
            text-decoration: underline;
        }

        .verify-alert {
            border-radius: 14px;
            font-size: 0.93rem;
        }

        @media (max-width: 575.98px) {
            .verify-card {
                border-radius: 20px;
            }

            .verify-title {
                font-size: 1.7rem;
            }

            .verify-copy {
                font-size: 0.98rem;
            }

            .verify-otp {
                gap: 0.4rem;
            }

            .verify-otp input {
                width: 2.75rem;
                min-width: 2.75rem;
                height: 3.4rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/auth/auth-cover-verify-bg.svg" alt="" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card verify-card p-sm-5">
                    <div class="wd-50 mb-5">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="" class="img-fluid verify-logo">
                    </div>
                    <h2 class="verify-title">Verify Your Email</h2>
                    <p class="verify-copy">We sent a 6-digit code to <strong><?php echo $masked_contact; ?></strong>. Enter it to continue your student application.</p>

                    <?php if ($verify_message !== ''): ?>
                        <div class="alert alert-info verify-alert" role="alert"><?php echo htmlspecialchars($verify_message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <?php if ($verify_error !== ''): ?>
                        <div class="alert alert-danger verify-alert" role="alert"><?php echo htmlspecialchars($verify_error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" class="w-100 mt-4 pt-2" autocomplete="one-time-code">
                        <label class="verify-label" for="digit1">Verification Code</label>
                        <div id="otp" class="inputs verify-otp d-flex flex-row justify-content-center mt-2">
                            <input name="digit1" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit1" maxlength="6" autocomplete="one-time-code" required>
                            <input name="digit2" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit2" maxlength="6" required>
                            <input name="digit3" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit3" maxlength="6" required>
                            <input name="digit4" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit4" maxlength="6" required>
                            <input name="digit5" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit5" maxlength="6" required>
                            <input name="digit6" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit6" maxlength="6" required>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="btn btn-lg btn-primary w-100 verify-submit">Verify And Continue</button>
                        </div>
                        <div class="verify-resend text-muted">
                            <a href="auth-reset-cover.php?resend=1">Resend Code</a>
                        </div>
                    </form>
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



