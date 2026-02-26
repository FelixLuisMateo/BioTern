<?php
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    header('Location: idnotfound-404.php?source=students-dtr&id=' . urlencode($student_id));
    exit;
}

$month_input = isset($_GET['month']) ? trim((string)$_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_input)) {
    $month_input = date('Y-m');
}

$month_start = $month_input . '-01';
$month_ts = strtotime($month_start);
if ($month_ts === false) {
    $month_input = date('Y-m');
    $month_start = $month_input . '-01';
    $month_ts = strtotime($month_start);
}
$month_end = date('Y-m-t', $month_ts);
$days_in_month = (int)date('t', $month_ts);

$student_stmt = $conn->prepare("
    SELECT 
        s.id, 
        s.student_id, 
        s.first_name, 
        s.middle_name, 
        s.last_name, 
        s.assignment_track,
        s.internal_total_hours,
        s.internal_total_hours_remaining,
        s.external_total_hours,
        s.external_total_hours_remaining,
        c.name AS course_name
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE s.id = ?
    LIMIT 1
");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();

if (!$student) {
    header('Location: idnotfound-404.php?source=students-dtr&id=' . urlencode($student_id));
    exit;
}

$att_stmt = $conn->prepare("
    SELECT attendance_date, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out, total_hours, status
    FROM attendances
    WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
    ORDER BY attendance_date ASC
");
$att_stmt->bind_param("iss", $student_id, $month_start, $month_end);
$att_stmt->execute();
$att_res = $att_stmt->get_result();

$records_by_day = [];
$month_total_hours = 0.0;
while ($row = $att_res->fetch_assoc()) {
    $day = (int)date('j', strtotime($row['attendance_date']));
    $records_by_day[$day] = $row;
    $month_total_hours += (float)($row['total_hours'] ?? 0);
}
$att_stmt->close();

$present_days = count($records_by_day);
$month_label = date('F Y', $month_ts);
$student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$assignment_track = strtolower((string)($student['assignment_track'] ?? 'internal'));
$total_hours_target = $assignment_track === 'external'
    ? (float)($student['external_total_hours'] ?? 0)
    : (float)($student['internal_total_hours'] ?? 0);
$stored_remaining_hours = $assignment_track === 'external'
    ? $student['external_total_hours_remaining']
    : $student['internal_total_hours_remaining'];
$total_hours_remaining = $stored_remaining_hours !== null
    ? max(0, (float)$stored_remaining_hours)
    : max(0, $total_hours_target - $month_total_hours);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt_time($time_value) {
    if (empty($time_value)) return '-';
    return date('h:i A', strtotime($time_value));
}

function fmt_hours_hm($hours_value) {
    $minutes_total = (int)max(0, round(((float)$hours_value) * 60));
    $hours = intdiv($minutes_total, 60);
    $minutes = $minutes_total % 60;
    return str_pad((string)$hours, 2, '0', STR_PAD_LEFT) . 'h:' . str_pad((string)$minutes, 2, '0', STR_PAD_LEFT) . 'm';
}

function status_badge($status) {
    $s = strtolower((string)$status);
    if ($s === 'approved') return '<span class="badge bg-soft-success text-success">Approved</span>';
    if ($s === 'rejected') return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
    return '<span class="badge bg-soft-warning text-warning">Pending</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Student DTR</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        .dtr-summary-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 14px;
            background: #fff;
            height: 100%;
        }
        .dtr-summary-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 6px;
        }
        .dtr-summary-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
        }
        .dtr-table th {
            white-space: nowrap;
            font-size: 12px;
        }
        .dtr-table td {
            vertical-align: middle;
            font-size: 12px;
        }
        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .student-meta-highlight {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
        .student-meta-highlight .chip {
            background: #edf4ff;
            border: 1px solid #b9d4ff;
            color: #1f3c6b;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 700;
            font-size: 12px;
        }
    </style>
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Daily Time Record</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="students-view.php?id=<?php echo intval($student_id); ?>">Student Profile</a></li>
                        <li class="breadcrumb-item">DTR</li>
                    </ul>
                </div>
            </div>

            <div class="main-content">
                <div class="card stretch stretch-full mb-3">
                    <div class="card-body">
                        <div class="toolbar">
                            <div>
                                <h5 class="mb-1"><?php echo h($student_name); ?></h5>
                                <div class="student-meta-highlight">
                                    <span class="chip">Student ID: <?php echo h($student['student_id']); ?></span>
                                    <span class="chip">Course: <?php echo h($student['course_name'] ?? 'N/A'); ?></span>
                                    <span class="chip">Track: <?php echo h(strtoupper((string)($student['assignment_track'] ?? 'internal'))); ?></span>
                                </div>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <form method="get" action="" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="id" value="<?php echo intval($student_id); ?>">
                                    <input type="month" name="month" class="form-control" value="<?php echo h($month_input); ?>">
                                    <button type="submit" class="btn btn-light-brand">Load</button>
                                </form>
                                <a href="students-view.php?id=<?php echo intval($student_id); ?>" class="btn btn-outline-primary">Back</a>
                                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="dtr-summary-card">
                            <div class="dtr-summary-label">Month</div>
                            <div class="dtr-summary-value"><?php echo h($month_label); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dtr-summary-card">
                            <div class="dtr-summary-label">Present Days</div>
                            <div class="dtr-summary-value"><?php echo intval($present_days); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dtr-summary-card">
                            <div class="dtr-summary-label">Total Hours Remaining</div>
                            <div class="dtr-summary-value"><?php echo h(fmt_hours_hm($total_hours_remaining)); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dtr-summary-card">
                            <div class="dtr-summary-label">Total Hours</div>
                            <div class="dtr-summary-value"><?php echo h(fmt_hours_hm($month_total_hours)); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h6 class="mb-0">Attendance History</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover dtr-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Date</th>
                                        <th>Morning In (AM)</th>
                                        <th>Morning Out (AM)</th>
                                        <th>Afternoon In (PM)</th>
                                        <th>Afternoon Out (PM)</th>
                                        <th>Total Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                                        <?php
                                        $row = $records_by_day[$day] ?? null;
                                        $date_iso = date('Y-m-', $month_ts) . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                                        ?>
                                        <tr>
                                            <td><?php echo $day; ?></td>
                                            <td><?php echo h(date('M d, Y', strtotime($date_iso))); ?></td>
                                            <td><?php echo $row ? h(fmt_time($row['morning_time_in'])) : '-'; ?></td>
                                            <td><?php echo $row ? h(fmt_time($row['morning_time_out'])) : '-'; ?></td>
                                            <td><?php echo $row ? h(fmt_time($row['afternoon_time_in'])) : '-'; ?></td>
                                            <td><?php echo $row ? h(fmt_time($row['afternoon_time_out'])) : '-'; ?></td>
                                            <td><?php echo $row ? h(fmt_hours_hm((float)($row['total_hours'] ?? 0))) : '00h:00m'; ?></td>
                                            <td><?php echo $row ? status_badge($row['status']) : '<span class="badge bg-soft-secondary text-secondary">No Record</span>'; ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <?php include 'includes/header.php';?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
