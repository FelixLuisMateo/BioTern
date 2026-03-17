<?php
require_once dirname(__DIR__) . '/config/db.php';
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Build the login URL dynamically so it always points to BioTern_unified regardless of server path.
$_header_script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$_header_unified_pos = stripos($_header_script, '/BioTern_unified/');
$_header_login_url = ($_header_unified_pos !== false)
    ? substr($_header_script, 0, $_header_unified_pos) . '/BioTern_unified/auth/auth-login-cover.php'
    : '/BioTern_unified/auth/auth-login-cover.php';

// Enforce authenticated session for all pages using the shared app header.
$header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
if ($header_user_id_session <= 0) {
    header('Location: ' . $_header_login_url);
    exit;
}

// Refresh session identity from DB so page access stays connected to current account data.
$header_db = @new mysqli(defined('DB_HOST') ? DB_HOST : '127.0.0.1', defined('DB_USER') ? DB_USER : 'root', defined('DB_PASS') ? DB_PASS : '', defined('DB_NAME') ? DB_NAME : 'biotern_db');
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
            header('Location: ' . $_header_login_url);
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
// Resolve base URL once so every relative head asset (including favicon) stays valid.
if ($base_href === '') {
    $resolved_base_href = '';

    $doc_root_real = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $project_root_real = realpath(dirname(__DIR__));
    if (is_string($doc_root_real) && $doc_root_real !== '' && is_string($project_root_real) && $project_root_real !== '') {
        $doc_root_norm = str_replace('\\', '/', rtrim($doc_root_real, '/\\'));
        $project_root_norm = str_replace('\\', '/', rtrim($project_root_real, '/\\'));
        if (stripos($project_root_norm, $doc_root_norm) === 0) {
            $relative_root = trim(substr($project_root_norm, strlen($doc_root_norm)), '/');
            $resolved_base_href = '/' . ($relative_root !== '' ? ($relative_root . '/') : '');
        }
    }

    if ($resolved_base_href === '') {
        $script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $project_segment = '/' . basename(dirname(__DIR__)) . '/';
        $project_pos = stripos($script_name, $project_segment);
        if ($project_pos !== false) {
            $resolved_base_href = substr($script_name, 0, $project_pos) . $project_segment;
        } else {
            $resolved_base_href = '/';
        }
    }

    $base_href = $resolved_base_href;
}

