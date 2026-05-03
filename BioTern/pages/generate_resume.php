<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

function resume_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function resolve_resume_profile_image_url(string $profilePath, int $userId = 0): ?string
{
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    return $resolved !== '' ? $resolved : null;
}

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    die('Invalid student id');
}

$query = "
    SELECT
        s.*,
        c.name AS course_name,
        d.name AS department_name,
        sec.code AS section_code,
        sec.name AS section_name,
        COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        i.company_name,
        i.company_address,
        i.position,
        i.status AS internship_status
    FROM students s
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN internships i ON i.student_id = s.id AND i.deleted_at IS NULL
    WHERE s.id = ?
    ORDER BY i.updated_at DESC, i.id DESC
    LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Student not found');
}
$student = $result->fetch_assoc();
$stmt->close();

$full_name = trim(implode(' ', array_filter([
    (string)($student['first_name'] ?? ''),
    (string)($student['middle_name'] ?? ''),
    (string)($student['last_name'] ?? ''),
])));
$profileUrl = resolve_resume_profile_image_url((string)($student['profile_picture'] ?? ''), (int)($student['user_id'] ?? 0));
$course = trim((string)($student['course_name'] ?? ''));
$department = trim((string)($student['department_name'] ?? ''));
$section = biotern_format_section_label((string)($student['section_code'] ?? ''), (string)($student['section_name'] ?? ''));
$company = trim((string)($student['company_name'] ?? ''));
$companyAddress = trim((string)($student['company_address'] ?? ''));
$companyPosition = trim((string)($student['position'] ?? ''));
if ($companyPosition !== '' && preg_match('/\b(head|supervisor|manager|coordinator|representative|director|president|officer)\b/i', $companyPosition)) {
    $companyPosition = '';
}
$phone = trim((string)($student['phone'] ?? ''));
$email = trim((string)($student['email'] ?? ''));
$birthday = trim((string)($student['date_of_birth'] ?? ''));
$address = trim((string)($student['address'] ?? ''));
$summary = trim((string)($student['bio'] ?? ''));
if ($summary === '') {
    $summary = 'Student trainee prepared for on-the-job training with academic background in the assigned program and readiness to support department and company workflows.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BioTern || Resume - <?php echo resume_h($full_name); ?></title>
<style>
    @page { size: A4; margin: 12mm; }
    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        color: #111827;
        background: #eef2f7;
    }
    .resume-shell {
        max-width: 900px;
        margin: 20px auto;
        background: #fff;
        box-shadow: 0 16px 42px rgba(15, 23, 42, 0.12);
        border: 1px solid #dbe3ee;
    }
    .resume-sheet {
        padding: 26px 30px 30px;
    }
    .hero {
        display: grid;
        grid-template-columns: 1.45fr 170px;
        gap: 20px;
        align-items: start;
        margin-bottom: 18px;
    }
    .name {
        margin: 0 0 6px;
        font-size: 34px;
        line-height: 1.02;
        font-weight: 800;
    }
    .subtitle {
        margin: 0 0 12px;
        font-size: 14px;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .contact-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 16px;
        font-size: 14px;
    }
    .contact-row strong {
        display: inline-block;
        min-width: 78px;
    }
    .photo {
        width: 170px;
        height: 170px;
        border: 1px solid #cbd5e1;
        overflow: hidden;
        justify-self: end;
        background: #f8fafc;
    }
    .photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .photo-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #64748b;
    }
    .section {
        margin-top: 16px;
    }
    .section-title {
        margin: 0 0 10px;
        padding: 6px 10px;
        font-size: 15px;
        font-weight: 800;
        background: #e5e7eb;
        border-left: 4px solid #475569;
        text-transform: uppercase;
    }
    .summary {
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
        text-align: justify;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 18px;
    }
    .info-card {
        border: 1px solid #e2e8f0;
        padding: 10px 12px;
        min-height: 74px;
    }
    .info-label {
        margin: 0 0 6px;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .info-value {
        margin: 0;
        font-size: 15px;
        line-height: 1.4;
        font-weight: 700;
    }
    .company-block {
        border: 1px solid #e2e8f0;
        padding: 14px 16px;
    }
    .company-name {
        margin: 0 0 4px;
        font-size: 18px;
        font-weight: 800;
    }
    .company-meta {
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
    }
    .print-btn {
        position: fixed;
        right: 20px;
        bottom: 20px;
        padding: 12px 20px;
        border: 0;
        border-radius: 10px;
        background: #1d4ed8;
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.22);
    }
    @media (max-width: 640px) {
        .resume-shell { margin: 0; }
        .resume-sheet { padding: 20px; }
        .hero { grid-template-columns: 1fr; }
        .photo { justify-self: start; }
        .contact-grid, .info-grid { grid-template-columns: 1fr; }
    }
    @media print {
        body { background: #fff; }
        .resume-shell { max-width: none; margin: 0; box-shadow: none; border: 0; }
        .resume-sheet { padding: 0; }
        .print-btn { display: none; }
    }
</style>
</head>
<body>
<div class="resume-shell">
    <div class="resume-sheet">
        <section class="hero">
            <div>
                <h1 class="name"><?php echo resume_h($full_name !== '' ? $full_name : 'Student Name'); ?></h1>
                <p class="subtitle"><?php echo resume_h($course !== '' ? $course : 'Student Trainee'); ?></p>
                <div class="contact-grid">
                    <div class="contact-row"><strong>Email</strong><?php echo resume_h($email !== '' ? $email : 'Not provided'); ?></div>
                    <div class="contact-row"><strong>Phone</strong><?php echo resume_h($phone !== '' ? $phone : 'Not provided'); ?></div>
                    <div class="contact-row"><strong>Birthday</strong><?php echo resume_h($birthday !== '' ? $birthday : 'Not provided'); ?></div>
                    <div class="contact-row"><strong>Student ID</strong><?php echo resume_h((string)($student['student_id'] ?? 'Not provided')); ?></div>
                    <div class="contact-row" style="grid-column: 1 / -1;"><strong>Address</strong><?php echo resume_h($address !== '' ? $address : 'Not provided'); ?></div>
                </div>
            </div>
            <div class="photo">
                <?php if ($profileUrl !== null): ?>
                    <img src="<?php echo resume_h($profileUrl); ?>" alt="Profile Photo">
                <?php else: ?>
                    <div class="photo-placeholder">No Photo</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section">
            <h2 class="section-title">Professional Summary</h2>
            <p class="summary"><?php echo nl2br(resume_h($summary)); ?></p>
        </section>

        <section class="section">
            <h2 class="section-title">Academic Profile</h2>
            <div class="info-grid">
                <div class="info-card">
                    <p class="info-label">Department</p>
                    <p class="info-value"><?php echo resume_h($department !== '' ? $department : 'Not provided'); ?></p>
                </div>
                <div class="info-card">
                    <p class="info-label">Course</p>
                    <p class="info-value"><?php echo resume_h($course !== '' ? $course : 'Not provided'); ?></p>
                </div>
                <div class="info-card">
                    <p class="info-label">Section</p>
                    <p class="info-value"><?php echo resume_h($section !== '' ? $section : 'Not provided'); ?></p>
                </div>
                <div class="info-card">
                    <p class="info-label">Current Track</p>
                    <p class="info-value"><?php echo resume_h(ucfirst((string)($student['assignment_track'] ?? 'Internal'))); ?></p>
                </div>
            </div>
        </section>

        <section class="section">
            <h2 class="section-title">Current Internship</h2>
            <div class="company-block">
                <p class="company-name"><?php echo resume_h($company !== '' ? $company : 'No company linked yet'); ?></p>
                <p class="company-meta">
                    <strong>Internship Role:</strong> <?php echo resume_h($companyPosition !== '' ? $companyPosition : 'OJT Trainee'); ?><br>
                    <strong>Status:</strong> <?php echo resume_h(trim((string)($student['internship_status'] ?? '')) !== '' ? ucfirst((string)$student['internship_status']) : 'Not started'); ?><br>
                    <strong>Address:</strong> <?php echo resume_h($companyAddress !== '' ? $companyAddress : 'No company address saved yet'); ?>
                </p>
            </div>
        </section>
    </div>
</div>

<button class="print-btn" type="button" onclick="window.print()">Print Resume</button>
</body>
</html>
