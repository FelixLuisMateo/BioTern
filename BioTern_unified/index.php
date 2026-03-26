<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
    $index_conn = @new mysqli(
        defined('DB_HOST') ? DB_HOST : '127.0.0.1',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'biotern_db',
        defined('DB_PORT') ? (int)DB_PORT : 3306
    );
    if (!$index_conn->connect_errno) {
        biotern_auth_restore_session_from_cookie($index_conn);
        $index_conn->close();
    }
}

$landing_user_id = (int)($_SESSION['user_id'] ?? 0);
$landing_logged_in = $landing_user_id > 0 || !empty($_SESSION['logged_in']);
$landing_signin_label = $landing_logged_in ? 'Dashboard' : 'Sign In';
$landing_hero_label = $landing_logged_in ? 'Go to Dashboard' : 'Go to Dashboard Login';

$year = date('Y');
$landing_theme_preferences = [
    'skin' => 'light',
    'menu' => 'auto',
    'font' => 'default',
    'navigation' => 'light',
    'header' => 'light',
];
$landing_theme_api = 'api/theme-customizer.php';



// Hardcode the web root-relative path for correct URL generation
$landing_app_root = '/biotern/biotern_unified';

$favicon_root = $landing_app_root . '/';
$favicon_ico_path = __DIR__ . '/assets/images/favicon.ico';
$favicon_png_path = __DIR__ . '/assets/images/favicon-rounded.png';
$favicon_ico_mtime = @filemtime($favicon_ico_path);
$favicon_png_mtime = @filemtime($favicon_png_path);
$favicon_ico_version = ($favicon_ico_mtime !== false) ? (string)$favicon_ico_mtime : '20260310';
$favicon_png_version = ($favicon_png_mtime !== false) ? (string)$favicon_png_mtime : '20260310';
$favicon_ico_href = $favicon_root . 'assets/images/favicon.ico?v=' . rawurlencode($favicon_ico_version);
$favicon_png_href = $favicon_root . 'assets/images/favicon-rounded.png?v=' . rawurlencode($favicon_png_version);

$landing_apply_href = $landing_app_root . '/auth/auth-register-creative.php?role=student';
$landing_start_href = $landing_app_root . '/auth/auth-register-creative.php?role=student';
$landing_signin_href = $landing_logged_in
    ? ($landing_app_root . '/homepage.php')
    : '/BioTern/BioTern_unified/auth/auth-login-cover.php';
