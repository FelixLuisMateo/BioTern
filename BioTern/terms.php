<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$year = date('Y');
$page_title = 'Terms || BioTern';
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
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button" title="Dark mode"><i class="feather-moon"></i></a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button app-hidden-toggle" title="Light mode"><i class="feather-sun"></i></a>
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
                        <div class="public-info-kicker">Last updated May 20, 2026</div>
                        <h1 class="public-info-title">Terms of Use</h1>
                        <p class="public-info-lede">These terms describe the expected use of BioTern for internship application, attendance, document, and monitoring workflows.</p>
                    </div>
                    <div class="public-info-body">
                        <section class="public-info-section">
                            <h2>Acceptable Use</h2>
                            <p>Use BioTern only for legitimate internship, academic, and administrative activities. Do not submit false records, attempt to access accounts that are not yours, or interfere with system operations.</p>
                        </section>
                        <section class="public-info-section">
                            <h2>Account Responsibility</h2>
                            <p>You are responsible for keeping your sign-in details confidential and for the activity submitted through your account. Report suspected account misuse to a coordinator or administrator promptly.</p>
                        </section>
                        <section class="public-info-section">
                            <h2>Submitted Records</h2>
                            <p>Applications, attendance logs, documents, approvals, and reports should be accurate and submitted only when you are authorized to provide them. BioTern records may be reviewed by authorized school personnel for verification and monitoring.</p>
                        </section>
                        <section class="public-info-section">
                            <h2>Changes and Availability</h2>
                            <p>BioTern may be updated to improve security, accuracy, or school workflows. Access can be limited when maintenance, verification, policy enforcement, or account review is required.</p>
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
