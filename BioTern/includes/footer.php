<?php
// Shared footer include.  It closes main container and adds global scripts.
$page_is_public = isset($page_is_public) && $page_is_public === true;
$page_render_container = isset($page_render_container) ? (bool)$page_render_container : !$page_is_public;
$page_render_footer = isset($page_render_footer) ? (bool)$page_render_footer : !$page_is_public;
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

    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/dataTables.min.js"></script>
    <script src="assets/vendors/js/dataTables.bs5.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/global-ui-helpers.js"></script>
    <script src="assets/js/theme-preferences-runtime.js"></script>
    <script src="assets/js/customers-init.min.js"></script>
    <?php if (isset($page_vendor_scripts) && is_array($page_vendor_scripts)): ?>
        <?php foreach ($page_vendor_scripts as $vendor_script): ?>
            <?php if (is_string($vendor_script) && trim($vendor_script) !== ''): ?>
                <script src="<?php echo htmlspecialchars($vendor_script, ENT_QUOTES, 'UTF-8'); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (isset($page_scripts) && is_array($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <?php if (is_string($script) && trim($script) !== ''): ?>
                <script src="<?php echo htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <!--! END: Apps Init !-->
    <!-- Theme runtime moved to assets/js/global-ui-helpers.js and assets/js/theme-preferences-runtime.js -->
    <!-- Header search inline bootstrap removed; handled by assets/js/common-init.min.js -->
</body>

</html>