$landing_hero_href = $landing_signin_href;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Internship Monitoring</title>
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo htmlspecialchars($favicon_png_href, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_ico_href, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_ico_href, ENT_QUOTES, 'UTF-8'); ?>">
    <script src="assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/core.css">
    <link rel="stylesheet" type="text/css" href="assets/css/biotern-unified.css">

    <style>
        :root {
            --biotern-primary: #3454d1;
            --biotern-ink: #1f2937;
            --biotern-soft: #e9edf7;
            --biotern-accent: #25b865;
        }

        body {
            background:
                radial-gradient(900px 420px at 8% 16%, rgba(52, 84, 209, 0.14), transparent 65%),
                radial-gradient(760px 380px at 92% 84%, rgba(37, 184, 101, 0.1), transparent 65%),
                #f7f9fc;
            color: var(--biotern-ink);
            min-height: 100vh;
        }

        body.landing-public {
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        body.landing-public .nxl-header {
            left: 0;
            right: 0;
            min-height: 52px;
        }

        body.landing-public .nxl-container {
            top: 0;
            margin-left: 0;
            min-height: auto;
            flex: 1 0 auto;
            margin-top: 24px;
        }

        body.landing-public .nxl-content {
            padding-top: 0;
        }

        html.app-skin-dark body {
            background:
                radial-gradient(900px 420px at 8% 16%, rgba(52, 84, 209, 0.18), transparent 65%),
                radial-gradient(760px 380px at 92% 84%, rgba(37, 184, 101, 0.12), transparent 65%),
                #0a122b;
            color: #e6ecff;
        }

        .landing-header {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid rgba(31, 41, 55, 0.08);
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .landing-header .header-wrapper {
            min-height: 52px;
            max-width: 1320px;
            margin: 0 auto;
            width: 100%;
            padding: 0 12px;
            align-items: center;
        }

        .landing-header .nxl-h-item {
            min-height: 52px;
        }

        .landing-header .nxl-head-link {
            padding: 6px 8px;
        }

        html.app-skin-dark .landing-header {
            background: rgba(11, 17, 34, 0.9);
            border-bottom-color: rgba(148, 163, 184, 0.2);
        }

        .landing-brand-logo {
            width: auto;
            height: 40px;
            object-fit: contain;
        }

        .landing-header .btn.btn-sm {
            padding: 6px 10px;
            font-size: 11px;
            line-height: 1.15;
        }

        html.app-skin-dark .landing-brand-logo {
            filter: brightness(0) invert(1);
        }

        .hero-wrap {
            padding: 46px 0 24px;
        }

        .hero-card {
            border-radius: 20px;
            border: 1px solid rgba(52, 84, 209, 0.18);
            background: linear-gradient(130deg, #ffffff 0%, #f6f9ff 56%, #eef3ff 100%);
            box-shadow: 0 24px 52px rgba(20, 39, 80, 0.12);
            overflow: hidden;
        }

        html.app-skin-dark .hero-card {
            border-color: rgba(107, 138, 255, 0.28);
            background: linear-gradient(130deg, #121a32 0%, #101b38 56%, #0f1a36 100%);
            box-shadow: 0 24px 52px rgba(0, 0, 0, 0.45);
        }

        .hero-body {
            padding: 2.2rem;
        }

        .hero-title {
            font-size: clamp(1.9rem, 4vw, 2.8rem);
            line-height: 1.08;
            color: #17203a;
            letter-spacing: -0.7px;
        }

        .hero-card .hero-title {
            color: #17203a !important;
            text-shadow: none !important;
        }

        html.app-skin-dark .hero-card .hero-title {
            color: #f1f5ff !important;
        }

        .hero-subtitle {
            color: #5c6782;
            max-width: 58ch;
        }

        .hero-card .hero-subtitle {
            color: #5c6782 !important;
        }

        html.app-skin-dark .hero-card .hero-subtitle {
            color: #c5d0ee !important;
        }

        .hero-cta {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .hero-logo {
            max-width: 260px;
            height: auto;
            animation: float-logo 4s ease-in-out infinite;
            filter: drop-shadow(0 16px 24px rgba(52, 84, 209, 0.2));
        }

        .hero-college-logo {
            max-width: 210px;
            width: 100%;
            height: auto;
            object-fit: contain;
            animation: float-logo 4s ease-in-out infinite;
            filter: drop-shadow(0 16px 24px rgba(0, 0, 0, 0.15));
        }

        @media (max-width: 991.98px) {
            .landing-brand-logo {
                height: 34px;
            }

            .landing-header .header-wrapper {
                padding: 0 12px;
            }

            .hero-logo {
                max-width: 210px;
            }

            .hero-college-logo {
                max-width: 170px;
            }
        }

        @keyframes float-logo {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .mini-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(52, 84, 209, 0.08);
            color: #2c44a6;
            border: 1px solid rgba(52, 84, 209, 0.16);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        html.app-skin-dark .mini-pill {
            background: rgba(79, 112, 238, 0.16);
            color: #b8cbff;
            border-color: rgba(134, 160, 255, 0.35);
        }

        .feature-card {
            height: 100%;
            border-radius: 14px;
            border: 1px solid rgba(31, 41, 55, 0.08);
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        html.app-skin-dark .feature-card {
            border-color: rgba(148, 163, 184, 0.16);
            background: rgba(15, 24, 47, 0.9);
        }

        html.app-skin-dark .feature-card h5 {
            color: #f1f5ff;
        }

        html.app-skin-dark .feature-card .text-muted {
            color: #b8c5e3 !important;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(31, 41, 55, 0.1);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: rgba(52, 84, 209, 0.12);
            color: var(--biotern-primary);
        }

        .landing-footer {
            border-top: 1px solid rgba(31, 41, 55, 0.08);
            background: #ffffff;
            padding: 18px 0;
            margin-top: 0;
            flex-shrink: 0;
        }

        html.app-skin-dark .landing-footer {
            border-top-color: rgba(148, 163, 184, 0.2);
            background: #0b1122;
        }

        html.app-skin-dark .landing-footer .text-muted {
            color: #b8c5e3 !important;
        }

        @media (max-width: 575.98px) {
            .landing-header .btn.btn-sm {
                padding-left: 10px;
                padding-right: 10px;
                line-height: 1.2;
            }

            .hero-body {
                padding: 1.5rem;
            }

            .landing-header .header-wrapper {
                gap: 8px;
            }
        }
    </style>
</head>
<body class="landing-public" data-theme-prefs="<?php echo htmlspecialchars(json_encode($landing_theme_preferences), ENT_QUOTES, 'UTF-8'); ?>" data-theme-api="<?php echo htmlspecialchars($landing_theme_api, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="nxl-header landing-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="<?php echo htmlspecialchars($landing_app_root . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($landing_app_root . '/assets/images/logo-full-header.png', ENT_QUOTES, 'UTF-8'); ?>" alt="BioTern" class="landing-brand-logo">
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
                    <a href="<?php echo htmlspecialchars($landing_apply_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light-brand">Apply</a>
                    <a href="/BioTern/BioTern_unified/auth/auth-login-cover.php" class="btn btn-sm btn-primary"><?php echo htmlspecialchars($landing_signin_label, ENT_QUOTES, 'UTF-8'); ?></a>
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
                            <h1 class="hero-title mb-3">Built for schools, supervisors, and interns in one BioTern workspace.</h1>
                            <p class="hero-subtitle mb-4">Track attendance with biometric confidence, monitor internship progress, and generate key documents and reports without juggling multiple systems.</p>
                            <div class="hero-cta">
                                <a href="<?php echo htmlspecialchars($landing_hero_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><?php echo htmlspecialchars($landing_hero_label, ENT_QUOTES, 'UTF-8'); ?></a>
                                <a href="<?php echo htmlspecialchars($landing_start_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light-brand btn-lg">Start Application</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 d-flex justify-content-center p-4 p-lg-0">
                        <img class="hero-college-logo" src="<?php echo htmlspecialchars($landing_app_root . '/assets/images/auth/auth-cover-login-bg.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Clark College Logo">
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

    <script src="<?php echo htmlspecialchars($landing_app_root . '/assets/vendors/js/vendors.min.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($landing_app_root . '/assets/js/common-init.min.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($landing_app_root . '/assets/js/global-ui-helpers.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($landing_app_root . '/assets/js/theme-preferences-runtime.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($landing_app_root . '/assets/js/theme-customizer-init.min.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

