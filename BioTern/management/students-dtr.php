<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    header('Location: idnotfound-404.php?source=students-dtr&id=' . urlencode($student_id));
    exit;
}

$month_invalid = false;
$range_invalid = false;
$month_input = isset($_GET['month']) ? trim((string)$_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_input)) {
    $month_invalid = true;
    $month_input = date('Y-m');
}

$month_start = $month_input . '-01';
$month_ts = strtotime($month_start);
if ($month_ts === false) {
    $month_invalid = true;
    $month_input = date('Y-m');
    $month_start = $month_input . '-01';
    $month_ts = strtotime($month_start);
}
$month_end = date('Y-m-t', $month_ts);
$days_in_month = (int)date('t', $month_ts);
$prev_month_input = date('Y-m', strtotime('-1 month', $month_ts));
$start_date_input = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : $month_start;
$end_date_input = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : $month_end;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_input) || strtotime($start_date_input) === false) {
    $range_invalid = true;
    $start_date_input = $month_start;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_input) || strtotime($end_date_input) === false) {
    $range_invalid = true;
    $end_date_input = $month_end;
}
if (strtotime($end_date_input) < strtotime($start_date_input)) {
    $range_invalid = true;
    $start_date_input = $month_start;
    $end_date_input = $month_end;
}

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
$att_stmt->bind_param("iss", $student_id, $start_date_input, $end_date_input);
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
$selected_range_label = date('M d, Y', strtotime($start_date_input)) . ' to ' . date('M d, Y', strtotime($end_date_input));
$student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
if ($student_name === '') {
    $student_name = 'Student #' . (string)($student['student_id'] ?? $student_id);
}
$viewer_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$is_student_viewer = ($viewer_role === 'student');
$profile_back_href = $is_student_viewer ? 'student-profile.php' : ('students-view.php?id=' . intval($student_id));
$profile_back_label = $is_student_viewer ? 'Back to My Profile' : 'Back to Profile';
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
$total_hours_completed = max(0, $total_hours_target - $total_hours_remaining);

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

