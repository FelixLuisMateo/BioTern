<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php?next=tools/import-students-excel.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin access only.';
    exit;
}

function students_excel_csrf_token(): string
{
    $token = (string)($_SESSION['students_excel_import_csrf'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['students_excel_import_csrf'] = $token;
    }
    return $token;
}

function students_excel_header(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function students_excel_sheet_key(string $value): string
{
    return students_excel_header($value);
}

function students_excel_password(string $password): string
{
    $password = trim($password);
    if ($password === '') {
        $password = 'password123';
    }
    $info = password_get_info($password);
    if (($info['algo'] ?? 0) !== 0) {
        return $password;
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    return $hashed !== false ? $hashed : $password;
}

function students_excel_username(string $firstName, string $lastName, string $email): string
{
    $base = $email !== '' ? (string)strstr($email, '@', true) : trim($firstName . $lastName);
    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$base);
    if ($base === '') {
        $base = 'student' . time();
    }
    return substr((string)$base, 0, 255);
}

function students_excel_load_workbook(string $path, string &$errorMessage): array
{
    $errorMessage = '';
    try {
        $spreadsheet = IOFactory::load($path);
    } catch (Throwable $e) {
        $errorMessage = 'Unable to read workbook: ' . $e->getMessage();
        return [];
    }

    $sheets = [];
    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        $rows = $worksheet->toArray('', true, true, false);
        if (empty($rows)) {
            continue;
        }
        $sheetKey = students_excel_sheet_key((string)$worksheet->getTitle());
        $headerRow = array_shift($rows);
        $headers = [];
        foreach ($headerRow as $cell) {
            $headers[] = students_excel_header((string)$cell);
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            $assoc = [];
            $hasContent = false;
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $value = isset($row[$index]) ? trim((string)$row[$index]) : '';
                if ($value !== '') {
                    $hasContent = true;
                }
                $assoc[$header] = $value;
            }
            if ($hasContent) {
                $normalizedRows[] = $assoc;
            }
        }
        $sheets[$sheetKey] = $normalizedRows;
    }

    return $sheets;
}

