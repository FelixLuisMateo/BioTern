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

    function buildPrintHtml(table, selectedRows, options) {
        var title = escapeHtml(options.title || document.title || 'Selected List');
        var subtitle = escapeHtml(options.subtitle || '');
        var headers = getHeaderCells(table).map(function (headerCell) {
            return '<th>' + escapeHtml(tableText(headerCell)) + '</th>';
        }).join('');

        var rows = selectedRows.map(function (row, index) {
            var cells = getRowCells(row).map(function (cell) {
                return '<td>' + escapeHtml(tableText(cell)) + '</td>';
            }).join('');
            return '<tr><td class="print-index">' + (index + 1) + '</td>' + cells + '</tr>';
        }).join('');

        var emptyFallback = '<tr><td class="print-index">1</td><td colspan="' + (getHeaderCells(table).length + 1) + '">No selected rows.</td></tr>';

        return [
            '<!doctype html>',
            '<html>',
            '<head>',
            '<meta charset="utf-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            '<title>' + title + '</title>',
            '<style>',
            'body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111827;}',
            'h1{font-size:18px;margin:0 0 6px;}',
            '.subtitle{font-size:12px;color:#6b7280;margin:0 0 16px;}',
            'table{width:100%;border-collapse:collapse;font-size:12px;}',
            'th,td{border:1px solid #d1d5db;padding:8px 10px;vertical-align:top;text-align:left;}',
            'th{background:#f3f4f6;font-weight:700;}',
            '.print-index{width:40px;text-align:center;white-space:nowrap;}',
            '@media print{body{margin:12mm;}}',
            '</style>',
            '</head>',
            '<body>',
            '<h1>' + title + '</h1>',
            subtitle ? '<p class="subtitle">' + subtitle + '</p>' : '',
            '<table>',
            '<thead><tr><th class="print-index">#</th>' + headers + '</tr></thead>',
            '<tbody>',
            rows || emptyFallback,
            '</tbody>',
            '</table>',
            '</body>',
            '</html>'
        ].join('');
    }

    function openPrintWindow(html) {
        var win = window.open('', '_blank', 'noopener,noreferrer,width=1200,height=900');
        if (!win) {
            alert('Please allow popups to print the selected rows.');
            return null;
        }

        win.document.open();
        win.document.write(html);
        win.document.close();
        return win;
    }

    function initTable(table) {
        var printBtn = document.querySelector('[data-ojt-print-selected="' + table.id + '"]');
        if (!printBtn) {
            return;
        }

        printBtn.addEventListener('click', function (event) {
            event.preventDefault();
            var selectedRows = getSelectedRows(table);
            if (selectedRows.length === 0) {
                alert('Select at least one row to print.');
                return;
            }

            var html = buildPrintHtml(table, selectedRows, {
                title: table.getAttribute('data-print-title'),
                subtitle: table.getAttribute('data-print-subtitle')
            });
            var win = openPrintWindow(html);
            if (!win) {
                return;
            }

            win.onload = function () {
                win.focus();
                win.print();
            };
            setTimeout(function () {
                try {
                    win.focus();
                    win.print();
                } catch (error) {
                    // Ignore transient print timing issues.
                }
            }, 250);
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
