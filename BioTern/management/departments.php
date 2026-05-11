<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
/** @var mysqli $conn */

require_roles_page(['admin']);

$deptColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM departments");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $deptColumns[] = strtolower((string)$column['Field']);
    }
}

$hasColumn = function ($columnName) use ($deptColumns) {
    return in_array(strtolower($columnName), $deptColumns, true);
};

if (!$hasColumn('location')) {
    @$conn->query("ALTER TABLE departments ADD COLUMN location VARCHAR(255) NULL AFTER code");
    $deptColumns[] = 'location';
}

$selectFields = ['id', 'name', 'code'];
if ($hasColumn('location')) {
    $selectFields[] = 'location';
}
if ($hasColumn('department_head')) {
    $selectFields[] = 'department_head';
}
if ($hasColumn('is_active')) {
    $selectFields[] = 'is_active';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$whereClause = $hasColumn('deleted_at') ? " WHERE deleted_at IS NULL" : "";
$orderBy = $hasColumn('created_at') ? "created_at DESC" : "id DESC";

$departments = [];
$listQuery = "SELECT " . implode(', ', $selectFields) . " FROM departments" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 200";
$listResult = $conn->query($listQuery);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

$departmentSupervisors = [];
$supRes = $conn->query("
    SELECT department_id, GROUP_CONCAT(TRIM(CONCAT(first_name, ' ', last_name)) ORDER BY first_name, last_name SEPARATOR ', ') AS supervisor_names
    FROM supervisors
    WHERE deleted_at IS NULL AND department_id IS NOT NULL
    GROUP BY department_id
");
if ($supRes) {
    while ($row = $supRes->fetch_assoc()) {
        $departmentSupervisors[(int)($row['department_id'] ?? 0)] = (string)($row['supervisor_names'] ?? '');
    }
}

// set title for header include
$page_title = 'Departments';

	include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Departments</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Departments</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="departments-create.php" class="btn btn-primary">
            <i class="feather-plus me-2"></i>
            <span>Create Department</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card app-mobile-inline-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Departments</h5>
            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($departments); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive app-data-table-wrap">
                <table class="table table-hover mb-0 app-data-table app-academic-list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php if ($hasColumn('location')): ?><th>Location</th><?php endif; ?>
                            <?php if ($hasColumn('department_head')): ?><th>Department Head</th><?php endif; ?>
                            <th>Supervisors</th>
                            <?php if ($hasColumn('is_active')): ?><th>Status</th><?php endif; ?>
                            <?php if ($hasColumn('created_at')): ?><th>Created</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><span class="app-academic-id-pill"><?php echo (int)$dept['id']; ?></span></td>
                                <td><span class="app-academic-code-pill"><?php echo htmlspecialchars((string)($dept['code'] ?? '')); ?></span></td>
                                <td>
                                    <div class="app-academic-name-cell">
                                        <span class="app-academic-name"><?php echo htmlspecialchars((string)($dept['name'] ?? '')); ?></span>
                                    </div>
                                </td>
                                <?php if ($hasColumn('location')): ?>
                                    <td><span class="app-academic-created"><?php echo htmlspecialchars((string)($dept['location'] ?? '-')); ?></span></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('department_head')): ?>
                                    <td><span class="app-academic-head"><?php echo htmlspecialchars((string)($dept['department_head'] ?? '-')); ?></span></td>
                                <?php endif; ?>
                                <td><span class="app-academic-created"><?php echo htmlspecialchars($departmentSupervisors[(int)$dept['id']] ?? '-'); ?></span></td>
                                <?php if ($hasColumn('is_active')): ?>
                                    <td>
                                        <?php if ((string)($dept['is_active'] ?? '0') === '1'): ?>
                                            <span class="app-academic-status-pill is-active">Active</span>
                                        <?php else: ?>
                                            <span class="app-academic-status-pill is-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasColumn('created_at')): ?>
                                    <td><span class="app-academic-created"><?php echo htmlspecialchars((string)($dept['created_at'] ?? '-')); ?></span></td>
                                <?php endif; ?>
                                <td class="action-cell">
                                    <a href="departments-edit.php?id=<?php echo (int)$dept['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo 5 + ($hasColumn('location') ? 1 : 0) + ($hasColumn('department_head') ? 1 : 0) + ($hasColumn('is_active') ? 1 : 0) + ($hasColumn('created_at') ? 1 : 0); ?>" class="text-center py-4 text-muted">No departments found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="app-mobile-list app-ojt-mobile-list">
                <?php if (!empty($departments)): ?>
                    <?php foreach ($departments as $dept): ?>
                        <details class="app-mobile-item app-ojt-mobile-item">
                            <summary class="app-mobile-summary app-ojt-mobile-summary">
                                <div class="app-mobile-summary-main app-ojt-mobile-summary-main">
                                    <div class="app-mobile-summary-text app-ojt-mobile-summary-text">
                                        <span class="app-mobile-name app-ojt-mobile-name"><?php echo htmlspecialchars((string)($dept['name'] ?? '')); ?></span>
                                        <span class="app-mobile-subtext app-ojt-mobile-subtext">Code: <?php echo htmlspecialchars((string)($dept['code'] ?? '-')); ?></span>
                                    </div>
                                </div>
                                <?php $summary_status_class = ($hasColumn('is_active') && (string)($dept['is_active'] ?? '0') === '1') ? 'status-active' : 'status-review'; ?>
                                <span class="app-ojt-mobile-status-dot <?php echo $summary_status_class; ?>" aria-hidden="true"></span>
                            </summary>
                            <div class="app-mobile-details app-ojt-mobile-details">
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">ID</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo (int)$dept['id']; ?></span>
                                </div>
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">Code</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($dept['code'] ?? '-')); ?></span>
                                </div>
                                <?php if ($hasColumn('location')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Location</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($dept['location'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasColumn('department_head')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Department Head</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($dept['department_head'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">Supervisors</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars($departmentSupervisors[(int)$dept['id']] ?? '-'); ?></span>
                                </div>
                                <?php if ($hasColumn('is_active')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Status</span>
                                        <span class="app-mobile-value app-ojt-mobile-value">
                                            <?php if ((string)($dept['is_active'] ?? '0') === '1'): ?>
                                                <span class="app-academic-status-pill is-active">Active</span>
                                            <?php else: ?>
                                                <span class="app-academic-status-pill is-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasColumn('created_at')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Created</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($dept['created_at'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="app-mobile-row app-mobile-row-stack app-ojt-mobile-row app-ojt-mobile-row-stack">
                                    <span class="app-mobile-label app-ojt-mobile-label">Actions</span>
                                    <div class="app-ojt-mobile-actions">
                                        <a href="departments-edit.php?id=<?php echo (int)$dept['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                    </div>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="app-data-empty">No departments found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
    </div> <!-- .nxl-content -->
</main>
<?php
include 'includes/footer.php';



