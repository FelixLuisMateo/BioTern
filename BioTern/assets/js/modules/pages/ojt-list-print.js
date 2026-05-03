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
        if (table.getAttribute('data-print-mode') === 'student-section') {
            var studentRows = rows.map(function (row, index) {
                return (
                    '<tr>' +
                    '<td class="print-index">' + (index + 1) + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-student-no') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-last-name') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-first-name') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-middle-name') || '') + '</td>' +
                    '<td></td>' +
                    '</tr>'
                );
            }).join('');

            if (!studentRows) {
                studentRows = '<tr><td class="print-index">1</td><td colspan="5">' + escapeHtml(emptyMessage || 'No rows found.') + '</td></tr>';
            }

            return {
                headers: '<th>Student No.</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Remarks</th>',
                rows: studentRows
            };
        }

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

    function openPrintPreview(printSheet) {
        var preview = window.open('', '_blank');
        if (!preview) {
            alert('Allow pop-ups so the print preview can open in a new tab.');
            return;
        }

        var styles = Array.prototype.slice.call(document.querySelectorAll('link[rel="stylesheet"], style')).map(function (node) {
            return node.outerHTML;
        }).join('\n');
        var sheetHtml = printSheet.outerHTML.replace('aria-hidden="true"', 'aria-hidden="false"');

        preview.document.open();
        preview.document.write(
            '<!doctype html><html><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<title>Print Preview</title>' +
            styles +
            '<style>' +
            'body{background:#fff;margin:0;padding:24px;color:#111;font-family:Arial,sans-serif;}' +
            '.print-preview-toolbar{position:sticky;top:0;z-index:10;display:flex;justify-content:flex-end;gap:8px;padding:10px 0 18px;background:#fff;}' +
            '.print-preview-toolbar button{border:0;border-radius:6px;padding:9px 14px;font-weight:700;cursor:pointer;}' +
            '.print-preview-toolbar .primary{background:#0ea5e9;color:#fff;}' +
            '.print-preview-toolbar .light{background:#eef2f7;color:#172033;}' +
            '.student-list-print-sheet,.ojt-print-sheet{display:block!important;position:static!important;visibility:visible!important;}' +
            '@media print{body{padding:0}.print-preview-toolbar{display:none!important}}' +
            '</style></head><body>' +
            '<div class="print-preview-toolbar"><button class="light" type="button" onclick="window.close()">Close</button><button class="primary" type="button" onclick="window.print()">Print</button></div>' +
            sheetHtml +
            '</body></html>'
        );
        preview.document.close();
        preview.focus();
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
                openPrintPreview(printSheet);
            });
        });

        function updateSelectedPrintButtons(selectedCount) {
            selectedPrintBtns.forEach(function (selectedPrintBtn) {
                selectedPrintBtn.classList.toggle('d-none', selectedCount === 0);
                selectedPrintBtn.setAttribute('aria-hidden', selectedCount === 0 ? 'true' : 'false');
                var label = selectedPrintBtn.querySelector('span');
                if (label) {
                    label.textContent = selectedCount > 0 ? 'Print Selected (' + selectedCount + ')' : 'Print Selected';
                }
            });
        }

        table.addEventListener('ojt-selection-change', function (event) {
            updateSelectedPrintButtons((event.detail && event.detail.selectedCount) || 0);
        });
        updateSelectedPrintButtons(getSelectedRows(table).length);

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
                openPrintPreview(printSheet);
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
