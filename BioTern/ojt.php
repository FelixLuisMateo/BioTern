<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (file_exists(__DIR__ . '/lib/ops_helpers.php')) {
    require_once __DIR__ . '/lib/ops_helpers.php';
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$current_user_id = intval($_SESSION['user_id'] ?? 0);

function ojt_table_exists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function safe_pct($num, $den) {
    $d = (float)$den;
    if ($d <= 0) return 0;
    $p = round(((float)$num / $d) * 100, 1);
    if ($p < 0) return 0;
    if ($p > 100) return 100;
    return $p;
}

function pipeline_stage(array $row): string {
    $has_app = !empty($row['has_application']);
    $has_endorse = !empty($row['has_endorsement']);
    $has_moa = !empty($row['has_moa']);
    $wf_app = strtolower((string)($row['wf_application'] ?? ''));
    $wf_endorse = strtolower((string)($row['wf_endorsement'] ?? ''));
    $wf_moa = strtolower((string)($row['wf_moa'] ?? ''));
    $intern = strtolower((string)($row['internship_status'] ?? ''));
    $progress = (float)($row['progress_pct'] ?? 0);

    if ($intern === 'completed' || $progress >= 100) return 'Completed';
    if ($intern === 'ongoing') return 'Ongoing';
    if ($wf_moa === 'approved' || $has_moa) return 'Accepted';
    if ($wf_endorse === 'approved' || $has_endorse) return 'Endorsed';
    if ($wf_app === 'approved' || $has_app) return 'Applied';
    return 'Applied';
}

function stage_badge_class(string $stage): string {
    $map = [
        'Applied' => 'bg-soft-warning text-warning',
        'Endorsed' => 'bg-soft-info text-info',
        'Accepted' => 'bg-soft-primary text-primary',
        'Ongoing' => 'bg-soft-success text-success',
        'Completed' => 'bg-soft-success text-success',
        'Dropped' => 'bg-soft-danger text-danger'
    ];
    return $map[$stage] ?? 'bg-soft-secondary text-secondary';
}

$conn->query("CREATE TABLE IF NOT EXISTS ojt_reminder_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reminder_type VARCHAR(100) NOT NULL,
    payload TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    queued_by INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(student_id),
    INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$conn->query("CREATE TABLE IF NOT EXISTS document_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    review_notes TEXT NULL,
    approved_by INT NOT NULL DEFAULT 0,
    approved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_doc (user_id, doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$search = trim((string)($_GET['search'] ?? ''));
$course_filter = trim((string)($_GET['course'] ?? ''));
$status_filter = trim((string)($_GET['stage'] ?? ''));
$risk_filter = trim((string)($_GET['risk'] ?? 'all'));

$courses = [];
$cres = $conn->query('SELECT id, name FROM courses ORDER BY name');
if ($cres) {
    while ($c = $cres->fetch_assoc()) {
        $courses[] = $c;
    }
}

$sql = "
SELECT
    s.id,
    s.student_id,
    s.first_name,
    s.last_name,
    s.email,
    s.phone,
    s.status AS student_status,
    s.created_at,
    s.profile_picture,
    c.name AS course_name,
    i.status AS internship_status,
    i.required_hours,
    i.rendered_hours,
    i.start_date,
    i.end_date,
    COALESCE(u_supervisor.name, s.supervisor_name) AS supervisor_name,
    COALESCE(u_coordinator.name, s.coordinator_name) AS coordinator_name,
    COALESCE(app.has_application, 0) AS has_application,
    COALESCE(endorse.has_endorsement, 0) AS has_endorsement,
    COALESCE(moa.has_moa, 0) AS has_moa,
    COALESCE(dau.has_dau_moa, 0) AS has_dau_moa,
    COALESCE(wf.wf_application, '') AS wf_application,
    COALESCE(wf.wf_endorsement, '') AS wf_endorsement,
    COALESCE(wf.wf_moa, '') AS wf_moa,
    COALESCE(wf.wf_dau_moa, '') AS wf_dau_moa,
    COALESCE(att.last_attendance_date, '') AS last_attendance_date,
    COALESCE(att.pending_count, 0) AS pending_logs,
    COALESCE(att.total_hours, 0) AS attendance_total_hours
FROM students s
LEFT JOIN courses c ON s.course_id = c.id
LEFT JOIN internships i ON s.id = i.student_id AND i.status IN ('ongoing','completed')
LEFT JOIN users u_supervisor ON i.supervisor_id = u_supervisor.id
LEFT JOIN users u_coordinator ON i.coordinator_id = u_coordinator.id
LEFT JOIN (SELECT user_id, 1 AS has_application FROM application_letter GROUP BY user_id) app ON app.user_id = s.id
LEFT JOIN (SELECT user_id, 1 AS has_endorsement FROM endorsement_letter GROUP BY user_id) endorse ON endorse.user_id = s.id
LEFT JOIN (SELECT user_id, 1 AS has_moa FROM moa GROUP BY user_id) moa ON moa.user_id = s.id
LEFT JOIN (SELECT user_id, 1 AS has_dau_moa FROM dau_moa GROUP BY user_id) dau ON dau.user_id = s.id
LEFT JOIN (
    SELECT
        user_id,
        MAX(CASE WHEN doc_type = 'application' THEN status END) AS wf_application,
        MAX(CASE WHEN doc_type = 'endorsement' THEN status END) AS wf_endorsement,
        MAX(CASE WHEN doc_type = 'moa' THEN status END) AS wf_moa,
        MAX(CASE WHEN doc_type = 'dau_moa' THEN status END) AS wf_dau_moa
    FROM document_workflow
    GROUP BY user_id
) wf ON wf.user_id = s.id
LEFT JOIN (
    SELECT
        student_id,
        MAX(attendance_date) AS last_attendance_date,
        SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status <> 'rejected' OR status IS NULL THEN COALESCE(total_hours, 0) ELSE 0 END) AS total_hours
    FROM attendances
    GROUP BY student_id
) att ON att.student_id = s.id
ORDER BY s.first_name, s.last_name
";

$res = $conn->query($sql);
$rows = [];
$today = date('Y-m-d');

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $required = (float)($row['required_hours'] ?? 0);
        $rendered = (float)($row['rendered_hours'] ?? 0);
        if ($rendered <= 0) {
            $rendered = (float)($row['attendance_total_hours'] ?? 0);
        }
        $row['progress_pct'] = safe_pct($rendered, $required);
        $row['stage'] = pipeline_stage($row);

        $risk = [];
        if (empty($row['has_moa'])) $risk[] = 'No MOA';
        if (empty($row['has_endorsement'])) $risk[] = 'No Endorsement';
        if (($row['internship_status'] ?? '') === 'ongoing' && !empty($row['last_attendance_date'])) {
            $days = (int)floor((strtotime($today) - strtotime($row['last_attendance_date'])) / 86400);
            if ($days >= 3) $risk[] = 'No biometric logs 3+ days';
        }
        if (($row['internship_status'] ?? '') === 'ongoing' && $row['progress_pct'] < 50) {
            $risk[] = 'Low completion';
        }
        if ((int)($row['pending_logs'] ?? 0) > 0) {
            $risk[] = 'Pending attendance approvals';
        }
        $row['risk_flags'] = $risk;
        $risk_score = 0;
        if (empty($row['has_moa'])) $risk_score += 25;
        if (empty($row['has_endorsement'])) $risk_score += 20;
        if (($row['internship_status'] ?? '') === 'ongoing' && $row['progress_pct'] < 50) $risk_score += 25;
        if ((int)($row['pending_logs'] ?? 0) > 0) $risk_score += 15;
        if (($row['internship_status'] ?? '') === 'ongoing' && !empty($row['last_attendance_date'])) {
            $days = (int)floor((strtotime($today) - strtotime($row['last_attendance_date'])) / 86400);
            if ($days >= 3) $risk_score += 15;
        }
        if ($risk_score > 100) $risk_score = 100;
        $row['risk_score'] = $risk_score;

        $haystack = strtolower(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['student_id'] ?? '') . ' ' . ($row['course_name'] ?? '')));
        if ($search !== '' && strpos($haystack, strtolower($search)) === false) {
            continue;
        }
        if ($course_filter !== '' && strcasecmp((string)($row['course_name'] ?? ''), $course_filter) !== 0) {
            continue;
        }
        if ($status_filter !== '' && strcasecmp($row['stage'], $status_filter) !== 0) {
            continue;
        }
        if ($risk_filter === 'at_risk' && count($risk) === 0) {
            continue;
        }
        if ($risk_filter === 'clean' && count($risk) > 0) {
            continue;
        }

        $rows[] = $row;
    }
}

