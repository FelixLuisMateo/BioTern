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

// Get student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    die("Invalid student ID");
}

// Fetch Student Details
$student_query = "
    SELECT 
        s.id,
        s.student_id,
        s.first_name,
        s.last_name,
        s.middle_name,
        s.email,
        s.phone,
        s.date_of_birth,
        s.gender,
        s.address,
        s.emergency_contact,
        s.status,
        s.biometric_registered,
        s.biometric_registered_at,
        s.created_at,
        c.name as course_name,
        c.id as course_id
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Student not found");
}

$student = $result->fetch_assoc();

// Fetch all courses for dropdown
$courses_query = "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name ASC";
$courses_result = $conn->query($courses_query);
$courses = [];
if ($courses_result->num_rows > 0) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First Name, Last Name, and Email are required fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    } else {
        // Check if email already exists (excluding current student)
        $email_check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $student_id);
        $email_check->execute();
        $email_result = $email_check->get_result();

        if ($email_result->num_rows > 0) {
            $error_message = "Email address already exists!";
        } else {
            // Update student in database
            $update_query = "
                UPDATE students 
                SET 
                    first_name = ?,
                    last_name = ?,
                    middle_name = ?,
                    email = ?,
                    phone = ?,
                    date_of_birth = ?,
                    gender = ?,
                    address = ?,
                    emergency_contact = ?,
                    course_id = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";

            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                $error_message = "Prepare failed: " . $conn->error;
            } else {
                $update_stmt->bind_param(
                    "ssssssssssii",
                    $first_name,
                    $last_name,
                    $middle_name,
                    $email,
                    $phone,
                    $date_of_birth,
                    $gender,
                    $address,
                    $emergency_contact,
                    $course_id,
                    $status,
                    $student_id
                );

                if ($update_stmt->execute()) {
                    $success_message = "✓ Student information updated successfully!";
                    // Refresh student data
                    $stmt = $conn->prepare($student_query);
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $student = $result->fetch_assoc();
                } else {
                    $error_message = "Error updating student: " . $update_stmt->error;
                }

                $update_stmt->close();
            }
        }
    }
}

// Helper functions
function formatDate($date) {
    if ($date) {
        return date('Y-m-d', strtotime($date));
    }
    return '';
}

function formatDateTime($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return 'N/A';
}

?>

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Edit Student - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/datepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
</head>

<body>
    <!--! Navigation !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.html" class="b-brand">
                    <img src="assets/images/logo-full.png" alt="" class="logo logo-lg">
                    <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm">
                </a>
            </div>
            <div class="navbar-content">
                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboards</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="index.html">CRM</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="analytics.html">Analytics</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="students.php">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-view.php">Students View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-create.html">Students Create</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="attendance.php">Attendance DTR</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="demo-biometric.php">Demo Biometric</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-settings"></i></span>
                            <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="settings-general.html">General</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                            <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="#!">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.html">KnowledgeBase</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item nxl-header-search">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                            <div class="input-group search-form">
                                <span class="input-group-text">
                                    <i class="feather-search fs-6 text-muted"></i>
                                </span>
                                <input type="text" class="form-control search-input-field" placeholder="Search....">
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar">
                                    <div>
                                        <h6 class="text-dark mb-0">Felix Luis Mateo</h6>
                                        <span class="fs-12 fw-medium text-muted">felixluismateo@example.com</span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="./auth-login-minimal.html" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Student</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item"><a href="students-view.php?id=<?php echo $student['id']; ?>">View</a></li>
                        <li class="breadcrumb-item">Edit</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="students-view.php?id=<?php echo $student['id']; ?>" class="btn btn-light-brand">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back to View</span>
                            </a>
                            <a href="students.php" class="btn btn-primary">
                                <i class="feather-list me-2"></i>
                                <span>Back to List</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="card-title mb-0">Edit Student Information</h5>
                                    <small class="text-muted">Student ID: <?php echo htmlspecialchars($student['student_id']); ?></small>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Alert Messages -->
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="feather-check-circle me-2"></i>
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="feather-alert-circle me-2"></i>
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- Edit Form -->
                                <form method="POST" action="" id="editStudentForm">
                                    <!-- Personal Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-user me-2"></i>Personal Information
                                        </h6>

                                        <div class="row">
                                            <div class="col-md-4 mb-4">
                                                <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label for="middle_name" class="form-label fw-semibold">Middle Name</label>
                                                <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                                       value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="date_of_birth" class="form-label fw-semibold">Date of Birth</label>
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                       value="<?php echo formatDate($student['date_of_birth']); ?>">
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="gender" class="form-label fw-semibold">Gender</label>
                                                <select class="form-control" id="gender" name="gender">
                                                    <option value="">-- Select Gender --</option>
                                                    <option value="male" <?php echo $student['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="female" <?php echo $student['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="other" <?php echo $student['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="address" class="form-label fw-semibold">Home Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-4">
                                            <label for="emergency_contact" class="form-label fw-semibold">Emergency Contact</label>
                                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                                   value="<?php echo htmlspecialchars($student['emergency_contact'] ?? ''); ?>"
                                                   placeholder="Name and phone number">
                                        </div>
                                    </div>

                                    <hr>

                                    <!-- Academic Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-book me-2"></i>Academic Information
                                        </h6>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="course_id" class="form-label fw-semibold">Course</label>
                                                <select class="form-control" id="course_id" name="course_id">
                                                    <option value="0">-- Select Course --</option>
                                                    <?php foreach ($courses as $course): ?>
                                                        <option value="<?php echo $course['id']; ?>" 
                                                            <?php echo $student['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($course['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="status" class="form-label fw-semibold">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="1" <?php echo $student['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                                                    <option value="0" <?php echo $student['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold text-muted">Biometric Status</label>
                                                <div class="form-text">
                                                    <?php if ($student['biometric_registered']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="feather-check me-1"></i>Registered
                                                        </span>
                                                        <small class="d-block mt-2 text-muted">
                                                            Registered on: <?php echo formatDateTime($student['biometric_registered_at']); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="feather-alert-circle me-1"></i>Not Registered
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold text-muted">Registration Date</label>
                                                <div class="form-text">
                                                    <small class="text-muted">
                                                        Registered: <?php echo formatDateTime($student['created_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <!-- Form Actions -->
                                    <div class="d-flex gap-2 justify-content-end pt-4">
                                        <a href="students-view.php?id=<?php echo $student['id']; ?>" class="btn btn-light-brand">
                                            <i class="feather-x me-2"></i>Cancel
                                        </a>
                                        <button type="reset" class="btn btn-outline-secondary">
                                            <i class="feather-refresh-cw me-2"></i>Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather-save me-2"></i>Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p><span>By: <a target="_blank" href="">ACT 2A</a></span> • <span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <script src="assets/vendors/js/datepicker.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>

    <script>
        // Initialize form elements
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize select2 for dropdowns
            $('#course_id, #status, #gender').select2({
                allowClear: true,
                width: '100%'
            });

            // Initialize datepicker for date fields
            $('#date_of_birth').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true
            });

            // Form validation
            document.getElementById('editStudentForm').addEventListener('submit', function(e) {
                var firstName = document.getElementById('first_name').value.trim();
                var lastName = document.getElementById('last_name').value.trim();
                var email = document.getElementById('email').value.trim();

                if (!firstName || !lastName || !email) {
                    e.preventDefault();
                    alert('Please fill in all required fields!');
                    return false;
                }

                // Email validation
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address!');
                    return false;
                }

                return true;
            });
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>

</html>

<?php
$conn->close();
?>