<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
/** @var mysqli $conn */

require_roles_page(['admin']);

section_schedule_ensure_columns($conn);

$message = '';
$message_type = 'info';

function get_table_columns(mysqli $conn, string $table): array {
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = strtolower((string)$row['Field']);
        }
    }
    return $columns;
}

function has_col(array $cols, string $name): bool {
    return in_array(strtolower($name), $cols, true);
}

$sectionCols = get_table_columns($conn, 'sections');
$courseCols = get_table_columns($conn, 'courses');
$deptCols = get_table_columns($conn, 'departments');

$hasSectionDeletedAt = has_col($sectionCols, 'deleted_at');
$hasSectionDepartment = has_col($sectionCols, 'department_id');
$hasSectionIsActive = has_col($sectionCols, 'is_active');
$hasSectionStatus = has_col($sectionCols, 'status');
$hasSectionUpdatedAt = has_col($sectionCols, 'updated_at');

$hasCourseDeletedAt = has_col($courseCols, 'deleted_at');
$hasDeptDeletedAt = has_col($deptCols, 'deleted_at');

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid section id.');
}

$courses = [];
$courseSql = "SELECT id, code, name FROM courses" . ($hasCourseDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
$courseRes = $conn->query($courseSql);
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
}

$departments = [];
if ($hasSectionDepartment) {
    $deptSql = "SELECT id, code, name FROM departments" . ($hasDeptDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
    $deptRes = $conn->query($deptSql);
    if ($deptRes) {
        while ($row = $deptRes->fetch_assoc()) {
            $departments[] = $row;
        }
    }
}

$selectFields = ['id', 'name', 'code', 'course_id'];
if ($hasSectionDepartment) {
    $selectFields[] = 'department_id';
}
if ($hasSectionStatus) {
    $selectFields[] = 'status';
} elseif ($hasSectionIsActive) {
    $selectFields[] = 'is_active';
}
$selectFields[] = 'attendance_session';
$selectFields[] = 'schedule_time_in';
$selectFields[] = 'schedule_time_out';
$selectFields[] = 'late_after_time';
$selectFields[] = 'weekly_schedule_json';

$whereSql = "id = ?";
if ($hasSectionDeletedAt) {
    $whereSql .= " AND deleted_at IS NULL";
}
$stmt = $conn->prepare("SELECT " . implode(', ', $selectFields) . " FROM sections WHERE " . $whereSql . " LIMIT 1");
$section = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$section) {
    die('Section not found.');
}

