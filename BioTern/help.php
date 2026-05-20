<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$year = date('Y');
$page_title = 'Help || BioTern';
$page_body_class = 'public-info-page';
$page_is_public = true;
$page_styles = [
    'assets/css/modules/pages/page-public-info.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
    <header class="nxl-header public-info-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="index.php" class="d-flex align-items-center" aria-label="BioTern home">
                    <img src="assets/images/logo-full-header.png" alt="BioTern" class="public-info-logo">
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
                    <a href="auth/auth-login.php" class="btn btn-sm btn-primary">Sign In</a>
                </div>
            </div>
        </div>
    </header>

    <main class="nxl-container">
        <div class="nxl-content">
            <div class="public-info-wrap">
                <article class="public-info-panel">
                    <div class="public-info-hero">
                        <div class="public-info-kicker">Support</div>
                        <h1 class="public-info-title">Help Center</h1>
                        <p class="public-info-lede">Find quick guidance for signing in, submitting student applications, uploading documents, and keeping OJT attendance records organized in BioTern.</p>
                    </div>
                    <div class="public-info-body">
                        <section class="public-info-section">
                            <h2>Getting Started</h2>
                            <ul>
                                <li>Students can start an application from the Student Application button on the landing page.</li>
                                <li>Registered users can sign in with the email, username, or ID assigned to their BioTern account.</li>
                                <li>Use the dashboard after signing in to review pending tasks, internship progress, documents, and attendance summaries.</li>
                            </ul>
                        </section>
                        <section class="public-info-section">
                            <h2>Common Tasks</h2>
                            <ul>
                                <li>Upload required internship documents from the document area assigned to your role.</li>
                                <li>Review attendance and rendered hours from the attendance or OJT monitoring pages.</li>
                                <li>Keep profile details current so coordinators and supervisors can verify records accurately.</li>
                            </ul>
                        </section>
                        <section class="public-info-section">
                            <h2>Need More Help?</h2>
                            <p>If something looks incorrect, contact your OJT coordinator or system administrator and include your name, role, and a short description of the issue.</p>
                            <div class="public-info-contact">
                                <a href="auth/auth-login.php"><i class="feather-log-in"></i><span>Go to sign in</span></a>
                                <a href="auth/auth-register.php?role=student"><i class="feather-edit-3"></i><span>Start student application</span></a>
                            </div>
                        </section>
                    </div>
                </article>
            </div>
        </div>
    </main>

    <footer class="footer public-info-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright"><span>Copyright &copy; <?php echo $year; ?></span></p>
            <p class="mb-0"><span>By: <a href="#">ACT 2A</a></span> <span class="ms-2">Distributed by: <a href="#">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="help.php" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="terms.php" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="privacy.php" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </div>
    </footer>
<?php $page_render_footer = false; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
