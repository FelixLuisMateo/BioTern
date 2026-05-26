<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
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

function dtr_format_range_label(string $from, string $to): string
{
    if ($from === '' && $to === '') {
        return '-';
    }
    if ($from === $to || $to === '') {
        return $from;
    }
    return $from . ' to ' . $to;
}

function dtr_proof_url(string $origin, array $row): string
{
    $proofId = (int)($row['proof_id'] ?? 0);
    if ($proofId <= 0) {
        return '';
    }
    return $origin === 'external'
        ? 'external-dtr-proof.php?id=' . $proofId
        : 'manual-dtr-proof.php?id=' . $proofId;
}

$manualDtrLockedOrigin = isset($manualDtrLockedOrigin) ? strtolower(trim((string)$manualDtrLockedOrigin)) : '';
$manualDtrPageLabel = trim((string)($manualDtrPageLabel ?? 'Manual DTR Review'));
$manualDtrPageMode = strtolower(trim((string)($manualDtrPageMode ?? 'review')));
if (!in_array($manualDtrPageMode, ['review', 'results'], true)) {
    $manualDtrPageMode = 'review';
}
$manualDtrCurrentPage = basename((string)($_SERVER['PHP_SELF'] ?? 'reports-dtr-manual-input.php'));
if ($manualDtrCurrentPage === 'reports-dtr-manual-input.php' && $manualDtrLockedOrigin === 'internal') {
    $manualDtrCurrentPage = $manualDtrPageMode === 'results'
        ? 'reports-dtr-manual-internal-results.php'
        : 'reports-dtr-manual-internal.php';
} elseif ($manualDtrCurrentPage === 'reports-dtr-manual-input.php' && $manualDtrLockedOrigin === 'external') {
    $manualDtrCurrentPage = $manualDtrPageMode === 'results'
        ? 'reports-dtr-manual-external-results.php'
        : 'reports-dtr-manual-external.php';
}

$flash = $_SESSION['manual_dtr_flash'] ?? null;
unset($_SESSION['manual_dtr_flash']);

