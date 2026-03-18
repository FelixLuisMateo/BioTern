<?php
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_is_public = isset($page_is_public) && $page_is_public === true;
$page_render_navigation = isset($page_render_navigation) ? (bool)$page_render_navigation : !$page_is_public;
$page_render_header = isset($page_render_header) ? (bool)$page_render_header : !$page_is_public;
$page_render_container = isset($page_render_container) ? (bool)$page_render_container : !$page_is_public;
$page_render_footer = isset($page_render_footer) ? (bool)$page_render_footer : !$page_is_public;

// Enforce authenticated session for all non-public pages using the shared app header.
$header_user_id_session = (int)($_SESSION['user_id'] ?? 0);
$header_db = null;
if (!$page_is_public) {
    if ($header_user_id_session <= 0) {
        header('Location: /BioTern/BioTern/auth/auth-login-cover.php');
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
                header('Location: /BioTern/BioTern/auth/auth-login-cover.php');
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

$biotern_theme_api_endpoint = $base_href . 'api/theme-customizer.php';
require_once __DIR__ . '/theme-preferences.php';

$default_theme_prefs = [
    'skin' => 'light',
    'menu' => 'auto',
    'font' => 'default',
    'navigation' => 'light',
    'header' => 'light',
    'scheme' => 'blue',
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
$theme_scheme = strtolower(trim((string)($biotern_theme_preferences['scheme'] ?? 'blue')));
if ($theme_scheme === 'gray') {
    $html_classes[] = 'app-theme-gray';
}
$html_class_attr = implode(' ', $html_classes);
$page_body_class = isset($page_body_class) && is_string($page_body_class) ? trim($page_body_class) : '';

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
    if ($session_avatar !== '') {
        $normalized_avatar = ltrim(str_replace('\\', '/', $session_avatar), '/');
        $avatar_fs_path = dirname(__DIR__) . '/' . $normalized_avatar;
        if (is_file($avatar_fs_path)) {
            $header_avatar = $normalized_avatar;
        }
    }

    if ($header_user_id_session > 0) {
        $hdr_db = $header_db;
        if (!$hdr_db) {
            $hdr_db = @new mysqli('127.0.0.1', 'root', '', 'biotern_db');
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
    <!--! END: Vendors CSS-->
    <!-- Theme runtime config moved to body data attributes -->
    <!--! BEGIN: Early Skin Script -->
    <!-- moved to assets/js/theme-preload-init.min.js, assets/js/global-ui-helpers.js, and assets/js/theme-preferences-runtime.js -->
    <!--! END: Early Skin Script -->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/css/core.css" />
    <link rel="stylesheet" type="text/css" href="assets/css/ui.css" />
    <link rel="stylesheet" type="text/css" href="assets/css/theme.css" />
    <?php if (isset($page_styles) && is_array($page_styles)): ?>
        <?php foreach ($page_styles as $stylesheet): ?>
            <?php if (is_string($stylesheet) && trim($stylesheet) !== ''): ?>
                <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
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
                        <a href="javascript:void(0);" id="menu-expend-button">
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
                                    <?php foreach ($header_notifications as $n): ?>
                                        <div class="notifications-item">
                                            <img src="assets/images/avatar/1.png" alt="" class="rounded me-3 border">
                                            <div class="notifications-desc">
                                                <a href="javascript:void(0);" class="font-body text-truncate-2-line"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                <div class="fs-12 text-muted text-truncate-2-line"><?php echo htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="notifications-date text-muted border-bottom border-bottom-dashed"><?php echo htmlspecialchars($n['created_at'] !== '' ? date('M d, Y h:i A', strtotime($n['created_at'])) : 'Just now', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="px-3 py-3 text-muted fs-12">No notifications yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="dropdown nxl-h-item click-only-dropdown">
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
    <?php endif; ?>
    <?php if ($page_render_container): ?>
    <?php endif; ?>

