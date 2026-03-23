<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hbc_base = isset($base_href) && is_string($base_href) ? trim($base_href) : '';
if ($hbc_base === '') {
    $script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $project_segment = '/' . basename(dirname(__DIR__)) . '/';
    $project_pos = stripos($script_name, $project_segment);
    if ($project_pos !== false) {
        $hbc_base = substr($script_name, 0, $project_pos) . $project_segment;
    } else {
        $hbc_base = '/';
    }
}
$hbc_base = str_replace('\\', '/', $hbc_base);
if ($hbc_base !== '' && $hbc_base[0] !== '/') {
    $hbc_base = '/' . $hbc_base;
}
$hbc_base = preg_replace('#/+#', '/', $hbc_base);
if ($hbc_base === '' || substr($hbc_base, -1) !== '/') {
    $hbc_base .= '/';
}
$base_href = $hbc_base;

if (!function_exists('hbc_href')) {
    function hbc_href(string $base, string $file): string
    {
        return htmlspecialchars($base . ltrim($file, '/'), ENT_QUOTES, 'UTF-8');
    }
}

$hbc_user_id = (int)($_SESSION['user_id'] ?? 0);
$hbc_user_name = (string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User');
$hbc_user_email = (string)($_SESSION['email'] ?? '');
$hbc_user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$hbc_profile_picture = trim((string)($_SESSION['profile_picture'] ?? ''));
$hbc_status = 'Active';
$hbc_member_since = 'Unknown';
$hbc_last_login = 'No login record';
$hbc_notifications_unread = 0;

if ($hbc_user_id > 0 && isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
    $stmt = $conn->prepare('SELECT name, username, email, role, is_active, profile_picture, created_at FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $hbc_user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            $hbc_user_name = (string)($row['name'] ?? $row['username'] ?? $hbc_user_name);
            $hbc_user_email = (string)($row['email'] ?? $hbc_user_email);
            $hbc_user_role = strtolower(trim((string)($row['role'] ?? $hbc_user_role)));
            $hbc_profile_picture = trim((string)($row['profile_picture'] ?? $hbc_profile_picture));
            $hbc_status = ((int)($row['is_active'] ?? 1) === 1) ? 'Active' : 'Inactive';
            $created = trim((string)($row['created_at'] ?? ''));
            if ($created !== '' && strtotime($created) !== false) {
                $hbc_member_since = date('M d, Y', strtotime($created));
            }
        }
    }

    $stmt = $conn->prepare("SELECT created_at FROM login_logs WHERE user_id = ? AND status = 'success' ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $hbc_user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $created = trim((string)($row['created_at'] ?? ''));
        if ($created !== '' && strtotime($created) !== false) {
            $hbc_last_login = date('M d, Y h:i A', strtotime($created));
        }
    }
}

if ($hbc_profile_picture !== '') {
    $hbc_avatar = $hbc_base . ltrim(str_replace('\\', '/', $hbc_profile_picture), '/');
} else {
    $hbc_avatar = $hbc_base . 'assets/images/avatar/' . (((int)($hbc_user_id % 5)) + 1) . '.png';
}

$hbc_role_badge = 'bg-soft-primary text-primary';
if ($hbc_user_role === 'admin') {
    $hbc_role_badge = 'bg-soft-success text-success';
} elseif ($hbc_user_role === 'supervisor') {
    $hbc_role_badge = 'bg-soft-warning text-warning';
} elseif ($hbc_user_role === 'coordinator') {
    $hbc_role_badge = 'bg-soft-info text-info';
} elseif ($hbc_user_role === 'student') {
    $hbc_role_badge = 'bg-soft-secondary text-secondary';
}

include_once __DIR__ . '/navigation.php';
?>
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
                    <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                        <i class="feather-sun"></i>
                    </a>
                </div>
                <div class="dropdown nxl-h-item">
                    <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                        <i class="feather-bell"></i>
                        <?php if ((int)$hbc_notifications_unread > 0): ?>
                            <span class="badge bg-danger nxl-h-badge"><?php echo (int)$hbc_notifications_unread; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                        <div class="d-flex justify-content-between align-items-center notifications-head">
                            <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                        </div>
                    </div>
                </div>
                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <img src="<?php echo htmlspecialchars($hbc_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo htmlspecialchars($hbc_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                <div>
                                    <h6 class="text-dark mb-0"><?php echo htmlspecialchars($hbc_user_name, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($hbc_user_role !== ''): ?>
                                            <span class="badge <?php echo $hbc_role_badge; ?> ms-1"><?php echo htmlspecialchars(ucfirst($hbc_user_role), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </h6>
                                    <span class="fs-12 fw-medium text-muted"><?php echo htmlspecialchars($hbc_user_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-item-text pb-1">
                            <div class="fs-11 fw-semibold text-muted text-uppercase">Status</div>
                        </div>
                        <div class="dropdown-item-text pt-1 pb-1">
                            <span class="hstack justify-content-between gap-2">
                                <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="wd-10 ht-10 border border-2 border-gray-1 <?php echo $hbc_status === 'Active' ? 'bg-success' : 'bg-secondary'; ?> rounded-circle me-2"></i>Account</span>
                                <span class="badge <?php echo $hbc_status === 'Active' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'; ?>"><?php echo htmlspecialchars($hbc_status, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                        </div>
                        <div class="dropdown-item-text pt-1 pb-1">
                            <span class="hstack justify-content-between gap-2">
                                <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-shield me-2"></i>Role</span>
                                <span class="badge <?php echo $hbc_role_badge; ?>"><?php echo htmlspecialchars($hbc_user_role !== '' ? ucfirst($hbc_user_role) : 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                        </div>
                        <div class="dropdown-item-text pt-1 pb-1">
                            <span class="hstack justify-content-between gap-2">
                                <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-calendar me-2"></i>Member Since</span>
                                <span class="fs-12 text-light"><?php echo htmlspecialchars($hbc_member_since, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                        </div>
                        <div class="dropdown-item-text pt-1 pb-2">
                            <span class="hstack justify-content-between gap-2">
                                <span class="d-inline-flex align-items-center text-nowrap fw-medium text-light"><i class="feather-clock me-2"></i>Last Login</span>
                                <span class="fs-12 text-light text-end"><?php echo htmlspecialchars($hbc_last_login, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo hbc_href($hbc_base, 'profile-details.php'); ?>" class="dropdown-item">
                            <i class="feather-user"></i>
                            <span>Profile Details</span>
                        </a>
                        <a href="<?php echo hbc_href($hbc_base, 'activity-feed.php'); ?>" class="dropdown-item">
                            <i class="feather-activity"></i>
                            <span>Activity Feed</span>
                        </a>
                        <a href="<?php echo hbc_href($hbc_base, 'notifications.php'); ?>" class="dropdown-item">
                            <i class="feather-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-settings"></i>
                            <span>Account Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo hbc_href($hbc_base, 'auth-login-cover.php?logout=1'); ?>" class="dropdown-item">
                            <i class="feather-log-out"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