$students = [];
$studentSql = 'SELECT id, student_id, first_name, last_name FROM students';
if ($coordinatorStudentScopeSql !== '') {
    $studentSql .= ' WHERE ' . $coordinatorStudentScopeSql;
}
$studentSql .= ' ORDER BY last_name ASC, first_name ASC LIMIT 1000';
$studentRes = $conn->query($studentSql);
if ($studentRes instanceof mysqli_result) {
    while ($s = $studentRes->fetch_assoc()) {
        $students[] = $s;
    }
    $studentRes->close();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = strtolower(trim((string)($_POST['manual_dtr_action'] ?? 'create')));

    if (in_array($action, ['approve_selected', 'reject_selected'], true)) {
        $origin = strtolower(trim((string)($_POST['origin'] ?? 'internal')));
        $origin = $origin === 'external' ? 'external' : 'internal';
        $ids = isset($_POST['attendance_ids']) && is_array($_POST['attendance_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['attendance_ids']))))
            : [];
        $reviewNote = trim((string)($_POST['review_note'] ?? ''));
        $newStatus = $action === 'approve_selected' ? 'approved' : 'rejected';

        if ($ids === []) {
            $_SESSION['manual_dtr_flash'] = ['type' => 'warning', 'message' => 'Select at least one manual DTR date first.'];
            header('Location: ' . $manualDtrCurrentPage);
            exit;
        }
        if ($newStatus === 'rejected' && $reviewNote === '') {
            $_SESSION['manual_dtr_flash'] = ['type' => 'warning', 'message' => 'A review note is required when rejecting manual DTR.'];
            header('Location: ' . $manualDtrCurrentPage);
            exit;
        }

        $idList = implode(',', array_map('intval', $ids));
        $reviewerId = (int)($_SESSION['user_id'] ?? 0);
        $updated = 0;
        $studentIds = [];

        if ($origin === 'external') {
            $lookup = $conn->query("SELECT DISTINCT student_id FROM external_attendance WHERE id IN ({$idList})");
            if ($lookup instanceof mysqli_result) {
                while ($row = $lookup->fetch_assoc()) {
                    $studentIds[] = (int)($row['student_id'] ?? 0);
                }
                $lookup->close();
            }
            $stmt = $conn->prepare("
                UPDATE external_attendance
                SET status = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    notes = CASE WHEN ? <> '' THEN CONCAT(TRIM(COALESCE(notes, '')), CASE WHEN TRIM(COALESCE(notes, '')) = '' THEN '' ELSE ' | ' END, ?) ELSE notes END,
                    updated_at = NOW()
                WHERE id IN ({$idList})
                  AND source = 'manual'
            ");
            if ($stmt) {
                $stmt->bind_param('siss', $newStatus, $reviewerId, $reviewNote, $reviewNote);
                $stmt->execute();
                $updated = max(0, (int)$stmt->affected_rows);
                $stmt->close();
            }
            foreach (array_unique(array_filter($studentIds)) as $studentIdForSync) {
                external_attendance_sync_student_hours($conn, (int)$studentIdForSync);
            }
        } else {
            $lookup = $conn->query("SELECT DISTINCT student_id FROM attendances WHERE id IN ({$idList})");
            if ($lookup instanceof mysqli_result) {
                while ($row = $lookup->fetch_assoc()) {
                    $studentIds[] = (int)($row['student_id'] ?? 0);
                }
                $lookup->close();
            }
            $stmt = $conn->prepare("
                UPDATE attendances
                SET status = ?,
                    approved_by = CASE WHEN ? = 'approved' THEN ? ELSE approved_by END,
                    approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_at END,
                    remarks = CASE WHEN ? <> '' THEN CONCAT(TRIM(COALESCE(remarks, '')), CASE WHEN TRIM(COALESCE(remarks, '')) = '' THEN '' ELSE ' | ' END, ?) ELSE remarks END,
                    updated_at = NOW()
                WHERE id IN ({$idList})
                  AND source = 'manual'
            ");
            if ($stmt) {
                $stmt->bind_param('ssisss', $newStatus, $newStatus, $reviewerId, $newStatus, $reviewNote, $reviewNote);
                $stmt->execute();
                $updated = max(0, (int)$stmt->affected_rows);
                $stmt->close();
            }
            foreach (array_unique(array_filter($studentIds)) as $studentIdForSync) {
                if (function_exists('attendance_workflow_sync_student_progress')) {
                    attendance_workflow_sync_student_progress($conn, (int)$studentIdForSync);
                }
            }
        }
        manual_dtr_requests_sync_for_attendance_ids($conn, $ids, $newStatus, $reviewerId, $reviewNote);
        if ($updated > 0) {
            biotern_admin_activity_log(
                $conn,
                $newStatus === 'approved' ? 'approve' : 'reject',
                'manual_dtr_request',
                null,
                [
                    'origin' => $origin,
                    'attendance_ids' => $ids,
                    'status' => $newStatus,
                    'review_note' => $reviewNote,
                ],
                null,
                ucfirst($newStatus) . ' ' . $updated . ' manual DTR date(s).'
            );
        }

        $_SESSION['manual_dtr_flash'] = [
            'type' => $updated > 0 ? 'success' : 'warning',
            'message' => $updated > 0
                ? ucfirst($newStatus) . ' ' . $updated . ' manual DTR date(s).'
                : 'No manual DTR rows were updated.',
        ];
        $return = $manualDtrCurrentPage;
        foreach (['origin', 'student_id', 'from', 'to'] as $key) {
            if (isset($_POST[$key]) && trim((string)$_POST[$key]) !== '') {
                $return .= (str_contains($return, '?') ? '&' : '?') . urlencode($key) . '=' . urlencode((string)$_POST[$key]);
            }
        }
        header('Location: ' . $return);
        exit;
    }

    if ($action === 'delete') {
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        if ($attendanceId > 0) {
            if ($coordinatorStudentScopeSql !== '') {
                $scopeStmt = $conn->prepare("
                    SELECT a.id
                    FROM attendances a
                    INNER JOIN students scope_s ON scope_s.id = a.student_id
                    WHERE a.id = ?
                      AND a.source = 'manual'
                      AND " . str_replace('course_id', 'scope_s.course_id', $coordinatorStudentScopeSql) . "
                    LIMIT 1
                ");
                if (!$scopeStmt) {
                    $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'Unable to verify coordinator course access.'];
                    header('Location: ' . $manualDtrCurrentPage);
                    exit;
                }
                $scopeStmt->bind_param('i', $attendanceId);
                $scopeStmt->execute();
                $allowedAttendance = (bool)$scopeStmt->get_result()->fetch_assoc();
                $scopeStmt->close();
                if (!$allowedAttendance) {
                    $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'You can only delete manual DTR records for students in your assigned courses.'];
                    header('Location: ' . $manualDtrCurrentPage);
                    exit;
                }
            }
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
        header('Location: ' . $manualDtrCurrentPage);
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
        header('Location: ' . $manualDtrCurrentPage);
        exit;
    }
    if ($coordinatorStudentScopeSql !== '') {
        $scopeStmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND {$coordinatorStudentScopeSql} LIMIT 1");
        if (!$scopeStmt) {
            $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'Unable to verify coordinator course access.'];
            header('Location: ' . $manualDtrCurrentPage);
            exit;
        }
        $scopeStmt->bind_param('i', $studentId);
        $scopeStmt->execute();
        $allowedStudent = (bool)$scopeStmt->get_result()->fetch_assoc();
        $scopeStmt->close();
        if (!$allowedStudent) {
            $_SESSION['manual_dtr_flash'] = ['type' => 'danger', 'message' => 'You can only add manual DTR records for students in your assigned courses.'];
            header('Location: ' . $manualDtrCurrentPage);
            exit;
        }
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

    header('Location: ' . $manualDtrCurrentPage);
    exit;
}

$filterDate = trim((string)($_GET['date'] ?? ''));
$filterStudent = (int)($_GET['student_id'] ?? 0);
$queueOrigin = strtolower(trim((string)($_GET['origin_filter'] ?? 'all')));
if (!in_array($queueOrigin, ['all', 'internal', 'external'], true)) {
    $queueOrigin = 'all';
}
if (in_array($manualDtrLockedOrigin, ['internal', 'external'], true)) {
    $queueOrigin = $manualDtrLockedOrigin;
}
$queueStatusDefault = $manualDtrPageMode === 'results' ? 'all' : 'pending';
$queueStatus = strtolower(trim((string)($_GET['status_filter'] ?? $queueStatusDefault)));
if (!in_array($queueStatus, ['pending', 'approved', 'rejected', 'all'], true)) {
    $queueStatus = $queueStatusDefault;
}
if ($manualDtrPageMode === 'review') {
    $queueStatus = 'pending';
} elseif ($queueStatus === 'pending') {
    $queueStatus = 'all';
}
$queueSearch = trim((string)($_GET['q'] ?? ''));
$queueDateFrom = trim((string)($_GET['from'] ?? ''));
$queueDateTo = trim((string)($_GET['to'] ?? ''));
if ($queueDateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $queueDateFrom) !== 1) {
    $queueDateFrom = '';
}
if ($queueDateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $queueDateTo) !== 1) {
    $queueDateTo = '';
}
$where = ["a.source = 'manual'"];
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate) === 1) {
    $where[] = "a.attendance_date = '" . $conn->real_escape_string($filterDate) . "'";
}
if ($filterStudent > 0) {
    $where[] = 'a.student_id = ' . $filterStudent;
}
if ($coordinatorStudentScopeSql !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM students scope_s WHERE scope_s.id = a.student_id AND ' . str_replace('course_id', 'scope_s.course_id', $coordinatorStudentScopeSql) . ')';
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

