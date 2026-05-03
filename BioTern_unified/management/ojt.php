<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$avatar_helpers = dirname(__DIR__) . '/includes/avatar.php';
if (file_exists($avatar_helpers)) {
    require_once $avatar_helpers;
}
$ops_helpers = dirname(__DIR__) . '/lib/ops_helpers.php';
if (file_exists($ops_helpers)) {
    require_once $ops_helpers;
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

$conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$current_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
$current_user_role = trim((string)($_SESSION['role'] ?? ''));
$current_profile_rel = ltrim(str_replace('\\', '/', trim((string)($_SESSION['profile_picture'] ?? ''))), '/');
$current_profile_img = 'assets/images/avatar/' . (($current_user_id > 0 ? ($current_user_id % 5) : 0) + 1) . '.png';
if ($current_profile_rel !== '' && file_exists(dirname(__DIR__) . '/' . $current_profile_rel)) {
    $current_profile_img = $current_profile_rel;
}

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

function resolve_profile_image_url(string $profilePath): ?string {
    $resolved = function_exists('biotern_avatar_public_src')
        ? biotern_avatar_public_src($profilePath)
        : ltrim(str_replace('\\', '/', trim($profilePath)), '/');
    if ($resolved === '') {
        return null;
    }
    $rootPath = dirname(__DIR__) . '/' . $resolved;
    if (!file_exists($rootPath)) {
        return null;
    }
    $mtime = @filemtime($rootPath);
    return $resolved . ($mtime ? ('?v=' . $mtime) : '');
}

function ojt_student_initials(string $firstName, string $lastName): string {
    $tokens = [];
    foreach ([$firstName, $lastName] as $value) {
        $clean = trim((string)$value);
        if ($clean === '') {
            continue;
        }
        $parts = preg_split('/\s+/', $clean);
        $source = $parts && $parts[0] !== '' ? $parts[0] : $clean;
        $tokens[] = strtoupper(substr($source, 0, 1));
    }

    if ($tokens !== []) {
        return implode('', array_slice($tokens, 0, 2));
    }

    $fallback = trim((string)$firstName . ' ' . (string)$lastName);
    if ($fallback === '') {
        return 'BT';
    }

    $parts = preg_split('/\s+/', $fallback) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'BT';
}

function ojt_initials_avatar_uri(string $firstName, string $lastName): string {
    $initials = ojt_student_initials($firstName, $lastName);
    $seed = strtolower(trim($firstName . ' ' . $lastName));
    $palette = ['1d4ed8', '0f766e', 'b45309', 'be123c', '4338ca', '0f172a'];
    $background = $palette[abs(crc32($seed)) % count($palette)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
        . '<rect width="120" height="120" rx="60" fill="#' . $background . '"/>'
        . '<text x="60" y="68" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="42" font-weight="700" fill="#ffffff">'
        . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
        . '</text></svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function formatSectionDisplayLabel($code, $name): string {
    $code = trim((string)$code);
    $name = trim((string)$name);
    if ($code === '' && $name === '') {
        return '';
    }
    if ($code !== '' && $name !== '') {
        $compactName = strtoupper((string)preg_replace('/\s+/', '', $name));
        if (
            preg_match('/^([A-Za-z]+)\s*-?\s*([0-9]+[A-Za-z]?)$/', $code, $matches)
            && strtoupper($matches[2]) === $compactName
        ) {
            return strtoupper($matches[1]) . ' - ' . $name;
        }
        return $code . ' - ' . $name;
    }
    return $code !== '' ? $code : $name;
}

function normalize_person_name(string $name): string {
    $clean = trim(preg_replace('/\s+/', ' ', $name));
    if ($clean === '') {
        return '';
    }
    if (strpos($clean, ' ') === false) {
        $clean = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $clean);
    }
    return trim($clean);
}

function to_last_name_first(string $name): string {
    $clean = normalize_person_name($name);
    if ($clean === '') {
        return '';
    }
    $parts = preg_split('/\s+/', $clean);
    if (!$parts || count($parts) < 2) {
        return $clean;
    }
    $last = array_pop($parts);
    $first = trim(implode(' ', $parts));
    return $last . ', ' . $first;
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
$section_filter = trim((string)($_GET['section'] ?? ''));
$school_year_filter = trim((string)($_GET['school_year'] ?? ''));
$semester_filter = trim((string)($_GET['semester'] ?? ''));
$status_filter = trim((string)($_GET['stage'] ?? ''));
$risk_filter = trim((string)($_GET['risk'] ?? 'all'));
biotern_db_add_column_if_missing($conn, 'students', 'semester', "semester VARCHAR(30) DEFAULT NULL");
biotern_db_add_column_if_missing($conn, 'internships', 'semester', "semester VARCHAR(30) DEFAULT NULL");
$semester_options = ['1st Semester', '2nd Semester', 'Summer'];
$school_year_options = [];
$school_year_start = 2005;
$current_calendar_month = (int)date('n');
$current_calendar_year = (int)date('Y');
$current_school_year_start = $current_calendar_month >= 7 ? $current_calendar_year : ($current_calendar_year - 1);
$latest_school_year_start = max(2025, $current_school_year_start);
for ($year = $latest_school_year_start; $year >= $school_year_start; $year--) {
    $school_year_options[] = sprintf('%d-%d', $year, $year + 1);
}

$courses = [];
$cres = $conn->query('SELECT id, name FROM courses ORDER BY name');
if ($cres) {
    while ($c = $cres->fetch_assoc()) {
        $courses[] = $c;
    }
}

$sections = [];
$sres = $conn->query("SELECT id, code, name FROM sections ORDER BY code ASC, name ASC");
if ($sres) {
    while ($s = $sres->fetch_assoc()) {
        $s['section_label'] = formatSectionDisplayLabel($s['code'] ?? '', $s['name'] ?? '');
        $sections[] = $s;
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
    COALESCE(NULLIF(s.school_year, ''), '-') AS school_year,
    COALESCE(NULLIF(s.semester, ''), '-') AS semester,
    u_student.id AS account_user_id,
    COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
    c.name AS course_name,
    COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name,
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
LEFT JOIN users u_student ON s.user_id = u_student.id
LEFT JOIN courses c ON s.course_id = c.id
LEFT JOIN sections sec ON s.section_id = sec.id
LEFT JOIN internships i
    ON s.id = i.student_id
   AND i.status IN ('ongoing','completed')
   AND COALESCE(i.school_year, '') = COALESCE(s.school_year, '')
   AND COALESCE(i.semester, '') = COALESCE(s.semester, '')
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
WHERE COALESCE(u_student.application_status, 'approved') = 'approved'
ORDER BY s.last_name, s.first_name
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
        if ($section_filter !== '' && strcasecmp((string)($row['section_name'] ?? ''), $section_filter) !== 0) {
            continue;
        }
        if ($school_year_filter !== '' && strcasecmp((string)($row['school_year'] ?? ''), $school_year_filter) !== 0) {
            continue;
        }
        if ($semester_filter !== '' && strcasecmp((string)($row['semester'] ?? ''), $semester_filter) !== 0) {
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
    $a_name = strtolower(trim((string)($a['last_name'] ?? '') . ' ' . (string)($a['first_name'] ?? '')));
    $b_name = strtolower(trim((string)($b['last_name'] ?? '') . ' ' . (string)($b['first_name'] ?? '')));
    if ($a_name === $b_name) {
        return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
    }
    return strcmp($a_name, $b_name);
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
    $redirectParams = array_filter([
        'search' => $search,
        'course' => $course_filter,
        'section' => $section_filter,
        'school_year' => $school_year_filter,
        'semester' => $semester_filter,
        'stage' => $status_filter,
        'risk' => $risk_filter,
        'queued' => 1
    ], static function ($value) {
        return $value !== '' && $value !== null;
    });
    header('Location: ojt.php' . ($redirectParams ? ('?' . http_build_query($redirectParams)) : ''));
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

$print_ojt_rows = $rows;
usort($print_ojt_rows, function ($a, $b) {
    $a_last = strtolower((string)($a['last_name'] ?? ''));
    $b_last = strtolower((string)($b['last_name'] ?? ''));
    if ($a_last === $b_last) {
        return strcasecmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? ''));
    }
    return strcmp($a_last, $b_last);
});
$print_section_label = $section_filter !== '' ? $section_filter : 'ALL';
if ($semester_filter !== '') {
    $print_section_label .= ' / ' . $semester_filter;
}
if ($school_year_filter !== '') {
    $print_section_label .= ' / ' . $school_year_filter;
}

$page_title = 'BioTern || OJT Monitor';
$page_styles = array();
include 'includes/header.php';
?>
<style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f5f7fb;
        }
        main.nxl-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .nxl-content {
            flex: 1;
            padding-bottom: 18px;
        }
        footer.footer {
            margin-top: auto;
        }
        .nxl-content { padding-top: 12px; }
        .nxl-content > .page-header,
        .nxl-content > .alert,
        .nxl-content > .collapse,
        .nxl-content > .filter-card,
        .nxl-content > .stretch.stretch-full {
            margin-bottom: 14px !important;
        }
        .ojt-toolbar {
            margin-bottom: 12px;
            padding: 14px 16px;
            border: 1px solid #e4ebf7;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
            display: block;
        }
        .ojt-toolbar-left {
            min-width: 0;
            max-width: none;
        }
        .ojt-toolbar-right {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            align-content: flex-end;
            flex-wrap: wrap;
            row-gap: 6px;
            column-gap: 6px !important;
            width: 100%;
            margin-top: 12px;
        }
        .ojt-toolbar-right .btn,
        .ojt-toolbar-right a.btn,
        .ojt-toolbar-right form .btn {
            min-height: 32px;
            border-radius: 8px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .01em;
            padding: 0.28rem 0.58rem;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }
        .ojt-toolbar-right form {
            margin: 0;
            display: inline-flex;
        }
        .ojt-toolbar-right .btn i { font-size: 11px; }
        .ojt-toolbar-right .btn.btn-light,
        .ojt-toolbar-right .btn.btn-outline-secondary,
        .ojt-toolbar-right .btn.btn-outline-primary {
            border-color: #d5e0f2;
            background: #fdfefe;
        }
        .ojt-toolbar-right .btn.btn-light:hover,
        .ojt-toolbar-right .btn.btn-outline-secondary:hover,
        .ojt-toolbar-right .btn.btn-outline-primary:hover {
            background: #f4f8ff;
            border-color: #bdd1ee;
        }
        .kpi-value { font-size: 1.5rem; font-weight: 700; }
        .chip { border: 1px solid #dbe3f0; border-radius: 999px; padding: 3px 10px; font-size: 12px; display: inline-block; margin: 0; line-height: 1.2; max-width: 100%; white-space: normal; word-break: break-word; }
        .chip.ok { border-color: #198754; color: #198754; }
        .chip.miss { border-color: #dc3545; color: #dc3545; }
        .risk-pill { background: #fff4e5; color: #996000; border: 1px solid #ffe3b3; border-radius: 999px; padding: 2px 8px; font-size: 11px; display: inline-block; margin: 0; line-height: 1.2; max-width: 100%; white-space: normal; word-break: break-word; }
        .doc-progress-wrap,
        .risk-wrap {
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            gap: 6px;
            max-height: none;
            overflow: visible;
        }
        #ojtListTable th:nth-child(4), #ojtListTable td:nth-child(4) { min-width: 180px; }
        #ojtListTable th:nth-child(6), #ojtListTable td:nth-child(6) { min-width: 180px; }
        #ojtListTable td[data-label="Actions"] .d-flex {
            width: 100%;
            flex-wrap: wrap;
            gap: 6px !important;
        }
        #ojtListTable td[data-label="Actions"] .btn {
            flex: 1 1 96px;
            min-width: 0;
        }
        .filter-panel {
            border: 1px solid #dfe8f5;
            border-radius: 14px;
            padding: 1rem 1rem 0.4rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
            margin-bottom: 14px !important;
        }
        .filter-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid #e5edf7;
        }
        .filter-panel-head-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        .filter-panel-label {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #1e3a8a;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0;
        }
        .filter-panel-sub {
            font-size: 0.78rem;
            color: #64748b;
            margin: 0;
        }
        .filter-toggle-btn {
            border-color: #d5deed;
            color: #1e293b;
            background: #f8fbff;
        }
        .filter-toggle-btn:hover,
        .filter-toggle-btn:focus {
            background-color: #eef4ff;
            color: #0f172a;
            border-color: #b8c7e2;
        }
        .filter-form {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 0.65rem;
            align-items: end;
        }
        .filter-form > [class*="col-"] {
            width: 100%;
            max-width: 100%;
            padding-right: 0;
            padding-left: 0;
        }
        .filter-panel .filter-form {
            row-gap: 10px;
        }
        .filter-panel .form-control,
        .filter-panel .form-select {
            background: #ffffff;
            border-color: #d8e2f2;
            min-height: 42px;
        }
        .filter-panel .form-control:focus,
        .filter-panel .form-select:focus {
            border-color: #8cb3ea;
            box-shadow: 0 0 0 0.14rem rgba(58, 120, 220, 0.16);
        }
        .table td, .table th { vertical-align: top; }
        .student-link { color: inherit; text-decoration: none; }
        .student-link:hover { color: inherit; text-decoration: none; opacity: 0.95; }
        .card {
            border: 1px solid #e8edf6;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }
        .card.stretch.stretch-full {
            border-color: #dfe8f5;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            margin-bottom: 18px;
            height: auto !important;
            min-height: 0 !important;
            flex: 0 0 auto !important;
        }
        .page-subtitle { font-size: 12px; color: #6c7a92; margin-top: -2px; }
        .ojt-toolbar .page-subtitle {
            margin-top: 0;
            line-height: 1.4;
            max-width: 72ch;
        }
        #ojtListTable thead th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: #6c7a92; }
        #ojtListTable { width: 100%; min-width: 900px; table-layout: auto; }
        #ojtListTable thead th { background: #f8fbff; }
        #ojtListTable tbody tr:hover { background: #fbfdff; }
        #ojtListTable th,
        #ojtListTable td {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .student-link {
            display: flex;
            align-items: flex-start !important;
            gap: 0.7rem;
        }
        .student-link > div {
            min-width: 0;
        }
        .student-link .fw-semibold,
        .student-link .text-muted,
        .fs-12 {
            white-space: normal;
            overflow-wrap: anywhere;
        }
        #ojtListTable .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 100%;
            white-space: normal;
            text-align: center;
        }
        #ojtListTable .progress {
            min-width: 0;
            margin-top: 0.35rem;
        }
        #ojtListTable td[data-label="Risk Score"] .badge {
            min-width: 44px;
        }
        .app-skin-dark body { background: #0b1220; }
        .app-skin-dark .card,
        .app-skin-dark .filter-panel {
            border-color: #253252;
            background: #111a2e;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        }
        .app-skin-dark .ojt-toolbar {
            border-color: #253252;
            background: #111a2e;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        }
        .app-skin-dark .page-subtitle,
        .app-skin-dark #ojtListTable thead th {
            color: #99abc8;
        }
        .app-skin-dark #ojtListTable thead th {
            background: #0f1a2e;
            border-color: #26324f;
        }
        .app-skin-dark .ojt-toolbar-right .btn.btn-light,
        .app-skin-dark .ojt-toolbar-right .btn.btn-outline-secondary,
        .app-skin-dark .ojt-toolbar-right .btn.btn-outline-primary {
            background: #0f1a2e;
            border-color: #2b3b5e;
            color: #d7e3f7;
        }
        .app-skin-dark .ojt-toolbar-right .btn.btn-light:hover,
        .app-skin-dark .ojt-toolbar-right .btn.btn-outline-secondary:hover,
        .app-skin-dark .ojt-toolbar-right .btn.btn-outline-primary:hover {
            background: #12213a;
            border-color: #38507a;
        }
        .app-skin-dark .filter-panel .form-control,
        .app-skin-dark .filter-panel .form-select {
            background: #0f1a2e;
            border-color: #2b3b5e;
            color: #d7e3f7;
        }
        .filter-form .select2-container .select2-selection--single {
            min-height: 42px;
            display: flex;
            align-items: center;
        }
        .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
            text-align: left;
            padding-left: 0.15rem;
            padding-right: 1.75rem;
        }
        .filter-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        .app-skin-dark .filter-panel .form-control::placeholder {
            color: #9fb2d1;
        }
        .app-skin-dark .filter-panel-label {
            color: #dbeafe;
        }
        .app-skin-dark .filter-panel-sub {
            color: #94a3b8;
        }
        .app-skin-dark .filter-toggle-btn {
            background-color: #0f172a;
            color: #e2e8f0;
            border-color: #334155;
        }
        .app-skin-dark .filter-toggle-btn:hover,
        .app-skin-dark .filter-toggle-btn:focus {
            background-color: #1e293b;
            color: #f8fafc;
            border-color: #475569;
        }
        .app-skin-dark .chip { border-color: #314c72; color: #d4e2f9; }
        .app-skin-dark .risk-pill { background: #3f2e12; border-color: #7d5c1d; color: #ffd793; }
        .page-header .breadcrumb {
            margin-bottom: 0;
        }
        .page-header .page-header-title {
            border-right: 0 !important;
            padding-right: 0 !important;
            margin-right: 0 !important;
        }
        .page-header-left {
            align-items: center !important;
        }
        .page-header .page-header-title {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
        }
        .page-header .page-header-title h5 {
            margin: 0;
        }
        .page-header .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.15rem;
        }
        @media (max-width: 1279.98px) {
            .ojt-toolbar-right {
                display: flex !important;
                flex-wrap: nowrap;
                overflow-x: auto;
                overflow-y: hidden;
                gap: 8px !important;
                padding-bottom: 2px;
                scrollbar-width: thin;
                justify-content: flex-start;
            }
            .ojt-toolbar-right .btn,
            .ojt-toolbar-right a.btn,
            .ojt-toolbar-right form {
                flex: 0 0 auto;
            }
            .ojt-toolbar-right form .btn {
                width: auto;
            }
            #ojtListTable { min-width: 100%; }
            #ojtListTable thead { display: none; }
            #ojtListTable,
            #ojtListTable tbody,
            #ojtListTable tr,
            #ojtListTable td {
                display: block;
                width: 100%;
            }
            #ojtListTable tbody tr {
                margin: 10px;
                border: 1px solid #e3ebf9;
                border-radius: 14px;
                background: #fff;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
                padding: 12px;
            }
            .app-skin-dark #ojtListTable tbody tr {
                background: #111a2e;
                border-color: #253252;
            }
            #ojtListTable td {
                border: 0;
                padding: 0 0 10px 0;
            }
            #ojtListTable td::before {
                content: attr(data-label);
                display: block;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: #6c7a92;
                margin-bottom: 4px;
                font-weight: 700;
            }
            .app-skin-dark #ojtListTable td::before { color: #99abc8; }
            #ojtListTable td:last-child { padding-bottom: 0; }
            #ojtListTable td[colspan] {
                text-align: center;
                padding: 14px 8px;
            }
            #ojtListTable td[colspan]::before { display: none; }
            #ojtListTable td:last-child .d-flex {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px !important;
            }
            #ojtListTable td:last-child .btn { width: 100%; }
        }
        @media (max-width: 1199.98px) {
            .ojt-toolbar .page-subtitle {
                max-width: 100%;
            }
        }
        @media (max-width: 991.98px) {
            .ojt-toolbar-right { width: 100%; display: grid !important; grid-template-columns: 1fr 1fr; gap: 8px !important; overflow: visible; justify-content: stretch; }
            .ojt-toolbar-right .btn, .ojt-toolbar-right form, .ojt-toolbar-right form .btn { width: 100%; }
            .filter-form { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
            .nxl-content { padding-left: 10px; padding-right: 10px; }
        }
        @media (max-width: 575.98px) {
            .ojt-toolbar-right { grid-template-columns: 1fr; }
            .kpi-value { font-size: 1.25rem; }
            .chip, .risk-pill { font-size: 10px; padding: 2px 8px; }
            .student-link img { width: 36px !important; height: 36px !important; }
            .table td, .table th { padding: 0.5rem 0.45rem; font-size: 12px; }
            .header-right .nxl-h-item { display: none; }
            .header-right .dark-light-theme { display: block !important; }
            .filter-form { grid-template-columns: 1fr; }
        }
        @media (max-width: 767.98px) {
            .nxl-content { padding-left: 8px; padding-right: 8px; }
            .card { border-radius: 14px; }
            #ojtListTable tbody tr { margin: 8px; padding: 10px; }
            #ojtListTable td:last-child .d-flex {
                grid-template-columns: 1fr;
            }
        }

        .ojt-print-sheet { display: none; }
        @media print {
            body * { visibility: hidden !important; }
            .ojt-print-sheet, .ojt-print-sheet * { visibility: visible !important; }
            .ojt-print-sheet {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 7.45in;
                background: #fff;
                color: #111;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px;
                margin: 0 auto;
                padding: 12px 14px;
            }
            .ojt-print-sheet .header {
                position: relative;
                min-height: 0.9in;
                text-align: center;
                border-bottom: 1px solid #8ab0e6;
                padding: 0.08in 0 0.06in 0;
                margin-bottom: 14px;
            }
            .ojt-print-sheet .crest {
                position: absolute;
                top: 0.22in;
                left: 0.22in;
                width: 0.77in;
                height: 0.76in;
                object-fit: contain;
            }
            .ojt-print-sheet .header h2 {
                font-family: Calibri, Arial, sans-serif;
                color: #1b4f9c;
                font-size: 14pt;
                margin: 6px 0 2px 0;
                font-weight: 700;
                text-transform: uppercase;
            }
            .ojt-print-sheet .header .meta {
                font-family: Calibri, Arial, sans-serif;
                color: #1b4f9c;
                font-size: 10pt;
            }
            .ojt-print-sheet .header .tel {
                font-family: Calibri, Arial, sans-serif;
                color: #1b4f9c;
                font-size: 12pt;
            }
            .ojt-print-sheet .print-title {
                text-align: center;
                font-size: 24px;
                letter-spacing: 1px;
                font-weight: 700;
                margin: 18px 0 16px;
            }
            .ojt-print-sheet .print-meta {
                margin-bottom: 14px;
                font-size: 13px;
            }
            .ojt-print-sheet .print-meta strong {
                min-width: 76px;
                display: inline-block;
            }
            .ojt-print-sheet table {
                width: 100%;
                min-width: 100%;
                border-collapse: collapse;
                font-size: 12.5px;
                table-layout: auto;
            }
            .ojt-print-sheet th, .ojt-print-sheet td {
                border: 1px solid #d9d9d9;
                padding: 9px 8px;
                text-align: left;
            }
            .ojt-print-sheet th {
                text-transform: uppercase;
                font-weight: 700;
                background: #f8f8f8;
            }
            .ojt-print-sheet td.col-index,
            .ojt-print-sheet th.col-index {
                width: 46px;
                text-align: center;
            }
        }
    </style>
