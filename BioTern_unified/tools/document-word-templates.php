<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php?next=tools/document-word-templates.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin access only.';
    exit;
}

function word_template_csrf_token(): string
{
    $token = (string)($_SESSION['word_template_csrf'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['word_template_csrf'] = $token;
    }
    return $token;
}

function word_template_types(): array
{
    return [
        'application' => ['label' => 'Application', 'table' => 'application_letter'],
        'endorsement' => ['label' => 'Endorsement', 'table' => 'endorsement_letter'],
        'moa' => ['label' => 'MOA', 'table' => 'moa'],
        'dau_moa' => ['label' => 'DAU MOA', 'table' => 'dau_moa'],
    ];
}

function word_template_dirs(): array
{
    static $resolved = null;
    if (is_array($resolved)) {
        return $resolved;
    }

    $projectBase = dirname(__DIR__) . '/uploads/word_templates';
    $projectGenerated = dirname(__DIR__) . '/uploads/generated_word_documents';
    $projectRoot = dirname(__DIR__) . '/uploads';

    if (is_dir($projectBase) || (is_dir($projectRoot) && is_writable($projectRoot) && @mkdir($projectBase, 0755, true))) {
        if (!is_dir($projectGenerated) && is_dir($projectRoot) && is_writable($projectRoot)) {
            @mkdir($projectGenerated, 0755, true);
        }
        if (is_dir($projectBase) && is_dir($projectGenerated)) {
            $resolved = [$projectBase, $projectGenerated];
            return $resolved;
        }
    }

    $runtimeRoot = rtrim((string)sys_get_temp_dir(), '\\/') . '/biotern_word_templates';
    $runtimeBase = $runtimeRoot . '/templates';
    $runtimeGenerated = $runtimeRoot . '/generated';

    if (!is_dir($runtimeBase)) {
        @mkdir($runtimeBase, 0755, true);
    }
    if (!is_dir($runtimeGenerated)) {
        @mkdir($runtimeGenerated, 0755, true);
    }

    $resolved = [$runtimeBase, $runtimeGenerated];
    return $resolved;
}

function word_template_uses_runtime_storage(): bool
{
    [$base] = word_template_dirs();
    return strpos(str_replace('\\', '/', $base), str_replace('\\', '/', rtrim((string)sys_get_temp_dir(), '\\/'))) === 0;
}

function word_template_path(string $type): string
{
    [$base] = word_template_dirs();
    return $base . '/' . $type . '.docx';
}

function word_template_current_files(): array
{
    $files = [];
    foreach (array_keys(word_template_types()) as $type) {
        $path = word_template_path($type);
        $files[$type] = is_file($path) ? basename($path) . ' (' . date('Y-m-d H:i', (int)filemtime($path)) . ')' : 'No template uploaded';
    }
    return $files;
}

function word_template_render_value($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function word_template_first_non_empty(array $data, array $keys, string $fallback = ''): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $value = trim((string)$data[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return $fallback;
}

function word_template_blank_profiles(): array
{
    return [
        'application' => [
            ['label' => 'Letter date', 'keys' => ['application_date', 'date', 'generated_date']],
            ['label' => 'Recipient name', 'keys' => ['application_person', 'ap_name', 'recipient_name', 'contact_person_name']],
            ['label' => 'Recipient position', 'keys' => ['application_position', 'ap_position', 'position', 'recipient_position']],
            ['label' => 'Company name', 'keys' => ['company_name', 'ap_company', 'partner_name']],
            ['label' => 'Company address', 'keys' => ['company_address', 'ap_address', 'partner_address']],
            ['label' => 'Student name in body', 'keys' => ['full_name']],
            ['label' => 'Student signature name', 'keys' => ['full_name']],
            ['label' => 'Student home address', 'keys' => ['student_address_line', 'address', 'full_address']],
            ['label' => 'Student contact number', 'keys' => ['student_contact', 'phone', 'contact_no', 'mobile_number']],
        ],
        'endorsement' => [
            ['label' => 'Recipient name', 'keys' => ['recipient_name', 'application_person', 'ap_name']],
            ['label' => 'Recipient position', 'keys' => ['recipient_position', 'application_position', 'position']],
            ['label' => 'Company name', 'keys' => ['company_name', 'ap_company', 'partner_name']],
            ['label' => 'Company address', 'keys' => ['company_address', 'ap_address', 'partner_address']],
            ['label' => 'Student list line', 'keys' => ['endorsement_students', 'full_name']],
        ],
        'moa' => [
            ['label' => 'Partner company name', 'keys' => ['partner_company_name', 'company_name', 'partner_name']],
            ['label' => 'Partner address', 'keys' => ['partner_address', 'company_address']],
            ['label' => 'Partner representative in body', 'keys' => ['partner_representative', 'partner_rep']],
            ['label' => 'Company receipt or reference', 'keys' => ['company_receipt', 'receipt_no', 'reference_no']],
            ['label' => 'Signing place', 'keys' => ['signed_at', 'moa_address']],
            ['label' => 'Signing day', 'keys' => ['signed_day']],
            ['label' => 'Signing month', 'keys' => ['signed_month']],
            ['label' => 'Signing year', 'keys' => ['signed_year']],
            ['label' => 'Partner representative signature', 'keys' => ['partner_representative', 'partner_rep']],
            ['label' => 'Partner representative position', 'keys' => ['partner_position']],
            ['label' => 'School representative signature', 'keys' => ['school_representative', 'school_rep']],
            ['label' => 'School representative position', 'keys' => ['school_position']],
            ['label' => 'Witness for partner side', 'keys' => ['presence_partner_rep']],
            ['label' => 'School administrator witness', 'keys' => ['presence_school_admin', 'school_admin_name']],
            ['label' => 'School administrator position', 'keys' => ['presence_school_admin_position', 'school_admin_position']],
            ['label' => 'Notary city', 'keys' => ['notary_city']],
            ['label' => 'Notary appeared name', 'keys' => ['notary_appeared_1', 'partner_representative', 'partner_rep']],
            ['label' => 'Notary day', 'keys' => ['notary_day']],
            ['label' => 'Notary month', 'keys' => ['notary_month']],
            ['label' => 'Notary year', 'keys' => ['notary_year']],
            ['label' => 'Notary place', 'keys' => ['notary_place']],
            ['label' => 'Document number', 'keys' => ['doc_no']],
            ['label' => 'Page number', 'keys' => ['page_no']],
            ['label' => 'Book number', 'keys' => ['book_no']],
            ['label' => 'Series number', 'keys' => ['series_no']],
        ],
        'dau_moa' => [
            ['label' => 'Barangay name', 'keys' => ['partner_company_name', 'company_name', 'partner_name']],
            ['label' => 'Barangay address', 'keys' => ['partner_address', 'company_address']],
            ['label' => 'Barangay representative in body', 'keys' => ['partner_representative', 'partner_rep']],
            ['label' => 'Barangay receipt or reference', 'keys' => ['company_receipt', 'receipt_no', 'reference_no']],
            ['label' => 'Signing place', 'keys' => ['signed_at', 'moa_address']],
            ['label' => 'Signing day', 'keys' => ['signed_day']],
            ['label' => 'Signing month', 'keys' => ['signed_month']],
            ['label' => 'Signing year', 'keys' => ['signed_year']],
            ['label' => 'Barangay representative signature', 'keys' => ['partner_representative', 'partner_rep']],
            ['label' => 'Barangay representative position', 'keys' => ['partner_position']],
            ['label' => 'School representative signature', 'keys' => ['school_representative', 'school_rep']],
            ['label' => 'School representative position', 'keys' => ['school_position']],
            ['label' => 'Witness for barangay side', 'keys' => ['presence_partner_rep']],
            ['label' => 'School administrator witness', 'keys' => ['presence_school_admin', 'school_admin_name']],
            ['label' => 'School administrator position', 'keys' => ['presence_school_admin_position', 'school_admin_position']],
            ['label' => 'Notary city', 'keys' => ['notary_city']],
            ['label' => 'Notary appeared name', 'keys' => ['notary_appeared_1', 'partner_representative', 'partner_rep']],
            ['label' => 'Notary day', 'keys' => ['notary_day']],
            ['label' => 'Notary month', 'keys' => ['notary_month']],
            ['label' => 'Notary year', 'keys' => ['notary_year']],
            ['label' => 'Notary place', 'keys' => ['notary_place']],
            ['label' => 'Document number', 'keys' => ['doc_no']],
            ['label' => 'Page number', 'keys' => ['page_no']],
            ['label' => 'Book number', 'keys' => ['book_no']],
            ['label' => 'Series number', 'keys' => ['series_no']],
        ],
    ];
}

function word_template_auto_blank_values(string $type, array $data): array
{
    $profiles = word_template_blank_profiles();
    $fields = $profiles[$type] ?? [];
    $values = [];
    foreach ($fields as $field) {
        $fallback = '';
        if (($field['label'] ?? '') === 'Signing day' || ($field['label'] ?? '') === 'Notary day') {
            $fallback = date('d');
        } elseif (($field['label'] ?? '') === 'Signing month' || ($field['label'] ?? '') === 'Notary month') {
            $fallback = date('F');
        } elseif (($field['label'] ?? '') === 'Signing year' || ($field['label'] ?? '') === 'Notary year') {
            $fallback = date('Y');
        }
        $values[] = word_template_first_non_empty($data, (array)($field['keys'] ?? []), $fallback);
    }
    return $values;
}

function word_template_blank_profile_lines(string $type): array
{
    $profiles = word_template_blank_profiles();
    $fields = $profiles[$type] ?? [];
    $lines = [];
    foreach ($fields as $index => $field) {
        $lines[] = ($index + 1) . '. ' . (string)($field['label'] ?? 'Blank field');
    }
    return $lines;
}

function word_template_replace_underscore_blanks(string $xml, array $orderedValues): string
{
    $index = 0;
    return preg_replace_callback('/_{3,}/', function (array $matches) use (&$index, $orderedValues) {
        $replacement = (string)($orderedValues[$index] ?? '');
        $index++;
        if ($replacement === '') {
            return $matches[0];
        }
        return word_template_render_value($replacement);
    }, $xml) ?? $xml;
}

function word_template_replace_docx(string $templatePath, string $outputPath, array $data, string &$errorMessage): bool
{
    $errorMessage = '';
    if (!copy($templatePath, $outputPath)) {
        $errorMessage = 'Failed to copy the template file.';
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($outputPath) !== true) {
        $errorMessage = 'Failed to open the generated DOCX file.';
        return false;
    }

    $targets = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if ($name === 'word/document.xml' || preg_match('~^word/(header|footer)[0-9]+\.xml$~', $name)) {
            $targets[] = $name;
        }
    }

    foreach ($targets as $name) {
        $xml = (string)$zip->getFromName($name);
        foreach ($data as $key => $value) {
            $safeValue = word_template_render_value($value);
            $xml = str_replace(['{{' . $key . '}}', '${' . $key . '}'], $safeValue, $xml);
        }
        $xml = word_template_replace_underscore_blanks($xml, word_template_auto_blank_values((string)($data['document_type'] ?? ''), $data));
        $zip->addFromString($name, $xml);
    }

    $zip->close();
    return true;
}

function word_template_student_list(mysqli $mysqli): array
{
    $rows = [];
    $result = $mysqli->query("SELECT id, student_id, first_name, middle_name, last_name FROM students ORDER BY first_name ASC, last_name ASC");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

function word_template_document_data(mysqli $mysqli, string $type, int $studentId): array
{
    $types = word_template_types();
    $table = (string)($types[$type]['table'] ?? '');
    if ($table === '') {
        return [];
    }

    if ($type === 'dau_moa') {
        $exists = $mysqli->query("SHOW TABLES LIKE 'dau_moa'");
        if (!$exists || $exists->num_rows === 0) {
            $table = 'moa';
        }
        if ($exists instanceof mysqli_result) {
            $exists->free();
        }
    }

    $stmt = $mysqli->prepare("SELECT * FROM `{$table}` WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? $row : [];
}

function word_template_merge_data(mysqli $mysqli, string $type, int $studentId): array
{
    $stmt = $mysqli->prepare("SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($student)) {
        return [];
    }

    $document = word_template_document_data($mysqli, $type, $studentId);
    $data = [];
    foreach ($student as $key => $value) {
        $data[$key] = $value;
        $data['student_' . $key] = $value;
    }
    foreach ($document as $key => $value) {
        $data[$key] = $value;
        $data['doc_' . $key] = $value;
    }
    $data['full_name'] = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
    $data['student_address_line'] = word_template_first_non_empty($data, ['address', 'home_address', 'current_address', 'street_address', 'full_address']);
    $data['student_contact'] = word_template_first_non_empty($data, ['phone', 'contact_no', 'mobile_number', 'telephone', 'contact_number']);
    $data['application_date'] = word_template_first_non_empty($data, ['date', 'application_date'], date('F j, Y'));
    $data['application_person'] = word_template_first_non_empty($data, ['application_person', 'recipient_name', 'name']);
    $data['application_position'] = word_template_first_non_empty($data, ['application_position', 'position', 'recipient_position']);
    $data['company_name'] = word_template_first_non_empty($data, ['company_name', 'company', 'partner_company_name', 'partner_name', 'barangay_name']);
    $data['company_address'] = word_template_first_non_empty($data, ['company_address', 'address_company', 'partner_address', 'barangay_address']);
    $data['partner_company_name'] = word_template_first_non_empty($data, ['partner_company_name', 'company_name', 'partner_name', 'barangay_name']);
    $data['partner_representative'] = word_template_first_non_empty($data, ['partner_representative', 'partner_rep', 'representative_name']);
    $data['school_representative'] = word_template_first_non_empty($data, ['school_representative', 'school_rep'], 'MR. JOMAR G. SANGIL');
    $data['school_position'] = word_template_first_non_empty($data, ['school_position'], 'ICT DEPARTMENT HEAD');
    $data['presence_school_admin'] = word_template_first_non_empty($data, ['presence_school_admin', 'school_admin_name'], 'MR. ROSS CARVEL C. RAMIREZ');
    $data['presence_school_admin_position'] = word_template_first_non_empty($data, ['presence_school_admin_position', 'school_admin_position'], 'HEAD OF ACADEMIC AFFAIRS');
    $data['endorsement_students'] = $data['full_name'];
    $data['document_type'] = (string)($type);
    $data['generated_date'] = date('F j, Y');
    $data['generated_datetime'] = date('Y-m-d H:i:s');
    return $data;
}

$statusType = '';
$statusMessage = '';
$statusDetails = [];
$csrfToken = word_template_csrf_token();
$templateFiles = word_template_current_files();
$students = word_template_student_list($conn);
$selectedType = strtolower(trim((string)($_POST['template_type'] ?? $_GET['template_type'] ?? 'application')));
$selectedStudentId = (int)($_POST['student_id'] ?? 0);
$usingRuntimeStorage = word_template_uses_runtime_storage();

if (!isset(word_template_types()[$selectedType])) {
    $selectedType = 'application';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $statusType = 'danger';
        $statusMessage = 'Invalid security token. Refresh and try again.';
    } else {
        $mode = strtolower(trim((string)($_POST['mode'] ?? '')));
        if ($mode === 'upload_template') {
            if (!isset($_FILES['template_file']) || !is_array($_FILES['template_file'])) {
                $statusType = 'danger';
                $statusMessage = 'Choose a Word template file first.';
            } else {
                $uploadError = (int)($_FILES['template_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                $originalName = (string)($_FILES['template_file']['name'] ?? '');
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $tmpName = (string)($_FILES['template_file']['tmp_name'] ?? '');
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $statusType = 'danger';
                    $statusMessage = 'Template upload failed. Error code: ' . $uploadError;
                } elseif ($extension !== 'docx') {
                    $statusType = 'danger';
                    $statusMessage = 'Only .docx templates are supported.';
                } elseif ($tmpName === '' || !(is_uploaded_file($tmpName) || is_file($tmpName))) {
                    $statusType = 'danger';
                    $statusMessage = 'Uploaded template file is invalid.';
                } else {
                    $targetPath = word_template_path($selectedType);
                    if (move_uploaded_file($tmpName, $targetPath) || rename($tmpName, $targetPath)) {
                        $statusType = 'success';
                        $statusMessage = 'Template uploaded successfully for ' . word_template_types()[$selectedType]['label'] . '.';
                        $templateFiles = word_template_current_files();
                    } else {
                        $statusType = 'danger';
                        $statusMessage = 'Failed to save the uploaded template.';
                    }
                }
            }
        } elseif ($mode === 'generate_document') {
            $templatePath = word_template_path($selectedType);
            if (!is_file($templatePath)) {
                $statusType = 'danger';
                $statusMessage = 'Upload a Word template first for this document type.';
            } elseif ($selectedStudentId <= 0) {
                $statusType = 'danger';
                $statusMessage = 'Choose a student before generating the Word document.';
            } else {
                $data = word_template_merge_data($conn, $selectedType, $selectedStudentId);
                if (empty($data)) {
                    $statusType = 'danger';
                    $statusMessage = 'Unable to load the student or document data.';
                } else {
                    [, $generatedDir] = word_template_dirs();
                    $fileName = $selectedType . '-template-' . $selectedStudentId . '-' . date('Ymd-His') . '.docx';
                    $outputPath = $generatedDir . '/' . $fileName;
                    $errorMessage = '';
                    if (!word_template_replace_docx($templatePath, $outputPath, $data, $errorMessage)) {
                        $statusType = 'danger';
                        $statusMessage = 'Failed to generate the document. ' . $errorMessage;
                    } else {
                        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
                        header('Content-Length: ' . filesize($outputPath));
                        readfile($outputPath);
                        exit;
                    }
                }
            }
        }
    }
}

$page_title = 'Word Document Templates';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
.word-template-shell{max-width:1150px;margin:0 auto}.word-template-hero{background:linear-gradient(135deg,#5b3a1f 0%,#8a5c2f 48%,#f5e7d6 100%);color:#fff;border:0}.word-template-card{border:1px solid #e5ebf2;border-radius:1rem;box-shadow:0 18px 40px rgba(16,24,40,.06)}.word-template-badge{display:inline-flex;padding:.35rem .75rem;border-radius:999px;background:#fff2e2;color:#7a4517;font-weight:700;font-size:.8rem}.word-template-profile{background:#fff7ed;border:1px solid #fed7aa;border-radius:.9rem;padding:1rem 1.1rem}.word-template-profile ol{margin:0;padding-left:1.2rem}.app-skin-dark .word-template-card{background:#16202b;border-color:#253243;box-shadow:0 18px 40px rgba(0,0,0,.35)}.app-skin-dark .word-template-card h4,.app-skin-dark .word-template-card h5,.app-skin-dark .word-template-card h6,.app-skin-dark .word-template-card label,.app-skin-dark .word-template-card p,.app-skin-dark .word-template-card li,.app-skin-dark .word-template-card .form-text,.app-skin-dark .word-template-card code{color:#eaf2fb}.app-skin-dark .word-template-card .text-muted{color:#aab8c5!important}.app-skin-dark .word-template-badge{background:rgba(255,180,84,.16);color:#ffd39a}.app-skin-dark .word-template-profile{background:rgba(133,77,14,.18);border-color:#8a5a17}.app-skin-dark .form-control,.app-skin-dark .form-select{background:#0f1720;border-color:#334354;color:#edf4fb}
</style>
<div class="container-xxl py-4">
    <div class="word-template-shell">
        <?php if ($statusType !== ''): ?>
            <div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> mb-4">
                <strong><?php echo $statusType === 'success' ? 'Success:' : 'Error:'; ?></strong>
                <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($statusDetails)): ?>
                    <div class="small mt-2">
                        <?php foreach ($statusDetails as $detail): ?>
                            <div><?php echo htmlspecialchars((string)$detail, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card word-template-hero mb-4">
            <div class="card-body p-4 p-md-5">
                <span class="word-template-badge">Actual Word Template Workflow</span>
                <h2 class="mt-3 mb-2 text-white">Upload real Microsoft Word templates for printing-ready documents</h2>
                <p class="mb-0 text-white-50">This adds a separate DOCX template workflow for Application, Endorsement, MOA, and DAU MOA. Your current document pages stay untouched.</p>
            </div>
        </div>

        <?php if ($usingRuntimeStorage): ?>
            <div class="alert alert-warning mb-4">
                <strong>Temporary storage mode:</strong>
                This deployment is using runtime temp storage because Vercel is read-only. Uploads work without filesystem warnings, but uploaded templates may not persist after a cold restart or redeploy.
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card word-template-card">
                    <div class="card-body p-4 p-md-5">
                        <span class="word-template-badge">Upload Template</span>
                        <h4 class="mt-3 mb-2">Store one DOCX template per document type</h4>
                        <p class="text-muted mb-4">You can still use placeholders like <code>{{first_name}}</code> or <code>{{company_name}}</code>, but you do not have to. This tool can also auto-fill underscore blanks like <code>__________</code> based on the current Application, Endorsement, MOA, and DAU MOA document layouts.</p>

                        <form method="post" enctype="multipart/form-data" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="mode" value="upload_template">
                            <div class="mb-3">
                                <label for="template_type" class="form-label fw-semibold">Document type</label>
                                <select class="form-select" id="template_type" name="template_type">
                                    <?php foreach (word_template_types() as $key => $meta): ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedType === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$meta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="template_file" class="form-label fw-semibold">Word template file</label>
                                <input class="form-control" type="file" id="template_file" name="template_file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                <div class="form-text">Only `.docx` is supported for placeholder replacement.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload Template</button>
                        </form>

                        <h5 class="mb-3">Installed templates</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Type</th><th>Template</th></tr></thead>
                                <tbody>
                                <?php foreach (word_template_types() as $key => $meta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$meta['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($templateFiles[$key] ?? 'No template uploaded'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card word-template-card mb-4">
                    <div class="card-body p-4 p-md-5">
                        <span class="word-template-badge">Generate DOCX</span>
                        <h4 class="mt-3 mb-2">Create a filled Word document from the uploaded template</h4>
                        <p class="text-muted mb-4">This downloads a `.docx` using database values from the student record and the saved document-specific table.</p>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="mode" value="generate_document">
                            <div class="mb-3">
                                <label for="generate_template_type" class="form-label fw-semibold">Document type</label>
                                <select class="form-select" id="generate_template_type" name="template_type">
                                    <?php foreach (word_template_types() as $key => $meta): ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedType === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$meta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="student_id" class="form-label fw-semibold">Student</label>
                                <select class="form-select" id="student_id" name="student_id">
                                    <option value="0">Choose a student</option>
                                    <?php foreach ($students as $student): ?>
                                        <?php $label = trim((string)(($student['first_name'] ?? '') . ' ' . (($student['middle_name'] ?? '') !== '' ? ($student['middle_name'] . ' ') : '') . ($student['last_name'] ?? ''))); ?>
                                        <option value="<?php echo (int)($student['id'] ?? 0); ?>" <?php echo $selectedStudentId === (int)($student['id'] ?? 0) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label . ' - ' . (string)($student['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Generate Word Document</button>
                        </form>
                    </div>
                </div>

                <div class="card word-template-card">
                    <div class="card-body p-4 p-md-5">
                        <span class="word-template-badge">Localhost Review</span>
                        <h5 class="mt-3 mb-2">Open this tool locally</h5>
                        <p class="text-muted mb-2">You can review it first before pushing anything.</p>
                        <div><code>http://localhost/BioTern/BioTern_unified/tools/document-word-templates.php</code></div>
                        <hr>
                        <h6>How auto-fill works</h6>
                        <p class="text-muted">If your Word template contains underscore blanks, the generator now follows a document-specific profile based on the old built-in document page for that type.</p>
                        <div class="word-template-profile">
                            <h6 class="mb-2">Blank order for <?php echo htmlspecialchars((string)(word_template_types()[$selectedType]['label'] ?? 'this document'), ENT_QUOTES, 'UTF-8'); ?></h6>
                            <ol class="small">
                                <?php foreach (word_template_blank_profile_lines($selectedType) as $line): ?>
                                    <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <p class="text-muted mt-3 mb-0">For special templates where a blank should go somewhere unusual, placeholders still work too: <code>{{full_name}}</code>, <code>{{student_id}}</code>, <code>{{course_name}}</code>, <code>{{generated_date}}</code>, <code>{{student_first_name}}</code>, <code>{{doc_company_name}}</code>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
