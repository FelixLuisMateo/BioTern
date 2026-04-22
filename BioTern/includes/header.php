<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/auth-session.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
require_once __DIR__ . '/avatar.php';
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
biotern_boot_session(isset($conn) ? $conn : null);

$header_script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$header_root = '/';
$header_script_dir = str_replace('\\', '/', dirname($header_script_name));
if ($header_script_dir === '\\' || $header_script_dir === '.') {
    $header_script_dir = '/';
}
$header_script_dir = rtrim($header_script_dir, '/');
if ($header_script_dir === '') {
    $header_script_dir = '/';
}

// If the executing script is inside a known app subfolder, strip that segment to reach app root.
$header_app_subdirs = [
    'api', 'apps', 'auth', 'documents', 'includes', 'management', 'pages', 'public', 'reports', 'settings', 'tools',
];
$header_dir_basename = strtolower(trim((string)basename($header_script_dir), '/'));
if (in_array($header_dir_basename, $header_app_subdirs, true)) {
    $header_script_dir = str_replace('\\', '/', dirname($header_script_dir));
}

$header_script_dir = rtrim((string)$header_script_dir, '/');
if ($header_script_dir === '' || $header_script_dir === '.') {
    $header_root = '/';
} else {
    $header_root = $header_script_dir . '/';
}
$header_login_url = $header_root . 'auth/auth-login.php';

$page_is_public = isset($page_is_public) && $page_is_public === true;
$page_render_navigation = isset($page_render_navigation) ? (bool)$page_render_navigation : !$page_is_public;
$page_render_header = isset($page_render_header) ? (bool)$page_render_header : !$page_is_public;

// Enforce authenticated session for all non-public pages using the shared app header.
$header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
$header_db = null;
$header_account_status_text = 'Active';
$header_member_since_text = 'Unknown';
$header_last_login_text = 'No login record';
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
        $stmt = $header_db->prepare("SELECT id, name, username, email, role, is_active, profile_picture, created_at FROM users WHERE id = ? LIMIT 1");
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

            // If a DB-backed avatar exists, force the shared session marker so all pages render it consistently.
            $header_has_db_avatar = false;
            $header_avatar_stmt = $header_db->prepare('SELECT 1 FROM user_profile_pictures WHERE user_id = ? LIMIT 1');
            if ($header_avatar_stmt) {
                $header_avatar_stmt->bind_param('i', $header_user_id_session);
                $header_avatar_stmt->execute();
                $header_has_db_avatar = (bool)$header_avatar_stmt->get_result()->fetch_row();
                $header_avatar_stmt->close();
            }
            if ($header_has_db_avatar) {
                $_SESSION['profile_picture'] = 'db-avatar';
            }

            $header_account_status_text = ((int)($header_user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive';
            $header_created_at_raw = (string)($header_user['created_at'] ?? '');
            if ($header_created_at_raw !== '') {
                $header_created_at_ts = strtotime($header_created_at_raw);
                if ($header_created_at_ts !== false) {
                    $header_member_since_text = date('M d, Y', $header_created_at_ts);
                }
            }

            $header_last_login_stmt = $header_db->prepare('SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
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
    } else {
        $header_db = null;
    }
}

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'BioTern';
} elseif (!preg_match('/^\s*BioTern\b/i', $page_title)) {
    $page_title = 'BioTern || ' . ltrim(trim($page_title), "|-: ");
}

// Allow notification links from any page to mark a notification as read,
// then redirect back to the same page without notif_read in the query string.
if (!$page_is_public && $header_user_id_session > 0 && isset($_GET['notif_read'])) {
    $notifReadId = (int)$_GET['notif_read'];
    if ($notifReadId > 0 && isset($conn) && $conn instanceof mysqli) {
        biotern_notifications_mark_read($conn, $header_user_id_session, $notifReadId);
    }

    $redirectParams = $_GET;
    unset($redirectParams['notif_read']);
    $redirectTarget = basename((string)($_SERVER['PHP_SELF'] ?? 'homepage.php'));
    $redirectQuery = http_build_query($redirectParams);
    if ($redirectQuery !== '') {
        $redirectTarget .= '?' . $redirectQuery;
    }
    header('Location: ' . $redirectTarget);
    exit;
}