<section class="ojt-print-sheet">
    <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title">OJT STUDENT LIST</div>
    <div class="print-meta"><strong>SECTION:</strong> <?php
echo htmlspecialchars($print_section_label); ?></div>
    <table>
        <thead>
            <tr>
                <th class="col-index">#</th>
                <th>Student No.</th>
                <th>Student Name</th>
                <th>Course</th>
                <th>Section</th>
                <th>Supervisor</th>
                <th>Coordinator</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php
if (!empty($print_ojt_rows)): ?>
                <?php
foreach ($print_ojt_rows as $i => $r): ?>
                    <tr>
                        <td class="col-index"><?php
echo (int)$i + 1; ?></td>
                        <td><?php
echo htmlspecialchars((string)($r['student_id'] ?? '')); ?></td>
                        <?php
$student_last = normalize_person_name((string)($r['last_name'] ?? ''));
                        $student_first = normalize_person_name((string)($r['first_name'] ?? ''));
                        $student_name_lf = trim($student_last . ($student_last !== '' && $student_first !== '' ? ', ' : '') . $student_first);
                        ?>
                        <td><?php
echo htmlspecialchars($student_name_lf); ?></td>
                        <td><?php
echo htmlspecialchars((string)($r['course_name'] ?? '')); ?></td>
                        <td><?php
