(function () {
    function initPasswordToggle() {
        var toggle = document.querySelector('[data-account-password-toggle]');
        if (!toggle) {
            return;
        }

        var fields = document.querySelectorAll('[data-account-password-field]');
        var label = document.querySelector('[data-account-password-toggle-label]');

        function sync() {
            var visible = !!toggle.checked;
            fields.forEach(function (field) {
                field.type = visible ? 'text' : 'password';
            });
            if (label) {
                label.textContent = visible ? 'Hide passwords' : 'Show passwords';
            }
        }

        toggle.addEventListener('change', sync);
        sync();
    }

    function initAnchorMenu() {
        var links = Array.prototype.slice.call(document.querySelectorAll('[data-settings-anchor]'));
        if (!links.length) {
            return;
        }

        var sections = links.map(function (link) {
            var target = link.getAttribute('href') || '';
            if (target.charAt(0) !== '#') {
                return null;
            }
            var section = document.querySelector(target);
            return section ? { link: link, section: section } : null;
        }).filter(Boolean);

        if (!sections.length) {
            return;
        }

        function setActive(activeId) {
            sections.forEach(function (entry) {
                entry.link.classList.toggle('is-active', ('#' + entry.section.id) === activeId);
            });
        }

        function syncActive() {
            var scrollEdge = window.scrollY + 180;
            var current = '#' + sections[0].section.id;
            sections.forEach(function (entry) {
                if (entry.section.offsetTop <= scrollEdge) {
                    current = '#' + entry.section.id;
                }
            });
            setActive(current);
        }

        links.forEach(function (link) {
            link.addEventListener('click', function () {
                setActive(link.getAttribute('href'));
            });
        });

        window.addEventListener('scroll', syncActive, { passive: true });
        syncActive();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initPasswordToggle();
            initAnchorMenu();
        });
    } else {
        initPasswordToggle();
        initAnchorMenu();
    }
})();
