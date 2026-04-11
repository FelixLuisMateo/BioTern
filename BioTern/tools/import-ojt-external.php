<?php
ob_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator'], true)) {
    header('Location: homepage.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'biotern_db',
        defined('DB_PORT') ? (int)DB_PORT : 3306
    );
    if ($conn->connect_error) {
        ob_end_clean();
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
}

$conn->query("CREATE TABLE IF NOT EXISTS ojt_external (
    student_no VARCHAR(100) NOT NULL,
    user_id INT NULL,
    last_name VARCHAR(150) NOT NULL DEFAULT '',
    first_name VARCHAR(150) NOT NULL DEFAULT '',
    middle_name VARCHAR(150) NOT NULL DEFAULT '',
    course_id INT NULL,
    section_id INT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_no),
    KEY idx_ojt_external_user_id (user_id),
    KEY idx_ojt_external_course_id (course_id),
    KEY idx_ojt_external_section_id (section_id),
    KEY idx_ojt_external_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$flashType = '';
$flashMessage = '';

$normalizeHeader = static function (string $header): string {
    $header = strtolower(trim($header));
    $header = str_replace(['-', ' '], '_', $header);
    return preg_replace('/[^a-z0-9_]/', '', $header) ?? '';
};

$headerCandidates = [
    'student_no' => ['student_no', 'studentno', 'student_number', 'student_num', 'student_id'],
    'user_id' => ['user_id', 'userid'],
    'last_name' => ['last_name', 'lastname', 'surname'],
    'first_name' => ['first_name', 'firstname', 'given_name'],
    'middle_name' => ['middle_name', 'middlename', 'middle_initial'],
    'course_id' => ['course_id', 'courseid'],
    'section_id' => ['section_id', 'sectionid'],
    'email' => ['email', 'email_address'],
    'password' => ['password', 'pass'],
    'status' => ['status', 'state'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
            throw new RuntimeException('Upload a CSV file first.');
        }

        $tmp = (string)($_FILES['csv_file']['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $handle = fopen($tmp, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $rawHeader = fgetcsv($handle);
        if (!is_array($rawHeader) || empty($rawHeader)) {
            fclose($handle);
            throw new RuntimeException('CSV is empty.');
        }

        $normalizedHeader = [];
        foreach ($rawHeader as $idx => $col) {
            $normalizedHeader[(int)$idx] = $normalizeHeader((string)$col);
        }

        $resolved = [];
        foreach ($headerCandidates as $target => $candidates) {
            $resolved[$target] = null;
            foreach ($normalizedHeader as $idx => $normalized) {
                if (in_array($normalized, $candidates, true)) {
                    $resolved[$target] = (int)$idx;
                    break;
                }
            }
        }

        if ($resolved['student_no'] === null || $resolved['last_name'] === null || $resolved['first_name'] === null) {
            fclose($handle);
            throw new RuntimeException('CSV must include Student No, Last Name, and First Name columns.');
        }

        $stmt = $conn->prepare("INSERT INTO ojt_external
            (student_no, user_id, last_name, first_name, middle_name, course_id, section_id, email, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                last_name = VALUES(last_name),
                first_name = VALUES(first_name),
                middle_name = VALUES(middle_name),
                course_id = VALUES(course_id),
                section_id = VALUES(section_id),
                email = VALUES(email),
                password = VALUES(password),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP");

        if (!$stmt) {
            fclose($handle);
            throw new RuntimeException('Failed to prepare import query.');
        }

        $processed = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row)) {
                continue;
            }

            $studentNo = trim((string)($row[$resolved['student_no']] ?? ''));
            if ($studentNo === '') {
                continue;
            }

            $userIdRaw = $resolved['user_id'] !== null ? trim((string)($row[$resolved['user_id']] ?? '')) : '';
            $courseIdRaw = $resolved['course_id'] !== null ? trim((string)($row[$resolved['course_id']] ?? '')) : '';
            $sectionIdRaw = $resolved['section_id'] !== null ? trim((string)($row[$resolved['section_id']] ?? '')) : '';

            $userId = (ctype_digit($userIdRaw) && (int)$userIdRaw > 0) ? (int)$userIdRaw : null;
            $courseId = (ctype_digit($courseIdRaw) && (int)$courseIdRaw > 0) ? (int)$courseIdRaw : null;
            $sectionId = (ctype_digit($sectionIdRaw) && (int)$sectionIdRaw > 0) ? (int)$sectionIdRaw : null;

            $lastName = trim((string)($row[$resolved['last_name']] ?? ''));
            $firstName = trim((string)($row[$resolved['first_name']] ?? ''));
            $middleName = $resolved['middle_name'] !== null ? trim((string)($row[$resolved['middle_name']] ?? '')) : '';
            $email = $resolved['email'] !== null ? trim((string)($row[$resolved['email']] ?? '')) : '';
            $password = $resolved['password'] !== null ? trim((string)($row[$resolved['password']] ?? '')) : '';
            $status = $resolved['status'] !== null ? trim((string)($row[$resolved['status']] ?? '')) : 'active';
            if ($status === '') {
                $status = 'active';
            }

            $stmt->bind_param(
                'sisssiisss',
                $studentNo,
                $userId,
                $lastName,
                $firstName,
                $middleName,
                $courseId,
                $sectionId,
                $email,
                $password,
                $status
            );
            $stmt->execute();
            $processed++;
        }

        fclose($handle);
        $stmt->close();

        // Auto-link imported external data to students table via student number.
        $conn->query("UPDATE ojt_external oe
                        INNER JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oe.student_no, '')) COLLATE utf8mb4_unicode_ci
            SET oe.user_id = s.user_id
            WHERE (oe.user_id IS NULL OR oe.user_id = 0)
              AND s.user_id IS NOT NULL
              AND s.user_id > 0");

        $flashType = 'success';
        $flashMessage = 'Imported OJT External rows: ' . $processed;
    } catch (Throwable $e) {
        $flashType = 'danger';
        $flashMessage = $e->getMessage();
    }
}

$page_title = 'Import OJT External';
$page_body_class = 'page-fingerprint-mapping';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
];
$base_href = '';
include __DIR__ . '/../includes/header.php';
ob_end_flush();
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Import OJT External</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Import OJT External</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <div class="page-header-right-items">
                    <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                        <a href="ojt-external-list.php" class="btn btn-light-brand">External List</a>
                        <a href="ojt-internal-list.php" class="btn btn-outline-secondary">Internal List</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> py-2"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="card mb-4 bio-console-panel">
                <div class="card-header"><strong>CSV Upload</strong></div>
                <div class="card-body">
                    <p class="text-muted mb-3">Required CSV columns: Student No, Last Name, First Name. Recommended: User Id, Middle Name, Course Id, Section Id, Email, Password, Status.</p>
                    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="csv_file">OJT External CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="col-12 col-md-4 fm-actions">
                            <button type="submit" class="btn btn-primary">Import CSV</button>
                            <a href="ojt-external-list.php" class="btn btn-light">View External List</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>