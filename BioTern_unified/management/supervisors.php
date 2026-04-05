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

$message = '';
$message_type = 'success';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function supervisor_office_value(array $row): string
{
    foreach (['office', 'specialization', 'office_location'] as $field) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->begin_transaction();
        try {
            $user_id = 0;
            $lookup = $conn->prepare('SELECT user_id FROM supervisors WHERE id = ? LIMIT 1');
            if ($lookup) {
                $lookup->bind_param('i', $id);
                if ($lookup->execute()) {
                    $lookup->bind_result($user_id);
                    $lookup->fetch();
                }
                $lookup->close();
            }

            $stmt = $conn->prepare('DELETE FROM supervisors WHERE id = ?');
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
            $message = 'Supervisor deleted successfully.';
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$rows = [];
$table_check = $conn->query("SHOW TABLES LIKE 'supervisors'");
if (!$table_check || $table_check->num_rows === 0) {
    $message = 'Supervisors table is missing. Import your database or run the migrations first.';
    $message_type = 'danger';
} else {
    $sql = "
        SELECT s.*, u.name AS user_name, u.username AS user_username, d.name AS department_name
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
}

$page_title = 'Supervisors';
include 'includes/header.php';
?>
<style>
    .supervisors-shell .page-subtitle {
        font-size: 12px;
        color: #6c7a92;
        margin: 0;
        line-height: 1.45;
        max-width: 72ch;
    }
    .supervisors-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        margin-bottom: 1rem;
        border: 1px solid #dfe8f5;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }
    .supervisors-toolbar-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .supervisor-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .supervisor-actions form {
        margin: 0;
    }
    .supervisor-actions .btn {
        min-width: 70px;
    }
    .supervisors-table-wrap table {
        width: 100%;
    }
    .supervisors-table-wrap th,
    .supervisors-table-wrap td {
        vertical-align: middle;
    }
    .supervisors-table-wrap td:last-child {
        white-space: nowrap;
    }
    .supervisors-table-wrap .contact-link {
        color: inherit;
        text-decoration: none;
    }
    .supervisors-table-wrap .contact-link:hover {
        text-decoration: underline;
    }
    html.app-skin-dark .supervisors-toolbar,
    html.app-skin-dark .supervisors-shell .card.stretch.stretch-full {
        border-color: #253252;
        background: #111a2e;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
    }
    html.app-skin-dark .supervisors-shell .page-subtitle {
        color: #99abc8;
    }
    @media (max-width: 991.98px) {
        .supervisors-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }
        .supervisors-toolbar-actions {
            width: 100%;
            justify-content: stretch;
        }
        .supervisors-toolbar-actions .btn {
            width: 100%;
        }
    }
</style>
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
</div>

<div class="main-content supervisors-shell">
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo h($message_type); ?> py-2"><?php echo h($message); ?></div>
    <?php endif; ?>

    <div class="supervisors-toolbar">
        <p class="page-subtitle">Manage supervisor accounts, department assignments, and active access status in one place.</p>
        <div class="supervisors-toolbar-actions">
            <span class="badge bg-primary text-white px-3 py-2" style="font-weight:600;"><?php echo count($rows); ?> total</span>
            <a href="supervisors-create.php" class="btn btn-primary">Create Supervisor</a>
        </div>
    </div>

    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Supervisors</h5>
            <span class="text-muted small">Directory and account status</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive supervisors-table-wrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
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
                            <tr><td colspan="9" class="text-center py-4 text-muted">No supervisors found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><?php echo h(trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></td>
                                <td><?php echo h($r['user_username'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!empty($r['email'])): ?>
                                        <a class="contact-link" href="mailto:<?php echo h($r['email']); ?>"><?php echo h($r['email']); ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['phone'])): ?>
                                        <a class="contact-link" href="tel:<?php echo h($r['phone']); ?>"><?php echo h($r['phone']); ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($r['department_name'] ?? '-'); ?></td>
                                <td><?php echo h(supervisor_office_value($r) ?: '-'); ?></td>
                                <td>
                                    <?php if ((int)($r['is_active'] ?? 0) === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="supervisor-actions">
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


