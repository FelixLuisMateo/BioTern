<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/attendance_workflow.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor']);

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    die('Database connection is not available.');
}
/** @var mysqli $db */
$db = $conn;
attendance_workflow_ensure_correction_schema($db);

function attendance_corrections_review_request(mysqli $db, int $requestId, string $decision, string $reviewRemarks, int $reviewerId): array
{
    $requestStmt = $db->prepare("SELECT * FROM attendance_correction_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    if (!$requestStmt) {
        return ['success' => false, 'message' => 'Unable to load the correction request.', 'updated_count' => 0];
    }

    $requestStmt->bind_param('i', $requestId);
    $requestStmt->execute();
    $request = $requestStmt->get_result()->fetch_assoc() ?: null;
    $requestStmt->close();
    if (!$request) {
        return ['success' => false, 'message' => 'Correction request is no longer pending.', 'updated_count' => 0];
    }

    if ($decision === 'approved') {
        try {
            attendance_workflow_apply_approved_correction($db, $request, $reviewerId, $reviewRemarks);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'updated_count' => 0];
        }
    }

    $reviewStmt = $db->prepare("
        UPDATE attendance_correction_requests
        SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_remarks = ?, updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    if (!$reviewStmt) {
        return ['success' => false, 'message' => 'Unable to save the correction review.', 'updated_count' => 0];
    }

    $reviewStmt->bind_param('sisi', $decision, $reviewerId, $reviewRemarks, $requestId);
    $reviewStmt->execute();
    $updated = $reviewStmt->affected_rows > 0 ? 1 : 0;
    $reviewStmt->close();

    return [
        'success' => $updated > 0,
        'message' => $updated > 0 ? 'Correction request reviewed successfully.' : 'Correction request was not updated.',
        'updated_count' => $updated,
    ];
}

$flash = null;
$flashType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
    $reviewRemarks = trim((string)($_POST['review_remarks'] ?? ''));

    if ($requestId <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
        $flash = 'Invalid correction review request.';
        $flashType = 'danger';
    } else {
        $reviewResult = attendance_corrections_review_request($db, $requestId, $decision, $reviewRemarks, get_current_user_id_or_zero());
        $flash = (string)($reviewResult['message'] ?? 'Correction review updated.');
        $flashType = !empty($reviewResult['success']) ? 'success' : 'danger';
    }
}

$query = "
    SELECT r.*, a.attendance_date, a.morning_time_in, a.morning_time_out, a.afternoon_time_in, a.afternoon_time_out, a.status AS attendance_status,
           s.first_name, s.last_name
    FROM attendance_correction_requests r
    LEFT JOIN attendances a ON a.id = r.attendance_id
    LEFT JOIN students s ON s.id = a.student_id
    ORDER BY r.created_at DESC
    LIMIT 100
";
$res = $db->query($query);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$page_title = 'BioTern || Attendance Corrections';
$page_styles = [
    'assets/css/modules/pages/page-attendance.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];

include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Attendance Corrections</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">Attendance Corrections</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="attendanceCorrectionsActionsMenu">
                        <i class="feather-grid me-1"></i>
                        <span>Actions</span>
                    </button>
                    <div class="page-header-actions" id="attendanceCorrectionsActionsMenu">
                        <div class="dashboard-actions-panel">
                            <div class="dashboard-actions-meta">
                                <span class="text-muted fs-12">Quick Actions</span>
                            </div>
                            <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <a href="attendance.php" class="btn btn-light-brand">
                                <i class="feather-calendar me-1"></i>
                                <span>Attendance DTR</span>
                            </a>
                            <a href="homepage.php" class="btn btn-outline-secondary">
                                <i class="feather-home me-1"></i>
                                <span>Dashboard</span>
                            </a>
                            <button type="button" class="btn btn-light" data-action="print-page">
                                <i class="feather-printer me-1"></i>
                                <span>Print</span>
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-content">
                <?php if ($flash !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Attendance Correction Requests</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Reason</th>
                                        <th>Requested Changes</th>
                                        <th>Status</th>
                                        <th>Requested</th>
                                        <th>Review</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="8" class="text-center">No correction requests.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                            <?php
                                                $requestedChanges = [];
                                                $requestedChangesRaw = trim((string)($row['requested_changes'] ?? ''));
                                                if ($requestedChangesRaw !== '') {
                                                    $decoded = json_decode($requestedChangesRaw, true);
                                                    $requestedChanges = is_array($decoded) ? attendance_workflow_normalize_request_changes($decoded) : [];
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo (int)$row['id']; ?></td>
                                                <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                                                <td><?php echo htmlspecialchars($row['attendance_date'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['correction_reason']); ?></td>
                                                <td>
                                                    <?php if ($requestedChanges === []): ?>
                                                        <span class="text-muted">No manual clock-out supplied</span>
                                                    <?php else: ?>
                                                        <div class="small text-muted">
                                                            <?php foreach ($requestedChanges as $field => $value): ?>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $field)), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                                                                    <?php echo htmlspecialchars((string)($value ?? '--'), ENT_QUOTES, 'UTF-8'); ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                                <td>
                                                    <?php if (strtolower((string)$row['status']) === 'pending'): ?>
                                                        <form method="post" class="d-grid gap-2">
                                                            <input type="hidden" name="request_id" value="<?php echo (int)$row['id']; ?>">
                                                            <textarea name="review_remarks" class="form-control form-control-sm" rows="2" placeholder="Optional review note"></textarea>
                                                            <div class="d-flex gap-2">
                                                                <button type="submit" name="decision" value="approved" class="btn btn-sm btn-success">Approve</button>
                                                                <button type="submit" name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="small text-muted">
                                                            <div><strong>Reviewed:</strong> <?php echo htmlspecialchars((string)($row['reviewed_at'] ?? '--'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div><strong>Note:</strong> <?php echo htmlspecialchars((string)($row['review_remarks'] ?? '--'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>



