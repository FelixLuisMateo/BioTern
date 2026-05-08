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
$scheduleSummaryLines = section_schedule_summary_lines($sectionSchedule);
$scheduleBoardStartMinutes = 7 * 60;
$scheduleBoardEndMinutes = 21 * 60;
$scheduleBoardSlotMinutes = 30;

function section_edit_time_minutes(?string $time): ?int {
    $time = trim((string)$time);
    if (!preg_match('/^(\d{2}):(\d{2})/', $time, $matches)) {
        return null;
    }

    $hour = (int)$matches[1];
    $minute = (int)$matches[2];
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        return null;
    }

    return ($hour * 60) + $minute;
}

function section_edit_time_label(?string $time): string {
    $minutes = section_edit_time_minutes($time);
    if ($minutes === null) {
        return '--:--';
    }

    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    $suffix = $hour >= 12 ? 'PM' : 'AM';
    $displayHour = $hour % 12;
    if ($displayHour === 0) {
        $displayHour = 12;
    }

    return sprintf('%d:%02d %s', $displayHour, $minute, $suffix);
}

function section_edit_session_label(?string $session): string {
    return match (section_schedule_normalize_session($session)) {
        'morning_only' => 'Morning',
        'afternoon_only' => 'Afternoon',
        default => 'Whole day',
    };
}

function section_edit_timetable_style(array $daySchedule, int $startMinutes, int $endMinutes, int $slotMinutes): string {
    $timeIn = section_edit_time_minutes((string)($daySchedule['schedule_time_in'] ?? ''));
    $timeOut = section_edit_time_minutes((string)($daySchedule['schedule_time_out'] ?? ''));
    if ($timeIn === null || $timeOut === null || $timeOut <= $timeIn) {
        return 'display: none;';
    }

    $clampedStart = max($startMinutes, min($timeIn, $endMinutes));
    $clampedEnd = max($startMinutes, min($timeOut, $endMinutes));
    if ($clampedEnd <= $clampedStart) {
        return 'display: none;';
    }

    $rowStart = intdiv($clampedStart - $startMinutes, $slotMinutes) + 2;
    $rowSpan = max(1, (int)ceil(($clampedEnd - $clampedStart) / $slotMinutes));

    return '--schedule-row-start: ' . $rowStart . '; --schedule-row-span: ' . $rowSpan . ';';
}

