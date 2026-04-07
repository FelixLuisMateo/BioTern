<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$header_script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$header_root = '/';
$header_project_pos = stripos($header_script_name, '/BioTern/BioTern/');
if ($header_project_pos !== false) {
    $header_root = substr($header_script_name, 0, $header_project_pos) . '/BioTern/BioTern/';
}
$header_login_url = $header_root . 'auth/auth-login-cover.php';

$page_is_public = isset($page_is_public) && $page_is_public === true;
$page_render_navigation = isset($page_render_navigation) ? (bool)$page_render_navigation : !$page_is_public;
$page_render_header = isset($page_render_header) ? (bool)$page_render_header : !$page_is_public;

// Enforce authenticated session for all non-public pages using the shared app header.
$header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
$header_db = null;
if (!$page_is_public) {
    if ($header_user_id_session <= 0) {
        header('Location: ' . $header_login_url);
        exit;
    }

    // Refresh session identity from DB so page access stays connected to current account data.
    $header_db = @new mysqli(
        defined('DB_HOST') ? DB_HOST : '127.0.0.1',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'biotern_db',
        defined('DB_PORT') ? (int)DB_PORT : 3306
    );
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
                header('Location: ' . $header_login_url);
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
    } else {
        $header_db = null;
    }
}

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'BioTern';
}
if (!isset($base_href)) {
    $base_href = '';
}

$favicon_ico_path = dirname(__DIR__) . '/assets/images/favicon.ico';
$favicon_png_path = dirname(__DIR__) . '/assets/images/favicon-rounded.png';
$favicon_logo_path = dirname(__DIR__) . '/assets/images/logo-abbr.png';
$favicon_ico_version = @filemtime($favicon_ico_path);
$favicon_png_version = @filemtime($favicon_png_path);
$favicon_logo_version = @filemtime($favicon_logo_path);
if ($favicon_ico_version === false) {
    $favicon_ico_version = '20260318';
}
if ($favicon_png_version === false) {
    $favicon_png_version = '20260318';
}
if ($favicon_logo_version === false) {
    $favicon_logo_version = '20260318';
}

if (!function_exists('header_asset_versioned_href')) {
    function header_asset_versioned_href(string $href): string
    {
        $trimmed = trim($href);
        if ($trimmed === '') {
            return $trimmed;
        }

        // Skip external/data URIs.
        if (preg_match('/^(?:[a-z]+:)?\\/\\//i', $trimmed) || stripos($trimmed, 'data:') === 0) {
            return $trimmed;
        }

        $parts = explode('?', $trimmed, 2);
        $relative = ltrim(str_replace('\\', '/', $parts[0]), '/');
        if ($relative === '') {
            return $trimmed;
        }

        $absolute = dirname(__DIR__) . '/' . $relative;
        $mtime = @filemtime($absolute);
        if ($mtime === false) {
            return $trimmed;
        }

        $separator = strpos($trimmed, '?') !== false ? '&' : '?';
        return $trimmed . $separator . 'v=' . rawurlencode((string)$mtime);
    }
}

