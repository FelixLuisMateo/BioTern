<?php
$filename = __DIR__ . '/attendance.txt';
if (file_exists($filename)) {
    $json = file_get_contents($filename);
    $logs = json_decode($json, true);
    if (is_array($logs) && count($logs) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>User ID</th><th>Type</th><th>Time</th><th>Flag</th></tr>";
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['id']) . "</td>";
            // Type: 1 = In, 0 = Out (guessing, adjust as needed)
            $type = ($log['type'] == 1) ? 'In' : 'Out';
            echo "<td>" . htmlspecialchars($type) . "</td>";
            echo "<td>" . htmlspecialchars($log['time']) . "</td>";
            echo "<td>" . htmlspecialchars($log['flag']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No attendance records found.";
    }
} else {
    echo "Attendance file not found.";
}
?>