<?php
// Include database connection
include_once 'config/db.php';

echo "Testing database connection...<br>";

// Test attendance queries
$result = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'pending'");
$awaiting = $result->fetch_assoc()['count'];
echo "Awaiting: " . $awaiting . "<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'approved'");
$approved = $result->fetch_assoc()['count'];
echo "Approved: " . $approved . "<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'rejected'");
$rejected = $result->fetch_assoc()['count'];
echo "Rejected: " . $rejected . "<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM attendances");
$total = $result->fetch_assoc()['count'];
echo "Total: " . $total . "<br>";

echo "<br>Internships:<br>";
$result = $conn->query("SELECT COUNT(*) as count FROM internships");
$internships = $result->fetch_assoc()['count'];
echo "Total Internships: " . $internships . "<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM students");
$students = $result->fetch_assoc()['count'];
echo "Total Students: " . $students . "<br>";

echo "<br>All data loaded successfully!";
?>
