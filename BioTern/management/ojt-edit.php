<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
$ops_helpers = dirname(__DIR__) . '/lib/ops_helpers.php';
if (file_exists($ops_helpers)) {
    require_once $ops_helpers;
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

function ojt_edit_table_exists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function get_columns(mysqli $conn, string $table): array {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM {$table}");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    return $cols;
}

if (empty($_SESSION['ojt_edit_csrf'])) {
    $_SESSION['ojt_edit_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['ojt_edit_csrf'];
$current_role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$can_edit_controls = in_array($current_role, ['admin', 'coordinator'], true);

$student_id = isset($_GET['id']) ? intval($_GET['id']) : intval($_POST['student_id'] ?? 0);
$message = '';
$message_type = 'success';
$change_log = [];

$student = null;
$internship = null;
$supervisor_users = [];
$coordinator_users = [];

if (ojt_edit_table_exists($conn, 'students') && $student_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (ojt_edit_table_exists($conn, 'internships') && $student_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM internships WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $internship = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (ojt_edit_table_exists($conn, 'users')) {
    $role_col_exists = in_array('role', get_columns($conn, 'users'), true);
    if ($role_col_exists) {
        $res_sup = $conn->query("SELECT id, name FROM users WHERE role = 'supervisor' AND (is_active = 1 OR is_active IS NULL) ORDER BY name");
        if ($res_sup) while ($u = $res_sup->fetch_assoc()) $supervisor_users[] = $u;
        $res_co = $conn->query("SELECT id, name FROM users WHERE role = 'coordinator' AND (is_active = 1 OR is_active IS NULL) ORDER BY name");
        if ($res_co) while ($u = $res_co->fetch_assoc()) $coordinator_users[] = $u;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ojt'])) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $message = 'Invalid form token. Please reload and try again.';
        $message_type = 'danger';
    } elseif (!$student) {
        $message = 'Student not found.';
        $message_type = 'danger';
    } elseif (!$can_edit_controls) {
        $message = 'You can review records but cannot edit control fields.';
        $message_type = 'warning';
    } else {
        $reason = trim((string)($_POST['change_reason'] ?? ''));
        if ($reason === '') {
            $reason = 'No reason provided by user.';
        }
        $internal_total = max(0, intval($_POST['internal_total_hours'] ?? 0));
        $internal_remaining = max(0, intval($_POST['internal_total_hours_remaining'] ?? 0));
        $external_total = max(0, intval($_POST['external_total_hours'] ?? 0));
        $external_remaining = max(0, intval($_POST['external_total_hours_remaining'] ?? 0));
        $i_required = max(0, intval($_POST['required_hours'] ?? 0));
        $i_start_chk = trim((string)($_POST['start_date'] ?? ''));
        $i_end_chk = trim((string)($_POST['end_date'] ?? ''));

        if ($internal_remaining > $internal_total) {
            $message = 'Internal remaining hours cannot be greater than internal total hours.';
            $message_type = 'warning';
        } elseif ($external_remaining > $external_total) {
            $message = 'External remaining hours cannot be greater than external total hours.';
            $message_type = 'warning';
        } elseif ($i_start_chk !== '' && $i_end_chk !== '' && strtotime($i_start_chk) !== false && strtotime($i_end_chk) !== false && strtotime($i_end_chk) < strtotime($i_start_chk)) {
            $message = 'Internship end date cannot be earlier than start date.';
            $message_type = 'warning';
        } else {
        $conn->begin_transaction();
        try {
                $student_cols = get_columns($conn, 'students');
                $updates = [];
                $types = '';
                $values = [];

                $editable_student_fields = [
                    'supervisor_name',
                    'coordinator_name',
                    'assignment_track',
                    'internal_total_hours',
                    'external_total_hours',
                    'internal_total_hours_remaining',
                    'external_total_hours_remaining',
                    'status'
                ];

                foreach ($editable_student_fields as $field) {
                    if (!in_array($field, $student_cols, true)) continue;
                    $new = trim((string)($_POST[$field] ?? ''));
                    if (in_array($field, ['internal_total_hours','external_total_hours','internal_total_hours_remaining','external_total_hours_remaining','status'], true)) {
                        $new_val = ($new === '') ? 0 : max(0, intval($new));
                        $old_val = intval($student[$field] ?? 0);
                        if ($new_val !== $old_val) {
                            $updates[] = "{$field} = ?";
                            $types .= 'i';
                            $values[] = $new_val;
                            $change_log[] = "students.{$field}: {$old_val} -> {$new_val}";
                        }
                    } else {
                        $old_val = (string)($student[$field] ?? '');
                        if ($new !== $old_val) {
                            $updates[] = "{$field} = ?";
                            $types .= 's';
                            $values[] = $new;
                            $change_log[] = "students.{$field}: {$old_val} -> {$new}";
                        }
                    }
                }

                if (!empty($updates)) {
                    $sql = 'UPDATE students SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?';
                    $types .= 'i';
                    $values[] = $student_id;
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$values);
                    $stmt->execute();
                    $stmt->close();
                }

                if (ojt_edit_table_exists($conn, 'internships')) {
                    $intern_cols = get_columns($conn, 'internships');
                    $i_status = trim((string)($_POST['internship_status'] ?? 'ongoing'));
                    $i_required = max(0, intval($_POST['required_hours'] ?? 0));
                    $i_start = trim((string)($_POST['start_date'] ?? ''));
                    $i_end = trim((string)($_POST['end_date'] ?? ''));
                    $selected_supervisor_id = max(0, intval($_POST['supervisor_user_id'] ?? 0));
                    $selected_coordinator_id = max(0, intval($_POST['coordinator_user_id'] ?? 0));
                    $selected_supervisor_name = '';
                    $selected_coordinator_name = '';
                    if ($selected_supervisor_id > 0) {
                        $stmt_u = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
                        $stmt_u->bind_param('i', $selected_supervisor_id);
                        $stmt_u->execute();
                        $selected_supervisor_name = (string)($stmt_u->get_result()->fetch_assoc()['name'] ?? '');
                        $stmt_u->close();
                    }
                    if ($selected_coordinator_id > 0) {
                        $stmt_u = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
                        $stmt_u->bind_param('i', $selected_coordinator_id);
                        $stmt_u->execute();
                        $selected_coordinator_name = (string)($stmt_u->get_result()->fetch_assoc()['name'] ?? '');
                        $stmt_u->close();
                    }

                    $i_start = $i_start === '' ? null : $i_start;
                    $i_end = $i_end === '' ? null : $i_end;

                    if ($internship) {
                        $i_updates = [];
                        $i_types = '';
                        $i_vals = [];

                        if (in_array('status', $intern_cols, true) && $i_status !== (string)($internship['status'] ?? '')) {
                            $i_updates[] = 'status = ?';
                            $i_types .= 's';
                            $i_vals[] = $i_status;
                            $change_log[] = 'internships.status: ' . ($internship['status'] ?? '') . ' -> ' . $i_status;
                        }
                        if (in_array('supervisor_id', $intern_cols, true) && $selected_supervisor_id > 0 && $selected_supervisor_id !== intval($internship['supervisor_id'] ?? 0)) {
                            $i_updates[] = 'supervisor_id = ?';
                            $i_types .= 'i';
                            $i_vals[] = $selected_supervisor_id;
                            $change_log[] = 'internships.supervisor_id updated';
                        }
                        if (in_array('coordinator_id', $intern_cols, true) && $selected_coordinator_id > 0 && $selected_coordinator_id !== intval($internship['coordinator_id'] ?? 0)) {
                            $i_updates[] = 'coordinator_id = ?';
                            $i_types .= 'i';
                            $i_vals[] = $selected_coordinator_id;
                            $change_log[] = 'internships.coordinator_id updated';
                        }
                        if (in_array('required_hours', $intern_cols, true) && $i_required !== intval($internship['required_hours'] ?? 0)) {
                            $i_updates[] = 'required_hours = ?';
                            $i_types .= 'i';
                            $i_vals[] = $i_required;
                            $change_log[] = 'internships.required_hours: ' . intval($internship['required_hours'] ?? 0) . ' -> ' . $i_required;
                        }
                        if (in_array('start_date', $intern_cols, true) && (string)($i_start ?? '') !== (string)($internship['start_date'] ?? '')) {
                            $i_updates[] = 'start_date = ?';
                            $i_types .= 's';
                            $i_vals[] = $i_start;
                            $change_log[] = 'internships.start_date updated';
                        }
                        if (in_array('end_date', $intern_cols, true) && (string)($i_end ?? '') !== (string)($internship['end_date'] ?? '')) {
                            $i_updates[] = 'end_date = ?';
                            $i_types .= 's';
                            $i_vals[] = $i_end;
                            $change_log[] = 'internships.end_date updated';
                        }

                        if (!empty($i_updates)) {
                            $i_sql = 'UPDATE internships SET ' . implode(', ', $i_updates) . ', updated_at = NOW() WHERE id = ?';
                            $i_types .= 'i';
                            $i_vals[] = intval($internship['id']);
                            $stmt = $conn->prepare($i_sql);
                            $stmt->bind_param($i_types, ...$i_vals);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        $insert_cols = ['student_id'];
                        $insert_vals = [$student_id];
                        $insert_types = 'i';
                        if (in_array('status', $intern_cols, true)) { $insert_cols[] = 'status'; $insert_vals[] = $i_status; $insert_types .= 's'; }
                        if (in_array('supervisor_id', $intern_cols, true) && $selected_supervisor_id > 0) { $insert_cols[] = 'supervisor_id'; $insert_vals[] = $selected_supervisor_id; $insert_types .= 'i'; }
                        if (in_array('coordinator_id', $intern_cols, true) && $selected_coordinator_id > 0) { $insert_cols[] = 'coordinator_id'; $insert_vals[] = $selected_coordinator_id; $insert_types .= 'i'; }
                        if (in_array('required_hours', $intern_cols, true)) { $insert_cols[] = 'required_hours'; $insert_vals[] = $i_required; $insert_types .= 'i'; }
                        if (in_array('start_date', $intern_cols, true)) { $insert_cols[] = 'start_date'; $insert_vals[] = $i_start; $insert_types .= 's'; }
                        if (in_array('end_date', $intern_cols, true)) { $insert_cols[] = 'end_date'; $insert_vals[] = $i_end; $insert_types .= 's'; }
                        $ph = implode(',', array_fill(0, count($insert_cols), '?'));
                        $ins = 'INSERT INTO internships (' . implode(',', $insert_cols) . ') VALUES (' . $ph . ')';
                        $stmt = $conn->prepare($ins);
                        $stmt->bind_param($insert_types, ...$insert_vals);
                        $stmt->execute();
                        $stmt->close();
                        $change_log[] = 'internships record created';
                    }

                    if ($selected_supervisor_name !== '' && in_array('supervisor_name', $student_cols, true) && $selected_supervisor_name !== (string)($student['supervisor_name'] ?? '')) {
                        $stmt_sname = $conn->prepare("UPDATE students SET supervisor_name = ?, updated_at = NOW() WHERE id = ?");
                        $stmt_sname->bind_param('si', $selected_supervisor_name, $student_id);
                        $stmt_sname->execute();
                        $stmt_sname->close();
                        $change_log[] = 'students.supervisor_name synchronized from user assignment';
                    }
                    if ($selected_coordinator_name !== '' && in_array('coordinator_name', $student_cols, true) && $selected_coordinator_name !== (string)($student['coordinator_name'] ?? '')) {
                        $stmt_cname = $conn->prepare("UPDATE students SET coordinator_name = ?, updated_at = NOW() WHERE id = ?");
                        $stmt_cname->bind_param('si', $selected_coordinator_name, $student_id);
                        $stmt_cname->execute();
                        $stmt_cname->close();
                        $change_log[] = 'students.coordinator_name synchronized from user assignment';
                    }
                }

                $conn->query("CREATE TABLE IF NOT EXISTS ojt_edit_audit (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id INT NOT NULL,
                    editor_user_id INT NOT NULL DEFAULT 0,
                    reason VARCHAR(500) NOT NULL,
                    changes_text TEXT NOT NULL,
                    diff_json LONGTEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX(student_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                $audit_cols = get_columns($conn, 'ojt_edit_audit');
                if (!in_array('diff_json', $audit_cols, true)) {
                    $conn->query("ALTER TABLE ojt_edit_audit ADD COLUMN diff_json LONGTEXT NULL AFTER changes_text");
                }

                if (!empty($change_log)) {
                    $changes_text = implode("\n", $change_log);
                    $diff_json = json_encode($change_log, JSON_UNESCAPED_UNICODE);
                    $editor = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                    $stmt = $conn->prepare('INSERT INTO ojt_edit_audit (student_id, editor_user_id, reason, changes_text, diff_json) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('iisss', $student_id, $editor, $reason, $changes_text, $diff_json);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'OJT profile updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'No changes detected.';
                    $message_type = 'warning';
                }

                $conn->commit();

                $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
                $stmt->bind_param('i', $student_id);
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (ojt_edit_table_exists($conn, 'internships')) {
                    $stmt = $conn->prepare('SELECT * FROM internships WHERE student_id = ? ORDER BY id DESC LIMIT 1');
                    $stmt->bind_param('i', $student_id);
                    $stmt->execute();
                    $internship = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
        } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Save failed: ' . $e->getMessage();
            $message_type = 'danger';
        }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_review_note'])) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $message = 'Invalid form token. Please reload and try again.';
        $message_type = 'danger';
    } elseif ($student_id <= 0) {
        $message = 'Student not found.';
        $message_type = 'danger';
    } else {
        $review_note = trim((string)($_POST['review_note'] ?? ''));
        if ($review_note === '') {
            $message = 'Review note cannot be empty.';
            $message_type = 'warning';
        } else {
            $conn->query("CREATE TABLE IF NOT EXISTS ojt_supervisor_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                reviewer_user_id INT NOT NULL DEFAULT 0,
                reviewer_role VARCHAR(50) NOT NULL DEFAULT '',
                note TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $reviewer = intval($_SESSION['user_id'] ?? 0);
            $stmt = $conn->prepare("INSERT INTO ojt_supervisor_reviews (student_id, reviewer_user_id, reviewer_role, note) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiss', $student_id, $reviewer, $current_role, $review_note);
            $stmt->execute();
            $stmt->close();
            $message = 'Review note saved.';
            $message_type = 'success';
        }
    }
}

$audit_rows = [];
if ($student_id > 0 && ojt_edit_table_exists($conn, 'ojt_edit_audit')) {
    $stmt = $conn->prepare('SELECT * FROM ojt_edit_audit WHERE student_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $audit_rows[] = $r;
    }
    $stmt->close();
}
$review_rows = [];
if ($student_id > 0 && ojt_edit_table_exists($conn, 'ojt_supervisor_reviews')) {
    $stmt = $conn->prepare('SELECT * FROM ojt_supervisor_reviews WHERE student_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $review_rows[] = $r;
    }
    $stmt->close();
}

$page_title = 'BioTern || OJT Edit';
$page_styles = [
    'assets/css/modules/management/management-ojt-shared.css',
    'assets/css/modules/management/management-ojt-edit.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];

include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="app-ojt-edit-content">
        <div class="page-header app-ojt-edit-page-header d-flex justify-content-between align-items-center">
            <h5 class="m-b-10">OJT Edit Control</h5>
            <div class="d-flex gap-2 app-ojt-edit-page-header-actions">
                <a href="ojt.php" class="btn btn-light">Back to OJT List</a>
                <?php if ($student_id > 0): ?><a href="ojt-view.php?id=<?php echo (int)$student_id; ?>" class="btn btn-primary">Open OJT View</a><?php endif; ?>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!$student): ?>
            <div class="card app-ojt-edit-surface-card card-body"><div class="alert alert-warning mb-0">Select a valid student from OJT List first.</div></div>
        <?php else: ?>
            <div class="card app-ojt-edit-surface-card card-body mb-3 app-ojt-student-context-card">
                <h6 class="fw-bold mb-3">Student Context</h6>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label app-ojt-edit-form-label">Name</label><input class="form-control" value="<?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label app-ojt-edit-form-label">Student ID</label><input class="form-control" value="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label app-ojt-edit-form-label">Current Track</label><input class="form-control" value="<?php echo htmlspecialchars($student['assignment_track'] ?? 'internal'); ?>" readonly></div>
                </div>
            </div>

            <form method="post" class="card app-ojt-edit-surface-card card-body mb-3 app-ojt-controls-form" data-confirm-message="Apply these OJT changes? This action is logged.">
                <input type="hidden" name="save_ojt" value="1">
                <input type="hidden" name="student_id" value="<?php echo (int)$student_id; ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

                <h6 class="fw-bold mb-1">Operational Controls</h6>
                <div class="section-subtitle app-ojt-edit-section-subtitle mb-3">Use this panel for audited internship data maintenance.</div>
                <?php if (!$can_edit_controls): ?>
                    <div class="alert alert-info">Your role is <strong><?php echo htmlspecialchars($current_role ?: 'unknown'); ?></strong>. You can add review notes, but control fields are read-only.</div>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Student Status</label>
                        <select name="status" class="form-select" <?php echo $can_edit_controls ? '' : 'disabled'; ?>>
                            <option value="1" <?php echo intval($student['status'] ?? 0) === 1 ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo intval($student['status'] ?? 0) === 0 ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Assignment Track</label>
                        <select name="assignment_track" class="form-select" <?php echo $can_edit_controls ? '' : 'disabled'; ?>>
                            <option value="internal" <?php echo (($student['assignment_track'] ?? 'internal') === 'internal') ? 'selected' : ''; ?>>Internal</option>
                            <option value="external" <?php echo (($student['assignment_track'] ?? '') === 'external') ? 'selected' : ''; ?>>External</option>
                        </select>
                    </div>
                    <?php $current_sup_id = intval($internship['supervisor_id'] ?? 0); ?>
                    <?php $current_co_id = intval($internship['coordinator_id'] ?? 0); ?>
                    <div class="col-md-3">
                        <label class="form-label">Supervisor (User)</label>
                        <select name="supervisor_user_id" class="form-select" <?php echo $can_edit_controls ? '' : 'disabled'; ?>>
                            <option value="0">Unassigned</option>
                            <?php foreach ($supervisor_users as $su): ?>
                                <option value="<?php echo intval($su['id']); ?>" <?php echo $current_sup_id === intval($su['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($su['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Coordinator (User)</label>
                        <select name="coordinator_user_id" class="form-select" <?php echo $can_edit_controls ? '' : 'disabled'; ?>>
                            <option value="0">Unassigned</option>
                            <?php foreach ($coordinator_users as $cu): ?>
                                <option value="<?php echo intval($cu['id']); ?>" <?php echo $current_co_id === intval($cu['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cu['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Supervisor Display Name</label><input type="text" name="supervisor_name" class="form-control" value="<?php echo htmlspecialchars($student['supervisor_name'] ?? ''); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>
                    <div class="col-md-3"><label class="form-label">Coordinator Display Name</label><input type="text" name="coordinator_name" class="form-control" value="<?php echo htmlspecialchars($student['coordinator_name'] ?? ''); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>

                    <div class="col-md-3"><label class="form-label">Internal Total Hours</label><input type="number" min="0" name="internal_total_hours" class="form-control" value="<?php echo htmlspecialchars((string)($student['internal_total_hours'] ?? 0)); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>
                    <div class="col-md-3"><label class="form-label">Internal Remaining</label><input type="number" min="0" name="internal_total_hours_remaining" class="form-control" value="<?php echo htmlspecialchars((string)($student['internal_total_hours_remaining'] ?? 0)); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>
                    <div class="col-md-3"><label class="form-label">External Total Hours</label><input type="number" min="0" name="external_total_hours" class="form-control" value="<?php echo htmlspecialchars((string)($student['external_total_hours'] ?? 0)); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>
                    <div class="col-md-3"><label class="form-label">External Remaining</label><input type="number" min="0" name="external_total_hours_remaining" class="form-control" value="<?php echo htmlspecialchars((string)($student['external_total_hours_remaining'] ?? 0)); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>

                    <div class="col-md-3">
                        <label class="form-label">Internship Status</label>
                        <select name="internship_status" class="form-select" <?php echo $can_edit_controls ? '' : 'disabled'; ?>>
                            <?php $ist = strtolower((string)($internship['status'] ?? 'ongoing')); ?>
                            <option value="ongoing" <?php echo $ist === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                            <option value="completed" <?php echo $ist === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="dropped" <?php echo $ist === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Required Hours</label><input type="number" min="0" name="required_hours" class="form-control" value="<?php echo htmlspecialchars((string)($internship['required_hours'] ?? 0)); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>
                    <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars((string)($internship['start_date'] ?? '')); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>
                    <div class="col-md-3"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars((string)($internship['end_date'] ?? '')); ?>" <?php echo $can_edit_controls ? '' : 'readonly'; ?>></div>

                    <div class="col-12">
                        <label class="form-label">Change Justification (Optional)</label>
                        <textarea name="change_reason" class="form-control" rows="2" placeholder="Optional: explain why this OJT update is needed for audit/compliance."></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2 edit-actions app-ojt-edit-actions">
                    <button type="submit" class="btn btn-primary" <?php echo $can_edit_controls ? '' : 'disabled'; ?>>Save Controlled Changes</button>
                    <a href="#previous-changes" class="btn btn-outline-info">Previous Changes</a>
                    <a href="ojt-view.php?id=<?php echo (int)$student_id; ?>" class="btn btn-success">Return to OJT View</a>
                </div>
            </form>

            <form method="post" class="card app-ojt-edit-surface-card card-body mb-3">
                <input type="hidden" name="save_review_note" value="1">
                <input type="hidden" name="student_id" value="<?php echo (int)$student_id; ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <h6 class="fw-bold mb-3">Supervisor/Reviewer Notes</h6>
                <div class="row g-2">
                    <div class="col-12">
                        <textarea name="review_note" class="form-control" rows="2" placeholder="Add monitoring note, concern, or recommendation."></textarea>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="submit" class="btn btn-outline-primary">Save Review Note</button>
                </div>
                <?php if ($review_rows): ?>
                    <div class="mt-3">
                        <?php foreach ($review_rows as $rv): ?>
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars((string)($rv['reviewer_role'] ?? 'reviewer')); ?></strong>
                                    <span class="text-muted"><?php echo htmlspecialchars((string)($rv['created_at'] ?? '')); ?></span>
                                </div>
                                <div><?php echo htmlspecialchars((string)($rv['note'] ?? '')); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>

            <div class="card app-ojt-edit-surface-card card-body" id="previous-changes">
                <h6 class="fw-bold mb-3">Recent OJT Edit Audit</h6>
                <?php if (!$audit_rows): ?>
                    <div class="text-muted">No audit entries yet.</div>
                <?php else: ?>
                    <?php foreach ($audit_rows as $audit): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <strong>Reason:</strong>
                                <span class="text-muted"><?php echo htmlspecialchars((string)$audit['created_at']); ?></span>
                            </div>
                            <div><?php echo htmlspecialchars((string)$audit['reason']); ?></div>
                            <?php
                            $lines = preg_split('/\r\n|\r|\n/', (string)($audit['changes_text'] ?? ''));
                            ?>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Before</th>
                                            <th>After</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lines as $ln): ?>
                                            <?php
                                            $ln = trim($ln);
                                            if ($ln === '') continue;
                                            $field = $ln;
                                            $before = '-';
                                            $after = '-';
                                            if (strpos($ln, ':') !== false && strpos($ln, '->') !== false) {
                                                [$left, $right] = explode(':', $ln, 2);
                                                $field = trim($left);
                                                [$b, $a] = explode('->', $right, 2);
                                                $before = trim((string)$b);
                                                $after = trim((string)$a);
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($field); ?></td>
                                                <td><?php echo htmlspecialchars($before); ?></td>
                                                <td><?php echo htmlspecialchars($after); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>
<?php $conn->close(); ?>







