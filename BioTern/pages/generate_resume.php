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

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Resume - ' . $full_name;
$base_href = $asset_prefix;
$page_body_class = 'app-generate-page';
$page_styles = [
    'assets/css/modules/documents/generate-shell-clean.css',
    'assets/css/modules/documents/generate-resume-page.css',
];
$page_scripts = [
    'assets/js/modules/documents/generate-resume-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="main-content">
<div class="container app-resume-container">
    <div class="resume app-resume-sheet">
        <div class="header app-resume-header">
            <div class="left app-resume-left">
        <h1><?php echo htmlspecialchars($full_name); ?></h1>
                <div class="contact app-resume-contact">
          <?php if ($phone): ?><div><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></div><?php endif; ?>
          <?php if ($email): ?><div><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></div><?php endif; ?>
          <?php if ($address): ?><div><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($address)); ?></div><?php endif; ?>
        </div>
                <div class="meta app-resume-meta small app-resume-small">
            <?php echo htmlspecialchars($student['student_id'] ?? ''); ?> &nbsp;•&nbsp; <?php echo htmlspecialchars($student['course_name'] ?? ''); ?>
        </div>
      </div>

            <div class="right app-resume-right">
                <div class="photo app-resume-photo" aria-hidden="true">
            <?php if (!empty($profile) && file_exists(__DIR__ . '/' . $profile)): ?>
                <img src="<?php echo htmlspecialchars($profile); ?>" alt="Profile">
            <?php else: ?>
                                <div class="photo-placeholder app-resume-photo-placeholder">No Photo</div>
            <?php endif; ?>
        </div>
      </div>
    </div>
        <div class="two-col app-resume-two-col">
                <div class="col app-resume-col">
                        <div class="section app-resume-section">
                <h3>Academic:</h3>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($student['course_name'] ?? '-'); ?></p>
                <p><strong>Total Hours:</strong> <?php echo htmlspecialchars($student['internal_total_hours'] ?? '-'); ?></p>
            </div>

                        <div class="section app-resume-section">
                <h3>Internship</h3>
                <p><strong>Supervisor:</strong> <?php echo htmlspecialchars($student['supervisor_name'] ?? '-'); ?></p>
                <p><strong>Coordinator:</strong> <?php echo htmlspecialchars($student['coordinator_name'] ?? '-'); ?></p>
            </div>
        </div>

                <div class="col app-resume-col">
                        <div class="section app-resume-section">
                <h3>Personal:</h3>
                <p><strong>Birthday:</strong> <?php echo htmlspecialchars($student['date_of_birth'] ?? '-'); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? '-'); ?></p>
                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($student['emergency_contact'] ?? '-'); ?></p>
            </div>

            
        </div>
    </div>

    <div class="section app-resume-section">
        <h3>Summary / Remarks</h3>
        <p class="small app-resume-small">Generated resume for <?php echo htmlspecialchars($full_name); ?>.</p>
    </div>
  </div>

    <button class="btn btn-primary print-btn app-resume-print-btn" id="btn_print_resume" type="button">Print</button>
</div>
</div>

</div> <!-- .nxl-content -->
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>






