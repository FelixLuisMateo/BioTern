<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$courseColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM courses");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $courseColumns[] = strtolower((string)$column['Field']);
    }
}

$hasColumn = function ($columnName) use ($courseColumns) {
    return in_array(strtolower($columnName), $courseColumns, true);
};

$selectFields = ['id', 'name', 'code'];
if ($hasColumn('course_head')) {
    $selectFields[] = 'course_head';
}
if ($hasColumn('description')) {
    $selectFields[] = 'description';
}
if ($hasColumn('total_ojt_hours')) {
    $selectFields[] = 'total_ojt_hours';
}
if ($hasColumn('is_active')) {
    $selectFields[] = 'is_active';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$whereClause = $hasColumn('deleted_at') ? " WHERE deleted_at IS NULL" : "";
$orderBy = $hasColumn('created_at') ? "created_at DESC" : "id DESC";

$courses = [];
$listQuery = "SELECT " . implode(', ', $selectFields) . " FROM courses" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 200";
$listResult = $conn->query($listQuery);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $courses[] = $row;
    }
}

$colCount = count($selectFields) + 1; // +1 for Actions column

$page_title = 'Courses';
$page_styles = [
    'assets/css/modules/management/management-courses.css'
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Courses</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Courses</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="courses-create.php" class="btn btn-primary">
            <i class="feather-plus me-2"></i>
            <span>Create Course</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card app-mobile-inline-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Courses</h5>
            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($courses); ?> total</span>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive app-data-table-wrap">
                <table class="table table-hover mb-0 courses-table app-data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php if ($hasColumn('course_head')): ?><th>Course Head</th><?php endif; ?>
                            <?php if ($hasColumn('total_ojt_hours')): ?><th>Total OJT Hours</th><?php endif; ?>
                            <?php if ($hasColumn('is_active')): ?><th>Status</th><?php endif; ?>
                            <?php if ($hasColumn('created_at')): ?><th>Created</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($courses)): ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><span class="app-courses-id-pill"><?php echo (int)$course['id']; ?></span></td>
                                <td>
                                    <span class="app-courses-code-pill"><?php echo htmlspecialchars((string)($course['code'] ?? '')); ?></span>
                                </td>
                                <td>
                                    <div class="app-courses-name-cell">
                                        <span class="app-courses-name"><?php echo htmlspecialchars((string)($course['name'] ?? '')); ?></span>
                                        <?php if ($hasColumn('total_ojt_hours')): ?>
                                            <span class="app-courses-meta"><?php echo htmlspecialchars((string)($course['total_ojt_hours'] ?? '-')); ?> OJT hrs</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ($hasColumn('course_head')): ?>
                                    <td>
                                        <span class="app-courses-head"><?php echo htmlspecialchars((string)($course['course_head'] ?? '-')); ?></span>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasColumn('total_ojt_hours')): ?>
                                    <td><strong><?php echo htmlspecialchars((string)($course['total_ojt_hours'] ?? '-')); ?></strong></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('is_active')): ?>
                                    <td>
                                        <?php if ((string)($course['is_active'] ?? '0') === '1'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasColumn('created_at')): ?>
                                    <td><span class="app-courses-created"><?php echo htmlspecialchars((string)($course['created_at'] ?? '-')); ?></span></td>
                                <?php endif; ?>
                                <td class="action-cell">
                                    <a href="courses-edit.php?id=<?php echo (int)$course['id']; ?>" class="btn btn-sm btn-outline-primary app-courses-edit-btn">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $colCount; ?>" class="text-center py-4 text-muted">No courses found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="app-mobile-list app-ojt-mobile-list">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <details class="app-mobile-item app-ojt-mobile-item">
                            <summary class="app-mobile-summary app-ojt-mobile-summary">
                                <div class="app-mobile-summary-main app-ojt-mobile-summary-main">
                                    <div class="app-mobile-summary-text app-ojt-mobile-summary-text">
                                        <span class="app-mobile-name app-ojt-mobile-name"><?php echo htmlspecialchars((string)($course['name'] ?? '')); ?></span>
                                        <span class="app-mobile-subtext app-ojt-mobile-subtext">Code: <?php echo htmlspecialchars((string)($course['code'] ?? '-')); ?></span>
                                    </div>
                                </div>
                                <span class="app-ojt-mobile-status-dot status-review" aria-hidden="true"></span>
                            </summary>
                            <div class="app-mobile-details app-ojt-mobile-details">
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">ID</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo (int)$course['id']; ?></span>
                                </div>
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">Code</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($course['code'] ?? '-')); ?></span>
                                </div>
                                <?php if ($hasColumn('course_head')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Course Head</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($course['course_head'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasColumn('total_ojt_hours')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">OJT Hours</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($course['total_ojt_hours'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasColumn('is_active')): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Status</span>
                                        <span class="app-mobile-value app-ojt-mobile-value">
                                            <?php if ((string)($course['is_active'] ?? '0') === '1'): ?>
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
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($course['created_at'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="app-mobile-row app-mobile-row-stack app-ojt-mobile-row app-ojt-mobile-row-stack">
                                    <span class="app-mobile-label app-ojt-mobile-label">Actions</span>
                                    <div class="app-ojt-mobile-actions">
                                        <a href="courses-edit.php?id=<?php echo (int)$course['id']; ?>" class="btn btn-sm btn-outline-primary app-courses-edit-btn">Edit</a>
                                    </div>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="app-data-empty">No courses found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
    </div> <!-- .nxl-content -->
</main>
<?php
include 'includes/footer.php';




