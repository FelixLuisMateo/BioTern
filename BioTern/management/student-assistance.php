<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($role !== 'admin') {
    header('Location: homepage.php');
    exit;
}

function sa_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sa_redirect(): void
{
    header('Location: student-assistance.php');
    exit;
}

function sa_ensure_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS student_assistance_programs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id INT NOT NULL,
        program_code VARCHAR(30) NOT NULL DEFAULT 'SA',
        weekly_required_hours DECIMAL(5,2) NOT NULL DEFAULT 21.00,
        school_year VARCHAR(50) NOT NULL DEFAULT '',
        semester VARCHAR(50) NOT NULL DEFAULT '',
        start_date DATE NULL,
        end_date DATE NULL,
        can_continue_next_semester TINYINT(1) NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_sa_student_status (student_id, status),
        KEY idx_sa_period (school_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

sa_ensure_schema($conn);

$flash = ['type' => '', 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['sa_action'] ?? '')));
    $id = (int)($_POST['id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    $weeklyHours = max(0, (float)($_POST['weekly_required_hours'] ?? 21));
    $schoolYear = trim((string)($_POST['school_year'] ?? ''));
    $semester = trim((string)($_POST['semester'] ?? ''));
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = trim((string)($_POST['end_date'] ?? ''));
    $status = strtolower(trim((string)($_POST['status'] ?? 'active')));
    $status = in_array($status, ['active', 'paused', 'completed'], true) ? $status : 'active';
    $canContinue = isset($_POST['can_continue_next_semester']) ? 1 : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("UPDATE student_assistance_programs SET deleted_at = NOW(), status = 'completed' WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        sa_redirect();
    }

    if ($studentId <= 0 || $weeklyHours <= 0) {
        $flash = ['type' => 'danger', 'message' => 'Select a student and enter weekly required hours.'];
    } elseif ($action === 'save') {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE student_assistance_programs
                SET student_id = ?, weekly_required_hours = ?, school_year = ?, semester = ?, start_date = NULLIF(?, ''), end_date = NULLIF(?, ''), can_continue_next_semester = ?, status = ?, notes = ?
                WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('idssssissi', $studentId, $weeklyHours, $schoolYear, $semester, $startDate, $endDate, $canContinue, $status, $notes, $id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO student_assistance_programs
                (student_id, weekly_required_hours, school_year, semester, start_date, end_date, can_continue_next_semester, status, notes)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('idssssiss', $studentId, $weeklyHours, $schoolYear, $semester, $startDate, $endDate, $canContinue, $status, $notes);
                $stmt->execute();
                $stmt->close();
            }
        }
        sa_redirect();
    }
}

$students = [];
$studentRes = $conn->query("SELECT s.id, s.student_id, s.first_name, s.last_name, c.name AS course_name, COALESCE(NULLIF(sec.code, ''), sec.name, '-') AS section_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE s.deleted_at IS NULL
    ORDER BY s.last_name ASC, s.first_name ASC");
if ($studentRes instanceof mysqli_result) {
    while ($row = $studentRes->fetch_assoc()) {
        $students[] = $row;
    }
    $studentRes->close();
}

$assignments = [];
$res = $conn->query("SELECT sap.*, s.student_id AS student_number, s.first_name, s.last_name, s.user_id,
        COALESCE(NULLIF(s.profile_picture, ''), NULLIF(u.profile_picture, '')) AS profile_picture,
        c.name AS course_name, COALESCE(NULLIF(sec.code, ''), sec.name, '-') AS section_name,
        COALESCE(SUM(CASE WHEN a.attendance_date >= COALESCE(sap.start_date, '1900-01-01') AND (sap.end_date IS NULL OR a.attendance_date <= sap.end_date) THEN a.total_hours ELSE 0 END), 0) AS rendered_hours
    FROM student_assistance_programs sap
    INNER JOIN students s ON s.id = sap.student_id
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    LEFT JOIN attendances a ON a.student_id = s.id AND LOWER(COALESCE(a.status, 'pending')) = 'approved'
    WHERE sap.deleted_at IS NULL
    GROUP BY sap.id
    ORDER BY FIELD(sap.status, 'active', 'paused', 'completed'), s.last_name ASC");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $assignments[] = $row;
    }
    $res->close();
}

