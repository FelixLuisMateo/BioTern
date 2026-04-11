<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$message = '';
$message_type = 'success';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE coordinators SET deleted_at = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = 'Coordinator deleted successfully.';
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
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Coordinators</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
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

    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Coordinators</h5>
            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($rows); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive app-data-table-wrap">
                <table class="table table-hover mb-0 app-data-table app-academic-list-table">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No coordinators found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><span class="app-academic-id-pill"><?php echo (int)$r['id']; ?></span></td>
                                <td>
                                    <div class="app-academic-name-cell">
                                        <span class="app-academic-name"><?php echo h(trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></span>
                                        <span class="app-academic-meta"><?php echo h($r['email'] ?? '-'); ?></span>
                                    </div>
                                </td>
                                <td><span class="app-academic-created"><?php echo h($r['user_name'] ?? '-'); ?></span></td>
                                <td><span class="app-academic-created"><?php echo h($r['email'] ?? '-'); ?></span></td>
                                <td><span class="app-academic-created"><?php echo h($r['phone'] ?? '-'); ?></span></td>
                                <td><span class="app-academic-head"><?php echo h($r['department_name'] ?? '-'); ?></span></td>
                                <td><span class="app-academic-created"><?php echo h($r['office_location'] ?? '-'); ?></span></td>
                                <td>
                                    <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                        <span class="app-academic-status-pill is-active">Active</span>
                                    <?php else: ?>
                                        <span class="app-academic-status-pill is-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-cell">
                                    <div class="d-flex gap-2">
                                        <a href="coordinators-edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                        <form method="post" data-confirm-message="Delete this coordinator?">
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

</div> <!-- .nxl-content -->
</main>
<?php
include 'includes/footer.php';
$conn->close();
?>




