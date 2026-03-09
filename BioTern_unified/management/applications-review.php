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
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'");

$flashType = '';
$flashMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
    $notes = trim((string)($_POST['approval_notes'] ?? ''));
    $internalHoursRaw = isset($_POST['internal_total_hours']) ? trim((string)$_POST['internal_total_hours']) : '140';
    $externalHoursRaw = isset($_POST['external_total_hours']) ? trim((string)$_POST['external_total_hours']) : '250';

    $internalHours = is_numeric($internalHoursRaw) ? (int)$internalHoursRaw : -1;
    $externalHours = is_numeric($externalHoursRaw) ? (int)$externalHoursRaw : -1;

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
                $stmt = $conn->prepare("UPDATE users SET application_status = 'approved', approved_by = ?, approved_at = NOW(), rejected_at = NULL, approval_notes = ? WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception('Unable to update application status.');
                }
                $stmt->bind_param('isi', $currentUserId, $notes, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception('Unable to approve application.');
                }
                $stmt->close();

                $studentStmt = $conn->prepare("UPDATE students SET internal_total_hours = ?, external_total_hours = ?, internal_total_hours_remaining = CASE WHEN assignment_track = 'external' THEN 0 ELSE ? END, external_total_hours_remaining = CASE WHEN assignment_track = 'external' THEN ? ELSE 0 END WHERE user_id = ? LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception('Unable to update student hour settings.');
                }
                $studentStmt->bind_param('iiiii', $internalHours, $externalHours, $internalHours, $externalHours, $userId);
                if (!$studentStmt->execute()) {
                    $studentStmt->close();
                    throw new Exception('Unable to save updated internal/external hours.');
                }
                $studentStmt->close();

                $conn->commit();
                $flashType = 'success';
                $flashMessage = 'Application approved and student hours updated.';
            } catch (Throwable $e) {
                $conn->rollback();
                $flashType = 'danger';
                $flashMessage = 'Unable to process approval: ' . $e->getMessage();
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET application_status = 'rejected', approved_by = ?, approved_at = NULL, rejected_at = NOW(), approval_notes = ? WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('isi', $currentUserId, $notes, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    $flashType = 'warning';
                    $flashMessage = 'Application rejected.';
                }
            }
        }

        if ($flashMessage === '') {
            $flashType = 'danger';
            $flashMessage = 'Unable to process this application.';
        }
    }
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

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
        s.student_id,
        s.first_name,
        s.last_name,
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

$sql .= " ORDER BY COALESCE(u.application_submitted_at, u.created_at) DESC, u.id DESC";
$applications = $conn->query($sql);

$page_title = 'BioTern || Student Applications';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Student Applications</h5>
                </div>
            </div>
        </div>

        <div class="main-content">
            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                    <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" onchange="this.form.submit()">
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Username / Email</th>
                                    <th>Course / Assignment</th>
                                    <th>Status</th>
                                    <th>Hours (Int/Ext)</th>
                                    <th>Submitted</th>
                                    <th>Notes</th>
                                    <th style="width: 430px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($applications && $applications->num_rows > 0): ?>
                                    <?php while ($row = $applications->fetch_assoc()): ?>
                                        <?php
                                            $status = strtolower((string)($row['application_status'] ?? 'approved'));
                                            $badge = 'secondary';
                                            if ($status === 'pending') $badge = 'warning';
                                            if ($status === 'approved') $badge = 'success';
                                            if ($status === 'rejected') $badge = 'danger';

                                            $courseName = trim((string)($row['course_name'] ?? ''));
                                            $departmentName = trim((string)($row['department_name'] ?? ''));
                                            $sectionCode = trim((string)($row['section_code'] ?? ''));
                                            $sectionName = trim((string)($row['section_name'] ?? ''));
                                            $coordinatorName = trim((string)($row['coordinator_name'] ?? ''));
                                            $supervisorName = trim((string)($row['supervisor_name'] ?? ''));

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
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars(trim((string)($row['first_name'] . ' ' . $row['last_name'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted">ID: <?php echo htmlspecialchars((string)($row['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($courseLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted d-block">Department: <?php echo htmlspecialchars($departmentLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                                <small class="text-muted d-block">Section: <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                                <small class="text-muted d-block">Coordinator: <?php echo htmlspecialchars($coordinatorLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                                <small class="text-muted d-block">Supervisor: <?php echo htmlspecialchars($supervisorLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td><span class="badge bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>
                                                <span class="text-muted"><?php echo (int)($row['internal_total_hours'] ?? 140); ?> / <?php echo (int)($row['external_total_hours'] ?? 250); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($row['application_submitted_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['approval_notes'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <form method="post" class="d-flex gap-2 align-items-center flex-wrap">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>">
                                                    <input type="number" class="form-control form-control-sm" name="internal_total_hours" min="0" required value="<?php echo (int)($row['internal_total_hours'] ?? 140); ?>" style="max-width: 110px;" title="Internal Hours">
                                                    <input type="number" class="form-control form-control-sm" name="external_total_hours" min="0" required value="<?php echo (int)($row['external_total_hours'] ?? 250); ?>" style="max-width: 110px;" title="External Hours">
                                                    <input type="text" class="form-control form-control-sm" name="approval_notes" placeholder="optional note">
                                                    <button type="submit" name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                                                    <button type="submit" name="decision" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No applications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
