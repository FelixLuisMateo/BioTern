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
                        <p class="public-info-lede">These terms explain how students, coordinators, supervisors, and administrators should use BioTern for internship applications, attendance, documents, approvals, and reports.</p>
                    </div>
                    <div class="public-info-body">
                        <div class="public-info-grid">
                            <section class="public-info-box">
                                <h2>Purpose of the System</h2>
                                <p>BioTern is provided for school-related internship monitoring. It supports student applications, OJT records, document submission, approvals, reports, and coordinator review.</p>
                            </section>
                            <section class="public-info-box">
                                <h2>Account Responsibility</h2>
                                <p>Keep your sign-in details private. Activity submitted through your account may be treated as your official action unless you report account misuse promptly.</p>
                            </section>
                            <section class="public-info-box">
                                <h2>Accurate Records</h2>
                                <p>Applications, attendance logs, uploaded documents, company details, and reports must be accurate. Do not knowingly submit false, incomplete, or misleading information.</p>
                            </section>
                            <section class="public-info-box">
                                <h2>Role-Based Access</h2>
                                <p>Only use pages, records, and actions assigned to your role. Do not attempt to view, change, export, or approve records unless you are authorized to do so.</p>
                            </section>
                        </div>
                        <section class="public-info-section">
                            <h2>Prohibited Actions</h2>
                            <ul>
                                <li>Sharing another user&apos;s private information outside approved school workflows.</li>
                                <li>Uploading harmful files, unrelated documents, or content that does not belong in an internship record.</li>
                                <li>Bypassing security controls, manipulating attendance, or interfering with system operation.</li>
                            </ul>
                        </section>
                        <section class="public-info-section">
                            <h2>Review, Suspension, and Corrections</h2>
                            <p>Authorized personnel may review records for verification, compliance, and reporting. Accounts or submissions may be corrected, returned, limited, or suspended when information is inaccurate, incomplete, unauthorized, or harmful to the system.</p>
                        </section>
                        <section class="public-info-section">
                            <h2>System Changes</h2>
                            <p>BioTern may be updated to improve security, accuracy, performance, or school workflows. Features and page names may change as the internship process is refined.</p>
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
