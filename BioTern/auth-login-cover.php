<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'biotern_db';
$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['identifier']) ? trim((string)$_POST['identifier']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($identifier === '' || $password === '') {
        $login_error = 'Please enter your username/email and password.';
    } else {
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($mysqli->connect_errno) {
            $login_error = 'Database connection failed.';
        } else {
            $stmt = $mysqli->prepare("
                SELECT id, name, username, email, password, role, is_active
                FROM users
                WHERE (username = ? OR email = ?)
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param('ss', $identifier, $identifier);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    $login_error = 'Invalid username/email or password.';
                } elseif ((int)($user['is_active'] ?? 0) !== 1) {
                    $login_error = 'Your account is inactive.';
                } elseif (!password_verify($password, (string)$user['password'])) {
                    $login_error = 'Invalid username/email or password.';
                } else {
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = (string)$user['name'];
                    $_SESSION['username'] = (string)$user['username'];
                    $_SESSION['email'] = (string)$user['email'];
                    $_SESSION['role'] = (string)$user['role'];
                    $_SESSION['logged_in'] = true;

                    header('Location: index.php');
                    exit;
                }
            } else {
                $login_error = 'Login query preparation failed.';
            }
            $mysqli->close();
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
    <meta name="author" content="theme_ocean">
    <title>BioTern || Login Cover</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
<<<<<<< HEAD
    <!--! END: Custom CSS-->
    <style>
        /* Make the login card and form controls larger for better usability */
        .auth-cover-card {
            max-width: 640px;
            padding: 3.5rem !important;
            border-radius: 12px;
        }
        .auth-cover-card .wd-50 img {
            max-width: 96px;
        }
        .auth-cover-card .form-control {
            padding: 14px 16px;
            font-size: 1rem;
        }
        /* Improve password toggle button and icon sizing */
        .auth-cover-card .input-group .btn {
            min-width: 44px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-cover-card .input-group .btn i svg {
            width: 18px;
            height: 18px;
        }
        /* Subtle entrance animation */
        .auth-cover-card { transform: translateY(8px); opacity: 0; transition: transform .36s ease, opacity .36s ease; }
        .auth-cover-card.is-visible { transform: translateY(0); opacity: 1; }
        /* Focus styles for accessibility */
        .auth-cover-card .form-control:focus { box-shadow: 0 0 0 3px rgba(102,126,234,0.12); border-color: #667eea; }
        .auth-cover-card .input-group .btn {
            padding: 10px 14px;
            font-size: 0.9rem;
        }
        .auth-cover-card .btn.btn-lg {
            padding: 12px 20px;
            font-size: 1rem;
        }
        @media (max-width: 767px) {
            .auth-cover-card { max-width: 92%; padding: 2rem !important; }
        }
    </style>
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
=======
>>>>>>> 69407bdc433b3f164e46aaf84c335ce5f1204278
</head>

<body>
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="assets/images/auth/auth-cover-login-bg.png" alt="" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5">
                    <div class="wd-50 mb-5">
                        <img src="assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <h2 class="fs-25 fw-bolder mb-4">Login</h2>
                    <h4 class="fs-15 fw-bold mb-2">Log in to your Clark College of Science and Technology internship account.</h4>

<<<<<<< HEAD
                    <form action="index.php" class="w-100 mt-4 pt-2" id="loginForm" novalidate>
                        <div class="mb-4">
                            <input type="email" id="loginEmail" name="email" class="form-control" placeholder="Email or Username" value="" required aria-required="true" aria-label="Email or Username" autofocus>
                        </div>
                        <div class="mb-3 input-group">
                            <input type="password" class="form-control" placeholder="Password" id="passwordInput" name="password" value="" required aria-required="true" aria-label="Password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password"><i></i></button>
=======
                    <?php if ($login_error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>

                    <form action="auth-login-cover.php" method="post" class="w-100 mt-4 pt-2">
                        <div class="mb-4">
                            <input type="text" name="identifier" class="form-control" placeholder="Email or Username" value="<?php echo isset($_POST['identifier']) ? htmlspecialchars((string)$_POST['identifier']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Password" value="" required>
>>>>>>> 69407bdc433b3f164e46aaf84c335ce5f1204278
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="rememberMe">
                                    <label class="custom-control-label c-pointer" for="rememberMe">Remember Me</label>
                                </div>
                            </div>
                            <div>
                                <a href="auth-reset-cover.php" class="fs-11 text-primary">Forget password?</a>
                            </div>
                        </div>
                        <div class="mt-5">
                            <button type="submit" id="loginBtn" class="btn btn-lg btn-primary w-100" disabled aria-disabled="true">Login</button>
                        </div>
                        </form>

                        <!-- Create Account prompt removed per request -->
                </div>
            </div>
        </div>
    </main>
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
<<<<<<< HEAD
    <!--! END: Apps Init !-->
    <!-- Theme Customizer removed -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.getElementById('togglePassword');
            var pwd = document.getElementById('passwordInput');
            const eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">\
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>\
                <path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path>\
                <line x1="1" y1="1" x2="23" y2="23"></line>\
            </svg>';

            if (toggle && pwd) {
                // initialize icon
                const icon = toggle.querySelector('i');
                if (icon && !icon.innerHTML.trim()) {
                    icon.innerHTML = eyeSVG;
                    toggle.setAttribute('title','Show password');
                    toggle.setAttribute('aria-label','Show password');
                }

                toggle.addEventListener('click', function () {
                    const wasPassword = pwd.type === 'password';
                    pwd.type = wasPassword ? 'text' : 'password';
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
                        this.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');
                        this.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                    }
                });
            }

            // Enable/disable login button based on input presence
            const loginBtn = document.getElementById('loginBtn');
            const emailField = document.getElementById('loginEmail');
            function updateLoginState() {
                const ok = emailField && pwd && emailField.value.trim() !== '' && pwd.value.trim() !== '';
                if (loginBtn) {
                    loginBtn.disabled = !ok;
                    loginBtn.setAttribute('aria-disabled', (!ok).toString());
                }
            }
            if (emailField && pwd) {
                emailField.addEventListener('input', updateLoginState);
                pwd.addEventListener('input', updateLoginState);
                updateLoginState();
            }

            // Reveal animated card once JS runs
            document.querySelectorAll('.auth-cover-card').forEach(function(el){ el.classList.add('is-visible'); });
        });
    </script>
=======
    <script src="assets/js/theme-customizer-init.min.js"></script>
>>>>>>> 69407bdc433b3f164e46aaf84c335ce5f1204278
</body>

</html>
