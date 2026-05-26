<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/attendance_workflow.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/manual_dtr_requests.php';
require_once dirname(__DIR__) . '/includes/admin-activity-log.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);
external_attendance_ensure_schema($conn);
manual_dtr_requests_ensure_schema($conn);

$currentRole = get_current_user_role();
$currentUserId = get_current_user_id_or_zero();
$coordinatorAllowedCourseIds = $currentRole === 'coordinator'
    ? coordinator_course_ids($conn, $currentUserId)
    : [];
$coordinatorStudentScopeSql = '';
if ($currentRole === 'coordinator') {
    $coordinatorStudentScopeSql = empty($coordinatorAllowedCourseIds)
        ? '1 = 0'
        : 'course_id IN (' . implode(',', array_map('intval', $coordinatorAllowedCourseIds)) . ')';
}

function manual_student_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function manual_student_format_time($value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '00:00:00') {
        return '-';
    }
    $ts = strtotime($raw);
    return $ts === false ? $raw : date('h:i A', $ts);
}

function manual_student_proof_url(string $origin, int $proofId): string
{
    if ($proofId <= 0) {
        return '';
    }
    return $origin === 'external'
        ? 'external-dtr-proof.php?id=' . $proofId
        : 'manual-dtr-proof.php?id=' . $proofId;
}

