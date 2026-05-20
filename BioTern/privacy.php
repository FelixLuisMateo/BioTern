<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$year = date('Y');
$page_title = 'Privacy || BioTern';
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
                        <h1 class="public-info-title">Privacy Notice</h1>
                        <p class="public-info-lede">This notice explains what information BioTern handles, why it is used, who may access it, and how users can request corrections to internship records.</p>
                    </div>
                    <div class="public-info-body">
                        <div class="public-info-grid">
                            <section class="public-info-box">
                                <h2>Information Collected</h2>
                                <ul>
                                    <li>Account name, email, username, role, and profile details.</li>
                                    <li>Student application and internship placement details.</li>
                                    <li>Attendance logs, rendered hours, approvals, and reports.</li>
                                    <li>Uploaded files, document status, and system activity logs.</li>
                                </ul>
                            </section>
                            <section class="public-info-box">
                                <h2>Why It Is Used</h2>
                                <ul>
                                    <li>To process student applications and verify internship status.</li>
                                    <li>To monitor attendance, rendered hours, and OJT progress.</li>
                                    <li>To support document review, approvals, and school reporting.</li>
                                    <li>To protect accounts and troubleshoot system issues.</li>
                                </ul>
                            </section>
                            <section class="public-info-box">
                                <h2>Who Can Access It</h2>
                                <p>Access is based on assigned roles. Students, coordinators, supervisors, and administrators may only view or manage records needed for their approved internship responsibilities.</p>
                            </section>
                            <section class="public-info-box">
                                <h2>How Long It Is Kept</h2>
                                <p>Records may be retained while needed for internship monitoring, academic verification, reporting, audits, account support, and school record requirements.</p>
                            </section>
                        </div>
                        <section class="public-info-section">
                            <h2>Data Protection</h2>
                            <p>BioTern uses role-based access and account controls to limit who can view or change information. Users also share responsibility by protecting their account, signing out on shared devices, and uploading only required documents.</p>
                        </section>
                        <section class="public-info-section">
                            <h2>Corrections and Privacy Questions</h2>
                            <p>If your name, contact details, internship placement, attendance, or document status is incorrect, contact your coordinator or administrator. Include the record that needs correction and the correct information to review.</p>
                        </section>
                    </div>
                </article>
            </div>
        </div>
    </main>

    <footer class="footer public-info-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright"><span>Copyright &copy; <?php echo $year; ?></span></p>
            <p class="mb-0 public-info-credit"><span>Developed by <strong>ACT 2A - Group 5</strong></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="help.php" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="terms.php" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="privacy.php" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </div>
    </footer>
<?php $page_render_footer = false; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
