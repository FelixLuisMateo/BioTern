<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$conn->query("CREATE TABLE IF NOT EXISTS school_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year VARCHAR(20) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$flash_message = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_school_year'])) {
        $year = trim((string)($_POST['year'] ?? ''));
        if (preg_match('/^\d{4}\s*-\s*\d{4}$/', $year)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO school_years (year) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param('s', $year);
                $stmt->execute();
                $stmt->close();
                $flash_message = 'School year added.';
            }
        } else {
            $flash_type = 'danger';
            $flash_message = 'Use format YYYY-YYYY (example: 2025-2026).';
        }
    }

    if (isset($_POST['update_school_year'])) {
        $id = (int)($_POST['id'] ?? 0);
        $year = trim((string)($_POST['year'] ?? ''));
        if ($id > 0 && preg_match('/^\d{4}\s*-\s*\d{4}$/', $year)) {
            $stmt = $conn->prepare("UPDATE school_years SET year = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $year, $id);
                $stmt->execute();
                $stmt->close();
                $flash_message = 'School year updated.';
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM school_years WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $flash_message = 'School year deleted.';
        }
    }
}

$editing_id = (int)($_GET['edit'] ?? 0);
$editing_year = '';
if ($editing_id > 0) {
    $stmt = $conn->prepare("SELECT year FROM school_years WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $editing_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $editing_year = (string)($row['year'] ?? '');
        $stmt->close();
    }
}

$school_years = [];
$res = $conn->query("SELECT id, year, created_at FROM school_years ORDER BY year DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $school_years[] = $row;
    }
}

$page_title = 'School Years';
$page_scripts = array('assets/js/modules/management/school-years-runtime.js');
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">School Years</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">School Years</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <?php if ($flash_message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mb-3"><?php echo htmlspecialchars($flash_message); ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="id" value="<?php echo (int)$editing_id; ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">School Year</label>
                    <input type="text" name="year" class="form-control" required placeholder="2025-2026" value="<?php echo htmlspecialchars($editing_year); ?>">
                </div>
                <div class="col-12 col-md-8 d-flex gap-2">
                    <?php if ($editing_id > 0): ?>
                        <button type="submit" name="update_school_year" value="1" class="btn btn-primary">Update</button>
                        <a href="school_years.php" class="btn btn-light">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_school_year" value="1" class="btn btn-primary">Add School Year</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card app-data-card app-data-toolbar app-academic-list-card app-mobile-inline-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All School Years</h5>
            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($school_years); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive app-data-table-wrap">
                <table class="table table-hover mb-0 app-data-table app-academic-list-table">
                    <thead>
                        <tr><th>ID</th><th>Year</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($school_years)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No school years found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($school_years as $sy): ?>
                                <tr>
                                    <td><span class="app-academic-id-pill"><?php echo (int)$sy['id']; ?></span></td>
                                    <td>
                                        <div class="app-academic-name-cell">
                                            <span class="app-academic-name"><?php echo htmlspecialchars((string)$sy['year']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="app-academic-created"><?php echo htmlspecialchars((string)($sy['created_at'] ?? '-')); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                        <a href="school_years.php?edit=<?php echo (int)$sy['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                        <a href="school_years.php?delete=<?php echo (int)$sy['id']; ?>" class="btn btn-sm btn-outline-danger js-confirm-action" data-confirm="Delete this school year?">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="app-mobile-list app-ojt-mobile-list">
                <?php if (empty($school_years)): ?>
                    <div class="app-data-empty">No school years found.</div>
                <?php else: ?>
                    <?php foreach ($school_years as $sy): ?>
                        <details class="app-mobile-item app-ojt-mobile-item">
                            <summary class="app-mobile-summary app-ojt-mobile-summary">
                                <div class="app-mobile-summary-main app-ojt-mobile-summary-main">
                                    <div class="app-mobile-summary-text app-ojt-mobile-summary-text">
                                        <span class="app-mobile-name app-ojt-mobile-name"><?php echo htmlspecialchars((string)$sy['year']); ?></span>
                                        <span class="app-mobile-subtext app-ojt-mobile-subtext">ID: <?php echo (int)$sy['id']; ?></span>
                                    </div>
                                </div>
                                <span class="app-ojt-mobile-status-dot status-review" aria-hidden="true"></span>
                            </summary>
                            <div class="app-mobile-details app-ojt-mobile-details">
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">Created</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($sy['created_at'] ?? '-')); ?></span>
                                </div>
                                <div class="app-mobile-row app-mobile-row-stack app-ojt-mobile-row app-ojt-mobile-row-stack">
                                    <span class="app-mobile-label app-ojt-mobile-label">Actions</span>
                                    <div class="app-ojt-mobile-actions">
                                        <a href="school_years.php?edit=<?php echo (int)$sy['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                        <a href="school_years.php?delete=<?php echo (int)$sy['id']; ?>" class="btn btn-sm btn-outline-danger js-confirm-action" data-confirm="Delete this school year?">Delete</a>
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
<?php include 'includes/footer.php'; ?>