$printSection = trim((string)($r['section_name'] ?? '-'));
$printSemester = trim((string)($r['semester'] ?? ''));
$printSchoolYear = trim((string)($r['school_year'] ?? ''));
if ($printSemester !== '' && $printSemester !== '-') {
    $printSection .= ' / ' . $printSemester;
}
if ($printSchoolYear !== '' && $printSchoolYear !== '-') {
    $printSection .= ' / ' . $printSchoolYear;
}
echo htmlspecialchars($printSection); ?></td>
                        <td><?php
echo htmlspecialchars(to_last_name_first((string)($r['supervisor_name'] ?? ''))); ?></td>
                        <td><?php
echo htmlspecialchars(to_last_name_first((string)($r['coordinator_name'] ?? ''))); ?></td>
                        <td></td>
                    </tr>
                <?php
endforeach; ?>
            <?php
else: ?>
                <tr>
                    <td class="col-index">1</td>
                    <td colspan="7">No OJT students found for current filter.</td>
                </tr>
            <?php
endif; ?>
        </tbody>
    </table>
</section>

        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">OJT Monitoring</h5>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">OJT</li>
                        <li class="breadcrumb-item">Monitoring</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="ojt-toolbar">
            <div class="ojt-toolbar-left">
                <div class="page-subtitle">Review internship readiness, document progress, and risk alerts for every approved student.</div>
            </div>
            <div class="ojt-toolbar-right ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#kpiPanel" aria-expanded="false" aria-controls="kpiPanel">
                    <i class="feather-bar-chart-2 me-1"></i>KPI Snapshot
                </button>
                <button class="btn filter-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#ojtFilterCollapse" aria-expanded="false" aria-controls="ojtFilterCollapse">
                    <i class="feather-filter me-1"></i>Filter List
                </button>
                <a href="ojt-workflow-board.php" class="btn btn-outline-primary"><i class="feather-kanban me-1"></i>Workflow Board</a>
                <a href="ojt.php?<?php
echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-light"><i class="feather-download me-1"></i>Export CSV</a>
                <button type="button" class="btn btn-light" id="ojtPrintBtn"><i class="feather-printer me-1"></i>Print OJT List</button>
                <form method="post" class="d-inline">
                    <button type="submit" name="queue_reminders" value="1" class="btn btn-warning"><i class="feather-bell me-1"></i>Queue Risk Alerts</button>
                </form>
                <a href="ojt-create.php" class="btn btn-primary"><i class="feather-plus me-1"></i>New OJT Assignment</a>
            </div>
        </div>
        <?php
if (isset($_GET['queued'])): ?>
            <div class="alert alert-success py-2">Risk reminder queue updated successfully for the current filtered students.</div>
        <?php
endif; ?>

        <div class="collapse mb-3" id="kpiPanel">
            <div class="row g-2 mb-2">
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Total Interns</div><div class="kpi-value"><?php
echo $total; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Ongoing Internships</div><div class="kpi-value"><?php
echo $ongoing; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Completed Internships</div><div class="kpi-value"><?php
echo $completed; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">At-Risk Interns</div><div class="kpi-value"><?php
echo $at_risk; ?></div><small class="text-muted">Average Progress: <?php
echo $avg_progress; ?>%</small></div></div>
            </div>
            <div class="row g-2">
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">7-Day Active Interns</div><div class="kpi-value"><?php
echo $trend_active_7d; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">7-Day At-Risk Snapshot</div><div class="kpi-value"><?php
echo $trend_at_risk_7d; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">7-Day Pending Approvals</div><div class="kpi-value"><?php
echo $trend_pending_7d; ?></div></div></div>
                <div class="col-md-3"><div class="card card-body p-2"><div class="text-muted">Avg Approval Turnaround</div><div class="kpi-value"><?php