if (!function_exists('header_resolve_avatar_href')) {
    function header_resolve_avatar_href(string $rawAvatar, string $fallback = 'assets/images/avatar/1.png'): string
    {
        $normalized = trim(str_replace('\\', '/', $rawAvatar));
        if ($normalized === '') {
            return header_asset_versioned_href($fallback);
        }

        $normalized = preg_replace('/\?.*$/', '', $normalized);
        $normalized = ltrim((string)$normalized, '/');
        $normalized = preg_replace('#^(\./)+#', '', (string)$normalized);

        $candidates = [];
        if ($normalized !== '') {
            $candidates[] = $normalized;
            if (stripos($normalized, 'BioTern/BioTern/') === 0) {
                $candidates[] = substr($normalized, strlen('BioTern/BioTern/'));
            } elseif (stripos($normalized, 'BioTern/') === 0) {
                $candidates[] = substr($normalized, strlen('BioTern/'));
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $candidate = ltrim((string)$candidate, '/');
            if ($candidate === '') {
                continue;
            }
            $absolute = dirname(__DIR__) . '/' . $candidate;
            if (is_file($absolute)) {
                return header_asset_versioned_href($candidate);
            }
        }

        return header_asset_versioned_href($fallback);
    }
}

$biotern_theme_api_endpoint = $base_href . 'api/theme-customizer.php';
require_once __DIR__ . '/theme-preferences.php';

$default_theme_prefs = [
    'skin' => 'light',
    'menu' => 'auto',
    'font' => 'app-font-family-montserrat',
    'navigation' => 'light',
    'header' => 'light',
    'scheme' => 'blue',
    'surfaces' => 'linked',
];

if (isset($page_theme_preferences) && is_array($page_theme_preferences)) {
    if (function_exists('biotern_theme_sanitize')) {
        $biotern_theme_preferences = biotern_theme_sanitize($page_theme_preferences);
    } else {
        $biotern_theme_preferences = $page_theme_preferences;
    }
} elseif (function_exists('biotern_theme_preferences')) {
    $biotern_theme_preferences = biotern_theme_preferences();
} else {
    $biotern_theme_preferences = $default_theme_prefs;
}

if (!is_array($biotern_theme_preferences)) {
    $biotern_theme_preferences = $default_theme_prefs;
}

$biotern_theme_preferences = array_merge($default_theme_prefs, $biotern_theme_preferences);

$html_classes = [];
$theme_skin = (($biotern_theme_preferences['skin'] ?? 'light') === 'dark') ? 'dark' : 'light';
$theme_surfaces = strtolower(trim((string)($biotern_theme_preferences['surfaces'] ?? 'linked')));
if ($theme_surfaces !== 'independent') {
    $theme_surfaces = 'linked';
}
$theme_navigation = (($biotern_theme_preferences['navigation'] ?? 'light') === 'dark') ? 'dark' : 'light';
$theme_header = (($biotern_theme_preferences['header'] ?? 'light') === 'dark') ? 'dark' : 'light';
if ($theme_surfaces === 'linked') {
    $theme_navigation = $theme_skin;
    $theme_header = $theme_skin;
    $biotern_theme_preferences['navigation'] = $theme_navigation;
    $biotern_theme_preferences['header'] = $theme_header;
}
$biotern_theme_preferences['surfaces'] = $theme_surfaces;

if ($theme_skin === 'dark') {
    $html_classes[] = 'app-skin-dark';
}
if (($biotern_theme_preferences['menu'] ?? 'auto') === 'mini') {
    $html_classes[] = 'minimenu';
}
if (($biotern_theme_preferences['font'] ?? 'default') !== 'default') {
    $html_classes[] = (string)$biotern_theme_preferences['font'];
}
if ($theme_navigation === 'dark') {
    $html_classes[] = 'app-navigation-dark';
}
if ($theme_header === 'dark') {
    $html_classes[] = 'app-header-dark';
}
$theme_scheme = strtolower(trim((string)($biotern_theme_preferences['scheme'] ?? 'blue')));
if ($theme_scheme === 'gray') {
    $html_classes[] = 'app-theme-gray';
}
$html_class_attr = implode(' ', $html_classes);
$page_body_class = isset($page_body_class) && is_string($page_body_class) ? trim($page_body_class) : '';
$header_script_name = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$header_is_management_page = stripos($header_script_name, '/management/') !== false;
$header_is_homepage = stripos($header_script_name, 'homepage.php') !== false;
$header_script_basename = strtolower(basename($header_script_name));
$header_is_settings_page = stripos($header_script_name, '/settings/') !== false
    || stripos($header_script_basename, 'settings-') === 0
    || $header_script_basename === 'theme-customizer.php';
if ($header_is_management_page) {
    $page_body_class = trim($page_body_class . ' management-page');
}
if ($header_is_settings_page) {
    $page_body_class = trim($page_body_class . ' settings-page');
}
if (!$page_is_public && stripos($page_body_class, 'mobile-bottom-nav') === false) {
    $page_body_class = trim($page_body_class . ' mobile-bottom-nav');
}

$header_user_name = 'BioTern User';
$header_user_email = 'admin@biotern.local';
$header_user_role = '';
$header_avatar = 'assets/images/avatar/1.png';
$header_notifications = [];
$header_notifications_unread = 0;

if ($page_render_header) {
    $header_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
    if ($header_user_name === '') {
        $header_user_name = 'BioTern User';
    }
    $header_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
    if ($header_user_email === '') {
        $header_user_email = 'admin@biotern.local';
    }
    $header_user_role = strtolower(trim((string)($_SESSION['role'] ?? '')));

    $session_avatar = trim((string)($_SESSION['profile_picture'] ?? ''));
    $header_avatar = header_resolve_avatar_href($session_avatar, $header_avatar);

    if ($header_user_id_session > 0) {
        $hdr_db = $header_db;
        if (!$hdr_db) {
            $hdr_db = @new mysqli(
                defined('DB_HOST') ? DB_HOST : '127.0.0.1',
                defined('DB_USER') ? DB_USER : 'root',
                defined('DB_PASS') ? DB_PASS : '',
                defined('DB_NAME') ? DB_NAME : 'biotern_db',
                defined('DB_PORT') ? (int)DB_PORT : 3306
            );
        }

        if ($hdr_db instanceof mysqli && !$hdr_db->connect_errno) {
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
                            'id' => (int)($n['id'] ?? 0),
                            'title' => (string)($n['title'] ?? 'Notification'),
                            'message' => (string)($n['message'] ?? ''),
                            'type' => (string)($n['type'] ?? ''),
                            'action_url' => (string)($n['action_url'] ?? ''),
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
                            'id' => (int)($n['id'] ?? 0),
                            'title' => $title,
                            'message' => $message,
                            'type' => (string)($n['type'] ?? ''),
                            'action_url' => '',
                            'is_read' => (int)($n['is_read'] ?? 0),
                            'created_at' => (string)($n['created_at'] ?? ''),
                        ];
                    }
                    $list_stmt->close();
                }
            }
        }

        if ($hdr_db instanceof mysqli && $hdr_db !== $header_db) {
            $hdr_db->close();
        }
    }
}

