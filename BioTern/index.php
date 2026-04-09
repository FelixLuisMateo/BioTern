<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('biotern_auth_cookie_key')) {
    function biotern_auth_cookie_key()
    {
        $appKey = getenv('APP_KEY');
        if ($appKey !== false && trim((string)$appKey) !== '') {
            return (string)$appKey;
        }

        $dbPassKey = defined('DB_PASS') ? (string)DB_PASS : '';
        if ($dbPassKey !== '') {
            return $dbPassKey;
        }

        return 'biotern-fallback-auth-key';
    }
}

if (!function_exists('biotern_parse_auth_cookie')) {
    function biotern_parse_auth_cookie()
    {
        $raw = isset($_COOKIE['biotern_auth']) ? (string)$_COOKIE['biotern_auth'] : '';
        if ($raw === '') {
            return null;
        }

        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            return null;
        }

        $userId = (int)$parts[0];
        $issuedAt = (int)$parts[1];
        $expiresAt = (int)$parts[2];
        $signature = (string)$parts[3];

        if ($userId <= 0 || $issuedAt <= 0 || $expiresAt <= 0 || $signature === '') {
            return null;
        }

        if ($expiresAt < time()) {
            return null;
        }

        $payload = $userId . '|' . $issuedAt . '|' . $expiresAt;
        $expected = hash_hmac('sha256', $payload, biotern_auth_cookie_key());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return ['user_id' => $userId];
    }
}

if (!function_exists('biotern_restore_session_from_cookie')) {
    function biotern_restore_session_from_cookie($conn)
    {
        if (!($conn instanceof mysqli) || $conn->connect_errno) {
            return;
        }

        $parsed = biotern_parse_auth_cookie();
        if (!is_array($parsed) || !isset($parsed['user_id'])) {
            return;
        }

        $cookieUserId = (int)$parsed['user_id'];
        if ($cookieUserId <= 0) {
            return;
        }

        $stmt = $conn->prepare("SELECT id, name, username, email, role, is_active, profile_picture FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('i', $cookieUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['name'] = (string)($user['name'] ?? '');
        $_SESSION['username'] = (string)($user['username'] ?? '');
        $_SESSION['email'] = (string)($user['email'] ?? '');
        $_SESSION['role'] = (string)($user['role'] ?? '');
        $_SESSION['profile_picture'] = (string)($user['profile_picture'] ?? '');
        $_SESSION['logged_in'] = true;
    }
}

if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
    biotern_restore_session_from_cookie(isset($conn) ? $conn : null);
}

$landing_user_id = (int)($_SESSION['user_id'] ?? 0);
$landing_logged_in = $landing_user_id > 0 || !empty($_SESSION['logged_in']);
$landing_signin_href = $landing_logged_in ? 'homepage.php' : 'auth/auth-login.php';
$landing_signin_label = $landing_logged_in ? 'Dashboard' : 'Sign In';
$landing_hero_href = $landing_logged_in ? 'homepage.php' : 'auth/auth-login.php';
$landing_hero_label = $landing_logged_in ? 'Go to Dashboard' : 'Go to Dashboard Login';

$year = date('Y');
$page_title = 'BioTern || Internship Monitoring';
$page_body_class = 'landing-public';
$page_is_public = true;
$page_styles = [
    'assets/css/modules/pages/page-landing.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
    <header class="nxl-header landing-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="index.php" class="d-flex align-items-center">
                    <img src="assets/images/logo-full-header.png" alt="BioTern" class="landing-brand-logo landing-brand-logo-full">
                    <img src="assets/images/logo-abbr.png" alt="BioTern" class="landing-brand-logo landing-brand-logo-short">
                </a>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center gap-2">
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button" title="Dark mode">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button app-hidden-toggle" title="Light mode">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <a href="auth/auth-register.php?role=student" class="btn btn-sm btn-light-brand">Apply</a>
                    <a href="<?php echo htmlspecialchars($landing_signin_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-primary"><?php echo htmlspecialchars($landing_signin_label, ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
        </div>
    </header>

    <main class="nxl-container">
        <div class="nxl-content hero-wrap">
            <div class="container">
            <div class="hero-card">
                <div class="row g-0 align-items-center">
                    <div class="col-lg-8">
                        <div class="hero-body">
                            <h1 class="hero-title mb-3">Manage attendance, documents, and internship progress in one place.</h1>
                            <p class="hero-subtitle mb-4">BioTern gives students, supervisors, and coordinators one shared workspace for daily OJT records, approvals, and reporting.</p>
                            <div class="hero-points mb-4">
                                <div class="hero-point"><i class="feather-check-circle"></i><span>Keep DTR, endorsements, and approvals organized.</span></div>
                                <div class="hero-point"><i class="feather-check-circle"></i><span>Monitor internship status without switching systems.</span></div>
                                <div class="hero-point"><i class="feather-check-circle"></i><span>Review student progress with clearer records and reports.</span></div>
                            </div>
                            <div class="hero-cta">
                                <a href="<?php echo htmlspecialchars($landing_hero_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><?php echo htmlspecialchars($landing_hero_label, ENT_QUOTES, 'UTF-8'); ?></a>
                                <a href="auth/auth-register.php?role=student" class="btn btn-light-brand btn-lg">Student Application</a>
                            </div>
                        </div>  
                    </div>
                    <div class="col-lg-4">
                        <div class="hero-side-panel">
                            <img class="hero-college-logo" src="assets/images/auth/auth-cover-login-bg.png" alt="Clark College Logo">
                            <div class="hero-side-copy">
                                <h5 class="mb-2">Built for everyday internship work</h5>
                                <ul class="hero-side-list mb-0">
                                    <li>Attendance and rendered hours</li>
                                    <li>Student applications and approvals</li>
                                    <li>Documents, reports, and follow-up</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </main>

    <footer class="footer landing-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright &copy; <?php echo $year; ?></span>
            </p>
            <p class="mb-0"><span>By: <a href="#">ACT 2A</a></span> <span class="ms-2">Distributed by: <a href="#">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="#" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="#" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="#" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </div>
    </footer>
<?php include __DIR__ . '/includes/footer.php'; ?>


