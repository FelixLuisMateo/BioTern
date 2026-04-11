<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

function dtr_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dtr_parse_time(?string $value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    return $ts === false ? null : $ts;
}

function dtr_calculate_hours(?string $morningIn, ?string $morningOut, ?string $afternoonIn, ?string $afternoonOut): float
{
    $totalSeconds = 0;
    $pairs = [[$morningIn, $morningOut], [$afternoonIn, $afternoonOut]];
    foreach ($pairs as [$start, $end]) {
        $startTs = dtr_parse_time($start);
        $endTs = dtr_parse_time($end);
        if ($startTs !== null && $endTs !== null && $endTs > $startTs) {
            $totalSeconds += ($endTs - $startTs);
        }
    }
    return round($totalSeconds / 3600, 2);
}

$flash = $_SESSION['manual_dtr_flash'] ?? null;
unset($_SESSION['manual_dtr_flash']);

$students = [];
$studentRes = $conn->query("SELECT id, student_id, first_name, last_name FROM students ORDER BY last_name ASC, first_name ASC LIMIT 1000");
if ($studentRes instanceof mysqli_result) {
    while ($s = $studentRes->fetch_assoc()) {
        $students[] = $s;
    }
    $studentRes->close();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = strtolower(trim((string)($_POST['manual_dtr_action'] ?? 'create')));

    if ($action === 'delete') {
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        if ($attendanceId > 0) {
            $stmt = $conn->prepare("DELETE FROM attendances WHERE id = ? AND source = 'manual'");
            if ($stmt) {
                $stmt->bind_param('i', $attendanceId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['manual_dtr_flash'] = ['type' => 'success', 'message' => 'Manual DTR record deleted.'];
            } else {
                $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'Unable to delete record.'];
            }
        }
        header('Location: reports-dtr-manual-input.php');
        exit;
    }

    $studentId = (int)($_POST['student_id'] ?? 0);
    $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
    $morningIn = trim((string)($_POST['morning_time_in'] ?? ''));
    $morningOut = trim((string)($_POST['morning_time_out'] ?? ''));
    $afternoonIn = trim((string)($_POST['afternoon_time_in'] ?? ''));
    $afternoonOut = trim((string)($_POST['afternoon_time_out'] ?? ''));
    $status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    if ($studentId <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate) !== 1) {
        $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'Student and attendance date are required.'];
        header('Location: reports-dtr-manual-input.php');
        exit;
    }

    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }

    $hours = dtr_calculate_hours($morningIn, $morningOut, $afternoonIn, $afternoonOut);

    $internshipId = null;
    $internshipStmt = $conn->prepare("SELECT id FROM internships WHERE student_id = ? AND status = 'ongoing' ORDER BY id DESC LIMIT 1");
    if ($internshipStmt) {
        $internshipStmt->bind_param('i', $studentId);
        $internshipStmt->execute();
        $internshipRow = $internshipStmt->get_result()->fetch_assoc();
        $internshipStmt->close();
        if ($internshipRow && isset($internshipRow['id'])) {
            $internshipId = (int)$internshipRow['id'];
        }
    }

    $approvedBy = null;
    $approvedAt = null;
    if ($status === 'approved') {
        $approvedBy = (int)($_SESSION['user_id'] ?? 0);
        $approvedAt = date('Y-m-d H:i:s');
    }

    $insertSql = "
        INSERT INTO attendances
        (student_id, internship_id, attendance_date, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out, total_hours, source, status, approved_by, approved_at, remarks, created_at, updated_at)
        VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, 'manual', ?, ?, ?, NULLIF(?, ''), NOW(), NOW())
    ";
    $insertStmt = $conn->prepare($insertSql);
    if ($insertStmt) {
        $insertStmt->bind_param(
            'iisssssdsiss',
            $studentId,
            $internshipId,
            $attendanceDate,
            $morningIn,
            $morningOut,
            $afternoonIn,
            $afternoonOut,
            $hours,
            $status,
            $approvedBy,
            $approvedAt,
            $remarks
        );
        $ok = $insertStmt->execute();
        $insertStmt->close();

        $_SESSION['manual_dtr_flash'] = $ok
            ? ['type' => 'success', 'message' => 'Manual DTR record added successfully.']
            : ['type' => 'danger', 'message' => 'Failed to add manual DTR record.'];
    } else {
        $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'Failed to prepare manual DTR insert.'];
    }

    header('Location: reports-dtr-manual-input.php');
    exit;
}

$filterDate = trim((string)($_GET['date'] ?? ''));
$filterStudent = (int)($_GET['student_id'] ?? 0);
$where = ["a.source = 'manual'"];
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate) === 1) {
    $where[] = "a.attendance_date = '" . $conn->real_escape_string($filterDate) . "'";
}
if ($filterStudent > 0) {
    $where[] = 'a.student_id = ' . $filterStudent;
}

