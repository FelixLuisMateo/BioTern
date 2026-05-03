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

function student_documents_fetch_by_student_or_user(mysqli $conn, string $table, int $studentId, int $userId): ?array
{
    if ($studentId <= 0 || !student_documents_table_exists($conn, $table)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE user_id IN (?, ?) ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        return student_documents_fetch_row($conn, $table, $studentId);
    }

    $stmt->bind_param('ii', $studentId, $userId);
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

$applicationRow = student_documents_fetch_by_student_or_user($conn, 'application_letter', $studentId, $currentUserId);
$endorsementRow = student_documents_fetch_by_student_or_user($conn, 'endorsement_letter', $studentId, $currentUserId);
$moaRow = student_documents_fetch_by_student_or_user($conn, 'moa', $studentId, $currentUserId);
$dauMoaRow = student_documents_fetch_by_student_or_user($conn, 'dau_moa', $studentId, $currentUserId);

$documentCards = [
    [
        'title' => 'Application Letter',
        'status' => $applicationRow ? 'Ready to view and print.' : 'No saved application letter yet.',
        'view_url' => $applicationRow ? 'student-application-letter.php' : '',
    ],
    [
        'title' => 'Endorsement Letter',
        'status' => $endorsementRow ? 'Ready to view and print.' : 'No saved endorsement letter yet.',
        'view_url' => $endorsementRow ? 'student-endorsement-letter.php' : '',
    ],
    [
        'title' => 'MOA',
        'status' => $moaRow ? 'Ready to view and print.' : 'No saved MOA yet.',
        'view_url' => $moaRow ? 'student-moa.php' : '',
    ],
    [
        'title' => 'DAU MOA',
        'status' => $dauMoaRow ? 'Ready to view and print.' : 'No saved DAU MOA yet.',
        'view_url' => $dauMoaRow ? 'student-dau-moa.php' : '',
    ],
    [
        'title' => 'Parent Consent and Waiver',
        'status' => 'Available to view and print anytime.',
        'view_url' => student_documents_build_url('document_parent_consent.php', ['student_id' => $studentId]),
    ],
    [
        'title' => 'Resume',
        'status' => 'Available from your linked BioTern profile.',
        'view_url' => student_documents_build_url('generate_resume.php', ['id' => $studentId]),
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
        <div class="page-header dashboard-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">My Documents</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">My Documents</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <a href="student-profile.php" class="btn btn-outline-primary">Back to Profile</a>
            </div>
        </div>
        <div class="main-content">
            <div class="student-home-shell">
                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <span class="student-metric-label">Printing Access</span>
                                <h3 class="mb-1">My Generated Documents</h3>
                                <div class="student-dtr-meta">Editing stays exclusive to admin, coordinator, and supervisor accounts.</div>
                            </div>
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
