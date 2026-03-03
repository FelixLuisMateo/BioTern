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
</style>
    </div> <!-- .nxl-content -->
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
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function(){
                var serverPrefs = window.__bioternThemePrefs || {};
                var darkBtn = document.querySelector('.dark-button');
                var lightBtn = document.querySelector('.light-button');

                var pageSkinLight = document.getElementById('theme-page-skin-light');
                var pageSkinDark = document.getElementById('theme-page-skin-dark');
                var pageMenu = document.getElementById('theme-page-menu');
                var pageFont = document.getElementById('theme-page-font');
                var pageNavigation = document.getElementById('theme-page-navigation');
                var pageHeader = document.getElementById('theme-page-header');
                var pageSave = document.getElementById('theme-page-save');
                var pageReset = document.getElementById('theme-page-reset');
                var allowedFonts = [
                    'app-font-family-inter',
                    'app-font-family-lato',
                    'app-font-family-rubik',
                    'app-font-family-cinzel',
                    'app-font-family-nunito',
                    'app-font-family-roboto',
                    'app-font-family-ubuntu',
                    'app-font-family-poppins',
                    'app-font-family-raleway',
                    'app-font-family-system-ui',
                    'app-font-family-noto-sans',
                    'app-font-family-fira-sans',
                    'app-font-family-work-sans',
                    'app-font-family-open-sans',
                    'app-font-family-montserrat',
                    'app-font-family-maven-pro',
                    'app-font-family-quicksand',
                    'app-font-family-josefin-sans',
                    'app-font-family-ibm-plex-sans',
                    'app-font-family-montserrat-alt',
                    'app-font-family-roboto-slab',
                    'app-font-family-source-sans-pro'
                ];

                function getSavedSkin(){
                    if (serverPrefs.skin === 'dark') return 'app-skin-dark';
                    if (serverPrefs.skin === 'light') return '';
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

                function getSavedMenuMode() {
                    try {
                        var menuState = localStorage.getItem('nexel-classic-dashboard-menu-mini-theme');
                        if (menuState === 'menu-mini-theme') return 'mini';
                        if (menuState === 'menu-expend-theme') return 'expanded';
                    } catch (e) {
                    }

                    if (serverPrefs.menu === 'mini' || serverPrefs.menu === 'expanded' || serverPrefs.menu === 'auto') {
                        return serverPrefs.menu;
                    }

                    return 'auto';
                }

                function getSavedFont() {
                    if (typeof serverPrefs.font === 'string' && serverPrefs.font !== '') {
                        return serverPrefs.font;
                    }
                    try {
                        var legacyFont = localStorage.getItem('font-family');
                        return legacyFont !== null ? legacyFont : 'default';
                    } catch (e) {
                        return 'default';
                    }
                }

                function getSavedNavigationMode() {
                    if (serverPrefs.navigation === 'dark' || serverPrefs.navigation === 'light') {
                        return serverPrefs.navigation;
                    }
                    try {
                        var nav = localStorage.getItem('app-navigation');
                        if (nav === 'app-navigation-dark') return 'dark';
                    } catch (e) {
                    }
                    return 'light';
                }

                function getSavedHeaderMode() {
                    if (serverPrefs.header === 'dark' || serverPrefs.header === 'light') {
                        return serverPrefs.header;
                    }
                    try {
                        var hdr = localStorage.getItem('app-header');
                        if (hdr === 'app-header-dark') return 'dark';
                    } catch (e) {
                    }
                    return 'light';
                }

                function clearFontClasses() {
                    var classes = document.documentElement.className || '';
                    document.documentElement.className = classes.replace(/\bapp-font-family-[^\s]+\b/g, '').replace(/\s{2,}/g, ' ').trim();
                }

                function applyFont(fontClass) {
                    var nextFont = (allowedFonts.indexOf(fontClass) !== -1) ? fontClass : 'default';
                    clearFontClasses();
                    if (nextFont !== 'default') {
                        document.documentElement.classList.add(nextFont);
                    }
                    try {
                        if (nextFont === 'default') {
                            localStorage.removeItem('font-family');
                        } else {
                            localStorage.setItem('font-family', nextFont);
                        }
                    } catch (e) {
                    }
                    return nextFont;
                }

                function applyNavigationMode(mode) {
                    var next = mode === 'dark' ? 'dark' : 'light';
                    document.documentElement.classList.remove('app-navigation-dark');
                    if (next === 'dark') {
                        document.documentElement.classList.add('app-navigation-dark');
                    }
                    try {
                        localStorage.setItem('app-navigation', next === 'dark' ? 'app-navigation-dark' : 'app-navigation-light');
                    } catch (e) {
                    }
                    return next;
                }

                function applyHeaderMode(mode) {
                    var next = mode === 'dark' ? 'dark' : 'light';
                    document.documentElement.classList.remove('app-header-dark');
                    if (next === 'dark') {
                        document.documentElement.classList.add('app-header-dark');
                    }
                    try {
                        localStorage.setItem('app-header', next === 'dark' ? 'app-header-dark' : 'app-header-light');
                    } catch (e) {
                    }
                    return next;
                }

                function saveThemePreferences(payload) {
                    if (!window.fetch) return;
                    var endpoint = window.__bioternThemeApi || 'api/theme-customizer.php';
                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload || {})
                    }).catch(function(){
                    });
                }

                function applyMenuMode(mode) {
                    var nextMode = (mode === 'mini' || mode === 'expanded') ? mode : 'auto';
                    var width = window.innerWidth || document.documentElement.clientWidth || 0;

                    if (nextMode === 'mini') {
                        document.documentElement.classList.add('minimenu');
                        try { localStorage.setItem('nexel-classic-dashboard-menu-mini-theme', 'menu-mini-theme'); } catch (e) {}
                        return;
                    }

                    if (nextMode === 'expanded') {
                        document.documentElement.classList.remove('minimenu');
                        try { localStorage.setItem('nexel-classic-dashboard-menu-mini-theme', 'menu-expend-theme'); } catch (e) {}
                        return;
                    }

                    try { localStorage.removeItem('nexel-classic-dashboard-menu-mini-theme'); } catch (e) {}
                    if (width >= 1024 && width <= 1600) {
                        document.documentElement.classList.add('minimenu');
                    } else {
                        document.documentElement.classList.remove('minimenu');
                    }
                }

                function currentSkinValue() {
                    return document.documentElement.classList.contains('app-skin-dark') ? 'dark' : 'light';
                }

                function currentFontValue() {
                    for (var i = 0; i < allowedFonts.length; i++) {
                        if (document.documentElement.classList.contains(allowedFonts[i])) {
                            return allowedFonts[i];
                        }
                    }
                    return 'default';
                }

                function currentNavigationValue() {
                    return document.documentElement.classList.contains('app-navigation-dark') ? 'dark' : 'light';
                }

                function currentHeaderValue() {
                    return document.documentElement.classList.contains('app-header-dark') ? 'dark' : 'light';
                }

                function setDark(isDark, persist){
                    if(isDark){
                        document.documentElement.classList.add('app-skin-dark');
                        try{
                            localStorage.setItem('app-skin','app-skin-dark');
                            // Keep legacy key in sync for older scripts.
                            localStorage.setItem('app-skin-dark','app-skin-dark');
                        }catch(e){}
                        if(darkBtn) darkBtn.style.display = 'none';
                        if(lightBtn) lightBtn.style.display = '';
                        if (persist !== false) {
                            saveThemePreferences({
                                skin: 'dark',
                                menu: getSavedMenuMode(),
                                font: currentFontValue(),
                                navigation: currentNavigationValue(),
                                header: currentHeaderValue()
                            });
                        }
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
                        if (persist !== false) {
                            saveThemePreferences({
                                skin: 'light',
                                menu: getSavedMenuMode(),
                                font: currentFontValue(),
                                navigation: currentNavigationValue(),
                                header: currentHeaderValue()
                            });
                        }
                    }

                    syncCustomizerInputs();
                }

                function syncCustomizerInputs() {
                    var skin = currentSkinValue();
                    var menu = getSavedMenuMode();
                    var font = currentFontValue();
                    var navigation = currentNavigationValue();
                    var header = currentHeaderValue();
                    var navLightRadio = document.getElementById('theme-page-navigation-light');
                    var navDarkRadio = document.getElementById('theme-page-navigation-dark');
                    var headerLightRadio = document.getElementById('theme-page-header-light');
                    var headerDarkRadio = document.getElementById('theme-page-header-dark');

                    if (pageSkinDark) pageSkinDark.checked = skin === 'dark';
                    if (pageSkinLight) pageSkinLight.checked = skin !== 'dark';
                    if (pageMenu) pageMenu.value = menu;
                    if (pageFont) pageFont.value = font;
                    if (pageNavigation) pageNavigation.value = navigation;
                    if (pageHeader) pageHeader.value = header;
                    if (navDarkRadio) navDarkRadio.checked = navigation === 'dark';
                    if (navLightRadio) navLightRadio.checked = navigation !== 'dark';
                    if (headerDarkRadio) headerDarkRadio.checked = header === 'dark';
                    if (headerLightRadio) headerLightRadio.checked = header !== 'dark';
                }

                var s = getSavedSkin();
                var isDark = (typeof s === 'string' && s.indexOf('dark') !== -1) || document.documentElement.classList.contains('app-skin-dark');
                applyFont(getSavedFont());
                applyNavigationMode(getSavedNavigationMode());
                applyHeaderMode(getSavedHeaderMode());
                setDark(isDark, false);
                applyMenuMode(getSavedMenuMode());
                syncCustomizerInputs();

                if(darkBtn) darkBtn.addEventListener('click', function(e){ e.preventDefault(); setDark(true, true); });
                if(lightBtn) lightBtn.addEventListener('click', function(e){ e.preventDefault(); setDark(false, true); });

                function resolveSelectedSkin(lightEl, darkEl) {
                    if (darkEl && darkEl.checked) return 'dark';
                    if (lightEl && lightEl.checked) return 'light';
                    return currentSkinValue();
                }

                if (pageSave) {
                    pageSave.addEventListener('click', function () {
                        var skin = resolveSelectedSkin(pageSkinLight, pageSkinDark);
                        var menu = pageMenu ? pageMenu.value : getSavedMenuMode();
                        var font = pageFont ? pageFont.value : currentFontValue();
                        var navigation = pageNavigation ? pageNavigation.value : currentNavigationValue();
                        var header = pageHeader ? pageHeader.value : currentHeaderValue();
                        setDark(skin === 'dark', false);
                        applyMenuMode(menu);
                        font = applyFont(font);
                        navigation = applyNavigationMode(navigation);
                        header = applyHeaderMode(header);
                        saveThemePreferences({ skin: skin, menu: menu, font: font, navigation: navigation, header: header });
                        syncCustomizerInputs();
                    });
                }

                if (pageReset) {
                    pageReset.addEventListener('click', function () {
                        setDark(false, false);
                        applyMenuMode('auto');
                        applyFont('default');
                        applyNavigationMode('light');
                        applyHeaderMode('light');
                        saveThemePreferences({ skin: 'light', menu: 'auto', font: 'default', navigation: 'light', header: 'light' });
                        syncCustomizerInputs();
                    });
                }
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




