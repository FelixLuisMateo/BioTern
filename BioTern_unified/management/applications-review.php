<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS application_submitted_at DATETIME NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approval_notes VARCHAR(255) NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS disciplinary_remark VARCHAR(255) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS gender VARCHAR(30) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(255) NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(50) NULL");

$departmentOptions = [];
$courseOptions = [];
$sectionOptions = [];
$coordinatorOptions = [];
$supervisorOptions = [];

$courseResult = $conn->query("SELECT id, code, name FROM courses ORDER BY name ASC");
if ($courseResult) {
    while ($course = $courseResult->fetch_assoc()) {
        $courseOptions[] = [
            'id' => (int)($course['id'] ?? 0),
            'code' => trim((string)($course['code'] ?? '')),
            'name' => trim((string)($course['name'] ?? '')),
        ];
    }
}

$sectionResult = $conn->query("SELECT id, course_id, code, name FROM sections ORDER BY code ASC, name ASC");
if ($sectionResult) {
    while ($sec = $sectionResult->fetch_assoc()) {
        $sectionOptions[] = [
            'id' => (int)($sec['id'] ?? 0),
            'course_id' => (int)($sec['course_id'] ?? 0),
            'code' => trim((string)($sec['code'] ?? '')),
            'name' => trim((string)($sec['name'] ?? '')),
        ];
    }
}

$departmentResult = $conn->query("SELECT id, code, name FROM departments ORDER BY name ASC");
if ($departmentResult) {
    while ($dep = $departmentResult->fetch_assoc()) {
        $departmentOptions[] = [
            'id' => (int)($dep['id'] ?? 0),
            'code' => trim((string)($dep['code'] ?? '')),
            'name' => trim((string)($dep['name'] ?? '')),
        ];
    }
}

