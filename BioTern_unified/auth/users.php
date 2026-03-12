<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : ''; 
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function normalized_upload_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function sync_profile_picture_to_role_tables(mysqli $conn, int $user_id, string $profile_path): void
{
    if ($user_id <= 0 || $profile_path === '') {
        return;
    }

    $queries = [
        'UPDATE students SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?',
        'UPDATE coordinators SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?',
        'UPDATE supervisors SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?',
    ];

    foreach ($queries as $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $profile_path, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
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
            $conn->begin_transaction();
            try {
                $cleanup = [
                    ['table' => 'admin', 'columns' => ['user_id']],
                    ['table' => 'coordinators', 'columns' => ['user_id']],
                    ['table' => 'supervisors', 'columns' => ['user_id']],
                    ['table' => 'students', 'columns' => ['user_id']],
                    ['table' => 'notifications', 'columns' => ['user_id']],
                    ['table' => 'login_logs', 'columns' => ['user_id']],
                    ['table' => 'application_letter', 'columns' => ['user_id']],
                    ['table' => 'endorsement_letter', 'columns' => ['user_id']],
                    ['table' => 'moa', 'columns' => ['user_id']],
                    ['table' => 'dau_moa', 'columns' => ['user_id']],
                    ['table' => 'document_workflow', 'columns' => ['user_id']],
                    ['table' => 'messages', 'columns' => ['from_user_id', 'to_user_id']],
                ];

                foreach ($cleanup as $item) {
                    if (!table_exists($conn, $item['table'])) {
                        continue;
                    }
                    $cols = $item['columns'];
                    if (count($cols) === 1) {
                        $sql = "DELETE FROM `{$item['table']}` WHERE `{$cols[0]}` = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param('i', $id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        $sql = "DELETE FROM `{$item['table']}` WHERE `{$cols[0]}` = ? OR `{$cols[1]}` = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param('ii', $id, $id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                if (!$stmt) {
                    throw new Exception('Delete failed: ' . $conn->error);
                }
                $stmt->bind_param('i', $id);
                if (!$stmt->execute()) {
                    throw new Exception('Delete failed: ' . $stmt->error);
                }
                $stmt->close();

                $conn->commit();
                $_SESSION['users_flash_message'] = 'User deleted.';
                $_SESSION['users_flash_type'] = 'success';
            } catch (Throwable $e) {
                $conn->rollback();
                $_SESSION['users_flash_message'] = $e->getMessage();
                $_SESSION['users_flash_type'] = 'danger';
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
                        $old_path = defined('DB_PASS') ? DB_PASS : ''; 
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
                                sync_profile_picture_to_role_tables($conn, $id, $dest_rel);
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

$search = trim((string)($_GET['q'] ?? ''));
$role_filter = strtolower(trim((string)($_GET['role'] ?? 'all')));
$status_filter = strtolower(trim((string)($_GET['status'] ?? 'all')));

$where = [];
$types = defined('DB_PASS') ? DB_PASS : ''; 
$params = [];

$where[] = "(role <> 'student' OR COALESCE(application_status, 'approved') = 'approved')";

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
    WHERE (role <> 'student' OR COALESCE(application_status, 'approved') = 'approved')
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

$page_title = 'Users Account';
$base_href = defined('DB_PASS') ? DB_PASS : ''; 
include __DIR__ . '/../includes/header.php';
?>

<style>
    .users-admin-page .stat-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(2, 6, 23, 0.06);
    }
    .users-admin-page {
        padding-top: 0.1rem;
    }
    .users-admin-page .stat-label {
        font-size: 0.7rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.05rem;
    }
    .users-admin-page .users-panel {
        border: 1px solid rgba(15, 23, 42, 0.1);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(2, 6, 23, 0.06);
        max-width: 100%;
    }
    .users-admin-page .users-panel .card-header {
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.95) 0%, rgba(241, 245, 249, 0.9) 100%);
        border-bottom: 1px solid rgba(148, 163, 184, 0.25);
        padding: 0.5rem 0.75rem;
    }
    .users-admin-page .users-panel .card-header .form-label {
        margin-bottom: 0.2rem !important;
        font-size: 0.78rem;
    }
    .users-admin-page .users-panel .card-header .form-control,
    .users-admin-page .users-panel .card-header .form-select,
    .users-admin-page .users-panel .card-header .btn {
        height: 34px;
        padding-top: 0.3rem;
        padding-bottom: 0.3rem;
    }
    .users-admin-page .users-table {
        width: max-content;
        min-width: 100%;
        table-layout: auto;
    }
    .users-admin-page .users-table thead th {
        border-bottom-width: 1px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 0.7rem;
        color: #64748b;
        background: rgba(248, 250, 252, 0.7);
        white-space: nowrap;
    }
    .users-admin-page .users-table td {
        white-space: nowrap;
        vertical-align: middle;
    }
    .users-admin-page .users-table .user-cell {
        min-width: 190px;
    }
    .users-admin-page .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: #fff;
    }
    .users-admin-page .users-table .email-cell {
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .users-admin-page .users-table .created-cell {
        color: #64748b;
        font-size: 0.84rem;
    }
    .users-admin-page .actions-group {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 0.45rem;
        min-width: 450px;
    }
    .users-admin-page .actions-group .role-form {
        flex: 1 1 160px;
        min-width: 140px;
    }
    .users-admin-page .actions-group form {
        margin: 0;
    }
    .users-admin-page .actions-group .form-select,
    .users-admin-page .actions-group .btn {
        height: 32px;
        font-size: 0.78rem;
        border-radius: 8px;
    }
    .users-admin-page .actions-group .upload-form {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 0 0 auto;
    }
    .users-admin-page .actions-group .upload-form .form-control {
        width: 150px;
        min-width: 130px;
        height: 32px;
        font-size: 0.75rem;
        padding: 0.2rem 0.35rem;
    }
    .users-admin-page .actions-group .upload-form .btn {
        white-space: nowrap;
    }
    .users-admin-page .actions-group .role-form .form-select {
        width: 100%;
        border-color: rgba(100, 116, 139, 0.25);
        background-color: rgba(255, 255, 255, 0.88);
        color: #1f2937 !important;
        -webkit-text-fill-color: #1f2937 !important;
        opacity: 1 !important;
        font-weight: 600;
        line-height: 1.25;
        padding-top: 0.42rem;
        padding-bottom: 0.42rem;
    }
    .users-admin-page .actions-group .role-form .form-select option {
        color: #111827;
    }
    .users-admin-page .actions-group .btn {
        padding: 0.35rem 0.7rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .users-admin-page .actions-group .btn {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    .users-admin-page .table-responsive {
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
    }
    @media (max-width: 1400px) {
        .users-admin-page .actions-group {
            min-width: 310px;
        }
        .users-admin-page .actions-group .btn,
        .users-admin-page .actions-group .form-select {
            width: auto;
        }
    }
    @media (max-width: 1200px) {
        .users-admin-page .users-table {
            min-width: 1120px;
        }
    }
    .app-skin-dark .users-admin-page .stat-card,
    .app-skin-dark .users-admin-page .users-panel {
        border-color: rgba(148, 163, 184, 0.18);
        box-shadow: none;
    }
    .app-skin-dark .users-admin-page .users-panel .card-header,
    .app-skin-dark .users-admin-page .users-table thead th {
        background: rgba(15, 23, 42, 0.55);
        color: #cbd5e1;
    }
    .app-skin-dark .users-admin-page .form-select {
        background-color: rgba(15, 23, 42, 0.72) !important;
        border-color: rgba(148, 163, 184, 0.28) !important;
        color: #f8fafc !important;
        -webkit-text-fill-color: #f8fafc !important;
        opacity: 1 !important;
    }
    .app-skin-dark .users-admin-page .form-select:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25) !important;
    }
    .app-skin-dark .users-admin-page .form-select option {
        background-color: #0f172a !important;
        color: #e2e8f0 !important;
    }
    .app-skin-dark .users-admin-page .actions-group .role-form .form-select {
        background-color: rgba(15, 23, 42, 0.55);
        border-color: rgba(148, 163, 184, 0.2);
        color: #f8fafc !important;
        -webkit-text-fill-color: #f8fafc !important;
        opacity: 1 !important;
    }
    .app-skin-dark .users-admin-page .actions-group .role-form .form-select option {
        color: #e2e8f0;
        background-color: #0f172a;
    }
    .app-skin-dark .users-admin-page .actions-group .upload-form .form-control {
        color: #f8fafc !important;
        background-color: rgba(15, 23, 42, 0.72) !important;
        border-color: rgba(148, 163, 184, 0.28) !important;
    }
    .app-skin-dark .users-admin-page .actions-group .upload-form .form-control::file-selector-button {
        color: #f8fafc !important;
        background: #1f2937 !important;
        border: 1px solid rgba(148, 163, 184, 0.35) !important;
    }
    .app-skin-dark .users-admin-page .actions-group .upload-form .form-control::-webkit-file-upload-button {
        color: #f8fafc !important;
        background: #1f2937 !important;
        border: 1px solid rgba(148, 163, 184, 0.35) !important;
    }
    .app-skin-dark .users-admin-page .actions-group .btn-action-toggle {
        border-color: #f59e0b;
        color: #fbbf24;
        background-color: transparent;
    }
    .app-skin-dark .users-admin-page .actions-group .btn-action-delete {
        border-color: #f43f5e;
        color: #fb7185;
        background-color: transparent;
    }
    .app-skin-dark .users-admin-page .stat-label,
    .app-skin-dark .users-admin-page .users-table .created-cell {
        color: #94a3b8;
    }
</style>

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
        <a href="auth-register-creative.php" class="btn btn-primary">Create User</a>
    </div>
</div>

<div class="main-content users-admin-page">
    <?php if ($flash_message !== ''): ?>
        <div class="alert alert-<?php echo e($flash_type); ?> py-2"><?php echo e($flash_message); ?></div>
    <?php endif; ?>

    <div class="row g-1 mb-1">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Total Users</div>
                    <div class="h5 mb-0"><?php echo $stats['total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Active</div>
                    <div class="h5 mb-0 text-success"><?php echo $stats['active']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Inactive</div>
                    <div class="h5 mb-0 text-danger"><?php echo $stats['inactive']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body py-1 px-3">
                    <div class="stat-label">Admins</div>
                    <div class="h5 mb-0"><?php echo $stats['admins']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card stretch stretch-full users-panel">
        <div class="card-header">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="usersFilterSearch">Search</label>
                    <input type="text" id="usersFilterSearch" name="q" class="form-control" value="<?php echo e($search); ?>" placeholder="Name, username, or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="usersFilterRole">Role</label>
                    <select id="usersFilterRole" name="role" class="form-select">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="coordinator" <?php echo $role_filter === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                        <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="usersFilterStatus">Status</label>
                    <select id="usersFilterStatus" name="status" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Verified</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
                                $id = (int)$r['id'];
                                $is_active = (int)($r['is_active'] ?? 0) === 1;
                                $role = strtolower((string)($r['role'] ?? 'student'));
                                $pp = normalized_upload_path((string)($r['profile_picture'] ?? ''));
                                $pp_url = defined('DB_PASS') ? DB_PASS : ''; 
                                if ($pp !== '' && file_exists(dirname(__DIR__) . '/' . $pp)) {
                                    $mtime = @filemtime(dirname(__DIR__) . '/' . $pp);
                                    $pp_url = $pp . ($mtime ? ('?v=' . $mtime) : '');
                                }
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td class="user-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($pp_url !== ''): ?>
                                            <img src="<?php echo e($pp_url); ?>" alt="avatar" class="user-avatar">
                                        <?php else: ?>
                                            <img src="../assets/images/avatar/<?php echo ($id % 5) + 1; ?>.png" alt="avatar" class="user-avatar">
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold"><?php echo e($r['name'] ?? '-'); ?></div>
                                            <div class="text-muted small">@<?php echo e($r['username'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="email-cell"><?php echo e($r['email'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-soft-primary text-primary text-capitalize"><?php echo e($role); ?></span>
                                </td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['email_verified_at'])): ?>
                                        <span class="badge bg-soft-success text-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-warning text-warning">Not Verified</span>
                                    <?php endif; ?>
                                </td>
                                <td class="created-cell"><?php echo e($r['created_at'] ?? '-'); ?></td>
                                <td>
                                    <div class="actions-group">
                                        <form method="post" class="role-form">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit();">
                                                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="coordinator" <?php echo $role === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                                                <option value="supervisor" <?php echo $role === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                                <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                                            </select>
                                        </form>

                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="next_status" value="<?php echo $is_active ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-action-toggle <?php echo $is_active ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                <?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <form method="post" enctype="multipart/form-data" class="upload-form">
                                            <input type="hidden" name="action" value="upload_profile_picture">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <input type="file" name="profile_picture" class="form-control form-control-sm" accept="image/*" required>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Upload</button>
                                        </form>

                                        <form method="post" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <button type="submit" class="btn btn-sm btn-action-delete btn-outline-danger">Delete</button>
                                        </form>
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

<?php
require_once dirname(__DIR__) . '/config/db.php';
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>

