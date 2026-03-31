<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function student_dtr_calculate_hours(array $attendance): float
{
    $segments = [
        ['morning_time_in', 'morning_time_out'],
        ['break_time_in', 'break_time_out'],
        ['afternoon_time_in', 'afternoon_time_out'],
    ];

    $totalSeconds = 0;

    foreach ($segments as [$startKey, $endKey]) {
        $start = trim((string)($attendance[$startKey] ?? ''));
        $end = trim((string)($attendance[$endKey] ?? ''));

        if ($start === '' || $end === '') {
            continue;
        }

        $startTs = strtotime('1970-01-01 ' . $start);
        $endTs = strtotime('1970-01-01 ' . $end);
        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            continue;
        }

        $totalSeconds += ($endTs - $startTs);
    }

    return round($totalSeconds / 3600, 2);
}

function student_dtr_format_time(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '00:00:00') {
        return '--';
    }

    $ts = strtotime($raw);
    return $ts !== false ? date('g:i A', $ts) : $raw;
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$requestedMonthNumber = isset($_GET['month_num']) ? (int)$_GET['month_num'] : 0;

if ($requestedYear >= 2024 && $requestedMonthNumber >= 1 && $requestedMonthNumber <= 12) {
    $selectedMonth = sprintf('%04d-%02d', $requestedYear, $requestedMonthNumber);
} else {
    $selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}\-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }
}

$selectedYear = (int)substr($selectedMonth, 0, 4);
$selectedMonthNumber = (int)substr($selectedMonth, 5, 2);
$availableYears = [];
for ($year = max((int)date('Y') + 1, $selectedYear); $year >= 2024; $year--) {
    $availableYears[] = $year;
}
$monthOptions = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));

$user = null;
$student = null;
$internship = null;
$attendanceRows = [];
$attendanceSummary = [
    'total_logs' => 0,
    'approved_logs' => 0,
    'pending_logs' => 0,
    'rejected_logs' => 0,
    'total_hours' => 0.0,
];
$attendanceInsights = [
    'approved_hours' => 0.0,
    'pending_hours' => 0.0,
    'rejected_hours' => 0.0,
    'biometric_logs' => 0,
    'manual_logs' => 0,
    'days_present' => 0,
    'average_hours' => 0.0,
    'last_recorded_date' => '',
];

$userStmt = $conn->prepare('SELECT id, name, email, profile_picture FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: null;
    $userStmt->close();
}

$studentLookupSql = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.assignment_track,
        s.internal_total_hours, s.internal_total_hours_remaining, s.external_total_hours, s.external_total_hours_remaining,
        c.name AS course_name, sec.code AS section_code, sec.name AS section_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE s.user_id = ?
    LIMIT 1";
$studentStmt = $conn->prepare($studentLookupSql);
if ($studentStmt) {
    $studentStmt->bind_param('i', $currentUserId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
}

if (!$student && $user) {
    $fallbackEmail = trim((string)($user['email'] ?? ''));
    $fallbackName = trim((string)($user['name'] ?? ''));
    $fallbackStmt = $conn->prepare(
        "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.assignment_track,
                s.internal_total_hours, s.internal_total_hours_remaining, s.external_total_hours, s.external_total_hours_remaining,
                c.name AS course_name, sec.code AS section_code, sec.name AS section_name
         FROM students s
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN sections sec ON sec.id = s.section_id
         WHERE ((? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
             OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)))
         ORDER BY s.id DESC
         LIMIT 1"
    );
    if ($fallbackStmt) {
        $fallbackStmt->bind_param('ssss', $fallbackEmail, $fallbackEmail, $fallbackName, $fallbackName);
        $fallbackStmt->execute();
        $student = $fallbackStmt->get_result()->fetch_assoc() ?: null;
        $fallbackStmt->close();
    }
}