$coordinatorResult = $conn->query("SELECT id, department_id, CONCAT(first_name, ' ', last_name) AS full_name FROM coordinators WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
if ($coordinatorResult) {
    while ($coor = $coordinatorResult->fetch_assoc()) {
        $coordinatorOptions[] = [
            'id' => (int)($coor['id'] ?? 0),
            'department_id' => (int)($coor['department_id'] ?? 0),
            'full_name' => trim((string)($coor['full_name'] ?? '')),
        ];
    }
}

$supervisorResult = $conn->query("SELECT id, department_id, CONCAT(first_name, ' ', last_name) AS full_name FROM supervisors WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
if ($supervisorResult) {
    while ($sup = $supervisorResult->fetch_assoc()) {
        $supervisorOptions[] = [
            'id' => (int)($sup['id'] ?? 0),
            'department_id' => (int)($sup['department_id'] ?? 0),
            'full_name' => trim((string)($sup['full_name'] ?? '')),
        ];
    }
}

$coordinatorNameMap = [];
foreach ($coordinatorOptions as $item) {
    $coordinatorNameMap[(int)$item['id']] = (string)$item['full_name'];
}

$supervisorNameMap = [];
foreach ($supervisorOptions as $item) {
    $supervisorNameMap[(int)$item['id']] = (string)$item['full_name'];
}

function formatDisplayDateTime($rawValue)
{
    $value = trim((string)$rawValue);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('M d, Y h:i A', $timestamp);
}

$flashType = '';
$flashMessage = '';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = (string)$_SESSION['flash_message'];
    $flashType = isset($_SESSION['flash_type']) ? (string)$_SESSION['flash_type'] : 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
    $notes = trim((string)($_POST['approval_notes'] ?? ''));
    $disciplinaryRemark = trim((string)($_POST['disciplinary_remark'] ?? ''));
    $internalHoursRaw = isset($_POST['internal_total_hours']) ? trim((string)$_POST['internal_total_hours']) : '140';
    $externalHoursRaw = isset($_POST['external_total_hours']) ? trim((string)$_POST['external_total_hours']) : '250';
    $departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $coordinatorId = isset($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : 0;
    $supervisorId = isset($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : 0;

    $internalHours = is_numeric($internalHoursRaw) ? (int)$internalHoursRaw : -1;
    $externalHours = is_numeric($externalHoursRaw) ? (int)$externalHoursRaw : -1;
    $coordinatorName = $coordinatorId > 0 && isset($coordinatorNameMap[$coordinatorId]) ? $coordinatorNameMap[$coordinatorId] : null;
    $supervisorName = $supervisorId > 0 && isset($supervisorNameMap[$supervisorId]) ? $supervisorNameMap[$supervisorId] : null;

    if ($userId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        $flashType = 'danger';
        $flashMessage = 'Invalid request.';
    } elseif ($internalHours < 0 || $externalHours < 0) {
        $flashType = 'danger';
        $flashMessage = 'Hours must be valid non-negative numbers.';
    } else {
        if ($decision === 'approve') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE users SET application_status = 'approved', is_active = 1, approved_by = ?, approved_at = NOW(), rejected_at = NULL, approval_notes = ?, disciplinary_remark = ? WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception('Unable to update application status.');
                }
                $stmt->bind_param('issi', $currentUserId, $notes, $disciplinaryRemark, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception('Unable to approve application.');
                }
                $stmt->close();

                $studentStmt = $conn->prepare("UPDATE students SET department_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), coordinator_name = ?, supervisor_id = NULLIF(?, 0), supervisor_name = ?, internal_total_hours = ?, external_total_hours = ?, internal_total_hours_remaining = CASE WHEN assignment_track = 'external' THEN 0 ELSE ? END, external_total_hours_remaining = CASE WHEN assignment_track = 'external' THEN ? ELSE 0 END WHERE user_id = ? LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception('Unable to update student hour settings.');
                }
                $studentStmt->bind_param('iisiisiiii', $departmentId, $coordinatorId, $coordinatorName, $supervisorId, $supervisorName, $internalHours, $externalHours, $internalHours, $externalHours, $userId);
                if (!$studentStmt->execute()) {
                    $studentStmt->close();
                    throw new Exception('Unable to save updated assignment and hour settings.');
                }
                $studentStmt->close();

                // Create internship record on approval (if not already created).
                $studentRow = null;
                $studentLookup = $conn->prepare("SELECT id, course_id, department_id, coordinator_id, supervisor_id, assignment_track, internal_total_hours, external_total_hours FROM students WHERE user_id = ? LIMIT 1");
                if ($studentLookup) {
                    $studentLookup->bind_param('i', $userId);
                    $studentLookup->execute();
                    $studentRow = $studentLookup->get_result()->fetch_assoc();
                    $studentLookup->close();
                }

                if ($studentRow && !empty($studentRow['id'])) {
                    $studentId = (int)$studentRow['id'];
                    $courseId = (int)($studentRow['course_id'] ?? 0);
                    $departmentId = (int)($studentRow['department_id'] ?? 0);
                    $coordinatorId = (int)($studentRow['coordinator_id'] ?? 0);
                    $supervisorId = (int)($studentRow['supervisor_id'] ?? 0);
                    $assignmentTrack = strtolower((string)($studentRow['assignment_track'] ?? 'internal'));
                    $internalHours = (int)($studentRow['internal_total_hours'] ?? 0);
                    $externalHours = (int)($studentRow['external_total_hours'] ?? 0);

                    if ($studentId > 0 && $courseId > 0 && $departmentId > 0 && $coordinatorId > 0 && $supervisorId > 0) {
                        $existsIntern = $conn->prepare("SELECT id FROM internships WHERE student_id = ? LIMIT 1");
                        $hasIntern = false;
                        if ($existsIntern) {
                            $existsIntern->bind_param('i', $studentId);
                            $existsIntern->execute();
                            $resIntern = $existsIntern->get_result();
                            $hasIntern = ($resIntern && $resIntern->num_rows > 0);
                            $existsIntern->close();
                        }

                        if (!$hasIntern) {
                            $internCoordinatorUserId = 0;
                            $internSupervisorUserId = 0;

                            $mapCoord = $conn->prepare("SELECT user_id FROM coordinators WHERE id = ? LIMIT 1");
                            if ($mapCoord) {
                                $mapCoord->bind_param('i', $coordinatorId);
                                $mapCoord->execute();
                                $coordRow = $mapCoord->get_result()->fetch_assoc();
                                $mapCoord->close();
                                if ($coordRow && !empty($coordRow['user_id'])) {
                                    $internCoordinatorUserId = (int)$coordRow['user_id'];
                                }
                            }

                            $mapSup = $conn->prepare("SELECT user_id FROM supervisors WHERE id = ? LIMIT 1");
                            if ($mapSup) {
                                $mapSup->bind_param('i', $supervisorId);
                                $mapSup->execute();
                                $supRow = $mapSup->get_result()->fetch_assoc();
                                $mapSup->close();
                                if ($supRow && !empty($supRow['user_id'])) {
                                    $internSupervisorUserId = (int)$supRow['user_id'];
                                }
                            }

                            if ($internCoordinatorUserId > 0 && $internSupervisorUserId > 0) {
                                $today = date('Y-m-d');
                                $year = (int)date('Y');
                                $schoolYear = $year . '-' . ($year + 1);
                                $type = $assignmentTrack === 'external' ? 'external' : 'internal';
                                $requiredHours = $type === 'external' ? max(0, $externalHours) : max(0, $internalHours);
                                $renderedHours = 0;
                                $completionPct = 0;

                                $insertIntern = $conn->prepare("
                                    INSERT INTO internships
                                    (student_id, course_id, department_id, coordinator_id, supervisor_id, type, start_date, status, school_year, required_hours, rendered_hours, completion_percentage, created_at, updated_at)
                                    VALUES
                                    (?, ?, ?, ?, ?, ?, ?, 'ongoing', ?, ?, ?, ?, NOW(), NOW())
                                ");
                                if ($insertIntern) {
                                    $insertIntern->bind_param(
                                        'iiiiisssiid',
                                        $studentId,
                                        $courseId,
                                        $departmentId,
                                        $internCoordinatorUserId,
                                        $internSupervisorUserId,
                                        $type,
                                        $today,
                                        $schoolYear,
                                        $requiredHours,
                                        $renderedHours,
                                        $completionPct
                                    );
                                    $insertIntern->execute();
                                    $insertIntern->close();
                                }
                            }
                        }
                    }
                }

                $conn->commit();
                $flashType = 'success';
                $flashMessage = 'Application approved and student hours updated.';
            } catch (Throwable $e) {
                $conn->rollback();
                $flashType = 'danger';
                $flashMessage = 'Unable to process approval: ' . $e->getMessage();
            }
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE users SET application_status = 'rejected', is_active = 0, approved_by = ?, approved_at = NULL, rejected_at = NOW(), approval_notes = ?, disciplinary_remark = ? WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception('Unable to reject application.');
                }
                $stmt->bind_param('issi', $currentUserId, $notes, $disciplinaryRemark, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception('Unable to reject application.');
                }

                $stmt->close();

                $studentStmt = $conn->prepare("UPDATE students SET department_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), coordinator_name = ?, supervisor_id = NULLIF(?, 0), supervisor_name = ? WHERE user_id = ? LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception('Unable to update student assignments.');
                }
                $studentStmt->bind_param('iisisi', $departmentId, $coordinatorId, $coordinatorName, $supervisorId, $supervisorName, $userId);
                if (!$studentStmt->execute()) {
                    $studentStmt->close();
                    throw new Exception('Unable to save updated assignments.');
                }

                $studentStmt->close();
                $conn->commit();
                $flashType = 'warning';
                $flashMessage = 'Application rejected and assignments updated.';
            } catch (Throwable $e) {
                $conn->rollback();
                $flashType = 'danger';
                $flashMessage = 'Unable to process rejection: ' . $e->getMessage();
            }
        }

        if ($flashMessage === '') {
            $flashType = 'danger';
            $flashMessage = 'Unable to process this application.';
        }
    }

    if ($flashMessage !== '') {
        $_SESSION['flash_type'] = $flashType;
        $_SESSION['flash_message'] = $flashMessage;
        $redirect = 'applications-review.php';
        $qs = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
        if ($qs !== '') {
            $redirect .= '?' . $qs;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

$courseFilter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$sectionFilter = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$coordinatorFilter = isset($_GET['coordinator_id']) ? (int)$_GET['coordinator_id'] : 0;
$supervisorFilter = isset($_GET['supervisor_id']) ? (int)$_GET['supervisor_id'] : 0;

$sql = "
    SELECT
        u.id AS user_id,
        u.username,
        u.email,
        u.role,
        u.application_status,
        u.application_submitted_at,
        u.approved_at,
        u.rejected_at,
        u.approval_notes,
        u.disciplinary_remark,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.address,
        s.phone,
        s.date_of_birth,
        s.gender,
        s.emergency_contact,
        s.emergency_contact_phone,
        s.department_id,
        s.coordinator_id,
        s.supervisor_id,
        s.coordinator_name,
        s.supervisor_name,
        s.internal_total_hours,
        s.external_total_hours,
        c.name AS course_name,
        d.name AS department_name,
        sec.code AS section_code,
        sec.name AS section_name
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN departments d ON d.id = s.department_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE u.role = 'student'
";

if ($statusFilter !== 'all') {
    $sql .= " AND u.application_status = '" . $conn->real_escape_string($statusFilter) . "'";
}

if ($courseFilter > 0) {
    $sql .= " AND s.course_id = " . (int)$courseFilter;
}
if ($sectionFilter > 0) {
    $sql .= " AND s.section_id = " . (int)$sectionFilter;
}
if ($coordinatorFilter > 0) {
    $sql .= " AND s.coordinator_id = " . (int)$coordinatorFilter;
}
if ($supervisorFilter > 0) {
    $sql .= " AND s.supervisor_id = " . (int)$supervisorFilter;
}

$sql .= " ORDER BY COALESCE(u.application_submitted_at, u.created_at) DESC, u.id DESC";
$applications = $conn->query($sql);

$page_title = 'BioTern || Student Applications';
include 'includes/header.php';
?>
<style>
    .nxl-container,
    .nxl-content,
    .nxl-content.apps-review-shell {
        padding-top: -22pxd !important;
        margin-top: -22px !important;
        margin-left: -50px;
    }

    .apps-review-shell {
        padding-top: 0;
    }

    .apps-review-shell .apps-review-title-row {
        margin-bottom: 0 !important;
        margin-top: 0 !important;
        padding-bottom: 0 !important;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
    }
    .apps-review-shell .apps-review-title-row,
    .apps-review-shell .main-content {
        width: 100%;
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        padding-left: 8px;
        padding-right: 8px;
        box-sizing: border-box;
    }
    .apps-review-shell .page-subtitle { color: #8a93a6; font-size: 13px; margin-top: 4px; }
    .apps-review-shell .page-header-title h5 {
        margin-bottom: 0 !important;
        margin-left: 0;
    }
    .apps-review-shell .page-subtitle {
        margin-top: 2px;
        margin-bottom: 0;
        margin-left: 0;
    }
    .apps-review-card {
        border: 1px solid rgba(140, 160, 190, 0.18);
        border-radius: 14px;
        overflow: hidden;
        width: 100%;
        margin-top: 0;
        position: relative;
        z-index: 1;
        box-sizing: border-box;
    }

    .apps-review-shell .main-content {
        margin-top: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .apps-review-card .card-body {
        padding: 14px;
    }

    .apps-review-shell .main-content > .apps-review-card,
    .apps-review-shell .main-content > .card.apps-review-card {
        margin-top: 0 !important;
    }

    .apps-review-toolbar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 8px;
        margin-bottom: 10px;
        align-items: end;
    }

    .apps-review-toolbar .form-label { font-size: 12px; margin-bottom: 6px; color: #8a93a6; }
    .apps-review-toolbar .toolbar-actions { display: flex; gap: 8px; justify-content: flex-end; }

    .apps-review-table {
        width: 100%;
        table-layout: auto;
    }

    .apps-review-table thead th {
        font-size: 11px;
        letter-spacing: .35px;
        text-transform: uppercase;
        color: #9aa7c0;
        white-space: normal;
        word-break: break-word;
    }

    .apps-review-table th,
    .apps-review-table td {
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .apps-review-table th:nth-child(1),
    .apps-review-table td:nth-child(1) { width: 30%; }
    .apps-review-table th:nth-child(2),
    .apps-review-table td:nth-child(2) { width: 23%; }
    .apps-review-table th:nth-child(5),
    .apps-review-table td:nth-child(5) { width: 14%; white-space: nowrap; }
    .apps-review-table th:nth-child(6),
    .apps-review-table td:nth-child(6) { width: 12%; text-align: center; }

    .student-block small {
        overflow-wrap: anywhere;
    }

    .student-block { display: flex; align-items: center; gap: 10px; }
    .student-avatar {
        width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #3f66db, #8b5cf6);
    }

    .hours-pill { display: inline-block; border: 1px solid rgba(140, 160, 190, 0.3); border-radius: 999px; padding: 3px 9px; font-weight: 600; }

    .apps-review-table > :not(caption) > * > * {
        padding: 10px 10px;
        vertical-align: middle;
    }

    .expand-btn {
        min-width: 86px;
        width: auto;
        white-space: nowrap;
        font-size: 10px;
        letter-spacing: 0.2px;
        padding: 5px 10px;
        line-height: 1.15;
    }
    .application-detail-row td { padding: 0 !important; border-top: none; }
    .application-detail-box {
        border-top: 1px dashed rgba(140, 160, 190, 0.25);
        background: #f5f7fb;
        padding: 10px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 10px;
        align-items: start;
        width: 100%;
        box-sizing: border-box;
    }

    .detail-grid > * { min-width: 0; }

    .detail-meta {
        border: 1px solid rgba(140, 160, 190, 0.22);
        border-radius: 10px;
        padding: 10px;
        font-size: 12px;
        line-height: 1.3;
        color: #334155;
        background: #ffffff;
    }

    .detail-meta .line { margin-bottom: 4px; color: #475569; }
    .detail-meta .line:last-child { margin-bottom: 0; }
    .detail-meta .line strong { color: #1e293b; font-weight: 600; }

    .action-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(140px, 1fr));
        gap: 6px;
        width: 100%;
        box-sizing: border-box;
    }

    .field-label {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 3px;
        display: inline-block;
    }

    .field-wrap { width: 100%; }

    .action-form .wide-field,
    .action-form .approval-note,
    .action-form .disciplinary-note,
    .action-form .action-buttons { grid-column: 1 / -1; }

    .action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .action-buttons .btn { width: 100%; }

    .action-form .form-control,
    .action-form .form-control-sm {
        font-size: 12px;
        padding-top: 5px;
        padding-bottom: 5px;
    }

    .action-form .btn.btn-sm {
        padding-top: 5px;
        padding-bottom: 5px;
        font-size: 12px;
        line-height: 1.2;
    }

    .app-skin-dark .application-detail-box {
        background: rgba(30, 45, 80, 0.22);
    }

    .app-skin-dark .detail-meta {
        background: rgba(30, 45, 80, 0.18);
        color: #cbd5e1;
        border-color: rgba(140, 160, 190, 0.28);
    }

    .app-skin-dark .detail-meta .line {
        color: #b9c6db;
    }

    .app-skin-dark .detail-meta .line strong {
        color: #e2e8f5;
    }

    .app-skin-dark .field-label {
        color: #9fb0cc;
    }

    .application-detail-box,
    .table-responsive {
        width: 100%;
        box-sizing: border-box;
    }

.table-responsive { overflow-x: hidden; }

.apps-review-table td[data-label="Status"],
.apps-review-table th:nth-child(3),
.apps-review-table td[data-label="Hours (Int/Ext)"],
.apps-review-table th:nth-child(4),
.apps-review-table td[data-label="Submitted"],
.apps-review-table th:nth-child(5) {
    text-align: center;
}

.apps-review-table td[data-label="Review"],
.apps-review-table th:nth-child(6) {
    text-align: center !important;
}

.apps-review-table .expand-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 96px;
}

.apps-review-table td[data-label="Review"] .expand-btn {
    display: inline-flex;
    margin-left: auto;
    margin-right: auto;
}

.alert-auto-dismiss {
    transition: opacity 0.35s ease;
}

.alert-auto-dismiss.is-hiding {
    opacity: 0;
}

    @media (max-width: 1200px) {
        .apps-review-table th:nth-child(1),
        .apps-review-table td:nth-child(1) { width: 29%; }
        .apps-review-table th:nth-child(2),
        .apps-review-table td:nth-child(2) { width: 22%; }
        .apps-review-table th:nth-child(5),
        .apps-review-table td:nth-child(5) { width: 15%; }
        .apps-review-table th:nth-child(6),
        .apps-review-table td:nth-child(6) { width: 13%; }
    }

    @media (max-width: 1100px) {
        .apps-review-table thead {
            display: none;
        }

        .apps-review-table,
        .apps-review-table tbody,
        .apps-review-table tr,
        .apps-review-table td {
            display: block;
            width: 100% !important;
        }

        .apps-review-table tr.summary-row {
            border: 1px solid rgba(140, 160, 190, 0.24);
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
            background: #fff;
        }

        .apps-review-table tr.summary-row td {
            border: 0;
            border-top: 1px dashed rgba(140, 160, 190, 0.22);
            padding: 8px 10px;
            display: grid;
            grid-template-columns: 116px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            text-align: left !important;
            white-space: normal;
        }

        .apps-review-table tr.summary-row td:first-child {
            border-top: 0;
        }

        .apps-review-table tr.summary-row td::before {
            content: attr(data-label);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
            color: #7b8aa4;
            line-height: 1.25;
            padding-top: 2px;
        }

        .student-block {
            align-items: flex-start;
        }

        .apps-review-table td:nth-child(6) {
            text-align: left;
        }

        .apps-review-table td[data-label="Review"] {
            text-align: center !important;
        }

        .expand-btn {
            width: 100%;
            min-width: 0;
        }

        .application-detail-row {
            margin-top: -6px;
            margin-bottom: 10px;
        }

        .application-detail-row td {
            padding: 0 !important;
        }

        .application-detail-box {
            border-radius: 10px;
            border: 1px dashed rgba(140, 160, 190, 0.22);
        }
    }

    @media (max-width: 991px) {
        .detail-grid { grid-template-columns: 1fr; }
        .apps-review-toolbar .toolbar-actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }

    @media (max-width: 767px) {
        .apps-review-shell .apps-review-title-row,
        .apps-review-shell .main-content {
            padding-left: 2px;
            padding-right: 2px;
        }
        .apps-review-card .card-body { padding: 10px; }
        .apps-review-toolbar { grid-template-columns: 1fr 1fr; }
        .apps-review-toolbar .toolbar-actions { grid-column: 1 / -1; }
        .apps-review-table tr.summary-row td {
            grid-template-columns: 96px minmax(0, 1fr);
            gap: 8px;
            padding: 8px;
        }
    }
</style>
<main class="nxl-container">
    <div class="nxl-content apps-review-shell">
        <div class="apps-review-title-row">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Student Applications</h5>
                    <p class="page-subtitle mb-0">Review, approve, and manage internship applications in one clean view.</p>
                </div>
            </div>
        </div>

        <div class="main-content">
            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> alert-auto-dismiss" role="alert">
                    <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="card apps-review-card">
                <div class="card-body">
                    <form method="get" class="apps-review-toolbar">
                        <div>
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Course</label>
                            <select class="form-control" name="course_id">
                                <option value="0">All Courses</option>
                                <?php foreach ($courseOptions as $course): ?>
                                    <?php
                                        $courseId = (int)($course['id'] ?? 0);
                                        $courseCode = trim((string)($course['code'] ?? ''));
                                        $courseName = trim((string)($course['name'] ?? ''));
                                        $courseLabel = $courseCode !== '' ? ($courseCode . ($courseName !== '' ? (' - ' . $courseName) : '')) : $courseName;
                                    ?>
                                    <option value="<?php echo $courseId; ?>" <?php echo $courseFilter === $courseId ? 'selected' : ''; ?>><?php echo htmlspecialchars($courseLabel !== '' ? $courseLabel : ('Course #' . $courseId), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Section</label>
                            <select class="form-control" name="section_id">
                                <option value="0">All Sections</option>
                                <?php foreach ($sectionOptions as $sec): ?>
                                    <?php
                                        $secId = (int)($sec['id'] ?? 0);
                                        $secCode = trim((string)($sec['code'] ?? ''));
                                        $secName = trim((string)($sec['name'] ?? ''));
                                        $secLabel = $secCode !== '' && $secName !== '' ? ($secCode . ' - ' . $secName) : ($secCode !== '' ? $secCode : $secName);
                                    ?>
                                    <option value="<?php echo $secId; ?>" <?php echo $sectionFilter === $secId ? 'selected' : ''; ?>><?php echo htmlspecialchars($secLabel !== '' ? $secLabel : ('Section #' . $secId), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Coordinator</label>
                            <select class="form-control" name="coordinator_id">
                                <option value="0">All Coordinators</option>
                                <?php foreach ($coordinatorOptions as $coor): ?>
                                    <?php $coorId = (int)($coor['id'] ?? 0); ?>
                                    <option value="<?php echo $coorId; ?>" <?php echo $coordinatorFilter === $coorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($coor['full_name'] ?? ('Coordinator #' . $coorId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Supervisor</label>
                            <select class="form-control" name="supervisor_id">
                                <option value="0">All Supervisors</option>
                                <?php foreach ($supervisorOptions as $sup): ?>
                                    <?php $supId = (int)($sup['id'] ?? 0); ?>
                                    <option value="<?php echo $supId; ?>" <?php echo $supervisorFilter === $supId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($sup['full_name'] ?? ('Supervisor #' . $supId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="toolbar-actions">
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            <a href="applications-review.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle apps-review-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Hours (Int/Ext)</th>
                                    <th>Submitted</th>
                                    <th style="width: 120px;">Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($applications && $applications->num_rows > 0): ?>
                                    <?php $rowIndex = 0; ?>
                                    <?php while ($row = $applications->fetch_assoc()): ?>
                                        <?php
                                            $rowIndex++;
                                            $status = strtolower((string)($row['application_status'] ?? 'approved'));
                                            $badge = 'secondary';
                                            if ($status === 'pending') $badge = 'warning';
                                            if ($status === 'approved') $badge = 'success';
                                            if ($status === 'rejected') $badge = 'danger';

                                            $courseName = trim((string)($row['course_name'] ?? ''));
                                            $middleName = trim((string)($row['middle_name'] ?? ''));
                                            $departmentName = trim((string)($row['department_name'] ?? ''));
                                            $sectionCode = trim((string)($row['section_code'] ?? ''));
                                            $sectionName = trim((string)($row['section_name'] ?? ''));
                                            $coordinatorName = trim((string)($row['coordinator_name'] ?? ''));
                                            $supervisorName = trim((string)($row['supervisor_name'] ?? ''));
                                            $addressValue = trim((string)($row['address'] ?? ''));
                                            $phoneValue = trim((string)($row['phone'] ?? ''));
                                            $dateOfBirthValue = trim((string)($row['date_of_birth'] ?? ''));
                                            $genderValue = trim((string)($row['gender'] ?? ''));
                                            $emergencyContactValue = trim((string)($row['emergency_contact'] ?? ''));
                                            $emergencyContactPhoneValue = trim((string)($row['emergency_contact_phone'] ?? ''));

                                            $emergencyContactNameOnly = $emergencyContactValue;
                                            $emergencyPhoneFromContact = '';
                                            if ($emergencyContactValue !== '' && preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $emergencyContactValue, $contactParts)) {
                                                $emergencyContactNameOnly = trim((string)($contactParts[1] ?? ''));
                                                $emergencyPhoneFromContact = trim((string)($contactParts[2] ?? ''));
                                            }
                                            if ($emergencyContactPhoneValue === '' && $emergencyPhoneFromContact !== '') {
                                                $emergencyContactPhoneValue = $emergencyPhoneFromContact;
                                            }
                                            $selectedDepartmentId = (int)($row['department_id'] ?? 0);
                                            $selectedCoordinatorId = (int)($row['coordinator_id'] ?? 0);
                                            $selectedSupervisorId = (int)($row['supervisor_id'] ?? 0);

                                            $courseLabel = $courseName !== '' ? $courseName : 'To be assigned';
                                            $departmentLabel = $departmentName !== '' ? $departmentName : 'To be assigned';
                                            $sectionLabel = 'To be assigned';
                                            if ($sectionCode !== '' && $sectionName !== '') {
                                                $sectionLabel = $sectionCode . ' - ' . $sectionName;
                                            } elseif ($sectionCode !== '') {
                                                $sectionLabel = $sectionCode;
                                            } elseif ($sectionName !== '') {
                                                $sectionLabel = $sectionName;
                                            }
                                            $coordinatorLabel = $coordinatorName !== '' ? $coordinatorName : 'To be assigned';
                                            $supervisorLabel = $supervisorName !== '' ? $supervisorName : 'To be assigned';
                                            $addressLabel = $addressValue !== '' ? $addressValue : '-';
                                            $phoneLabel = $phoneValue !== '' ? $phoneValue : '-';
                                            $dateOfBirthLabel = $dateOfBirthValue !== '' ? $dateOfBirthValue : '-';
                                            $genderLabel = $genderValue !== '' ? ucfirst(strtolower($genderValue)) : '-';
                                            $emergencyContactLabel = $emergencyContactNameOnly !== '' ? $emergencyContactNameOnly : '-';
                                            $emergencyContactPhoneLabel = $emergencyContactPhoneValue !== '' ? $emergencyContactPhoneValue : '-';

                                            $submittedAt = formatDisplayDateTime($row['application_submitted_at'] ?? '');
                                            $approvedAt = formatDisplayDateTime($row['approved_at'] ?? '');
                                            $rejectedAt = formatDisplayDateTime($row['rejected_at'] ?? '');
                                            $firstInitial = strtoupper(substr(trim((string)($row['first_name'] ?? '')), 0, 1));
                                            $lastInitial = strtoupper(substr(trim((string)($row['last_name'] ?? '')), 0, 1));
                                            $initials = trim($firstInitial . $lastInitial);
                                            if ($initials === '') {
                                                $initials = 'ST';
                                            }

                                            $collapseId = 'applicationDetail_' . (int)$row['user_id'] . '_' . $rowIndex;
                                        ?>
                                        <tr class="summary-row">
                                            <td data-label="Student">
                                                <div class="student-block">
                                                    <span class="student-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars(trim((string)($row['first_name'] . ' ' . $row['last_name'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <small class="text-muted d-block">ID: <?php echo htmlspecialchars((string)($row['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Course">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($courseLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted d-block">Section: <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td data-label="Status">
                                                <span class="badge bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td data-label="Hours (Int/Ext)">
                                                <span class="hours-pill"><?php echo (int)($row['internal_total_hours'] ?? 140); ?> / <?php echo (int)($row['external_total_hours'] ?? 250); ?></span>
                                            </td>
                                            <td data-label="Submitted"><?php echo htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-label="Review" class="text-center">
                                                <button class="btn btn-outline-primary btn-sm expand-btn application-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>" data-expand-text="Details" data-collapse-text="Hide">Details</button>
                                            </td>
                                        </tr>
                                        <tr class="application-detail-row">
                                            <td colspan="6">
                                                <div id="<?php echo $collapseId; ?>" class="collapse application-detail-box">
                                                    <div class="detail-grid">
                                                        <div class="detail-meta">
                                                            <div class="line"><strong>Full Name:</strong> <?php echo htmlspecialchars(trim((string)($row['first_name'] . ' ' . $middleName . ' ' . $row['last_name'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Username:</strong> <?php echo htmlspecialchars((string)($row['username'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Email:</strong> <?php echo htmlspecialchars((string)($row['email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Student ID:</strong> <?php echo htmlspecialchars((string)($row['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Address:</strong> <?php echo htmlspecialchars($addressLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Phone:</strong> <?php echo htmlspecialchars($phoneLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Date of Birth:</strong> <?php echo htmlspecialchars($dateOfBirthLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Gender:</strong> <?php echo htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Parent/Emergency Contact:</strong> <?php echo htmlspecialchars($emergencyContactLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Parent/Emergency Phone:</strong> <?php echo htmlspecialchars($emergencyContactPhoneLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Department:</strong> <?php echo htmlspecialchars($departmentLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Section:</strong> <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Coordinator:</strong> <?php echo htmlspecialchars($coordinatorLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Supervisor:</strong> <?php echo htmlspecialchars($supervisorLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Submitted:</strong> <?php echo htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Approval Note:</strong> <?php echo htmlspecialchars((string)($row['approval_notes'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div class="line"><strong>Disciplinary:</strong> <?php echo htmlspecialchars(trim((string)($row['disciplinary_remark'] ?? '')) !== '' ? (string)$row['disciplinary_remark'] : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php if ($status === 'approved' && $approvedAt !== '-'): ?>
                                                                <div class="line"><strong>Approved:</strong> <?php echo htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php elseif ($status === 'rejected' && $rejectedAt !== '-'): ?>
                                                                <div class="line"><strong>Rejected:</strong> <?php echo htmlspecialchars($rejectedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <form method="post" class="action-form">
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>">
                                                            <div class="field-wrap wide-field">
                                                                <label class="field-label">Department</label>
                                                                <select class="form-control form-control-sm" name="department_id" title="Department">
                                                                    <option value="0">Unassigned</option>
                                                                    <?php foreach ($departmentOptions as $dep): ?>
                                                                        <?php
                                                                            $depId = (int)($dep['id'] ?? 0);
                                                                            $depLabel = trim((string)($dep['name'] ?? ''));
                                                                            $depCode = trim((string)($dep['code'] ?? ''));
                                                                            if ($depCode !== '') {
                                                                                $depLabel = $depCode . ($depLabel !== '' ? (' - ' . $depLabel) : '');
                                                                            }
                                                                        ?>
                                                                        <option value="<?php echo $depId; ?>" <?php echo $depId === $selectedDepartmentId ? 'selected' : ''; ?>><?php echo htmlspecialchars($depLabel !== '' ? $depLabel : ('Department #' . $depId), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <select class="form-control form-control-sm" name="coordinator_id" title="Coordinator">
                                                                <option value="0">Coordinator: Unassigned</option>
                                                                <?php foreach ($coordinatorOptions as $coor): ?>
                                                                    <?php $coorId = (int)($coor['id'] ?? 0); ?>
                                                                    <option value="<?php echo $coorId; ?>" <?php echo $coorId === $selectedCoordinatorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($coor['full_name'] ?? ('Coordinator #' . $coorId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <select class="form-control form-control-sm" name="supervisor_id" title="Supervisor">
                                                                <option value="0">Supervisor: Unassigned</option>
                                                                <?php foreach ($supervisorOptions as $sup): ?>
                                                                    <?php $supId = (int)($sup['id'] ?? 0); ?>
                                                                    <option value="<?php echo $supId; ?>" <?php echo $supId === $selectedSupervisorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($sup['full_name'] ?? ('Supervisor #' . $supId)), ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <div class="field-wrap">
                                                                <label class="field-label">Internal OJT Hours</label>
                                                                <input type="number" class="form-control form-control-sm" name="internal_total_hours" min="0" required value="<?php echo (int)($row['internal_total_hours'] ?? 140); ?>" title="Internal OJT Hours">
                                                            </div>
                                                            <div class="field-wrap">
                                                                <label class="field-label">External OJT Hours</label>
                                                                <input type="number" class="form-control form-control-sm" name="external_total_hours" min="0" required value="<?php echo (int)($row['external_total_hours'] ?? 250); ?>" title="External OJT Hours">
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm approval-note" name="approval_notes" placeholder="Add note (optional)">
                                                            <input type="text" class="form-control form-control-sm disciplinary-note" name="disciplinary_remark" placeholder="Disciplinary remark (if misconduct)">
                                                            <div class="action-buttons">
                                                                <button type="submit" name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                                                                <button type="submit" name="decision" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No applications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        var alertEl = document.querySelector('.alert-auto-dismiss');
        if (alertEl) {
            alertEl.classList.add('is-hiding');
            setTimeout(function () {
                alertEl.remove();
            }, 400);
        }
    }, 3500);

    document.querySelectorAll('.application-toggle-btn').forEach(function (button) {
        const targetSelector = button.getAttribute('data-bs-target');
        if (!targetSelector) return;
        const collapseTarget = document.querySelector(targetSelector);
        if (!collapseTarget) return;

        const expandText = button.getAttribute('data-expand-text') || 'Show Details';
        const collapseText = button.getAttribute('data-collapse-text') || 'Hide Details';
        button.textContent = collapseTarget.classList.contains('show') ? collapseText : expandText;

        collapseTarget.addEventListener('show.bs.collapse', function () {
            button.textContent = collapseText;
            button.setAttribute('aria-expanded', 'true');
        });

        collapseTarget.addEventListener('hide.bs.collapse', function () {
            button.textContent = expandText;
            button.setAttribute('aria-expanded', 'false');
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
