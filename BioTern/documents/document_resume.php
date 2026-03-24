<?php
$student_name = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resume</title>
    <link rel="stylesheet" href="../assets/css/documents/document-resume.css">
</head>
<body>
    <h1><?php echo htmlspecialchars($student_name !== '' ? $student_name : 'Student Name'); ?></h1>
    <small><?php echo htmlspecialchars((string)($student['email'] ?? '')); ?> | <?php echo htmlspecialchars((string)($student['phone'] ?? '')); ?></small>
    <hr>
    <h3>Objective</h3>
    <p><?php echo htmlspecialchars((string)($student['bio'] ?? 'To apply my skills and gain internship experience.')); ?></p>
    <h3>Education</h3>
    <p>Course: <?php echo htmlspecialchars((string)($student['course_name'] ?? ($student['course_id'] ?? 'N/A'))); ?></p>
    <h3>Personal Information</h3>
    <p>Address: <?php echo htmlspecialchars((string)($student['address'] ?? '')); ?></p>
    <p>Emergency Contact: <?php echo htmlspecialchars((string)($student['emergency_contact'] ?? '')); ?></p>
</body>
</html>


