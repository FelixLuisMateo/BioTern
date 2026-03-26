<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
require_once __DIR__ . '/avatar.php';
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('header_notification_safe_target')) {
    function header_notification_safe_target(string $target, string $fallback = 'homepage.php'): string
    {
        $target = trim($target);
        if ($target === '') {
            return $fallback;
        }
        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:)?//~i', $target)) {
            return $fallback;
        }
        return $target;
    }
}

if (!function_exists('header_notification_badge_text')) {
    function header_notification_badge_text(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'NT';
        }
        $parts = preg_split('/\s+/', $value) ?: [];
        $first = $parts[0] ?? '';
        $second = $parts[1] ?? '';
        if ($second !== '') {
            $result = substr($first, 0, 1) . substr($second, 0, 1);
        } else {
            $result = substr($first, 0, 2);
        }
        return strtoupper($result !== '' ? $result : 'NT');
    }
}

// Build the login URL dynamically so it always points to BioTern_unified regardless of server path.
$_header_script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$_header_unified_pos = stripos($_header_script, '/BioTern_unified/');
$_header_login_url = ($_header_unified_pos !== false)
    ? substr($_header_script, 0, $_header_unified_pos) . '/BioTern_unified/auth/auth-login-cover.php'
    : '/BioTern_unified/auth/auth-login-cover.php';

// Enforce authenticated session for all pages using the shared app header.
$header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
$header_account_status_text = 'Active';
$header_member_since_text = 'Unknown';
$header_last_login_text = 'No login record';

// Helper function to get role badge colors
if (!function_exists('get_role_badge_color')) {
    function get_role_badge_color(string $role = ''): string {
        $role = strtolower(trim($role));
        return match($role) {
            'admin' => 'bg-soft-success text-success',
            'supervisor' => 'bg-soft-warning text-warning',
            'coordinator' => 'bg-soft-info text-info',
            'student' => 'bg-soft-secondary text-secondary',
            default => 'bg-soft-primary text-primary'
        };
    }
}

$header_conn = @new mysqli(
    defined('DB_HOST') ? DB_HOST : '127.0.0.1',
    defined('DB_USER') ? DB_USER : 'root',
    defined('DB_PASS') ? DB_PASS : '',
    defined('DB_NAME') ? DB_NAME : 'biotern_db',
    defined('DB_PORT') ? (int)DB_PORT : 3306
);

if ($header_user_id_session <= 0 && !$header_conn->connect_errno) {
    biotern_auth_restore_session_from_cookie($header_conn);
    $header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
}

if ($header_user_id_session <= 0) {
    header('Location: ' . $_header_login_url);
    exit;
}