echo number_format($trend_avg_approval_hours, 2); ?>h</div></div></div>
            </div>
        </div>

        <div class="collapse" id="ojtFilterCollapse">
            <div class="row mb-3 px-3">
                <div class="col-12">
                    <div class="filter-panel">
                        <div class="filter-panel-head">
                            <div>
                                <div class="filter-panel-label">
                                    <i class="feather-sliders"></i>
                                    <span>OJT Filters</span>
                                </div>
                                <p class="filter-panel-sub">Use these filters to narrow the OJT list by student, course, section, school year, semester, stage, or risk status.</p>
                            </div>
                            <div class="filter-panel-head-actions">
                                <a href="ojt.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                            </div>
                        </div>
                        <form method="get" class="filter-form row g-2 align-items-end" id="ojtFilterForm">
                <div class="col-md-3">
                    <label class="form-label">Search OJT Student</label>
                    <input type="text" name="search" id="ojtFilterSearch" class="form-control" value="<?php
echo htmlspecialchars($search); ?>" placeholder="Student name, ID, or course">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Course</label>
                    <select name="course" id="ojtFilterCourse" class="form-select">
                        <option value="">All</option>
                        <?php
foreach ($courses as $course): ?>
                            <option value="<?php
echo htmlspecialchars($course['name']); ?>" <?php
echo ($course_filter === $course['name']) ? 'selected' : ''; ?>><?php
echo htmlspecialchars($course['name']); ?></option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <select name="section" id="ojtFilterSection" class="form-select">
                        <option value="">All</option>
                        <?php
