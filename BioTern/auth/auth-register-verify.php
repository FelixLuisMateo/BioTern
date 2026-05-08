<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/mailer.php';
require_once dirname(__DIR__) . '/lib/student-registration-verification.php';

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;

$loginTokenRaw = trim((string)($_GET['login_token'] ?? $_POST['login_token'] ?? ''));
$loginToken = biotern_student_reg_normalize_token($loginTokenRaw);
$verification = null;
$targetEmail = '';
$userId = 0;
$expiresAt = 0;
$error = '';
$message = '';
$approvedFromEmail = (isset($_GET['approve']) && (string)$_GET['approve'] === '1');

if ($loginToken !== '' && $conn instanceof mysqli && !$conn->connect_errno) {
    $record = biotern_login_email_verify_load($conn, $loginToken);
    if (is_array($record)) {
        $verification = $record;
        $targetEmail = trim((string)($record['target_email'] ?? ''));
        $userId = (int)($record['user_id'] ?? 0);
        $expiresAt = (int)($record['expires_at'] ?? 0);
    }
}

function biotern_send_login_verify_page_email(mysqli $conn, int $userId, string $targetEmail, string $verifyToken, int $expiresAt): array
{
    if ($userId <= 0 || trim($targetEmail) === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'reference' => '', 'error' => 'Invalid verification request.'];
    }

    $appBaseUrl = biotern_mail_asset_base();
    $verifyPath = 'auth/auth-register-verify.php?login_token=' . rawurlencode($verifyToken) . '&approve=1';
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
    if (biotern_send_mail($conn, $targetEmail, $subject, $text, $html, $mailRef)) {
        return ['ok' => true, 'reference' => '', 'error' => ''];
    }

    return ['ok' => false, 'reference' => (string)$mailRef, 'error' => 'Unable to resend the verification email right now.'];
}

if ($loginToken === '' || !$verification || $targetEmail === '' || $userId <= 0) {
    header('Location: ' . $route_prefix . 'auth-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? 'resend')));
    if ($action === 'resend') {
        $newExpiresAt = time() + 900;
        if (!biotern_login_email_verify_store($conn, $loginToken, $userId, $targetEmail, $newExpiresAt)) {
            $error = 'Unable to refresh your verification email right now.';
        } else {
            $expiresAt = $newExpiresAt;
            $sendResult = biotern_send_login_verify_page_email($conn, $userId, $targetEmail, $loginToken, $expiresAt);
            if (!empty($sendResult['ok'])) {
                $message = 'A new verification email was sent to your inbox.';
            } else {
                $error = 'Unable to resend the verification email right now.';
                if (!empty($sendResult['reference'])) {
                    $error .= ' Reference: ' . $sendResult['reference'];
                }
            }
        }
    }
}

if ($approvedFromEmail && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($expiresAt <= time()) {
        $error = 'This verification link has expired. Please resend a new verification email.';
    } else {
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL");
        $verifyStmt = $conn->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ? LIMIT 1");
        if ($verifyStmt) {
            $verifyStmt->bind_param('i', $userId);
            $verifyStmt->execute();
            $verifyStmt->close();
            biotern_login_email_verify_consume($conn, $loginToken);
            header('Location: ' . $route_prefix . 'auth-login.php?verified=1');
            exit;
        }
        $error = 'Unable to complete email verification right now.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Verify Email</title>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <style>
        html, body.auth-verify-page {
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
        .verify-card .btn { min-height: 50px; font-weight: 600; }
        .verify-meta { color: #b2bfd5; font-size: .98rem; }
        .verify-email-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(52, 84, 209, 0.12);
            border: 1px solid rgba(93, 125, 246, 0.28);
            color: #dbeafe;
            font-weight: 600;
            font-size: .95rem;
        }
        .verify-helper {
            margin-top: 18px;
            color: #94a3b8;
            font-size: .92rem;
            line-height: 1.7;
        }
    </style>
</head>
<body class="auth-verify-page">
    <div class="verify-bg-watermark" aria-hidden="true"></div>
    <div class="verify-card">
        <h3 class="mb-2">Verify Your Email</h3>
        <p class="verify-meta mb-3">Before you can access BioTern, open your Gmail inbox and click the verification button we sent to this address.</p>
        <div class="verify-email-pill mb-4"><?php echo htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" class="d-grid gap-3">
            <input type="hidden" name="action" value="resend">
            <input type="hidden" name="login_token" value="<?php echo htmlspecialchars($loginToken, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-primary">Resend Verification Email</button>
            <a href="<?php echo htmlspecialchars($route_prefix . 'auth-login.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light">Back to Login</a>
        </form>
        <div class="verify-helper">
            This verification link expires in <?php echo max(1, (int)ceil(max(0, $expiresAt - time()) / 60)); ?> minute(s). After you verify your email, come back and log in with your Student ID and password.
        </div>
    </div>
</body>
</html>
