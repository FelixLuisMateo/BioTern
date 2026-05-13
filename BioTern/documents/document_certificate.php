<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor']);

$currentUserId = get_current_user_id_or_zero();
$currentRole = get_current_user_role();
$selectedStudentId = (int)($_GET['id'] ?? $_GET['student_id'] ?? 0);

function certificate_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function certificate_person_name(array $row, string $prefix): string
{
    return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
        (string)($row[$prefix . '_first_name'] ?? ''),
        (string)($row[$prefix . '_middle_name'] ?? ''),
        (string)($row[$prefix . '_last_name'] ?? ''),
    ], static fn($part) => trim($part) !== ''))));
}

function certificate_display_date(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '' || $date === '0000-00-00') {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('F j, Y', $timestamp) : $date;
}

function certificate_supervisor_profile_id(mysqli $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT id FROM supervisors WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function certificate_coordinator_profile_id(mysqli $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT id FROM coordinators WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function certificate_scope_sql(mysqli $conn, string $role, int $userId): string
{
    if ($role === 'admin') {
        return '1 = 1';
    }

    if ($role === 'supervisor') {
        $profileId = certificate_supervisor_profile_id($conn, $userId);
        $ids = array_values(array_unique(array_filter([$userId, $profileId], static fn($id) => (int)$id > 0)));
        if ($ids === []) {
            return '1 = 0';
        }
        $idList = implode(',', array_map('intval', $ids));
        return "(s.supervisor_id IN ({$idList}) OR i.supervisor_id IN ({$idList}))";
    }

    if ($role === 'coordinator') {
        $profileId = certificate_coordinator_profile_id($conn, $userId);
        $ids = array_values(array_unique(array_filter([$userId, $profileId], static fn($id) => (int)$id > 0)));
        $parts = [];
        if ($ids !== []) {
            $idList = implode(',', array_map('intval', $ids));
            $parts[] = "(s.coordinator_id IN ({$idList}) OR i.coordinator_id IN ({$idList}))";
        }
        if (table_exists($conn, 'coordinator_courses')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM coordinator_courses cc
                WHERE cc.coordinator_user_id = " . (int)$userId . "
                  AND cc.course_id = s.course_id
                LIMIT 1
            )";
        }
        return $parts !== [] ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
    }

    return '1 = 0';
}

$scopeSql = certificate_scope_sql($conn, $currentRole, $currentUserId);
$externalCompletionSql = "
    LOWER(TRIM(COALESCE(s.assignment_track, ''))) = 'external'
    AND (
        LOWER(TRIM(COALESCE(i.status, ''))) IN ('completed', 'finished')
        OR COALESCE(i.completion_percentage, 0) >= 100
        OR GREATEST(
            COALESCE(ea_stats.approved_hours, 0),
            COALESCE(i.rendered_hours, 0),
            GREATEST(0, COALESCE(s.external_total_hours, 250) - COALESCE(s.external_total_hours_remaining, COALESCE(s.external_total_hours, 250)))
        ) >= COALESCE(NULLIF(i.required_hours, 0), NULLIF(s.external_total_hours, 0), 250)
        OR COALESCE(s.external_total_hours_remaining, 999999) <= 0
    )
";
$certificate = null;
$eligibleStudents = [];
$error = '';

if ($selectedStudentId > 0) {
    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.school_year,
            c.name AS course_name,
            sec.code AS section_code,
            sec.name AS section_name,
            i.company_name,
            i.position,
            i.start_date,
            i.end_date,
            COALESCE(NULLIF(i.required_hours, 0), NULLIF(s.external_total_hours, 0), 250) AS required_hours,
            GREATEST(
                COALESCE(ea_stats.approved_hours, 0),
                COALESCE(i.rendered_hours, 0),
                GREATEST(0, COALESCE(s.external_total_hours, 250) - COALESCE(s.external_total_hours_remaining, COALESCE(s.external_total_hours, 250)))
            ) AS rendered_hours,
            i.completion_percentage,
            ea_stats.first_attendance_date,
            ea_stats.last_attendance_date,
            ea_stats.approved_hours,
            COALESCE(coord_i.first_name, coord_s.first_name) AS coordinator_first_name,
            COALESCE(coord_i.middle_name, coord_s.middle_name) AS coordinator_middle_name,
            COALESCE(coord_i.last_name, coord_s.last_name) AS coordinator_last_name,
            COALESCE(sup_i.first_name, sup_s.first_name) AS supervisor_first_name,
            COALESCE(sup_i.middle_name, sup_s.middle_name) AS supervisor_middle_name,
            COALESCE(sup_i.last_name, sup_s.last_name) AS supervisor_last_name,
            s.coordinator_name AS fallback_coordinator_name,
            s.supervisor_name AS fallback_supervisor_name,
            e.score,
            e.evaluation_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        LEFT JOIN internships i ON i.id = (
            SELECT i2.id FROM internships i2
            WHERE i2.student_id = s.id
              AND i2.type = 'external'
            ORDER BY FIELD(i2.status, 'completed', 'finished', 'ongoing', 'pending', 'cancelled'), i2.id DESC
            LIMIT 1
        )
        LEFT JOIN (
            SELECT
                student_id,
                MIN(attendance_date) AS first_attendance_date,
                MAX(attendance_date) AS last_attendance_date,
                SUM(total_hours) AS approved_hours
            FROM external_attendance
            WHERE status = 'approved'
            GROUP BY student_id
        ) ea_stats ON ea_stats.student_id = s.id
        LEFT JOIN coordinators coord_i ON coord_i.user_id = i.coordinator_id
        LEFT JOIN coordinators coord_s ON coord_s.id = s.coordinator_id
        LEFT JOIN supervisors sup_i ON sup_i.user_id = i.supervisor_id
        LEFT JOIN supervisors sup_s ON sup_s.id = s.supervisor_id
        LEFT JOIN evaluations e ON e.id = (
            SELECT e2.id FROM evaluations e2
            WHERE e2.student_id = s.id
            ORDER BY e2.evaluation_date DESC, e2.id DESC
            LIMIT 1
        )
        WHERE s.id = ? AND {$scopeSql} AND {$externalCompletionSql}
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $selectedStudentId);
        $stmt->execute();
        $certificate = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    if (!$certificate) {
        $error = 'Certificate is not available yet. Make sure the student exists and you have access to that student.';
    }
}

