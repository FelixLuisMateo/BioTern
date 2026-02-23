<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Reset Cover</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$reset_message = '';
$reset_error = '';

// Handle resend request from verification page
if (isset($_GET['resend']) && intval($_GET['resend']) === 1) {
    // We expect the contact (email or phone) to be stored in session when the reset was initiated
    $contact = isset($_SESSION['password_reset_contact']) ? (string)$_SESSION['password_reset_contact'] : '';
    if ($contact === '') {
        $reset_error = 'No active password reset request found to resend.';
    } else {
        try {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }
        // Save code and timestamp in session
        $_SESSION['password_reset_code'] = $code;
        $_SESSION['password_reset_code_sent_at'] = time();

        // Send by email if contact looks like an email
        $sentEmail = false;
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $to = $contact;
            $subject = 'Your password reset verification code';
            $message = "Your verification code is: $code\n\nIf you did not request this, please ignore this message.";
            $headers = 'From: no-reply@localhost' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
            // Suppress warnings from mail in dev environments
            $sentEmail = @mail($to, $subject, $message, $headers);
        }

        // Placeholder for SMS sending - integrate your SMS provider here
        $sentSms = false;
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            // If contact is a phone number, you'd call an SMS API here.
            // Example (pseudo): $sentSms = send_sms($contact, "Your code: $code");
            // For now, just mark as not sent.
            $sentSms = false;
        }

        $reset_message = 'A new verification code has been generated and sent.';
        if ($sentEmail) $reset_message .= ' (email)';
        if ($sentSms) $reset_message .= ' (sms)';

        // After regenerating, redirect back to verification page so user can enter code
        header('Location: auth-verify-cover.php?resent=1');
        exit;
    }
}

// Handle initial reset form submission (generate and send first code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact'])) {
    $contact = trim((string)$_POST['contact']);
    if ($contact === '') {
        $reset_error = 'Please provide your email or phone.';
    } else {
        try {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        // Save contact, code and timestamp in session
        $_SESSION['password_reset_contact'] = $contact;
        $_SESSION['password_reset_code'] = $code;
        $_SESSION['password_reset_code_sent_at'] = time();

        // Send by email if contact looks like an email
        $sentEmail = false;
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $to = $contact;
            $subject = 'Your password reset verification code';
            $message = "Your verification code is: $code\n\nIf you did not request this, please ignore this message.";
            $headers = 'From: no-reply@localhost' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
            $sentEmail = @mail($to, $subject, $message, $headers);
        }

        // Placeholder for SMS sending - integrate your SMS provider here
        $sentSms = false;
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            // If contact is a phone number, you'd call an SMS API here.
            // Example (pseudo): $sentSms = send_sms($contact, "Your code: $code");
            $sentSms = false;
        }

        $reset_message = 'A verification code has been generated and sent.';
        if ($sentEmail) $reset_message .= ' (email)';
        if ($sentSms) $reset_message .= ' (sms)';

        // Redirect to verification page so user can enter the code
        header('Location: auth-verify-cover.php?sent=1');
        exit;
    }
}

?>
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="assets/images/auth/auth-cover-reset-bg.svg" alt="" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5">
                    <div class="wd-50 mb-5">
                        <img src="assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <h2 class="fs-20 fw-bolder mb-4">Reset</h2>
                    <h4 class="fs-13 fw-bold mb-2">Reset to your username/password</h4>
                    <p class="fs-12 fw-medium text-muted">Enter your email or phone and we'll send a verification code to reset your password.</p>
                    <?php if (!empty($reset_error)) : ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($reset_error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($reset_message)) : ?>
                        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($reset_message); ?></div>
                    <?php endif; ?>
                    <form method="post" action="auth-reset-cover.php" class="w-100 mt-4 pt-2">
                        <div class="mb-4">
                            <input name="contact" class="form-control" placeholder="Email or phone" required>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="btn btn-lg btn-primary w-100">Reset Now</button>
                        </div>
                    </form>
                    <div class="mt-5 text-muted">
                        <span> Don't have an account?</span>
                        <a href="auth-register-cover.php" class="fw-bold">Create an Account</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
</body>

</html>