$rows = [];
$sql = "
    SELECT
        a.id,
        a.attendance_date,
        a.morning_time_in,
        a.morning_time_out,
        a.afternoon_time_in,
        a.afternoon_time_out,
        a.total_hours,
        a.status,
        a.remarks,
        a.created_at,
        s.id AS student_row_id,
        s.student_id,
        s.first_name,
        s.last_name
    FROM attendances a
    LEFT JOIN students s ON s.id = a.student_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.attendance_date DESC, a.id DESC
    LIMIT 300
";
$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->close();
}

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css']);
$page_title = 'BioTern || Manual DTR Input Report';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="main-content">
    <div class="page-header page-header-with-middle">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Manual DTR Input</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="reports-ojt.php">Reports</a></li>
                <li class="breadcrumb-item">Manual DTR Input</li>
            </ul>
        </div>
        <div class="page-header-middle">
            <p class="page-header-statement">Create and review manually-entered DTR records for correction workflows and exceptional cases.</p>
        </div>
    </div>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="alert alert-<?php echo dtr_h((string)($flash['type'] ?? 'info')); ?>"><?php echo dtr_h((string)$flash['message']); ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header fw-semibold">Add Manual DTR Record</div>
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="manual_dtr_action" value="create">
                <div class="col-md-4">
                    <label class="form-label">Student</label>
                    <select class="form-select" name="student_id" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>">
                                <?php echo dtr_h((string)($s['student_id'] ?? '-')); ?> - <?php echo dtr_h(trim((string)($s['last_name'] ?? '') . ', ' . (string)($s['first_name'] ?? ''))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="attendance_date" value="<?php echo dtr_h(date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Morning In</label>
                    <input type="time" class="form-control" name="morning_time_in">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Morning Out</label>
                    <input type="time" class="form-control" name="morning_time_out">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Afternoon In</label>
                    <input type="time" class="form-control" name="afternoon_time_in">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Afternoon Out</label>
                    <input type="time" class="form-control" name="afternoon_time_out">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-control" name="remarks" placeholder="Reason / correction note">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Filter Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo dtr_h($filterDate); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Filter Student</label>
                    <select class="form-select" name="student_id">
                        <option value="0">All students</option>
                        <?php foreach ($students as $s): ?>
                            <?php $sid = (int)($s['id'] ?? 0); ?>
                            <option value="<?php echo $sid; ?>"<?php echo $filterStudent === $sid ? ' selected' : ''; ?>>
                                <?php echo dtr_h((string)($s['student_id'] ?? '-')); ?> - <?php echo dtr_h(trim((string)($s['last_name'] ?? '') . ', ' . (string)($s['first_name'] ?? ''))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="reports-dtr-manual-input.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Manual DTR Records</span>
            <span class="badge bg-soft-primary text-primary"><?php echo count($rows); ?> row(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Morning</th>
                            <th>Afternoon</th>
                            <th>Total Hours</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No manual DTR records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $status = strtolower((string)($row['status'] ?? 'pending'));
                                $statusClass = 'warning';
                                if ($status === 'approved') $statusClass = 'success';
                                if ($status === 'rejected') $statusClass = 'danger';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo dtr_h(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></div>
                                        <small class="text-muted">ID: <?php echo dtr_h((string)($row['student_id'] ?? '-')); ?></small>
                                    </td>
                                    <td><?php echo dtr_h((string)($row['attendance_date'] ?? '-')); ?></td>
                                    <td><?php echo dtr_h((string)($row['morning_time_in'] ?? '-')); ?> - <?php echo dtr_h((string)($row['morning_time_out'] ?? '-')); ?></td>
                                    <td><?php echo dtr_h((string)($row['afternoon_time_in'] ?? '-')); ?> - <?php echo dtr_h((string)($row['afternoon_time_out'] ?? '-')); ?></td>
                                    <td><?php echo dtr_h(number_format((float)($row['total_hours'] ?? 0), 2)); ?></td>
                                    <td><span class="badge bg-soft-<?php echo dtr_h($statusClass); ?> text-<?php echo dtr_h($statusClass); ?> text-capitalize"><?php echo dtr_h($status); ?></span></td>
                                    <td><?php echo dtr_h(trim((string)($row['remarks'] ?? '')) !== '' ? (string)$row['remarks'] : '-'); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Delete this manual DTR record?');">
                                            <input type="hidden" name="manual_dtr_action" value="delete">
                                            <input type="hidden" name="attendance_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                        </form>
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