function students_excel_find_user(mysqli $mysqli, string $email, string $username): ?array
{
    if ($email !== '') {
        $stmt = $mysqli->prepare('SELECT id, email, username FROM users WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    if ($username !== '') {
        $stmt = $mysqli->prepare('SELECT id, email, username FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    return null;
}

function students_excel_find_student(mysqli $mysqli, string $studentCode, string $email, int $userId = 0): ?array
{
    if ($studentCode !== '') {
        $stmt = $mysqli->prepare('SELECT id, user_id, student_id, email FROM students WHERE student_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $studentCode);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    if ($email !== '') {
        $stmt = $mysqli->prepare('SELECT id, user_id, student_id, email FROM students WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    if ($userId > 0) {
        $stmt = $mysqli->prepare('SELECT id, user_id, student_id, email FROM students WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    return null;
}

function students_excel_upsert_user(mysqli $mysqli, array $row, string &$errorMessage): int
{
    $firstName = trim((string)($row['first_name'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $fullName = trim((string)($row['name'] ?? trim($firstName . ' ' . $lastName)));
    $profilePicture = trim((string)($row['profile_picture'] ?? ''));
    $isActive = (int)((string)($row['is_active'] ?? '1') === '0' ? 0 : 1);
    if ($username === '') {
        $username = students_excel_username($firstName, $lastName, $email);
    }
    if ($fullName === '') {
        $fullName = $username;
    }
    $passwordHash = students_excel_password((string)($row['password'] ?? ''));
    $existing = students_excel_find_user($mysqli, $email, $username);

    if ($existing) {
        $userId = (int)($existing['id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = 'student', is_active = ?, profile_picture = ?, application_status = 'approved', updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $errorMessage = 'Failed to prepare user update: ' . $mysqli->error;
            return 0;
        }
        $stmt->bind_param('ssssisi', $fullName, $username, $email, $passwordHash, $isActive, $profilePicture, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $errorMessage = 'Failed to update user: ' . $mysqli->error;
            return 0;
        }
        return $userId;
    }

    $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, application_status, profile_picture, created_at, updated_at) VALUES (?, ?, ?, ?, 'student', ?, 'approved', ?, NOW(), NOW())");
    if (!$stmt) {
        $errorMessage = 'Failed to prepare user insert: ' . $mysqli->error;
        return 0;
    }
    $stmt->bind_param('ssssis', $fullName, $username, $email, $passwordHash, $isActive, $profilePicture);
    $ok = $stmt->execute();
    $userId = $ok ? (int)$mysqli->insert_id : 0;
    $stmt->close();
    if (!$ok || $userId <= 0) {
        $errorMessage = 'Failed to insert user: ' . $mysqli->error;
        return 0;
    }
    return $userId;
}

function students_excel_upsert_student(mysqli $mysqli, array $row, int $userId, string &$errorMessage): int
{
    $studentCode = trim((string)($row['student_id'] ?? ''));
    $firstName = trim((string)($row['first_name'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    $middleName = trim((string)($row['middle_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $passwordHash = students_excel_password((string)($row['password'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $bio = trim((string)($row['bio'] ?? ''));
    $departmentId = trim((string)($row['department_id'] ?? '0'));
    $sectionId = (int)($row['section_id'] ?? 0);
    $supervisorName = trim((string)($row['supervisor_name'] ?? ''));
    $coordinatorName = trim((string)($row['coordinator_name'] ?? ''));
    $supervisorId = (int)($row['supervisor_id'] ?? 0);
    $coordinatorId = (int)($row['coordinator_id'] ?? 0);
    $phone = trim((string)($row['phone'] ?? ''));
    $dateOfBirth = trim((string)($row['date_of_birth'] ?? ''));
    $gender = strtolower(trim((string)($row['gender'] ?? '')));
    $address = trim((string)($row['address'] ?? ''));
    $internalTotalHours = (int)($row['internal_total_hours'] ?? 0);
    $internalRemaining = (int)($row['internal_total_hours_remaining'] ?? $internalTotalHours);
    $externalTotalHours = (int)($row['external_total_hours'] ?? 0);
    $externalRemaining = (int)($row['external_total_hours_remaining'] ?? $externalTotalHours);
    $emergencyContact = trim((string)($row['emergency_contact'] ?? ''));
    $profilePicture = trim((string)($row['profile_picture'] ?? ''));
    $status = trim((string)($row['status'] ?? '1'));
    $schoolYear = trim((string)($row['school_year'] ?? ''));
    $assignmentTrack = strtolower(trim((string)($row['assignment_track'] ?? 'internal')));
    $courseId = (int)($row['course_id'] ?? 0);

    if ($studentCode === '' || $firstName === '' || $lastName === '' || $email === '' || $courseId <= 0) {
        $errorMessage = 'Missing required student fields: student_id, first_name, last_name, email, course_id.';
        return 0;
    }
    if ($username === '') {
        $username = students_excel_username($firstName, $lastName, $email);
    }
    if (!in_array($gender, ['male', 'female', 'other', ''], true)) {
        $gender = '';
    }
    if (!in_array($assignmentTrack, ['internal', 'external'], true)) {
        $assignmentTrack = 'internal';
    }
    if ($schoolYear === '') {
        $schoolYear = date('Y') . '-' . (date('Y') + 1);
    }

    $existing = students_excel_find_student($mysqli, $studentCode, $email, $userId);
    if ($existing) {
        $studentPk = (int)($existing['id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE students SET user_id = ?, course_id = ?, student_id = ?, first_name = ?, last_name = ?, middle_name = ?, username = ?, password = ?, email = ?, bio = ?, department_id = ?, section_id = ?, supervisor_name = ?, coordinator_name = ?, supervisor_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), phone = ?, date_of_birth = NULLIF(?, ''), gender = NULLIF(?, ''), address = ?, internal_total_hours = ?, internal_total_hours_remaining = ?, external_total_hours = ?, external_total_hours_remaining = ?, emergency_contact = ?, profile_picture = ?, status = ?, school_year = ?, assignment_track = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $errorMessage = 'Failed to prepare student update: ' . $mysqli->error;
            return 0;
        }
        $stmt->bind_param('iissssssssiissiisssiiiissssi', $userId, $courseId, $studentCode, $firstName, $lastName, $middleName, $username, $passwordHash, $email, $bio, $departmentId, $sectionId, $supervisorName, $coordinatorName, $supervisorId, $coordinatorId, $phone, $dateOfBirth, $gender, $address, $internalTotalHours, $internalRemaining, $externalTotalHours, $externalRemaining, $emergencyContact, $profilePicture, $status, $schoolYear, $assignmentTrack, $studentPk);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $errorMessage = 'Failed to update student: ' . $mysqli->error;
            return 0;
        }
        return $studentPk;
    }

    $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, bio, department_id, section_id, supervisor_name, coordinator_name, supervisor_id, coordinator_id, phone, date_of_birth, gender, address, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining, emergency_contact, profile_picture, status, school_year, assignment_track, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        $errorMessage = 'Failed to prepare student insert: ' . $mysqli->error;
        return 0;
    }
    $stmt->bind_param('iissssssssiissiisssiiiissss', $userId, $courseId, $studentCode, $firstName, $lastName, $middleName, $username, $passwordHash, $email, $bio, $departmentId, $sectionId, $supervisorName, $coordinatorName, $supervisorId, $coordinatorId, $phone, $dateOfBirth, $gender, $address, $internalTotalHours, $internalRemaining, $externalTotalHours, $externalRemaining, $emergencyContact, $profilePicture, $status, $schoolYear, $assignmentTrack);
    $ok = $stmt->execute();
    $studentPk = $ok ? (int)$mysqli->insert_id : 0;
    $stmt->close();
    if (!$ok || $studentPk <= 0) {
        $errorMessage = 'Failed to insert student: ' . $mysqli->error;
        return 0;
    }
    return $studentPk;
}

function students_excel_import_documents(mysqli $mysqli, array $rows, array &$summary, array &$errors): void
{
    foreach ($rows as $index => $row) {
        $studentCode = trim((string)($row['student_id'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $documentType = trim((string)($row['document_type'] ?? ''));
        $fileName = trim((string)($row['file_name'] ?? ''));
        $filePath = trim((string)($row['file_path'] ?? ''));
        $description = trim((string)($row['description'] ?? ''));
        $fileType = trim((string)($row['file_type'] ?? ''));
        $fileSize = (int)($row['file_size'] ?? 0);

        if ($documentType === '' || ($studentCode === '' && $email === '')) {
            $errors[] = 'Documents row ' . ($index + 2) . ': missing student_id/email or document_type.';
            continue;
        }

        $student = students_excel_find_student($mysqli, $studentCode, $email);
        if (!$student) {
            $errors[] = 'Documents row ' . ($index + 2) . ': student not found.';
            continue;
        }

        $studentPk = (int)($student['id'] ?? 0);
        $existingId = 0;
        if ($fileName !== '') {
            $stmt = $mysqli->prepare('SELECT id FROM documents WHERE student_id = ? AND document_type = ? AND file_name = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('iss', $studentPk, $documentType, $fileName);
                $stmt->execute();
                $rowFound = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $existingId = (int)($rowFound['id'] ?? 0);
            }
        }

        if ($existingId > 0) {
            $stmt = $mysqli->prepare('UPDATE documents SET file_path = ?, description = ?, file_type = ?, file_size = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('sssii', $filePath, $description, $fileType, $fileSize, $existingId);
                if ($stmt->execute()) {
                    $summary['documents_updated']++;
                } else {
                    $errors[] = 'Documents row ' . ($index + 2) . ': failed to update document.';
                }
                $stmt->close();
            }
            continue;
        }

        $stmt = $mysqli->prepare('INSERT INTO documents (student_id, document_type, file_path, file_name, uploaded_at, created_at, updated_at, file_type, file_size, description) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('issssis', $studentPk, $documentType, $filePath, $fileName, $fileType, $fileSize, $description);
            if ($stmt->execute()) {
                $summary['documents_inserted']++;
            } else {
                $errors[] = 'Documents row ' . ($index + 2) . ': failed to insert document.';
            }
            $stmt->close();
        }
    }
}

function students_excel_import_workbook(mysqli $mysqli, string $path, array &$summary, array &$errors, string &$message): bool
{
    $loadError = '';
    $sheets = students_excel_load_workbook($path, $loadError);
    if ($loadError !== '') {
        $message = $loadError;
        return false;
    }

    $studentsRows = $sheets['students'] ?? ($sheets['student_database'] ?? []);
    $documentsRows = $sheets['documents'] ?? [];
    if (empty($studentsRows)) {
        $message = 'Workbook must contain a sheet named Students or student_database.';
        return false;
    }

    $summary = ['users_upserted' => 0, 'students_upserted' => 0, 'documents_inserted' => 0, 'documents_updated' => 0];
    foreach ($studentsRows as $index => $row) {
        $rowError = '';
        $userId = students_excel_upsert_user($mysqli, $row, $rowError);
        if ($userId <= 0) {
            $errors[] = 'Students row ' . ($index + 2) . ': ' . $rowError;
            continue;
        }
        $summary['users_upserted']++;

        $studentPk = students_excel_upsert_student($mysqli, $row, $userId, $rowError);
        if ($studentPk <= 0) {
            $errors[] = 'Students row ' . ($index + 2) . ': ' . $rowError;
            continue;
        }
        $summary['students_upserted']++;
    }

    if (!empty($documentsRows)) {
        students_excel_import_documents($mysqli, $documentsRows, $summary, $errors);
    }

    $message = 'Excel import finished. Users upserted: ' . $summary['users_upserted'] . '. Students upserted: ' . $summary['students_upserted'] . '.';
    if (!empty($documentsRows)) {
        $message .= ' Documents inserted: ' . $summary['documents_inserted'] . '. Documents updated: ' . $summary['documents_updated'] . '.';
    }
    if (!empty($errors)) {
        $message .= ' Some rows need review.';
    }
    return $summary['students_upserted'] > 0;
}

$statusType = '';
$statusMessage = '';
$statusDetails = [];
$csrfToken = students_excel_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $statusType = 'danger';
        $statusMessage = 'Invalid security token. Refresh the page and try again.';
    } elseif (!isset($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
        $statusType = 'danger';
        $statusMessage = 'Choose an Excel workbook first.';
    } else {
        $uploadError = (int)($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $statusType = 'danger';
            $statusMessage = 'Excel upload failed. Error code: ' . $uploadError;
        } else {
            $tmpName = (string)($_FILES['excel_file']['tmp_name'] ?? '');
            $isValidTempFile = $tmpName !== '' && (is_uploaded_file($tmpName) || is_file($tmpName));
            if (!$isValidTempFile) {
                $statusType = 'danger';
                $statusMessage = 'Uploaded Excel file is invalid.';
            } else {
                $summary = [];
                $errors = [];
                $message = '';
                $ok = students_excel_import_workbook($conn, $tmpName, $summary, $errors, $message);
                $statusType = $ok ? 'success' : 'danger';
                $statusMessage = $message !== '' ? $message : ($ok ? 'Excel import completed.' : 'Excel import failed.');
                foreach ($summary as $label => $value) {
                    $statusDetails[] = ucwords(str_replace('_', ' ', (string)$label)) . ': ' . (int)$value;
                }
                foreach (array_slice($errors, 0, 8) as $line) {
                    $statusDetails[] = $line;
                }
                if (count($errors) > 8) {
                    $statusDetails[] = 'Additional row issues not shown: ' . (count($errors) - 8);
                }
            }
        }
    }
}

$page_title = 'Excel Student Database Import';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
.excel-import-shell{max-width:1120px;margin:0 auto}.excel-import-hero{background:linear-gradient(135deg,#195b42 0%,#2f855a 50%,#e0f3e8 100%);color:#fff;border:0}.excel-import-card{border:1px solid #e5ebf2;border-radius:1rem;box-shadow:0 18px 40px rgba(16,24,40,.06)}.excel-import-step{border:1px solid #edf1f5;border-radius:1rem;background:#fbfcfe;padding:1rem}.excel-import-badge{display:inline-flex;padding:.35rem .75rem;border-radius:999px;background:#eefbf3;color:#195b42;font-weight:700;font-size:.8rem}.app-skin-dark .excel-import-card{background:#16202b;border-color:#253243;box-shadow:0 18px 40px rgba(0,0,0,.35)}.app-skin-dark .excel-import-card h4,.app-skin-dark .excel-import-card h5,.app-skin-dark .excel-import-card h6,.app-skin-dark .excel-import-card label,.app-skin-dark .excel-import-card p,.app-skin-dark .excel-import-card li,.app-skin-dark .excel-import-card .form-text{color:#eaf2fb}.app-skin-dark .excel-import-card .text-muted{color:#aab8c5!important}.app-skin-dark .excel-import-step{background:#1b2632;border-color:#2a394b}.app-skin-dark .excel-import-badge{background:rgba(53,180,104,.14);color:#9ff0be}.app-skin-dark .form-control{background:#0f1720;border-color:#334354;color:#edf4fb}
</style>
<div class="container-xxl py-4">
    <div class="excel-import-shell">
        <?php if ($statusType !== ''): ?><div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> mb-4"><strong><?php echo $statusType === 'success' ? 'Success:' : 'Import error:'; ?></strong> <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($statusDetails)): ?><div class="small mt-2"><?php foreach ($statusDetails as $detail): ?><div><?php echo htmlspecialchars((string)$detail, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?></div><?php endif; ?>
        <div class="card excel-import-hero mb-4"><div class="card-body p-4 p-md-5"><span class="excel-import-badge">Separate Excel Workflow</span><h2 class="mt-3 mb-2 text-white">Import student database information from Excel without using SQL replace logic</h2><p class="mb-0 text-white-50">This page is separate on purpose so workbook-based student imports do not get mixed into the SQL replace workflow.</p></div></div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card excel-import-card"><div class="card-body p-4 p-md-5"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3"><div><span class="excel-import-badge">Import Workbook</span><h4 class="mt-3 mb-2">Students sheet first, Documents sheet optional</h4><p class="text-muted mb-0">Use one workbook with a sheet named <code>Students</code>. If you also want document metadata imported, add a second sheet named <code>Documents</code>.</p></div><a href="import-sql.php" class="btn btn-light">Back to SQL Tools</a></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><div class="mb-3"><label for="excel_file" class="form-label fw-semibold">Upload Excel workbook</label><input class="form-control" type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"><div class="form-text">Recommended workbook sheets: <code>Students</code> and optional <code>Documents</code>.</div></div><div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Import Excel Database</button><a href="/BioTern_unified/management/students-edit.php" class="btn btn-outline-primary">Review Students</a></div></form></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card excel-import-card mb-4"><div class="card-body"><span class="excel-import-badge">Workbook Rules</span><div class="excel-import-step mt-3"><h6>Students sheet required</h6><p class="text-muted mb-0">Required columns for new students: <code>student_id</code>, <code>first_name</code>, <code>last_name</code>, <code>email</code>, <code>course_id</code>.</p></div><div class="excel-import-step mt-3"><h6>Documents sheet optional</h6><p class="text-muted mb-0">Use columns like <code>student_id</code> or <code>email</code>, plus <code>document_type</code>, <code>file_name</code>, <code>file_path</code>, <code>description</code>.</p></div></div></div>
                <div class="card excel-import-card"><div class="card-body"><span class="excel-import-badge">Localhost Review</span><h5 class="mt-3 mb-2">Open this on localhost</h5><p class="text-muted mb-2">Review this tool locally before pushing changes.</p><div class="small"><div><code>http://localhost/BioTern/BioTern_unified/tools/import-students-excel.php</code></div></div></div></div>
            </div>
        </div>
        <div class="card excel-import-card mt-4"><div class="card-body p-4 p-md-5"><span class="excel-import-badge">Suggested Columns</span><div class="row g-4 mt-1"><div class="col-md-6"><h6>Students sheet columns</h6><p class="text-muted mb-0"><code>student_id</code>, <code>first_name</code>, <code>last_name</code>, <code>middle_name</code>, <code>email</code>, <code>username</code>, <code>password</code>, <code>course_id</code>, <code>department_id</code>, <code>section_id</code>, <code>phone</code>, <code>date_of_birth</code>, <code>gender</code>, <code>address</code>, <code>internal_total_hours</code>, <code>internal_total_hours_remaining</code>, <code>external_total_hours</code>, <code>external_total_hours_remaining</code>, <code>emergency_contact</code>, <code>school_year</code>, <code>assignment_track</code>, <code>status</code>, <code>profile_picture</code>, <code>supervisor_id</code>, <code>supervisor_name</code>, <code>coordinator_id</code>, <code>coordinator_name</code>.</p></div><div class="col-md-6"><h6>Documents sheet columns</h6><p class="text-muted mb-0"><code>student_id</code> or <code>email</code>, <code>document_type</code>, <code>file_name</code>, <code>file_path</code>, <code>description</code>, <code>file_type</code>, <code>file_size</code>. This imports document metadata only, not physical upload file contents.</p></div></div></div></div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