if ($student) {
    $studentId = (int)($student['id'] ?? 0);

    $internshipStmt = $conn->prepare(
        "SELECT company_name, position, status, start_date, end_date, required_hours, rendered_hours, completion_percentage
         FROM internships
         WHERE student_id = ? AND deleted_at IS NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT 1"
    );
    if ($internshipStmt) {
        $internshipStmt->bind_param('i', $studentId);
        $internshipStmt->execute();
        $internship = $internshipStmt->get_result()->fetch_assoc() ?: null;
        $internshipStmt->close();
    }

    $attendanceStmt = $conn->prepare(
        "SELECT id, attendance_date, morning_time_in, morning_time_out, break_time_in, break_time_out,
                afternoon_time_in, afternoon_time_out, total_hours, status, remarks, source
         FROM attendances
         WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
         ORDER BY attendance_date DESC, id DESC"
    );
    if ($attendanceStmt) {
        $attendanceStmt->bind_param('iss', $studentId, $monthStart, $monthEnd);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result();
        while ($attendanceResult && ($row = $attendanceResult->fetch_assoc())) {
            $computedHours = isset($row['total_hours']) && $row['total_hours'] !== null && $row['total_hours'] !== ''
                ? (float)$row['total_hours']
                : student_dtr_calculate_hours($row);

            $row['display_hours'] = $computedHours;
            $attendanceRows[] = $row;

            $attendanceSummary['total_logs']++;
            $attendanceSummary['total_hours'] += $computedHours;
            $attendanceInsights['days_present']++;

            if ($attendanceInsights['last_recorded_date'] === '') {
                $attendanceInsights['last_recorded_date'] = (string)($row['attendance_date'] ?? '');
            }

            $statusKey = strtolower(trim((string)($row['status'] ?? 'pending')));
            if ($statusKey === 'approved') {
                $attendanceSummary['approved_logs']++;
                $attendanceInsights['approved_hours'] += $computedHours;
            } elseif ($statusKey === 'rejected') {
                $attendanceSummary['rejected_logs']++;
                $attendanceInsights['rejected_hours'] += $computedHours;
            } else {
                $attendanceSummary['pending_logs']++;
                $attendanceInsights['pending_hours'] += $computedHours;
            }

            $sourceKey = strtolower(trim((string)($row['source'] ?? 'manual')));
            if ($sourceKey === 'biometric') {
                $attendanceInsights['biometric_logs']++;
            } else {
                $attendanceInsights['manual_logs']++;
            }
        }
        $attendanceStmt->close();
    }
}

if ($attendanceSummary['total_logs'] > 0) {
    $attendanceInsights['average_hours'] = round($attendanceSummary['total_hours'] / $attendanceSummary['total_logs'], 2);
}

$displayName = trim((string)($user['name'] ?? ''));
if ($displayName === '' && $student) {
    $displayName = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
}
if ($displayName === '') {
    $displayName = 'Student User';
}

$courseSection = array_filter([
    trim((string)($student['course_name'] ?? '')),
    trim((string)($student['section_code'] ?? '')),
    trim((string)($student['section_name'] ?? '')),
]);
$avatarSrc = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $currentUserId);
$requiredHours = (float)($internship['required_hours'] ?? 0);
$renderedHours = (float)($internship['rendered_hours'] ?? 0);
$completionPercentage = (float)($internship['completion_percentage'] ?? 0);
$track = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
$remainingHours = $track === 'external'
    ? (int)($student['external_total_hours_remaining'] ?? 0)
    : (int)($student['internal_total_hours_remaining'] ?? 0);
$lastRecordedDateText = $attendanceInsights['last_recorded_date'] !== ''
    ? date('M d, Y', strtotime($attendanceInsights['last_recorded_date']))
    : 'No entries yet';

