<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_once dirname(__DIR__) . '/lib/section_format.php';
$ops_helpers = dirname(__DIR__) . '/lib/ops_helpers.php';
if (file_exists($ops_helpers)) {
    require_once $ops_helpers;
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$current_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
$current_user_role = trim((string)($_SESSION['role'] ?? ''));
$current_user_role_key = strtolower($current_user_role);
$coordinator_allowed_course_ids = $current_user_role_key === 'coordinator' && function_exists('coordinator_course_ids')
    ? coordinator_course_ids($conn, $current_user_id)
    : [];
$coordinator_course_scope_sql = '';
if ($current_user_role_key === 'coordinator') {
    $coordinator_course_scope_sql = empty($coordinator_allowed_course_ids)
        ? '1 = 0'
        : 's.course_id IN (' . implode(',', array_map('intval', $coordinator_allowed_course_ids)) . ')';
}
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

function ojt_column_exists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
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

    if ($intern === 'dropped' || $intern === 'cancelled') return 'Dropped';
    if ($intern === 'completed' || $progress >= 100) return 'Completed';
    if ($intern === 'ongoing') return 'Ongoing';
    if ($wf_moa === 'approved' || $has_moa) return 'Accepted';
    if ($wf_endorse === 'approved' || $has_endorse) return 'Endorsed';
    if ($wf_app === 'approved' || $has_app) return 'Applied';
    return 'Applied';
}

function stage_badge_class(string $stage): string {
    $map = [
        'Applied' => 'app-ojt-stage-pill is-applied',
        'Endorsed' => 'app-ojt-stage-pill is-endorsed',
        'Accepted' => 'app-ojt-stage-pill is-accepted',
        'Ongoing' => 'app-ojt-stage-pill is-ongoing',
        'Completed' => 'app-ojt-stage-pill is-completed',
        'Dropped' => 'app-ojt-stage-pill is-dropped'
    ];
    return $map[$stage] ?? 'app-ojt-stage-pill';
}

function resolve_profile_image_url(string $profilePath, int $userId = 0): ?string {
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    return $resolved !== '' ? $resolved : null;
}

function formatSectionDisplayLabel($code, $name): string {
    if (function_exists('biotern_format_section_label')) {
        return biotern_format_section_label((string)$code, (string)$name);
    }

    $code = trim((string)$code);
    $name = trim((string)$name);
    return $code !== '' ? $code : $name;
}