// Allow notification dropdown actions to remove a read notification,
// then redirect back to the same page without notif_remove in the query string.
if (!$page_is_public && $header_user_id_session > 0 && isset($_GET['notif_remove'])) {
    $notifRemoveId = (int)$_GET['notif_remove'];
    if ($notifRemoveId > 0 && isset($conn) && $conn instanceof mysqli) {
        $canRemoveReadNotification = false;
        $headerNotifColumns = biotern_notification_columns($conn);
        $checkReadSql = 'SELECT is_read FROM notifications WHERE id = ? AND user_id = ?';
        if (isset($headerNotifColumns['deleted_at'])) {
            $checkReadSql .= ' AND deleted_at IS NULL';
        }
        $checkReadSql .= ' LIMIT 1';

        $checkReadStmt = $conn->prepare($checkReadSql);
        if ($checkReadStmt) {
            $checkReadStmt->bind_param('ii', $notifRemoveId, $header_user_id_session);
            $checkReadStmt->execute();
            $checkReadRow = $checkReadStmt->get_result()->fetch_assoc();
            $checkReadStmt->close();
            $canRemoveReadNotification = (int)($checkReadRow['is_read'] ?? 0) === 1;
        }

        if ($canRemoveReadNotification) {
            biotern_notifications_clear($conn, $header_user_id_session, $notifRemoveId);
        }
    }

    $redirectParams = $_GET;
    unset($redirectParams['notif_remove']);
    $redirectTarget = basename((string)($_SERVER['PHP_SELF'] ?? 'homepage.php'));
    $redirectQuery = http_build_query($redirectParams);
    if ($redirectQuery !== '') {
        $redirectTarget .= '?' . $redirectQuery;
    }
    header('Location: ' . $redirectTarget);
    exit;
}
if (!isset($base_href) || trim((string)$base_href) === '') {
    $base_href = $header_root;
}
$header_notification_actions_url = (string)$base_href . 'api/notifications-actions.php';
$header_notification_feed_url = (string)$base_href . 'api/notifications-feed.php';

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