// Refresh session identity from DB so page access stays connected to current account data.
if (!$header_conn->connect_errno) {
    $stmt = $header_conn->prepare("SELECT id, name, username, email, role, is_active, profile_picture, created_at FROM users WHERE id = ? LIMIT 1");
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

        $header_account_status_text = ((int)($header_user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive';
        $header_created_at_raw = (string)($header_user['created_at'] ?? '');
        if ($header_created_at_raw !== '') {
            $header_created_at_ts = strtotime($header_created_at_raw);
            if ($header_created_at_ts !== false) {
                $header_member_since_text = date('M d, Y', $header_created_at_ts);
            }
        }

        $header_last_login_stmt = $header_conn->prepare('SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
        if ($header_last_login_stmt) {
            $header_login_success = 'success';
            $header_last_login_stmt->bind_param('is', $header_user_id_session, $header_login_success);
            $header_last_login_stmt->execute();
            $header_last_login_row = $header_last_login_stmt->get_result()->fetch_assoc();
            $header_last_login_stmt->close();

            $header_last_login_raw = (string)($header_last_login_row['created_at'] ?? '');
            if ($header_last_login_raw !== '') {
                $header_last_login_ts = strtotime($header_last_login_raw);
                if ($header_last_login_ts !== false) {
                    $header_last_login_text = date('M d, Y h:i A', $header_last_login_ts);
                }
            }
        }
    }
}

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'BioTern';
}

// FORCE base_href to the correct web root-relative path to prevent file system paths in <base href>
$base_href = '/BioTern/BioTern_unified/';

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

$session_avatar = (string)($_SESSION['profile_picture'] ?? '');
$header_avatar = biotern_avatar_public_src($session_avatar, $header_user_id_session);

$header_notifications = [];
$header_notifications_unread = 0;
$header_notification_return_url = header_notification_safe_target((string)($_SERVER['REQUEST_URI'] ?? ''), 'homepage.php');

$header_profile_url = $base_href . 'profile-details.php';
$header_activity_url = $base_href . 'activity-feed.php';
$header_notifications_url = $base_href . 'notifications.php';
$header_account_settings_url = $base_href . 'profile-details.php#account-settings';

if ($header_user_id_session > 0) {
    $headerAutoReadNotificationId = (int)($_GET['notif_read'] ?? 0);

    if ($headerAutoReadNotificationId > 0 && !$header_conn->connect_errno) {
        biotern_notifications_mark_read($header_conn, $header_user_id_session, $headerAutoReadNotificationId);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['header_notification_action'])) {
        $notificationAction = trim((string)($_POST['header_notification_action'] ?? ''));
        $notificationId = (int)($_POST['header_notification_id'] ?? 0);
        $notificationNext = header_notification_safe_target((string)($_POST['header_notification_next'] ?? ''), $header_notification_return_url);

        if (!$header_conn->connect_errno) {
            if ($notificationAction === 'mark_read' && $notificationId > 0) {
                biotern_notifications_mark_read($header_conn, $header_user_id_session, $notificationId);
            } elseif ($notificationAction === 'mark_all_read') {
                biotern_notifications_mark_all_read($header_conn, $header_user_id_session);
            } elseif ($notificationAction === 'clear_one' && $notificationId > 0) {
                biotern_notifications_clear($header_conn, $header_user_id_session, $notificationId);
            } elseif ($notificationAction === 'clear_all') {
                biotern_notifications_clear_all($header_conn, $header_user_id_session);
            }
        }

        header('Location: ' . $notificationNext);
        exit;
    }

    if (!$header_conn->connect_errno) {
        $header_notifications_unread = biotern_notifications_count_unread($header_conn, $header_user_id_session);
        $header_notifications = biotern_notifications_fetch($header_conn, $header_user_id_session, 8);
    }
}

