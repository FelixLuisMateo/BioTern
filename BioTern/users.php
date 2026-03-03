<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
    SELECT id, name, username, email, role, is_active, email_verified_at, created_at, updated_at
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

$page_title = 'Users Account';
include 'includes/header.php';
?>

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

<div class="main-content">
    <?php if ($flash_message !== ''): ?>
        <div class="alert alert-<?php echo e($flash_type); ?> py-2"><?php echo e($flash_message); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-muted small">Total Users</div>
                    <div class="h4 mb-0"><?php echo $stats['total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-muted small">Active</div>
                    <div class="h4 mb-0 text-success"><?php echo $stats['active']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-muted small">Inactive</div>
                    <div class="h4 mb-0 text-danger"><?php echo $stats['inactive']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-muted small">Admins</div>
                    <div class="h4 mb-0"><?php echo $stats['admins']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card stretch stretch-full">
        <div class="card-header">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" name="q" class="form-control" value="<?php echo e($search); ?>" placeholder="Name, username, or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Role</label>
                    <select name="role" class="form-select">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="coordinator" <?php echo $role_filter === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                        <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select">
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
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Verified</th>
                            <th>Created</th>
                            <th style="min-width: 270px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $id = (int)$r['id'];
                                $is_active = (int)($r['is_active'] ?? 0) === 1;
                                $role = strtolower((string)($r['role'] ?? 'student'));
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo e($r['name'] ?? '-'); ?></td>
                                <td><?php echo e($r['username'] ?? '-'); ?></td>
                                <td><?php echo e($r['email'] ?? '-'); ?></td>
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
                                <td><?php echo e($r['created_at'] ?? '-'); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="post" class="d-flex gap-2">
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
                                            <button type="submit" class="btn btn-sm <?php echo $is_active ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                <?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <form method="post" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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
include 'includes/footer.php';
$conn->close();
?>
