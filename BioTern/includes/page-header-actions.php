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
        $toggleIcon = trim((string)($config['toggle_icon'] ?? 'feather-grid'));
        $toggleLabel = trim((string)($config['toggle_label'] ?? 'Actions'));
        $metaLabel = trim((string)($config['meta_label'] ?? 'Quick Actions'));
        $showMeta = !array_key_exists('show_meta', $config) || (bool)$config['show_meta'];

        if ($toggleIcon === '') {
            $toggleIcon = 'feather-grid';
        }
        if ($toggleLabel === '') {
            $toggleLabel = 'Actions';
        }
        if ($metaLabel === '') {
            $metaLabel = 'Quick Actions';
        }
        ?>
        <div class="<?php echo htmlspecialchars($rootClass, ENT_QUOTES, 'UTF-8'); ?>">
            <button
                type="button"
                class="<?php echo htmlspecialchars($toggleClass, ENT_QUOTES, 'UTF-8'); ?>"
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