if ($header_conn instanceof mysqli && !$header_conn->connect_errno) {
    $header_conn->close();
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
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/datepicker.min.css">
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
    <link rel="stylesheet" type="text/css" href="assets/css/datepicker-global.css" />
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
                            <?php if ($header_notifications_unread > 0): ?>
                                <span class="badge bg-danger nxl-h-badge"><?php echo (int)$header_notifications_unread; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="notifications-head header-notifications-head">
                                <div class="header-notifications-summary">
                                    <span class="header-notifications-kicker">Activity Center</span>
                                    <div class="header-notifications-title-row">
                                        <span class="fw-semibold">Notifications</span>
                                        <span class="header-notifications-count"><?php echo (int)$header_notifications_unread; ?> unread</span>
                                    </div>
                                    <div class="header-notifications-subtitle">Operational alerts, chat activity, and account updates.</div>
                                </div>
                                <?php if (!empty($header_notifications)): ?>
                                    <div class="header-notifications-toolbar">
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="header_notification_action" value="mark_all_read">
                                            <input type="hidden" name="header_notification_next" value="<?php echo htmlspecialchars($header_notification_return_url, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="header-notification-toolbar-btn">Mark all read</button>
                                        </form>
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="header_notification_action" value="clear_all">
                                            <input type="hidden" name="header_notification_next" value="<?php echo htmlspecialchars($header_notification_return_url, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="header-notification-toolbar-btn danger">Clear all</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
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
                                    $notificationActionUrl = header_notification_safe_target((string)($n['action_url'] ?? ''), $header_notification_return_url);
                                    $notificationActionUrl = biotern_notification_open_url($notificationActionUrl, (int)($n['id'] ?? 0), $header_notification_return_url);
                                    $notificationIsUnread = (int)($n['is_read'] ?? 0) === 0;
                                    $notificationOpenLabel = trim((string)($n['action_url'] ?? '')) !== '' ? 'Open' : 'Mark read';
                                    ?>
                                    <div class="notifications-item header-notification-item<?php echo $notificationIsUnread ? ' unread' : ''; ?>">
                                        <div class="header-notification-badge"><i class="<?php echo htmlspecialchars((string)($notificationMeta['icon'] ?? 'feather-bell'), ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                                        <div class="notifications-desc header-notification-desc">
                                            <div class="header-notification-meta-row">
                                                <div class="header-notification-title"><?php echo htmlspecialchars($notificationTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="header-notification-time" title="<?php echo htmlspecialchars($notificationCreatedAt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(biotern_notification_time_ago($notificationCreatedAt), ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <div class="header-notification-message text-muted"><?php echo htmlspecialchars((string)($notificationMeta['label'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="header-notification-message"><?php echo htmlspecialchars($notificationMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="header-notification-actions">
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="header_notification_action" value="mark_read">
                                                    <input type="hidden" name="header_notification_id" value="<?php echo (int)($n['id'] ?? 0); ?>">
                                                    <input type="hidden" name="header_notification_next" value="<?php echo htmlspecialchars($notificationActionUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="header-notification-action-link primary"><?php echo htmlspecialchars($notificationOpenLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                                                </form>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="header_notification_action" value="clear_one">
                                                    <input type="hidden" name="header_notification_id" value="<?php echo (int)($n['id'] ?? 0); ?>">
                                                    <input type="hidden" name="header_notification_next" value="<?php echo htmlspecialchars($header_notification_return_url, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="header-notification-action-link">Dismiss</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="header-notifications-empty">
                                    <div class="header-notifications-empty-title">No notifications</div>
                                    <div class="header-notifications-empty-copy">New activity will appear here when something requires your attention.</div>
                                </div>
                            <?php endif; ?>
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
                                                <span class="badge <?php echo get_role_badge_color($header_user_role); ?> ms-1"><?php
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
                            <div class="dropdown-item-text pb-1">
                                <div class="fs-11 fw-semibold text-muted text-uppercase">Status</div>
                            </div>
                            <div class="dropdown-item-text pt-1 pb-1">
                                <span class="hstack justify-content-between gap-2">
                                    <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="wd-10 ht-10 border border-2 border-gray-1 <?php echo $header_account_status_text === 'Active' ? 'bg-success' : 'bg-secondary'; ?> rounded-circle me-2"></i>Account</span>
                                    <span class="badge <?php echo $header_account_status_text === 'Active' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'; ?>"><?php echo htmlspecialchars($header_account_status_text, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>
                            <div class="dropdown-item-text pt-1 pb-1">
                                <span class="hstack justify-content-between gap-2">
                                    <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-shield me-2"></i>Role</span>
                                    <span class="badge <?php echo get_role_badge_color($header_user_role); ?>"><?php echo htmlspecialchars($header_user_role !== '' ? ucfirst($header_user_role) : 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>
                            <div class="dropdown-item-text pt-1 pb-1">
                                <span class="hstack justify-content-between gap-2">
                                    <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-calendar me-2"></i>Member Since</span>
                                    <span class="fs-12 text-light"><?php echo htmlspecialchars($header_member_since_text, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>
                            <div class="dropdown-item-text pt-1 pb-1">
                                <span class="hstack justify-content-between gap-2">
                                    <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-clock me-2"></i>Last Login</span>
                                    <span class="fs-12 text-light text-end"><?php echo htmlspecialchars($header_last_login_text, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>
                            <div class="dropdown-item-text pt-1 pb-2">
                                <span class="hstack justify-content-between gap-2">
                                    <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-bell me-2"></i>Notifications</span>
                                    <span class="badge <?php echo (int)$header_notifications_unread > 0 ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-secondary'; ?>"><?php echo (int)$header_notifications_unread > 0 ? ((int)$header_notifications_unread . ' unread') : 'All read'; ?></span>
                                </span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo htmlspecialchars($header_profile_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <a href="<?php echo htmlspecialchars($header_activity_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
                                <i class="feather-activity"></i>
                                <span>Activity Feed</span>
                            </a>
                            <a href="<?php echo htmlspecialchars($header_notifications_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
                                <i class="feather-bell"></i>
                                <span>Notifications</span>
                            </a>
                            <a href="<?php echo htmlspecialchars($header_account_settings_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
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

