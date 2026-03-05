<?php
// Shared header include.  Sets up HTML <head> and page header/navigation.
// Pages can set a $page_title variable before including this file.
if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'BioTern';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$header_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
if ($header_user_name === '') {
    $header_user_name = 'BioTern User';
}
$header_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
if ($header_user_email === '') {
    $header_user_email = 'admin@biotern.local';
}
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
$header_uid = (int)($_SESSION['user_id'] ?? 0);
if ($header_uid > 0) {
    $hdr_db = @new mysqli('127.0.0.1', 'root', '', 'biotern_db');
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
            $count_stmt->bind_param('i', $header_uid);
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
                $list_stmt->bind_param('i', $header_uid);
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
                $list_stmt->bind_param('i', $header_uid);
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
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
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
        // Apply saved skin + sidebar state as early as possible to avoid initial layout flash.
        (function(){
            try {
                var skin = localStorage.getItem('app-skin-dark');
                if (skin === 'app-skin-dark') {
                    document.documentElement.classList.add('app-skin-dark');
                }
            } catch (e) {
                /* ignore */
            }

            try {
                var menuState = localStorage.getItem('nexel-classic-dashboard-menu-mini-theme');
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
                /* ignore */
            }
        })();
    </script>
    <!--! END: Early Skin Script -->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css" />
    <!--! END: Custom CSS-->
    <style>
        .nxl-header .user-avtar,
        .nxl-user-dropdown .user-avtar {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            min-height: 40px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            object-position: center !important;
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
                    <div class="nxl-h-item d-none d-sm-flex">
                        <a href="javascript:void(0);" class="nxl-head-link me-0">
                            <i class="feather-search"></i>
                        </a>
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
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($header_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar">
                                    <div>
                                        <h6 class="text-dark mb-0"><?php echo htmlspecialchars($header_user_name, ENT_QUOTES, 'UTF-8'); ?></h6>
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
