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
$hasSectionCreatedAt = has_col($sectionCols, 'created_at');
$hasSectionUpdatedAt = has_col($sectionCols, 'updated_at');

$hasCourseDeletedAt = has_col($courseCols, 'deleted_at');
$hasDeptDeletedAt = has_col($deptCols, 'deleted_at');

$courses = [];
$courseCodeById = [];
$courseSql = "SELECT id, code, name FROM courses" . ($hasCourseDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
$courseRes = $conn->query($courseSql);
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
        $courseId = isset($row['id']) ? (int)$row['id'] : 0;
        if ($courseId > 0) {
            $courseCodeById[$courseId] = strtoupper(trim((string)($row['code'] ?? '')));
        }
    }
}

$existingSectionCodeIndex = [];
$existingSectionSql = "SELECT id, code FROM sections" . ($hasSectionDeletedAt ? " WHERE deleted_at IS NULL" : "");
$existingSectionRes = $conn->query($existingSectionSql);
if ($existingSectionRes) {
    while ($row = $existingSectionRes->fetch_assoc()) {
        $normalizedCode = biotern_normalize_section_code((string)($row['code'] ?? ''));
        if ($normalizedCode !== '') {
            $existingSectionCodeIndex[$normalizedCode] = (int)($row['id'] ?? 0);
        }
    }
}

