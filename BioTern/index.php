<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$landing_user_id = (int)($_SESSION['user_id'] ?? 0);
$landing_logged_in = $landing_user_id > 0 || !empty($_SESSION['logged_in']);
$landing_signin_href = $landing_logged_in ? 'homepage.php' : 'auth/auth-login-cover.php';
$landing_signin_label = $landing_logged_in ? 'Dashboard' : 'Sign In';
$landing_hero_href = $landing_logged_in ? 'homepage.php' : 'auth/auth-login-cover.php';
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
                    <img src="assets/images/logo-full-header.png" alt="BioTern" class="landing-brand-logo">
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
                    <a href="document_application.php" class="btn btn-sm btn-light-brand">Apply</a>
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
                            <span class="mini-pill mb-3"><i class="feather-shield"></i> Biometric-secured internship monitoring</span>
                            <h1 class="hero-title mb-3">Build for schools, supervisors, and interns in one BioTern workspace.</h1>
                            <p class="hero-subtitle mb-4">Track attendance with biometric confidence, monitor internship progress, and generate key documents and reports without juggling multiple systems.</p>
                            <div class="hero-cta">
                                <a href="<?php echo htmlspecialchars($landing_hero_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><?php echo htmlspecialchars($landing_hero_label, ENT_QUOTES, 'UTF-8'); ?></a>
                                <a href="document_application.php" class="btn btn-light-brand btn-lg">Start Application</a>
                            </div>
                        </div>  
                    </div>
                    <div class="col-lg-4 d-flex justify-content-center p-4 p-lg-0">
                        <img class="hero-college-logo" src="assets/images/auth/auth-cover-login-bg.png" alt="Clark College Logo">
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <span class="feature-icon mb-3"><i class="feather-fingerprint"></i></span>
                            <h5 class="mb-2">Biometric Attendance</h5>
                            <p class="text-muted mb-0">Automate attendance capture and approvals with reduced manual errors.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <span class="feature-icon mb-3"><i class="feather-users"></i></span>
                            <h5 class="mb-2">Unified Management</h5>
                            <p class="text-muted mb-0">Handle students, coordinators, supervisors, and internships from one panel.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <span class="feature-icon mb-3"><i class="feather-file-text"></i></span>
                            <h5 class="mb-2">Document Workflow</h5>
                            <p class="text-muted mb-0">Create and manage application letters, endorsements, MOA, and reports faster.</p>
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


