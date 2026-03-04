<?php
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce authenticated session for all pages using the shared app header.
$header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
if ($header_user_id_session <= 0) {
    header('Location: /BioTern/BioTern_organized/auth-login-cover.php');
    exit;
}

// Refresh session identity from DB so page access stays connected to current account data.
$header_db = @new mysqli('127.0.0.1', 'root', '', 'biotern_db');
if (!$header_db->connect_errno) {
    $stmt = $header_db->prepare("SELECT id, name, username, email, role, is_active, profile_picture FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $header_user_id_session);
        $stmt->execute();
        $header_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$header_user || (int)($header_user['is_active'] ?? 0) !== 1) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: /BioTern/BioTern_organized/auth-login-cover.php');
            exit;
        }

        $_SESSION['user_id'] = (int)$header_user['id'];
        $_SESSION['name'] = (string)($header_user['name'] ?? '');
        $_SESSION['username'] = (string)($header_user['username'] ?? '');
        $_SESSION['email'] = (string)($header_user['email'] ?? '');
        $_SESSION['role'] = (string)($header_user['role'] ?? '');
        $_SESSION['profile_picture'] = (string)($header_user['profile_picture'] ?? '');
        $_SESSION['logged_in'] = true;
    }
    $header_db->close();
}

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'BioTern';
}
if (!isset($base_href)) {
    $base_href = '';
}

$biotern_theme_api_endpoint = $base_href . 'api/theme-customizer.php';
require_once __DIR__ . '/theme-preferences.php';

$default_theme_prefs = [
    'skin' => 'light',
    'menu' => 'auto',
    'font' => 'default',
    'navigation' => 'light',
    'header' => 'light',
];

if (function_exists('biotern_theme_preferences')) {
    $biotern_theme_preferences = biotern_theme_preferences();
} else {
    $biotern_theme_preferences = $default_theme_prefs;
}

if (!is_array($biotern_theme_preferences)) {
    $biotern_theme_preferences = $default_theme_prefs;
}

$biotern_theme_preferences = array_merge($default_theme_prefs, $biotern_theme_preferences);

$html_classes = [];
if (($biotern_theme_preferences['skin'] ?? 'light') === 'dark') {
    $html_classes[] = 'app-skin-dark';
}
if (($biotern_theme_preferences['menu'] ?? 'auto') === 'mini') {
    $html_classes[] = 'minimenu';
}
if (($biotern_theme_preferences['font'] ?? 'default') !== 'default') {
    $html_classes[] = (string)$biotern_theme_preferences['font'];
}
if (($biotern_theme_preferences['navigation'] ?? 'light') === 'dark') {
    $html_classes[] = 'app-navigation-dark';
}
if (($biotern_theme_preferences['header'] ?? 'light') === 'dark') {
    $html_classes[] = 'app-header-dark';
}
$html_class_attr = implode(' ', $html_classes);

$header_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
if ($header_user_name === '') {
    $header_user_name = 'BioTern User';
}
$header_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
if ($header_user_email === '') {
    $header_user_email = 'admin@biotern.local';
}
$header_user_role = strtolower(trim((string)($_SESSION['role'] ?? '')));