$departments = [];
$defaultDepartmentId = 0;
if ($hasSectionDepartment) {
    $deptSql = "SELECT id, code, name FROM departments" . ($hasDeptDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
    $deptRes = $conn->query($deptSql);
    if ($deptRes) {
        while ($row = $deptRes->fetch_assoc()) {
            $departments[] = $row;
            if ($defaultDepartmentId <= 0 && isset($row['id'])) {
                $defaultDepartmentId = (int)$row['id'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $range_start = strtoupper(trim((string)($_POST['range_start'] ?? '')));
    $range_end = strtoupper(trim((string)($_POST['range_end'] ?? '')));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $department_id = $hasSectionDepartment ? (int)$defaultDepartmentId : 0;
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
    $selectedCourseCode = (string)($courseCodeById[$course_id] ?? '');
    $yearThreeCourses = ['HTM', 'HMT', 'BSOA', 'BSE'];

    if ($course_id <= 0 || $selectedCourseCode === '') {
        $message = 'Course is required.';
        $message_type = 'danger';
    } elseif ($hasSectionDepartment && $department_id <= 0) {
        $message = 'No department available for sections. Please create a department first.';
        $message_type = 'danger';
    } elseif (!preg_match('/^(\d+)([A-Z])$/', $range_start, $startParts) || !preg_match('/^(\d+)([A-Z])$/', $range_end, $endParts)) {
        $message = 'Section range must use format like 2A to 2Z.';
        $message_type = 'danger';
    } else {
        $startNumber = (int)$startParts[1];
        $endNumber = (int)$endParts[1];
        $startLetter = ord($startParts[2]);
        $endLetter = ord($endParts[2]);

        if ($startNumber !== $endNumber) {
            $message = 'Start and end ranges must have the same year number (example: 2A to 2Z).';
            $message_type = 'danger';
        } elseif (in_array($selectedCourseCode, $yearThreeCourses, true) && $startNumber !== 3) {
            $message = 'For ' . $selectedCourseCode . ', use year 3 range (example: 3A to 3Z).';
            $message_type = 'danger';
        } elseif ($startLetter > $endLetter) {
            $message = 'Range start must come before range end.';
            $message_type = 'danger';
        } else {
            $codesToCreate = [];
            for ($letter = $startLetter; $letter <= $endLetter; $letter++) {
                $suffix = $startNumber . chr($letter);
                $codesToCreate[] = biotern_normalize_section_code($selectedCourseCode . '-' . $suffix);
            }

            $createdCount = 0;
            $skippedCount = 0;
            $errorText = '';

            foreach ($codesToCreate as $code) {
                if ($code === '' || isset($existingSectionCodeIndex[$code])) {
                    $skippedCount++;
                    continue;
                }

                $existingSectionCodeIndex[$code] = 1;
                $name = $code;
                $columns = ['code', 'name', 'course_id'];
                $values = ["'" . $conn->real_escape_string($code) . "'", "'" . $conn->real_escape_string($name) . "'", (string)$course_id];

                $columns[] = 'attendance_session';
                $values[] = "'" . $conn->real_escape_string($attendance_session) . "'";
                $columns[] = 'schedule_time_in';
                $values[] = $schedule_time_in !== null ? ("'" . $conn->real_escape_string($schedule_time_in) . "'") : 'NULL';
                $columns[] = 'schedule_time_out';
                $values[] = $schedule_time_out !== null ? ("'" . $conn->real_escape_string($schedule_time_out) . "'") : 'NULL';
                $columns[] = 'late_after_time';
                $values[] = $late_after_time !== null ? ("'" . $conn->real_escape_string($late_after_time) . "'") : 'NULL';
                $columns[] = 'weekly_schedule_json';
                $values[] = "'" . $conn->real_escape_string($weekly_schedule_json) . "'";

                if ($hasSectionDepartment) {
                    $columns[] = 'department_id';
                    $values[] = (string)$department_id;
                }

                if ($hasSectionStatus) {
                    $columns[] = 'status';
                    $values[] = (string)$status_flag;
                } elseif ($hasSectionIsActive) {
                    $columns[] = 'is_active';
                    $values[] = (string)$status_flag;
                }

                if ($hasSectionCreatedAt) {
                    $columns[] = 'created_at';
                    $values[] = 'NOW()';
                }
                if ($hasSectionUpdatedAt) {
                    $columns[] = 'updated_at';
                    $values[] = 'NOW()';
                }

                $insertSql = "INSERT INTO sections (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
                if ($conn->query($insertSql)) {
                    $createdCount++;
                } else {
                    $errorText = $conn->error;
                    break;
                }
            }

            if ($errorText !== '') {
                $message = 'Failed to create sections: ' . $errorText;
                $message_type = 'danger';
            } elseif ($createdCount > 0 && $skippedCount === 0) {
                header('Location: sections.php');
                exit;
            } elseif ($createdCount > 0) {
                $message = $createdCount . ' section(s) created. ' . $skippedCount . ' duplicate section(s) skipped.';
                $message_type = 'warning';
            } else {
                $message = 'No new sections were created (all duplicates).';
                $message_type = 'warning';
            }
        }
    }
}

$page_title = 'Create Section';
$page_styles = [
    'assets/css/modules/management/management-create-shared.css',
    'assets/css/modules/management/management-sections-schedule.css',
];
$page_scripts = [
    'assets/js/modules/management/management-sections-create-runtime.js',
    'assets/js/modules/management/management-sections-schedule-runtime.js',
];
include 'includes/header.php';
?>
<?php
$defaultSectionSchedule = section_schedule_from_row([
    'attendance_session' => section_schedule_normalize_session((string)($_POST['attendance_session'] ?? 'whole_day')),
    'schedule_time_in' => (string)($_POST['schedule_time_in'] ?? '08:00'),
    'schedule_time_out' => (string)($_POST['schedule_time_out'] ?? '17:00'),
    'late_after_time' => (string)($_POST['late_after_time'] ?? '08:00'),
    'weekly_schedule_json' => section_schedule_encode_weekly(
        section_schedule_normalize_weekly_input(
            $_POST['weekly_schedule'] ?? [],
            [
                'attendance_session' => (string)($_POST['attendance_session'] ?? 'whole_day'),
                'schedule_time_in' => (string)($_POST['schedule_time_in'] ?? '08:00'),
                'schedule_time_out' => (string)($_POST['schedule_time_out'] ?? '17:00'),
                'late_after_time' => (string)($_POST['late_after_time'] ?? '08:00'),
            ]
        )
    ),
]);
$defaultWeeklySchedule = $defaultSectionSchedule['weekly_schedule'] ?? [];
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Create Section</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="sections.php">Sections</a></li>
            <li class="breadcrumb-item">Create</li>
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
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Course *</label>
                        <select id="courseSelect" name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" data-course-code="<?php echo htmlspecialchars(strtoupper((string)($course['code'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars((string)($course['code'] ?: $course['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Range Start *</label>
                        <input type="text" id="rangeStartInput" name="range_start" class="form-control text-uppercase" required>
                        <small class="form-text text-muted">Example: 2A</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Range End *</label>
                        <input type="text" id="rangeEndInput" name="range_end" class="form-control text-uppercase" required>
                        <small class="form-text text-muted">Example: 2D</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Attendance Session</label>
                        <select name="attendance_session" class="form-select">
                            <option value="whole_day" <?php echo $defaultSectionSchedule['attendance_session'] === 'whole_day' ? 'selected' : ''; ?>>Whole day</option>
                            <option value="morning_only" <?php echo $defaultSectionSchedule['attendance_session'] === 'morning_only' ? 'selected' : ''; ?>>Morning only</option>
                            <option value="afternoon_only" <?php echo $defaultSectionSchedule['attendance_session'] === 'afternoon_only' ? 'selected' : ''; ?>>Afternoon only</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scheduled Time In</label>
                        <input type="time" name="schedule_time_in" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$defaultSectionSchedule['schedule_time_in']); ?>" step="60">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scheduled Time Out</label>
                        <input type="time" name="schedule_time_out" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$defaultSectionSchedule['schedule_time_out']); ?>" step="60">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Late After</label>
                        <input type="time" name="late_after_time" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$defaultSectionSchedule['late_after_time']); ?>" step="60">
                    </div>
                </div>
                <div class="weekly-schedule-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h6 class="mb-1">Monday to Saturday Schedule</h6>
                            <small class="text-muted">Set the actual class hours for each weekday so attendance follows them automatically.</small>
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
                            <?php $daySchedule = $defaultWeeklySchedule[$dayKey] ?? section_schedule_empty_day($defaultSectionSchedule); ?>
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
                <?php if ($hasSectionDepartment): ?>
                    <input type="hidden" name="department_id" value="<?php echo (int)$defaultDepartmentId; ?>">
                <?php endif; ?>
                <div class="mt-3 create-form-actions app-form-actions">
                    <button type="submit" class="btn btn-primary">Create Section</button>
                    <a href="sections.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php
include 'includes/footer.php';
$conn->close();
?>





