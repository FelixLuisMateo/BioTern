<?php
include 'config/db.php';

// Test database connection
echo "Database Connection Test\n";
echo "========================\n\n";

try {
    // Test student count
    $result = $conn->query('SELECT COUNT(*) as count FROM students');
    $row = $result->fetch_assoc();
    echo "Total Students: " . $row['count'] . "\n";
    
    // Test internships count
    $result = $conn->query('SELECT COUNT(*) as count FROM internships');
    $row = $result->fetch_assoc();
    echo "Total Internships: " . $row['count'] . "\n";
    
    // Test attendance count
    $result = $conn->query('SELECT COUNT(*) as count FROM attendances');
    $row = $result->fetch_assoc();
    echo "Total Attendance Records: " . $row['count'] . "\n";
    
    // Test courses count
    $result = $conn->query('SELECT COUNT(*) as count FROM courses');
    $row = $result->fetch_assoc();
    echo "Total Courses: " . $row['count'] . "\n";
    
    echo "\n✓ Database connection successful!";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}

$conn->close();
?>
