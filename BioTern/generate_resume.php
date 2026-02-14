<?php
// Simple resume generator
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) die('Invalid student id');

$query = "SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) die('Student not found');
$student = $result->fetch_assoc();

// Normalize values
$full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$profile = $student['profile_picture'] ?? '';

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>BioTern || Resume - <?php echo htmlspecialchars($full_name); ?></title>
<link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
<style>
    body { font-family: Arial, Helvetica, sans-serif; color: #222; padding: 24px; }
    .resume { max-width: 800px; margin: 0 auto; border: 1px solid #e6e6e6; padding: 24px; }
    .header { display:flex; gap:16px; align-items:center; }
    .photo { width:120px; height:120px; border-radius:6px; overflow:hidden; background:#f2f2f2; display:flex; align-items:center; justify-content:center; }
    .photo img{ max-width:100%; max-height:100%; object-fit:cover; }
    .name { flex:1; }
    h1 { margin:0; font-size:24px; }
    .meta { color:#666; margin-top:6px; }
    .section { margin-top:18px; }
    .section h3 { margin:0 0 8px 0; font-size:16px; border-bottom:1px solid #eee; padding-bottom:6px; }
    .two-col { display:flex; gap:24px; }
    .col { flex:1; }
    .print-btn { position:fixed; right:20px; bottom:20px; }
    @media print { .print-btn { display:none } }
</style>
</head>
<body>
<div class="resume">
    <div class="header">
        <div class="photo">
            <?php if (!empty($profile) && file_exists(__DIR__ . '/' . $profile)): ?>
                <img src="<?php echo htmlspecialchars($profile); ?>" alt="Profile">
            <?php else: ?>
                <div style="padding:8px; text-align:center; color:#999;">No Photo</div>
            <?php endif; ?>
        </div>
        <div class="name">
            <h1><?php echo htmlspecialchars($full_name); ?></h1>
            <div class="meta">
                <?php echo htmlspecialchars($student['student_id'] ?? ''); ?> &nbsp;•&nbsp; <?php echo htmlspecialchars($student['course_name'] ?? ''); ?>
            </div>
            <div class="meta">
                <?php echo htmlspecialchars($student['email'] ?? ''); ?> &nbsp;•&nbsp; <?php echo htmlspecialchars($student['phone'] ?? ''); ?>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Profile</h3>
        <p><?php echo nl2br(htmlspecialchars($student['address'] ?? '-')); ?></p>
    </div>

    <div class="two-col">
        <div class="col">
            <div class="section">
                <h3>Academic</h3>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($student['course_name'] ?? '-'); ?></p>
                <p><strong>Total Hours:</strong> <?php echo htmlspecialchars($student['total_hours'] ?? '-'); ?></p>
            </div>

            <div class="section">
                <h3>Internship</h3>
                <p><strong>Supervisor:</strong> <?php echo htmlspecialchars($student['supervisor_name'] ?? '-'); ?></p>
                <p><strong>Coordinator:</strong> <?php echo htmlspecialchars($student['coordinator_name'] ?? '-'); ?></p>
            </div>
        </div>
        <div class="col">
            <div class="section">
                <h3>Personal</h3>
                <p><strong>Birthday:</strong> <?php echo htmlspecialchars($student['date_of_birth'] ?? '-'); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? '-'); ?></p>
                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($student['emergency_contact'] ?? '-'); ?></p>
            </div>

            <div class="section">
                <h3>Other</h3>
                <p><strong>Biometric Registered:</strong> <?php echo $student['biometric_registered'] ? 'Yes' : 'No'; ?></p>
                <p><strong>Registered At:</strong> <?php echo htmlspecialchars($student['created_at'] ?? '-'); ?></p>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Summary / Remarks</h3>
        <p>Generated resume for <?php echo htmlspecialchars($full_name); ?>.</p>
    </div>
</div>
<button class="btn btn-primary print-btn" onclick="window.print()">Print / Save as PDF</button>
</body>
</html>