if (!function_exists('header_role_badge_color')) {
    function header_role_badge_color(string $role = ''): string
    {
        $role = strtolower(trim($role));
        return match ($role) {
            'admin' => 'bg-soft-success text-success',
            'supervisor' => 'bg-soft-warning text-warning',
            'coordinator' => 'bg-soft-info text-info',
            'student' => 'bg-soft-secondary text-secondary',
            default => 'bg-soft-primary text-primary',
        };
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
$theme_scheme = (string)($biotern_theme_preferences['scheme'] ?? 'blue');
if (function_exists('biotern_theme_normalize_scheme')) {
    $theme_scheme = biotern_theme_normalize_scheme($theme_scheme);
} else {
    $theme_scheme = strtolower(trim($theme_scheme));
    $theme_scheme = preg_replace('/[^a-z0-9-]+/', '-', $theme_scheme);
    $theme_scheme = trim((string)$theme_scheme, '-');
    if ($theme_scheme === '') {
        $theme_scheme = 'blue';
    }
}
$biotern_theme_preferences['scheme'] = $theme_scheme;
$html_classes[] = 'app-theme-' . $theme_scheme;
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
$header_notifications_read = 0;
$header_profile_url = 'profile-details.php';
$header_notifications_url = 'notifications.php';
$header_account_settings_url = 'account-settings.php#security';
$header_avatar_debug_enabled = isset($_GET['avatar_debug']) && (string)$_GET['avatar_debug'] === '1';

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
    $header_avatar_path = biotern_avatar_public_src($session_avatar, $header_user_id_session);
    $header_avatar_base = header_asset_versioned_href($header_avatar_path);
    $header_avatar_sep = (strpos($header_avatar_base, '?') !== false) ? '&' : '?';
    $header_avatar = $header_avatar_base . $header_avatar_sep . 'u=' . rawurlencode((string)$header_user_id_session);

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
            $has_action_url = false;
            $has_deleted_at = false;
            $col_res = $hdr_db->query("SHOW COLUMNS FROM notifications");
            if ($col_res instanceof mysqli_result) {
                while ($col = $col_res->fetch_assoc()) {
                    $field = strtolower((string)($col['Field'] ?? ''));
                    if ($field === 'title') $has_title = true;
                    if ($field === 'message') $has_message = true;
                    if ($field === 'type') $has_type = true;
                    if ($field === 'data') $has_data = true;
                    if ($field === 'action_url') $has_action_url = true;
                    if ($field === 'deleted_at') $has_deleted_at = true;
                }
            }

            $count_sql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)";
            if ($has_deleted_at) {
                $count_sql .= " AND deleted_at IS NULL";
            }
            $count_stmt = $hdr_db->prepare($count_sql);
            if ($count_stmt) {
                $count_stmt->bind_param('i', $header_user_id_session);
                $count_stmt->execute();
                $row = $count_stmt->get_result()->fetch_assoc();
                $count_stmt->close();
                $header_notifications_unread = (int)($row['unread_count'] ?? 0);
            }

            if ($has_title && $has_message) {
                $list_sql = "SELECT id, title, message, is_read, created_at";
                if ($has_type) {
                    $list_sql .= ", type";
                }
                if ($has_action_url) {
                    $list_sql .= ", action_url";
                }
                $list_sql .= " FROM notifications WHERE user_id = ?";
                if ($has_deleted_at) {
                    $list_sql .= " AND deleted_at IS NULL";
                }
                $list_sql .= " ORDER BY created_at DESC, id DESC LIMIT 6";

                $list_stmt = $hdr_db->prepare($list_sql);
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
                $list_sql = "SELECT id, type, data, is_read, created_at FROM notifications WHERE user_id = ?";
                if ($has_deleted_at) {
                    $list_sql .= " AND deleted_at IS NULL";
                }
                $list_sql .= " ORDER BY created_at DESC, id DESC LIMIT 6";

                $list_stmt = $hdr_db->prepare($list_sql);
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

            foreach ($header_notifications as $header_notification_row) {
                if ((int)($header_notification_row['is_read'] ?? 0) === 1) {
                    $header_notifications_read++;
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
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/mobile-page-header.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/mobile-dashboard.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/page-header-actions-scheme.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/header-account-menu.css'), ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(header_asset_versioned_href('assets/css/state/notification-skin.css'), ENT_QUOTES, 'UTF-8'); ?>" />
</head>

<body<?php echo $page_body_class !== '' ? ' class="' . htmlspecialchars($page_body_class, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
    data-theme-user-id="<?php echo (int)$header_user_id_session; ?>"
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
                            <div class="nxl-h-item nxl-header-search-inline d-none d-sm-flex">
                                <div class="header-search-inline-shell">
                                    <span class="header-search-inline-icon" aria-hidden="true">
                                        <i class="feather-search"></i>
                                    </span>
                                    <input
                                        type="text"
                                        id="headerSearchInput"
                                        name="header_search"
                                        class="header-search-inline-input"
                                        placeholder="Search pages..."
                                        autocomplete="off"
                                        spellcheck="false"
                                        aria-label="Search pages">
                                    <button type="button" class="header-search-inline-clear" id="headerSearchClear" aria-label="Clear search">
                                        <i class="feather-x"></i>
                                    </button>
                                </div>
                                <div class="dropdown-menu nxl-h-dropdown nxl-search-dropdown header-search-inline-results"></div>
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
                                <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu"
                                    data-notification-actions-url="<?php echo htmlspecialchars($header_notification_actions_url, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notification-feed-url="<?php echo htmlspecialchars($header_notification_feed_url, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notifications-url="<?php echo htmlspecialchars($header_notifications_url, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-current-user-id="<?php echo (int)$header_user_id_session; ?>"
                                    data-notification-browser-icon="<?php echo htmlspecialchars(header_asset_versioned_href('assets/images/logo-abbr.png'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notification-browser-badge="<?php echo htmlspecialchars(header_asset_versioned_href('assets/images/favicon-rounded.png'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notification-service-worker-url="<?php echo htmlspecialchars((string)$base_href . 'service-worker.js', ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="d-flex justify-content-between align-items-center notifications-head px-3 py-2 border-bottom">
                                        <span class="fw-semibold">Notifications</span>
                                        <div class="header-notifications-head-tools">
                                            <button type="button" class="header-notification-browser-link" data-notification-browser-enable title="Enable browser alerts" aria-label="Enable browser alerts">
                                                <i class="feather-monitor"></i>
                                                <span>Enable Alerts</span>
                                            </button>
                                            <button type="button" class="header-notification-remove-all-link<?php echo $header_notifications_read > 0 ? '' : ' is-disabled'; ?>" data-notification-remove-all aria-label="Remove all read notifications" title="Remove all read notifications"<?php echo $header_notifications_read > 0 ? '' : ' disabled aria-disabled="true"'; ?>>
                                                <i class="feather-trash-2"></i>
                                                <span>Remove All</span>
                                            </button>
                                            <a href="<?php echo htmlspecialchars($header_notifications_url, ENT_QUOTES, 'UTF-8'); ?>" class="header-notification-settings-link" title="Notification settings" aria-label="Notification settings">
                                                <i class="feather-settings"></i>
                                            </a>
                                            <span class="badge bg-soft-primary text-primary header-notifications-unread-pill"><?php echo (int)$header_notifications_unread; ?> unread</span>
                                        </div>
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
                                            $notificationTarget = biotern_notification_open_url(
                                                (string)($n['action_url'] ?? ''),
                                                (int)($n['id'] ?? 0),
                                                'notifications.php',
                                                $notificationTitle,
                                                $notificationMessage,
                                                (string)($n['type'] ?? '')
                                            );
                                            $notificationIsUnread = (int)($n['is_read'] ?? 0) === 0;
                                            $notificationRemoveTarget = basename((string)($_SERVER['PHP_SELF'] ?? 'homepage.php'));
                                            $notificationRemoveParams = $_GET;
                                            $notificationRemoveParams['notif_remove'] = (int)($n['id'] ?? 0);
                                            unset($notificationRemoveParams['notif_read']);
                                            $notificationRemoveQuery = http_build_query($notificationRemoveParams);
                                            if ($notificationRemoveQuery !== '') {
                                                $notificationRemoveTarget .= '?' . $notificationRemoveQuery;
                                            }
                                            ?>
                                            <div class="header-notification-row<?php echo $notificationIsUnread ? ' unread' : ' read'; ?>" data-notification-id="<?php echo (int)($n['id'] ?? 0); ?>" data-notification-read="<?php echo $notificationIsUnread ? '0' : '1'; ?>">
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
                                                <?php if (!$notificationIsUnread): ?>
                                                    <a href="<?php echo htmlspecialchars($notificationRemoveTarget, ENT_QUOTES, 'UTF-8'); ?>" class="header-notification-remove-link" data-notification-remove-one title="Remove notification" aria-label="Remove notification">
                                                        <i class="feather-trash-2"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="px-3 py-3 text-muted fs-12 header-notifications-empty<?php echo !empty($header_notifications) ? ' d-none' : ''; ?>">No notifications yet.</div>
                                    <div class="notifications-menu-footer">
                                        <a href="notifications.php">Open Notifications</a>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown nxl-h-item click-only-dropdown">
                                <a href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-display="static" data-bs-offset="0,10" role="button" data-bs-auto-close="outside">
                                    <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0" data-avatar-debug-src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>">
                                </a>
                                <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                                    <div class="dropdown-header user-dropdown-hero">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar" data-avatar-debug-src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="user-dropdown-identity">
                                                <h6 class="user-dropdown-name"><?php echo htmlspecialchars($header_user_name, ENT_QUOTES, 'UTF-8'); ?></h6>
                                                <div class="user-dropdown-meta-row">
                                                    <span class="user-dropdown-role-inline"><?php echo htmlspecialchars($header_user_role !== '' ? ucfirst($header_user_role) : 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                                <span class="user-dropdown-email"><?php echo htmlspecialchars($header_user_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div class="dropdown-item-text pb-1">
                                        <div class="user-dropdown-section-title">Status</div>
                                    </div>
                                    <div class="user-dropdown-status-grid">
                                        <div class="dropdown-item-text user-dropdown-status-item">
                                            <span class="user-dropdown-status-row">
                                                <span class="user-dropdown-label">
                                                    <span class="user-dropdown-icon-box">
                                                        <span class="user-dropdown-status-dot <?php echo $header_account_status_text === 'Active' ? 'is-active' : 'is-inactive'; ?>"></span>
                                                    </span>
                                                    <span>Account</span>
                                                </span>
                                                <span class="badge <?php echo $header_account_status_text === 'Active' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'; ?>"><?php echo htmlspecialchars($header_account_status_text, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </div>
                                        <div class="dropdown-item-text user-dropdown-status-item">
                                            <span class="user-dropdown-status-row">
                                                <span class="user-dropdown-label">
                                                    <span class="user-dropdown-icon-box"><i class="feather-shield"></i></span>
                                                    <span>Role</span>
                                                </span>
                                                <span class="badge <?php echo header_role_badge_color($header_user_role); ?>"><?php echo htmlspecialchars($header_user_role !== '' ? ucfirst($header_user_role) : 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </div>
                                        <div class="dropdown-item-text user-dropdown-status-item">
                                            <span class="user-dropdown-status-row">
                                                <span class="user-dropdown-label">
                                                    <span class="user-dropdown-icon-box"><i class="feather-calendar"></i></span>
                                                    <span>Member Since</span>
                                                </span>
                                                <span class="user-dropdown-value"><?php echo htmlspecialchars($header_member_since_text, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </div>
                                        <div class="dropdown-item-text user-dropdown-status-item">
                                            <span class="user-dropdown-status-row">
                                                <span class="user-dropdown-label">
                                                    <span class="user-dropdown-icon-box"><i class="feather-clock"></i></span>
                                                    <span>Last Login</span>
                                                </span>
                                                <span class="user-dropdown-value text-end"><?php echo htmlspecialchars($header_last_login_text, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </div>
                                        <div class="dropdown-item-text user-dropdown-status-item">
                                            <span class="user-dropdown-status-row">
                                                <span class="user-dropdown-label">
                                                    <span class="user-dropdown-icon-box"><i class="feather-bell"></i></span>
                                                    <span>Notifications</span>
                                                </span>
                                                <span class="badge header-profile-notifications-pill <?php echo (int)$header_notifications_unread > 0 ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-secondary'; ?>"><?php echo (int)$header_notifications_unread > 0 ? ((int)$header_notifications_unread . ' unread') : 'All read'; ?></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div class="dropdown-item-text pb-1">
                                        <div class="user-dropdown-section-title">Workspace</div>
                                    </div>
                                    <div class="user-dropdown-links">
                                        <a href="<?php echo htmlspecialchars($header_profile_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
                                            <i class="feather-user"></i>
                                            <span>Profile Details</span>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($header_notifications_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
                                            <i class="feather-bell"></i>
                                            <span>Notifications</span>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($header_account_settings_url, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
                                            <i class="feather-settings"></i>
                                            <span>Account Settings</span>
                                        </a>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <a href="auth-login.php?logout=1" class="dropdown-item user-dropdown-logout">
                                        <i class="feather-log-out"></i>
                                        <span>Logout</span>
                                    </a>
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

        <script>
            (function () {
                function initHeaderNotificationActions() {
                    var menu = document.querySelector('.nxl-notifications-menu[data-notification-actions-url]');
                    if (!menu) {
                        return;
                    }

                    var endpoint = (menu.getAttribute('data-notification-actions-url') || '').trim();
                    if (!endpoint) {
                        return;
                    }

                    var dropdown = menu.closest('.dropdown');
                    var bellLink = dropdown ? dropdown.querySelector('.nxl-head-link') : null;
                    var headBadge = menu.querySelector('.notifications-head .badge');
                    var list = menu.querySelector('.header-notifications-list');
                    var emptyState = menu.querySelector('.header-notifications-empty');
                    var removeAllButton = menu.querySelector('[data-notification-remove-all]');
                    var pending = false;

                    function parseCount(value) {
                        var parsed = parseInt(String(value || ''), 10);
                        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
                    }

                    function setPending(state) {
                        pending = !!state;
                        if (removeAllButton) {
                            var isDisabled = removeAllButton.classList.contains('is-disabled');
                            removeAllButton.disabled = pending || isDisabled;
                        }
                    }

                    function updateUnreadBadges(unreadCount) {
                        var count = parseCount(unreadCount);
                        if (headBadge) {
                            headBadge.textContent = count + ' unread';
                        }

                        if (!bellLink) {
                            return;
                        }

                        var bellBadge = bellLink.querySelector('.nxl-h-badge');
                        if (count > 0) {
                            if (!bellBadge) {
                                bellBadge = document.createElement('span');
                                bellBadge.className = 'badge bg-danger nxl-h-badge';
                                bellLink.appendChild(bellBadge);
                            }
                            bellBadge.textContent = String(count);
                        } else if (bellBadge && bellBadge.parentNode) {
                            bellBadge.parentNode.removeChild(bellBadge);
                        }
                    }

                    function updateRemoveAllState() {
                        if (!removeAllButton) {
                            return;
                        }

                        var hasReadRows = !!menu.querySelector('.header-notification-row.read');
                        removeAllButton.classList.toggle('is-disabled', !hasReadRows);
                        removeAllButton.disabled = pending || !hasReadRows;
                        removeAllButton.setAttribute('aria-disabled', hasReadRows ? 'false' : 'true');
                    }

                    function updateEmptyState() {
                        var hasRows = !!menu.querySelector('.header-notification-row');
                        if (list) {
                            list.classList.toggle('d-none', !hasRows);
                        }
                        if (emptyState) {
                            emptyState.classList.toggle('d-none', hasRows);
                        }
                        updateRemoveAllState();
                    }

                    function postAction(payload) {
                        var params = new URLSearchParams();
                        Object.keys(payload || {}).forEach(function (key) {
                            params.append(key, String(payload[key]));
                        });

                        return fetch(endpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: params.toString()
                        }).then(function (response) {
                            return response.json().catch(function () {
                                return { ok: false, message: 'Invalid server response.' };
                            });
                        });
                    }

                    menu.addEventListener('click', function (event) {
                        var removeOne = event.target.closest('[data-notification-remove-one]');
                        if (removeOne) {
                            event.preventDefault();
                            if (pending) {
                                return;
                            }

                            var row = removeOne.closest('.header-notification-row');
                            var notificationId = row ? parseCount(row.getAttribute('data-notification-id')) : 0;
                            if (notificationId <= 0) {
                                return;
                            }

                            setPending(true);
                            postAction({ action: 'remove_one_read', notification_id: notificationId })
                                .then(function (result) {
                                    if (!result || result.ok !== true) {
                                        return;
                                    }

                                    if (row && row.parentNode) {
                                        row.parentNode.removeChild(row);
                                    }

                                    updateUnreadBadges(result.unread_count);
                                    updateEmptyState();
                                })
                                .finally(function () {
                                    setPending(false);
                                    updateRemoveAllState();
                                });
                            return;
                        }

                        var removeAll = event.target.closest('[data-notification-remove-all]');
                        if (removeAll) {
                            event.preventDefault();
                            if (pending || removeAll.classList.contains('is-disabled')) {
                                return;
                            }

                            setPending(true);
                            postAction({ action: 'remove_all_read' })
                                .then(function (result) {
                                    if (!result || result.ok !== true) {
                                        return;
                                    }

                                    menu.querySelectorAll('.header-notification-row.read').forEach(function (row) {
                                        if (row && row.parentNode) {
                                            row.parentNode.removeChild(row);
                                        }
                                    });

                                    updateUnreadBadges(result.unread_count);
                                    updateEmptyState();
                                })
                                .finally(function () {
                                    setPending(false);
                                    updateRemoveAllState();
                                });
                        }
                    });

                    updateUnreadBadges(<?php echo (int)$header_notifications_unread; ?>);
                    updateEmptyState();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initHeaderNotificationActions);
                } else {
                    initHeaderNotificationActions();
                }
            })();
        </script>

        <?php if ($header_avatar_debug_enabled): ?>
            <div id="avatar-debug-panel" style="position:fixed;right:12px;bottom:12px;z-index:2000;max-width:460px;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:10px;padding:10px 12px;box-shadow:0 8px 24px rgba(2,6,23,.35);font:12px/1.4 Consolas,'Courier New',monospace;">
                <div style="font-weight:700;margin-bottom:6px;">Avatar Debug Mode</div>
                <div style="opacity:.9;word-break:break-all;">Page: <?php echo htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div id="avatar-debug-lines" style="margin-top:6px;display:grid;gap:4px;"></div>
            </div>
            <script>
                (function () {
                    var out = document.getElementById('avatar-debug-lines');
                    if (!out) return;
                    var srcSet = new Set();
                    document.querySelectorAll('img[data-avatar-debug-src]').forEach(function (img) {
                        var src = img.getAttribute('data-avatar-debug-src') || img.getAttribute('src') || '';
                        if (src) srcSet.add(src);
                    });
                    var sources = Array.from(srcSet);
                    if (!sources.length) {
                        out.innerHTML = '<div>No avatar sources found.</div>';
                        return;
                    }
                    sources.forEach(function (src) {
                        var line = document.createElement('div');
                        line.style.wordBreak = 'break-all';
                        line.textContent = '[checking] ' + src;
                        out.appendChild(line);
                        fetch(src, { method: 'GET', credentials: 'same-origin' })
                            .then(function (res) {
                                var ctype = res.headers && res.headers.get ? (res.headers.get('content-type') || '') : '';
                                line.textContent = '[' + res.status + ' ' + res.statusText + (ctype ? ' | ' + ctype : '') + '] ' + src;
                            })
                            .catch(function (err) {
                                line.textContent = '[fetch-error] ' + src + ' :: ' + (err && err.message ? err.message : 'unknown');
                            });
                    });
                })();
            </script>
        <?php endif; ?>
    <?php endif; ?>

