<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : ''; 
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$message = defined('DB_PASS') ? DB_PASS : ''; 
$message_type = 'success';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->begin_transaction();
        try {
            $user_id = 0;
            $lookup = $conn->prepare('SELECT user_id FROM coordinators WHERE id = ? LIMIT 1');
            if ($lookup) {
                $lookup->bind_param('i', $id);
                if ($lookup->execute()) {
                    $lookup->bind_result($user_id);
                    $lookup->fetch();
                }
                $lookup->close();
            }

            $stmt = $conn->prepare('DELETE FROM coordinators WHERE id = ?');
            if (!$stmt) {
                throw new Exception('Delete failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                throw new Exception('Delete failed: ' . $stmt->error);
            }
            $stmt->close();

            if ((int)$user_id > 0) {
                $del_user = $conn->prepare('DELETE FROM users WHERE id = ?');
                if ($del_user) {
                    $del_user->bind_param('i', $user_id);
                    $del_user->execute();
                    $del_user->close();
                }
            }

            $conn->commit();
            $message = 'Coordinator deleted successfully.';
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$rows = [];
$sql = "
    SELECT c.*, u.name AS user_name, d.name AS department_name
    FROM coordinators c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE c.deleted_at IS NULL
    ORDER BY c.id DESC
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}

$page_title = 'Coordinators';
include 'includes/header.php';
?>
<style>
    .coordinator-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .coordinator-actions form {
        margin: 0;
    }
    .coordinator-actions .btn {
        min-width: 70px;
    }
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Coordinators</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item">Coordinators</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="coordinators-create.php" class="btn btn-primary">Create Coordinator</a>
    </div>
</div>

<div class="main-content">
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo h($message_type); ?> py-2"><?php echo h($message); ?></div>
    <?php endif; ?>

    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Coordinators</h5>
            <span class="badge bg-primary text-white px-3 py-1" style="font-weight:600;"><?php echo count($rows); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Office</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No coordinators found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><?php echo h(trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></td>
                                <td><?php echo h($r['user_name'] ?? '-'); ?></td>
                                <td><?php echo h($r['email'] ?? '-'); ?></td>
                                <td><?php echo h($r['phone'] ?? '-'); ?></td>
                                <td><?php echo h($r['department_name'] ?? '-'); ?></td>
                                <td><?php echo h($r['office_location'] ?? '-'); ?></td>
                                <td>
                                    <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="coordinator-actions">
                                        <a href="coordinators-edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this coordinator?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
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
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php';
$conn->close();
?>

