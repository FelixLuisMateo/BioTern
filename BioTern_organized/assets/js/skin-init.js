// Apply saved skin as early as possible to avoid flash-of-unstyled (FOUS)
(function(){
    try {
        var skin = localStorage.getItem('app-skin-dark');
        if (skin === 'app-skin-dark') {
            document.documentElement.classList.add('app-skin-dark');
        }
    } catch (e) {
        /* ignore */
    }
})();
