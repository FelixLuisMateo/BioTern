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
    ['title' => 'Parent Consent', 'href' => 'document_parent_consent.php' . $studentQuery, 'icon' => 'feather-user-check'],
    ['title' => 'Application Letter', 'href' => 'document_application.php' . $studentQuery, 'icon' => 'feather-file-text'],
    ['title' => 'Endorsement Letter', 'href' => 'document_endorsement.php' . $studentQuery, 'icon' => 'feather-send'],
    ['title' => 'MOA', 'href' => 'document_moa.php' . $studentQuery, 'icon' => 'feather-briefcase'],
    ['title' => 'Dau MOA', 'href' => 'document_dau_moa.php' . $studentQuery, 'icon' => 'feather-map-pin'],
    ['title' => 'Evaluation Form', 'href' => $studentId > 0 ? ('../students-view.php?id=' . $studentId . '&tab=evaluation') : 'document_evaluation.php', 'icon' => 'feather-star'],
    ['title' => 'Certificate of Completion', 'href' => 'document_certificate.php' . $studentQuery, 'icon' => 'feather-award'],
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
        <div class="page-header dashboard-page-header document-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Documents</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Documents</li>
                </ul>
            </div>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentsHubActionsMenu',
                'items_html' => biotern_document_header_actions_html((int)$studentId),
                'inline' => true,
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
                            <div class="documents-hub-preview-frame" data-document-thumbnail>
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
        height: 420px;
        overflow: hidden;
        background: #eef1f6;
        border-top: 1px solid var(--biotern-border, #e5e7eb);
        position: relative;
    }
    .documents-hub-preview-frame iframe {
        width: 100%;
        height: 100%;
        border: 0;
        background: #eef1f6;
        pointer-events: none;
    }
    @media (max-width: 991.98px) {
        .documents-hub-preview-frame {
            height: 360px;
        }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var selectors = [
        '#editor',
        '#moa_content',
        '.certificate-sheet',
        '#letter_preview',
        '.doc-preview'
    ];

    function appendFallback(doc) {
        var fallback = doc.createElement('div');
        fallback.className = 'documents-hub-fallback-paper';
        fallback.innerHTML = '<span></span><span></span><span></span><span></span><span></span>';
        return fallback;
    }

    function findPaper(doc) {
        var i;
        for (i = 0; i < selectors.length; i += 1) {
            var node = doc.querySelector(selectors[i]);
            if (node && (node.querySelector('.a4-page, .certificate-content') || node.textContent.trim().length > 20)) {
                return node;
            }
        }
        return null;
    }

    function thumbnailCss() {
        return [
            'html,body{margin:0!important;padding:0!important;width:100%!important;height:100%!important;overflow:hidden!important;background:#eef1f6!important;}',
            'body{display:block!important;}',
            '.documents-hub-thumbnail-stage{box-sizing:border-box;width:100%;height:100%;padding:18px;display:flex;align-items:flex-start;justify-content:center;overflow:hidden;background:#eef1f6;}',
            '.documents-hub-thumbnail-paper{flex:0 0 auto;transform:scale(.42);transform-origin:top center;margin:0!important;}',
            '.documents-hub-thumbnail-paper#editor,.documents-hub-thumbnail-paper#moa_content{width:210mm!important;max-width:none!important;min-height:0!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;overflow:visible!important;}',
            '.documents-hub-thumbnail-paper .a4-pages-stack,.documents-hub-thumbnail-paper#moa_content{display:flex!important;flex-direction:column!important;align-items:center!important;gap:16px!important;}',
            '.documents-hub-thumbnail-paper .a4-page,.documents-hub-thumbnail-paper#letter_preview,.documents-hub-thumbnail-paper.doc-preview{width:210mm!important;min-height:297mm!important;max-width:none!important;background:#fff!important;box-sizing:border-box!important;box-shadow:0 10px 24px rgba(15,23,42,.18)!important;}',
            '.documents-hub-thumbnail-paper.certificate-sheet{box-shadow:0 10px 24px rgba(15,23,42,.18)!important;}',
            '.documents-hub-fallback-paper{width:210mm;height:297mm;box-sizing:border-box;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.18);padding:80px 70px;transform:scale(.42);transform-origin:top center;}',
            '.documents-hub-fallback-paper span{display:block;height:10px;margin-bottom:24px;background:#d1d5db;border-radius:4px;}',
            '@media(max-width:991.98px){.documents-hub-thumbnail-stage{padding:14px}.documents-hub-thumbnail-paper,.documents-hub-fallback-paper{transform:scale(.34);}}'
        ].join('');
    }

    function renderThumbnail(frame, attempt) {
        var iframe = frame.querySelector('iframe');
        var doc;
        var source;
        var clone;
        var style;
        var stage;

        if (!iframe) {
            return;
        }

        try {
            doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
        } catch (err) {
            return;
        }

        if (!doc || !doc.body) {
            return;
        }

        source = findPaper(doc);
        if (!source && attempt < 12) {
            window.setTimeout(function () {
                renderThumbnail(frame, attempt + 1);
            }, 250);
            return;
        }

        clone = source ? source.cloneNode(true) : appendFallback(doc);
        clone.classList.add('documents-hub-thumbnail-paper');

        doc.body.innerHTML = '';
        style = doc.createElement('style');
        style.textContent = thumbnailCss();
        stage = doc.createElement('main');
        stage.className = 'documents-hub-thumbnail-stage';
        stage.appendChild(clone);
        doc.head.appendChild(style);
        doc.body.appendChild(stage);
    }

    document.querySelectorAll('[data-document-thumbnail]').forEach(function (frame) {
        var iframe = frame.querySelector('iframe');
        var iframeDoc;
        if (!iframe) {
            return;
        }
        iframe.addEventListener('load', function () {
            window.setTimeout(function () {
                renderThumbnail(frame, 0);
            }, 350);
        });
        try {
            iframeDoc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
            if (iframeDoc && /complete|interactive/.test(iframeDoc.readyState || '')) {
                window.setTimeout(function () {
                    renderThumbnail(frame, 0);
                }, 350);
            }
        } catch (err) {}
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
