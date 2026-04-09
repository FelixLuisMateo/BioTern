<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$studentStmt = $conn->prepare("SELECT id, student_id, first_name, last_name FROM students WHERE user_id = ? LIMIT 1");
$studentStmt->bind_param('i', $currentUserId);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();

if (!$student) {
    header('Location: auth-login.php?logout=1');
    exit;
}

$month = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$attStmt = $conn->prepare("SELECT attendance_date, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out, total_hours, status FROM attendances WHERE student_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC");
$studentId = (int)$student['id'];
$attStmt->bind_param('iss', $studentId, $start, $end);
$attStmt->execute();
$attendances = $attStmt->get_result();

$page_title = 'BioTern || My DTR';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">My DTR</h5>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label class="form-label">Month</label>
                            <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Morning In</th>
                                    <th>Morning Out</th>
                                    <th>Afternoon In</th>
                                    <th>Afternoon Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($attendances && $attendances->num_rows > 0): ?>
                                    <?php while ($row = $attendances->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$row['attendance_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['morning_time_in'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['morning_time_out'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['afternoon_time_in'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['afternoon_time_out'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['total_hours'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No attendance records for this month.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>