usort($rows, function($a, $b) {
    return intval($b['risk_score'] ?? 0) <=> intval($a['risk_score'] ?? 0);
});

$total = count($rows);
$ongoing = count(array_filter($rows, fn($r) => ($r['stage'] ?? '') === 'Ongoing'));
$completed = count(array_filter($rows, fn($r) => ($r['stage'] ?? '') === 'Completed'));
$at_risk = count(array_filter($rows, fn($r) => count($r['risk_flags'] ?? []) > 0));
$avg_progress = 0;
if ($total > 0) {
    $sum = 0;
    foreach ($rows as $r) $sum += (float)$r['progress_pct'];
    $avg_progress = round($sum / $total, 1);
}

$trend_active_7d = 0;
$trend_at_risk_7d = 0;
$trend_pending_7d = 0;
$trend_avg_approval_hours = 0;
if (ojt_table_exists($conn, 'attendances')) {
    $res_t = $conn->query("
        SELECT
            COUNT(DISTINCT CASE WHEN attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN student_id END) AS active_7d,
            SUM(CASE WHEN attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND (status = 'pending' OR status IS NULL) THEN 1 ELSE 0 END) AS pending_7d,
            AVG(CASE WHEN status = 'approved' AND updated_at IS NOT NULL AND created_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, updated_at) END) AS avg_approval_min
        FROM attendances
    ");
    if ($res_t) {
        $t = $res_t->fetch_assoc();
        $trend_active_7d = intval($t['active_7d'] ?? 0);
        $trend_pending_7d = intval($t['pending_7d'] ?? 0);
        $trend_avg_approval_hours = round((float)($t['avg_approval_min'] ?? 0) / 60, 2);
    }
}
$trend_at_risk_7d = $at_risk;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['queue_reminders'])) {
    foreach ($rows as $r) {
        foreach (($r['risk_flags'] ?? []) as $flag) {
            $payload = json_encode([
                'student_id' => intval($r['id']),
                'student' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                'flag' => $flag,
                'risk_score' => intval($r['risk_score'] ?? 0)
            ], JSON_UNESCAPED_UNICODE);
            $stmt_q = $conn->prepare("INSERT INTO ojt_reminder_queue (student_id, reminder_type, payload, status, queued_by) VALUES (?, ?, ?, 'pending', ?)");
            $rtype = 'risk_flag';
            $sid = intval($r['id']);
            $stmt_q->bind_param('issi', $sid, $rtype, $payload, $current_user_id);
            $stmt_q->execute();
            $stmt_q->close();
        }
    }
    header('Location: ojt.php?queued=1');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ojt_dashboard_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Course', 'Stage', 'Risk Score', 'Risk Flags', 'Required Hours', 'Rendered Hours', 'Last Attendance']);
    foreach ($rows as $r) {
        $required = (float)($r['required_hours'] ?? 0);
        $rendered = (float)($r['rendered_hours'] ?? 0);
        if ($rendered <= 0) $rendered = (float)($r['attendance_total_hours'] ?? 0);
        fputcsv($out, [
            $r['student_id'] ?? '',
            trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            $r['course_name'] ?? '',
            $r['stage'] ?? '',
            intval($r['risk_score'] ?? 0),
            implode('; ', $r['risk_flags'] ?? []),
            $required,
            $rendered,
            $r['last_attendance_date'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || OJT Dashboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <script>
        (function(){
            try{
                var s = localStorage.getItem('app-skin-dark') || localStorage.getItem('app-skin') || localStorage.getItem('app_skin') || localStorage.getItem('theme');
                if (s && (s.indexOf && s.indexOf('dark') !== -1 || s === 'app-skin-dark')) {
                    document.documentElement.classList.add('app-skin-dark');
                }
            }catch(e){}
        })();
    </script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <script>try{var s=localStorage.getItem('app-skin')||localStorage.getItem('app_skin')||localStorage.getItem('theme'); if(s&&s.indexOf('dark')!==-1)document.documentElement.classList.add('app-skin-dark');}catch(e){};</script>
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        body { background: #f5f7fb; }
        .kpi-value { font-size: 1.5rem; font-weight: 700; }
        .chip { border: 1px solid #dbe3f0; border-radius: 999px; padding: 3px 10px; font-size: 12px; display: inline-block; margin: 2px 4px 2px 0; }
        .chip.ok { border-color: #198754; color: #198754; }
        .chip.miss { border-color: #dc3545; color: #dc3545; }
        .risk-pill { background: #fff4e5; color: #996000; border: 1px solid #ffe3b3; border-radius: 999px; padding: 2px 8px; font-size: 11px; display: inline-block; margin: 2px 4px 2px 0; }
        .filter-card { border: 1px solid #e9eef7; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05); }
        .table td, .table th { vertical-align: middle; }
        .student-link { color: inherit; text-decoration: none; }
        .student-link:hover { color: inherit; text-decoration: none; opacity: 0.95; }
        .card { border: 1px solid #e8edf6; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); }
        .page-subtitle { font-size: 12px; color: #6c7a92; margin-top: -2px; }
        #ojtListTable thead th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: #6c7a92; }
    </style>
</head>
<body>
<?php include_once 'includes/navigation.php'; ?>
<header class="nxl-header">
    <div class="header-wrapper">
        <div class="header-left d-flex align-items-center gap-4">
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box"><div class="hamburger-inner"></div></div>
                </div>
            </a>
            <div class="nxl-navigation-toggle">
                <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
            </div>
        </div>
        <div class="header-right ms-auto">
            <div class="d-flex align-items-center">
                <div class="dropdown nxl-h-item nxl-header-search">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <i class="feather-search"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                        <div class="input-group search-form">
                            <span class="input-group-text"><i class="feather-search fs-6 text-muted"></i></span>
                            <input type="text" class="form-control search-input-field" placeholder="Search....">
                            <span class="input-group-text"><button type="button" class="btn-close"></button></span>
                        </div>
                    </div>
                </div>
                <div class="nxl-h-item d-none d-sm-flex">
                    <div class="full-screen-switcher">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                            <i class="feather-maximize maximize"></i>
                            <i class="feather-minimize minimize"></i>
                        </a>
                    </div>
                </div>
                <div class="nxl-h-item dark-light-theme">
                    <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button"><i class="feather-moon"></i></a>
                    <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none"><i class="feather-sun"></i></a>
                </div>
                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <i class="feather-clock"></i>
                        <span class="badge bg-success nxl-h-badge">2</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-timesheets-menu">
                        <div class="d-flex justify-content-between align-items-center timesheets-head">
                            <h6 class="fw-bold text-dark mb-0">Timesheets</h6>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-column timesheets-body">
                            <i class="feather-clock fs-1 mb-4"></i>
                            <p class="text-muted">No started timers found yet.</p>
                        </div>
                    </div>
                </div>
                <div class="dropdown nxl-h-item">
                    <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                        <i class="feather-bell"></i>
                        <span class="badge bg-danger nxl-h-badge">3</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                        <div class="d-flex justify-content-between align-items-center notifications-head">
                            <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                        </div>
                    </div>
                </div>
                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar me-0">
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Biometric Internship Monitoring Dashboard</h5>
                    <div class="page-subtitle">Clark College of Science and Technology</div>
                </div>
            </div>
            <div class="page-header-right ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#kpiPanel" aria-expanded="false" aria-controls="kpiPanel">
                    <i class="feather-bar-chart-2 me-1"></i>Metrics Summary
                </button>
                <a href="ojt-workflow-board.php" class="btn btn-outline-primary"><i class="feather-kanban me-1"></i>Workflow Board</a>
                <a href="ojt.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-light"><i class="feather-download me-1"></i>Export CSV Report</a>
                <form method="post" class="d-inline">
                    <button type="submit" name="queue_reminders" value="1" class="btn btn-warning"><i class="feather-bell me-1"></i>Queue Risk Reminders</button>
                </form>
                <a href="ojt-create.php" class="btn btn-primary"><i class="feather-plus me-1"></i>New OJT Assignment</a>
            </div>
        </div>
        <?php if (isset($_GET['queued'])): ?>
            <div class="alert alert-success py-2">Reminders queued successfully for flagged students.</div>
        <?php endif; ?>

        <div class="collapse mb-3" id="kpiPanel">
            <div class="row g-2 mb-2">
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Total Interns</div><div class="kpi-value"><?php echo $total; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Ongoing Internships</div><div class="kpi-value"><?php echo $ongoing; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Completed Internships</div><div class="kpi-value"><?php echo $completed; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">At-Risk Interns</div><div class="kpi-value"><?php echo $at_risk; ?></div><small class="text-muted">Average Progress: <?php echo $avg_progress; ?>%</small></div></div>
            </div>
            <div class="row g-2">
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">7-Day Active Interns</div><div class="kpi-value"><?php echo $trend_active_7d; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">7-Day At-Risk Snapshot</div><div class="kpi-value"><?php echo $trend_at_risk_7d; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">7-Day Pending Approvals</div><div class="kpi-value"><?php echo $trend_pending_7d; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Avg Approval Turnaround</div><div class="kpi-value"><?php echo number_format($trend_avg_approval_hours, 2); ?>h</div></div></div>
            </div>
        </div>

        <div class="card card-body filter-card mb-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search Student</label>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name / Student ID / Course">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['name']); ?>" <?php echo ($course_filter === $course['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stage</label>
                    <select name="stage" class="form-select">
                        <option value="">All</option>
                        <?php foreach (['Applied','Endorsed','Accepted','Ongoing','Completed'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($status_filter === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Risk</label>
                    <select name="risk" class="form-select">
                        <option value="all" <?php echo ($risk_filter === 'all') ? 'selected' : ''; ?>>All</option>
                        <option value="at_risk" <?php echo ($risk_filter === 'at_risk') ? 'selected' : ''; ?>>At Risk</option>
                        <option value="clean" <?php echo ($risk_filter === 'clean') ? 'selected' : ''; ?>>Clean</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                    <a href="ojt.php" class="btn btn-light w-100">Reset</a>
                </div>
            </form>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="ojtListTable">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Pipeline</th>
                            <th>Document Progress</th>
                            <th>Hours</th>
                            <th>Risk</th>
                            <th>Risk Score</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No records found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $index => $r): ?>
                            <?php
                            $profile = trim((string)($r['profile_picture'] ?? ''));
                            $img = 'assets/images/avatar/' . (($index % 5) + 1) . '.png';
                            if ($profile !== '' && file_exists(__DIR__ . '/' . $profile)) {
                                $img = $profile . '?v=' . filemtime(__DIR__ . '/' . $profile);
                            }
                            $required = (float)($r['required_hours'] ?? 0);
                            $rendered = (float)($r['rendered_hours'] ?? 0);
                            if ($rendered <= 0) $rendered = (float)($r['attendance_total_hours'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <a class="student-link d-flex align-items-center gap-2" href="ojt-view.php?id=<?php echo (int)$r['id']; ?>">
                                        <img src="<?php echo htmlspecialchars($img); ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;" alt="profile">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></div>
                                            <div class="text-muted fs-12"><?php echo htmlspecialchars($r['student_id'] ?? ''); ?> | <?php echo htmlspecialchars($r['course_name'] ?? '-'); ?></div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge <?php echo stage_badge_class($r['stage']); ?>"><?php echo htmlspecialchars($r['stage']); ?></span>
                                    <div class="text-muted fs-12 mt-1">Last biometric: <?php echo htmlspecialchars($r['last_attendance_date'] ?: 'none'); ?></div>
                                </td>
                                <td>
                                    <span class="chip <?php echo !empty($r['has_application']) ? 'ok' : 'miss'; ?>">Application (<?php echo htmlspecialchars($r['wf_application'] ?: 'draft'); ?>)</span>
                                    <span class="chip <?php echo !empty($r['has_endorsement']) ? 'ok' : 'miss'; ?>">Endorsement (<?php echo htmlspecialchars($r['wf_endorsement'] ?: 'draft'); ?>)</span>
                                    <span class="chip <?php echo !empty($r['has_moa']) ? 'ok' : 'miss'; ?>">MOA (<?php echo htmlspecialchars($r['wf_moa'] ?: 'draft'); ?>)</span>
                                    <span class="chip <?php echo !empty($r['has_dau_moa']) ? 'ok' : 'miss'; ?>">DAU MOA (<?php echo htmlspecialchars($r['wf_dau_moa'] ?: 'draft'); ?>)</span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo number_format($rendered, 1); ?> / <?php echo number_format($required, 1); ?></div>
                                    <div class="progress" style="height:6px;"><div class="progress-bar" style="width:<?php echo (float)$r['progress_pct']; ?>%"></div></div>
                                    <div class="text-muted fs-12"><?php echo (float)$r['progress_pct']; ?>%</div>
                                </td>
                                <td>
                                    <?php if (empty($r['risk_flags'])): ?>
                                        <span class="text-success fs-12">No critical flags</span>
                                    <?php else: ?>
                                        <?php foreach ($r['risk_flags'] as $rf): ?><span class="risk-pill"><?php echo htmlspecialchars($rf); ?></span><?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-soft-danger text-danger"><?php echo intval($r['risk_score'] ?? 0); ?></span></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-light" href="ojt-view.php?id=<?php echo (int)$r['id']; ?>">View</a>
                                        <a class="btn btn-sm btn-outline-primary" href="ojt-edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-success" href="students-dtr.php?id=<?php echo (int)$r['id']; ?>">DTR</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="assets/vendors/js/vendors.min.js"></script>
<script src="assets/js/common-init.min.js"></script>
<script>
    (function () {
        var root = document.documentElement;
        var darkBtn = document.querySelector('.dark-button');
        var lightBtn = document.querySelector('.light-button');
        function applyTheme(isDark) {
            root.classList.toggle('app-skin-dark', isDark);
            try {
                localStorage.setItem('app-skin', isDark ? 'app-skin-dark' : 'app-skin-light');
                localStorage.setItem('app_skin', isDark ? 'app-skin-dark' : 'app-skin-light');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                if (isDark) localStorage.setItem('app-skin-dark', 'app-skin-dark');
                else localStorage.removeItem('app-skin-dark');
            } catch (e) {}
            if (darkBtn && lightBtn) {
                darkBtn.style.display = isDark ? 'none' : '';
                lightBtn.style.display = isDark ? '' : 'none';
            }
        }
        var isDark = root.classList.contains('app-skin-dark');
        applyTheme(isDark);
        if (darkBtn) darkBtn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(true); });
        if (lightBtn) lightBtn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(false); });
    })();
</script>
</body>
</html>
<?php $conn->close(); ?>
