(function () {
    'use strict';

    function updateHeaderState(table, headerCheckbox, rowCheckboxes) {
        if (!headerCheckbox) {
            return;
        }

        var checkedCount = rowCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        headerCheckbox.checked = rowCheckboxes.length > 0 && checkedCount === rowCheckboxes.length;
        headerCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
        table.classList.toggle('has-ojt-row-selection', checkedCount > 0);
        table.dispatchEvent(new CustomEvent('ojt-selection-change', {
            bubbles: true,
            detail: {
                selectedCount: checkedCount
            }
        }));
    }

    function initTable(table) {
        var dataTable = null;
        var hasPlaceholderRow = !!table.querySelector('tbody td[colspan]');
        if (
            !hasPlaceholderRow &&
            table.id &&
            table.id !== 'ojtListTable' &&
            window.jQuery &&
            window.jQuery.fn &&
            typeof window.jQuery.fn.DataTable === 'function' &&
            !window.jQuery.fn.DataTable.isDataTable('#' + table.id)
        ) {
            dataTable = window.jQuery('#' + table.id).DataTable({
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, 'All']
                ],
                lengthChange: false,
                dom: 'rtip',
                order: [],
                columnDefs: [
                    { orderable: false, targets: [0] }
                ]
            });
        } else if (
            !hasPlaceholderRow &&
            table.id &&
            window.jQuery &&
            window.jQuery.fn &&
            typeof window.jQuery.fn.DataTable === 'function' &&
            window.jQuery.fn.DataTable.isDataTable('#' + table.id)
        ) {
            dataTable = window.jQuery('#' + table.id).DataTable();
        }

        var viewAllButton = table.id ? document.querySelector('[data-view-all-table="' + table.id + '"]') : null;
        if (viewAllButton && dataTable) {
            function syncViewAllLabel() {
                viewAllButton.textContent = dataTable.page.len() === -1 ? 'Show paged list' : 'View all list';
            }
            viewAllButton.addEventListener('click', function () {
                dataTable.page.len(dataTable.page.len() === -1 ? 10 : -1).draw();
                syncViewAllLabel();
                updateHeaderState(table, headerCheckbox, getRowCheckboxes());
            });
            syncViewAllLabel();
        }

        var headerCheckbox = table.querySelector('[data-ojt-select-all]');
        function getRowCheckboxes() {
            if (dataTable) {
                return Array.prototype.slice.call(dataTable.rows().nodes().to$().find('[data-ojt-row-select]'));
            }
            return Array.prototype.slice.call(table.querySelectorAll('[data-ojt-row-select]'));
        }
        var rowCheckboxes = getRowCheckboxes();

        if (!headerCheckbox || rowCheckboxes.length === 0) {
            return;
        }

        function syncRowState(checkbox) {
            var row = checkbox.closest('tr');
            if (row) {
                row.classList.toggle('is-selected', checkbox.checked);
            }
        }

        headerCheckbox.addEventListener('change', function () {
            rowCheckboxes = getRowCheckboxes();
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = headerCheckbox.checked;
                syncRowState(checkbox);
            });
            updateHeaderState(table, headerCheckbox, rowCheckboxes);
        });

        rowCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                syncRowState(checkbox);
                updateHeaderState(table, headerCheckbox, getRowCheckboxes());
            });
            syncRowState(checkbox);
        });

        if (dataTable) {
            window.jQuery('#' + table.id).on('draw.dt', function () {
                rowCheckboxes = getRowCheckboxes();
                rowCheckboxes.forEach(syncRowState);
                updateHeaderState(table, headerCheckbox, rowCheckboxes);
            });
        }

        updateHeaderState(table, headerCheckbox, rowCheckboxes);
    }

    function init() {
        document.querySelectorAll('[data-ojt-select-table]').forEach(initTable);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
