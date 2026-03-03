<?php
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'BioTern';
}
if (!isset($base_href)) {
    $base_href = '';
}

$header_user_id = (int)($_SESSION['user_id'] ?? 0);
$header_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$header_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
$header_user_role = trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if ($header_user_name === '') {
    $header_user_name = 'BioTern User';
}
$session_profile = ltrim(str_replace('\\', '/', trim((string)($_SESSION['profile_picture'] ?? ''))), '/');
$header_avatar = '';
if ($session_profile !== '' && file_exists(dirname(__DIR__) . '/' . $session_profile)) {
    $header_avatar = $session_profile;
}
if ($header_avatar === '') {
    $avatar_index = ($header_user_id > 0) ? (($header_user_id % 5) + 1) : 1;
    $header_avatar = 'assets/images/avatar/' . $avatar_index . '.png';
}
?>
<!DOCTYPE html>
<html lang="zxx">

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
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/dataTables.bs5.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Early Skin Script -->
    <script>
        // Apply saved skin as early as possible to avoid flash-of-unstyled (FOUS)
        (function(){
            function getSavedSkin() {
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

        /* Keep topbar/user dropdown avatars perfectly circular */
        .nxl-header .user-avtar,
        .nxl-user-dropdown .user-avtar {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px;
            min-height: 40px;
            border-radius: 50% !important;
            object-fit: cover;
            aspect-ratio: 1 / 1;
            display: inline-block;
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
