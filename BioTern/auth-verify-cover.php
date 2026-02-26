<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
</head>

<body>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="assets/images/auth/auth-cover-verify-bg.svg" alt="" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5">
                    <div class="wd-50 mb-5">
                        <img src="assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <h2 class="fs-20 fw-bolder mb-4">Verify</h2>
                    <h4 class="fs-13 fw-bold mb-2">Enter the 6-digit one-time password sent to your account.</h4>
                    <p class="fs-12 fw-medium text-muted"><span>A code has been sent to</span> <strong><?php echo $masked_contact; ?></strong></p>

                    <?php if ($verify_message !== ''): ?>
                        <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($verify_message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <?php if ($verify_error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($verify_error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" class="w-100 mt-4 pt-2" autocomplete="one-time-code">
                        <div id="otp" class="inputs d-flex flex-row justify-content-center mt-2">
                            <input name="digit1" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit1" maxlength="1" required>
                            <input name="digit2" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit2" maxlength="1" required>
                            <input name="digit3" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit3" maxlength="1" required>
                            <input name="digit4" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit4" maxlength="1" required>
                            <input name="digit5" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit5" maxlength="1" required>
                            <input name="digit6" class="m-2 text-center form-control rounded" type="text" inputmode="numeric" pattern="[0-9]" id="digit6" maxlength="1" required>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="btn btn-lg btn-primary w-100">Validate</button>
                        </div>
                        <div class="mt-5 text-muted">
                            <span>Didn't get the code</span>
                            <a href="auth-reset-cover.php?resend=1">Resend</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var inputs = Array.prototype.slice.call(document.querySelectorAll('#otp input'));
            if (!inputs.length) return;

            function focusIndex(idx) {
                if (idx >= 0 && idx < inputs.length) {
                    inputs[idx].focus();
                    inputs[idx].select();
                }
            }

            inputs.forEach(function (input, idx) {
                input.addEventListener('input', function () {
                    var value = (this.value || '').replace(/\D/g, '');
                    this.value = value ? value.charAt(0) : '';
                    if (this.value && idx < inputs.length - 1) {
                        focusIndex(idx + 1);
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !this.value && idx > 0) {
                        focusIndex(idx - 1);
                    }
                    if (event.key.length === 1 && /\D/.test(event.key)) {
                        event.preventDefault();
                    }
                });

                input.addEventListener('paste', function (event) {
                    var pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
                    var digits = pasted.replace(/\D/g, '').slice(0, 6).split('');
                    if (!digits.length) return;

                    event.preventDefault();
                    for (var i = 0; i < inputs.length; i++) {
                        inputs[i].value = digits[i] || '';
                    }

                    var nextEmpty = inputs.findIndex(function (el) { return !el.value; });
                    focusIndex(nextEmpty === -1 ? inputs.length - 1 : nextEmpty);
                });
            });

            inputs[0].focus();
        });
    </script>
</body>

</html>
