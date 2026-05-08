<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

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

$track = strtolower(trim((string)($_GET['track'] ?? 'internal')));
if (!in_array($track, ['internal', 'external'], true)) {
    $track = 'internal';
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
            s.semester,
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

$tableName = $track === 'external' ? 'external_attendance' : 'attendances';
if ($track === 'external') {
    external_attendance_ensure_schema($conn);
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
    FROM {$tableName}
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
$trackLabel = $track === 'external' ? 'External' : 'Internal';
$sectionLabel = biotern_format_section_label((string)($studentMeta['section_label'] ?? ''), '');
$sectionLabel = str_replace(' | ', ' - ', $sectionLabel);
$schoolLogoPath = dirname(__DIR__) . '/assets/images/ccstlogo.png';
$schoolLogoSrc = '../assets/images/ccstlogo.png';
if (is_file($schoolLogoPath) && is_readable($schoolLogoPath)) {
    $logoData = file_get_contents($schoolLogoPath);
    if ($logoData !== false) {
        $schoolLogoSrc = 'data:image/png;base64,' . base64_encode($logoData);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BioTern || <?php echo dtr_h($trackLabel); ?> DTR - <?php echo dtr_h($studentName); ?></title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
        }
        .paper {
            width: 100%;
            max-width: 920px;
            margin: 0 auto;
            padding: 8mm 10mm 10mm;
            box-sizing: border-box;
        }
        .screen-actions {
            max-width: 920px;
            margin: 18px auto 0;
            padding: 0 10mm;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            box-sizing: border-box;
        }
        .screen-actions a,
        .screen-actions button {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .print-header {
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr) 84px;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid #2f5fb3;
            padding-bottom: 10px;
        }
        .print-header img {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        .print-header-copy {
            text-align: center;
            line-height: 1.3;
        }
        .print-header-spacer {
            width: 72px;
            height: 72px;
        }
        .print-school {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0;
        }
        .print-meta {
            margin: 0;
            font-size: 14px;
            color: #1f4e9f;
            font-weight: 600;
        }
        .print-title {
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            margin: 22px 0 18px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 18px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .meta-row {
            display: flex;
            gap: 8px;
            align-items: baseline;
        }
        .meta-label {
            min-width: 118px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        thead th {
            background: #f8fafc;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 12px;
        }
        th:nth-child(1),
        td:nth-child(1) {
            width: 140px;
            min-width: 140px;
            white-space: nowrap;
        }
        th:nth-child(6),
        td:nth-child(6) {
            width: 90px;
            min-width: 90px;
            white-space: nowrap;
        }
        tbody td:last-child,
        tbody td:nth-last-child(2) {
            white-space: nowrap;
        }
        .empty-row {
            text-align: center;
            color: #64748b;
            padding: 18px 10px;
        }
        @media print {
            .screen-actions {
                display: none;
            }
            .paper {
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="screen-actions">
        <a href="javascript:history.back()">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="paper">
        <div class="print-header">
            <img src="<?php echo dtr_h($schoolLogoSrc); ?>" alt="CCST Logo">
            <div class="print-header-copy">
                <p class="print-school">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
                <p class="print-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</p>
                <p class="print-meta">Telefax No.: (045) 624-0215</p>
            </div>
            <div class="print-header-spacer" aria-hidden="true"></div>
        </div>

        <div class="print-title"><?php echo dtr_h($trackLabel); ?> Daily Time Record</div>

        <div class="meta-grid">
            <div class="meta-row"><span class="meta-label">Student No.</span><span><?php echo dtr_h((string)($studentMeta['student_id'] ?? 'N/A')); ?></span></div>
            <div class="meta-row"><span class="meta-label">Name</span><span><?php echo dtr_h($studentName !== '' ? $studentName : 'N/A'); ?></span></div>
            <div class="meta-row"><span class="meta-label">Course</span><span><?php echo dtr_h((string)($studentMeta['course_name'] ?? 'N/A')); ?></span></div>
            <div class="meta-row"><span class="meta-label">Section</span><span><?php echo dtr_h($sectionLabel !== '' ? $sectionLabel : 'N/A'); ?></span></div>
            <div class="meta-row"><span class="meta-label">School Year</span><span><?php echo dtr_h((string)($studentMeta['school_year'] ?? 'N/A')); ?></span></div>
            <div class="meta-row"><span class="meta-label">Semester</span><span><?php echo dtr_h((string)($studentMeta['semester'] ?? 'N/A')); ?></span></div>
            <div class="meta-row"><span class="meta-label">Start Date</span><span><?php echo dtr_h($startDate !== '' ? $startDate : 'N/A'); ?></span></div>
            <div class="meta-row"><span class="meta-label">End Date</span><span><?php echo dtr_h($endDate !== '' ? $endDate : 'N/A'); ?></span></div>
            <div class="meta-row"><span class="meta-label">Total Logged</span><span><?php echo number_format($totalHours, 2); ?> hrs</span></div>
            <div class="meta-row"><span class="meta-label">Track</span><span><?php echo dtr_h($trackLabel); ?></span></div>
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
                        <td colspan="7" class="empty-row">No attendance records found for the selected date range.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