if ($eligibleStudents === []) {
    $result = $conn->query("
        SELECT
            s.id,
            s.student_id,
            TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student_name,
            c.name AS course_name,
            COALESCE(NULLIF(i.required_hours, 0), NULLIF(s.external_total_hours, 0), 250) AS required_hours,
            GREATEST(
                COALESCE(ea_stats.approved_hours, 0),
                COALESCE(i.rendered_hours, 0),
                GREATEST(0, COALESCE(s.external_total_hours, 250) - COALESCE(s.external_total_hours_remaining, COALESCE(s.external_total_hours, 250)))
            ) AS rendered_hours,
            i.completion_percentage,
            COALESCE(e.evaluation_date, i.end_date, CURDATE()) AS evaluation_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN internships i ON i.id = (
            SELECT i2.id FROM internships i2
            WHERE i2.student_id = s.id
              AND i2.type = 'external'
              AND i2.status IN ('ongoing', 'completed', 'finished')
            ORDER BY FIELD(i2.status, 'completed', 'finished', 'ongoing'), i2.id DESC
            LIMIT 1
        )
        LEFT JOIN (
            SELECT student_id, SUM(total_hours) AS approved_hours
            FROM external_attendance
            WHERE status = 'approved'
            GROUP BY student_id
        ) ea_stats ON ea_stats.student_id = s.id
        LEFT JOIN evaluations e ON e.id = (
            SELECT e2.id FROM evaluations e2
            WHERE e2.student_id = s.id
            ORDER BY e2.evaluation_date DESC, e2.id DESC
            LIMIT 1
        )
        WHERE {$scopeSql} AND {$externalCompletionSql}
        ORDER BY COALESCE(e.evaluation_date, i.end_date, s.updated_at, s.created_at) DESC, s.last_name ASC, s.first_name ASC
        LIMIT 100
    ");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $eligibleStudents[] = $row;
        }
        $result->close();
    }
}