$favicon_root = $base_href;
$favicon_ico_path = dirname(__DIR__) . '/assets/images/favicon.ico';
$favicon_png_path = dirname(__DIR__) . '/assets/images/favicon-rounded.png';
$favicon_logo_path = dirname(__DIR__) . '/assets/images/logo-abbr.png';
$favicon_ico_mtime = @filemtime($favicon_ico_path);
$favicon_png_mtime = @filemtime($favicon_png_path);
$favicon_logo_mtime = @filemtime($favicon_logo_path);
$favicon_ico_version = ($favicon_ico_mtime !== false) ? (string)$favicon_ico_mtime : '20260310';
$favicon_png_version = ($favicon_png_mtime !== false) ? (string)$favicon_png_mtime : '20260310';
$favicon_logo_version = ($favicon_logo_mtime !== false) ? (string)$favicon_logo_mtime : '20260310';
$favicon_ico_href = $favicon_root . 'assets/images/favicon.ico?v=' . rawurlencode($favicon_ico_version);
$favicon_png_href = $favicon_root . 'assets/images/favicon-rounded.png?v=' . rawurlencode($favicon_png_version);
$favicon_logo_href = $favicon_root . 'assets/images/logo-abbr.png?v=' . rawurlencode($favicon_logo_version);

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
$html_classes[] = 'ui-preload';
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
$theme_prefs_json = htmlspecialchars((string)json_encode($biotern_theme_preferences, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$theme_api_json = htmlspecialchars((string)json_encode($biotern_theme_api_endpoint, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

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

$header_notifications = [];
$header_notifications_unread = 0;
if ($header_user_id_session > 0) {
    $hdr_db = @new mysqli(defined('DB_HOST') ? DB_HOST : '127.0.0.1', defined('DB_USER') ? DB_USER : 'root', defined('DB_PASS') ? DB_PASS : '', defined('DB_NAME') ? DB_NAME : 'biotern_db');
    if (!$hdr_db->connect_errno) {
        $has_title = false;
        $has_message = false;
        $has_type = false;
        $has_data = false;
        $col_res = $hdr_db->query("SHOW COLUMNS FROM notifications");
        if ($col_res instanceof mysqli_result) {
            while ($col = $col_res->fetch_assoc()) {
                $field = strtolower((string)($col['Field'] ?? ''));
                if ($field === 'title') $has_title = true;
                if ($field === 'message') $has_message = true;
                if ($field === 'type') $has_type = true;
                if ($field === 'data') $has_data = true;
            }
        }

        $count_stmt = $hdr_db->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)");
        if ($count_stmt) {
            $count_stmt->bind_param('i', $header_user_id_session);
            $count_stmt->execute();
            $row = $count_stmt->get_result()->fetch_assoc();
            $count_stmt->close();
            $header_notifications_unread = (int)($row['unread_count'] ?? 0);
        }

        if ($has_title && $has_message) {
            $list_stmt = $hdr_db->prepare(
                "SELECT id, title, message, is_read, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC, id DESC
                 LIMIT 6"
            );
            if ($list_stmt) {
                $list_stmt->bind_param('i', $header_user_id_session);
                $list_stmt->execute();
                $res = $list_stmt->get_result();
                while ($n = $res->fetch_assoc()) {
                    $header_notifications[] = [
                        'title' => (string)($n['title'] ?? 'Notification'),
                        'message' => (string)($n['message'] ?? ''),
                        'is_read' => (int)($n['is_read'] ?? 0),
                        'created_at' => (string)($n['created_at'] ?? ''),
                    ];
                }
                $list_stmt->close();
            }
        } elseif ($has_type && $has_data) {
            $list_stmt = $hdr_db->prepare(
                "SELECT id, type, data, is_read, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC, id DESC
                 LIMIT 6"
            );
            if ($list_stmt) {
                $list_stmt->bind_param('i', $header_user_id_session);
                $list_stmt->execute();
                $res = $list_stmt->get_result();
                while ($n = $res->fetch_assoc()) {
                    $raw_data = (string)($n['data'] ?? '');
                    $title = ucfirst((string)($n['type'] ?? 'notification'));
                    $message = $raw_data;
                    $json = json_decode($raw_data, true);
                    if (is_array($json)) {
                        $title = (string)($json['title'] ?? $title);
                        $message = (string)($json['message'] ?? $message);
                    }
                    $header_notifications[] = [
                        'title' => $title,
                        'message' => $message,
                        'is_read' => (int)($n['is_read'] ?? 0),
                        'created_at' => (string)($n['created_at'] ?? ''),
                    ];
                }
                $list_stmt->close();
            }
        }
        $hdr_db->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zxx" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $html_class_attr !== '' ? 'class="' . htmlspecialchars($html_class_attr, ENT_QUOTES, 'UTF-8') . '" ' : '';
?>data-biotern-theme-prefs="<?php echo $theme_prefs_json; ?>" data-biotern-theme-api="<?php echo $theme_api_json; ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($base_href !== ''): ?>
        <base href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($base_href, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="icon" type="image/png" sizes="192x192" href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($favicon_logo_href, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($favicon_png_href, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($favicon_logo_href, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/x-icon" href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($favicon_ico_href, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($favicon_ico_href, ENT_QUOTES, 'UTF-8'); ?>">
    <!--! END: Favicon-->
    <script src="assets/js/pace-options.js"></script>
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
    <!--! BEGIN: Early Skin Script -->
    <script src="assets/js/header-early-skin.js"></script>
    <script>
        (function () {
            var root = document.documentElement;
            function clearPreload() {
                root.classList.remove('ui-preload');
            }
            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                requestAnimationFrame(clearPreload);
            } else {
                document.addEventListener('DOMContentLoaded', function () {
                    requestAnimationFrame(clearPreload);
                }, { once: true });
            }
            window.addEventListener('load', clearPreload, { once: true });
            setTimeout(clearPreload, 1500);
        })();
    </script>
    <!--! END: Early Skin Script -->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/css/layout-shared-overrides.css" />
    <?php
require_once dirname(__DIR__) . '/config/db.php';
if (isset($page_styles) && is_array($page_styles)): ?>
        <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($page_styles as $stylesheet): ?>
            <?php
require_once dirname(__DIR__) . '/config/db.php';
if (is_string($stylesheet) && trim($stylesheet) !== ''): ?>
                <link rel="stylesheet" type="text/css" href="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
        <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
    <!--! END: Custom CSS-->
</head>

<body>
    <?php
require_once dirname(__DIR__) . '/config/db.php';
include_once __DIR__ . '/navigation.php'; ?>
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
                    <a href="javascript:void(0);" id="menu-expend-button" class="hidden-inline-toggle">
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
                            <a href="javascript:void(0);" class="nxl-head-link me-0" data-action="toggle-fullscreen" aria-label="Toggle fullscreen">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button hidden-inline-toggle">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item click-only-dropdown header-notification-dropdown">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($header_notifications_unread > 0): ?>
                                <span class="badge bg-danger nxl-h-badge"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$header_notifications_unread; ?></span>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="d-flex justify-content-between align-items-center notifications-head px-3 py-2 border-bottom">
                                <span class="fw-semibold">Notifications</span>
                                <span class="badge bg-soft-primary text-primary"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$header_notifications_unread; ?> unread</span>
                            </div>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if (!empty($header_notifications)): ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($header_notifications as $n): ?>
                                    <div class="notifications-item">
                                        <img src="assets/images/avatar/1.png" alt="" class="rounded me-3 border">
                                        <div class="notifications-desc">
                                            <a href="javascript:void(0);" class="font-body text-truncate-2-line"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                            <div class="fs-12 text-muted text-truncate-2-line"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="notifications-date text-muted border-bottom border-bottom-dashed"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($n['created_at'] !== '' ? date('M d, Y h:i A', strtotime($n['created_at'])) : 'Just now', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                <div class="px-3 py-3 text-muted fs-12">No notifications yet.</div>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item click-only-dropdown">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-display="static" data-bs-offset="0,10" role="button" data-bs-auto-close="outside">
                            <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar">
                                    <div>
                                        <h6 class="text-dark mb-0">
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($header_user_name, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($header_user_role !== ''): ?>
                                                <span class="badge bg-soft-success text-success ms-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(ucfirst($header_user_role), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </h6>
                                        <span class="fs-12 fw-medium text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($header_user_email, ENT_QUOTES, 'UTF-8'); ?></span>
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