$detailOrigin = strtolower(trim((string)($_GET['origin'] ?? '')));
$detailOrigin = $detailOrigin === 'external' ? 'external' : ($detailOrigin === 'internal' ? 'internal' : '');
$detailStudentId = (int)($_GET['student_id'] ?? 0);
$detailFrom = trim((string)($_GET['from'] ?? ''));
$detailTo = trim((string)($_GET['to'] ?? ''));
$detailRows = [];
$detailMeta = null;

$scopeInternalSql = $coordinatorStudentScopeSql !== ''
    ? ' AND ' . str_replace('course_id', 's.course_id', $coordinatorStudentScopeSql)
    : '';
$scopeExternalSql = $scopeInternalSql;

$submissionRows = [];
$queueInternalWhere = '';
$queueExternalWhere = '';
if ($queueSearch !== '') {
    $safeSearch = $conn->real_escape_string($queueSearch);
    $searchSql = " AND (
        s.student_id LIKE '%{$safeSearch}%'
        OR s.first_name LIKE '%{$safeSearch}%'
        OR s.last_name LIKE '%{$safeSearch}%'
        OR c.name LIKE '%{$safeSearch}%'
        OR sec.code LIKE '%{$safeSearch}%'
        OR sec.name LIKE '%{$safeSearch}%'
    )";
    $queueInternalWhere .= $searchSql;
    $queueExternalWhere .= $searchSql;
}
if ($manualDtrPageMode === 'results' && $queueStatus === 'all') {
    $queueInternalStatusWhere = " AND LOWER(COALESCE(a.status, 'pending')) IN ('approved', 'rejected')";
    $queueExternalStatusWhere = " AND LOWER(COALESCE(ea.status, 'pending')) IN ('approved', 'rejected')";
} else {
    $queueInternalStatusWhere = " AND LOWER(COALESCE(a.status, 'pending')) = '" . $conn->real_escape_string($queueStatus) . "'";
    $queueExternalStatusWhere = " AND LOWER(COALESCE(ea.status, 'pending')) = '" . $conn->real_escape_string($queueStatus) . "'";
}
if ($queueDateFrom !== '' && $queueDateTo !== '') {
    $safeQueueFrom = $conn->real_escape_string($queueDateFrom);
    $safeQueueTo = $conn->real_escape_string($queueDateTo);
    $queueInternalWhere .= " AND a.attendance_date BETWEEN '{$safeQueueFrom}' AND '{$safeQueueTo}'";
    $queueExternalWhere .= " AND ea.attendance_date BETWEEN '{$safeQueueFrom}' AND '{$safeQueueTo}'";
} elseif ($queueDateFrom !== '') {
    $safeQueueFrom = $conn->real_escape_string($queueDateFrom);
    $queueInternalWhere .= " AND a.attendance_date >= '{$safeQueueFrom}'";
    $queueExternalWhere .= " AND ea.attendance_date >= '{$safeQueueFrom}'";
} elseif ($queueDateTo !== '') {
    $safeQueueTo = $conn->real_escape_string($queueDateTo);
    $queueInternalWhere .= " AND a.attendance_date <= '{$safeQueueTo}'";
    $queueExternalWhere .= " AND ea.attendance_date <= '{$safeQueueTo}'";
}
$internalSubmissionsSql = "
    SELECT
        'internal' COLLATE utf8mb4_general_ci AS origin,
        s.id AS student_id,
        CONVERT(s.student_id USING utf8mb4) COLLATE utf8mb4_general_ci AS student_number,
        CONVERT(s.first_name USING utf8mb4) COLLATE utf8mb4_general_ci AS first_name,
        CONVERT(s.last_name USING utf8mb4) COLLATE utf8mb4_general_ci AS last_name,
        CONVERT(COALESCE(c.name, '') USING utf8mb4) COLLATE utf8mb4_general_ci AS course_name,
        CONVERT(COALESCE(sec.code, sec.name, '') USING utf8mb4) COLLATE utf8mb4_general_ci AS section_label,
        MIN(a.attendance_date) AS date_from,
        MAX(a.attendance_date) AS date_to,
        COUNT(*) AS day_count,
        MIN(mda.id) AS proof_id,
        MIN(mdr.id) AS request_id,
        CONVERT(MAX(mdr.reason_category) USING utf8mb4) COLLATE utf8mb4_general_ci AS reason_category,
        CONVERT(COALESCE(a.status, 'pending') USING utf8mb4) COLLATE utf8mb4_general_ci AS status,
        MAX(a.created_at) AS submitted_at,
        CONVERT(GROUP_CONCAT(DISTINCT NULLIF(TRIM(a.remarks), '') SEPARATOR ' | ') USING utf8mb4) COLLATE utf8mb4_general_ci AS notes
    FROM attendances a
    INNER JOIN students s ON s.id = a.student_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    INNER JOIN manual_dtr_attachments mda ON mda.attendance_id = a.id AND mda.deleted_at IS NULL
    LEFT JOIN manual_dtr_request_entries mdre ON mdre.attendance_id = a.id
    LEFT JOIN manual_dtr_requests mdr ON mdr.id = mdre.request_id
    WHERE a.source = 'manual'
      {$queueInternalStatusWhere}
      {$scopeInternalSql}
      {$queueInternalWhere}
    GROUP BY s.id, s.student_id, s.first_name, s.last_name, c.name, sec.code, sec.name, a.status
