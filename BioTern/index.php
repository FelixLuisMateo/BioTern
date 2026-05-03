<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$landing_user_id = (int)($_SESSION['user_id'] ?? 0);
$landing_logged_in = $landing_user_id > 0 || !empty($_SESSION['logged_in']);
if ($landing_logged_in) {
    header('Location: homepage.php');
    exit;
}
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