$origin = strtolower(trim((string)($_GET['origin'] ?? $_POST['origin'] ?? 'internal')));
$origin = $origin === 'external' ? 'external' : 'internal';
$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$dateFrom = trim((string)($_GET['from'] ?? $_POST['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? $_POST['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo === '') {
    $dateTo = $dateFrom;
}
if ($dateTo !== '' && $dateFrom === '') {
    $dateFrom = $dateTo;
}

$flash = $_SESSION['manual_dtr_flash'] ?? null;
unset($_SESSION['manual_dtr_flash']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = strtolower(trim((string)($_POST['manual_dtr_action'] ?? '')));
    if (in_array($action, ['approve_selected', 'reject_selected'], true)) {
        $ids = isset($_POST['attendance_ids']) && is_array($_POST['attendance_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['attendance_ids']))))
            : [];
        $reviewNote = trim((string)($_POST['review_note'] ?? ''));
        $newStatus = $action === 'approve_selected' ? 'approved' : 'rejected';

        if ($ids === []) {
            $_SESSION['manual_dtr_flash'] = ['type' => 'warning', 'message' => 'Select at least one date first.'];
        } elseif ($newStatus === 'rejected' && $reviewNote === '') {
            $_SESSION['manual_dtr_flash'] = ['type' => 'warning', 'message' => 'A review note is required when rejecting manual DTR.'];
        } else {
            $idList = implode(',', array_map('intval', $ids));
            $updated = 0;
            if ($origin === 'external') {
                $stmt = $conn->prepare("
                    UPDATE external_attendance
                    SET status = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        notes = CASE WHEN ? <> '' THEN CONCAT(TRIM(COALESCE(notes, '')), CASE WHEN TRIM(COALESCE(notes, '')) = '' THEN '' ELSE ' | ' END, ?) ELSE notes END,
                        updated_at = NOW()
                    WHERE id IN ({$idList})
                      AND student_id = ?
                      AND source = 'manual'
                ");
                if ($stmt) {
                    $stmt->bind_param('sissi', $newStatus, $currentUserId, $reviewNote, $reviewNote, $studentId);
                    $stmt->execute();
                    $updated = max(0, (int)$stmt->affected_rows);
                    $stmt->close();
                    external_attendance_sync_student_hours($conn, $studentId);
                }
            } else {
                $stmt = $conn->prepare("
                    UPDATE attendances
                    SET status = ?,
                        approved_by = CASE WHEN ? = 'approved' THEN ? ELSE approved_by END,
                        approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_at END,
                        remarks = CASE WHEN ? <> '' THEN CONCAT(TRIM(COALESCE(remarks, '')), CASE WHEN TRIM(COALESCE(remarks, '')) = '' THEN '' ELSE ' | ' END, ?) ELSE remarks END,
                        updated_at = NOW()
                    WHERE id IN ({$idList})
                      AND student_id = ?
                      AND source = 'manual'
                ");
                if ($stmt) {
                    $stmt->bind_param('ssisssi', $newStatus, $newStatus, $currentUserId, $newStatus, $reviewNote, $reviewNote, $studentId);
                    $stmt->execute();
                    $updated = max(0, (int)$stmt->affected_rows);
                    $stmt->close();
                    if (function_exists('attendance_workflow_sync_student_progress')) {
                        attendance_workflow_sync_student_progress($conn, $studentId);
                    }
                }
            }
            manual_dtr_requests_sync_for_attendance_ids($conn, $ids, $newStatus, $currentUserId, $reviewNote);
            if ($updated > 0) {
                biotern_admin_activity_log(
                    $conn,
                    $newStatus === 'approved' ? 'approve' : 'reject',
                    'manual_dtr_request',
                    null,
                    [
                        'origin' => $origin,
                        'student_id' => $studentId,
                        'attendance_ids' => $ids,
                        'status' => $newStatus,
                        'review_note' => $reviewNote,
                    ],
                    null,
                    ucfirst($newStatus) . ' ' . $updated . ' manual DTR date(s) for student #' . $studentId . '.'
                );
            }

            $_SESSION['manual_dtr_flash'] = [
                'type' => $updated > 0 ? 'success' : 'warning',
                'message' => $updated > 0 ? ucfirst($newStatus) . ' ' . $updated . ' date(s).' : 'No manual DTR rows were updated.',
            ];
        }

        $return = 'reports-dtr-manual-student.php?origin=' . urlencode($origin)
            . '&student_id=' . $studentId
            . '&from=' . urlencode($dateFrom)
            . '&to=' . urlencode($dateTo);
        header('Location: ' . $return);
        exit;
    }
}

$scopeSql = $coordinatorStudentScopeSql !== ''
    ? ' AND ' . str_replace('course_id', 's.course_id', $coordinatorStudentScopeSql)
    : '';
$safeFrom = $conn->real_escape_string($dateFrom);
$safeTo = $conn->real_escape_string($dateTo);
$rows = [];

if ($studentId > 0 && $dateFrom !== '' && $dateTo !== '') {
    if ($origin === 'external') {
        $sql = "
            SELECT
                ea.id, ea.attendance_date, ea.morning_time_in, ea.morning_time_out,
                ea.afternoon_time_in, ea.afternoon_time_out, ea.total_hours, ea.status,
                ea.notes AS remarks, eda.id AS proof_id,
                s.student_id AS student_number, s.first_name, s.last_name,
                COALESCE(c.name, '') AS course_name, COALESCE(sec.code, sec.name, '') AS section_label
            FROM external_attendance ea
            INNER JOIN students s ON s.id = ea.student_id
            LEFT JOIN courses c ON c.id = s.course_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN external_dtr_attachments eda ON eda.id = (
                SELECT MIN(eda_inner.id)
                FROM external_dtr_attachments eda_inner
                WHERE eda_inner.external_attendance_id = ea.id
                  AND eda_inner.deleted_at IS NULL
            )
            WHERE ea.student_id = {$studentId}
              AND ea.source = 'manual'
              AND ea.attendance_date BETWEEN '{$safeFrom}' AND '{$safeTo}'
              {$scopeSql}
            ORDER BY ea.attendance_date ASC, ea.id ASC
        ";
    } else {
        $sql = "
            SELECT
                a.id, a.attendance_date, a.morning_time_in, a.morning_time_out,
                a.afternoon_time_in, a.afternoon_time_out, a.total_hours, a.status,
                a.remarks, mda.id AS proof_id,
                mdr.id AS request_id, mdr.reason_category, mdr.reason_details, mdr.submitted_ip, mdr.submitted_user_agent,
                s.student_id AS student_number, s.first_name, s.last_name,
                COALESCE(c.name, '') AS course_name, COALESCE(sec.code, sec.name, '') AS section_label
            FROM attendances a
            INNER JOIN students s ON s.id = a.student_id
            LEFT JOIN courses c ON c.id = s.course_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN manual_dtr_attachments mda ON mda.id = (
                SELECT MIN(mda_inner.id)
                FROM manual_dtr_attachments mda_inner
                WHERE mda_inner.attendance_id = a.id
                  AND mda_inner.deleted_at IS NULL
            )
            LEFT JOIN manual_dtr_request_entries mdre ON mdre.attendance_id = a.id
            LEFT JOIN manual_dtr_requests mdr ON mdr.id = mdre.request_id
            WHERE a.student_id = {$studentId}
              AND a.source = 'manual'
              AND a.attendance_date BETWEEN '{$safeFrom}' AND '{$safeTo}'
              {$scopeSql}
            ORDER BY a.attendance_date ASC, a.id ASC
        ";
    }
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->close();
    }
}

$meta = $rows[0] ?? [];
$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css']);
$page_scripts = array_merge($page_scripts ?? [], ['assets/js/modules/reports/reports-shell-runtime.js']);
$page_title = 'BioTern || Manual DTR Review';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Manual DTR Review</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                <li class="breadcrumb-item"><a href="reports-dtr-manual-input.php">Manual DTR Review</a></li>
                <li class="breadcrumb-item">Student Dates</li>
            </ul>
        </div>
    </div>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="alert alert-<?php echo manual_student_h((string)($flash['type'] ?? 'info')); ?>"><?php echo manual_student_h((string)$flash['message']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold"><?php echo manual_student_h(trim((string)($meta['first_name'] ?? '') . ' ' . (string)($meta['last_name'] ?? '')) ?: 'Manual DTR Submission'); ?></div>
                <small class="text-muted">
                    <?php echo manual_student_h((string)($meta['student_number'] ?? '-')); ?> |
                    <?php echo manual_student_h((string)($meta['course_name'] ?? '-')); ?> |
                    <?php echo manual_student_h((string)($meta['section_label'] ?? '-')); ?> |
                    <?php echo manual_student_h(ucfirst($origin)); ?> |
                    <?php echo manual_student_h($dateFrom . ($dateFrom === $dateTo ? '' : ' to ' . $dateTo)); ?>
                </small>
                <?php if (!empty($meta['request_id'])): ?>
                <div class="small text-muted mt-1">
                    Request #<?php echo (int)$meta['request_id']; ?> |
                    <?php echo manual_student_h(manual_dtr_category_label((string)($meta['reason_category'] ?? 'other'))); ?>
                    <?php if (trim((string)($meta['reason_details'] ?? '')) !== ''): ?>
                        - <?php echo manual_student_h((string)$meta['reason_details']); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="reports-dtr-manual-input.php" class="btn btn-outline-secondary btn-sm">Back to Students</a>
        </div>
        <form method="post">
            <input type="hidden" name="origin" value="<?php echo manual_student_h($origin); ?>">
            <input type="hidden" name="student_id" value="<?php echo (int)$studentId; ?>">
            <input type="hidden" name="from" value="<?php echo manual_student_h($dateFrom); ?>">
            <input type="hidden" name="to" value="<?php echo manual_student_h($dateTo); ?>">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="manualDtrSelectAll" checked></th>
                                <th>Date</th>
                                <th>Morning In</th>
                                <th>Morning Out</th>
                                <th>Afternoon In</th>
                                <th>Afternoon Out</th>
                                <th>Total Hours</th>
                                <th>Photo</th>
                                <th>Notes</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rows === []): ?>
                                <tr><td colspan="10" class="text-center py-4 text-muted">No manual DTR dates found for this student.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $status = strtolower((string)($row['status'] ?? 'pending'));
                                    $proofUrl = manual_student_proof_url($origin, (int)($row['proof_id'] ?? 0));
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="manual-dtr-row-check" name="attendance_ids[]" value="<?php echo (int)$row['id']; ?>" <?php echo $status === 'pending' ? 'checked' : ''; ?>></td>
                                        <td><span class="badge bg-soft-primary text-primary"><?php echo manual_student_h((string)$row['attendance_date']); ?></span></td>
                                        <td><?php echo manual_student_h(manual_student_format_time($row['morning_time_in'] ?? '')); ?></td>
                                        <td><?php echo manual_student_h(manual_student_format_time($row['morning_time_out'] ?? '')); ?></td>
                                        <td><?php echo manual_student_h(manual_student_format_time($row['afternoon_time_in'] ?? '')); ?></td>
                                        <td><?php echo manual_student_h(manual_student_format_time($row['afternoon_time_out'] ?? '')); ?></td>
                                        <td><?php echo manual_student_h(number_format((float)($row['total_hours'] ?? 0), 2)); ?></td>
                                        <td>
                                            <?php if ($proofUrl !== ''): ?>
                                                <a href="<?php echo manual_student_h($proofUrl); ?>" target="_blank" rel="noopener" class="badge bg-soft-secondary text-secondary">Proof</a>
                                            <?php else: ?>
                                                <span class="badge bg-soft-secondary text-secondary">No proof</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo manual_student_h(trim((string)($row['remarks'] ?? '')) !== '' ? (string)$row['remarks'] : '-'); ?></td>
                                        <td><span class="badge bg-soft-<?php echo $status === 'approved' ? 'success text-success' : ($status === 'rejected' ? 'danger text-danger' : 'warning text-warning'); ?>"><?php echo manual_student_h(ucfirst($status)); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <input type="text" name="review_note" class="form-control" placeholder="Review note (required only for rejection)">
                    </div>
                    <div class="col-md-5 d-flex gap-2 justify-content-md-end">
                        <button type="submit" name="manual_dtr_action" value="approve_selected" class="btn btn-success">Approve Selected</button>
                        <button type="submit" name="manual_dtr_action" value="reject_selected" class="btn btn-outline-danger">Reject Selected</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</main>
<script>
(function () {
    var selectAll = document.getElementById('manualDtrSelectAll');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.manual-dtr-row-check'), function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
    });
}());
</script>
<?php include 'includes/footer.php'; ?>