";
$externalSubmissionsSql = "
    SELECT
        'external' COLLATE utf8mb4_general_ci AS origin,
        s.id AS student_id,
        CONVERT(s.student_id USING utf8mb4) COLLATE utf8mb4_general_ci AS student_number,
        CONVERT(s.first_name USING utf8mb4) COLLATE utf8mb4_general_ci AS first_name,
        CONVERT(s.last_name USING utf8mb4) COLLATE utf8mb4_general_ci AS last_name,
        CONVERT(COALESCE(c.name, '') USING utf8mb4) COLLATE utf8mb4_general_ci AS course_name,
        CONVERT(COALESCE(sec.code, sec.name, '') USING utf8mb4) COLLATE utf8mb4_general_ci AS section_label,
        MIN(ea.attendance_date) AS date_from,
        MAX(ea.attendance_date) AS date_to,
        COUNT(*) AS day_count,
        MIN(eda.id) AS proof_id,
        NULL AS request_id,
        '' COLLATE utf8mb4_general_ci AS reason_category,
        CONVERT(COALESCE(ea.status, 'pending') USING utf8mb4) COLLATE utf8mb4_general_ci AS status,
        MAX(ea.created_at) AS submitted_at,
        CONVERT(GROUP_CONCAT(DISTINCT NULLIF(TRIM(ea.notes), '') SEPARATOR ' | ') USING utf8mb4) COLLATE utf8mb4_general_ci AS notes
    FROM external_attendance ea
    INNER JOIN students s ON s.id = ea.student_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    LEFT JOIN external_dtr_attachments eda ON eda.external_attendance_id = ea.id AND eda.deleted_at IS NULL
    WHERE ea.source = 'manual'
      {$queueExternalStatusWhere}
      {$scopeExternalSql}
      {$queueExternalWhere}
    GROUP BY s.id, s.student_id, s.first_name, s.last_name, c.name, sec.code, sec.name, ea.status
