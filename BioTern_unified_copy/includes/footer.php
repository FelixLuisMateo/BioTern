<?php
// Shared footer include.  It closes main container and adds global scripts.
?>
        </div> <!-- .nxl-content -->
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
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->

    <!--! ================================================================ !-->
 
    <!--! ================================================================ !-->
    <!--! BEGIN: Downloading Toast !-->
    <!--! ================================================================ !-->
    <div class="position-fixed app-floating-corner">
        <div id="toast" class="toast bg-black hide" data-bs-delay="3000" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header px-3 bg-transparent d-flex align-items-center justify-content-between border-bottom border-light border-opacity-10">
                <div class="text-white mb-0 mr-auto">Downloading...</div>
                <a href="javascript:void(0)" class="ms-2 mb-1 close fw-normal" data-bs-dismiss="toast" aria-label="Close">
                    <span class="text-white">&times;</span>
                </a>
            </div>
            <div class="toast-body p-3 text-white">
                <h6 class="fs-13 text-white">Project.zip</h6>
                <span class="text-light fs-11">4.2mb of 5.5mb</span>
            </div>
            <div class="toast-footer p-3 pt-0 border-top border-light border-opacity-10">
                <div class="progress mt-3 app-progress-thin">
                    <div class="progress-bar progress-bar-striped progress-bar-animated w-75 bg-dark" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>
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
    <!--! BEGIN: Theme Customizer  !-->
    <!-- Theme Customizer removed -->
    <!--! END: Theme Customizer !-->
    <!-- Theme runtime moved to assets/js/global-ui-helpers.js and assets/js/theme-preferences-runtime.js -->
    <!-- Header search inline bootstrap removed; handled by assets/js/common-init.min.js -->
</body>

</html>





