<?php
// Shared footer include.  It closes main container and adds global scripts.
?>
<style>
    /* Keep footer pinned at the bottom on short pages. */
    .nxl-container {
        display: flex;
        flex-direction: column;
        min-height: calc(100vh - 80px);
    }

    .nxl-container .nxl-content {
        flex: 1 0 auto;
    }

    .nxl-container .footer {
        margin-top: auto;
    }

    /* Topbar dropdowns: click-only behavior (never open on hover). */
    .nxl-header .dropdown.nxl-h-item > .dropdown-menu {
        display: none;
    }

    .nxl-header .dropdown.nxl-h-item > .dropdown-menu.show {
        display: block;
    }
</style>        </div> <!-- .nxl-content -->
        <!-- [ Footer ] start -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright &copy;</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p><span>By: <a href="javascript:void(0);">ACT 2A</a> </span><span>Distributed by: <a href="javascript:void(0);">Group 5</a></span></p>
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
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                // Remove template hover listeners from topbar dropdown wrappers
                // so Bootstrap click behavior is the only trigger.
                var items = document.querySelectorAll('.nxl-header .dropdown.nxl-h-item:not(.nxl-header-search)');
                items.forEach(function (node) {
                    var replacement = node.cloneNode(true);
                    node.parentNode.replaceChild(replacement, node);

                    var toggle = replacement.querySelector('[data-bs-toggle="dropdown"]');
                    if (toggle && window.bootstrap && window.bootstrap.Dropdown) {
                        window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
                            autoClose: true
                        });
                    }
                });
            });
        })();
    </script>
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function(){
                var darkBtn = document.querySelector('.dark-button');
                var lightBtn = document.querySelector('.light-button');
                function getSavedSkin(){
                    try{
                        // Respect primary key even when value is intentionally empty.
                        var primary = localStorage.getItem('app-skin');
                        if (primary !== null) return primary;
                        var alt = localStorage.getItem('app_skin');
                        if (alt !== null) return alt;
                        var theme = localStorage.getItem('theme');
                        if (theme !== null) return theme;
                        var legacy = localStorage.getItem('app-skin-dark');
                        return legacy !== null ? legacy : '';
                    }catch(e){
                        return '';
                    }
                }

                function setDark(isDark){
                    if(isDark){
                        document.documentElement.classList.add('app-skin-dark');
                        try{
                            localStorage.setItem('app-skin','app-skin-dark');
                            // Keep legacy key in sync for older scripts.
                            localStorage.setItem('app-skin-dark','app-skin-dark');
                        }catch(e){}
                        if(darkBtn) darkBtn.style.display = 'none';
                        if(lightBtn) lightBtn.style.display = '';
                    } else {
                        document.documentElement.classList.remove('app-skin-dark');
                        try{
                            localStorage.setItem('app-skin','');
                            localStorage.setItem('app-skin-dark','');
                            // Remove alternate legacy keys to prevent stale dark fallback.
                            localStorage.removeItem('app_skin');
                            localStorage.removeItem('theme');
                        }catch(e){}
                        if(darkBtn) darkBtn.style.display = '';
                        if(lightBtn) lightBtn.style.display = 'none';
                    }
                }

                var s = getSavedSkin();
                var isDark = (typeof s === 'string' && s.indexOf('dark') !== -1) || document.documentElement.classList.contains('app-skin-dark');
                setDark(isDark);

                if(darkBtn) darkBtn.addEventListener('click', function(e){ e.preventDefault(); setDark(true); });
                if(lightBtn) lightBtn.addEventListener('click', function(e){ e.preventDefault(); setDark(false); });
            });
        })();
    </script>
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                if (window.__bioternHeaderSearchUnifiedInit) return;
                window.__bioternHeaderSearchUnifiedInit = true;

                var navLinks = Array.prototype.slice.call(document.querySelectorAll('.nxl-navigation a.nxl-link[href]'))
                    .map(function (a) {
                        return {
                            href: a.getAttribute('href') || '',
                            text: (a.textContent || '').trim()
                        };
                    })
                    .filter(function (x) {
                        return x.href && x.href !== '#' && x.href.indexOf('javascript:') !== 0;
                    });

                function normalize(v) {
                    return (v || '').toLowerCase().trim();
                }

                function searchMatches(query) {
                    var q = normalize(query);
                    if (!q) return [];
                    return navLinks.filter(function (x) {
                        return normalize(x.text).indexOf(q) !== -1 || normalize(x.href).indexOf(q) !== -1;
                    }).slice(0, 6);
                }

                var dropdowns = document.querySelectorAll('.nxl-header-search');
                if (!dropdowns.length) return;

                dropdowns.forEach(function (node) {
                    // Remove template hover listeners by replacing the node.
                    var dd = node.cloneNode(true);
                    node.parentNode.replaceChild(dd, node);

                    var toggle = dd.querySelector('.nxl-head-link');
                    var menu = dd.querySelector('.nxl-search-dropdown');
                    var input = dd.querySelector('#headerSearchInput, .search-input-field');
                    var clearBtn = dd.querySelector('#headerSearchClear, .btn-close');
                    if (!toggle || !menu || !input) return;

                    dd.style.position = 'relative';
                    toggle.removeAttribute('data-bs-toggle');
                    toggle.removeAttribute('data-bs-auto-close');
                    menu.style.display = 'none';

                    var suggestionWrap = document.createElement('div');
                    suggestionWrap.style.padding = '0 0 6px';
                    var suggestionList = document.createElement('div');
                    suggestionWrap.appendChild(suggestionList);
                    menu.appendChild(suggestionWrap);

                    function closeMenu() {
                        menu.style.display = 'none';
                        menu.classList.remove('show');
                        toggle.classList.remove('show');
                    }

                    function openMenu() {
                        menu.style.display = 'block';
                        menu.classList.add('show');
                        toggle.classList.add('show');
                        setTimeout(function () { input.focus(); }, 20);
                    }

                    function renderSuggestions(items) {
                        suggestionList.innerHTML = '';
                        if (!items.length) {
                            suggestionList.innerHTML = '<div class="px-3 py-2 fs-12 text-muted">No matching pages</div>';
                            return;
                        }
                        items.forEach(function (item) {
                            var a = document.createElement('a');
                            a.href = item.href;
                            a.className = 'dropdown-item';
                            a.textContent = item.text;
                            suggestionList.appendChild(a);
                        });
                    }

                    toggle.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (menu.style.display === 'block') closeMenu();
                        else openMenu();
                    });

                    input.addEventListener('input', function () {
                        renderSuggestions(searchMatches(input.value));
                    });

                    input.addEventListener('keydown', function (e) {
                        if (e.key !== 'Enter') return;
                        e.preventDefault();
                        var matches = searchMatches(input.value);
                        if (matches.length) window.location.href = matches[0].href;
                    });

                    if (clearBtn) {
                        clearBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            input.value = '';
                            renderSuggestions([]);
                            input.focus();
                        });
                    }

                    document.addEventListener('click', function (e) {
                        if (!dd.contains(e.target)) closeMenu();
                    });
                });
            });
        })();
    </script>
</body>

</html>




