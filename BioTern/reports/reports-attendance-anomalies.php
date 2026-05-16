<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/tools/biometric_ops.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$isAdmin = $role === 'admin';

function anomalies_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function anomalies_choice(string $key, array $allowed, string $default): string
{
    $value = strtolower(trim((string)($_GET[$key] ?? $default)));
    return in_array($value, $allowed, true) ? $value : $default;
}

function anomalies_date(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function anomalies_person_label(array $row): string
{
    $studentName = trim((string)($row['student_first_name'] ?? '') . ' ' . (string)($row['student_last_name'] ?? ''));
    if ($studentName !== '') {
        $number = trim((string)($row['student_number'] ?? ''));
        return $studentName . ($number !== '' ? ' (' . $number . ')' : '');
    }
    $userName = trim((string)($row['mapped_user_name'] ?? ''));
    if ($userName !== '') {
        return $userName;
    }
    $username = trim((string)($row['mapped_username'] ?? ''));
    return $username !== '' ? $username : 'No BioTern match';
}

biometric_ops_ensure_tables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = strtolower(trim((string)($_POST['anomaly_action'] ?? '')));
    $id = max(0, (int)($_POST['anomaly_id'] ?? 0));
    $status = $action === 'resolve' ? 'resolved' : ($action === 'dismiss' ? 'dismissed' : '');
    if ($id > 0 && $status !== '') {
        $stmt = $conn->prepare("UPDATE biometric_anomalies SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $stmt->close();
        }
        biometric_ops_log_audit($conn, (int)($_SESSION['user_id'] ?? 0), $role, 'attendance_anomaly_' . $status, 'biometric_anomaly', (string)$id);
    }
    header('Location: reports-attendance-anomalies.php?' . http_build_query($_GET));
    exit;
}

$status = anomalies_choice('status', ['all', 'open', 'resolved', 'dismissed'], 'open');
$severity = anomalies_choice('severity', ['all', 'info', 'warning', 'critical'], 'all');
$from = anomalies_date('from');
$to = anomalies_date('to');
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = [];
if ($status !== 'all') {
    $where[] = "a.status = '" . $conn->real_escape_string($status) . "'";
}
if ($severity !== 'all') {
    $where[] = "a.severity = '" . $conn->real_escape_string($severity) . "'";
}
if ($from !== '') {
    $where[] = "DATE(COALESCE(a.event_time, a.created_at)) >= '" . $conn->real_escape_string($from) . "'";
}
if ($to !== '') {
    $where[] = "DATE(COALESCE(a.event_time, a.created_at)) <= '" . $conn->real_escape_string($to) . "'";
}
if ($q !== '') {
    $safe = '%' . $conn->real_escape_string($q) . '%';
    $where[] = "(
        a.anomaly_type LIKE '{$safe}'
        OR a.message LIKE '{$safe}'
        OR CAST(a.fingerprint_id AS CHAR) LIKE '{$safe}'
        OR s.student_id LIKE '{$safe}'
        OR s.first_name LIKE '{$safe}'
        OR s.last_name LIKE '{$safe}'
        OR u.name LIKE '{$safe}'
        OR u.username LIKE '{$safe}'
    )";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$summary = ['open_count' => 0, 'warning_count' => 0, 'critical_count' => 0, 'total_count' => 0];
