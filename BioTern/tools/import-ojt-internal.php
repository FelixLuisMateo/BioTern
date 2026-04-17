<?php
ob_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once __DIR__ . '/excel-workbook-reader.php';
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}
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

$conn->query("CREATE TABLE IF NOT EXISTS ojt_internal (
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
    KEY idx_ojt_internal_user_id (user_id),
    KEY idx_ojt_internal_course_id (course_id),
    KEY idx_ojt_internal_section_id (section_id),
    KEY idx_ojt_internal_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$flashType = '';
$flashMessage = '';
$flashDetail = '';

$normalizeValue = static function (string $value): string {
    return strtolower(trim($value));
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
        if (!isset($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
            throw new RuntimeException('Upload an Excel file first.');
        }

        $tmp = (string)($_FILES['excel_file']['tmp_name'] ?? '');
        $originalName = trim((string)($_FILES['excel_file']['name'] ?? 'uploaded-workbook.xlsx'));
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $workbookReadError = '';
        $rows = ojt_import_load_workbook_rows($tmp, $originalName, $workbookReadError);
        if ($rows === []) {
            throw new RuntimeException($workbookReadError !== '' ? $workbookReadError : 'Unable to read uploaded workbook.');
        }

        $normalizedHeader = array_keys($rows[0]);

        $resolved = [];
        foreach ($headerCandidates as $target => $candidates) {
            $resolved[$target] = null;
            foreach ($normalizedHeader as $normalized) {
                if (in_array($normalized, $candidates, true)) {
                    $resolved[$target] = (string)$normalized;
                    break;
                }
            }
        }

        if ($resolved['student_no'] === null || $resolved['last_name'] === null || $resolved['first_name'] === null) {
            throw new RuntimeException('Workbook must include Student No, Last Name, and First Name columns.');
        }

        $stmt = $conn->prepare("INSERT INTO ojt_internal
            (student_no, user_id, last_name, first_name, middle_name, course_id, section_id, email, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = COALESCE(VALUES(user_id), ojt_internal.user_id),
                last_name = VALUES(last_name),
                first_name = VALUES(first_name),
                middle_name = VALUES(middle_name),
                course_id = COALESCE(VALUES(course_id), ojt_internal.course_id),
                section_id = COALESCE(VALUES(section_id), ojt_internal.section_id),
                email = VALUES(email),
                password = VALUES(password),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP");

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare import query.');
        }

        $existingStudentNos = [];
        $existingRes = $conn->query('SELECT student_no, last_name, first_name, middle_name, email FROM ojt_internal');
        if ($existingRes instanceof mysqli_result) {
            while ($existing = $existingRes->fetch_assoc()) {
                $existingNo = trim((string)($existing['student_no'] ?? ''));
                if ($existingNo !== '') {
                    $existingStudentNos[$normalizeValue($existingNo)] = true;
                }
            }
            $existingRes->close();
        }

        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $duplicateStudentNos = [];
        $seenStudentNos = [];
        $invalidSkipped = 0;
        $lineNumber = 1;
        foreach ($rows as $row) {
            $lineNumber++;
            if (!is_array($row)) {
                continue;
            }

            $studentNo = trim((string)($row[$resolved['student_no']] ?? ''));
            if ($studentNo === '') {
                $invalidSkipped++;
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

            if ($lastName === '' || $firstName === '') {
                $invalidSkipped++;
                continue;
            }

            $normalizedStudentNo = $normalizeValue($studentNo);
            if (isset($existingStudentNos[$normalizedStudentNo]) || isset($seenStudentNos[$normalizedStudentNo])) {
                $duplicateStudentNos[$studentNo] = true;
            }
            $seenStudentNos[$normalizedStudentNo] = true;

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
            if (!$stmt->execute()) {
                throw new RuntimeException('Import failed at CSV line ' . $lineNumber . ': ' . $stmt->error);
            }

            $existingStudentNos[$normalizedStudentNo] = true;
            if ((int)$stmt->affected_rows === 1) {
                $inserted++;
            } else {
                $updated++;
            }
            $processed++;
        }

        $stmt->close();

        // Auto-link imported internal data to students table via student number.
        $conn->query("UPDATE ojt_internal oi
                        INNER JOIN students s ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(oi.student_no, '')) COLLATE utf8mb4_unicode_ci
            SET oi.user_id = s.user_id
            WHERE (oi.user_id IS NULL OR oi.user_id = 0)
              AND s.user_id IS NOT NULL
              AND s.user_id > 0");

        $duplicateList = array_keys($duplicateStudentNos);
        $flashType = 'success';
        if (!empty($duplicateList)) {
            $flashMessage = 'Import completed. Duplicate Student Numbers were merged by replacement.';
        } else {
            $flashMessage = 'Import completed successfully.';
        }
        $flashDetail = 'Processed: ' . $processed . ' | New: ' . $inserted . ' | Replaced: ' . $updated . ' | Duplicate Student No detected: ' . count($duplicateList) . ' | Invalid rows skipped: ' . $invalidSkipped;
        if (!empty($duplicateList)) {
            $flashDetail .= ' | Student No: ' . implode(', ', array_slice($duplicateList, 0, 8));
            if (count($duplicateList) > 8) {
                $flashDetail .= ' ...';
            }
        }
    } catch (Throwable $e) {
        $flashType = 'danger';
        $flashMessage = $e->getMessage();
        $flashDetail = '';
    }
}

$page_title = 'Import OJT Internal';
$page_body_class = 'page-fingerprint-mapping';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
];
$base_href = '';
include __DIR__ . '/../includes/header.php';
ob_end_flush();
?>
<style>
.import-uploader-box { border: 1px dashed rgba(120, 148, 255, 0.45); border-radius: 14px; padding: 16px; background: rgba(13, 32, 82, 0.25); }
.import-kpi { border: 1px solid rgba(120, 148, 255, 0.25); border-radius: 12px; padding: 10px 12px; background: rgba(6, 20, 52, 0.4); }
.import-kpi-label { font-size: 12px; color: #9eb6ff; display: block; }
.import-kpi-value { font-size: 18px; font-weight: 700; color: #ffffff; }
.biotern-toast { position: fixed; right: 18px; top: 78px; z-index: 2050; min-width: 320px; max-width: 460px; padding: 12px 14px; border-radius: 10px; color: #fff; opacity: 0; transform: translateY(-10px); transition: all .25s ease; box-shadow: 0 12px 28px rgba(0,0,0,.28); }
.biotern-toast.show { opacity: 1; transform: translateY(0); }
.biotern-toast-success { background: linear-gradient(135deg, #0e8c5a, #15a66d); }
.biotern-toast-danger { background: linear-gradient(135deg, #a02846, #cf3d63); }
</style>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Import OJT Internal</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Import OJT Internal</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <div class="page-header-right-items">
                    <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                        <a href="ojt-internal-list.php" class="btn btn-light-brand">Internal List</a>
                        <a href="import-ojt-external.php" class="btn btn-outline-secondary">Import OJT External</a>
                        <a href="ojt-external-list.php" class="btn btn-outline-secondary">External List</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
            <div class="card mb-4 bio-console-panel">
                <div class="card-header"><strong>Excel Upload</strong></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="import-kpi">
                                <span class="import-kpi-label">Duplicate Rule</span>
                                <span class="import-kpi-value">Student No (Unique Key)</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <p class="text-muted mb-0">Required Excel columns: Student No, Last Name, First Name. Recommended: User Id, Middle Name, Course Id, Section Id, Email, Password, Status.</p>
                        </div>
                    </div>

                    <div class="import-uploader-box">
                        <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="excel_file">OJT Internal Excel File</label>
                                <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required>
                            </div>
                            <div class="col-12 col-md-4 fm-actions">
                                <button type="submit" class="btn btn-primary">Import Excel</button>
                                <a href="ojt-internal-list.php" class="btn btn-light">View Internal List</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php if ($flashMessage !== ''): ?>
    <div id="import-toast" class="biotern-toast biotern-toast-<?php echo $flashType === 'success' ? 'success' : 'danger'; ?>">
        <div class="fw-semibold"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if ($flashDetail !== ''): ?>
            <div class="small mt-1"><?php echo htmlspecialchars($flashDetail, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var toast = document.getElementById('import-toast');
        if (!toast) {
            return;
        }
        setTimeout(function () { toast.classList.add('show'); }, 80);
        setTimeout(function () { toast.classList.remove('show'); }, 5500);
    })();
    </script>
<?php endif; ?>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>