";
if ($queueOrigin === 'internal') {
    $submissionSql = "{$internalSubmissionsSql} ORDER BY submitted_at DESC, last_name ASC";
} elseif ($queueOrigin === 'external') {
    $submissionSql = "{$externalSubmissionsSql} ORDER BY submitted_at DESC, last_name ASC";
} else {
    $submissionSql = "SELECT * FROM ({$internalSubmissionsSql} UNION ALL {$externalSubmissionsSql}) manual_queue ORDER BY submitted_at DESC, last_name ASC";
}
$submissionRes = $conn->query($submissionSql);
if ($submissionRes instanceof mysqli_result) {
    while ($row = $submissionRes->fetch_assoc()) {
        $submissionRows[] = $row;
    }
    $submissionRes->close();
}

if ($detailOrigin !== '' && $detailStudentId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $detailFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $detailTo)) {
    $safeFrom = $conn->real_escape_string($detailFrom);
    $safeTo = $conn->real_escape_string($detailTo);
    if ($detailOrigin === 'external') {
        $detailSql = "
            SELECT
                ea.id,
                ea.attendance_date,
                ea.morning_time_in,
                ea.morning_time_out,
                ea.afternoon_time_in,
                ea.afternoon_time_out,
                ea.total_hours,
                ea.status,
                ea.notes AS remarks,
                eda.id AS proof_id,
                s.student_id AS student_number,
                s.first_name,
                s.last_name,
                COALESCE(c.name, '') AS course_name,
                COALESCE(sec.code, sec.name, '') AS section_label
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
            WHERE ea.student_id = {$detailStudentId}
              AND ea.source = 'manual'
              AND ea.attendance_date BETWEEN '{$safeFrom}' AND '{$safeTo}'
              {$scopeExternalSql}
            ORDER BY ea.attendance_date ASC, ea.id ASC
        ";
    } else {
        $detailSql = "
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
                mda.id AS proof_id,
                s.student_id AS student_number,
                s.first_name,
                s.last_name,
                COALESCE(c.name, '') AS course_name,
                COALESCE(sec.code, sec.name, '') AS section_label
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
            WHERE a.student_id = {$detailStudentId}
              AND a.source = 'manual'
              AND a.attendance_date BETWEEN '{$safeFrom}' AND '{$safeTo}'
              {$scopeInternalSql}
            ORDER BY a.attendance_date ASC, a.id ASC
        ";
    }
    $detailRes = $conn->query($detailSql);
    if ($detailRes instanceof mysqli_result) {
        while ($row = $detailRes->fetch_assoc()) {
            $detailRows[] = $row;
        }
        $detailRes->close();
    }
    if ($detailRows !== []) {
        $detailMeta = $detailRows[0];
    }
}

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css']);
$page_scripts = array_merge($page_scripts ?? [], ['assets/js/modules/reports/reports-shell-runtime.js']);
$page_title = 'BioTern || ' . $manualDtrPageLabel;
include 'includes/header.php';
?>
<style>
    .manual-dtr-submission-row {
        cursor: pointer;
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
    }

    .manual-dtr-submission-row:hover > * {
        background-color: rgba(37, 99, 235, 0.14) !important;
    }

    .manual-dtr-submission-row:focus-within > *,
    .manual-dtr-submission-row:focus > * {
        background-color: rgba(37, 99, 235, 0.18) !important;
        box-shadow: inset 3px 0 0 #2563eb;
    }
