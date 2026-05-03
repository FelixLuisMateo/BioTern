(function () {
    function isMobileViewport() {
        return window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches;
    }

    function initMobileRowCollapse() {
        var tables = document.querySelectorAll('.logs-mobile-table[data-mobile-collapse="true"]');
        if (!tables.length) {
            return;
        }

        tables.forEach(function (table) {
            var visibleCells = parseInt(table.getAttribute('data-mobile-visible-cells') || '3', 10);
            if (!Number.isFinite(visibleCells) || visibleCells < 1) {
                visibleCells = 3;
            }

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var cells = Array.prototype.slice.call(row.querySelectorAll('td'));
                if (!cells.length || cells.length <= visibleCells || cells.length === 1) {
                    return;
                }
                if (cells[0].hasAttribute('colspan')) {
                    return;
                }

                cells.forEach(function (cell, index) {
                    if (index >= visibleCells) {
                        cell.classList.add('logs-mobile-extra-cell');
                    }
                });

                if (!row.querySelector('.logs-mobile-collapse-cell')) {
                    var toggleRowCell = document.createElement('td');
                    toggleRowCell.className = 'logs-mobile-collapse-cell';
                    toggleRowCell.setAttribute('colspan', String(cells.length));

                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'logs-mobile-collapse-btn';
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'Show more';

                    btn.addEventListener('click', function () {
                        var isCollapsed = row.classList.contains('logs-mobile-row-collapsed');
                        row.classList.toggle('logs-mobile-row-collapsed', !isCollapsed);
                        btn.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
                        btn.textContent = isCollapsed ? 'Show less' : 'Show more';
                    });

                    toggleRowCell.appendChild(btn);
                    row.appendChild(toggleRowCell);
                }

                if (isMobileViewport()) {
                    row.classList.add('logs-mobile-row-collapsed');
                    var rowBtn = row.querySelector('.logs-mobile-collapse-btn');
                    if (rowBtn) {
                        rowBtn.setAttribute('aria-expanded', 'false');
                        rowBtn.textContent = 'Show more';
                    }
                } else {
                    row.classList.remove('logs-mobile-row-collapsed');
                }
            });
        });
    }

    function initAdminLogsAutoFilter() {
        var form = document.querySelector('.admin-logs-auto-filter');
        if (!form) {
            return;
        }

        var search = form.querySelector('input[name="search"]');
        var timer = null;

        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                form.requestSubmit();
            });
        });

        if (search) {
            search.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    form.requestSubmit();
                }, 450);
            });
        }

        var pageJump = document.getElementById('adminLogsPageJump');
        if (pageJump) {
            pageJump.addEventListener('change', function () {
                if (pageJump.form) {
                    pageJump.form.submit();
                }
            });
        }
    }

    function initMobileRowCollapseEvents() {
        initMobileRowCollapse();
        var resizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(initMobileRowCollapse, 160);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAdminLogsAutoFilter();
            initMobileRowCollapseEvents();
        });
        return;
    }

    initAdminLogsAutoFilter();
    initMobileRowCollapseEvents();
})();