$header_avatar = 'assets/images/avatar/1.png';
$session_avatar = trim((string)($_SESSION['profile_picture'] ?? ''));
if ($session_avatar !== '') {
    $normalized_avatar = ltrim(str_replace('\\', '/', $session_avatar), '/');
    $avatar_fs_path = dirname(__DIR__) . '/' . $normalized_avatar;
    if (is_file($avatar_fs_path)) {
        $header_avatar = $normalized_avatar;
    }
}
?>
<!DOCTYPE html>
<html lang="zxx"<?php echo $html_class_attr !== '' ? ' class="' . htmlspecialchars($html_class_attr, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <?php if ($base_href !== ''): ?>
        <base href="<?php echo htmlspecialchars($base_href, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
    <script src="assets/js/theme-preload-init.min.js"></script>
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/dataTables.bs5.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <!--! END: Vendors CSS-->
    <script>
        window.__bioternThemePrefs = <?php echo json_encode($biotern_theme_preferences, JSON_UNESCAPED_SLASHES); ?>;
        window.__bioternThemeApi = <?php echo json_encode($biotern_theme_api_endpoint, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <!--! BEGIN: Early Skin Script -->
    <script>
        // Apply saved skin + sidebar state as early as possible to avoid initial layout flash.
        (function(){
            var serverPrefs = window.__bioternThemePrefs || {};
            var allowedFonts = [
                'app-font-family-inter',
                'app-font-family-lato',
                'app-font-family-rubik',
                'app-font-family-cinzel',
                'app-font-family-nunito',
                'app-font-family-roboto',
                'app-font-family-ubuntu',
                'app-font-family-poppins',
                'app-font-family-raleway',
                'app-font-family-system-ui',
                'app-font-family-noto-sans',
                'app-font-family-fira-sans',
                'app-font-family-work-sans',
                'app-font-family-open-sans',
                'app-font-family-maven-pro',
                'app-font-family-quicksand',
                'app-font-family-montserrat',
                'app-font-family-josefin-sans',
                'app-font-family-ibm-plex-sans',
                'app-font-family-montserrat-alt',
                'app-font-family-roboto-slab',
                'app-font-family-source-sans-pro'
            ];

            function clearFontClasses() {
                try {
                    var cls = document.documentElement.className || '';
                    var cleaned = cls.replace(/\bapp-font-family-[^\s]+\b/g, '').replace(/\s{2,}/g, ' ').trim();
                    document.documentElement.className = cleaned;
                } catch (e) {
                }
            }

            function applyFont(fontClass) {
                clearFontClasses();
                if (fontClass && allowedFonts.indexOf(fontClass) !== -1) {
                    document.documentElement.classList.add(fontClass);
                }
            }

            function getSavedFont() {
                if (typeof serverPrefs.font === 'string' && serverPrefs.font !== '') {
                    return serverPrefs.font;
                }

                try {
                    var legacyFont = localStorage.getItem('font-family');
                    return legacyFont !== null ? legacyFont : 'default';
                } catch (e) {
                    return 'default';
                }
            }

            applyFont(getSavedFont());

            function applyNavigationMode(mode) {
                document.documentElement.classList.remove('app-navigation-dark');
                if (mode === 'dark') {
                    document.documentElement.classList.add('app-navigation-dark');
                }
            }

            function applyHeaderMode(mode) {
                document.documentElement.classList.remove('app-header-dark');
                if (mode === 'dark') {
                    document.documentElement.classList.add('app-header-dark');
                }
            }

            function getSavedNavigationMode() {
                if (serverPrefs.navigation === 'dark' || serverPrefs.navigation === 'light') {
                    return serverPrefs.navigation;
                }
                try {
                    var nav = localStorage.getItem('app-navigation');
                    if (nav === 'app-navigation-dark') return 'dark';
                } catch (e) {
                }
                return 'light';
            }

            function getSavedHeaderMode() {
                if (serverPrefs.header === 'dark' || serverPrefs.header === 'light') {
                    return serverPrefs.header;
                }
                try {
                    var hdr = localStorage.getItem('app-header');
                    if (hdr === 'app-header-dark') return 'dark';
                } catch (e) {
                }
                return 'light';
            }

            applyNavigationMode(getSavedNavigationMode());
            applyHeaderMode(getSavedHeaderMode());

            function getSavedSkin() {
                if (serverPrefs.skin === 'dark') return 'app-skin-dark';
                if (serverPrefs.skin === 'light') return '';
                try {
                    // Respect the primary key even when intentionally set to empty (light mode).
                    var primary = localStorage.getItem('app-skin');
                    if (primary !== null) return primary;
                    var alt = localStorage.getItem('app_skin');
                    if (alt !== null) return alt;
                    var theme = localStorage.getItem('theme');
                    if (theme !== null) return theme;
                    var legacy = localStorage.getItem('app-skin-dark');
                    return legacy !== null ? legacy : '';
                } catch (e) {
                    return '';
                }
            }

            var skin = getSavedSkin();
            if (typeof skin === 'string' && skin.indexOf('dark') !== -1) {
                document.documentElement.classList.add('app-skin-dark');
            } else {
                document.documentElement.classList.remove('app-skin-dark');
            }

            try {
                var menuState = localStorage.getItem('nexel-classic-dashboard-menu-mini-theme');
                if (!menuState) {
                    if (serverPrefs.menu === 'mini') {
                        menuState = 'menu-mini-theme';
                    } else if (serverPrefs.menu === 'expanded') {
                        menuState = 'menu-expend-theme';
                    }
                }
                var width = window.innerWidth || document.documentElement.clientWidth || 0;

                if (menuState === 'menu-mini-theme') {
                    document.documentElement.classList.add('minimenu');
                } else if (menuState === 'menu-expend-theme') {
                    document.documentElement.classList.remove('minimenu');
                } else {
                    if (width >= 1024 && width <= 1600) {
                        document.documentElement.classList.add('minimenu');
                    } else if (width > 1600) {
                        document.documentElement.classList.remove('minimenu');
                    }
                }
            } catch (e) {
                // ignore localStorage errors
            }
        })();
    </script>
    <!--! END: Early Skin Script -->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css" />
    <!--! END: Custom CSS-->
    <style>
        html.app-skin-dark input.form-control,
        html.app-skin-dark textarea.form-control,
        html.app-skin-dark .form-control[type="text"],
        html.app-skin-dark .form-control[type="email"],
        html.app-skin-dark .form-control[type="password"],
        html.app-skin-dark .form-control[type="number"],
        html.app-skin-dark .form-control[type="date"],
        html.app-skin-dark .form-control[type="time"],
        html.app-skin-dark .form-control[type="search"],
        html.app-skin-dark .form-control[type="tel"],
        html.app-skin-dark .form-control[type="url"] {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark input.form-control::placeholder,
        html.app-skin-dark textarea.form-control::placeholder {
            color: #d1dcf0 !important;
            opacity: 1 !important;
        }

        /* Keep browser autofill readable in dark mode */
        html.app-skin-dark input.form-control:-webkit-autofill,
        html.app-skin-dark input.form-control:-webkit-autofill:hover,
        html.app-skin-dark input.form-control:-webkit-autofill:focus,
        html.app-skin-dark textarea.form-control:-webkit-autofill,
        html.app-skin-dark textarea.form-control:-webkit-autofill:hover,
        html.app-skin-dark textarea.form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff !important;
            caret-color: #ffffff !important;
            box-shadow: 0 0 0 1000px #0f172a inset !important;
            -webkit-box-shadow: 0 0 0 1000px #0f172a inset !important;
            border-color: #4a5568 !important;
            transition: background-color 9999s ease-out 0s;
        }

        html.app-skin-dark input.form-control:autofill,
        html.app-skin-dark textarea.form-control:autofill {
            color: #ffffff !important;
            background-color: #0f172a !important;
        }

        html.app-skin-dark select.form-control,
        html.app-skin-dark select.form-select,
        html.app-skin-dark select.form-control option,
        html.app-skin-dark select.form-select option {
            color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .select2-container--default .select2-selection--single,
        html.app-skin-dark .select2-container--default .select2-selection--multiple {
            color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .select2-container--default .select2-selection--single .select2-selection__rendered,
        html.app-skin-dark .select2-container--default .select2-selection--multiple .select2-selection__rendered,
        html.app-skin-dark .select2-container--default .select2-selection__placeholder {
            color: #ffffff !important;
        }

        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown,
        html.app-skin-dark .select2-container--default .select2-results__option {
            color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
            color: #ffffff !important;
            background-color: #334155 !important;
        }

        html.app-skin-dark .filter-form input.form-control,
        html.app-skin-dark .filter-form select.form-control,
        html.app-skin-dark .filter-form select.form-select,
        html.app-skin-dark .filter-form .select2-container--default .select2-selection--single,
        html.app-skin-dark .filter-form .select2-container--default .select2-selection--multiple {
            color: #ffffff !important;
        }

        html.app-skin-dark .filter-form input.form-control::placeholder {
            color: #d1dcf0 !important;
            opacity: 1;
        }

        html.app-skin-dark .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered,
        html.app-skin-dark .filter-form .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            color: #ffffff !important;
        }

        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown,
        html.app-skin-dark .select2-container--default .select2-results__option {
            color: #ffffff !important;
        }

        html.app-skin-dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
            color: #ffffff !important;
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/navigation.php'; ?>
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item nxl-header-search d-none d-sm-flex">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                            <div class="input-group search-form">
                                <span class="input-group-text">
                                    <i class="feather-search fs-6 text-muted"></i>
                                </span>
                                <input type="text" id="headerSearchInput" name="header_search" class="form-control search-input-field" placeholder="Search page...">
                                <span class="input-group-text">
                                    <button type="button" class="btn-close" id="headerSearchClear"></button>
                                </span>
                            </div>
                            <div class="dropdown-divider mt-0"></div>
                            <div class="px-3 py-2 fs-12 text-muted">Type and press Enter to open first matching menu page.</div>
                        </div>
                    </div>
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <span class="badge bg-danger nxl-h-badge">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="notifications-item">
                                <img src="assets/images/avatar/2.png" alt="" class="rounded me-3 border">
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line">You have new notifications.</a>
                                    <div class="notifications-date text-muted border-bottom border-bottom-dashed">Just now</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar">
                                    <div>
                                        <h6 class="text-dark mb-0">
                                            <?php echo htmlspecialchars($header_user_name, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($header_user_role !== ''): ?>
                                                <span class="badge bg-soft-success text-success ms-1"><?php echo htmlspecialchars(ucfirst($header_user_role), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </h6>
                                        <span class="fs-12 fw-medium text-muted"><?php echo htmlspecialchars($header_user_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <span class="hstack">
                                    <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                    <span>Active</span>
                                </span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-activity"></i>
                                <span>Activity Feed</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-bell"></i>
                                <span>Notifications</span>
                            </a>
                            <a href="settings-general.php" class="dropdown-item">
                                <i class="feather-settings"></i>
                                <span>Account Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="auth-login-cover.php?logout=1" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!--! ================================================================ !-->
    <!--! [End] Header !-->
    <!--! ================================================================ !-->

    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container">
        <div class="nxl-content">
