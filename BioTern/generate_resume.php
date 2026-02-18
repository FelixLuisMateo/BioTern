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
$phone = $student['phone'] ?? '';
$email = $student['email'] ?? '';
$address = $student['address'] ?? '';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>BioTern || Resume - <?php echo htmlspecialchars($full_name); ?></title>
<link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
<style>
    :root{ --muted:#666; --border:#e6e6e6; --text:#222; --accent:#007bff; }
    html,body{ height:100%; margin:0; font-family: Arial, Helvetica, sans-serif; color:var(--text); background:#f7f7f7; }
    .container { padding: 30px; }
    .resume { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid var(--border); padding: 28px; box-shadow: 0 6px 20px rgba(0,0,0,0.04); }
    .header { display:flex; gap:20px; align-items:flex-start; justify-content:space-between; }
    .left { flex:1; min-width: 0; }
    .right { width: 150px; flex-shrink: 0; display:flex; align-items:flex-start; justify-content:flex-end; }
    .photo { width:150px; height:150px; border-radius:8px; overflow:hidden; background:#f2f2f2; display:flex; align-items:center; justify-content:center; }
    .photo img{ width:100%; height:100%; object-fit:cover; display:block; }
    h1 { margin:0; font-size:28px; line-height:1.05; }
    .contact { margin-top:10px; color:var(--muted); font-size:14px; }
    .contact div { margin-bottom:6px; }
    .meta { color:var(--muted); margin-top:8px; font-size:13px; }
    .section { margin-top:22px; }
    .section h3 { margin:0 0 10px 0; font-size:16px; border-bottom:1px solid #eee; padding-bottom:6px; }
    .two-col { display:flex; gap:24px; margin-top:12px; }
    .col { flex:1; }
    .small { font-size:13px; color:var(--muted); }
    .print-btn { position:fixed; right:20px; bottom:20px; }
    @media (max-width: 700px) {
        .header { flex-direction: column-reverse; align-items: stretch; }
        .right { width: 100%; justify-content: center; margin-bottom: 16px; }
        .photo { width:120px; height:120px; margin: 0 auto; }
        h1 { font-size:22px; text-align:center; }
        .left .contact { text-align:center; }
    }
    @media print { .print-btn { display:none } }
</style>
</head>
<body>
<div class="container">
  <div class="resume">
    <div class="header">
      <div class="left">
        <h1><?php echo htmlspecialchars($full_name); ?></h1>
        <div class="contact">
          <?php if ($phone): ?><div><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></div><?php endif; ?>
          <?php if ($email): ?><div><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></div><?php endif; ?>
          <?php if ($address): ?><div><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($address)); ?></div><?php endif; ?>
        </div>
        <div class="meta small">
            <?php echo htmlspecialchars($student['student_id'] ?? ''); ?> &nbsp;•&nbsp; <?php echo htmlspecialchars($student['course_name'] ?? ''); ?>
        </div>
      </div>

      <div class="right">
        <div class="photo" aria-hidden="true">
            <?php if (!empty($profile) && file_exists(__DIR__ . '/' . $profile)): ?>
                <img src="<?php echo htmlspecialchars($profile); ?>" alt="Profile">
            <?php else: ?>
                <div style="padding:8px; text-align:center; color:#999; font-size:14px;">No Photo</div>
            <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="two-col">
        <div class="col">
            <div class="section">
                <h3>Academic:</h3>
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
                <h3>Personal:</h3>
                <p><strong>Birthday:</strong> <?php echo htmlspecialchars($student['date_of_birth'] ?? '-'); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? '-'); ?></p>
                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($student['emergency_contact'] ?? '-'); ?></p>
            </div>

            
        </div>
    </div>

    <div class="section">
        <h3>Summary / Remarks</h3>
        <p class="small">Generated resume for <?php echo htmlspecialchars($full_name); ?>.</p>
    </div>
  </div>

  <button class="btn btn-primary print-btn" onclick="window.print()">Print / Save as PDF</button>
</div>
</body>
</html>