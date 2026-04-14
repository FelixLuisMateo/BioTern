<?php
require_once dirname(__DIR__) . '/config/db.php';

function dtr_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dtr_fmt_time($value): string
{
    if (empty($value)) {
        return '-';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('h:i A', $ts) : '-';
}

function dtr_valid_date(?string $value): bool
{
    $raw = trim((string)$value);
    return $raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1;
}

$studentId = 0;
if (isset($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
} elseif (isset($student['id'])) {
    $studentId = (int)$student['id'];
}

$studentMeta = null;
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));

if ($studentId > 0) {
    $metaSql = "
        SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.school_year,
            c.name AS course_name,
            COALESCE(NULLIF(sec.code, ''), sec.name) AS section_label,
            i.start_date AS internship_start_date,
            i.end_date AS internship_end_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        LEFT JOIN internships i ON i.student_id = s.id
        WHERE s.id = ?
        ORDER BY i.id DESC
        LIMIT 1
    ";
    $metaStmt = $conn->prepare($metaSql);
    if ($metaStmt) {
        $metaStmt->bind_param('i', $studentId);
        $metaStmt->execute();
        $studentMeta = $metaStmt->get_result()->fetch_assoc() ?: null;
        $metaStmt->close();
    }
}

if (!$studentMeta) {
    http_response_code(404);
    echo 'Student not found.';
    exit;
}

if (!dtr_valid_date($startDate)) {
    $startDate = trim((string)($studentMeta['internship_start_date'] ?? ''));
}
if (!dtr_valid_date($endDate)) {
    $endDate = trim((string)($studentMeta['internship_end_date'] ?? ''));
}

if (!dtr_valid_date($startDate) && dtr_valid_date($endDate)) {
    $startDate = $endDate;
}
if (!dtr_valid_date($endDate) && dtr_valid_date($startDate)) {
    $endDate = $startDate;
}

$where = ['student_id = ?'];
$types = 'i';
$params = [$studentId];
if (dtr_valid_date($startDate) && dtr_valid_date($endDate)) {
    if (strtotime($endDate) < strtotime($startDate)) {
        $tmp = $startDate;
        $startDate = $endDate;
        $endDate = $tmp;
    }
    $where[] = 'attendance_date BETWEEN ? AND ?';
    $types .= 'ss';
    $params[] = $startDate;
    $params[] = $endDate;
}

$attendances = [];
$totalHours = 0.0;
$attendanceSql = "
    SELECT attendance_date, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out, total_hours, status
    FROM attendances
    WHERE " . implode(' AND ', $where) . "
    ORDER BY attendance_date ASC, id ASC
";
$attStmt = $conn->prepare($attendanceSql);
if ($attStmt) {
    if ($types === 'i') {
        $attStmt->bind_param('i', $studentId);
    } else {
        $attStmt->bind_param('iss', $studentId, $startDate, $endDate);
    }
    $attStmt->execute();
    $attRes = $attStmt->get_result();
    while ($row = $attRes->fetch_assoc()) {
        $attendances[] = $row;
        $totalHours += (float)($row['total_hours'] ?? 0);
    }
    $attStmt->close();
}

$studentName = trim((string)($studentMeta['first_name'] ?? '') . ' ' . (string)($studentMeta['middle_name'] ?? '') . ' ' . (string)($studentMeta['last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BioTern || DTR - <?php echo dtr_h($studentName); ?></title>
    <link rel="stylesheet" href="../assets/css/modules/documents/document-dtr.css">
</head>
<body>
    <h1>Daily Time Record (DTR)</h1>
    <div class="meta">
        <p><strong>Student Number:</strong> <?php echo dtr_h((string)($studentMeta['student_id'] ?? 'N/A')); ?></p>
        <p><strong>Name:</strong> <?php echo dtr_h($studentName !== '' ? $studentName : 'N/A'); ?></p>
        <p><strong>Course:</strong> <?php echo dtr_h((string)($studentMeta['course_name'] ?? 'N/A')); ?></p>
        <p><strong>Section:</strong> <?php echo dtr_h((string)($studentMeta['section_label'] ?? 'N/A')); ?></p>
        <p><strong>School Year:</strong> <?php echo dtr_h((string)($studentMeta['school_year'] ?? 'N/A')); ?></p>
        <p><strong>Start Date:</strong> <?php echo dtr_h($startDate !== '' ? $startDate : 'N/A'); ?></p>
        <p><strong>End Date:</strong> <?php echo dtr_h($endDate !== '' ? $endDate : 'N/A'); ?></p>
        <p><strong>Total Logged Hours:</strong> <?php echo number_format($totalHours, 2); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Morning In</th>
                <th>Morning Out</th>
                <th>Afternoon In</th>
                <th>Afternoon Out</th>
                <th>Total Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($attendances !== []): ?>
                <?php foreach ($attendances as $row): ?>
                    <tr>
                        <td><?php echo dtr_h((string)($row['attendance_date'] ?? '')); ?></td>
                        <td><?php echo dtr_h(dtr_fmt_time($row['morning_time_in'] ?? '')); ?></td>
                        <td><?php echo dtr_h(dtr_fmt_time($row['morning_time_out'] ?? '')); ?></td>
                        <td><?php echo dtr_h(dtr_fmt_time($row['afternoon_time_in'] ?? '')); ?></td>
                        <td><?php echo dtr_h(dtr_fmt_time($row['afternoon_time_out'] ?? '')); ?></td>
                        <td><?php echo dtr_h(number_format((float)($row['total_hours'] ?? 0), 2)); ?></td>
                        <td><?php echo dtr_h(ucfirst((string)($row['status'] ?? 'pending'))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="muted">No attendance records found for selected date range.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>


