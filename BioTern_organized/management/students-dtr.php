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
$prev_month_input = date('Y-m', strtotime('-1 month', $month_ts));

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

$page_title = 'BioTern || Student DTR';
include __DIR__ . '/../includes/header.php';
?>
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
    .dtr-mobile-list {
        display: none;
    }

    @media (max-width: 767.98px) {
        .toolbar {
            align-items: stretch;
        }
        .toolbar > div {
            width: 100%;
        }
        .toolbar .d-flex {
            width: 100%;
            flex-wrap: wrap;
        }
        .toolbar form {
            width: 100%;
            flex-wrap: wrap;
        }
        .toolbar input[type="month"],
        .toolbar form button,
        .toolbar .btn {
            width: 100%;
        }
        .dtr-summary-card {
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
            box-shadow: 0 6px 16px rgba(31, 60, 107, 0.08);
        }
        .student-meta-highlight .chip {
            width: 100%;
        }
        .dtr-desktop-table {
            display: none;
        }
        .dtr-mobile-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 12px;
            background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
        }
        .dtr-day-card {
            border: 1px solid #dbe8ff;
            border-radius: 14px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 6px 18px rgba(15, 34, 62, 0.06);
        }
        .dtr-day-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }
        .dtr-day-title {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #17345a;
            line-height: 1.3;
        }
        .dtr-slot-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }
        .dtr-slot {
            border: 1px solid #edf2fb;
            border-radius: 10px;
            padding: 8px;
            background: #fbfdff;
        }
        .dtr-slot-label {
            margin: 0;
            font-size: 11px;
            color: #627089;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .dtr-slot-value {
            margin: 2px 0 0;
            font-size: 13px;
            font-weight: 700;
            color: #1d2f4d;
        }
        .dtr-hours-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px dashed #d8e4f8;
            padding-top: 8px;
        }
        .dtr-hours-label {
            margin: 0;
            font-size: 12px;
            color: #61708a;
        }
        .dtr-hours-value {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #12375f;
        }
        .dtr-empty-note {
            margin: 8px 0 0;
            font-size: 12px;
            color: #8b94a7;
        font-style: italic;
        }
    }

    .app-skin-dark .dtr-summary-card {
        background: linear-gradient(135deg, #1c2432 0%, #111827 100%);
        border-color: #2a3448;
    }
    .app-skin-dark .dtr-summary-label {
        color: #93a4bf;
    }
    .app-skin-dark .dtr-summary-value {
        color: #e5edf8;
    }
    .app-skin-dark .student-meta-highlight .chip {
        background: #1b2a42;
        border-color: #30496f;
        color: #c9dbf8;
    }
    .app-skin-dark .dtr-mobile-list {
        background: linear-gradient(180deg, #111827 0%, #0f172a 100%);
    }
    .app-skin-dark .dtr-day-card {
        background: #1a2332;
        border-color: #2e3a51;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    }
    .app-skin-dark .dtr-day-title {
        color: #e8eefb;
    }
    .app-skin-dark .dtr-slot {
        background: #0f172a;
        border-color: #2d3a52;
    }
    .app-skin-dark .dtr-slot-label {
        color: #9aabc6;
    }
    .app-skin-dark .dtr-slot-value {
        color: #e2eaf8;
    }
    .app-skin-dark .dtr-hours-row {
        border-top-color: #334155;
    }
    .app-skin-dark .dtr-hours-label {
        color: #9aabc6;
    }
    .app-skin-dark .dtr-hours-value {
        color: #f3f7ff;
    }
    .app-skin-dark .dtr-empty-note {
        color: #8a9ab2;
    }
</style>

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
                    <a href="students-dtr.php?id=<?php echo intval($student_id); ?>&month=<?php echo h($prev_month_input); ?>" class="btn btn-outline-dark">Last Month</a>
                    <a href="students-view.php?id=<?php echo intval($student_id); ?>" class="btn btn-outline-primary">Back</a>
                    <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card">
                <div class="dtr-summary-label">Month</div>
                <div class="dtr-summary-value"><?php echo h($month_label); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card">
                <div class="dtr-summary-label">Present Days</div>
                <div class="dtr-summary-value"><?php echo intval($present_days); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="dtr-summary-card">
                <div class="dtr-summary-label">Total Hours Remaining</div>
                <div class="dtr-summary-value"><?php echo h(fmt_hours_hm($total_hours_remaining)); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
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
            <div class="table-responsive dtr-desktop-table">
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
            <div class="dtr-mobile-list">
                <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                    <?php
                    $row = $records_by_day[$day] ?? null;
                    $date_iso = date('Y-m-', $month_ts) . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                    ?>
                    <div class="dtr-day-card">
                        <div class="dtr-day-top">
                            <p class="dtr-day-title">Day <?php echo $day; ?> - <?php echo h(date('M d, Y', strtotime($date_iso))); ?></p>
                            <?php echo $row ? status_badge($row['status']) : '<span class="badge bg-soft-secondary text-secondary">No Record</span>'; ?>
                        </div>
                        <?php if ($row): ?>
                            <div class="dtr-slot-grid">
                                <div class="dtr-slot">
                                    <p class="dtr-slot-label">Morning In</p>
                                    <p class="dtr-slot-value"><?php echo h(fmt_time($row['morning_time_in'])); ?></p>
                                </div>
                                <div class="dtr-slot">
                                    <p class="dtr-slot-label">Morning Out</p>
                                    <p class="dtr-slot-value"><?php echo h(fmt_time($row['morning_time_out'])); ?></p>
                                </div>
                                <div class="dtr-slot">
                                    <p class="dtr-slot-label">Afternoon In</p>
                                    <p class="dtr-slot-value"><?php echo h(fmt_time($row['afternoon_time_in'])); ?></p>
                                </div>
                                <div class="dtr-slot">
                                    <p class="dtr-slot-label">Afternoon Out</p>
                                    <p class="dtr-slot-value"><?php echo h(fmt_time($row['afternoon_time_out'])); ?></p>
                                </div>
                            </div>
                            <div class="dtr-hours-row">
                                <p class="dtr-hours-label">Total Hours</p>
                                <p class="dtr-hours-value"><?php echo h(fmt_hours_hm((float)($row['total_hours'] ?? 0))); ?></p>
                            </div>
                        <?php else: ?>
                            <p class="dtr-empty-note">No attendance logs for this day.</p>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php $conn->close(); ?>


