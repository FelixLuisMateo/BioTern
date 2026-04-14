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
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8');
        $appBaseUrl = biotern_mail_asset_base();
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
                                            <div style="font-size:13px;color:#a3b3cc;">Student Application</div>
                                        </td>
                                        <td align="right" style="vertical-align:middle;">' . $logoHtml . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px;color:#e5e7eb;">
                                <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Verify your email</div>
                                <div style="font-size:14px;color:#94a3b8;margin-bottom:18px;">
                                    We sent a 6-digit code to <strong style="color:#e5e7eb;">' . $safeEmail . '</strong>.
                                </div>
                                <div style="text-align:center;margin:20px 0;">
                                    <div style="display:inline-block;padding:12px 20px;border-radius:12px;background:#0f172a;border:1px solid #263453;font-size:24px;letter-spacing:6px;font-weight:700;color:#ffffff;">
                                        ' . $safeCode . '
                                    </div>
                                </div>
                                <div style="font-size:13px;color:#94a3b8;">
                                    Enter this code to continue your student application. This code expires in 15 minutes.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:18px 24px;border-top:1px solid #1f2a44;color:#6b7a99;font-size:12px;">
                                If you did not request this, you can ignore this email.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
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
        html,
        body.auth-verify-page {
            min-height: 100vh;
            background: #0b1220 !important;
            color: #fff;
            display: grid;
            place-items: center;
            position: relative;
            overflow: hidden;
        }
        .verify-bg-watermark {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(12, 20, 36, 0.65), rgba(12, 20, 36, 0.55)),
                radial-gradient(circle at 18% 20%, rgba(56, 120, 232, 0.2), transparent 42%),
                radial-gradient(circle at 82% 84%, rgba(155, 206, 255, 0.12), transparent 44%),
                url("<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>auth/building.png");
            background-repeat: no-repeat, no-repeat, no-repeat, no-repeat;
            background-size: cover, cover, cover, cover;
            background-position: center center, center center, center center, center center;
        }
        .verify-card {
            width: min(100%, 560px);
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(129,153,199,.22);
            border-radius: 22px;
            padding: 36px 38px;
            box-shadow: 0 28px 70px rgba(0,0,0,.32);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(6px);
        }
        .verify-card .form-label { color: #b6c4dc; font-size: .88rem; }
        .verify-card .form-control {
            min-height: 54px;
            letter-spacing: .3em;
            text-align: center;
            font-weight: 700;
            font-size: 1.05rem;
            background: rgba(15, 23, 42, 0.75);
            border-color: rgba(129, 153, 199, 0.35);
            color: #e5e7eb;
        }
        .verify-card .form-control::placeholder { color: rgba(148, 163, 184, 0.75); }
        .verify-card .form-control:focus {
            border-color: rgba(90, 141, 255, 0.65);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.2);
        }
        .verify-card .btn { min-height: 50px; font-weight: 600; }
        .verify-card .btn-outline-light {
            border-color: rgba(148, 163, 184, 0.5);
            color: #e5e7eb;
        }
        .verify-card .btn-outline-light:hover { background: rgba(148, 163, 184, 0.12); }
        .verify-meta { color: #b2bfd5; font-size: .98rem; }
    </style>
</head>
<body class="auth-verify-page">
    <div class="verify-bg-watermark" aria-hidden="true"></div>
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
