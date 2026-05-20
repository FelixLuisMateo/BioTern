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
                        <p class="public-info-lede">Use this page as the quick guide for the main BioTern tasks: account access, student applications, internship documents, attendance records, and where to go when something needs correction.</p>
                    </div>
                    <div class="public-info-body">
                        <div class="public-info-grid">
                            <section class="public-info-box">
                                <h2>Account Access</h2>
                                <ul>
                                    <li>Use the Sign In button to access your dashboard.</li>
                                    <li>Students should apply first before expecting an active account.</li>
                                    <li>If your account is inactive, ask the coordinator or administrator to verify your status.</li>
                                </ul>
                            </section>
                            <section class="public-info-box">
                                <h2>Student Application</h2>
                                <ul>
                                    <li>Start from Student Application on the landing page.</li>
                                    <li>Submit accurate contact, school, and internship details.</li>
                                    <li>Watch for coordinator feedback if your application needs correction.</li>
                                </ul>
                            </section>
                            <section class="public-info-box">
                                <h2>Documents</h2>
                                <ul>
                                    <li>Upload only the required files for your internship process.</li>
                                    <li>Check document status before resubmitting the same file.</li>
                                    <li>Use clear file names so reviewers can identify each document.</li>
                                </ul>
                            </section>
                            <section class="public-info-box">
                                <h2>Attendance and OJT</h2>
                                <ul>
                                    <li>Review attendance entries and rendered hours regularly.</li>
                                    <li>Report missing or incorrect logs as soon as possible.</li>
                                    <li>Use OJT monitoring records as the source for progress review.</li>
                                </ul>
                            </section>
                        </div>
                        <section class="public-info-section">
                            <h2>Before Asking for Support</h2>
                            <ul>
                                <li>Refresh the page and sign in again if the dashboard does not load correctly.</li>
                                <li>Confirm that your profile details and internship information are complete.</li>
                                <li>Prepare a screenshot, your account name, and the page where the issue happened.</li>
                            </ul>
                        </section>
                        <section class="public-info-section">
                            <h2>Need More Help?</h2>
                            <p>Contact your OJT coordinator or system administrator for account activation, document review, attendance corrections, role access, and system errors. Include your name, role, section, and a short description of what you were trying to do.</p>
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