$existingSectionCodeIndex = [];
$existingSectionSql = "SELECT id, code FROM sections WHERE id <> ?";
if ($hasSectionDeletedAt) {
    $existingSectionSql .= " AND deleted_at IS NULL";
}
$existingSectionStmt = $conn->prepare($existingSectionSql);
if ($existingSectionStmt) {
    $existingSectionStmt->bind_param('i', $id);
    $existingSectionStmt->execute();
    $existingSectionRes = $existingSectionStmt->get_result();
    if ($existingSectionRes) {
        while ($row = $existingSectionRes->fetch_assoc()) {
            $normalizedCode = biotern_normalize_section_code((string)($row['code'] ?? ''));
            if ($normalizedCode !== '') {
                $existingSectionCodeIndex[$normalizedCode] = (int)($row['id'] ?? 0);
            }
        }
    }
    $existingSectionStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $code = biotern_normalize_section_code((string)($_POST['code'] ?? ''));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $status_text = strtolower(trim((string)($_POST['status'] ?? 'active')));
    $status_flag = ($status_text === 'inactive') ? 0 : 1;
    $attendance_session = section_schedule_normalize_session((string)($_POST['attendance_session'] ?? 'whole_day'));
    $schedule_time_in = section_schedule_normalize_time_input((string)($_POST['schedule_time_in'] ?? ''));
    $schedule_time_out = section_schedule_normalize_time_input((string)($_POST['schedule_time_out'] ?? ''));
    $late_after_time = section_schedule_normalize_time_input((string)($_POST['late_after_time'] ?? ''));
    $weekly_schedule = section_schedule_normalize_weekly_input(
        $_POST['weekly_schedule'] ?? [],
        [
            'attendance_session' => $attendance_session,
            'schedule_time_in' => $schedule_time_in ?? '',
            'schedule_time_out' => $schedule_time_out ?? '',
            'late_after_time' => $late_after_time ?? '',
        ]
    );
    $weekly_schedule_json = section_schedule_encode_weekly($weekly_schedule);

    if ($name === '' || $code === '' || $course_id <= 0) {
        $message = 'Section name, code, and course are required.';
        $message_type = 'danger';
    } elseif ($hasSectionDepartment && $department_id <= 0) {
        $message = 'Department is required.';
        $message_type = 'danger';
    } else {
        if ($code === '' || isset($existingSectionCodeIndex[$code])) {
            $message = 'Section code already exists.';
            $message_type = 'warning';
        } else {
            $set = [
                "name = ?",
                "code = ?",
                "course_id = ?",
                "attendance_session = ?",
                "schedule_time_in = ?",
                "schedule_time_out = ?",
                "late_after_time = ?",
                "weekly_schedule_json = ?"
            ];
            $types = "ssisssss";
            $params = [
                $name,
                $code,
                $course_id,
                $attendance_session,
                $schedule_time_in,
                $schedule_time_out,
                $late_after_time,
                $weekly_schedule_json,
            ];

            if ($hasSectionDepartment) {
                $set[] = "department_id = ?";
                $types .= "i";
                $params[] = $department_id;
            }

            if ($hasSectionStatus) {
                $set[] = "status = ?";
                $types .= "i";
                $params[] = $status_flag;
            } elseif ($hasSectionIsActive) {
                $set[] = "is_active = ?";
                $types .= "i";
                $params[] = $status_flag;
            }

            if ($hasSectionUpdatedAt) {
                $set[] = "updated_at = NOW()";
            }

            $updateSql = "UPDATE sections SET " . implode(', ', $set) . " WHERE id = ? LIMIT 1";
            $types .= "i";
            $params[] = $id;

            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                $message = 'Failed to prepare update statement.';
                $message_type = 'danger';
            } else {
                $bindArgs = [];
                $bindArgs[] = &$types;
                foreach ($params as $k => $v) {
                    $bindArgs[] = &$params[$k];
                }
                call_user_func_array([$updateStmt, 'bind_param'], $bindArgs);
                if ($updateStmt->execute()) {
                    $message = 'Section updated successfully.';
                    $message_type = 'success';
                    $section['name'] = $name;
                    $section['code'] = $code;
                    $section['course_id'] = $course_id;
                    $section['attendance_session'] = $attendance_session;
                    $section['schedule_time_in'] = $schedule_time_in;
                    $section['schedule_time_out'] = $schedule_time_out;
                    $section['late_after_time'] = $late_after_time;
                    $section['weekly_schedule_json'] = $weekly_schedule_json;
                    if ($hasSectionDepartment) {
                        $section['department_id'] = $department_id;
                    }
                    if ($hasSectionStatus) {
                        $section['status'] = $status_flag;
                    } elseif ($hasSectionIsActive) {
                        $section['is_active'] = $status_flag;
                    }
                } else {
                    $message = 'Failed to update section: ' . $updateStmt->error;
                    $message_type = 'danger';
                }
                $updateStmt->close();
            }
        }
    }
}

$activeValue = $hasSectionStatus
    ? (string)($section['status'] ?? '1')
    : (string)($section['is_active'] ?? '1');
$sectionSchedule = section_schedule_from_row($section);
$weeklySchedule = $sectionSchedule['weekly_schedule'] ?? [];

$page_title = 'Edit Section';
include 'includes/header.php';
?>
<style>
    html.app-skin-dark input.form-control[type="time"] {
        color-scheme: dark;
    }

    html.app-skin-light input.form-control[type="time"] {
        color-scheme: light;
    }

    .weekly-schedule-card {
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
    }

    .weekly-schedule-grid {
        display: grid;
        gap: 0.75rem;
    }

    .weekly-schedule-row {
        display: grid;
        grid-template-columns: 120px minmax(160px, 1fr) repeat(3, minmax(150px, 1fr));
        gap: 0.75rem;
        align-items: end;
    }

    .weekly-schedule-day {
        font-weight: 600;
        padding-bottom: 0.65rem;
    }

    .weekly-schedule-head {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.75;
    }

    @media (max-width: 991.98px) {
        .weekly-schedule-row {
            grid-template-columns: 1fr;
            padding: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 10px;
        }

        .weekly-schedule-head {
            display: none;
        }

        .weekly-schedule-day {
            padding-bottom: 0;
        }
    }
