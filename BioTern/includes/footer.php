<?php
// Shared footer include.  It closes main container and adds global scripts.
$page_is_public = isset($page_is_public) && $page_is_public === true;
$page_render_footer = isset($page_render_footer) ? (bool)$page_render_footer : !$page_is_public;

if (!function_exists('footer_asset_versioned_src')) {
    function footer_asset_versioned_src(string $src): string
    {
        if (function_exists('header_asset_versioned_href')) {
            return header_asset_versioned_href($src);
        }

        $trimmed = trim($src);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (preg_match('/^(?:[a-z]+:)?\\/\\//i', $trimmed) || stripos($trimmed, 'data:') === 0) {
            return $trimmed;
        }

        $parts = explode('?', $trimmed, 2);
        $relative = ltrim(str_replace('\\', '/', $parts[0]), '/');
        if ($relative === '') {
            return $trimmed;
        }

        $absolute = dirname(__DIR__) . '/' . $relative;
        $mtime = @filemtime($absolute);
        if ($mtime === false) {
            return $trimmed;
        }

        $separator = strpos($trimmed, '?') !== false ? '&' : '?';
        return $trimmed . $separator . 'v=' . rawurlencode((string)$mtime);
    }
}
?>
<?php if ($page_render_footer): ?>
    <!-- [ Footer ] start -->
    <footer class="footer">
        <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
            <span>Copyright &copy;</span>
            <span class="app-current-year"></span>
        </p>
        <p><span>By: <a href="#">ACT 2A</a> </span><span>Distributed by: <a href="#">Group 5</a></span></p>
        <div class="d-flex align-items-center gap-4">
            <a href="#" class="fs-11 fw-semibold text-uppercase">Help</a>
            <a href="#" class="fs-11 fw-semibold text-uppercase">Terms</a>
            <a href="#" class="fs-11 fw-semibold text-uppercase">Privacy</a>
        </div>
    </footer>
    <!-- [ Footer ] end -->
<?php endif; ?>

<?php if (!$page_is_public): ?>
    <?php
    require_once __DIR__ . '/bottom-nav-groups.php';
    $profile_avatar = isset($header_avatar) ? (string)$header_avatar : 'assets/images/avatar/1.png';
    $nav_groups = biotern_build_bottom_nav_groups([
        'can_internship' => !empty($nav_can_internship),
        'can_academic' => !empty($nav_can_academic),
        'can_workspace' => !empty($nav_can_workspace),
        'can_system' => !empty($nav_can_system),
        'is_student' => !empty($nav_is_student),
        'student_external' => !empty($nav_student_has_external_access),
        'profile_avatar' => $profile_avatar,
    ]);
    ?>

    <?php if (!empty($nav_groups)): ?>
        <nav class="biotern-bottom-nav" aria-label="Primary">
            <?php foreach ($nav_groups as $group): ?>
                <?php
                $routes = isset($group['routes']) ? implode(',', (array)$group['routes']) : '';
                $is_profile = ($group['key'] ?? '') === 'profile';
                ?>
                <button type="button"
                        class="biotern-bottom-link<?php echo $is_profile ? ' is-profile' : ''; ?>"
                        data-panel-target="<?php echo htmlspecialchars((string)$group['key'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-routes="<?php echo htmlspecialchars($routes, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($is_profile): ?>
                        <span class="biotern-bottom-avatar">
                            <img src="<?php echo htmlspecialchars((string)($group['avatar'] ?? $profile_avatar), ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                        </span>
                    <?php else: ?>
                        <i class="<?php echo htmlspecialchars((string)$group['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <?php endif; ?>
                    <span class="biotern-bottom-label"><?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="biotern-bottom-sheet" id="bioternBottomSheet" aria-hidden="true">
            <div class="biotern-bottom-sheet-backdrop" data-sheet-close></div>
            <div class="biotern-bottom-sheet-panel" role="dialog" aria-modal="true">
                <div class="biotern-bottom-sheet-handle"></div>
                <?php foreach ($nav_groups as $group): ?>
                    <div class="biotern-bottom-sheet-content" data-panel="<?php echo htmlspecialchars((string)$group['key'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="biotern-bottom-sheet-title"><?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($group['sections'])): ?>
                            <?php foreach ($group['sections'] as $section): ?>
                                <div class="biotern-bottom-sheet-section">
                                    <div class="biotern-bottom-sheet-section-title"><?php echo htmlspecialchars((string)$section['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="biotern-bottom-sheet-links">
                                        <?php foreach ((array)$section['items'] as $item): ?>
                                            <a class="biotern-bottom-sheet-link" href="<?php echo htmlspecialchars((string)$item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="<?php echo htmlspecialchars((string)$item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                <span><?php echo htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <!-- Toast container for server-side flash messages (used by external attendance pages) -->
    <div id="bioternToastContainer" aria-live="polite" aria-atomic="true" style="position:fixed;right:18px;bottom:18px;z-index:200000;max-width:360px;pointer-events:none;"></div>
<?php endif; ?>

    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/vendors/js/vendors.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/vendors/js/dataTables.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/vendors/js/dataTables.bs5.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/vendors/js/select2.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/vendors/js/select2-active.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/vendors/js/datepicker.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/common-init-guards.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/common-init.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/ui-state-core.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/runtime-boot.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/custom-select-dropdown.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/global-ui-helpers.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/unified-date-picker.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/page-header-consistency.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/mobile-filter-actions-modal.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/page-header-actions-scheme.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/header-search-runtime.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/header-notifications-runtime.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/theme-preferences-runtime.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/navigation-core.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/mobile-bottom-nav.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/mobile-patterns.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(footer_asset_versioned_src('assets/js/modules/shared/customers-runtime.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php if (isset($page_vendor_scripts) && is_array($page_vendor_scripts)): ?>
        <?php foreach ($page_vendor_scripts as $vendor_script): ?>
            <?php if (is_string($vendor_script) && trim($vendor_script) !== ''): ?>
                <script src="<?php echo htmlspecialchars(footer_asset_versioned_src($vendor_script), ENT_QUOTES, 'UTF-8'); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (isset($page_scripts) && is_array($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <?php if (is_string($script) && trim($script) !== ''): ?>
                <script src="<?php echo htmlspecialchars(footer_asset_versioned_src($script), ENT_QUOTES, 'UTF-8'); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <!--! END: Apps Init !-->
    <!-- Theme runtime moved to assets/js/modules/shared/theme-state-core.js, assets/js/global-ui-helpers.js, and assets/js/theme-preferences-runtime.js -->
    <!-- Header search runtime moved to assets/js/modules/shared/header-search-runtime.js -->
</body>

</html>




