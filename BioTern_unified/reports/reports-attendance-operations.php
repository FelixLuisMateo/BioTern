<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

function attendance_ops_display_date(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($raw);
    return $timestamp ? date('M d, Y', $timestamp) : $raw;
}

$rows = [];
if (table_exists($conn, 'attendance_operational_report')) {
    $res = $conn->query("SELECT * FROM attendance_operational_report ORDER BY attendance_date DESC LIMIT 30");
    if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
}

$pendingCorrections = 0;
if (table_exists($conn, 'attendance_correction_requests')) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM attendance_correction_requests WHERE status = 'pending'");
    if ($r instanceof mysqli_result) {
        $pendingCorrections = (int)($r->fetch_assoc()['c'] ?? 0);
        $r->free();
    }
}

$pendingQueue = 0;
$failedQueue = 0;
if (table_exists($conn, 'biometric_event_queue')) {
    $r = $conn->query("SELECT SUM(status='pending') AS p, SUM(status='failed') AS f FROM biometric_event_queue");
    if ($r instanceof mysqli_result) {
        $x = $r->fetch_assoc() ?: [];
        $pendingQueue = (int)($x['p'] ?? 0);
        $failedQueue = (int)($x['f'] ?? 0);
        $r->free();
    }
}

$latestSummaryDate = !empty($rows) ? attendance_ops_display_date((string)($rows[0]['attendance_date'] ?? '')) : 'No summary data yet';
$page_title = 'BioTern || Attendance Operations Report';
include 'includes/header.php';
?>
<style>
    .page-header h5 { border-right: none !important; margin-right: 0 !important; padding-right: 0 !important; }
    .page-header .page-header-left { border-right: none !important; border-left: none !important; }
    .page-header .page-header-title { border-right: none !important; border-left: none !important; }
    .breadcrumb-wrapper { margin: 0 0 12px 0; }
    .breadcrumb { padding: 0; margin-bottom: 0; background: transparent; font-size: 13px; }
    .breadcrumb-item { color: #64748b; }
    .breadcrumb-item a { color: #64748b; text-decoration: none; transition: color 0.3s ease; }
    .breadcrumb-item a:hover { color: #3454d1; }
    .breadcrumb-item.active { color: #64748b; font-weight: 500; }
    .breadcrumb-item + .breadcrumb-item::before { color: #94a3b8; }
    .page-header .page-header-title { border-right: 0 !important; padding-right: 0 !important; margin-right: 0 !important; display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; }
    .page-header-left { align-items: center !important; }
    .page-header .page-header-title h5 { margin: 0; }
    .page-header .breadcrumb { display: flex; align-items: center; flex-wrap: wrap; margin: 0; }
    .report-hero {
        border: 1px solid rgba(80, 102, 144, 0.15);
        background: #ffffff;
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        margin-bottom: 1rem;
    }
    .report-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .report-kpi {
        border: 1px solid rgba(80, 102, 144, 0.14);
        border-radius: 12px;
        padding: 0.95rem 1rem;
        background: #fff;
    }
    .report-kpi-label {
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.35rem;
    }
    .report-kpi-value {
        font-size: 1.55rem;
        font-weight: 700;
        line-height: 1.1;
    }
    .report-kpi-subtext {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.35rem;
    }
    .report-kpi.pending .report-kpi-value { color: #f59e0b; }
    .report-kpi.queue .report-kpi-value { color: #2563eb; }
    .report-kpi.failed .report-kpi-value { color: #dc2626; }
    .report-kpi.days .report-kpi-value { color: #059669; }
    .report-table-card {
        border: 1px solid rgba(80, 102, 144, 0.14);
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }
    .report-table-card .table { margin-bottom: 0; }
    .report-table-card thead th {
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem;
        white-space: nowrap;
    }
    .report-pill {
        border-radius: 999px;
        padding: 0.4rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    html.app-skin-dark .report-hero {
        border-color: rgba(129, 153, 199, 0.25);
        background: #162033;
    }
    html.app-skin-dark .report-hero h6 { color: #e5edff; }
    html.app-skin-dark .report-hero p { color: #a9b7d6 !important; }
    html.app-skin-dark .report-kpi,
    html.app-skin-dark .report-table-card {
        background: #0f172a;
        border-color: rgba(129, 153, 199, 0.24);
        color: #dce7ff;
    }
    html.app-skin-dark .report-kpi-label,
    html.app-skin-dark .report-kpi-subtext {
        color: #9fb0d3;
    }
    html.app-skin-dark .report-table-card thead th {
        background: #111f36;
        color: #9fb0d3;
        border-bottom-color: rgba(129, 153, 199, 0.25);
    }
    html.app-skin-dark .report-table-card .table {
        --bs-table-bg: #0f172a;
        --bs-table-hover-bg: #18243d;
        --bs-table-border-color: rgba(129, 153, 199, 0.2);
    }
    @media (max-width: 768px) {
        .report-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Attendance Operations</h5>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                <li class="breadcrumb-item">Attendance Operations</li>
            </ul>
        </div>
    </div>
</div>
<div class="main-content pb-5">
    <div class="report-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h6 class="mb-1 fw-bold">Attendance Operations Overview</h6>
            <p class="text-muted mb-0">Track correction workload, biometric queue health, and daily attendance processing trends.</p>
        </div>
        <span class="report-pill bg-soft-primary text-primary"><i class="feather feather-activity"></i><?php echo htmlspecialchars($latestSummaryDate); ?></span>
    </div>

    <div class="report-summary-grid">
        <div class="report-kpi pending">
            <div class="report-kpi-label">Pending Corrections</div>
            <div class="report-kpi-value"><?php echo $pendingCorrections; ?></div>
            <div class="report-kpi-subtext">Requests still waiting for review.</div>
        </div>
        <div class="report-kpi queue">
            <div class="report-kpi-label">Pending Machine Events</div>
            <div class="report-kpi-value"><?php echo $pendingQueue; ?></div>
            <div class="report-kpi-subtext">Biometric queue items not processed yet.</div>
        </div>
        <div class="report-kpi failed">
            <div class="report-kpi-label">Failed Machine Events</div>
            <div class="report-kpi-value"><?php echo $failedQueue; ?></div>
            <div class="report-kpi-subtext">Queue failures that may need intervention.</div>
        </div>
        <div class="report-kpi days">
            <div class="report-kpi-label">Days Covered</div>
            <div class="report-kpi-value"><?php echo count($rows); ?></div>
            <div class="report-kpi-subtext">Daily operation summaries in this report.</div>
        </div>
    </div>

    <div class="report-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Records</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Rejected</th>
                        <th>Zero Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No attendance operations summary is available yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(attendance_ops_display_date((string)($row['attendance_date'] ?? ''))); ?></strong></td>
                                <td><?php echo (int)($row['total_records'] ?? 0); ?></td>
                                <td><?php echo (int)($row['approved_records'] ?? 0); ?></td>
                                <td><?php echo (int)($row['pending_records'] ?? 0); ?></td>
                                <td><?php echo (int)($row['rejected_records'] ?? 0); ?></td>
                                <td><?php echo (int)($row['zero_hour_records'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
