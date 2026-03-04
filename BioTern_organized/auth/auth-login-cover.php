<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['logout']) && (string)$_GET['logout'] === '1') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'biotern_db';
$login_error = '';
$next = isset($_GET['next']) ? basename((string)$_GET['next']) : '';
if ($next !== '' && !preg_match('/^[A-Za-z0-9_-]+\.php$/', $next)) {
    $next = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['identifier']) ? trim((string)$_POST['identifier']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $posted_next = isset($_POST['next']) ? basename((string)$_POST['next']) : '';
    if ($posted_next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $posted_next)) {
        $next = $posted_next;
    }

    if ($identifier === '' || $password === '') {
        $login_error = 'Please enter your username/email and password.';
    } else {
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($mysqli->connect_errno) {
            $login_error = 'Database connection failed.';
        } else {
            $stmt = $mysqli->prepare("SELECT id, name, username, email, password, role, is_active, profile_picture FROM users WHERE (username = ? OR email = ?) LIMIT 1");

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
                    $_SESSION['profile_picture'] = (string)($user['profile_picture'] ?? '');
                    $_SESSION['logged_in'] = true;

                    header('Location: ' . ($next !== '' ? $next : 'homepage.php'));
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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Login Cover</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <script src="assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
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

                    <?php if ($login_error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>

                    <form action="auth-login-cover.php" method="post" class="w-100 mt-4 pt-2">
                        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-4">
                            <input type="text" name="identifier" id="identifier" class="form-control" placeholder="Email or Username" value="<?php echo isset($_POST['identifier']) ? htmlspecialchars((string)$_POST['identifier']) : ''; ?>" required aria-required="true" aria-label="Email or Username" autofocus>
                        </div>
                        <div class="mb-3 input-group">
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required aria-required="true" aria-label="Password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password"><i></i></button>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe">
                                    <label class="form-check-label c-pointer" for="rememberMe">Remember Me</label>
                                </div>
                            </div>
                            <div>
                                <a href="auth-reset-cover.php" class="fs-11 text-primary">Forget password?</a>
                            </div>
                        </div>
                        <div class="mt-5">
                            <button type="submit" id="loginBtn" class="btn btn-lg btn-primary w-100">Login</button>
                        </div>
                    </form>

                    <!-- Create Account prompt removed -->
                </div>
            </div>
        </div>
    </main>

    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <script>
        // Minimal show/hide password toggle (kept per request)
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.getElementById('togglePassword');
            var pwd = document.getElementById('passwordInput');
            if (!toggle || !pwd) return;

            const eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

            const icon = toggle.querySelector('i');
            if (icon && !icon.innerHTML.trim()) {
                icon.innerHTML = eyeSVG;
                toggle.setAttribute('title', 'Show password');
                toggle.setAttribute('aria-label', 'Show password');
            }

            toggle.addEventListener('click', function () {
                var wasPassword = pwd.type === 'password';
                pwd.type = wasPassword ? 'text' : 'password';
                const icon = this.querySelector('i');
                if (icon) {
                    icon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
                    this.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');
                    this.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                }
            });
        });
    </script>
</body>
</html>

