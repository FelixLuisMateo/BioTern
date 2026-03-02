<?php
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$attendance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$attendance = null;

if ($attendance_id > 0) {
    $query = "
        SELECT 
            a.*,
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.email,
            s.student_id as student_number,
            c.name as course_name,
            d.name as department_name,
            u.name as approver_name
        FROM attendances a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN users u ON a.approved_by = u.id
        WHERE a.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $attendance = $result->fetch_assoc();
    }
    $stmt->close();
}

$conn->close();

function formatTime($time) {
    if ($time) {
        return date('h:i A', strtotime($time));
    }
    return '-';
}

function calculateHours($time_in, $time_out) {
    if ($time_in && $time_out) {
        $diff = strtotime($time_out) - strtotime($time_in);
        return round($diff / 3600, 2);
    }
    return 0;
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'approved':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'pending':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Attendance - BioTern</title>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <style>
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none !important; }
            .card { page-break-inside: avoid; }
        }
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .info-section { margin: 20px 0; }
        .info-row { display: flex; margin: 10px 0; }
        .info-label { font-weight: bold; width: 200px; }
        .attendance-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .attendance-table th, .attendance-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .attendance-table th { background-color: #f8f9fa; }
        .status-approved { color: green; font-weight: bold; }
        .status-rejected { color: red; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; font-size: 0.9rem; color: #666; }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn btn-primary no-print" style="margin-bottom: 20px;">
        <i class="feather-printer"></i> Print
    </button>
    
    <?php if ($attendance): ?>
        <div class="header">
            <h2>BioTern Attendance Record</h2>
            <p>Daily Time Record (DTR)</p>
        </div>
        
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Student Name:</span>
                <span><?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Student ID:</span>
                <span><?php echo htmlspecialchars($attendance['student_number']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span><?php echo htmlspecialchars($attendance['email'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Course:</span>
                <span><?php echo htmlspecialchars($attendance['course_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Department:</span>
                <span><?php echo htmlspecialchars($attendance['department_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Attendance Date:</span>
                <span><strong><?php echo date('F d, Y', strtotime($attendance['attendance_date'])); ?></strong></span>
            </div>
        </div>
        
        <table class="attendance-table">
            <thead>
                <tr>
                    <th colspan="2">Morning Session</th>
                    <th colspan="2">Break Session</th>
                    <th colspan="2">Afternoon Session</th>
                </tr>
                <tr>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo formatTime($attendance['morning_time_in']); ?></td>
                    <td><?php echo formatTime($attendance['morning_time_out']); ?></td>
                    <td><?php echo formatTime($attendance['break_time_in']); ?></td>
                    <td><?php echo formatTime($attendance['break_time_out']); ?></td>
                    <td><?php echo formatTime($attendance['afternoon_time_in']); ?></td>
                    <td><?php echo formatTime($attendance['afternoon_time_out']); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="info-section" style="margin-top: 30px;">
            <h5>Hours Summary</h5>
            <div class="info-row">
                <span class="info-label">Morning Hours:</span>
                <span><?php echo calculateHours($attendance['morning_time_in'], $attendance['morning_time_out']); ?> hours</span>
            </div>
            <div class="info-row">
                <span class="info-label">Break Hours:</span>
                <span><?php echo calculateHours($attendance['break_time_in'], $attendance['break_time_out']); ?> hours</span>
            </div>
            <div class="info-row">
                <span class="info-label">Afternoon Hours:</span>
                <span><?php echo calculateHours($attendance['afternoon_time_in'], $attendance['afternoon_time_out']); ?> hours</span>
            </div>
            <div class="info-row" style="margin-top: 10px; font-weight: bold;">
                <span class="info-label">Total Hours:</span>
                <span>
                    <?php 
                    $total = calculateHours($attendance['morning_time_in'], $attendance['morning_time_out']) +
                             calculateHours($attendance['afternoon_time_in'], $attendance['afternoon_time_out']);
                    echo $total . ' hours';
                    ?>
                </span>
            </div>
        </div>
        
        <div class="info-section">
            <h5>Approval Status</h5>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="status-<?php echo $attendance['status']; ?>">
                    <?php echo ucfirst($attendance['status']); ?>
                </span>
            </div>
            <?php if ($attendance['approved_by'] && $attendance['status'] === 'approved'): ?>
                <div class="info-row">
                    <span class="info-label">Approved By:</span>
                    <span><?php echo htmlspecialchars($attendance['approver_name'] ?? 'Admin'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Approved At:</span>
                    <span><?php echo date('F d, Y h:i A', strtotime($attendance['approved_at'])); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($attendance['remarks'])): ?>
                <div class="info-row">
                    <span class="info-label">Remarks:</span>
                    <span><?php echo htmlspecialchars($attendance['remarks']); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
            <p>BioTern Internship Management System</p>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <h4>Attendance Record Not Found</h4>
            <p>The attendance record you're trying to print could not be found.</p>
        </div>
    <?php endif; ?>
    
    <script>
        // Auto-trigger print dialog when page loads
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
