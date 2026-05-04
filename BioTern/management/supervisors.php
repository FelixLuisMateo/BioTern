<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
/** @var mysqli $conn */

require_roles_page(['admin']);

$message = '';
$message_type = 'success';

biotern_ensure_table_column($conn, 'supervisors', 'office_location', 'VARCHAR(255) DEFAULT NULL');

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
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/management/management-supervisors.css']);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Supervisors</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
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

    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card app-mobile-inline-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Supervisors</h5>
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
                            <th>Specialization</th>
                            <th>Office</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">No supervisors found.</td></tr>
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
                                <td><span class="app-academic-created"><?php echo h($r['specialization'] ?? '-'); ?></span></td>
                                <td><span class="app-academic-created"><?php echo h($r['office_location'] ?? ($r['office'] ?? '-')); ?></span></td>
                                <td>
                                    <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                        <span class="app-academic-status-pill is-active">Active</span>
                                    <?php else: ?>
                                        <span class="app-academic-status-pill is-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-cell">
                                    <div class="d-flex gap-2">
                                        <a href="supervisors-edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                        <form method="post" data-confirm-message="Delete this supervisor?">
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
            <div class="app-mobile-list app-ojt-mobile-list">
                <?php if (!$rows): ?>
                    <div class="app-ojt-mobile-empty text-muted">No supervisors found.</div>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $fullName = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['middle_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
                        $email = (string)($r['email'] ?? '-');
                        $phone = (string)($r['phone'] ?? '-');
                        $department = (string)($r['department_name'] ?? '-');
                        $specialization = (string)($r['specialization'] ?? '-');
                        $office = (string)($r['office_location'] ?? ($r['office'] ?? '-'));
                        $statusLabel = (int)($r['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive';
                        $statusClass = (int)($r['is_active'] ?? 0) === 1 ? 'status-active' : 'status-inactive';
                        ?>
                        <details class="app-mobile-item app-ojt-mobile-item">
                            <summary class="app-mobile-summary app-ojt-mobile-summary">
                                <div class="app-mobile-summary-main app-ojt-mobile-summary-main">
                                    <div class="app-mobile-summary-text app-ojt-mobile-summary-text">
                                        <span class="app-mobile-name app-ojt-mobile-name"><?php echo h($fullName !== '' ? $fullName : '-'); ?></span>
                                        <span class="app-mobile-subtext app-ojt-mobile-subtext">ID: <?php echo (int)($r['id'] ?? 0); ?> | <?php echo h($department); ?></span>
                                    </div>
                                </div>
                                <span class="app-ojt-mobile-status-dot <?php echo h($statusClass); ?>" aria-hidden="true"></span>
                            </summary>
                            <div class="app-mobile-details app-ojt-mobile-details">
                                <div class="app-mobile-row app-ojt-mobile-row"><span class="app-mobile-label app-ojt-mobile-label">User</span><span class="app-mobile-value app-ojt-mobile-value"><?php echo h((string)($r['user_name'] ?? '-')); ?></span></div>
                                <div class="app-mobile-row app-ojt-mobile-row"><span class="app-mobile-label app-ojt-mobile-label">Email</span><span class="app-mobile-value app-ojt-mobile-value"><?php echo h($email); ?></span></div>
                                <div class="app-mobile-row app-ojt-mobile-row"><span class="app-mobile-label app-ojt-mobile-label">Phone</span><span class="app-mobile-value app-ojt-mobile-value"><?php echo h($phone); ?></span></div>
                                <div class="app-mobile-row app-ojt-mobile-row"><span class="app-mobile-label app-ojt-mobile-label">Specialization</span><span class="app-mobile-value app-ojt-mobile-value"><?php echo h($specialization); ?></span></div>
                                <div class="app-mobile-row app-ojt-mobile-row"><span class="app-mobile-label app-ojt-mobile-label">Office</span><span class="app-mobile-value app-ojt-mobile-value"><?php echo h($office); ?></span></div>
                                <div class="app-mobile-row app-ojt-mobile-row"><span class="app-mobile-label app-ojt-mobile-label">Status</span><span class="app-mobile-value app-ojt-mobile-value"><?php echo h($statusLabel); ?></span></div>
                                <div class="app-mobile-row app-mobile-row-stack app-ojt-mobile-row app-ojt-mobile-row-stack">
                                    <span class="app-mobile-label app-ojt-mobile-label">Actions</span>
                                    <div class="app-ojt-mobile-actions d-flex gap-2">
                                        <a href="supervisors-edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" data-confirm-message="Delete this supervisor?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
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