</style>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10"><?php echo dtr_h($manualDtrPageLabel); ?></h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                <li class="breadcrumb-item"><?php echo dtr_h($manualDtrPageLabel); ?></li>
            </ul>
        </div>
    </div>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="alert alert-<?php echo dtr_h((string)($flash['type'] ?? 'info')); ?>"><?php echo dtr_h((string)$flash['message']); ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?php echo dtr_h($manualDtrCurrentPage); ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="manualDtrSearch">Search Student</label>
                    <input type="text" class="form-control" id="manualDtrSearch" name="q" value="<?php echo dtr_h($queueSearch); ?>" placeholder="Name, ID, course, section">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="manualDtrOrigin">Type</label>
                    <?php if ($manualDtrLockedOrigin !== ''): ?>
                    <input type="hidden" name="origin_filter" value="<?php echo dtr_h($manualDtrLockedOrigin); ?>">
                    <input type="text" class="form-control" value="<?php echo dtr_h(ucfirst($manualDtrLockedOrigin)); ?>" readonly>
                    <?php else: ?>
                    <select class="form-select" id="manualDtrOrigin" name="origin_filter">
                        <option value="all" <?php echo $queueOrigin === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="internal" <?php echo $queueOrigin === 'internal' ? 'selected' : ''; ?>>Internal</option>
                        <option value="external" <?php echo $queueOrigin === 'external' ? 'selected' : ''; ?>>External</option>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="manualDtrStatus">Status</label>
                    <?php if ($manualDtrPageMode === 'review'): ?>
                    <input type="hidden" name="status_filter" value="pending">
                    <input type="text" class="form-control" value="Pending" readonly>
                    <?php else: ?>
                    <select class="form-select" id="manualDtrStatus" name="status_filter">
                        <option value="approved" <?php echo $queueStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $queueStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="all" <?php echo $queueStatus === 'all' ? 'selected' : ''; ?>>Approved + Rejected</option>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="manualDtrFrom">From</label>
                    <input type="date" class="form-control" id="manualDtrFrom" name="from" value="<?php echo dtr_h($queueDateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="manualDtrTo">To</label>
                    <input type="date" class="form-control" id="manualDtrTo" name="to" value="<?php echo dtr_h($queueDateTo); ?>">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?php echo dtr_h($manualDtrCurrentPage); ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?php echo $manualDtrPageMode === 'results' ? 'Manual DTR Results' : 'Student Manual DTR Submissions'; ?></span>
            <span class="badge bg-soft-warning text-warning"><?php echo count($submissionRows); ?> <?php echo dtr_h($queueStatus === 'all' ? 'records' : $queueStatus); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Type</th>
                            <th>Date Range</th>
                            <th>Days</th>
                            <?php if ($manualDtrPageMode === 'results'): ?>
                            <th>Status</th>
                            <?php endif; ?>
                            <th>Proof</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($submissionRows === []): ?>
                            <tr><td colspan="<?php echo $manualDtrPageMode === 'results' ? 9 : 8; ?>" class="text-center py-4 text-muted">No student manual DTR submissions found for this filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissionRows as $submission): ?>
                                <?php
                                $openUrl = 'reports-dtr-manual-student.php?origin=' . urlencode((string)$submission['origin'])
                                    . '&student_id=' . (int)$submission['student_id']
                                    . '&from=' . urlencode((string)$submission['date_from'])
                                    . '&to=' . urlencode((string)$submission['date_to'])
                                    . '&view=' . urlencode($manualDtrPageMode);
                                $proofUrl = dtr_proof_url((string)$submission['origin'], $submission);
                                ?>
                                <tr class="manual-dtr-submission-row cursor-pointer" tabindex="0" role="link" onclick="window.location.href='<?php echo dtr_h($openUrl); ?>'" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='<?php echo dtr_h($openUrl); ?>'; }">
                                    <td>
                                        <div class="fw-semibold"><?php echo dtr_h(trim((string)($submission['first_name'] ?? '') . ' ' . (string)($submission['last_name'] ?? ''))); ?></div>
                                        <small class="text-muted"><?php echo dtr_h((string)($submission['student_number'] ?? '-')); ?></small>
                                    </td>
                                    <td><?php echo dtr_h((string)($submission['course_name'] ?? '-')); ?></td>
                                    <td><?php echo dtr_h((string)($submission['section_label'] ?? '-')); ?></td>
                                    <td>
                                        <span class="badge bg-soft-<?php echo $submission['origin'] === 'external' ? 'info text-info' : 'primary text-primary'; ?>"><?php echo dtr_h(ucfirst((string)$submission['origin'])); ?></span>
                                        <?php if (!empty($submission['request_id'])): ?>
                                            <div class="small text-muted mt-1">#<?php echo (int)$submission['request_id']; ?> <?php echo dtr_h(manual_dtr_category_label((string)($submission['reason_category'] ?? 'other'))); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo dtr_h(dtr_format_range_label((string)$submission['date_from'], (string)$submission['date_to'])); ?></td>
                                    <td><?php echo (int)($submission['day_count'] ?? 0); ?></td>
                                    <?php if ($manualDtrPageMode === 'results'): ?>
                                    <td>
                                        <?php $resultStatus = strtolower((string)($submission['status'] ?? 'pending')); ?>
                                        <span class="badge bg-soft-<?php echo $resultStatus === 'rejected' ? 'danger text-danger' : 'success text-success'; ?>">
                                            <?php echo dtr_h(ucfirst($resultStatus)); ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($proofUrl !== ''): ?>
                                            <a href="<?php echo dtr_h($proofUrl); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="badge bg-soft-secondary text-secondary">View proof</a>
                                        <?php else: ?>
                                            <span class="badge bg-soft-secondary text-secondary">No proof</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo dtr_h((string)($submission['submitted_at'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div> <!-- .nxl-content -->
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
