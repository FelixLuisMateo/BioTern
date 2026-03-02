<?php
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$message = '';
$message_type = 'success';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE supervisors SET deleted_at = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = 'Supervisor deleted successfully.';
            } else {
                $message = 'Delete failed: ' . $stmt->error;
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

$rows = [];
$sql = "
    SELECT s.*, u.name AS user_name, d.name AS department_name
    FROM supervisors s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.deleted_at IS NULL
    ORDER BY s.id DESC
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}

$page_title = 'Supervisors';
include 'includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Supervisors</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item">Supervisors</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="supervisors-create.php" class="btn btn-primary">Create Supervisor</a>
    </div>
</div>

<div class="main-content">
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo h($message_type); ?> py-2"><?php echo h($message); ?></div>
    <?php endif; ?>

    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Supervisors</h5>
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
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No supervisors found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><?php echo h(trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></td>
                                <td><?php echo h($r['user_name'] ?? '-'); ?></td>
                                <td><?php echo h($r['email'] ?? '-'); ?></td>
                                <td><?php echo h($r['phone'] ?? '-'); ?></td>
                                <td><?php echo h($r['department_name'] ?? '-'); ?></td>
                                <td><?php echo h($r['specialization'] ?? '-'); ?></td>
                                <td>
                                    <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="supervisors-edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this supervisor?');">
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
include 'includes/footer.php';
$conn->close();
?>