$activeCount = 0;
$pausedCount = 0;
$weeklyTotal = 0.0;
foreach ($assignments as $assignment) {
    if (($assignment['status'] ?? '') === 'active') {
        $activeCount++;
        $weeklyTotal += (float)($assignment['weekly_required_hours'] ?? 0);
    } elseif (($assignment['status'] ?? '') === 'paused') {
        $pausedCount++;
    }
}

$page_title = 'Student Assistance Program';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title"><h5 class="m-b-10">Student Assistance Program</h5></div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">SA Program</li>
                </ul>
            </div>
        </div>
        <div class="main-content">
            <?php if ($flash['message'] !== ''): ?>
                <div class="alert alert-<?php echo sa_h($flash['type']); ?>"><?php echo sa_h($flash['message']); ?></div>
            <?php endif; ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted fs-12">Active SA Students</div><div class="h4 mb-0"><?php echo (int)$activeCount; ?></div></div></div></div>
                <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted fs-12">Paused</div><div class="h4 mb-0"><?php echo (int)$pausedCount; ?></div></div></div></div>
                <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted fs-12">Required Weekly Hours</div><div class="h4 mb-0"><?php echo sa_h(number_format($weeklyTotal, 2)); ?>h</div></div></div></div>
            </div>
            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="card-title mb-0">Create or Edit SA</h5></div>
                        <div class="card-body">
                            <form method="post" class="row g-3" id="saForm">
                                <input type="hidden" name="sa_action" value="save">
                                <input type="hidden" name="id" id="sa_id" value="0">
                                <div class="col-12">
                                    <label class="form-label">Student</label>
                                    <select name="student_id" id="sa_student_id" class="form-select" required>
                                        <option value="0">Select student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo (int)$student['id']; ?>"><?php echo sa_h(trim(($student['last_name'] ?? '') . ', ' . ($student['first_name'] ?? '')) . ' - ' . ($student['student_id'] ?? '') . ' - ' . biotern_format_section_code((string)($student['section_name'] ?? ''))); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Weekly Hours</label>
                                    <input type="number" name="weekly_required_hours" id="sa_weekly_required_hours" class="form-control" min="1" step="0.5" value="21" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="sa_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="col-sm-6"><label class="form-label">School Year</label><input type="text" name="school_year" id="sa_school_year" class="form-control" placeholder="2026-2027"></div>
                                <div class="col-sm-6"><label class="form-label">Semester</label><input type="text" name="semester" id="sa_semester" class="form-control" placeholder="1st Semester"></div>
                                <div class="col-sm-6"><label class="form-label">Start Date</label><input type="date" name="start_date" id="sa_start_date" class="form-control"></div>
                                <div class="col-sm-6"><label class="form-label">End Date</label><input type="date" name="end_date" id="sa_end_date" class="form-control"></div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="can_continue_next_semester" id="sa_can_continue_next_semester" checked>
                                        <label class="form-check-label" for="sa_can_continue_next_semester">Can continue next semester</label>
                                    </div>
                                </div>
                                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="sa_notes" class="form-control" rows="3"></textarea></div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Save SA</button>
                                    <button type="button" class="btn btn-outline-secondary" id="saResetButton">New</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="card-title mb-0">SA Students</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead><tr><th>Student</th><th>Period</th><th>Weekly</th><th>Rendered</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                    <?php if ($assignments === []): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-5">No SA assignments yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <?php
                                        $payload = [
                                            'id' => (int)$assignment['id'],
                                            'student_id' => (int)$assignment['student_id'],
                                            'weekly_required_hours' => (string)$assignment['weekly_required_hours'],
                                            'school_year' => (string)$assignment['school_year'],
                                            'semester' => (string)$assignment['semester'],
                                            'start_date' => (string)$assignment['start_date'],
                                            'end_date' => (string)$assignment['end_date'],
                                            'can_continue_next_semester' => (int)$assignment['can_continue_next_semester'],
                                            'status' => (string)$assignment['status'],
                                            'notes' => (string)$assignment['notes'],
                                        ];
                                        $statusClass = ($assignment['status'] ?? '') === 'active' ? 'success' : (($assignment['status'] ?? '') === 'paused' ? 'warning' : 'secondary');
                                        $pp = biotern_avatar_public_src((string)($assignment['profile_picture'] ?? ''), (int)($assignment['user_id'] ?? 0));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar-image avatar-md"><?php if ($pp !== ''): ?><img src="<?php echo sa_h($pp); ?>" alt="" class="img-fluid"><?php else: ?><span class="avatar-text avatar-md bg-soft-primary text-primary">SA</span><?php endif; ?></div>
                                                    <div><div class="fw-bold"><?php echo sa_h(trim(($assignment['first_name'] ?? '') . ' ' . ($assignment['last_name'] ?? ''))); ?> <span class="badge bg-soft-primary text-primary">SA</span></div><small class="text-muted"><?php echo sa_h(($assignment['student_number'] ?? '-') . ' | ' . ($assignment['course_name'] ?? '-') . ' | ' . biotern_format_section_code((string)($assignment['section_name'] ?? '-'))); ?></small></div>
                                                </div>
                                            </td>
                                            <td><?php echo sa_h(trim(($assignment['school_year'] ?: '-') . ' / ' . ($assignment['semester'] ?: '-'))); ?><div class="fs-12 text-muted"><?php echo sa_h(($assignment['start_date'] ?: 'No start') . ' to ' . ($assignment['end_date'] ?: 'continuing')); ?></div></td>
                                            <td><span class="badge bg-soft-info text-info"><?php echo sa_h(number_format((float)$assignment['weekly_required_hours'], 2)); ?>h/week</span></td>
                                            <td><?php echo sa_h(number_format((float)$assignment['rendered_hours'], 2)); ?>h</td>
                                            <td><span class="badge bg-soft-<?php echo sa_h($statusClass); ?> text-<?php echo sa_h($statusClass); ?>"><?php echo sa_h(ucfirst((string)$assignment['status'])); ?></span></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-sa-edit='<?php echo sa_h(json_encode($payload, JSON_UNESCAPED_SLASHES)); ?>'>Edit</button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this SA assignment?');">
                                                    <input type="hidden" name="sa_action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$assignment['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var form = document.getElementById('saForm');
    var reset = document.getElementById('saResetButton');
    function setValue(id, value) {
        var input = document.getElementById(id);
        if (input) input.value = value || '';
    }
    document.querySelectorAll('[data-sa-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var data = JSON.parse(button.getAttribute('data-sa-edit') || '{}');
            setValue('sa_id', data.id);
            setValue('sa_student_id', data.student_id);
            setValue('sa_weekly_required_hours', data.weekly_required_hours);
            setValue('sa_school_year', data.school_year);
            setValue('sa_semester', data.semester);
            setValue('sa_start_date', data.start_date);
            setValue('sa_end_date', data.end_date);
            setValue('sa_status', data.status);
            setValue('sa_notes', data.notes);
            var cont = document.getElementById('sa_can_continue_next_semester');
            if (cont) cont.checked = parseInt(data.can_continue_next_semester || 0, 10) === 1;
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    if (reset && form) {
        reset.addEventListener('click', function () {
            form.reset();
            setValue('sa_id', '0');
            setValue('sa_weekly_required_hours', '21');
        });
    }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
