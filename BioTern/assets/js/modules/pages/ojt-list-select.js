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
        var headerCheckbox = table.querySelector('[data-ojt-select-all]');
        var rowCheckboxes = Array.prototype.slice.call(table.querySelectorAll('[data-ojt-row-select]'));

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
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = headerCheckbox.checked;
                syncRowState(checkbox);
            });
            updateHeaderState(table, headerCheckbox, rowCheckboxes);
        });

        rowCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                syncRowState(checkbox);
                updateHeaderState(table, headerCheckbox, rowCheckboxes);
            });
            syncRowState(checkbox);
        });

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