$page_title = 'Certificate of Completion';
$page_styles = [
    'assets/css/layout/page_shell.css',
];
include __DIR__ . '/../includes/header.php';
?>
<style>
    .certificate-picker-card,
    .certificate-print-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 10px;
        background: var(--bs-card-bg);
    }
    .certificate-print-area {
        display: flex;
        justify-content: center;
        overflow-x: auto;
        padding: .25rem 0;
    }
    .certificate-builder-grid {
        display: grid;
        grid-template-columns: minmax(300px, 390px) minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }
    .certificate-builder-panel {
        position: sticky;
        top: 92px;
        border: 1px solid var(--bs-border-color);
        border-radius: 14px;
        background: var(--bs-card-bg);
        padding: 18px;
    }
    .certificate-builder-panel h6 {
        margin: 0;
        font-size: 1rem;
    }
    .certificate-builder-panel p {
        margin: 6px 0 0;
        color: var(--bs-secondary-color);
        font-size: .84rem;
        line-height: 1.4;
    }
    .certificate-builder-field {
        margin-top: 14px;
    }
    .certificate-student-info {
        margin-top: 16px;
        padding: 14px;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-tertiary-bg);
    }
    .certificate-student-info-title {
        margin: 0 0 10px;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .certificate-student-info-row {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 10px;
        padding: 7px 0;
        border-top: 1px solid var(--bs-border-color);
        font-size: .82rem;
        line-height: 1.35;
    }
    .certificate-student-info-row:first-of-type {
        border-top: 0;
        padding-top: 0;
    }
    .certificate-student-info-label {
        color: var(--bs-secondary-color);
        font-weight: 700;
    }
    .certificate-student-info-value {
        color: var(--bs-body-color);
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .certificate-builder-field .form-label {
        margin-bottom: 6px;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .certificate-builder-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 18px;
    }
    .certificate-builder-actions .btn {
        min-height: 42px;
    }
    .certificate-preview-card {
        --certificate-preview-scale: 1;
        --certificate-preview-height: auto;
        border: 1px solid var(--bs-border-color);
        border-radius: 14px;
        background: var(--bs-card-bg);
        padding: 16px;
        min-width: 0;
        overflow: hidden;
    }
    .certificate-preview-card .certificate-print-area {
        justify-content: center;
        overflow: hidden;
        position: relative;
        height: var(--certificate-preview-height);
        min-height: 360px;
        padding: 0;
    }
    .certificate-preview-card .certificate-sheet {
        position: absolute;
        top: 0;
        left: 50%;
        flex: 0 0 auto;
        width: 1056px;
        max-width: none;
        margin: 0;
        transform: translateX(-50%) scale(var(--certificate-preview-scale));
        transform-origin: top center;
    }
    .certificate-fill-line {
        display: inline;
        min-width: 0;
        border-bottom: 0;
        padding: 0;
        line-height: inherit;
        vertical-align: baseline;
    }
    .certificate-name .certificate-fill-line {
        min-width: 0;
        border-bottom: 0;
    }
    .certificate-body .certificate-fill-line {
        min-width: 0;
        font-weight: 700;
    }
    .certificate-meta .certificate-fill-line {
        min-width: 0;
    }
    .certificate-sign-line .certificate-fill-line {
        min-width: 0;
        border-bottom: 0;
        padding: 0;
    }
    .certificate-sheet {
        position: relative;
        box-sizing: border-box;
        overflow: hidden;
        width: min(100%, 1056px);
        aspect-ratio: 297 / 210;
        min-height: 0;
        margin: 0 auto;
        padding: clamp(14px, 2vw, 22px) clamp(48px, 6vw, 70px) clamp(24px, 3.2vw, 32px);
        background:
            radial-gradient(circle at 50% 50%, rgba(255, 251, 237, 0.95) 0 42%, rgba(249, 239, 212, 0.88) 100%),
            #fff7df;
        color: #06243a;
        border: 1px solid #f3cf73;
        border-radius: 8px;
        box-shadow: 0 18px 46px rgba(15, 23, 42, .16);
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
    }
    .certificate-sheet::before,
    .certificate-sheet::after {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 0;
    }
    .certificate-sheet::before {
        background:
            linear-gradient(166deg, #06243a 0 8.6%, transparent 8.7% 100%),
            linear-gradient(158deg, transparent 0 9.5%, #f27b0f 9.6% 11.6%, transparent 11.7% 100%),
            linear-gradient(145deg, transparent 0 11.8%, #ffc20a 11.9% 21.2%, transparent 21.3% 100%),
            linear-gradient(42deg, transparent 0 75.5%, #ffc20a 75.6% 83.2%, transparent 83.3% 100%),
            linear-gradient(42deg, transparent 0 80.8%, #06243a 80.9% 89.4%, transparent 89.5% 100%),
            linear-gradient(315deg, #06243a 0 5.8%, transparent 5.9% 100%),
            linear-gradient(58deg, transparent 0 86.4%, #f27b0f 86.5% 88.1%, transparent 88.2% 100%),
            repeating-linear-gradient(54deg, transparent 0 11px, rgba(255, 194, 10, 0.92) 11px 18px, transparent 18px 27px);
        background-size: 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 158px 158px;
        background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, right -26px top -16px;
        background-repeat: no-repeat;
    }
    .certificate-sheet::after {
        background:
            radial-gradient(circle at 86% 27%, rgba(6, 36, 58, .28) 0 1px, transparent 1.2px),
            radial-gradient(circle at 16% 55%, rgba(6, 36, 58, .2) 0 1px, transparent 1.2px);
        background-size: 7px 7px, 7px 7px;
        mask-image: radial-gradient(circle at 86% 27%, #000 0 95px, transparent 96px), radial-gradient(circle at 16% 55%, #000 0 128px, transparent 129px);
    }
    .certificate-content {
        position: static;
        display: grid;
        width: 100%;
        max-width: 850px;
        margin: 0 auto;
        justify-items: center;
    }
    .certificate-content > :not(.certificate-seal) {
        position: relative;
        z-index: 1;
    }
    .certificate-logo {
        width: 66px;
        height: 66px;
        object-fit: contain;
        margin: -10px 0 4px;
    }
    .certificate-school {
        font-family: Arial, sans-serif;
        font-size: 17px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .certificate-school-subtitle {
        font-family: Arial, sans-serif;
        font-size: 12px;
        color: #526171;
        margin-top: 2px;
    }
    .certificate-title {
        position: relative;
        display: inline-block;
        margin: 10px 0 0;
        padding: 0 64px;
        font-family: Arial, sans-serif;
        font-size: clamp(38px, 5.2vw, 64px);
        line-height: .95;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0;
        color: #06243a;
    }
    .certificate-title::before,
    .certificate-title::after {
        content: "";
        position: absolute;
        top: 50%;
        width: 58px;
        border-top: 5px solid #06243a;
    }
    .certificate-title::before {
        left: 0;
    }
    .certificate-title::after {
        right: 0;
    }
    .certificate-title span {
        display: block;
        margin-top: 2px;
        font-size: clamp(22px, 2.7vw, 32px);
        color: #e77817;
    }
    .certificate-name {
        margin: 12px auto 6px;
        padding-bottom: 3px;
        width: min(100%, 680px);
        border-bottom: 2px solid #e77817;
        font-size: clamp(42px, 6vw, 68px);
        font-weight: 700;
        font-style: italic;
        color: #06243a;
        line-height: 1.02;
    }
    .certificate-body {
        max-width: 700px;
        margin: 6px auto;
        font-family: Arial, sans-serif;
        font-size: 14px;
        line-height: 1.34;
    }
    .certificate-given-line {
        max-width: 700px;
        margin: 5px auto 0;
        font-family: Arial, sans-serif;
        font-size: 12px;
        color: #27384a;
    }
    .certificate-presented {
        margin-top: 10px;
        font-family: Arial, sans-serif;
        font-size: 13px;
        font-style: italic;
        color: #30475f;
    }
    .certificate-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px 20px;
        width: min(100%, 760px);
        margin: 12px auto 0;
        text-align: center;
        font-family: Arial, sans-serif;
        font-size: 11px;
        color: #27384a;
    }
    .certificate-signatures {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 92px;
        width: min(100%, 640px);
        margin: 70px auto 0;
        font-family: Arial, sans-serif;
        font-size: 13px;
    }
    .certificate-sign-line {
        border-top: 1px solid #06243a;
        padding-top: 9px;
        font-weight: 700;
    }
    .certificate-sign-role {
        display: block;
        margin-top: 2px;
        color: #526171;
        font-weight: 500;
    }
    .certificate-seal {
        position: absolute;
        z-index: 2;
        left: 50%;
        bottom: 64px;
        width: 76px;
        height: 76px;
        transform: translateX(-50%);
        border-radius: 50%;
        background:
            radial-gradient(circle at 38% 32%, #fff2a7 0 10%, #f5c94d 24%, #d89b22 60%, #fff1a4 76%, #b97913 100%);
        box-shadow: 0 2px 0 rgba(110, 72, 12, .22), inset 0 0 0 6px rgba(255, 244, 171, .75), inset 0 0 0 9px rgba(202, 138, 4, .45);
    }
    .certificate-seal::before,
    .certificate-seal::after {
        content: "";
        position: absolute;
        top: 58px;
        width: 24px;
        height: 52px;
        background: linear-gradient(#f6c246, #d99019);
        clip-path: polygon(0 0, 100% 0, 76% 100%, 50% 78%, 24% 100%);
        z-index: -1;
    }
    .certificate-seal::before {
        left: 14px;
        transform: rotate(12deg);
    }
    .certificate-seal::after {
        right: 14px;
        transform: rotate(-12deg);
    }
    @media (max-width: 768px) {
        .certificate-builder-grid {
            grid-template-columns: 1fr;
        }
        .certificate-builder-panel {
            position: static;
        }
        .certificate-sheet {
            padding: 32px 22px 42px;
        }
        .certificate-title {
            font-size: 36px;
            padding: 0 42px;
        }
        .certificate-title::before,
        .certificate-title::after {
            width: 32px;
            border-top-width: 4px;
        }
        .certificate-title span {
            font-size: 22px;
        }
        .certificate-name {
            font-size: 34px;
        }
        .certificate-meta,
        .certificate-signatures {
            grid-template-columns: 1fr;
        }
    }
    @media (min-width: 992px) {
        .certificate-builder-panel {
            max-height: calc(100vh - 120px);
            overflow: auto;
        }
    }
    @page {
        size: landscape;
        margin: 0;
    }
    @media print {
        html,
        body {
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #fff !important;
        }
        body > * {
            display: none !important;
        }
        body > .nxl-container {
            display: block !important;
            visibility: visible !important;
            position: static !important;
            inset: 0 !important;
            width: auto !important;
            height: 0 !important;
            min-height: 0 !important;
            overflow: visible !important;
        }
        body * {
            visibility: hidden !important;
        }
        .certificate-print-area,
        .certificate-print-area * {
            visibility: visible !important;
        }
        .certificate-print-area {
            position: fixed;
            inset: 0;
            display: block !important;
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #fff;
            contain: size layout paint;
            break-after: avoid;
            page-break-after: avoid;
        }
        .certificate-preview-card .certificate-print-area {
            position: fixed !important;
            height: auto !important;
            min-height: 0 !important;
        }
        .certificate-preview-card .certificate-sheet {
            top: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            width: auto !important;
            height: auto !important;
            max-width: none !important;
            margin: 0 !important;
            transform: none !important;
            transform-origin: initial !important;
        }
        .certificate-sheet {
            position: absolute !important;
            inset: 0 !important;
            width: auto !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            aspect-ratio: auto !important;
            margin: 0 !important;
            padding: 1.2% 6.8% 4.3% !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            break-inside: avoid;
            page-break-inside: avoid;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .certificate-content {
            max-width: 850px !important;
            height: 100% !important;
            align-content: start !important;
            padding-top: 2px !important;
        }
        .certificate-logo {
            width: 78px !important;
            height: 78px !important;
            margin: 0 0 6px !important;
        }
        .certificate-school {
            font-size: 21px !important;
            letter-spacing: .07em !important;
        }
        .certificate-school-subtitle {
            font-size: 14px !important;
            margin-top: 2px !important;
        }
        .certificate-title {
            margin-top: 30px !important;
            padding: 0 64px !important;
            font-size: 64px !important;
        }
        .certificate-title::before,
        .certificate-title::after {
            width: 58px !important;
            border-top-width: 5px !important;
        }
        .certificate-title span {
            font-size: 32px !important;
        }
        .certificate-presented {
            margin-top: 10px !important;
            font-size: 13px !important;
        }
        .certificate-name {
            width: min(100%, 680px) !important;
            margin: 12px auto 6px !important;
            font-size: 68px !important;
            line-height: 1.02 !important;
        }
        .certificate-body {
            max-width: 700px !important;
            margin: 6px auto !important;
            font-size: 14px !important;
            line-height: 1.35 !important;
        }
        .certificate-given-line {
            max-width: 700px !important;
            margin: 5px auto 0 !important;
            font-size: 12px !important;
        }
        .certificate-meta {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            width: min(100%, 760px) !important;
            margin-top: 12px !important;
            gap: 8px 20px !important;
            font-size: 11px !important;
        }
        .certificate-signatures {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            width: min(100%, 640px) !important;
            gap: 92px !important;
            margin-top: 70px !important;
            font-size: 13px !important;
        }
        .certificate-sign-line {
            padding-top: 9px !important;
        }
        .certificate-seal {
            width: 76px !important;
            height: 76px !important;
            bottom: 92px !important;
            pointer-events: none !important;
        }
        .certificate-seal::before,
        .certificate-seal::after {
            top: 58px !important;
            width: 24px !important;
            height: 52px !important;
        }
        .certificate-no-print {
            display: none !important;
        }
        .certificate-preview-card {
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            padding: 0 !important;
        }
        .nxl-container,
        .nxl-content,
        .main-content {
            width: auto !important;
            height: 0 !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #fff !important;
        }
        .nxl-content,
        .main-content {
            display: contents !important;
        }
    }
</style>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header certificate-no-print">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Certificate of Completion</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Documents</li>
                    <li class="breadcrumb-item">Certificate of Completion</li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <?php
            $studentName = '';
            $sectionLabel = '';
            $rating = '-';
            $requiredHours = 250.0;
            $renderedHours = 0.0;
            $dateRange = '';
            $coordinatorName = 'OJT Coordinator';
            $supervisorName = 'Supervisor';
            $dateIssued = certificate_display_date(date('Y-m-d'));
            $positionLabel = '';
            $schoolYearLabel = '';
            $completionLabel = '';

            if ($certificate) {
                $studentName = trim((string)($certificate['first_name'] ?? '') . ' ' . (string)($certificate['middle_name'] ?? '') . ' ' . (string)($certificate['last_name'] ?? ''));
                $sectionLabel = biotern_format_section_label((string)($certificate['section_code'] ?? ''), (string)($certificate['section_name'] ?? ''));
                $score = (int)($certificate['score'] ?? 0);
                $rating = $score > 0 ? ($score > 5 ? ($score . '%') : ($score . '/5')) : '-';
                $requiredHours = (float)($certificate['required_hours'] ?? 250);
                if ($requiredHours <= 0) {
                    $requiredHours = 250;
                }
                $renderedHours = (float)($certificate['rendered_hours'] ?? $certificate['approved_hours'] ?? 0);
                $dateFrom = certificate_display_date($certificate['first_attendance_date'] ?? $certificate['start_date'] ?? '');
                $dateTo = certificate_display_date($certificate['last_attendance_date'] ?? $certificate['end_date'] ?? $certificate['evaluation_date'] ?? '');
                $dateRange = $dateFrom !== '' && $dateTo !== '' ? ($dateFrom . ' to ' . $dateTo) : ($dateFrom . $dateTo);
                $coordinatorName = certificate_person_name($certificate, 'coordinator');
                if ($coordinatorName === '') {
                    $coordinatorName = trim((string)($certificate['fallback_coordinator_name'] ?? '')) ?: 'OJT Coordinator';
                }
                $supervisorName = certificate_person_name($certificate, 'supervisor');
                if ($supervisorName === '') {
                    $supervisorName = trim((string)($certificate['fallback_supervisor_name'] ?? '')) ?: 'Supervisor';
                }
                $dateIssued = certificate_display_date($certificate['evaluation_date'] ?? date('Y-m-d'));
                $positionLabel = trim((string)($certificate['position'] ?? ''));
                $schoolYearLabel = trim((string)($certificate['school_year'] ?? ''));
                $completionValue = (float)($certificate['completion_percentage'] ?? 0);
                $completionLabel = $completionValue > 0 ? number_format($completionValue, 2) . '%' : '';
            }
            $studentInfoRows = [
                'Name' => $studentName,
                'Student ID' => (string)($certificate['student_id'] ?? ''),
                'Course' => (string)($certificate['course_name'] ?? ''),
                'Section' => $sectionLabel,
                'School Year' => $schoolYearLabel,
                'Host Company' => (string)($certificate['company_name'] ?? ''),
                'Position' => $positionLabel,
                'Training Dates' => $dateRange,
                'Hours' => number_format($renderedHours, 2) . ' / ' . number_format($requiredHours, 0),
                'Completion' => $completionLabel,
                'Evaluation' => $rating,
                'Date Given' => $dateIssued,
            ];
            ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger certificate-no-print"><?php echo certificate_h($error); ?></div>
            <?php endif; ?>

            <div class="certificate-builder-grid">
                <aside class="certificate-builder-panel certificate-no-print">
                    <h6>Certificate Builder</h6>
                    <p>Select an eligible external OJT student, then adjust any fill-in fields before printing.</p>

                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_student_select">Completed Student</label>
                        <select id="certificate_student_select" class="form-select">
                            <option value="">Choose completed student</option>
                            <?php foreach ($eligibleStudents as $row): ?>
                                <option value="<?php echo (int)($row['id'] ?? 0); ?>" <?php echo (int)($row['id'] ?? 0) === $selectedStudentId ? 'selected' : ''; ?>>
                                    <?php echo certificate_h(($row['student_name'] ?? 'Student') . ' - ' . ($row['student_id'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="certificate-student-info">
                        <p class="certificate-student-info-title">Autofilled Student Info</p>
                        <?php foreach ($studentInfoRows as $label => $value): ?>
                            <?php $displayValue = trim((string)$value); ?>
                            <div class="certificate-student-info-row">
                                <span class="certificate-student-info-label"><?php echo certificate_h($label); ?></span>
                                <span class="certificate-student-info-value"><?php echo certificate_h($displayValue !== '' ? $displayValue : '-'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_student_name_input">Student Name</label>
                        <input id="certificate_student_name_input" class="form-control" data-certificate-target="certificate_student_name" value="<?php echo certificate_h($studentName); ?>" placeholder="Student full name">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_required_hours_input">Required Hours</label>
                        <input id="certificate_required_hours_input" class="form-control" data-certificate-target="certificate_required_hours" value="<?php echo certificate_h(number_format($requiredHours, 0)); ?>" placeholder="250">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_date_range_input">Training Date Range</label>
                        <input id="certificate_date_range_input" class="form-control" data-certificate-target="certificate_date_range" value="<?php echo certificate_h($dateRange); ?>" placeholder="May 1, 2026 to May 30, 2026">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_course_input">Course</label>
                        <input id="certificate_course_input" class="form-control" data-certificate-target="certificate_course" value="<?php echo certificate_h((string)($certificate['course_name'] ?? '')); ?>" placeholder="Course name">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_student_id_input">Student ID</label>
                        <input id="certificate_student_id_input" class="form-control" data-certificate-target="certificate_student_id" value="<?php echo certificate_h($certificate['student_id'] ?? ''); ?>" placeholder="Student ID">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_section_input">Section</label>
                        <input id="certificate_section_input" class="form-control" data-certificate-target="certificate_section" value="<?php echo certificate_h($sectionLabel); ?>" placeholder="Section">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_company_input">Host Company</label>
                        <input id="certificate_company_input" class="form-control" data-certificate-target="certificate_company" value="<?php echo certificate_h($certificate['company_name'] ?? ''); ?>" placeholder="Host company">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_rendered_hours_input">Rendered Hours</label>
                        <input id="certificate_rendered_hours_input" class="form-control" data-certificate-target="certificate_rendered_hours" value="<?php echo certificate_h(number_format($renderedHours, 2)); ?>" placeholder="0.00">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_rating_input">Evaluation Rating</label>
                        <input id="certificate_rating_input" class="form-control" data-certificate-target="certificate_rating" value="<?php echo certificate_h($rating); ?>" placeholder="-">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_date_issued_input">Date Issued</label>
                        <input id="certificate_date_issued_input" class="form-control" data-certificate-targets="certificate_date_issued certificate_given_date" value="<?php echo certificate_h($dateIssued); ?>" placeholder="Date issued">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_coordinator_input">Coordinator</label>
                        <input id="certificate_coordinator_input" class="form-control" data-certificate-target="certificate_coordinator" value="<?php echo certificate_h($coordinatorName); ?>" placeholder="OJT Coordinator">
                    </div>
                    <div class="certificate-builder-field">
                        <label class="form-label" for="certificate_supervisor_input">Supervisor</label>
                        <input id="certificate_supervisor_input" class="form-control" data-certificate-target="certificate_supervisor" value="<?php echo certificate_h($supervisorName); ?>" placeholder="Supervisor">
                    </div>

                    <div class="certificate-builder-actions">
                        <a class="btn btn-light" href="document_certificate.php">Reset</a>
                        <button type="button" class="btn btn-primary" id="certificate_print_btn">Print</button>
                    </div>
                </aside>

                <div class="certificate-preview-card">
                    <section class="certificate-print-area">
                        <div class="certificate-sheet">
                            <div class="certificate-content">
                                <img class="certificate-logo" src="assets/images/ccstlogo.png" alt="CCST Logo">
                                <div class="certificate-school">Clark College of Science and Technology (CCST)</div>
                                <div class="certificate-school-subtitle">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                                <div class="certificate-title">Certificate <span>of Completion</span></div>
                                <div class="certificate-presented">This certificate is presented to</div>
                                <div class="certificate-name"><span id="certificate_student_name" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="Student Name"><?php echo certificate_h($studentName !== '' ? $studentName : 'Student Name'); ?></span></div>
                                <div class="certificate-body">
                                    Congratulations on completing <strong><span id="certificate_required_hours" class="certificate-live-field" data-certificate-placeholder="250"><?php echo certificate_h(number_format($requiredHours, 0)); ?></span> hours</strong>
                                    of external internship training from <strong><span id="certificate_date_range" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="Training Date Range"><?php echo certificate_h($dateRange !== '' ? $dateRange : 'Training Date Range'); ?></span></strong>.
                                    This achievement is recognized by CCST as part of the required OJT completion for
                                    <strong><span id="certificate_course" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="Course Name"><?php echo certificate_h((string)($certificate['course_name'] ?? 'Course Name')); ?></span></strong>.
                                </div>
                                <div class="certificate-given-line">
                                    Given this day of <strong><span id="certificate_given_date" class="certificate-live-field" data-certificate-placeholder="<?php echo certificate_h($dateIssued); ?>"><?php echo certificate_h($dateIssued); ?></span></strong>.
                                </div>
                                <div class="certificate-meta">
                                    <div><strong>Student ID:</strong> <span id="certificate_student_id" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="Student ID"><?php echo certificate_h(($certificate['student_id'] ?? '') !== '' ? $certificate['student_id'] : 'Student ID'); ?></span></div>
                                    <div><strong>Section:</strong> <span id="certificate_section" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="-"><?php echo certificate_h($sectionLabel !== '' ? $sectionLabel : '-'); ?></span></div>
                                    <div><strong>Host Company:</strong> <span id="certificate_company" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="-"><?php echo certificate_h(($certificate['company_name'] ?? '') !== '' ? $certificate['company_name'] : '-'); ?></span></div>
                                    <div><strong>Rendered Hours:</strong> <span id="certificate_rendered_hours" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="0.00"><?php echo certificate_h(number_format($renderedHours, 2)); ?></span></div>
                                    <div><strong>Evaluation Rating:</strong> <span id="certificate_rating" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="-"><?php echo certificate_h($rating); ?></span></div>
                                    <div><strong>Date Issued:</strong> <span id="certificate_date_issued" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="<?php echo certificate_h($dateIssued); ?>"><?php echo certificate_h($dateIssued); ?></span></div>
                                </div>
                                <span class="certificate-seal" aria-hidden="true"></span>
                                <div class="certificate-signatures">
                                    <div>
                                        <div class="certificate-sign-line">
                                            <span id="certificate_coordinator" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="OJT Coordinator"><?php echo certificate_h($coordinatorName); ?></span>
                                            <span class="certificate-sign-role">OJT Coordinator</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="certificate-sign-line">
                                            <span id="certificate_supervisor" class="certificate-live-field certificate-fill-line" data-certificate-placeholder="Supervisor"><?php echo certificate_h($supervisorName); ?></span>
                                            <span class="certificate-sign-role">Supervisor</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var studentSelect = document.getElementById('certificate_student_select');
    var printButton = document.getElementById('certificate_print_btn');
    var previewCard = document.querySelector('.certificate-preview-card');
    var previewSheet = previewCard ? previewCard.querySelector('.certificate-sheet') : null;

    function resizeCertificatePreview() {
        if (!previewCard || !previewSheet) {
            return;
        }

        var availableWidth = Math.max(280, previewCard.clientWidth - 32);
        var sheetWidth = 1056;
        var sheetHeight = sheetWidth / (297 / 210);
        var scale = Math.min(1, availableWidth / sheetWidth);

        previewCard.style.setProperty('--certificate-preview-scale', scale.toFixed(4));
        previewCard.style.setProperty('--certificate-preview-height', Math.ceil(sheetHeight * scale) + 'px');
    }

    function normalizeValue(value, target) {
        var text = String(value || '').trim();
        return text !== '' ? text : (target.getAttribute('data-certificate-placeholder') || '-');
    }

    function syncCertificateField(input) {
        var targetIds = input.getAttribute('data-certificate-targets') || input.getAttribute('data-certificate-target') || '';
        targetIds.split(/\s+/).forEach(function (targetId) {
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                return;
            }
            target.textContent = normalizeValue(input.value, target);
        });
    }

    document.querySelectorAll('[data-certificate-target], [data-certificate-targets]').forEach(function (input) {
        syncCertificateField(input);
        input.addEventListener('input', function () {
            syncCertificateField(input);
            resizeCertificatePreview();
        });
    });

    if (studentSelect) {
        studentSelect.addEventListener('change', function () {
            var id = String(studentSelect.value || '').trim();
            if (id !== '') {
                window.location.href = 'document_certificate.php?id=' + encodeURIComponent(id);
            }
        });
    }

    if (printButton) {
        printButton.addEventListener('click', function () {
            document.querySelectorAll('[data-certificate-target], [data-certificate-targets]').forEach(syncCertificateField);
            window.print();
        });
    }

    resizeCertificatePreview();
    window.addEventListener('resize', resizeCertificatePreview);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