$page_title = 'Edit Section';
$page_styles = array_merge($page_styles ?? [], [
    'assets/css/modules/management/management-sections-schedule.css',
]);
$page_scripts = array_merge($page_scripts ?? [], [
    'assets/js/modules/management/management-sections-schedule-runtime.js',
]);
include 'includes/header.php';
?>
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
    <div class="card stretch stretch-full section-schedule-editor">
        <div class="card-header">
            <div>
                <h5 class="card-title mb-1">Section Schedule Setup</h5>
                <div class="text-muted fs-12">Set the official class hours BioTern uses for biometric slotting, late status, and attendance display.</div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm section-print-button ms-auto" id="printSectionScheduleButton">
                <i class="feather-printer me-2"></i>
                Print Schedule
            </button>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="id" value="<?php echo (int)$section['id']; ?>">
                <div class="section-schedule-guide mb-3">
                    <div>
                        <span class="section-schedule-guide-kicker">How this schedule is used</span>
                        <h6>Attendance reads each weekday row first, then falls back to the default hours.</h6>
                        <p>Start with the normal class pattern. Only change the weekday rows that are different. Time In is the expected first punch, Late After decides the late badge, and Time Out is the end time shown in Attendance.</p>
                    </div>
                    <div class="section-schedule-summary" id="sectionScheduleSummary">
                        <?php foreach (array_slice($scheduleSummaryLines, 0, 3) as $summaryLine): ?>
                            <span><?php echo htmlspecialchars($summaryLine, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="section-form-layout mb-3">
                <div class="section-form-block">
                    <div class="section-form-block-title">
                        <h6>Section Identity</h6>
                        <span>Shown on students, reports, and attendance filters.</span>
                    </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Section Code *</label>
                        <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string)$section['code']); ?>" required>
                    </div>
                    <div class="col-md-6">
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
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo $activeValue === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $activeValue === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                </div>
                <div class="section-form-block section-default-hours">
                    <div class="section-form-block-title">
                        <h6>Default Time Template</h6>
                        <span>Used only when a weekday has no custom time.</span>
                    </div>
                    <div class="default-hours-preview" id="defaultHoursPreview">
                        <span>Default window</span>
                        <strong>--:-- to --:--</strong>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Attendance Session</label>
                            <select name="attendance_session" class="form-select">
                                <option value="whole_day" <?php echo $sectionSchedule['attendance_session'] === 'whole_day' ? 'selected' : ''; ?>>Whole day</option>
                                <option value="morning_only" <?php echo $sectionSchedule['attendance_session'] === 'morning_only' ? 'selected' : ''; ?>>Morning only</option>
                                <option value="afternoon_only" <?php echo $sectionSchedule['attendance_session'] === 'afternoon_only' ? 'selected' : ''; ?>>Afternoon only</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Time In</label>
                            <input type="time" name="schedule_time_in" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$sectionSchedule['schedule_time_in']); ?>" step="60">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Late After</label>
                            <input type="time" name="late_after_time" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$sectionSchedule['late_after_time']); ?>" step="60">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Time Out</label>
                            <input type="time" name="schedule_time_out" class="form-control js-section-time" value="<?php echo htmlspecialchars((string)$sectionSchedule['schedule_time_out']); ?>" step="60">
                        </div>
                    </div>
                </div>
                </div>
                <div class="weekly-schedule-card" id="sectionScheduleEditor">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h6 class="mb-1">Weekly Class Schedule</h6>
                            <small class="text-muted">The board below is a live preview of the exact weekday times saved for this section.</small>
                        </div>
                        <div class="section-schedule-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-schedule-preset="morning">Morning 8-12</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-schedule-preset="whole">Whole Day 8-5</button>
                            <button type="button" class="btn btn-primary btn-sm" id="copyDefaultScheduleButton">Copy Default To All Days</button>
                        </div>
                    </div>
                    <?php
                    $sectionBoardCode = trim((string)($section['code'] ?? ''));
                    $sectionBoardName = trim((string)($section['name'] ?? ''));
                    $sectionBoardTitle = $sectionBoardCode !== '' ? $sectionBoardCode : $sectionBoardName;
                    if ($sectionBoardTitle === '') {
                        $sectionBoardTitle = 'Section';
                    }
                    ?>
                    <div class="class-schedule-board mb-3"
                         data-schedule-board
                         data-board-start="<?php echo (int)$scheduleBoardStartMinutes; ?>"
                         data-board-end="<?php echo (int)$scheduleBoardEndMinutes; ?>"
                         data-board-slot="<?php echo (int)$scheduleBoardSlotMinutes; ?>">
                        <div class="class-schedule-board-header">
                            <span>Class Schedule</span>
                            <strong><?php echo htmlspecialchars($sectionBoardTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="class-schedule-scroll">
                            <div class="class-schedule-grid">
                                <div class="class-schedule-corner">Time</div>
                                <?php $weekdayIndex = 0; ?>
                                <?php foreach (section_schedule_weekday_order() as $dayKey): ?>
                                    <div class="class-schedule-day-head" style="grid-column: <?php echo $weekdayIndex + 2; ?>;">
                                        <?php echo htmlspecialchars(substr(section_schedule_weekday_label($dayKey), 0, 3), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <?php $weekdayIndex++; ?>
                                <?php endforeach; ?>
                                <?php for ($slotStart = $scheduleBoardStartMinutes; $slotStart < $scheduleBoardEndMinutes; $slotStart += 60): ?>
                                    <div class="class-schedule-time-label" style="grid-row: <?php echo intdiv($slotStart - $scheduleBoardStartMinutes, $scheduleBoardSlotMinutes) + 2; ?> / span 2;">
                                        <span><?php echo htmlspecialchars(section_edit_time_label(sprintf('%02d:%02d', intdiv($slotStart, 60), $slotStart % 60)), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endfor; ?>
                                <?php foreach (section_schedule_weekday_order() as $dayOffset => $dayKey): ?>
                                    <?php for ($slotStart = $scheduleBoardStartMinutes; $slotStart < $scheduleBoardEndMinutes; $slotStart += $scheduleBoardSlotMinutes): ?>
                                        <div class="class-schedule-cell" style="grid-column: <?php echo $dayOffset + 2; ?>; grid-row: <?php echo intdiv($slotStart - $scheduleBoardStartMinutes, $scheduleBoardSlotMinutes) + 2; ?>;"></div>
                                    <?php endfor; ?>
                                <?php endforeach; ?>
                                <?php foreach (section_schedule_weekday_order() as $dayOffset => $dayKey): ?>
                                    <?php
                                    $daySchedule = $weeklySchedule[$dayKey] ?? section_schedule_empty_day($sectionSchedule);
                                    $blockStyle = section_edit_timetable_style($daySchedule, $scheduleBoardStartMinutes, $scheduleBoardEndMinutes, $scheduleBoardSlotMinutes);
                                    ?>
                                    <div class="class-schedule-block"
                                         data-schedule-block="<?php echo htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8'); ?>"
                                         style="grid-column: <?php echo $dayOffset + 2; ?>; <?php echo htmlspecialchars($blockStyle, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="class-schedule-block-title"><?php echo htmlspecialchars($sectionBoardTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="class-schedule-block-session" data-schedule-block-session><?php echo htmlspecialchars(section_edit_session_label((string)($daySchedule['attendance_session'] ?? 'whole_day')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="class-schedule-block-time" data-schedule-block-time><?php echo htmlspecialchars(section_edit_time_label((string)($daySchedule['schedule_time_in'] ?? '')) . ' - ' . section_edit_time_label((string)($daySchedule['schedule_time_out'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
                            <div class="weekly-schedule-row" data-weekday-row="<?php echo htmlspecialchars($dayKey); ?>">
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
            <div class="section-print-schedule" aria-hidden="true">
                <div class="section-print-decor section-print-decor-left">✦</div>
                <div class="section-print-decor section-print-decor-right">✧</div>
                <div class="section-print-header">
                    <img src="../assets/images/ccstlogo.png" alt="" class="section-print-logo">
                    <div>
                        <div class="section-print-school">Clark College</div>
                        <div class="section-print-school-sub">of Science and Technology</div>
                    </div>
                </div>
                <div class="section-print-title">Class Schedule</div>
                <div class="section-print-term">First Semester SY 2025-2026</div>
                <div class="section-print-section"><?php echo htmlspecialchars($sectionBoardTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="section-print-table-wrap">
                    <div class="section-print-course-row">
                        <span>Course &amp; Sec :</span>
                        <strong><?php echo htmlspecialchars($sectionBoardTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="section-print-grid">
                        <div class="section-print-corner">Time</div>
                        <?php $printDayLabels = ['M', 'T', 'W', 'TH', 'F', 'S']; ?>
                        <?php foreach (section_schedule_weekday_order() as $dayOffset => $dayKey): ?>
                            <div class="section-print-day-head" style="grid-column: <?php echo $dayOffset + 2; ?>;">
                                <?php echo htmlspecialchars($printDayLabels[$dayOffset] ?? strtoupper(substr(section_schedule_weekday_label($dayKey), 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php for ($slotStart = $scheduleBoardStartMinutes; $slotStart < $scheduleBoardEndMinutes; $slotStart += 60): ?>
                            <div class="section-print-time-label" style="grid-row: <?php echo intdiv($slotStart - $scheduleBoardStartMinutes, $scheduleBoardSlotMinutes) + 2; ?> / span 2;">
                                <?php
                                $slotEnd = $slotStart + 60;
                                echo htmlspecialchars(
                                    strtolower(str_replace(' ', '', section_edit_time_label(sprintf('%02d:%02d', intdiv($slotStart, 60), $slotStart % 60))))
                                    . '-'
                                    . strtolower(str_replace(' ', '', section_edit_time_label(sprintf('%02d:%02d', intdiv($slotEnd, 60), $slotEnd % 60)))),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </div>
                        <?php endfor; ?>
                        <?php foreach (section_schedule_weekday_order() as $dayOffset => $dayKey): ?>
                            <?php for ($slotStart = $scheduleBoardStartMinutes; $slotStart < $scheduleBoardEndMinutes; $slotStart += $scheduleBoardSlotMinutes): ?>
                                <div class="section-print-cell" style="grid-column: <?php echo $dayOffset + 2; ?>; grid-row: <?php echo intdiv($slotStart - $scheduleBoardStartMinutes, $scheduleBoardSlotMinutes) + 2; ?>;"></div>
                            <?php endfor; ?>
                        <?php endforeach; ?>
                        <?php foreach (section_schedule_weekday_order() as $dayOffset => $dayKey): ?>
                            <?php
                            $daySchedule = $weeklySchedule[$dayKey] ?? section_schedule_empty_day($sectionSchedule);
                            $blockStyle = section_edit_timetable_style($daySchedule, $scheduleBoardStartMinutes, $scheduleBoardEndMinutes, $scheduleBoardSlotMinutes);
                            ?>
                            <div class="section-print-block <?php echo $dayOffset % 2 === 0 ? 'is-green' : 'is-blue'; ?>"
                                 data-print-schedule-block="<?php echo htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8'); ?>"
                                 style="grid-column: <?php echo $dayOffset + 2; ?>; <?php echo htmlspecialchars($blockStyle, ENT_QUOTES, 'UTF-8'); ?>">
                                <span><?php echo htmlspecialchars($sectionBoardTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span data-print-schedule-session><?php echo htmlspecialchars(section_edit_session_label((string)($daySchedule['attendance_session'] ?? 'whole_day')), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span data-print-schedule-time><?php echo htmlspecialchars(section_edit_time_label((string)($daySchedule['schedule_time_in'] ?? '')) . ' - ' . section_edit_time_label((string)($daySchedule['schedule_time_out'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="section-print-mode">Face To Face</div>
            </div>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php
include 'includes/footer.php';
$conn->close();
?>