function fmt_hours_compact($hours_value) {
    $formatted = number_format((float)$hours_value, 2, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted !== '' ? $formatted : '0';
}

function status_badge($status) {
    $s = strtolower((string)$status);
    if ($s === 'approved') return '<span class="badge bg-soft-success text-success">Approved</span>';
    if ($s === 'rejected') return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
    return '<span class="badge bg-soft-warning text-warning">Pending</span>';
}

$page_title = 'BioTern || Student Internal DTR';
$page_styles = ['assets/css/modules/management/management-students-dtr.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">

<div class="page-header app-students-dtr-page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Student Internal Daily Time Record</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo h($profile_back_href); ?>"><?php echo $is_student_viewer ? 'My Profile' : 'Student Profile'; ?></a></li>
            <li class="breadcrumb-item">Internal DTR</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="d-flex align-items-center gap-2">
            <a href="students-internal-dtr.php?id=<?php echo intval($student_id); ?>&month=<?php echo h($prev_month_input); ?>" class="btn btn-light-brand">
                <i class="feather-corner-up-left me-2"></i>
                <span>Last Month</span>
            </a>
            <a href="<?php echo h($profile_back_href); ?>" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-2"></i>
                <span><?php echo h($profile_back_label); ?></span>
            </a>
            <a href="document_dtr.php?track=internal&student_id=<?php echo intval($student_id); ?>&start_date=<?php echo h($start_date_input); ?>&end_date=<?php echo h($end_date_input); ?>" target="_blank" rel="noopener" class="btn btn-primary">
                <i class="feather-printer me-2"></i>
                <span>Print Internal DTR</span>
            </a>
        </div>
    </div>
</div>

<div class="main-content">
    <?php if ($month_invalid): ?>
        <div class="alert alert-info py-2">Invalid month format detected. Showing current month instead.</div>
    <?php endif; ?>
    <?php if ($range_invalid): ?>
        <div class="alert alert-info py-2">Invalid date range detected. Showing the selected month range instead.</div>
    <?php endif; ?>
    <div class="card stretch stretch-full mb-3 app-students-dtr-hero-card">
        <div class="card-body">
            <div class="toolbar app-students-dtr-toolbar">
                <div class="app-students-dtr-hero-copy">
                    <p class="app-students-dtr-overline">Internal Attendance Overview</p>
                    <h5 class="mb-1"><?php echo h($student_name); ?></h5>
                    <p class="app-students-dtr-copy-note mb-0">Review internal attendance logs, selected range totals, and printable date coverage without wasting space.</p>
                    <div class="student-meta-highlight app-students-dtr-meta-highlight">
                        <span class="chip app-students-dtr-chip">Student ID: <?php echo h($student['student_id']); ?></span>
                        <span class="chip app-students-dtr-chip">Course: <?php echo h($student['course_name'] ?? 'N/A'); ?></span>
                        <span class="chip app-students-dtr-chip">Track: <?php echo h(strtoupper((string)($student['assignment_track'] ?? 'internal'))); ?></span>
                    </div>
                </div>
                    <div class="app-students-dtr-toolbar-side">
                        <form method="get" action="" class="d-flex gap-2 align-items-center app-students-dtr-filter-form">
                            <input type="hidden" name="id" value="<?php echo intval($student_id); ?>">
                            <div class="app-students-dtr-filter-field">
                                <label class="app-students-dtr-filter-label" for="students-dtr-month">Month</label>
                                <input id="students-dtr-month" type="month" name="month" class="form-control" value="<?php echo h($month_input); ?>">
                            </div>
                            <div class="app-students-dtr-filter-field">
                                <label class="app-students-dtr-filter-label" for="students-dtr-start">Start</label>
                                <input id="students-dtr-start" type="date" name="start_date" class="form-control" value="<?php echo h($start_date_input); ?>">
                            </div>
                            <div class="app-students-dtr-filter-field">
                                <label class="app-students-dtr-filter-label" for="students-dtr-end">End</label>
                                <input id="students-dtr-end" type="date" name="end_date" class="form-control" value="<?php echo h($end_date_input); ?>">
                            </div>
                        <button type="submit" class="btn btn-light-brand">Load</button>
                    </form>
                    <div class="app-students-dtr-hours-chip">
                        <p class="app-students-dtr-hours-chip-label mb-0">Internal Hours Snapshot</p>
                        <div class="app-students-dtr-hours-chip-pill">
                            <span class="app-students-dtr-hours-chip-value"><?php echo h(fmt_hours_compact($total_hours_completed)); ?> / <?php echo h(fmt_hours_compact($total_hours_target)); ?></span>
                        </div>
                        <p class="app-students-dtr-hours-chip-meta mb-0">Remaining: <?php echo h(fmt_hours_hm($total_hours_remaining)); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card app-students-dtr-summary-card">
                <div class="dtr-summary-label app-students-dtr-summary-label">Date Range</div>
                <div class="dtr-summary-value app-students-dtr-summary-value"><?php echo h($selected_range_label); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card app-students-dtr-summary-card">
                <div class="dtr-summary-label app-students-dtr-summary-label">Present Days</div>
                <div class="dtr-summary-value app-students-dtr-summary-value"><?php echo intval($present_days); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card app-students-dtr-summary-card">
                <div class="dtr-summary-label app-students-dtr-summary-label">Total Hours Remaining</div>
                <div class="dtr-summary-value app-students-dtr-summary-value"><?php echo h(fmt_hours_hm($total_hours_remaining)); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card app-students-dtr-summary-card">
                <div class="dtr-summary-label app-students-dtr-summary-label">Logged In Range</div>
                <div class="dtr-summary-value app-students-dtr-summary-value"><?php echo h(fmt_hours_hm($month_total_hours)); ?></div>
            </div>
        </div>
    </div>

    <div class="card stretch stretch-full">
        <div class="card-header">
            <h6 class="mb-0">Internal Attendance History</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive dtr-desktop-table app-students-dtr-desktop-table">
                <table class="table table-hover dtr-table app-students-dtr-table mb-0">
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
                        <?php
                        $range_start_ts = strtotime($start_date_input);
                        $range_end_ts = strtotime($end_date_input);
                        for ($cursor = $range_start_ts; $cursor !== false && $range_end_ts !== false && $cursor <= $range_end_ts; $cursor = strtotime('+1 day', $cursor)):
                            $date_iso = date('Y-m-d', $cursor);
                            $day = (int)date('j', $cursor);
                            $row = $records_by_day[$day] ?? null;
                            if ($row && (string)($row['attendance_date'] ?? '') !== $date_iso) {
                                $row = null;
                            }
                            ?>
                            <tr>
                                <td data-label="Day"><?php echo $day; ?></td>
                                <td data-label="Date"><?php echo h(date('M d, Y', strtotime($date_iso))); ?></td>
                                <td data-label="Morning In (AM)"><?php echo $row ? h(fmt_time($row['morning_time_in'])) : '-'; ?></td>
                                <td data-label="Morning Out (AM)"><?php echo $row ? h(fmt_time($row['morning_time_out'])) : '-'; ?></td>
                                <td data-label="Afternoon In (PM)"><?php echo $row ? h(fmt_time($row['afternoon_time_in'])) : '-'; ?></td>
                                <td data-label="Afternoon Out (PM)"><?php echo $row ? h(fmt_time($row['afternoon_time_out'])) : '-'; ?></td>
                                <td data-label="Total Hours"><?php echo $row ? h(fmt_hours_hm((float)($row['total_hours'] ?? 0))) : '00h:00m'; ?></td>
                                <td data-label="Status"><?php echo $row ? status_badge($row['status']) : '<span class="badge bg-soft-secondary text-secondary">No Record</span>'; ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="dtr-mobile-list app-students-dtr-mobile-list">
                <?php for ($cursor = $range_start_ts; $cursor !== false && $range_end_ts !== false && $cursor <= $range_end_ts; $cursor = strtotime('+1 day', $cursor)): ?>
                    <?php
                    $date_iso = date('Y-m-d', $cursor);
                    $day = (int)date('j', $cursor);
                    $row = $records_by_day[$day] ?? null;
                    if ($row && (string)($row['attendance_date'] ?? '') !== $date_iso) {
                        $row = null;
                    }
                    ?>
                    <div class="dtr-day-card app-students-dtr-day-card">
                        <div class="dtr-day-top app-students-dtr-day-top">
                            <p class="dtr-day-title app-students-dtr-day-title">Day <?php echo $day; ?> - <?php echo h(date('M d, Y', strtotime($date_iso))); ?></p>
                            <?php echo $row ? status_badge($row['status']) : '<span class="badge bg-soft-secondary text-secondary">No Record</span>'; ?>
                        </div>
                        <?php if ($row): ?>
                            <div class="dtr-slot-grid app-students-dtr-slot-grid">
                                <div class="dtr-slot app-students-dtr-slot">
                                    <p class="dtr-slot-label app-students-dtr-slot-label">Morning In</p>
                                    <p class="dtr-slot-value app-students-dtr-slot-value"><?php echo h(fmt_time($row['morning_time_in'])); ?></p>
                                </div>
                                <div class="dtr-slot app-students-dtr-slot">
                                    <p class="dtr-slot-label app-students-dtr-slot-label">Morning Out</p>
                                    <p class="dtr-slot-value app-students-dtr-slot-value"><?php echo h(fmt_time($row['morning_time_out'])); ?></p>
                                </div>
                                <div class="dtr-slot app-students-dtr-slot">
                                    <p class="dtr-slot-label app-students-dtr-slot-label">Afternoon In</p>
                                    <p class="dtr-slot-value app-students-dtr-slot-value"><?php echo h(fmt_time($row['afternoon_time_in'])); ?></p>
                                </div>
                                <div class="dtr-slot app-students-dtr-slot">
                                    <p class="dtr-slot-label app-students-dtr-slot-label">Afternoon Out</p>
                                    <p class="dtr-slot-value app-students-dtr-slot-value"><?php echo h(fmt_time($row['afternoon_time_out'])); ?></p>
                                </div>
                            </div>
                            <div class="dtr-hours-row app-students-dtr-hours-row">
                                <p class="dtr-hours-label app-students-dtr-hours-label">Total Hours</p>
                                <p class="dtr-hours-value app-students-dtr-hours-value"><?php echo h(fmt_hours_hm((float)($row['total_hours'] ?? 0))); ?></p>
                            </div>
                        <?php else: ?>
                            <p class="dtr-empty-note app-students-dtr-empty-note">No attendance logs for this day.</p>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>
<?php $conn->close(); ?>