</style>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Edit Section</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="sections.php">Sections</a></li>
            <li class="breadcrumb-item">Edit</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="sections.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title mb-0">Section Form</h5>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="id" value="<?php echo (int)$section['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Section Code *</label>
                        <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string)$section['code']); ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Section Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars((string)$section['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" <?php echo ((int)$section['course_id'] === (int)$course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)($course['code'] ?: $course['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($hasSectionDepartment): ?>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['id']; ?>" <?php echo ((int)($section['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)($dept['code'] ?: $dept['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo $activeValue === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $activeValue === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Attendance Session</label>
                        <select name="attendance_session" class="form-select">
                            <option value="whole_day" <?php echo $sectionSchedule['attendance_session'] === 'whole_day' ? 'selected' : ''; ?>>Whole day</option>
                            <option value="morning_only" <?php echo $sectionSchedule['attendance_session'] === 'morning_only' ? 'selected' : ''; ?>>Morning only</option>
                            <option value="afternoon_only" <?php echo $sectionSchedule['attendance_session'] === 'afternoon_only' ? 'selected' : ''; ?>>Afternoon only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Scheduled Time In</label>
                        <input type="time" name="schedule_time_in" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$sectionSchedule['schedule_time_in']); ?>" step="60">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Scheduled Time Out</label>
                        <input type="time" name="schedule_time_out" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$sectionSchedule['schedule_time_out']); ?>" step="60">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Late After</label>
                        <input type="time" name="late_after_time" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$sectionSchedule['late_after_time']); ?>" step="60">
                    </div>
                </div>
                <div class="weekly-schedule-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h6 class="mb-1">Monday to Saturday Schedule</h6>
                            <small class="text-muted">Attendance status and biometric time slotting will follow the saved weekday schedule.</small>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyDefaultScheduleButton">Copy Default To All Days</button>
                    </div>
                    <div class="weekly-schedule-grid">
                        <div class="weekly-schedule-row weekly-schedule-head">
                            <div>Day</div>
                            <div>Session</div>
                            <div>Time In</div>
                            <div>Late After</div>
                            <div>Time Out</div>
                        </div>
                        <?php foreach (section_schedule_weekday_order() as $dayKey): ?>
                            <?php $daySchedule = $weeklySchedule[$dayKey] ?? section_schedule_empty_day($sectionSchedule); ?>
                            <div class="weekly-schedule-row">
                                <div class="weekly-schedule-day"><?php echo htmlspecialchars(section_schedule_weekday_label($dayKey)); ?></div>
                                <div>
                                    <label class="form-label d-lg-none">Session</label>
                                    <select name="weekly_schedule[<?php echo htmlspecialchars($dayKey); ?>][attendance_session]" class="form-select js-day-session">
                                        <option value="whole_day" <?php echo $daySchedule['attendance_session'] === 'whole_day' ? 'selected' : ''; ?>>Whole day</option>
                                        <option value="morning_only" <?php echo $daySchedule['attendance_session'] === 'morning_only' ? 'selected' : ''; ?>>Morning only</option>
                                        <option value="afternoon_only" <?php echo $daySchedule['attendance_session'] === 'afternoon_only' ? 'selected' : ''; ?>>Afternoon only</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label d-lg-none">Time In</label>
                                    <input type="time" name="weekly_schedule[<?php echo htmlspecialchars($dayKey); ?>][schedule_time_in]" class="form-control js-section-time js-weekly-time-in" value="<?php echo htmlspecialchars((string)$daySchedule['schedule_time_in']); ?>" step="60">
                                </div>
                                <div>
                                    <label class="form-label d-lg-none">Late After</label>
                                    <input type="time" name="weekly_schedule[<?php echo htmlspecialchars($dayKey); ?>][late_after_time]" class="form-control js-section-time js-weekly-late" value="<?php echo htmlspecialchars((string)$daySchedule['late_after_time']); ?>" step="60">
                                </div>
                                <div>
                                    <label class="form-label d-lg-none">Time Out</label>
                                    <input type="time" name="weekly_schedule[<?php echo htmlspecialchars($dayKey); ?>][schedule_time_out]" class="form-control js-section-time js-weekly-time-out" value="<?php echo htmlspecialchars((string)$daySchedule['schedule_time_out']); ?>" step="60">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Section</button>
                    <a href="sections.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<script>
    (function () {
        const copyDefaultsButton = document.getElementById('copyDefaultScheduleButton');
        if (copyDefaultsButton) {
            copyDefaultsButton.addEventListener('click', function () {
                const defaultSession = document.querySelector('select[name="attendance_session"]');
                const defaultTimeIn = document.querySelector('input[name="schedule_time_in"]');
                const defaultLateAfter = document.querySelector('input[name="late_after_time"]');
                const defaultTimeOut = document.querySelector('input[name="schedule_time_out"]');

                document.querySelectorAll('.weekly-schedule-row').forEach(function (row) {
                    const session = row.querySelector('.js-day-session');
                    const timeIn = row.querySelector('.js-weekly-time-in');
                    const lateAfter = row.querySelector('.js-weekly-late');
                    const timeOut = row.querySelector('.js-weekly-time-out');
                    if (session && defaultSession) {
                        session.value = defaultSession.value;
                    }
                    if (timeIn && defaultTimeIn) {
                        timeIn.value = defaultTimeIn.value;
                    }
                    if (lateAfter && defaultLateAfter) {
                        lateAfter.value = defaultLateAfter.value;
                    }
                    if (timeOut && defaultTimeOut) {
                        timeOut.value = defaultTimeOut.value;
                    }
                });
            });
        }
    })();
</script>
<?php
include 'includes/footer.php';
$conn->close();
?>




