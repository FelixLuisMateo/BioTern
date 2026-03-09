// Apply saved skin + sidebar state as early as possible to avoid initial layout flash.
(function(){
    function getSavedSkin() {
        try {
            var primary = localStorage.getItem('app-skin');
            if (primary !== null) return primary;
            var alt = localStorage.getItem('app_skin');
            if (alt !== null) return alt;
            var theme = localStorage.getItem('theme');
            if (theme !== null) return theme;
            var legacy = localStorage.getItem('app-skin-dark');
            return legacy !== null ? legacy : '';
        } catch (e) {
            return '';
        }
    }

    var skin = getSavedSkin();
    if (typeof skin === 'string' && skin.indexOf('dark') !== -1) {
        document.documentElement.classList.add('app-skin-dark');
    } else {
        document.documentElement.classList.remove('app-skin-dark');
    }

    try {
        var menuState = localStorage.getItem('nexel-classic-dashboard-menu-mini-theme');
        var width = window.innerWidth || document.documentElement.clientWidth || 0;

        if (menuState === 'menu-mini-theme') {
            document.documentElement.classList.add('minimenu');
        } else if (menuState === 'menu-expend-theme') {
            document.documentElement.classList.remove('minimenu');
        } else {
            if (width >= 1024 && width <= 1600) {
                document.documentElement.classList.add('minimenu');
            } else if (width > 1600) {
                document.documentElement.classList.remove('minimenu');
            }
        }
    } catch (e) {
        /* ignore */
    }
})();