$summaryRes = $conn->query("
    SELECT
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warning_count,
        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical_count,
        COUNT(*) AS total_count
    FROM biometric_anomalies
");
if ($summaryRes instanceof mysqli_result) {
    $summary = array_merge($summary, $summaryRes->fetch_assoc() ?: []);
    $summaryRes->close();
}

$total = 0;
$countRes = $conn->query("
    SELECT COUNT(*) AS total
    FROM biometric_anomalies a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN students s ON a.student_id = s.id
    {$whereSql}
");
if ($countRes instanceof mysqli_result) {
    $total = (int)(($countRes->fetch_assoc() ?: [])['total'] ?? 0);
    $countRes->close();
}
$totalPages = max(1, (int)ceil($total / $perPage));

$rows = [];
$res = $conn->query("
    SELECT
        a.*,
        u.name AS mapped_user_name,
        u.username AS mapped_username,
        s.first_name AS student_first_name,
        s.last_name AS student_last_name,
        s.student_id AS student_number,
        s.email AS student_email,
        c.name AS course_name,
        sec.code AS section_code,
        sec.name AS section_name
    FROM biometric_anomalies a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    {$whereSql}
    ORDER BY COALESCE(a.event_time, a.created_at) DESC, a.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->close();
}

$page_title = 'BioTern || Attendance Anomalies';
$page_body_class = 'reports-page attendance-anomalies-page';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title"><h5 class="m-b-10">Attendance Anomalies</h5></div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Reports</li>
                    <li class="breadcrumb-item">Attendance Anomalies</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <a href="biometric-machine.php" class="btn btn-light-brand"><i class="feather-cpu me-2"></i><span>Machine Manager</span></a>
            </div>
        </div>

        <div class="main-content">
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="card stretch stretch-full"><div class="card-body"><div class="text-muted fs-12">Open</div><div class="fs-3 fw-bold"><?php echo (int)$summary['open_count']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card stretch stretch-full"><div class="card-body"><div class="text-muted fs-12">Warnings</div><div class="fs-3 fw-bold"><?php echo (int)$summary['warning_count']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card stretch stretch-full"><div class="card-body"><div class="text-muted fs-12">Critical</div><div class="fs-3 fw-bold"><?php echo (int)$summary['critical_count']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card stretch stretch-full"><div class="card-body"><div class="text-muted fs-12">Total Recorded</div><div class="fs-3 fw-bold"><?php echo (int)$summary['total_count']; ?></div></div></div></div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-control"><option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Open</option><option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option><option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option><option value="dismissed" <?php echo $status === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option></select></div>
                        <div class="col-md-2"><label class="form-label">Severity</label><select name="severity" class="form-control"><option value="all" <?php echo $severity === 'all' ? 'selected' : ''; ?>>All</option><option value="info" <?php echo $severity === 'info' ? 'selected' : ''; ?>>Info</option><option value="warning" <?php echo $severity === 'warning' ? 'selected' : ''; ?>>Warning</option><option value="critical" <?php echo $severity === 'critical' ? 'selected' : ''; ?>>Critical</option></select></div>
                        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?php echo anomalies_h($from); ?>"></div>
                        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?php echo anomalies_h($to); ?>"></div>
                        <div class="col-md-3"><label class="form-label">Search</label><input type="search" name="q" class="form-control" value="<?php echo anomalies_h($q); ?>" placeholder="Finger ID, student, message"></div>
                        <div class="col-md-1 d-flex gap-2"><button type="submit" class="btn btn-primary">Apply</button><a href="reports-attendance-anomalies.php" class="btn btn-outline-secondary">Reset</a></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Finger ID</th>
                                    <th>Student / User</th>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows === []): ?>
                                    <tr><td colspan="<?php echo $isAdmin ? 8 : 7; ?>" class="text-center text-muted py-4">No anomalies matched the current filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <td><?php echo anomalies_h((string)($row['event_time'] ?: $row['created_at'])); ?></td>
                                            <td class="fw-semibold"><?php echo anomalies_h((string)($row['fingerprint_id'] ?? '-')); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo anomalies_h(anomalies_person_label($row)); ?></div>
                                                <small class="text-muted"><?php echo anomalies_h(trim((string)($row['course_name'] ?? '') . ' ' . (string)($row['section_code'] ?? '')) ?: 'No mapped section'); ?></small>
                                            </td>
                                            <td><?php echo anomalies_h(str_replace('_', ' ', (string)$row['anomaly_type'])); ?></td>
                                            <td><span class="badge bg-soft-<?php echo (string)$row['severity'] === 'critical' ? 'danger' : ((string)$row['severity'] === 'info' ? 'info' : 'warning'); ?> text-<?php echo (string)$row['severity'] === 'critical' ? 'danger' : ((string)$row['severity'] === 'info' ? 'info' : 'warning'); ?>"><?php echo anomalies_h((string)$row['severity']); ?></span></td>
                                            <td><?php echo anomalies_h((string)$row['message']); ?></td>
                                            <td><?php echo anomalies_h(ucwords(str_replace('_', ' ', (string)$row['status']))); ?></td>
                                            <?php if ($isAdmin): ?>
                                                <td>
                                                    <?php if ((string)$row['status'] === 'open'): ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="anomaly_id" value="<?php echo (int)$row['id']; ?>">
                                                            <button type="submit" name="anomaly_action" value="resolve" class="btn btn-sm btn-success">Resolve</button>
                                                        </form>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="anomaly_id" value="<?php echo (int)$row['id']; ?>">
                                                            <button type="submit" name="anomaly_action" value="dismiss" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">Closed</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <?php $baseQuery = $_GET; ?>
                    <?php $baseQuery['page'] = max(1, $page - 1); ?>
                    <a class="btn btn-outline-secondary<?php echo $page <= 1 ? ' disabled' : ''; ?>" href="reports-attendance-anomalies.php?<?php echo anomalies_h(http_build_query($baseQuery)); ?>">Previous</a>
                    <?php $baseQuery['page'] = min($totalPages, $page + 1); ?>
                    <a class="btn btn-outline-secondary<?php echo $page >= $totalPages ? ' disabled' : ''; ?>" href="reports-attendance-anomalies.php?<?php echo anomalies_h(http_build_query($baseQuery)); ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