foreach ($sections as $section): ?>
                            <option value="<?php
echo htmlspecialchars($section['section_label']); ?>" <?php
echo ($section_filter === $section['section_label']) ? 'selected' : ''; ?>><?php
echo htmlspecialchars($section['section_label']); ?></option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">School Year</label>
                    <select name="school_year" id="ojtFilterSchoolYear" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($school_year_options as $schoolYear): ?>
                            <option value="<?php echo htmlspecialchars($schoolYear, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($school_year_filter === $schoolYear) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($schoolYear, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Semester</label>
                    <select name="semester" id="ojtFilterSemester" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($semester_options as $semester): ?>
                            <option value="<?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($semester_filter === $semester) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stage</label>
                    <select name="stage" id="ojtFilterStage" class="form-select">
                        <option value="">All</option>
                        <?php
foreach (['Applied','Endorsed','Accepted','Ongoing','Completed'] as $st): ?>
                            <option value="<?php
echo $st; ?>" <?php
echo ($status_filter === $st) ? 'selected' : ''; ?>><?php
echo $st; ?></option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Risk</label>
                    <select name="risk" id="ojtFilterRisk" class="form-select">
                        <option value="all" <?php
echo ($risk_filter === 'all') ? 'selected' : ''; ?>>All</option>
                        <option value="at_risk" <?php
