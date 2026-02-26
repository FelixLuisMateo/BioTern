<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'biotern_db';

$reset_error = '';
$reset_success = '';

$contact = isset($_SESSION['password_reset_contact']) ? trim((string)$_SESSION['password_reset_contact']) : '';
$isVerified = !empty($_SESSION['password_reset_verified']);

if (!$isVerified || $contact === '') {
    $reset_error = 'Your reset session is invalid or expired. Please request a new password reset.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isVerified || $contact === '') {
        $reset_error = 'Your reset session is invalid or expired. Please request a new password reset.';
    } else {
        $newPassword = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        if ($newPassword === '' || $confirmPassword === '') {
            $reset_error = 'Please enter and confirm your new password.';
        } elseif (strlen($newPassword) < 8) {
            $reset_error = 'Password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $reset_error = 'Passwords do not match.';
        } else {
            $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
            if ($mysqli->connect_errno) {
                $reset_error = 'Database connection failed.';
            } else {
                // Use the verified reset contact (email) as the update key.
                // This avoids wrong-row updates in databases where id values are not unique.
                $update = $mysqli->prepare('UPDATE users SET password = ? WHERE email = ? LIMIT 1');
                if (!$update) {
                    $reset_error = 'Failed to prepare password update.';
                } else {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update->bind_param('ss', $passwordHash, $contact);
                    if ($update->execute()) {
                        if ($update->affected_rows < 1) {
                            $reset_error = 'No account was found for this reset request.';
                        } else {
                            $reset_success = 'Your password has been reset successfully. You can now log in.';

                            unset($_SESSION['password_reset_contact']);
                            unset($_SESSION['password_reset_user_id']);
                            unset($_SESSION['password_reset_code']);
                            unset($_SESSION['password_reset_code_sent_at']);
                            unset($_SESSION['password_reset_verified']);
                            unset($_SESSION['password_reset_last_sent_ok']);
                            unset($_SESSION['password_reset_last_error_ref']);
                        }
                    } else {
                        $reset_error = 'Unable to update password. Please try again.';
                    }
                    $update->close();
                }
                $mysqli->close();
            }
        }
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
    <title>BioTern || Resetting Minimal</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
</head>

<body>
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card mb-4 mt-5 mx-4 mx-sm-0 position-relative">
                    <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-0 start-50">
                        <img src="assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5">
                        <h2 class="fs-20 fw-bolder mb-4">Reset Password</h2>
                        <h4 class="fs-13 fw-bold mb-2">Set your new password</h4>
                        <p class="fs-12 fw-medium text-muted">Enter your new password below to complete your password reset.</p>

                        <?php if ($reset_error !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($reset_error); ?></div>
                        <?php endif; ?>

                        <?php if ($reset_success !== ''): ?>
                            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($reset_success); ?></div>
                            <div class="mt-4">
                                <a href="auth-login-cover.php" class="btn btn-lg btn-primary w-100">Go to Login</a>
                            </div>
                        <?php else: ?>
                            <form action="auth-resetting-minimal.php" method="post" class="w-100 mt-4 pt-2">
                                <div class="mb-4">
                                    <input type="password" name="new_password" class="form-control" placeholder="New Password" minlength="8" required>
                                </div>
                                <div class="mb-4">
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" minlength="8" required>
                                </div>
                                <div class="mt-5">
                                    <button type="submit" class="btn btn-lg btn-primary w-100" <?php echo (!$isVerified || $contact === '') ? 'disabled' : ''; ?>>Save Change</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="mt-5 text-muted">
                            <span>Remembered your password?</span>
                            <a href="auth-login-cover.php" class="fw-bold">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>
</body>

</html>
