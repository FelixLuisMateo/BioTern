<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

function rep_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rep_format_datetime(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($raw);
    return $ts ? date('M d, Y h:i A', $ts) : $raw;
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
    $statusFilter = 'all';
}

$sourceFilter = strtolower(trim((string)($_GET['source'] ?? 'all')));
if (!in_array($sourceFilter, ['all', 'users', 'staging'], true)) {
    $sourceFilter = 'all';
}

$records = [];

$usersHasDisciplinary = false;
$usersHasStatus = false;
$checkUsersCols = $conn->query("SHOW COLUMNS FROM users");
if ($checkUsersCols instanceof mysqli_result) {
    while ($col = $checkUsersCols->fetch_assoc()) {
        $field = strtolower((string)($col['Field'] ?? ''));
        if ($field === 'disciplinary_remark') {
            $usersHasDisciplinary = true;
        }
        if ($field === 'application_status') {
            $usersHasStatus = true;
        }
    }
    $checkUsersCols->close();
}

if ($usersHasDisciplinary && ($sourceFilter === 'all' || $sourceFilter === 'users')) {
    $where = ["u.role = 'student'", "TRIM(COALESCE(u.disciplinary_remark, '')) <> ''"];
    if ($statusFilter !== 'all' && $usersHasStatus) {
        $where[] = "COALESCE(u.application_status, 'approved') = '" . $conn->real_escape_string($statusFilter) . "'";
    }

    $sql = "
        SELECT
            'users' AS source,
            u.id AS user_id,
            COALESCE(s.student_id, '-') AS student_number,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)), ''), NULLIF(TRIM(u.name), ''), u.username, u.email) AS student_name,
            COALESCE(u.application_status, 'approved') AS application_status,
            u.disciplinary_remark,
            u.approval_notes,
            u.rejected_at,
            u.approved_at,
            u.application_submitted_at,
            rv.name AS reviewed_by_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id
        LEFT JOIN users rv ON rv.id = u.approved_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(u.rejected_at, u.approved_at, u.application_submitted_at, u.created_at) DESC, u.id DESC
    ";

    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $records[] = [
                'source' => 'users',
                'student_number' => (string)($row['student_number'] ?? '-'),
                'student_name' => (string)($row['student_name'] ?? '-'),
                'status' => strtolower((string)($row['application_status'] ?? 'approved')),
                'disciplinary_remark' => (string)($row['disciplinary_remark'] ?? ''),
                'approval_notes' => (string)($row['approval_notes'] ?? ''),
                'reviewed_by_name' => (string)($row['reviewed_by_name'] ?? '-'),
                'happened_at' => (string)($row['rejected_at'] ?? $row['approved_at'] ?? $row['application_submitted_at'] ?? ''),
            ];
        }
        $res->close();
    }
}

if (table_exists($conn, 'student_applications') && ($sourceFilter === 'all' || $sourceFilter === 'staging')) {
    $where = ["TRIM(COALESCE(sa.disciplinary_remark, '')) <> ''"];
    if ($statusFilter !== 'all') {
        $where[] = "COALESCE(sa.status, 'pending') = '" . $conn->real_escape_string($statusFilter) . "'";
    }

    $sql = "
        SELECT
            sa.student_id,
            sa.first_name,
            sa.middle_name,
            sa.last_name,
            sa.status,
            sa.disciplinary_remark,
            sa.approval_notes,
            sa.reviewed_at,
            reviewer.name AS reviewed_by_name
        FROM student_applications sa
        LEFT JOIN users reviewer ON reviewer.id = sa.reviewed_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(sa.reviewed_at, sa.submitted_at, sa.created_at) DESC, sa.id DESC
    ";

    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $records[] = [
                'source' => 'staging',
                'student_number' => (string)($row['student_id'] ?? '-'),
                'student_name' => trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['middle_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')),
                'status' => strtolower((string)($row['status'] ?? 'pending')),
                'disciplinary_remark' => (string)($row['disciplinary_remark'] ?? ''),
                'approval_notes' => (string)($row['approval_notes'] ?? ''),
                'reviewed_by_name' => (string)($row['reviewed_by_name'] ?? '-'),
                'happened_at' => (string)($row['reviewed_at'] ?? ''),
            ];
        }
        $res->close();
    }
}

usort($records, static function (array $a, array $b): int {
    return strcmp((string)($b['happened_at'] ?? ''), (string)($a['happened_at'] ?? ''));
});

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css', 'assets/css/modules/reports/reports-login-logs-page.css']);
$page_scripts = array_merge($page_scripts ?? [], ['assets/js/modules/reports/reports-login-logs-page.js', 'assets/js/modules/reports/reports-shell-runtime.js']);
$page_title = 'BioTern || Disciplinary Acts Report';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Disciplinary Acts</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                <li class="breadcrumb-item">Disciplinary Acts</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items d-flex">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <button type="button" class="btn btn-light-brand js-print-report"><i class="feather-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>All</option>
                        <option value="pending"<?php echo $statusFilter === 'pending' ? ' selected' : ''; ?>>Pending</option>
                        <option value="approved"<?php echo $statusFilter === 'approved' ? ' selected' : ''; ?>>Approved</option>
                        <option value="rejected"<?php echo $statusFilter === 'rejected' ? ' selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select class="form-select" name="source">
                        <option value="all"<?php echo $sourceFilter === 'all' ? ' selected' : ''; ?>>All Sources</option>
                        <option value="users"<?php echo $sourceFilter === 'users' ? ' selected' : ''; ?>>Users Table</option>
                        <option value="staging"<?php echo $sourceFilter === 'staging' ? ' selected' : ''; ?>>Application Staging</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="reports-disciplinary-acts.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Disciplinary Remarks</span>
            <span class="badge bg-soft-danger text-danger"><?php echo count($records); ?> record(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 logs-mobile-table" data-mobile-collapse="true" data-mobile-visible-cells="3">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Disciplinary Remark</th>
                            <th>Approval Note</th>
                            <th>Reviewed By</th>
                            <th>Date</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records === []): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No disciplinary records found for the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $row): ?>
                                <?php
                                $status = strtolower((string)($row['status'] ?? 'pending'));
                                $statusClass = 'secondary';
                                if ($status === 'approved') $statusClass = 'success';
                                if ($status === 'rejected') $statusClass = 'danger';
                                if ($status === 'pending') $statusClass = 'warning';
                                ?>
                                <tr>
                                    <td data-label="Student">
                                        <div class="fw-semibold"><?php echo rep_h($row['student_name'] !== '' ? $row['student_name'] : '-'); ?></div>
                                        <small class="text-muted">ID: <?php echo rep_h($row['student_number']); ?></small>
                                    </td>
                                    <td data-label="Status"><span class="badge bg-soft-<?php echo rep_h($statusClass); ?> text-<?php echo rep_h($statusClass); ?> text-capitalize"><?php echo rep_h($status); ?></span></td>
                                    <td data-label="Disciplinary Remark"><?php echo rep_h($row['disciplinary_remark']); ?></td>
                                    <td data-label="Approval Note"><?php echo rep_h(trim((string)$row['approval_notes']) !== '' ? $row['approval_notes'] : '-'); ?></td>
                                    <td data-label="Reviewed By"><?php echo rep_h(trim((string)$row['reviewed_by_name']) !== '' ? $row['reviewed_by_name'] : '-'); ?></td>
                                    <td data-label="Date"><?php echo rep_h(rep_format_datetime((string)$row['happened_at'])); ?></td>
                                    <td data-label="Source"><span class="badge bg-soft-primary text-primary"><?php echo rep_h(strtoupper((string)$row['source'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>
