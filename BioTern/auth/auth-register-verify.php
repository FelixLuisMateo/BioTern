<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/mailer.php';

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;

$pending = $_SESSION['student_registration_pending_post'] ?? null;
if (!is_array($pending) || empty($pending['account_email']) && empty($pending['email'])) {
    header('Location: ' . $route_prefix . 'auth-register.php?registered=error&msg=' . urlencode('No pending email verification found. Please register again.'));
    exit;
}

$targetEmail = trim((string)($pending['account_email'] ?? $pending['email'] ?? ''));
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? 'verify')));
    if ($action === 'resend') {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['student_registration_verify_code'] = $code;
        $_SESSION['student_registration_verify_email'] = $targetEmail;
        $_SESSION['student_registration_verify_expires_at'] = time() + 900;

        $subject = 'Verify your BioTern student application';
        $text = "Your BioTern verification code is: {$code}\n\nEnter this code to continue your student application. This code expires in 15 minutes.";
        $html = '<p>Your BioTern verification code is:</p><p style="font-size:24px;font-weight:700;letter-spacing:4px;">'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            . '</p><p>Enter this code to continue your student application. This code expires in 15 minutes.</p>';
        $mailRef = null;
        if (biotern_send_mail($conn, $targetEmail, $subject, $text, $html, $mailRef)) {
            $message = 'A new verification code was sent to your email.';
        } else {
            $error = 'Unable to resend the verification email right now.';
            if ($mailRef) {
                $error .= ' Reference: ' . $mailRef;
            }
        }
    } else {
        $code = trim((string)($_POST['verification_code'] ?? ''));
        $expected = (string)($_SESSION['student_registration_verify_code'] ?? '');
        $expiresAt = (int)($_SESSION['student_registration_verify_expires_at'] ?? 0);
        $expectedEmail = trim((string)($_SESSION['student_registration_verify_email'] ?? ''));

        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            $error = 'Enter the 6-digit verification code.';
        } elseif ($expected === '' || $expectedEmail !== $targetEmail || $expiresAt <= time()) {
            $error = 'The verification code expired. Please resend a new one.';
        } elseif (!hash_equals($expected, $code)) {
            $error = 'Invalid verification code.';
        } else {
            $_POST = $pending;
            $_POST['role'] = 'student';
            $_POST['registration_email_verified'] = '1';
            unset(
                $_SESSION['student_registration_verify_code'],
                $_SESSION['student_registration_verify_email'],
                $_SESSION['student_registration_verify_expires_at'],
                $_SESSION['student_registration_pending_post']
            );
            require_once dirname(__DIR__) . '/api/register_submit.php';
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
    <title>BioTern || Verify Registration</title>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <style>
        body { min-height: 100vh; background: #0b1220; color: #fff; display: grid; place-items: center; }
        .verify-card { width: min(100%, 480px); background: #121b2f; border: 1px solid rgba(129,153,199,.18); border-radius: 20px; padding: 28px; box-shadow: 0 24px 60px rgba(0,0,0,.28); }
        .verify-card .form-control { min-height: 48px; letter-spacing: .25em; text-align: center; font-weight: 700; }
        .verify-meta { color: #94a3b8; font-size: .92rem; }
    </style>
</head>
<body>
    <div class="verify-card">
        <h3 class="mb-2">Verify Your Email</h3>
        <p class="verify-meta mb-4">We sent a 6-digit code to <strong><?php echo htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8'); ?></strong>. Enter it to continue your student application.</p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" class="mb-3">
            <div class="mb-3">
                <label class="form-label">Verification Code</label>
                <input type="text" name="verification_code" class="form-control" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify And Continue</button>
        </form>
        <form method="post" class="d-grid">
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-outline-light">Resend Code</button>
        </form>
    </div>
</body>
</html>