if ($header_db instanceof mysqli) {
    $header_db->close();
    $header_db = null;
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
    <link rel="icon" type="image/png" sizes="192x192" href="assets/images/logo-abbr.png?v=<?php echo rawurlencode((string)$favicon_logo_version); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/images/favicon-rounded.png?v=<?php echo rawurlencode((string)$favicon_png_version); ?>">
    <link rel="apple-touch-icon" href="assets/images/logo-abbr.png?v=<?php echo rawurlencode((string)$favicon_logo_version); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico?v=<?php echo rawurlencode((string)$favicon_ico_version); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico?v=<?php echo rawurlencode((string)$favicon_ico_version); ?>">
    <!--! END: Favicon-->
    <script src="assets/js/modules/shared/theme-state-core.js"></script>
    <!-- paceOptions are configured in assets/js/theme-preload-init.min.js -->
    <script src="assets/js/theme-preload-init.min.js"></script>
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/dataTables.bs5.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/vendors/css/datepicker.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <!--! END: Vendors CSS-->
    <!-- Theme runtime config moved to body data attributes -->
    <!--! BEGIN: Early Skin Script -->
    <!-- moved to assets/js/modules/shared/theme-state-core.js, assets/js/theme-preload-init.min.js, assets/js/global-ui-helpers.js, and assets/js/theme-preferences-runtime.js -->
    <!--! END: Early Skin Script -->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/smacss.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/modules/app-ui-select-dropdown.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <?php if ($header_is_management_page): ?>
        <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/modules/management/management-mobile.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endif; ?>
    <?php if (isset($page_styles) && is_array($page_styles)): ?>
        <?php foreach ($page_styles as $stylesheet): ?>
            <?php if (is_string($stylesheet) && trim($stylesheet) !== ''): ?>
                <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href($stylesheet), ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/page-header-consistency.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/page-header-actions-scheme.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/header-account-menu.css'), ENT_QUOTES, 'UTF-8'); ?>" />
</head>

<body<?php echo $page_body_class !== '' ? ' class="' . htmlspecialchars($page_body_class, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
    data-theme-prefs="<?php echo htmlspecialchars(json_encode($biotern_theme_preferences, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
    data-theme-api="<?php echo htmlspecialchars((string)$biotern_theme_api_endpoint, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($page_render_navigation): ?>
        <?php include_once __DIR__ . '/navigation.php'; ?>
    <?php endif; ?>
    <?php if ($page_render_header): ?>
        <!--! ================================================================ !-->
        <!--! [Start] Header !-->
        <!--! ================================================================ !-->
        <header class="nxl-header">
            <div class="header-wrapper">
                <div class="header-left d-flex align-items-center gap-4">
                    <?php if ($header_is_homepage): ?>
                        <a href="homepage.php" class="d-flex align-items-center d-lg-none" aria-label="BioTern home">
                            <img src="assets/images/logo-abbr.png" alt="BioTern" class="biotern-mobile-logo">
                        </a>
                    <?php endif; ?>
                    <a href="javascript:void(0);" class="nxl-head-mobile-toggler<?php echo $header_is_homepage ? ' d-none d-lg-inline-flex' : ''; ?>" id="mobile-collapse">
                        <div class="hamburger hamburger--arrowturn">
                            <div class="hamburger-box">
                                <div class="hamburger-inner"></div>
                            </div>
                        </div>
                    </a>
                    <div class="nxl-navigation-toggle<?php echo $header_is_homepage ? ' d-none d-lg-flex' : ''; ?>">
                        <a href="javascript:void(0);" id="menu-mini-button">
                            <i class="feather-align-left"></i>
                        </a>
                        <a href="javascript:void(0);" id="menu-expend-button">
                            <i class="feather-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="header-right ms-auto">
                    <div class="d-flex align-items-center">
                        <?php if ($header_is_homepage): ?>
                            <a href="javascript:void(0);" class="nxl-head-link me-0 d-lg-none" aria-label="More options">
                                <i class="feather-more-horizontal"></i>
                            </a>
                        <?php endif; ?>
                        <div class="<?php echo $header_is_homepage ? 'd-none d-lg-flex' : 'd-flex'; ?> align-items-center">
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
                                <a href="javascript:void(0);" class="nxl-head-link me-0 light-button">
                                    <i class="feather-sun"></i>
                                </a>
                            </div>
                            <div class="dropdown nxl-h-item click-only-dropdown">
                                <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                                    <i class="feather-bell"></i>
                                    <?php if ($header_notifications_unread > 0): ?>
                                        <span class="badge bg-danger nxl-h-badge"><?php echo (int)$header_notifications_unread; ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                                    <div class="d-flex justify-content-between align-items-center notifications-head px-3 py-2 border-bottom">
                                        <span class="fw-semibold">Notifications</span>
                                        <span class="badge bg-soft-primary text-primary"><?php echo (int)$header_notifications_unread; ?> unread</span>
                                    </div>
                                    <?php if (!empty($header_notifications)): ?>
                                        <div class="header-notifications-list">
                                        <?php foreach ($header_notifications as $n): ?>
                                            <?php
                                            $notificationTitle = (string)($n['title'] ?? 'Notification');
                                            $notificationMessage = (string)($n['message'] ?? '');
                                            $notificationCreatedAt = (string)($n['created_at'] ?? '');
                                            $notificationType = biotern_notification_normalize_type(
                                                (string)($n['type'] ?? ''),
                                                $notificationTitle,
                                                $notificationMessage,
                                                (string)($n['action_url'] ?? '')
                                            );
                                            $notificationMeta = biotern_notification_type_meta($notificationType);
                                            $notificationTarget = biotern_notification_open_url((string)($n['action_url'] ?? ''), (int)($n['id'] ?? 0), 'notifications.php');
                                            $notificationIsUnread = (int)($n['is_read'] ?? 0) === 0;
                                            ?>
                                            <a href="<?php echo htmlspecialchars($notificationTarget, ENT_QUOTES, 'UTF-8'); ?>" class="notifications-item header-notification-item<?php echo $notificationIsUnread ? ' unread' : ''; ?>">
                                                <div class="header-notification-badge">
                                                    <i class="<?php echo htmlspecialchars((string)($notificationMeta['icon'] ?? 'feather-bell'), ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                </div>
                                                <div class="notifications-desc header-notification-desc">
                                                    <div class="header-notification-meta-row">
                                                        <div class="header-notification-title"><?php echo htmlspecialchars($notificationTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="header-notification-time" title="<?php echo htmlspecialchars($notificationCreatedAt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(biotern_notification_time_ago($notificationCreatedAt), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>
                                                    <div class="header-notification-type"><?php echo htmlspecialchars((string)($notificationMeta['label'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <div class="header-notification-message"><?php echo htmlspecialchars($notificationMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="px-3 py-3 text-muted fs-12">No notifications yet.</div>
                                    <?php endif; ?>
                                    <div class="notifications-menu-footer">
                                        <a href="notifications.php">Open Notifications</a>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown nxl-h-item click-only-dropdown">
                                <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                                    <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0">
                                </a>
                                <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                                    <div class="dropdown-header">
                                        <div class="header-menu-profile">
                                            <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar">
                                            <div class="header-menu-profile-copy">
                                                <div class="header-menu-profile-name">
                                                    <h6><?php echo htmlspecialchars($header_user_name, ENT_QUOTES, 'UTF-8'); ?></h6>
                                                    <?php if ($header_user_role !== ''): ?>
                                                        <span class="header-menu-pill"><?php echo htmlspecialchars(ucfirst($header_user_role), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="fs-12 fw-medium text-muted"><?php echo htmlspecialchars($header_user_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="header-menu-status">
                                        <span class="header-menu-status-dot"></span>
                                        <span>Active</span>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div class="dropdown-meta">Profile</div>
                                    <div class="header-menu-section">
                                        <a href="account-settings.php#overview" class="dropdown-item">
                                            <i class="feather-user"></i>
                                            <span>Profile Details</span>
                                        </a>
                                        <a href="account-settings.php#profile-form" class="dropdown-item">
                                            <i class="feather-edit-3"></i>
                                            <span>Edit Profile</span>
                                        </a>
                                    </div>
                                    <div class="dropdown-meta">Account</div>
                                    <div class="header-menu-section">
                                        <a href="notifications.php" class="dropdown-item">
                                            <i class="feather-bell"></i>
                                            <span>Notifications</span>
                                        </a>
                                        <a href="account-settings.php#security" class="dropdown-item">
                                            <i class="feather-shield"></i>
                                            <span>Security</span>
                                        </a>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div class="header-menu-section">
                                        <a href="auth-login-cover.php?logout=1" class="dropdown-item">
                                            <i class="feather-log-out"></i>
                                            <span>Logout</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!--! ================================================================ !-->
        <!--! [End] Header !-->
        <!--! ================================================================ !-->
    <?php endif; ?>

