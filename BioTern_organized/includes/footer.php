<?php
// Shared footer include.  It closes main container and adds global scripts.
?>
        </div> <!-- .nxl-content -->
        <!-- [ Footer ] start -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p><span>By: <a target="_blank" href="" target="_blank">ACT 2A</a> </span><span>Distributed by: <a target="_blank" href="" target="_blank">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
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
    <div class="position-fixed" style="right: 5px; bottom: 5px; z-index: 999999">
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
                <div class="progress mt-3" style="height: 5px">
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
    <script src="assets/js/customers-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <!-- Theme Customizer removed -->
    <!--! END: Theme Customizer !-->
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function(){
                var darkBtn = document.querySelector('.dark-button');
                var lightBtn = document.querySelector('.light-button');

                function setDark(isDark){
                    if(isDark){
                        document.documentElement.classList.add('app-skin-dark');
                        try{ localStorage.setItem('app-skin','app-skin-dark'); }catch(e){}
                        if(darkBtn) darkBtn.style.display = 'none';
                        if(lightBtn) lightBtn.style.display = '';
                    } else {
                        document.documentElement.classList.remove('app-skin-dark');
                        try{ localStorage.setItem('app-skin',''); }catch(e){}
                        if(darkBtn) darkBtn.style.display = '';
                        if(lightBtn) lightBtn.style.display = 'none';
                    }
                }

                var s = '';
                try{ s = localStorage.getItem('app-skin') || localStorage.getItem('app_skin') || localStorage.getItem('theme') || localStorage.getItem('app-skin-dark') || ''; }catch(e){}
                var isDark = (typeof s === 'string' && s.indexOf('dark') !== -1) || document.documentElement.classList.contains('app-skin-dark');
                setDark(isDark);

                if(darkBtn) darkBtn.addEventListener('click', function(e){ e.preventDefault(); setDark(true); });
                if(lightBtn) lightBtn.addEventListener('click', function(e){ e.preventDefault(); setDark(false); });
            });
        })();
    </script>
</body>

</html>
