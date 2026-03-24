<?php
$student_name = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? ''));
$student_number = (string)($student['student_id'] ?? 'N/A');

$attendances = [];
$total_hours = 0.0;
if (isset($conn) && $conn instanceof mysqli) {
    $stmt_att = $conn->prepare("\n        SELECT attendance_date, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out, total_hours, status\n        FROM attendances\n        WHERE student_id = ?\n        ORDER BY attendance_date DESC\n        LIMIT 120\n    ");
    if ($stmt_att) {
        $sid = (int)($student['id'] ?? 0);
        $stmt_att->bind_param('i', $sid);
        $stmt_att->execute();
        $res_att = $stmt_att->get_result();
        while ($row = $res_att->fetch_assoc()) {
            $attendances[] = $row;
            $total_hours += (float)($row['total_hours'] ?? 0);
        }
        $stmt_att->close();
    }
}

function dtr_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dtr_fmt_time($value): string {
    if (empty($value)) return '-';
    $ts = strtotime((string)$value);
    return $ts ? date('h:i A', $ts) : '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTR - <?php echo dtr_h($student_name); ?></title>
    <link rel="stylesheet" href="../assets/css/documents/document-dtr.css">
</head>
<body>
    <h1>Daily Time Record (DTR)</h1>
    <div class="meta">
        <p><strong>Student:</strong> <?php echo dtr_h($student_name); ?></p>
        <p><strong>Student ID:</strong> <?php echo dtr_h($student_number); ?></p>
        <p><strong>Total Logged Hours:</strong> <?php echo number_format($total_hours, 2); ?></p>
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
            <?php if (!empty($attendances)): ?>
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
                    <td colspan="7" class="muted">No attendance records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>


