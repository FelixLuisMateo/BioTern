<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalized_upload_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

$allowed_roles = ['admin', 'coordinator', 'supervisor', 'student'];
$current_user_id = (int) (
    $_SESSION['user_id'] ??
    $_SESSION['id'] ??
    $_SESSION['account_id'] ??
    0
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['users_flash_message'] = 'Invalid user ID.';
        $_SESSION['users_flash_type'] = 'danger';
        header('Location: users.php');
        exit;
    }

    if ($action === 'toggle_status') {
        $next_status = (int)($_POST['next_status'] ?? 0) === 1 ? 1 : 0;
        $stmt = $conn->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $next_status, $id);
            if ($stmt->execute()) {
                $_SESSION['users_flash_message'] = 'User status updated.';
                $_SESSION['users_flash_type'] = 'success';
            } else {
                $_SESSION['users_flash_message'] = 'Failed to update status: ' . $stmt->error;
                $_SESSION['users_flash_type'] = 'danger';
            }
            $stmt->close();
        }
    } elseif ($action === 'update_role') {
        $role = strtolower(trim((string)($_POST['role'] ?? '')));
        if (!in_array($role, $allowed_roles, true)) {
            $_SESSION['users_flash_message'] = 'Invalid role selected.';
            $_SESSION['users_flash_type'] = 'danger';
        } else {
            $stmt = $conn->prepare('UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $role, $id);
                if ($stmt->execute()) {
                    $_SESSION['users_flash_message'] = 'User role updated.';
                    $_SESSION['users_flash_type'] = 'success';
                } else {
                    $_SESSION['users_flash_message'] = 'Failed to update role: ' . $stmt->error;
                    $_SESSION['users_flash_type'] = 'danger';
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        if ($current_user_id > 0 && $id === $current_user_id) {
            $_SESSION['users_flash_message'] = 'You cannot delete the currently logged-in account.';
            $_SESSION['users_flash_type'] = 'danger';
        } else {
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $_SESSION['users_flash_message'] = 'User deleted.';
                    $_SESSION['users_flash_type'] = 'success';
                } else {
                    $_SESSION['users_flash_message'] = 'Delete failed: ' . $stmt->error;
                    $_SESSION['users_flash_type'] = 'danger';
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'upload_profile_picture') {
        if (!isset($_FILES['profile_picture']) || !is_array($_FILES['profile_picture'])) {
            $_SESSION['users_flash_message'] = 'Please choose an image file.';
            $_SESSION['users_flash_type'] = 'warning';
        } else {
            $file = $_FILES['profile_picture'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $_SESSION['users_flash_message'] = 'Upload failed. Please try again.';
                $_SESSION['users_flash_type'] = 'danger';
            } else {
                $tmp = (string)($file['tmp_name'] ?? '');
                $size = (int)($file['size'] ?? 0);
                $name = (string)($file['name'] ?? '');
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
                $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
                if ($finfo) finfo_close($finfo);
                $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                if ($size <= 0 || $size > 3 * 1024 * 1024) {
                    $_SESSION['users_flash_message'] = 'Image must be less than 3MB.';
                    $_SESSION['users_flash_type'] = 'warning';
                } elseif (!in_array($ext, $allowed_ext, true) || ($mime !== '' && !in_array($mime, $allowed_mime, true))) {
                    $_SESSION['users_flash_message'] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
                    $_SESSION['users_flash_type'] = 'warning';
                } else {
                    $upload_dir_fs = dirname(__DIR__) . '/assets/images/avatar/uploads';
                    if (!is_dir($upload_dir_fs)) {
                        @mkdir($upload_dir_fs, 0777, true);
                    }
                    $safe_name = 'user_' . $id . '_' . time() . '.' . $ext;
                    $dest_fs = $upload_dir_fs . '/' . $safe_name;
                    $dest_rel = 'assets/images/avatar/uploads/' . $safe_name;

                    if (!@move_uploaded_file($tmp, $dest_fs)) {
                        $_SESSION['users_flash_message'] = 'Failed to save uploaded file.';
                        $_SESSION['users_flash_type'] = 'danger';
                    } else {
                        // delete previous custom file if any
                        $old_stmt = $conn->prepare('SELECT profile_picture FROM users WHERE id = ? LIMIT 1');
                        $old_path = '';
                        if ($old_stmt) {
                            $old_stmt->bind_param('i', $id);
                            $old_stmt->execute();
                            $old_res = $old_stmt->get_result()->fetch_assoc();
                            $old_stmt->close();
                            $old_path = normalized_upload_path((string)($old_res['profile_picture'] ?? ''));
                        }

                        $stmt = $conn->prepare('UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?');
                        if ($stmt) {
                            $stmt->bind_param('si', $dest_rel, $id);
                            if ($stmt->execute()) {
                                $_SESSION['users_flash_message'] = 'Profile picture updated.';
                                $_SESSION['users_flash_type'] = 'success';
                                if ($current_user_id > 0 && $id === $current_user_id) {
                                    $_SESSION['profile_picture'] = $dest_rel;
                                }

                                if ($old_path !== '' && strpos($old_path, 'assets/images/avatar/uploads/') === 0) {
                                    $old_fs = dirname(__DIR__) . '/' . $old_path;
                                    if (is_file($old_fs)) {
                                        @unlink($old_fs);
                                    }
                                }
                            } else {
                                $_SESSION['users_flash_message'] = 'Failed to update profile_picture: ' . $stmt->error;
                                $_SESSION['users_flash_type'] = 'danger';
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    header('Location: users.php');
    exit;
}

$flash_message = (string)($_SESSION['users_flash_message'] ?? '');
$flash_type = (string)($_SESSION['users_flash_type'] ?? 'success');
unset($_SESSION['users_flash_message'], $_SESSION['users_flash_type']);
$users_toast_type = $flash_type;
$users_toast_message = $flash_message;

$search = trim((string)($_GET['q'] ?? ''));
$role_filter = strtolower(trim((string)($_GET['role'] ?? 'all')));
$status_filter = strtolower(trim((string)($_GET['status'] ?? 'all')));

$where = [];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR username LIKE ? OR email LIKE ?)';
    $types .= 'sss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (in_array($role_filter, $allowed_roles, true)) {
    $where[] = 'role = ?';
    $types .= 's';
    $params[] = $role_filter;
}

if ($status_filter === 'active' || $status_filter === 'inactive') {
    $where[] = 'is_active = ?';
    $types .= 'i';
    $params[] = $status_filter === 'active' ? 1 : 0;
}

$sql = '
    SELECT id, name, username, email, role, is_active, email_verified_at, created_at, updated_at, profile_picture
    FROM users
';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC';

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
    }
    $stmt->close();
}

$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'admins' => 0
];
$stats_query = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins
    FROM users
";
$stats_res = $conn->query($stats_query);
if ($stats_res) {
    $stats_data = $stats_res->fetch_assoc();
    if ($stats_data) {
        $stats['total'] = (int)($stats_data['total'] ?? 0);
        $stats['active'] = (int)($stats_data['active'] ?? 0);
        $stats['inactive'] = (int)($stats_data['inactive'] ?? 0);
        $stats['admins'] = (int)($stats_data['admins'] ?? 0);
    }
}

function format_users_datetime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('M d, Y', $ts);
}

function users_role_label(string $role): string
{
    return ucwords(str_replace('_', ' ', trim($role)));
}

$page_title = 'BioTern || Users';
$base_href = '';
$page_body_class = 'app-page-users-admin';
$page_styles = [
    'assets/css/state/notification-skin.css',
    'assets/css/modules/management/management-filters.css',
    'assets/css/modules/app-ui-lists-tables.css',
    'assets/css/modules/auth/page-users-admin.css',
];
$page_scripts = [
    'assets/js/modules/auth/users-page.js',
    'assets/js/theme-customizer-init.min.js',
];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">

<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Users Account</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item">Users</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="auth-register.php" class="btn btn-primary">Create User</a>
    </div>
</div>

<div class="main-content users-admin-page">
    <div class="row g-1 mb-1">
        <div class="col-md-3">
            <div class="card stat-card app-users-stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Total Users</div>
                    <div class="h5 mb-0"><?php echo $stats['total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card app-users-stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Active</div>
                    <div class="h5 mb-0 text-success"><?php echo $stats['active']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card app-users-stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Inactive</div>
                    <div class="h5 mb-0 text-danger"><?php echo $stats['inactive']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card app-users-stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Admins</div>
                    <div class="h5 mb-0"><?php echo $stats['admins']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <section class="app-users-filter-section">
        <div class="filter-panel filter-card app-users-filter-card">
            <div class="filter-panel-head app-users-filter-head">
                <div>
                    <div class="filter-panel-label app-users-filter-label">
                        <i class="feather-users"></i>
                        <span>Filter Users</span>
                    </div>
                    <p class="filter-panel-sub app-users-filter-sub">Search accounts by name, username, email, role, and active status.</p>
                </div>
                <div class="filter-panel-head-actions app-users-filter-actions">
                    <?php $active_user_filter_count = ($search !== '' ? 1 : 0) + ($role_filter !== 'all' ? 1 : 0) + ($status_filter !== 'all' ? 1 : 0); ?>
                    <span class="app-users-filter-status"><?php echo $active_user_filter_count; ?> active filter<?php echo $active_user_filter_count === 1 ? '' : 's'; ?></span>
                    <a href="users.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                </div>
            </div>
            <form method="get" class="filter-form row g-2 align-items-end app-users-filter-form" id="usersFilterForm">
                <div class="col-xl-4 col-lg-5 col-md-6">
                    <label class="form-label" for="usersFilterSearch">Search</label>
                    <input type="text" id="usersFilterSearch" name="q" class="form-control" value="<?php echo e($search); ?>" placeholder="Name, username, or email">
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6">
                    <label class="form-label" for="usersFilterRole">Role</label>
                    <select id="usersFilterRole" name="role" class="form-control" data-ui-select="custom">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="coordinator" <?php echo $role_filter === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                        <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6">
                    <label class="form-label" for="usersFilterStatus">Status</label>
                    <select id="usersFilterStatus" name="status" class="form-control" data-ui-select="custom">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-1 col-md-6 d-flex">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </section>

    <div class="card stretch stretch-full app-users-table-card app-data-card app-data-toolbar">
        <div class="card-body p-0">
            <div class="table-responsive app-users-table-wrap app-data-table-wrap">
                <table class="table table-hover align-middle mb-0 app-users-list-table app-data-table" id="usersListTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Account</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $index => $r): ?>
                            <?php
                                $id = (int)$r['id'];
                                $is_active = (int)($r['is_active'] ?? 0) === 1;
                                $role = strtolower((string)($r['role'] ?? 'student'));
                                $role_label = users_role_label($role);
                                $pp = normalized_upload_path((string)($r['profile_picture'] ?? ''));
                                $pp_url = $pp !== '' ? biotern_avatar_public_src($pp, $id) : '';
                                $display_name = trim((string)($r['name'] ?? ''));
                                $username_label = (string)($r['username'] ?? '-');
                                $email_label = (string)($r['email'] ?? '-');
                                $created_label = format_users_datetime((string)($r['created_at'] ?? ''));
                                $updated_label = format_users_datetime((string)($r['updated_at'] ?? ''));
                                $verified = !empty($r['email_verified_at']);
                                $verified_label = $verified ? 'Verified' : 'Not Verified';
                            ?>
                            <tr class="app-users-table-row">
                                <td data-label="User">
                                    <div class="app-users-student-block">
                                        <?php if ($pp_url !== ''): ?>
                                            <img src="<?php echo e($pp_url); ?>" alt="avatar" class="app-users-avatar">
                                        <?php else: ?>
                                            <img src="<?php echo e(header_asset_versioned_href('assets/images/avatar/' . (($index % 5) + 1) . '.png')); ?>" alt="avatar" class="app-users-avatar">
                                        <?php endif; ?>
                                        <div class="app-users-student-copy">
                                            <span class="app-users-student-name"><?php echo e($display_name !== '' ? $display_name : '-'); ?></span>
                                            <span class="app-users-student-meta">@<?php echo e($username_label); ?></span>
                                            <span class="app-users-student-submeta">ID <?php echo $id; ?></span>
                                        </div>
                                    </div>
                                    <div class="collapse app-users-inline-collapse" id="userRowDetails<?php echo $id; ?>">
                                        <div class="app-users-inline-details">
                                            <div class="app-users-inline-detail-item">
                                                <span class="app-users-inline-detail-label">Email</span>
                                                <span class="app-users-inline-detail-value"><?php echo e($email_label); ?></span>
                                            </div>
                                            <div class="app-users-inline-detail-item app-users-inline-detail-item-stack">
                                                <span class="app-users-inline-detail-label">Role</span>
                                                <form method="post" class="app-users-inline-form">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                    <select name="role" class="form-control app-users-inline-select" data-ui-select="custom" onchange="this.form.submit();">
                                                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        <option value="coordinator" <?php echo $role === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                                                        <option value="supervisor" <?php echo $role === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                                        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                                                    </select>
                                                </form>
                                            </div>
                                            <div class="app-users-inline-detail-item app-users-inline-detail-item-stack">
                                                <span class="app-users-inline-detail-label">Profile Photo</span>
                                                <form method="post" enctype="multipart/form-data" class="app-users-upload-form">
                                                    <input type="hidden" name="action" value="upload_profile_picture">
                                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                    <input type="file" name="profile_picture" class="form-control" accept="image/*" required>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Upload</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Account">
                                    <div class="app-users-cell-stack">
                                        <span class="app-users-cell-title">Role</span>
                                        <span class="app-users-role-pill is-<?php echo e($role); ?>"><?php echo e($role_label); ?></span>
                                        <span class="app-users-cell-meta"><?php echo e($email_label); ?></span>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <div class="app-users-status-block">
                                        <span class="app-users-status-pill <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
                                            <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <span class="app-users-status-pill <?php echo $verified ? 'is-verified' : 'is-unverified'; ?>">
                                            <?php echo e($verified_label); ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Activity">
                                    <div class="app-users-cell-stack">
                                        <span class="app-users-cell-title">Created</span>
                                        <span class="app-users-cell-value"><?php echo e($created_label); ?></span>
                                        <span class="app-users-cell-meta">Updated <?php echo e($updated_label); ?></span>
                                    </div>
                                </td>
                                <td data-label="Actions">
                                    <div class="app-users-row-actions">
                                        <button class="btn btn-sm btn-outline-secondary app-users-inline-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#userRowDetails<?php echo $id; ?>" aria-expanded="false" aria-controls="userRowDetails<?php echo $id; ?>">
                                            Details
                                        </button>
                                        <div class="dropdown users-action-dropdown">
                                            <a href="javascript:void(0)" class="btn btn-sm btn-light app-users-menu-toggle" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-label="More actions">
                                                <i class="feather feather-more-horizontal"></i>
                                            </a>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="post" class="m-0">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                        <input type="hidden" name="next_status" value="<?php echo $is_active ? 0 : 1; ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="feather feather-power me-3"></i>
                                                            <span><?php echo $is_active ? 'Deactivate' : 'Activate'; ?></span>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="mailto:<?php echo rawurlencode($email_label); ?>">
                                                        <i class="feather feather-mail me-3"></i>
                                                        <span>Email User</span>
                                                    </a>
                                                </li>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" class="m-0" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="feather feather-trash-2 me-3"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div> <!-- .nxl-content -->
</main>
<?php if ($users_toast_message !== ''): ?>
<script>
(function () {
    var payload = {
        type: <?php echo json_encode($users_toast_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        message: <?php echo json_encode($users_toast_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };
    if (!payload.message) {
        return;
    }

    var variantMap = { success: 'success', info: 'info', warning: 'warning', danger: 'error', error: 'error' };
    var iconMap = {
        success: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 7 9 18l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 10v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 7h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M15 9 9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    };

    var variant = variantMap[payload.type] || 'info';
    var root = document.body || document.documentElement;
    if (!root) {
        return;
    }

    var toast = document.createElement('div');
    toast.id = 'usersPageToast';
    toast.className = 'app-theme-toast-static app-theme-toast-static--' + variant;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');

    var iconWrap = document.createElement('span');
    iconWrap.className = 'app-theme-toast-static-icon';
    var iconEl = document.createElement('span');
    iconEl.className = 'app-theme-toast-static-icon-glyph';
    iconEl.setAttribute('aria-hidden', 'true');
    iconEl.innerHTML = iconMap[variant] || iconMap.info;
    iconWrap.appendChild(iconEl);

    var textWrap = document.createElement('span');
    textWrap.className = 'app-theme-toast-static-text';
    textWrap.textContent = String(payload.message);

    toast.appendChild(iconWrap);
    toast.appendChild(textWrap);
    root.appendChild(toast);

    window.setTimeout(function () {
        if (toast && toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5200);
})();
</script>
<?php endif; ?>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>



