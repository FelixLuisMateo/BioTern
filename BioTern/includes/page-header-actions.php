<?php

if (!function_exists('biotern_render_page_header_actions')) {
    /**
     * Render the shared page-header quick actions menu.
     */
    function biotern_render_page_header_actions(array $config = []): void
    {
        $menuId = trim((string)($config['menu_id'] ?? ''));
        $itemsHtml = (string)($config['items_html'] ?? '');
        if ($menuId === '' || trim($itemsHtml) === '') {
            return;
        }

        $rootClass = trim('page-header-right ms-auto ' . (string)($config['root_class'] ?? ''));
        $toggleClass = trim('btn btn-sm btn-light-brand page-header-actions-toggle ' . (string)($config['toggle_class'] ?? ''));
        $panelClass = trim('page-header-actions ' . (string)($config['panel_class'] ?? ''));
        $toggleIcon = trim((string)($config['toggle_icon'] ?? '')) ?: 'feather-grid';
        $toggleLabel = trim((string)($config['toggle_label'] ?? '')) ?: 'Actions';
        $metaLabel = trim((string)($config['meta_label'] ?? '')) ?: 'Quick Actions';
        $toggleAriaLabel = trim((string)($config['toggle_aria_label'] ?? '')) ?: 'Header actions';
        $showMeta = !array_key_exists('show_meta', $config) || (bool)$config['show_meta'];
        $inline = !empty($config['inline']);

        if ($inline) {
            ?>
            <?php if (empty($GLOBALS['biotern_document_actions_inline_css_printed'])): ?>
                <?php $GLOBALS['biotern_document_actions_inline_css_printed'] = true; ?>
                <style>
                    body .page-header.document-page-header {
                        flex-wrap: wrap !important;
                        align-items: flex-start !important;
                        gap: 6px 12px !important;
                        min-height: 96px !important;
                        padding-top: 10px !important;
                        padding-bottom: 8px !important;
                    }
                    body .page-header.document-page-header .page-header-left {
                        flex: 1 0 100% !important;
                        width: 100% !important;
                    }
                    body .page-header .document-header-actions {
                        flex: 1 0 100% !important;
                        width: 100% !important;
                        margin-left: 0 !important;
                        padding-top: 0 !important;
                    }
                    body .page-header .document-header-actions .document-header-actions-row {
                        width: 100% !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: flex-start !important;
                        gap: 8px !important;
                        flex-wrap: nowrap !important;
                        overflow-x: auto !important;
                        overflow-y: hidden !important;
                        padding-bottom: 2px !important;
                        scrollbar-width: thin;
                    }
                    body .page-header .document-header-actions .document-header-action-btn {
                        flex: 0 0 auto !important;
                        white-space: nowrap !important;
                    }
                </style>
            <?php endif; ?>
            <div class="<?php echo htmlspecialchars($rootClass . ' document-header-actions', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="page-header-right-items-wrapper document-header-actions-row">
                    <?php echo $itemsHtml; ?>
                </div>
            </div>
            <?php
            return;
        }

        ?>
        <div class="<?php echo htmlspecialchars($rootClass, ENT_QUOTES, 'UTF-8'); ?>">
            <button
                type="button"
                class="<?php echo htmlspecialchars($toggleClass, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($toggleAriaLabel, ENT_QUOTES, 'UTF-8'); ?>"
                aria-expanded="false"
                aria-controls="<?php echo htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="<?php echo htmlspecialchars($toggleIcon, ENT_QUOTES, 'UTF-8'); ?> me-1"></i>
                <span><?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </button>
            <div class="<?php echo htmlspecialchars($panelClass, ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="dashboard-actions-panel biotern-backdrop-glass">
                    <?php if ($showMeta): ?>
                        <div class="dashboard-actions-meta">
                            <span class="text-muted fs-12"><?php echo htmlspecialchars($metaLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="dashboard-actions-grid page-header-right-items-wrapper">
                        <?php echo $itemsHtml; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('biotern_document_header_actions_html')) {
    /**
     * Build the shared document navigation buttons in the requested order.
     */
    function biotern_document_header_actions_html(int $studentId = 0): string
    {
        $studentId = max(0, $studentId);
        $studentQuery = $studentId > 0 ? ('?id=' . $studentId) : '';
        $currentFile = basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
        $evaluationHref = $studentId > 0
            ? ('students-view.php?id=' . $studentId . '&tab=evaluation')
            : 'documents/document_evaluation.php';

        $items = [
            ['file' => 'document_parent_consent.php', 'href' => 'documents/document_parent_consent.php' . $studentQuery, 'icon' => 'feather-user-check', 'label' => 'Parent Consent'],
            ['file' => 'document_application.php', 'href' => 'documents/document_application.php' . $studentQuery, 'icon' => 'feather-file-text', 'label' => 'Application'],
            ['file' => 'document_endorsement.php', 'href' => 'documents/document_endorsement.php' . $studentQuery, 'icon' => 'feather-send', 'label' => 'Endorsement'],
            ['file' => 'document_moa.php', 'href' => 'documents/document_moa.php' . $studentQuery, 'icon' => 'feather-briefcase', 'label' => 'MOA'],
            ['file' => 'document_dau_moa.php', 'href' => 'documents/document_dau_moa.php' . $studentQuery, 'icon' => 'feather-map-pin', 'label' => 'Dau MOA'],
            ['file' => 'document_evaluation.php', 'href' => $evaluationHref, 'icon' => 'feather-star', 'label' => 'Evaluation Form'],
            ['file' => 'document_certificate.php', 'href' => 'documents/document_certificate.php' . $studentQuery, 'icon' => 'feather-award', 'label' => 'Certificate of Completion'],
        ];

        $html = '';
        foreach ($items as $item) {
            $isActive = $currentFile === $item['file'];
            $buttonClass = $isActive ? 'btn btn-primary active document-header-action-btn' : 'btn btn-light document-header-action-btn';
            $html .= sprintf(
                '<a href="%s" class="%s"%s><i class="%s me-1"></i>%s</a>',
                htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8'),
                $isActive ? ' aria-current="page"' : '',
                htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
            );
        }

        return $html;
    }
}
