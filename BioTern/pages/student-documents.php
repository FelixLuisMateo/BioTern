<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

biotern_boot_session(isset($conn) ? $conn : null);

function student_documents_table_exists(mysqli $conn, string $table): bool
{
    $table = trim($table);
    if ($table === '') {
        return false;
    }

    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function student_documents_fetch_row(mysqli $conn, string $table, int $studentId): ?array
{
    if ($studentId <= 0 || !student_documents_table_exists($conn, $table)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function student_documents_build_url(string $base, array $params): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if ($value === null) {
            continue;
        }
        $clean[(string)$key] = (string)$value;
    }

    return $base . (empty($clean) ? '' : ('?' . http_build_query($clean)));
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$student = null;
$user = null;

$userStmt = $conn->prepare('SELECT id, name, profile_picture FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: null;
    $userStmt->close();
}

$studentStmt = $conn->prepare("
    SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.assignment_track,
           c.name AS course_name, sec.code AS section_code, sec.name AS section_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE s.user_id = ?
    LIMIT 1
");
if ($studentStmt) {
    $studentStmt->bind_param('i', $currentUserId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
}

if (!$student) {
    header('Location: homepage.php');
    exit;
}

$studentId = (int)($student['id'] ?? 0);
$displayName = trim((string)($user['name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
}
$sectionLabel = biotern_format_section_label((string)($student['section_code'] ?? ''), (string)($student['section_name'] ?? ''));
$avatarSrc = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $currentUserId);

$applicationRow = student_documents_fetch_row($conn, 'application_letter', $studentId);
$endorsementRow = student_documents_fetch_row($conn, 'endorsement_letter', $studentId);
$moaRow = student_documents_fetch_row($conn, 'moa', $studentId);
$dauMoaRow = student_documents_fetch_row($conn, 'dau_moa', $studentId);

$documentCards = [
    [
        'title' => 'Application Letter',
        'status' => $applicationRow ? 'Ready to view and print.' : 'No saved application letter yet.',
        'view_url' => $applicationRow ? 'student-application-letter.php' : '',
    ],
    [
        'title' => 'Endorsement Letter',
        'status' => $endorsementRow ? 'Ready to view and print.' : 'No saved endorsement letter yet.',
        'view_url' => $endorsementRow ? student_documents_build_url('pages/generate_endorsement_letter.php', [
            'id' => $studentId,
            'recipient' => $endorsementRow['recipient_name'] ?? '',
            'recipient_title' => $endorsementRow['recipient_title'] ?? 'auto',
            'position' => $endorsementRow['recipient_position'] ?? '',
            'company' => $endorsementRow['company_name'] ?? '',
            'company_address' => $endorsementRow['company_address'] ?? '',
            'students' => $endorsementRow['students_to_endorse'] ?? $displayName,
            'greeting_pref' => $endorsementRow['greeting_preference'] ?? 'either',
            'use_saved_template' => '1',
        ]) : '',
    ],
    [
        'title' => 'MOA',
        'status' => $moaRow ? 'Ready to view and print.' : 'No saved MOA yet.',
        'view_url' => $moaRow ? student_documents_build_url('pages/generate_moa.php', [
            'partner_name' => $moaRow['company_name'] ?? '',
            'partner_rep' => $moaRow['partner_representative'] ?? '',
            'partner_position' => $moaRow['position'] ?? '',
            'partner_address' => $moaRow['company_address'] ?? '',
            'company_receipt' => $moaRow['company_receipt'] ?? '',
            'total_hours' => $moaRow['total_hours'] ?? '',
            'school_rep' => $moaRow['coordinator'] ?? '',
            'school_position' => $moaRow['school_posistion'] ?? ($moaRow['school_position'] ?? ''),
            'signed_at' => $moaRow['moa_address'] ?? '',
            'signed_day' => $moaRow['signed_day'] ?? '',
            'signed_month' => $moaRow['signed_month'] ?? '',
            'signed_year' => $moaRow['signed_year'] ?? '',
            'presence_partner_rep' => $moaRow['witness'] ?? '',
            'presence_school_admin' => $moaRow['school_administrator'] ?? '',
            'presence_school_admin_position' => $moaRow['school_admin_position'] ?? '',
            'notary_city' => $moaRow['notary_address'] ?? '',
            'notary_appeared_1' => $moaRow['witness'] ?? '',
            'notary_appeared_2' => '',
            'notary_day' => $moaRow['notary_day'] ?? '',
            'notary_month' => $moaRow['notary_month'] ?? '',
            'notary_year' => $moaRow['notary_year'] ?? '',
            'notary_place' => $moaRow['acknowledgement_address'] ?? '',
            'doc_no' => $moaRow['doc_no'] ?? '',
            'page_no' => $moaRow['page_no'] ?? '',
            'book_no' => $moaRow['book_no'] ?? '',
            'series_no' => $moaRow['series_no'] ?? '',
            'use_saved_template' => '1',
        ]) : '',
    ],
    [
        'title' => 'DAU MOA',
        'status' => $dauMoaRow ? 'Ready to view and print.' : 'No saved DAU MOA yet.',
        'view_url' => $dauMoaRow ? student_documents_build_url('pages/generate_dau_moa.php', [
            'partner_name' => $dauMoaRow['company_name'] ?? '',
            'partner_rep' => $dauMoaRow['partner_representative'] ?? '',
            'partner_position' => $dauMoaRow['position'] ?? '',
            'partner_address' => $dauMoaRow['company_address'] ?? '',
            'company_receipt' => $dauMoaRow['company_receipt'] ?? '',
            'total_hours' => $dauMoaRow['total_hours'] ?? '',
            'school_rep' => $dauMoaRow['school_representative'] ?? ($dauMoaRow['coordinator'] ?? ''),
            'school_position' => $dauMoaRow['school_posistion'] ?? ($dauMoaRow['school_position'] ?? ''),
            'signed_at' => $dauMoaRow['signed_at'] ?? ($dauMoaRow['moa_address'] ?? ''),
            'signed_day' => $dauMoaRow['signed_day'] ?? '',
            'signed_month' => $dauMoaRow['signed_month'] ?? '',
            'signed_year' => $dauMoaRow['signed_year'] ?? '',
            'presence_partner_rep' => $dauMoaRow['witness_partner'] ?? ($dauMoaRow['witness'] ?? ''),
            'presence_school_admin' => $dauMoaRow['school_administrator'] ?? '',
            'presence_school_admin_position' => $dauMoaRow['school_admin_position'] ?? '',
            'notary_city' => $dauMoaRow['notary_city'] ?? ($dauMoaRow['notary_address'] ?? ''),
            'notary_appeared_1' => $dauMoaRow['witness_partner'] ?? ($dauMoaRow['witness'] ?? ''),
            'notary_appeared_2' => '',
            'notary_day' => $dauMoaRow['notary_day'] ?? '',
            'notary_month' => $dauMoaRow['notary_month'] ?? '',
            'notary_year' => $dauMoaRow['notary_year'] ?? '',
            'notary_place' => $dauMoaRow['notary_place'] ?? ($dauMoaRow['acknowledgement_address'] ?? ''),
            'doc_no' => $dauMoaRow['doc_no'] ?? '',
            'page_no' => $dauMoaRow['page_no'] ?? '',
            'book_no' => $dauMoaRow['book_no'] ?? '',
            'series_no' => $dauMoaRow['series_no'] ?? '',
            'use_saved_template' => '1',
        ]) : '',
    ],
    [
        'title' => 'Resume',
        'status' => 'Available from your linked BioTern profile.',
        'view_url' => student_documents_build_url('pages/generate_resume.php', ['id' => $studentId]),
    ],
];

$page_title = 'BioTern || My Documents';
$page_styles = [
    'assets/css/homepage-student.css',
    'assets/css/student-dtr.css',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="main-content">
            <div class="student-home-shell">
                <section class="card student-home-hero border-0">
                    <div class="card-body">
                        <div class="student-home-hero__content">
                            <div>
                                <span class="student-home-eyebrow">Read-Only Documents</span>
                                <h2><?php echo htmlspecialchars($displayName !== '' ? $displayName : 'Student User', ENT_QUOTES, 'UTF-8'); ?></h2>
                                <p>This page is view-and-print only. Your authorized personnel prepare the document details, and you can open the finished copy here.</p>
                                <div class="student-home-meta">
                                    <span><?php echo htmlspecialchars((string)($student['student_id'] ?? 'No student number'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars((string)($student['course_name'] ?? 'No course yet'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars($sectionLabel !== '' ? $sectionLabel : 'No section yet', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="student-home-profile">
                                <img src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <span class="student-metric-label">Printing Access</span>
                                <h3 class="mb-1">My Generated Documents</h3>
                                <div class="student-dtr-meta">Editing stays exclusive to admin, coordinator, and supervisor accounts.</div>
                            </div>
                            <a href="student-profile.php" class="btn btn-outline-primary">Back to Profile</a>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($documentCards as $card): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <article class="student-metric-card h-100">
                                    <div class="card-body d-flex flex-column gap-3">
                                        <div>
                                            <span class="student-metric-label"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <p class="mb-0 mt-2"><?php echo htmlspecialchars($card['status'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="mt-auto">
                                            <?php if ($card['view_url'] !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($card['view_url'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary w-100" target="_blank" rel="noopener">
                                                View / Print
                                            </a>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary w-100" disabled>Waiting for staff input</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
