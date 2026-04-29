(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[char];
        });
    }

    function tableText(cell) {
        if (!cell) {
            return '';
        }

        var clone = cell.cloneNode(true);
        clone.querySelectorAll('[data-print-exclude="1"]').forEach(function (node) {
            node.remove();
        });
        clone.querySelectorAll('input, button, select, textarea, script').forEach(function (node) {
            node.remove();
        });
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function isPrintableColumn(cell) {
        return cell && !cell.classList.contains('app-ojt-select-column') && cell.getAttribute('data-print-exclude') !== '1';
    }

    function getSelectedRows(table) {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function (row) {
            var checkbox = row.querySelector('[data-ojt-row-select]');
            return !!(checkbox && checkbox.checked);
        });
    }

    function getPrintableRows(table, selectedOnly) {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function (row) {
            if (row.querySelector('td[colspan]')) {
                return false;
            }

            if (!selectedOnly) {
                return row.querySelectorAll('td').length > 0;
            }

            var checkbox = row.querySelector('[data-ojt-row-select]');
            return !!(checkbox && checkbox.checked);
        });
    }

    function getRowCells(row) {
        return Array.prototype.slice.call(row.children).filter(function (cell) {
            return isPrintableColumn(cell);
        });
    }

    function getHeaderCells(table) {
        return Array.prototype.slice.call(table.querySelectorAll('thead th')).filter(function (cell) {
            return isPrintableColumn(cell);
        });
    }

    function buildPrintRows(table, rows, emptyMessage) {
        var headerCells = getHeaderCells(table);
        var headers = headerCells.map(function (headerCell) {
            return '<th>' + escapeHtml(tableText(headerCell)) + '</th>';
        }).join('');

        var bodyRows = rows.map(function (row, index) {
            var cells = getRowCells(row).map(function (cell) {
                return '<td>' + escapeHtml(tableText(cell)) + '</td>';
            }).join('');
            return '<tr><td class="print-index">' + (index + 1) + '</td>' + cells + '</tr>';
        }).join('');

        if (!bodyRows) {
            bodyRows = '<tr><td class="print-index">1</td><td colspan="' + (headerCells.length + 1) + '">' + escapeHtml(emptyMessage || 'No rows found.') + '</td></tr>';
        }

        return {
            headers: headers,
            rows: bodyRows
        };
    }

    function fillPrintSheet(table, printSheet, printRows) {
        var titleNode = printSheet.querySelector('[data-ojt-print-title]');
        var subtitleNode = printSheet.querySelector('[data-ojt-print-subtitle]');
        var headNode = printSheet.querySelector('thead tr');
        var bodyNode = printSheet.querySelector('tbody');

        if (titleNode) {
            titleNode.textContent = table.getAttribute('data-print-title') || 'Selected List';
        }
        if (subtitleNode) {
            subtitleNode.textContent = table.getAttribute('data-print-subtitle') || '';
            subtitleNode.hidden = !subtitleNode.textContent;
        }
        if (headNode) {
            headNode.innerHTML = '<th class="print-index">#</th>' + printRows.headers;
        }
        if (bodyNode) {
            bodyNode.innerHTML = printRows.rows;
        }
    }

    function printSheetNow(printSheet, selectedMode) {
        if (selectedMode) {
            document.body.classList.add('app-ojt-print-selected-mode');
            window.addEventListener('afterprint', function cleanupSelectedPrintMode() {
                document.body.classList.remove('app-ojt-print-selected-mode');
                window.removeEventListener('afterprint', cleanupSelectedPrintMode);
            });
        }

        window.setTimeout(function () {
            window.print();
        }, 50);
    }

    function initTable(table) {
        var printSheet = document.querySelector('[data-ojt-print-sheet="' + table.id + '"]');
        var selectedPrintBtns = Array.prototype.slice.call(document.querySelectorAll('[data-ojt-print-selected="' + table.id + '"]'));
        var fullPrintBtns = Array.prototype.slice.call(document.querySelectorAll('[data-ojt-print-full="' + table.id + '"]'));

        if (selectedPrintBtns.length === 0 && fullPrintBtns.length === 0) {
            return;
        }

        selectedPrintBtns.forEach(function (selectedPrintBtn) {
            selectedPrintBtn.addEventListener('click', function (event) {
                event.preventDefault();

                var selectedRows = getSelectedRows(table);
                if (selectedRows.length === 0) {
                    alert('Select at least one row to print.');
                    return;
                }

                if (!printSheet) {
                    alert('Print layout is not available for this list.');
                    return;
                }

                var printRows = buildPrintRows(table, selectedRows, 'No selected rows.');
                fillPrintSheet(table, printSheet, printRows);
                printSheetNow(printSheet, true);
            });
        });

        fullPrintBtns.forEach(function (fullPrintBtn) {
            fullPrintBtn.addEventListener('click', function (event) {
                event.preventDefault();

                if (!printSheet) {
                    alert('Print layout is not available for this list.');
                    return;
                }

                var allRows = getPrintableRows(table, false);
                var printRows = buildPrintRows(table, allRows, 'No rows found.');
                fillPrintSheet(table, printSheet, printRows);
                printSheetNow(printSheet, false);
            });
        });
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