$page_title = 'BioTern || My DTR';
$page_styles = [
    'assets/css/homepage-student.css',
    'assets/css/student-dtr.css',
];
include 'includes/header.php';
?>
<div class="main-content">
    <div class="student-home-shell student-dtr-shell">
        <section class="card student-home-hero border-0 student-dtr-hero-card">
            <div class="card-body">
                <div class="student-dtr-hero">
                    <div>
                        <span class="student-home-eyebrow">Daily Time Record</span>
                        <div class="student-dtr-persona">
                            <img src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Student Avatar" class="student-dtr-avatar">
                            <div>
                                <h2 class="student-dtr-title">My DTR</h2>
                                <div class="student-dtr-meta"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <div class="student-home-meta student-dtr-chip-row">
                            <span><i class="feather-hash me-1"></i><?php echo htmlspecialchars(trim((string)($student['student_id'] ?? '')) !== '' ? (string)$student['student_id'] : 'No student number', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($courseSection)): ?>
                            <span><i class="feather-book-open me-1"></i><?php echo htmlspecialchars(implode(' | ', $courseSection), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <span><i class="feather-calendar me-1"></i><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>

                    <form method="get" class="student-dtr-filter">
                        <div>
                            <label class="form-label" for="studentDtrMonthSelect">Month</label>
                            <select id="studentDtrMonthSelect" name="month_num" class="form-select">
                                <?php foreach ($monthOptions as $monthNumber => $monthLabelOption): ?>
                                <option value="<?php echo $monthNumber; ?>" <?php echo $monthNumber === $selectedMonthNumber ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($monthLabelOption, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="studentDtrYearSelect">Year</label>
                            <select id="studentDtrYearSelect" name="year" class="form-select">
                                <?php foreach ($availableYears as $yearOption): ?>
                                <option value="<?php echo $yearOption; ?>" <?php echo $yearOption === $selectedYear ? 'selected' : ''; ?>>
                                    <?php echo $yearOption; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <div class="student-dtr-metrics">
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Total Logs</div>
                <strong><?php echo (int)$attendanceSummary['total_logs']; ?></strong>
                <small>Recorded entries for <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>.</small>
            </article>
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Approved</div>
                <strong><?php echo (int)$attendanceSummary['approved_logs']; ?></strong>
                <small><?php echo number_format((float)$attendanceInsights['approved_hours'], 2); ?> approved hours.</small>
            </article>
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Pending</div>
                <strong><?php echo (int)$attendanceSummary['pending_logs']; ?></strong>
                <small><?php echo number_format((float)$attendanceInsights['pending_hours'], 2); ?> hours waiting.</small>
            </article>
            <article class="student-metric-card student-dtr-metric">
                <div class="student-dtr-meta">Logged Hours</div>
                <strong><?php echo number_format((float)$attendanceSummary['total_hours'], 2); ?></strong>
                <small>Average <?php echo number_format((float)$attendanceInsights['average_hours'], 2); ?> hours per entry.</small>
            </article>
        </div>

        <div class="row g-4 align-items-start mt-0">
            <div class="col-12 col-xl-8">
                <section class="card student-panel">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <span class="student-metric-label">Attendance Logs</span>
                                <h3 class="mb-1">My DTR Entries</h3>
                                <div class="student-dtr-meta">Daily attendance records for the selected month.</div>
                            </div>
                            <a href="student-profile.php" class="btn btn-outline-primary">Back to Profile</a>
                        </div>

                        <?php if (!empty($attendanceRows)): ?>
                        <div class="student-dtr-table-wrap">
                            <table class="student-dtr-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Morning In</th>
                                        <th>Morning Out</th>
                                        <th>Break In</th>
                                        <th>Break Out</th>
                                        <th>Afternoon In</th>
                                        <th>Afternoon Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRows as $row): ?>
                                    <?php $statusClass = strtolower(trim((string)($row['status'] ?? 'pending'))); ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$row['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (trim((string)($row['remarks'] ?? '')) !== ''): ?>
                                            <div class="student-dtr-cell-note mt-1"><?php echo htmlspecialchars((string)$row['remarks'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(student_dtr_format_time($row['morning_time_in'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(student_dtr_format_time($row['morning_time_out'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(student_dtr_format_time($row['break_time_in'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(student_dtr_format_time($row['break_time_out'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(student_dtr_format_time($row['afternoon_time_in'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(student_dtr_format_time($row['afternoon_time_out'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><strong><?php echo number_format((float)($row['display_hours'] ?? 0), 2); ?>h</strong></td>
                                        <td><span class="student-dtr-status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($statusClass), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo htmlspecialchars(ucfirst((string)($row['source'] ?? 'manual')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="student-dtr-empty">No DTR entries found for <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?> yet.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="card student-panel student-dtr-side-card">
                    <div class="card-body">
                        <span class="student-metric-label">Internship</span>
                        <h3 class="mb-3">Progress</h3>
                        <div class="student-detail-list student-dtr-side-list">
                            <div>
                                <span>Company</span>
                                <strong><?php echo htmlspecialchars(trim((string)($internship['company_name'] ?? '')) !== '' ? (string)$internship['company_name'] : 'No company assigned yet', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Position</span>
                                <strong><?php echo htmlspecialchars(trim((string)($internship['position'] ?? '')) !== '' ? (string)$internship['position'] : 'No position assigned yet', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Current status</span>
                                <strong><?php echo htmlspecialchars(trim((string)($internship['status'] ?? '')) !== '' ? ucfirst((string)$internship['status']) : 'Not started', ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Rendered vs required</span>
                                <strong><?php echo number_format($renderedHours, 0); ?> / <?php echo number_format($requiredHours, 0); ?> hrs</strong>
                            </div>
                            <div>
                                <span>Completion</span>
                                <strong><?php echo number_format($completionPercentage, 0); ?>%</strong>
                            </div>
                            <div>
                                <span>Remaining on record</span>
                                <strong><?php echo number_format(max(0, $remainingHours), 0); ?> hrs</strong>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4 student-dtr-side-card">
                    <div class="card-body">
                        <span class="student-metric-label">Month Snapshot</span>
                        <h3 class="mb-3">Attendance Insight</h3>
                        <div class="student-detail-list student-dtr-side-list">
                            <div>
                                <span>Days with logs</span>
                                <strong><?php echo (int)$attendanceInsights['days_present']; ?></strong>
                            </div>
                            <div>
                                <span>Biometric entries</span>
                                <strong><?php echo (int)$attendanceInsights['biometric_logs']; ?></strong>
                            </div>
                            <div>
                                <span>Manual entries</span>
                                <strong><?php echo (int)$attendanceInsights['manual_logs']; ?></strong>
                            </div>
                            <div>
                                <span>Rejected logs</span>
                                <strong><?php echo (int)$attendanceSummary['rejected_logs']; ?></strong>
                            </div>
                            <div>
                                <span>Last recorded day</span>
                                <strong><?php echo htmlspecialchars($lastRecordedDateText, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <span class="student-metric-label">Quick Links</span>
                        <div class="d-grid gap-2">
                            <a href="student-profile.php" class="btn btn-outline-primary">My Profile</a>
                            <a href="document_application.php" class="btn btn-outline-secondary">My Documents</a>
                            <a href="apps-calendar.php" class="btn btn-outline-secondary">Calendar</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