echo ($risk_filter === 'at_risk') ? 'selected' : ''; ?>>At Risk</option>
                        <option value="clean" <?php
echo ($risk_filter === 'clean') ? 'selected' : ''; ?>>Clean</option>
                    </select>
                </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="ojtListTable">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Section</th>
                            <th>Stage</th>
                            <th>Document Checklist</th>
                            <th>Hours</th>
                            <th>Risk</th>
                            <th>Risk Score</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
if (!$rows): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No OJT students match the current filters.</td></tr>
                        <?php
endif; ?>
                        <?php
foreach ($rows as $index => $r): ?>
                            <?php
                            $profile = trim((string)($r['profile_picture'] ?? ''));
                            $has_account = (int)($r['account_user_id'] ?? 0) > 0;
                            $img = $has_account
                                ? resolve_profile_image_url($profile)
                                : ojt_initials_avatar_uri((string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''));
                            if ($img === null || $img === '') {
                                $img = $has_account
                                    ? 'assets/images/avatar/1.png'
                                    : ojt_initials_avatar_uri((string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''));
                            }
                            $required = (float)($r['required_hours'] ?? 0);
                            $rendered = (float)($r['rendered_hours'] ?? 0);
                            if ($rendered <= 0) $rendered = (float)($r['attendance_total_hours'] ?? 0);
                            ?>
                            <tr>
                                <td data-label="Student">
                                    <a class="student-link d-flex align-items-center gap-2" href="ojt-view.php?id=<?php
echo (int)$r['id']; ?>&amp;school_year=<?php
echo urlencode((string)($r['school_year'] ?? '')); ?>&amp;semester=<?php
echo urlencode((string)($r['semester'] ?? '')); ?>">
                                        <img src="<?php
echo htmlspecialchars($img); ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;" alt="profile">
                                        <div>
                                            <div class="fw-semibold"><?php
echo htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></div>
                                            <div class="text-muted fs-12"><?php
echo htmlspecialchars($r['student_id'] ?? ''); ?> | <?php
echo htmlspecialchars($r['course_name'] ?? '-'); ?></div>
                                        </div>
                                    </a>
                                </td>
                                <td data-label="Section"><?php
$sectionSemester = trim((string)($r['section_name'] ?? '-'));
$schoolYearLabel = trim((string)($r['school_year'] ?? ''));
$semesterLabel = trim((string)($r['semester'] ?? ''));
if ($semesterLabel !== '' && $semesterLabel !== '-') {
    $sectionSemester .= ' / ' . $semesterLabel;
}
if ($schoolYearLabel !== '' && $schoolYearLabel !== '-') {
    $sectionSemester .= ' / ' . $schoolYearLabel;
}
echo htmlspecialchars($sectionSemester); ?></td>
                                <td data-label="Stage">
                                    <span class="badge <?php
echo stage_badge_class($r['stage']); ?>"><?php
echo htmlspecialchars($r['stage']); ?></span>
                                    <div class="text-muted fs-12 mt-1">Last biometric: <?php
echo htmlspecialchars($r['last_attendance_date'] ?: 'none yet'); ?></div>
                                </td>
                                <td data-label="Document Checklist">
                                    <div class="doc-progress-wrap">
                                    <span class="chip <?php
echo !empty($r['has_application']) ? 'ok' : 'miss'; ?>">Application (<?php
echo htmlspecialchars($r['wf_application'] ?: 'draft'); ?>)</span>
                                    <span class="chip <?php
echo !empty($r['has_endorsement']) ? 'ok' : 'miss'; ?>">Endorsement (<?php
echo htmlspecialchars($r['wf_endorsement'] ?: 'draft'); ?>)</span>
                                    <span class="chip <?php
echo !empty($r['has_moa']) ? 'ok' : 'miss'; ?>">MOA (<?php
echo htmlspecialchars($r['wf_moa'] ?: 'draft'); ?>)</span>
                                    <span class="chip <?php
echo !empty($r['has_dau_moa']) ? 'ok' : 'miss'; ?>">DAU MOA (<?php
echo htmlspecialchars($r['wf_dau_moa'] ?: 'draft'); ?>)</span>
                                    </div>
                                </td>
                                <td data-label="Hours">
                                    <div class="fw-semibold"><?php
echo number_format($rendered, 1); ?> / <?php
echo number_format($required, 1); ?></div>
                                    <div class="progress" style="height:6px;"><div class="progress-bar" style="width:<?php
echo (float)$r['progress_pct']; ?>%"></div></div>
                                    <div class="text-muted fs-12"><?php
echo (float)$r['progress_pct']; ?>%</div>
                                </td>
                                <td data-label="Risk">
                                    <?php
if (empty($r['risk_flags'])): ?>
                                        <span class="text-success fs-12">No active risk flags</span>
                                    <?php
else: ?>
                                        <div class="risk-wrap"><?php
