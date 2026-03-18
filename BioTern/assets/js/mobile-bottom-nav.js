(function () {
    function normalize(value) {
        return (value || '').toLowerCase().trim();
    }

    function getCurrentRoute() {
        var path = window.location.pathname || '';
        var parts = path.split('/');
        return normalize(parts[parts.length - 1]);
    }

    function applyActiveState(nav) {
        var current = getCurrentRoute();
        if (!current) return;

        var links = nav.querySelectorAll('.biotern-bottom-link[data-routes]');
        links.forEach(function (link) {
            var raw = link.getAttribute('data-routes') || '';
            var routes = raw
                .split(',')
                .map(function (route) {
                    return normalize(route);
                })
                .filter(Boolean);

            if (!routes.length) return;
            if (routes.indexOf(current) === -1) return;

            nav.querySelectorAll('.biotern-bottom-link.active').forEach(function (activeLink) {
                if (activeLink !== link) {
                    activeLink.classList.remove('active');
                    activeLink.removeAttribute('aria-current');
                }
            });

            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        });
    }

    function initSheet(nav) {
        var sheet = document.getElementById('bioternBottomSheet');
        if (!sheet) return;

        var contentPanels = sheet.querySelectorAll('.biotern-bottom-sheet-content[data-panel]');

        function setPanel(target, trigger) {
            if (!target) return;
            sheet.dataset.activePanel = target;

            contentPanels.forEach(function (panel) {
                var isActive = panel.getAttribute('data-panel') === target;
                panel.classList.toggle('is-active', isActive);
            });

            nav.querySelectorAll('.biotern-bottom-link.is-open').forEach(function (btn) {
                if (btn !== trigger) {
                    btn.classList.remove('is-open');
                }
            });

            if (trigger) {
                trigger.classList.add('is-open');
            }
        }

        function openSheet(target, trigger) {
            setPanel(target, trigger);
            sheet.classList.add('is-open');
            sheet.setAttribute('aria-hidden', 'false');
        }

        function closeSheet() {
            sheet.classList.remove('is-open');
            sheet.setAttribute('aria-hidden', 'true');
            nav.querySelectorAll('.biotern-bottom-link.is-open').forEach(function (btn) {
                btn.classList.remove('is-open');
            });
        }

        nav.querySelectorAll('.biotern-bottom-link[data-panel-target]').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                var target = btn.getAttribute('data-panel-target') || '';
                if (!target) return;
                var isOpen = sheet.classList.contains('is-open');
                var isSame = sheet.dataset.activePanel === target;
                if (isOpen && isSame) {
                    closeSheet();
                } else {
                    openSheet(target, btn);
                }
            });
        });

        var backdrop = sheet.querySelector('[data-sheet-close]');
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                closeSheet();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSheet();
            }
        });

        sheet.querySelectorAll('.biotern-bottom-sheet-link').forEach(function (link) {
            link.addEventListener('click', function () {
                closeSheet();
            });
        });
    }

    function init() {
        var nav = document.querySelector('.biotern-bottom-nav');
        if (!nav) return;
        applyActiveState(nav);
        initSheet(nav);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