function sectionFilterMatches(string $rowSection, string $filterSection): bool {
    if ($filterSection === '') {
        return true;
    }
    if (function_exists('biotern_section_filter_key')) {
        $rowKey = biotern_section_filter_key($rowSection);
        $filterKey = biotern_section_filter_key($filterSection);
        return $rowKey !== '' && $filterKey !== '' && $rowKey === $filterKey;
    }
    return strcasecmp($rowSection, $filterSection) === 0;
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

$students_has_school_year = ojt_column_exists($conn, 'students', 'school_year');
$students_has_semester = ojt_column_exists($conn, 'students', 'semester');
$internships_has_school_year = ojt_column_exists($conn, 'internships', 'school_year');
$internships_has_semester = ojt_column_exists($conn, 'internships', 'semester');
$sections_has_code = ojt_column_exists($conn, 'sections', 'code');
$sections_has_name = ojt_column_exists($conn, 'sections', 'name');

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
$sectionLabelSql = $sections_has_code && $sections_has_name
    ? "COALESCE(NULLIF(code, ''), NULLIF(name, ''), '-') AS section_label, code, name"
    : ($sections_has_name
        ? "COALESCE(NULLIF(name, ''), '-') AS section_label, '' AS code, name"
        : "COALESCE(NULLIF(code, ''), '-') AS section_label, code, '' AS name");
$sectionOrderSql = $sections_has_code && $sections_has_name
    ? "ORDER BY code ASC, name ASC"
    : "ORDER BY section_label ASC";
$sres = $conn->query("SELECT id, {$sectionLabelSql} FROM sections {$sectionOrderSql}");
if ($sres) {
    while ($s = $sres->fetch_assoc()) {
        if ($sections_has_code && $sections_has_name) {
            $s['section_label'] = formatSectionDisplayLabel($s['code'] ?? '', $s['name'] ?? '');
        }
        $sections[] = $s;
    }
}
if ($section_filter !== '' && function_exists('biotern_section_filter_key')) {
    $sectionFilterKey = biotern_section_filter_key($section_filter);
    foreach ($sections as $sectionOption) {
        $sectionOptionLabel = (string)($sectionOption['section_label'] ?? '');
        if ($sectionFilterKey !== '' && biotern_section_filter_key($sectionOptionLabel) === $sectionFilterKey) {
            $section_filter = $sectionOptionLabel;
            break;
        }
    }
}

$studentSchoolYearSql = $students_has_school_year
    ? "COALESCE(NULLIF(s.school_year, ''), '-') AS school_year"
    : "'-' AS school_year";
$studentSemesterSql = $students_has_semester
    ? "COALESCE(NULLIF(s.semester, ''), '-') AS semester"
    : "'-' AS semester";
$sectionCodeRawSql = $sections_has_code ? "sec.code AS section_code_raw" : "'' AS section_code_raw";
$sectionNameRawSql = $sections_has_name ? "sec.name AS section_name_raw" : "'' AS section_name_raw";
$studentSectionSql = $sections_has_code && $sections_has_name
    ? "COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name"
    : ($sections_has_name
        ? "COALESCE(NULLIF(sec.name, ''), '-') AS section_name"
        : "COALESCE(NULLIF(sec.code, ''), '-') AS section_name");

$internshipJoinConditions = "s.id = i.student_id AND i.status IN ('ongoing','completed')";
if ($students_has_school_year && $internships_has_school_year) {
    $internshipJoinConditions .= " AND COALESCE(i.school_year, '') = COALESCE(s.school_year, '')";
}
if ($students_has_semester && $internships_has_semester) {
    $internshipJoinConditions .= " AND COALESCE(i.semester, '') = COALESCE(s.semester, '')";
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
    {$studentSchoolYearSql},
    {$studentSemesterSql},
    COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
    c.name AS course_name,
    {$sectionCodeRawSql},
    {$sectionNameRawSql},
    {$studentSectionSql},
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
LEFT JOIN (
    SELECT i_full.*
    FROM internships i_full
    INNER JOIN (
        SELECT student_id, MAX(id) AS latest_id
        FROM internships
        GROUP BY student_id
    ) i_latest ON i_latest.latest_id = i_full.id
) i ON s.id = i.student_id
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
    " . ($coordinator_course_scope_sql !== '' ? "AND {$coordinator_course_scope_sql}" : '') . "
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
        $row['section_name'] = formatSectionDisplayLabel(
            (string)($row['section_code_raw'] ?? ''),
            (string)($row['section_name_raw'] ?? '')
        );
        if ($row['section_name'] === '') {
            $row['section_name'] = '-';
        }
        $row['progress_pct'] = safe_pct($rendered, $required);
        $row['stage'] = pipeline_stage($row);

        $risk = [];
        if (empty($row['has_moa'])) $risk[] = 'No MOA';
        if (empty($row['has_endorsement'])) $risk[] = 'No Endorsement';
        if (($row['internship_status'] ?? '') === 'ongoing' && !empty($row['last_attendance_date'])) {
            $today_ts = strtotime($today);
            $last_ts = strtotime((string)$row['last_attendance_date']);
            if ($today_ts !== false && $last_ts !== false) {
                $days = (int)floor(($today_ts - $last_ts) / 86400);
                if ($days >= 3) $risk[] = 'No biometric logs 3+ days';
            }
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
            $today_ts = strtotime($today);
            $last_ts = strtotime((string)$row['last_attendance_date']);
            if ($today_ts !== false && $last_ts !== false) {
                $days = (int)floor(($today_ts - $last_ts) / 86400);
                if ($days >= 3) $risk_score += 15;
            }
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
        if ($section_filter !== '' && !sectionFilterMatches((string)($row['section_name'] ?? ''), $section_filter)) {
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

if (ojt_table_exists($conn, 'ojt_masterlist')) {
    $pendingMasterlistSql = "
        SELECT
            ml.id AS masterlist_id,
            ml.school_year,
            ml.semester,
            ml.student_no,
            ml.student_name,
            ml.section,
            ml.company_name,
            ml.status,
            ml.updated_at
        FROM ojt_masterlist ml
        LEFT JOIN students s
            ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(ml.student_no, '')) COLLATE utf8mb4_unicode_ci
        WHERE s.id IS NULL
        ORDER BY ml.student_name ASC, ml.id ASC
    ";
    $pendingMasterlistRes = $conn->query($pendingMasterlistSql);
    if ($pendingMasterlistRes) {
        while ($ml = $pendingMasterlistRes->fetch_assoc()) {
            $studentName = normalize_person_name((string)($ml['student_name'] ?? ''));
            $firstName = $studentName;
            $lastName = '';
            if (strpos($studentName, ',') !== false) {
                [$lastName, $firstName] = array_map('trim', explode(',', $studentName, 2));
            }

            $row = [
                'id' => 0,
                'masterlist_id' => (int)($ml['masterlist_id'] ?? 0),
                'source_type' => 'masterlist_pending_account',
                'student_id' => trim((string)($ml['student_no'] ?? '')),
                'first_name' => $firstName !== '' ? $firstName : $studentName,
                'last_name' => $lastName,
                'email' => '',
                'phone' => '',
                'student_status' => 'pending',
                'created_at' => (string)($ml['updated_at'] ?? ''),
                'school_year' => trim((string)($ml['school_year'] ?? '-')) ?: '-',
                'semester' => trim((string)($ml['semester'] ?? '-')) ?: '-',
                'profile_picture' => '',
                'course_name' => 'Pending account',
                'section_name' => formatSectionDisplayLabel((string)($ml['section'] ?? ''), '') ?: '-',
                'company_name' => trim((string)($ml['company_name'] ?? '')),
                'internship_status' => trim((string)($ml['status'] ?? 'pending')) ?: 'pending',
                'required_hours' => 250,
                'rendered_hours' => 0,
                'start_date' => '',
                'end_date' => '',
                'supervisor_name' => '',
                'coordinator_name' => '',
                'has_application' => 0,
                'has_endorsement' => 0,
                'has_moa' => 0,
                'has_dau_moa' => 0,
                'wf_application' => '',
                'wf_endorsement' => '',
                'wf_moa' => '',
                'wf_dau_moa' => '',
                'last_attendance_date' => '',
                'pending_logs' => 0,
                'attendance_total_hours' => 0,
                'progress_pct' => 0,
                'stage' => 'Applied',
                'risk_flags' => ['Pending account creation'],
                'risk_score' => 20,
            ];

            $haystack = strtolower(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['student_id'] ?? '') . ' ' . ($row['course_name'] ?? '') . ' ' . (string)($ml['company_name'] ?? '')));
            if ($search !== '' && strpos($haystack, strtolower($search)) === false) {
                continue;
            }
            if ($course_filter !== '' && strcasecmp((string)($row['course_name'] ?? ''), $course_filter) !== 0) {
                continue;
            }
            if ($section_filter !== '' && !sectionFilterMatches((string)($row['section_name'] ?? ''), $section_filter)) {
                continue;
            }
            if ($school_year_filter !== '' && strcasecmp((string)($row['school_year'] ?? ''), $school_year_filter) !== 0) {
                continue;
            }
            if ($semester_filter !== '' && strcasecmp((string)($row['semester'] ?? ''), $semester_filter) !== 0) {
                continue;
            }
            if ($status_filter !== '' && strcasecmp((string)$row['stage'], $status_filter) !== 0) {
                continue;
            }
            if ($risk_filter === 'clean') {
                continue;
            }

            $rows[] = $row;
        }
        $pendingMasterlistRes->close();
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
$active_filter_count = 0;
foreach ([$search, $course_filter, $section_filter, $status_filter] as $active_filter_value) {
    if (trim((string)$active_filter_value) !== '') {
        $active_filter_count++;
    }
}
if ($risk_filter !== 'all') {
    $active_filter_count++;
}
if ($school_year_filter !== '') {
    $active_filter_count++;
}
if ($semester_filter !== '') {
    $active_filter_count++;
}

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
        'queued' => 1,
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
    fputcsv($out, ['Student ID', 'Name', 'Course', 'Section', 'School Year', 'Semester', 'Stage', 'Risk Score', 'Risk Flags', 'Required Hours', 'Rendered Hours', 'Last Attendance']);
    foreach ($rows as $r) {
        $required = (float)($r['required_hours'] ?? 0);
        $rendered = (float)($r['rendered_hours'] ?? 0);
        if ($rendered <= 0) $rendered = (float)($r['attendance_total_hours'] ?? 0);
        fputcsv($out, [
            $r['student_id'] ?? '',
            trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            $r['course_name'] ?? '',
            $r['section_name'] ?? '',
            $r['school_year'] ?? '',
            $r['semester'] ?? '',
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

$page_title = 'BioTern || OJT Dashboard';
$page_body_class = 'app-page-ojt-dashboard';
$page_styles = [
    'assets/css/modules/management/management-filters.css',
    'assets/css/modules/management/management-ojt-shared.css',
    'assets/css/modules/management/management-ojt.css',
];
$page_scripts = [
    'assets/js/modules/pages/ojt-list-select.js',
    'assets/js/modules/pages/ojt-list-print.js',
    'assets/js/modules/management/ojt-dashboard-runtime.js',
    'assets/js/theme-customizer-init.min.js',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<section class="ojt-print-sheet app-ojt-print-sheet">
    <img class="crest app-ojt-print-crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
    <div class="header app-ojt-print-header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta app-ojt-print-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel app-ojt-print-tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title app-ojt-print-title">OJT STUDENT LIST</div>
    <div class="print-meta app-ojt-print-meta-row"><strong>SECTION:</strong> <?php echo htmlspecialchars($print_section_label); ?></div>
    <table>
        <thead>
            <tr>
                <th class="col-index app-ojt-print-col-index">#</th>
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
            <?php if (!empty($print_ojt_rows)): ?>
                <?php foreach ($print_ojt_rows as $i => $r): ?>
                    <tr>
                        <td class="col-index app-ojt-print-col-index"><?php echo (int)$i + 1; ?></td>
                        <td><?php echo htmlspecialchars((string)($r['student_id'] ?? '')); ?></td>
                        <?php
                        $student_last = normalize_person_name((string)($r['last_name'] ?? ''));
                        $student_first = normalize_person_name((string)($r['first_name'] ?? ''));
                        $student_name_lf = trim($student_last . ($student_last !== '' && $student_first !== '' ? ', ' : '') . $student_first);
                        ?>
                        <td><?php echo htmlspecialchars($student_name_lf); ?></td>
                        <td><?php echo htmlspecialchars((string)($r['course_name'] ?? '')); ?></td>
                        <?php
                        $printSection = trim((string)($r['section_name'] ?? ''));
                        $printSemester = trim((string)($r['semester'] ?? ''));
                        $printSchoolYear = trim((string)($r['school_year'] ?? ''));
                        if ($printSemester !== '' && $printSemester !== '-') {
                            $printSection .= ' / ' . $printSemester;
                        }
                        if ($printSchoolYear !== '' && $printSchoolYear !== '-') {
                            $printSection .= ' / ' . $printSchoolYear;
                        }
                        ?>
                        <td><?php echo htmlspecialchars($printSection); ?></td>
                        <td><?php echo htmlspecialchars(to_last_name_first((string)($r['supervisor_name'] ?? ''))); ?></td>
                        <td><?php echo htmlspecialchars(to_last_name_first((string)($r['coordinator_name'] ?? ''))); ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td class="col-index app-ojt-print-col-index">1</td>
                    <td colspan="7">No OJT students found for current filter.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

        <div class="page-header app-ojt-page-header">
            <div class="page-header-left app-ojt-page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">OJT Dashboard</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">OJT Dashboard</li>
                </ul>
            </div>
            <div class="page-header-right app-ojt-page-header-right ms-auto app-ojt-header-actions">
                <div class="app-table-header-search app-ojt-table-search">
                    <label class="visually-hidden" for="ojtHeaderSearchInput">Search OJT list</label>
                    <i class="feather-search" aria-hidden="true"></i>
                    <input type="search" id="ojtHeaderSearchInput" class="form-control" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search students">
                </div>
                <a href="ojt.php" class="btn btn-sm btn-outline-secondary">
                    <i class="feather-rotate-ccw me-1"></i>
                    <span>Reset</span>
                </a>
                <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="ojtActionsMenu">
                    <i class="feather-grid me-1"></i>
                    <span>Actions</span>
                </button>
                <div class="page-header-actions app-ojt-actions-panel" id="ojtActionsMenu">
                    <div class="dashboard-actions-panel">
                        <div class="dashboard-actions-meta">
                            <span class="text-muted fs-12">Quick Actions</span>
                        </div>
                        <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <a href="ojt-workflow-board.php" class="action-tile">
                                <i class="feather-kanban"></i>
                                <span>Workflow Board</span>
                            </a>
                            <a href="masterlist-pending-students.php" class="action-tile">
                                <i class="feather-user-plus"></i>
                                <span>Pending Accounts</span>
                            </a>
                            <a href="ojt.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="action-tile">
                                <i class="feather-download"></i>
                                <span>Export CSV</span>
                            </a>
                            <button type="button" class="action-tile" id="ojtPrintBtn">
                                <i class="feather-printer"></i>
                                <span>Print List</span>
                            </button>
                            <button type="button" class="action-tile" data-ojt-print-selected="ojtListTable">
                                <i class="feather-check-square"></i>
                                <span>Print Selected</span>
                            </button>
                            <form method="post" class="d-inline">
                                <button type="submit" name="queue_reminders" value="1" class="action-tile">
                                    <i class="feather-bell"></i>
                                    <span>Queue Reminders</span>
                                </button>
                            </form>
                            <a href="ojt-create.php" class="action-tile action-tile-primary" data-action-priority="1">
                                <i class="feather-plus"></i>
                                <span>New OJT Assignment</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (isset($_GET['queued'])): ?>
            <div class="alert alert-success py-2">Reminders queued successfully for flagged students.</div>
        <?php endif; ?>

        <section class="app-ojt-filters-section" id="ojtFilters">
            <div id="ojtFiltersPanel">
            <div class="filter-panel filter-card app-ojt-filter-card">
                <form method="get" class="filter-form row g-2 align-items-end app-ojt-filter-form" id="ojtFilterForm">
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <label class="form-label" for="ojtFilterCourse">Course</label>
                        <select name="course" id="ojtFilterCourse" class="form-control" data-ui-select="custom">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['name']); ?>" <?php echo ($course_filter === $course['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <label class="form-label" for="ojtFilterSection">Section</label>
                        <select name="section" id="ojtFilterSection" class="form-control" data-ui-select="custom">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section['section_label']); ?>" <?php echo ($section_filter === $section['section_label']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($section['section_label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <label class="form-label" for="ojtFilterSchoolYear">School Year</label>
                        <select name="school_year" id="ojtFilterSchoolYear" class="form-control" data-ui-select="custom">
                            <option value="">All School Years</option>
                            <?php foreach ($school_year_options as $schoolYear): ?>
                                <option value="<?php echo htmlspecialchars($schoolYear, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($school_year_filter === $schoolYear) ? 'selected' : ''; ?>><?php echo htmlspecialchars($schoolYear, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <label class="form-label" for="ojtFilterSemester">Semester</label>
                        <select name="semester" id="ojtFilterSemester" class="form-control" data-ui-select="custom">
                            <option value="">All Semesters</option>
                            <?php foreach ($semester_options as $semester): ?>
                                <option value="<?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($semester_filter === $semester) ? 'selected' : ''; ?>><?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <label class="form-label" for="ojtFilterStage">Stage</label>
                        <select name="stage" id="ojtFilterStage" class="form-control" data-ui-select="custom">
                            <option value="">All Stages</option>
                            <?php foreach (['Applied','Endorsed','Accepted','Ongoing','Completed','Dropped'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo ($status_filter === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <label class="form-label" for="ojtFilterRisk">Risk</label>
                        <select name="risk" id="ojtFilterRisk" class="form-control" data-ui-select="custom">
                            <option value="all" <?php echo ($risk_filter === 'all') ? 'selected' : ''; ?>>All Risk States</option>
                            <option value="at_risk" <?php echo ($risk_filter === 'at_risk') ? 'selected' : ''; ?>>At Risk</option>
                            <option value="clean" <?php echo ($risk_filter === 'clean') ? 'selected' : ''; ?>>Clean</option>
                        </select>
                    </div>
                </form>
            </div>
            </div>
        </section>

        <div class="card app-ojt-dashboard-card stretch stretch-full app-ojt-table-card app-data-card app-data-toolbar" id="ojtWorklist">
            <div class="card-body p-0">
                <div class="table-responsive students-table-wrap app-ojt-table-wrap app-data-table-wrap">
                    <table class="table table-hover mb-0 app-ojt-list-table app-data-table" id="ojtListTable" data-ojt-select-table data-print-title="OJT Student List" data-print-subtitle="<?php echo htmlspecialchars(trim($print_section_label !== 'ALL' ? 'Section: ' . $print_section_label : 'Current filtered OJT list'), ENT_QUOTES, 'UTF-8'); ?>">
                        <thead>
                        <tr>
                            <th class="app-ojt-select-column">
                                <div class="form-check app-ojt-select-check">
                                    <input class="form-check-input" type="checkbox" data-ojt-select-all aria-label="Select all OJT students">
                                </div>
                            </th>
                            <th>Student</th>
                            <th>Section</th>
                            <th>Stage</th>
                            <th>Document Checklist</th>
                            <th>Hours</th>
                            <th>Risk</th>
                            <th>Risk Score</th>
                            <th class="text-end" data-print-exclude="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No records found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $index => $r): ?>
                            <?php
                            $profile = trim((string)($r['profile_picture'] ?? ''));
                            $img = 'assets/images/avatar/' . (($index % 5) + 1) . '.png';
                            $profile_url = resolve_profile_image_url($profile, (int)($r['user_id'] ?? 0));
                            if ($profile_url !== null) {
                                $img = $profile_url;
                            }
                            $required = (float)($r['required_hours'] ?? 0);
                            $rendered = (float)($r['rendered_hours'] ?? 0);
                            if ($rendered <= 0) $rendered = (float)($r['attendance_total_hours'] ?? 0);
                            $risk_score_int = intval($r['risk_score'] ?? 0);
                            $risk_band = 'low';
                            if ($risk_score_int >= 75) {
                                $risk_band = 'high';
                            } elseif ($risk_score_int >= 40) {
                                $risk_band = 'medium';
                            }
                            $last_biometric_label = (string)($r['last_attendance_date'] ?: 'No recent biometric');
                            $hours_remaining = max($required - $rendered, 0);
                            $period_parts = [];
                            if (!empty($r['section_name']) && (string)$r['section_name'] !== '-') {
                                $period_parts[] = (string)$r['section_name'];
                            }
                            if (!empty($r['semester']) && (string)$r['semester'] !== '-') {
                                $period_parts[] = (string)$r['semester'];
                            }
                            if (!empty($r['school_year']) && (string)$r['school_year'] !== '-') {
                                $period_parts[] = (string)$r['school_year'];
                            }
                            $section_period_label = !empty($period_parts) ? implode(' / ', $period_parts) : '-';
                            $row_context_query = http_build_query([
                                'school_year' => (string)($r['school_year'] ?? ''),
                                'semester' => (string)($r['semester'] ?? ''),
                            ]);
                            $has_student_account = (int)($r['id'] ?? 0) > 0;
                            $ojt_view_link = $has_student_account ? ('ojt-view.php?id=' . (int)$r['id'] . ($row_context_query !== '' ? '&' . $row_context_query : '')) : '#';
                            $ojt_edit_link = $has_student_account ? ('ojt-edit.php?id=' . (int)$r['id'] . ($row_context_query !== '' ? '&' . $row_context_query : '')) : '#';
                            ?>
                            <tr class="app-ojt-table-row app-ojt-table-row-<?php echo htmlspecialchars($risk_band); ?>">
                                <td class="app-ojt-select-column">
                                    <div class="form-check app-ojt-select-check">
                                        <input class="form-check-input" type="checkbox" data-ojt-row-select aria-label="Select student <?php echo htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </td>
                                <td data-label="Student">
                                    <a class="student-link app-ojt-student-link app-ojt-student-block<?php echo !$has_student_account ? ' pe-none' : ''; ?>" href="<?php echo htmlspecialchars($ojt_view_link); ?>">
                                        <img src="<?php echo htmlspecialchars($img); ?>" class="app-avatar-42" alt="profile">
                                        <div class="app-ojt-student-block-copy">
                                            <div class="app-ojt-student-name"><?php echo htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></div>
                                            <div class="app-ojt-student-meta"><?php echo htmlspecialchars($r['student_id'] ?? ''); ?></div>
                                            <div class="app-ojt-student-submeta"><?php echo htmlspecialchars($r['course_name'] ?? '-'); ?></div>
                                            <?php if (!$has_student_account && trim((string)($r['company_name'] ?? '')) !== ''): ?><div class="app-ojt-student-submeta"><?php echo htmlspecialchars((string)$r['company_name']); ?></div><?php endif; ?>
                                            <?php if (!$has_student_account): ?><div class="app-ojt-student-submeta text-warning">Pending account creation</div><?php endif; ?>
                                        </div>
                                    </a>
                                </td>
                                <td data-label="Section">
                                    <div class="app-ojt-cell-stack app-ojt-section-block">
                                        <span class="app-ojt-cell-title">Section</span>
                                        <span class="app-ojt-section-pill"><?php echo htmlspecialchars($section_period_label); ?></span>
                                    </div>
                                </td>
                                <td data-label="Stage">
                                    <div class="app-ojt-cell-stack app-ojt-pipeline-block">
                                        <span class="app-ojt-cell-title">Stage</span>
                                        <span class="badge <?php echo stage_badge_class($r['stage']); ?>"><?php echo htmlspecialchars($r['stage']); ?></span>
                                        <span class="app-ojt-cell-meta">Last biometric: <?php echo htmlspecialchars($r['last_attendance_date'] ?: 'none yet'); ?></span>
                                    </div>
                                </td>
                                <td data-label="Document Checklist">
                                    <div class="app-ojt-cell-stack app-ojt-documents-block">
                                        <div class="app-ojt-chip-stack">
                                            <span class="chip app-ojt-chip <?php echo !empty($r['has_application']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">Application (<?php echo htmlspecialchars($r['wf_application'] ?: 'draft'); ?>)</span>
                                            <span class="chip app-ojt-chip <?php echo !empty($r['has_endorsement']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">Endorsement (<?php echo htmlspecialchars($r['wf_endorsement'] ?: 'draft'); ?>)</span>
                                            <span class="chip app-ojt-chip <?php echo !empty($r['has_moa']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">MOA (<?php echo htmlspecialchars($r['wf_moa'] ?: 'draft'); ?>)</span>
                                            <span class="chip app-ojt-chip <?php echo !empty($r['has_dau_moa']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">DAU MOA (<?php echo htmlspecialchars($r['wf_dau_moa'] ?: 'draft'); ?>)</span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Hours">
                                    <div class="app-ojt-cell-stack app-ojt-hours-block">
                                        <div class="app-ojt-hours-total"><?php echo number_format($rendered, 1); ?> / <?php echo number_format($required, 1); ?></div>
                                        <div class="progress app-progress-6"><div class="progress-bar" style="width:<?php echo (float)$r['progress_pct']; ?>%"></div></div>
                                        <span class="app-ojt-cell-meta"><?php echo (float)$r['progress_pct']; ?>%</span>
                                    </div>
                                </td>
                                <td data-label="Risk">
                                    <div class="app-ojt-cell-stack app-ojt-risk-flags-block">
                                        <?php if (empty($r['risk_flags'])): ?>
                                            <span class="app-ojt-clear-flag">No active risk flags</span>
                                        <?php else: ?>
                                            <div class="app-ojt-risk-stack">
                                                <?php foreach ($r['risk_flags'] as $rf): ?>
                                                    <span class="risk-pill app-ojt-risk-pill"><?php echo htmlspecialchars($rf); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Risk Score" data-order="<?php echo $risk_score_int; ?>">
                                    <div class="app-ojt-status-block app-ojt-risk-block">
                                        <span class="app-ojt-risk-score app-ojt-risk-score-<?php echo htmlspecialchars($risk_band); ?>"><?php echo $risk_score_int; ?></span>
                                    </div>
                                </td>
                                <td data-label="Actions" data-print-exclude="1">
                                    <div class="app-ojt-row-actions">
                                        <?php if ($has_student_account): ?>
                                            <a class="btn btn-sm btn-light app-ojt-action-btn" href="<?php echo htmlspecialchars($ojt_view_link); ?>">Open Record</a>
                                            <a class="btn btn-sm btn-outline-primary app-ojt-action-btn" href="<?php echo htmlspecialchars($ojt_edit_link); ?>">Update OJT</a>
                                            <a class="btn btn-sm btn-outline-success app-ojt-action-btn app-ojt-action-btn-wide" href="students-internal-dtr.php?id=<?php echo (int)$r['id']; ?>">Open Internal DTR</a>
                                        <?php else: ?>
                                            <span class="btn btn-sm btn-outline-warning app-ojt-action-btn app-ojt-action-btn-wide disabled">Create student account first</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="app-ojt-mobile-list app-mobile-list">
                    <?php if (!$rows): ?>
                        <div class="app-ojt-mobile-empty text-muted">No records found.</div>
                    <?php else: ?>
                        <?php foreach ($rows as $index => $r): ?>
                            <?php
                            $profile = trim((string)($r['profile_picture'] ?? ''));
                            $img = 'assets/images/avatar/' . (($index % 5) + 1) . '.png';
                            $profile_url = resolve_profile_image_url($profile, (int)($r['user_id'] ?? 0));
                            if ($profile_url !== null) {
                                $img = $profile_url;
                            }
                            $required = (float)($r['required_hours'] ?? 0);
                            $rendered = (float)($r['rendered_hours'] ?? 0);
                            if ($rendered <= 0) $rendered = (float)($r['attendance_total_hours'] ?? 0);
                            $stage = (string)($r['stage'] ?? 'Applied');
                            $risk_score_int = intval($r['risk_score'] ?? 0);
                            $risk_band = 'low';
                            if ($risk_score_int >= 75) {
                                $risk_band = 'high';
                            } elseif ($risk_score_int >= 40) {
                                $risk_band = 'medium';
                            }
                            $summary_status_class = 'status-pending';
                            if ($stage === 'Completed') {
                                $summary_status_class = 'status-complete';
                            } elseif ($stage === 'Ongoing') {
                                $summary_status_class = 'status-active';
                            } elseif ($stage === 'Accepted' || $stage === 'Endorsed') {
                                $summary_status_class = 'status-review';
                            }
                            $period_parts = [];
                            if (!empty($r['section_name']) && (string)$r['section_name'] !== '-') {
                                $period_parts[] = (string)$r['section_name'];
                            }
                            if (!empty($r['semester']) && (string)$r['semester'] !== '-') {
                                $period_parts[] = (string)$r['semester'];
                            }
                            if (!empty($r['school_year']) && (string)$r['school_year'] !== '-') {
                                $period_parts[] = (string)$r['school_year'];
                            }
                            $section_period_label = !empty($period_parts) ? implode(' / ', $period_parts) : '-';
                            $row_context_query = http_build_query([
                                'school_year' => (string)($r['school_year'] ?? ''),
                                'semester' => (string)($r['semester'] ?? ''),
                            ]);
                            $has_student_account = (int)($r['id'] ?? 0) > 0;
                            $ojt_view_link = $has_student_account ? ('ojt-view.php?id=' . (int)$r['id'] . ($row_context_query !== '' ? '&' . $row_context_query : '')) : '#';
                            $ojt_edit_link = $has_student_account ? ('ojt-edit.php?id=' . (int)$r['id'] . ($row_context_query !== '' ? '&' . $row_context_query : '')) : '#';
                            ?>
                            <details class="app-ojt-mobile-item app-mobile-item">
                                <summary class="app-ojt-mobile-summary app-mobile-summary">
                                    <div class="app-ojt-mobile-summary-main app-mobile-summary-main">
                                        <div class="avatar-image avatar-md">
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="img-fluid">
                                        </div>
                                        <div class="app-ojt-mobile-summary-text app-mobile-summary-text">
                                            <span class="app-ojt-mobile-name app-mobile-name"><?php echo htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></span>
                                            <span class="app-ojt-mobile-subtext app-mobile-subtext">ID: <?php echo htmlspecialchars((string)($r['student_id'] ?? '')); ?> &middot; <?php echo htmlspecialchars((string)($r['course_name'] ?? '-')); ?></span>
                                            <?php if (!$has_student_account && trim((string)($r['company_name'] ?? '')) !== ''): ?><span class="app-ojt-mobile-subtext app-mobile-subtext"><?php echo htmlspecialchars((string)$r['company_name']); ?></span><?php endif; ?>
                                            <?php if (!$has_student_account): ?><span class="app-ojt-mobile-subtext app-mobile-subtext text-warning">Pending account creation</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="app-ojt-mobile-status-dot <?php echo htmlspecialchars($summary_status_class); ?>" aria-hidden="true"></span>
                                </summary>
                                <div class="app-ojt-mobile-details app-mobile-details">
                                    <div class="app-ojt-mobile-topline">
                                        <span class="app-ojt-risk-score app-ojt-risk-score-<?php echo htmlspecialchars($risk_band); ?>">Risk <?php echo $risk_score_int; ?></span>
                                        <span class="app-ojt-section-pill"><?php echo htmlspecialchars($section_period_label); ?></span>
                                    </div>
                                    <div class="app-ojt-mobile-row app-mobile-row">
                                        <span class="app-ojt-mobile-label app-mobile-label">Pipeline</span>
                                        <span class="app-ojt-mobile-value app-mobile-value"><span class="badge <?php echo stage_badge_class($stage); ?>"><?php echo htmlspecialchars($stage); ?></span></span>
                                    </div>
                                    <div class="app-ojt-mobile-row app-mobile-row">
                                        <span class="app-ojt-mobile-label app-mobile-label">Last Biometric</span>
                                        <span class="app-ojt-mobile-value app-mobile-value"><?php echo htmlspecialchars((string)($r['last_attendance_date'] ?: 'none')); ?></span>
                                    </div>
                                    <div class="app-ojt-mobile-row app-ojt-mobile-row-stack app-mobile-row app-mobile-row-stack">
                                        <span class="app-ojt-mobile-label app-mobile-label">Document Progress</span>
                                        <span class="app-ojt-mobile-value app-mobile-value">
                                            <span class="app-ojt-chip-stack">
                                                <span class="chip app-ojt-chip <?php echo !empty($r['has_application']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">Application (<?php echo htmlspecialchars($r['wf_application'] ?: 'draft'); ?>)</span>
                                                <span class="chip app-ojt-chip <?php echo !empty($r['has_endorsement']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">Endorsement (<?php echo htmlspecialchars($r['wf_endorsement'] ?: 'draft'); ?>)</span>
                                                <span class="chip app-ojt-chip <?php echo !empty($r['has_moa']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">MOA (<?php echo htmlspecialchars($r['wf_moa'] ?: 'draft'); ?>)</span>
                                                <span class="chip app-ojt-chip <?php echo !empty($r['has_dau_moa']) ? 'ok app-ojt-chip-ok' : 'miss app-ojt-chip-miss'; ?>">DAU MOA (<?php echo htmlspecialchars($r['wf_dau_moa'] ?: 'draft'); ?>)</span>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="app-ojt-mobile-row">
                                        <span class="app-ojt-mobile-label">Hours</span>
                                        <span class="app-ojt-mobile-value"><?php echo number_format($rendered, 1); ?> / <?php echo number_format($required, 1); ?> (<?php echo (float)$r['progress_pct']; ?>%)</span>
                                    </div>
                                    <div class="app-ojt-mobile-row app-ojt-mobile-row-stack">
                                        <span class="app-ojt-mobile-label">Risk</span>
                                        <span class="app-ojt-mobile-value">
                                            <?php if (empty($r['risk_flags'])): ?>
                                                <span class="app-ojt-clear-flag">No critical flags</span>
                                            <?php else: ?>
                                                <span class="app-ojt-risk-stack">
                                                    <?php foreach ($r['risk_flags'] as $rf): ?><span class="risk-pill app-ojt-risk-pill"><?php echo htmlspecialchars($rf); ?></span><?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="app-ojt-mobile-actions">
                                        <?php if ($has_student_account): ?>
                                            <a class="btn btn-sm btn-light" href="<?php echo htmlspecialchars($ojt_view_link); ?>">View</a>
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($ojt_edit_link); ?>">Edit</a>
                                            <a class="btn btn-sm btn-outline-success" href="students-internal-dtr.php?id=<?php echo (int)$r['id']; ?>">Internal DTR</a>
                                        <?php else: ?>
                                            <span class="btn btn-sm btn-outline-warning disabled" aria-disabled="true">Create student account first</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<section class="student-list-print-sheet app-students-print-sheet app-ojt-selected-print-sheet" data-ojt-print-sheet="ojtListTable" aria-hidden="true">
    <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title" data-ojt-print-title>OJT STUDENT LIST</div>
    <p class="print-meta" data-ojt-print-subtitle><?php echo htmlspecialchars(trim($print_section_label !== 'ALL' ? 'Section: ' . $print_section_label : 'Current filtered OJT list'), ENT_QUOTES, 'UTF-8'); ?></p>
    <table>
        <thead>
            <tr></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
<?php include 'includes/footer.php'; ?>
<?php $conn->close(); ?>







