<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$studentId = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
$student = null;
if ($studentId > 0 && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, student_id FROM students WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}

$studentQuery = $studentId > 0 ? ('?id=' . $studentId) : '';
$documents = [
    ['title' => 'Application Letter', 'href' => 'documents/document_application.php' . $studentQuery, 'icon' => 'feather-file-text'],
    ['title' => 'Endorsement Letter', 'href' => 'documents/document_endorsement.php' . $studentQuery, 'icon' => 'feather-send'],
    ['title' => 'MOA', 'href' => 'documents/document_moa.php' . $studentQuery, 'icon' => 'feather-briefcase'],
    ['title' => 'DAU MOA', 'href' => 'documents/document_dau_moa.php' . $studentQuery, 'icon' => 'feather-map-pin'],
    ['title' => 'Waiver', 'href' => 'documents/document_parent_consent.php' . $studentQuery, 'icon' => 'feather-user-check'],
];

$page_title = 'Documents';
$base_href = '../';
$page_body_class = 'documents-hub-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/documents.css',
];

include __DIR__ . '/../includes/header.php';

$studentName = '';
if (is_array($student)) {
    $studentName = trim((string)(($student['first_name'] ?? '') . ' ' . (!empty($student['middle_name']) ? ($student['middle_name'] . ' ') : '') . ($student['last_name'] ?? '')));
}
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Documents</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Documents</li>
                </ul>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentsHubActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>

        <div class="main-content">
            <div class="card stretch stretch-full mb-3">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Document Hub</h5>
                        <p class="mb-0 text-muted fs-12">
                            <?php if ($studentName !== ''): ?>
                                Previewing documents for <?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($student['student_id']) ? ' - ' . htmlspecialchars((string)$student['student_id'], ENT_QUOTES, 'UTF-8') : ''; ?>.
                            <?php else: ?>
                                Open from a student record to preview that student's documents.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-3 documents-hub-preview-grid">
                <?php foreach ($documents as $doc): ?>
                    <div class="col-xl-6">
                        <div class="card stretch stretch-full documents-hub-preview-card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="<?php echo htmlspecialchars($doc['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                </div>
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($doc['href'], ENT_QUOTES, 'UTF-8'); ?>">Open</a>
                            </div>
                            <div class="documents-hub-preview-frame">
                                <iframe src="<?php echo htmlspecialchars($doc['href'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8'); ?>"></iframe>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
<style>
    .documents-hub-preview-card .card-header {
        min-height: 54px;
    }
    .documents-hub-preview-frame {
        height: 680px;
        overflow: hidden;
        background: #eef1f6;
        border-top: 1px solid var(--biotern-border, #e5e7eb);
    }
    .documents-hub-preview-frame iframe {
        width: 142%;
        height: 142%;
        border: 0;
        transform: scale(0.704);
        transform-origin: top left;
        background: #fff;
    }
    @media (max-width: 991.98px) {
        .documents-hub-preview-frame {
            height: 560px;
        }
        .documents-hub-preview-frame iframe {
            width: 166%;
            height: 166%;
            transform: scale(0.602);
        }
    }
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
