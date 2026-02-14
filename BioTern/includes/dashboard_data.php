<?php
// Include database connection
include_once 'config/db.php';

// Initialize dashboard data
$dashboard_data = array();

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
    
    // Calculation percentage for internships
    $internship_percentage = $total_internships > 0 ? round(($completed_internships / $total_internships) * 100) : 0;
    
    // Calculation percentage for attendance approval
    $total_attendance = $approved_attendances + $pending_approvals + $rejected_attendances;
    $approval_percentage = $total_attendance > 0 ? round(($approved_attendances / $total_attendance) * 100) : 0;
    
    // Calculation percentage for active internships
    $active_internship_percentage = $total_internships > 0 ? round(($active_internships / $total_internships) * 100) : 0;
    
    // Build dashboard data array
    $dashboard_data = array(
        'total_students' => $total_students,
        'active_students' => $active_students,
        'total_internships' => $total_internships,
        'active_internships' => $active_internships,
        'completed_internships' => $completed_internships,
        'pending_approvals' => $pending_approvals,
        'approved_attendances' => $approved_attendances,
        'rejected_attendances' => $rejected_attendances,
        'total_courses' => $total_courses,
        'internship_percentage' => $internship_percentage,
        'approval_percentage' => $approval_percentage,
        'active_internship_percentage' => $active_internship_percentage
    );
    
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Dashboard data fetch error: " . $e->getMessage());
    
    // Set default values if queries fail
    $dashboard_data = array(
        'total_students' => 0,
        'active_students' => 0,
        'total_internships' => 0,
        'active_internships' => 0,
        'completed_internships' => 0,
        'pending_approvals' => 0,
        'approved_attendances' => 0,
        'rejected_attendances' => 0,
        'total_courses' => 0,
        'internship_percentage' => 0,
        'approval_percentage' => 0,
        'active_internship_percentage' => 0
    );
}

?>
