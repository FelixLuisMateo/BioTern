<?php
// Include database connection
include_once 'config/db.php';

// Initialize dashboard data
$dashboard_data = array();
$recent_students = array();
$recent_attendances = array();

try {
    // Total Students
    $total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    
    // Active Students (with ongoing internships)
    $active_students = $conn->query("
        SELECT COUNT(DISTINCT s.id) as count 
        FROM students s 
        JOIN internships i ON s.id = i.student_id 
        WHERE i.status = 'ongoing'
    ")->fetch_assoc()['count'];
    
    // Biometric Registered Students
    $biometric_students = $conn->query("SELECT COUNT(*) as count FROM students WHERE biometric_registered = 1")->fetch_assoc()['count'];
    
    // Total Internships
    $total_internships = $conn->query("SELECT COUNT(*) as count FROM internships")->fetch_assoc()['count'];
    
    // Active/Ongoing Internships
    $active_internships = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'ongoing'")->fetch_assoc()['count'];
    
    // Completed Internships
    $completed_internships = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'completed'")->fetch_assoc()['count'];
    
    // Pending Attendance Approvals
    $pending_approvals = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'pending'")->fetch_assoc()['count'];
    
    // Approved Attendances
    $approved_attendances = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'approved'")->fetch_assoc()['count'];
    
    // Rejected Attendances
    $rejected_attendances = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'rejected'")->fetch_assoc()['count'];
    
    // Total Courses
    $total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
    
    // Today's Attendance Records
    $today = date('Y-m-d');
    $today_attendance = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE DATE(attendance_date) = '$today'")->fetch_assoc()['count'];
    
    // Recent Students (last 5)
    $recent_students_result = $conn->query("
        SELECT s.id, s.student_id, s.first_name, s.last_name, s.email, s.status, s.biometric_registered, s.created_at, c.name as course_name
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    
    if ($recent_students_result && $recent_students_result->num_rows > 0) {
        while ($row = $recent_students_result->fetch_assoc()) {
            $recent_students[] = $row;
        }
    }
    
    // Recent Attendance Records (last 10)
    $recent_attendance_result = $conn->query("
        SELECT a.id, a.student_id, a.attendance_date, a.morning_time_in, a.morning_time_out, a.status, a.created_at, s.first_name, s.last_name, s.student_id as student_num
        FROM attendances a
        LEFT JOIN students s ON a.student_id = s.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    
    if ($recent_attendance_result && $recent_attendance_result->num_rows > 0) {
        while ($row = $recent_attendance_result->fetch_assoc()) {
            $recent_attendances[] = $row;
        }
    }
    
    // Calculation percentage for internships
    $internship_percentage = $total_internships > 0 ? round(($completed_internships / $total_internships) * 100) : 0;
    
    // Calculation percentage for attendance approval
    $total_attendance = $approved_attendances + $pending_approvals + $rejected_attendances;
    $approval_percentage = $total_attendance > 0 ? round(($approved_attendances / $total_attendance) * 100) : 0;
    
    // Calculation percentage for active internships
    $active_internship_percentage = $total_internships > 0 ? round(($active_internships / $total_internships) * 100) : 0;
    
    // Biometric percentage
    $biometric_percentage = $total_students > 0 ? round(($biometric_students / $total_students) * 100) : 0;
    
    // Build dashboard data array
    $dashboard_data = array(
        'total_students' => $total_students,
        'active_students' => $active_students,
        'biometric_students' => $biometric_students,
        'total_internships' => $total_internships,
        'active_internships' => $active_internships,
        'completed_internships' => $completed_internships,
        'pending_approvals' => $pending_approvals,
        'approved_attendances' => $approved_attendances,
        'rejected_attendances' => $rejected_attendances,
        'total_courses' => $total_courses,
        'today_attendance' => $today_attendance,
        'internship_percentage' => $internship_percentage,
        'approval_percentage' => $approval_percentage,
        'active_internship_percentage' => $active_internship_percentage,
        'biometric_percentage' => $biometric_percentage
    );
    
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Dashboard data fetch error: " . $e->getMessage());
    
    // Set default values if queries fail
    $dashboard_data = array(
        'total_students' => 0,
        'active_students' => 0,
        'biometric_students' => 0,
        'total_internships' => 0,
        'active_internships' => 0,
        'completed_internships' => 0,
        'pending_approvals' => 0,
        'approved_attendances' => 0,
        'rejected_attendances' => 0,
        'total_courses' => 0,
        'today_attendance' => 0,
        'internship_percentage' => 0,
        'approval_percentage' => 0,
        'active_internship_percentage' => 0,
        'biometric_percentage' => 0
    );
}

?>