foreach ($r['risk_flags'] as $rf): ?><span class="risk-pill"><?php
echo htmlspecialchars($rf); ?></span><?php
endforeach; ?></div>
                                    <?php
endif; ?>
                                </td>
                                <td data-label="Risk Score"><span class="badge bg-soft-danger text-danger"><?php
echo intval($r['risk_score'] ?? 0); ?></span></td>
                                <td data-label="Actions">
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-light" href="ojt-view.php?id=<?php
echo (int)$r['id']; ?>&amp;school_year=<?php
echo urlencode((string)($r['school_year'] ?? '')); ?>&amp;semester=<?php
echo urlencode((string)($r['semester'] ?? '')); ?>">Open Record</a>
                                        <a class="btn btn-sm btn-outline-primary" href="ojt-edit.php?id=<?php
echo (int)$r['id']; ?>&amp;school_year=<?php
echo urlencode((string)($r['school_year'] ?? '')); ?>&amp;semester=<?php
echo urlencode((string)($r['semester'] ?? '')); ?>">Update OJT</a>
                                        <a class="btn btn-sm btn-outline-success" href="students-dtr.php?id=<?php
echo (int)$r['id']; ?>">Open DTR</a>
                                    </div>
                                </td>
                            </tr>
                        <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
<script src="assets/vendors/js/vendors.min.js"></script>
<script src="assets/js/global-ui-helpers.js"></script>
<script src="assets/js/common-init.min.js"></script>
<script src="assets/js/theme-customizer-init.min.js"></script>
<script>
    (function () {
        function setDark(isDark) {
            document.documentElement.classList.toggle('app-skin-dark', !!isDark);
            try {
                localStorage.setItem('app-skin', isDark ? 'app-skin-dark' : '');
                localStorage.setItem('app_skin', isDark ? 'app-skin-dark' : '');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                if (isDark) {
                    localStorage.setItem('app-skin-dark', 'app-skin-dark');
                } else {
                    localStorage.removeItem('app-skin-dark');
                }
            } catch (e) {}

            var darkBtn = document.querySelector('.dark-button');
            var lightBtn = document.querySelector('.light-button');
            if (darkBtn) darkBtn.style.display = isDark ? 'none' : '';
            if (lightBtn) lightBtn.style.display = isDark ? '' : 'none';
        }

        function getSavedSkinMode() {
            try {
                var appSkin = localStorage.getItem('app-skin');
                if (appSkin !== null) return appSkin.indexOf('dark') !== -1;

                var appSkinAlt = localStorage.getItem('app_skin');
                if (appSkinAlt !== null) return appSkinAlt.indexOf('dark') !== -1;

                var theme = localStorage.getItem('theme');
                if (theme !== null) return theme.toLowerCase() === 'dark';

                var legacy = localStorage.getItem('app-skin-dark');
                if (legacy !== null) return legacy.indexOf('dark') !== -1;
            } catch (e) {}

            return document.documentElement.classList.contains('app-skin-dark');
        }

        document.addEventListener('DOMContentLoaded', function () {
            setDark(getSavedSkinMode());

            var darkBtn = document.querySelector('.dark-button');
            var lightBtn = document.querySelector('.light-button');

            if (darkBtn) {
                darkBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    setDark(true);
                });
            }

            if (lightBtn) {
                lightBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    setDark(false);
                });
            }
        });
    })();
</script>
<script>
    (function () {
        var filterForm = document.getElementById('ojtFilterForm');
        var searchInput = document.getElementById('ojtFilterSearch');
        var submitTimer;
        function submitFilters() {
            if (filterForm) filterForm.submit();
        }
        function debounceSubmit() {
            clearTimeout(submitTimer);
            submitTimer = setTimeout(submitFilters, 350);
        }
        ['ojtFilterCourse', 'ojtFilterSection', 'ojtFilterSchoolYear', 'ojtFilterSemester', 'ojtFilterStage', 'ojtFilterRisk'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', submitFilters);
        });
        if (searchInput) searchInput.addEventListener('input', debounceSubmit);

        if (window.jQuery && $.fn.select2) {
            var $filterForm = $('#ojtFilterForm');
            ['#ojtFilterCourse', '#ojtFilterSection', '#ojtFilterSchoolYear', '#ojtFilterSemester', '#ojtFilterStage'].forEach(function (selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        width: '100%',
                        allowClear: false,
                        dropdownAutoWidth: false,
                        minimumResultsForSearch: Infinity,
                        dropdownParent: $filterForm
                    });
                }
            });
            ['#ojtFilterRisk'].forEach(function (selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        width: '100%',
                        allowClear: false,
                        dropdownAutoWidth: false,
                        dropdownParent: $filterForm
                    });
                }
            });
            ['#ojtFilterCourse', '#ojtFilterSection', '#ojtFilterSchoolYear', '#ojtFilterSemester', '#ojtFilterStage', '#ojtFilterRisk'].forEach(function (selector) {
                if ($(selector).length) {
                    $(selector).on('select2:select select2:clear', submitFilters);
                }
            });
        }

        var printBtn = document.getElementById('ojtPrintBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function (e) {
                e.preventDefault();
                window.print();
            });
        }
    })();

</script>
</body>
</html>
<?php
$conn->close(); ?>





