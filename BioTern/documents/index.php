<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$page_title = 'Documents';
$base_href = '../';
$page_body_class = 'documents-hub-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/documents.css',
];

include __DIR__ . '/../includes/header.php';

$documents = [
    ['title' => 'Application Letter', 'href' => 'documents/document_application.php', 'icon' => 'feather-file-text'],
    ['title' => 'Endorsement Letter', 'href' => 'documents/document_endorsement.php', 'icon' => 'feather-send'],
    ['title' => 'MOA', 'href' => 'documents/document_moa.php', 'icon' => 'feather-briefcase'],
    ['title' => 'DAU MOA', 'href' => 'documents/document_dau_moa.php', 'icon' => 'feather-map-pin'],
    ['title' => 'Parent Consent', 'href' => 'documents/document_parent_consent.php', 'icon' => 'feather-user-check'],
];
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
            <div class="row g-3">
                <?php foreach ($documents as $doc): ?>
                    <div class="col-md-6 col-xl-4">
                        <a class="card stretch stretch-full text-decoration-none" href="<?php echo htmlspecialchars($doc['href'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="card-body d-flex align-items-center gap-3">
                                <span class="avatar-text avatar-lg bg-soft-primary text-primary">
                                    <i class="<?php echo htmlspecialchars($doc['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </span>
                                <div>
                                    <h5 class="mb-1 text-reset"><?php echo htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                    <p class="mb-0 text-muted fs-12">Open, edit, preview, and print.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
