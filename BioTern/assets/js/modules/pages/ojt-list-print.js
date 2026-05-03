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
        var baseHref = document.baseURI || window.location.href;

        var styles = Array.prototype.slice.call(document.querySelectorAll('link[rel="stylesheet"], style')).map(function (node) {
            return node.outerHTML;
        }).join('\n');
        var sheetHtml = printSheet.outerHTML.replace('aria-hidden="true"', 'aria-hidden="false"');

        preview.document.open();
        preview.document.write(
            '<!doctype html><html><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<base href="' + escapeHtml(baseHref) + '">' +
            '<title>Print Preview</title>' +
            styles +
            '<style>' +
            'body{background:#fff;margin:0;padding:24px;color:#111;font-family:Arial,sans-serif;}' +
            '.print-preview-toolbar{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:10px 0 14px;background:#fff;}' +
            '.print-preview-toolbar button{appearance:none;border:0;border-radius:8px;padding:10px 16px;font:700 16px/1.2 Arial,sans-serif;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;min-height:0!important;height:auto!important;min-width:0!important;width:auto!important;max-width:none!important;}' +
            '.print-preview-toolbar .primary{background:#0ea5e9;color:#fff;}' +
            '.print-preview-toolbar .light{background:#eef2f7;color:#172033;}' +
            '.student-list-print-sheet,.ojt-print-sheet{display:block!important;position:static!important;visibility:visible!important;width:100%!important;max-width:100%!important;background:#fff!important;color:#111!important;font-family:Arial,Helvetica,sans-serif!important;font-size:12px!important;padding:18px 24px!important;box-sizing:border-box!important;}' +
            '.student-list-print-sheet .header,.ojt-print-sheet .header{position:relative!important;overflow:visible!important;height:auto!important;min-height:1.1in!important;text-align:center!important;border-bottom:1px solid #8ab0e6;padding:.08in 0 .06in 1.05in!important;margin-bottom:14px;z-index:2;}' +
            '.student-list-print-sheet .crest,.ojt-print-sheet .crest{position:absolute!important;display:block!important;top:.18in!important;left:.18in!important;width:.8in!important;height:.8in!important;max-width:.8in!important;max-height:.8in!important;object-fit:contain!important;z-index:5!important;opacity:1!important;visibility:visible!important;}' +
            '.student-list-print-sheet .header h2,.ojt-print-sheet .header h2{font-family:Calibri,Arial,sans-serif;color:#1b4f9c;font-size:14pt;margin:6px 0 2px;font-weight:700;text-transform:uppercase;}' +
            '.student-list-print-sheet .header .meta,.student-list-print-sheet .header .tel,.ojt-print-sheet .header .meta,.ojt-print-sheet .header .tel{font-family:Calibri,Arial,sans-serif;color:#1b4f9c;}' +
            '.student-list-print-sheet .header .meta,.ojt-print-sheet .header .meta{font-size:10pt;}' +
            '.student-list-print-sheet .header .tel,.ojt-print-sheet .header .tel{font-size:12pt;}' +
            '.student-list-print-sheet .print-title,.ojt-print-sheet .print-title{text-align:center;font-size:34px;letter-spacing:1px;font-weight:700;margin:26px 0 22px;}' +
            '.student-list-print-sheet .print-meta,.ojt-print-sheet .print-meta{margin-bottom:14px;font-size:13px;}' +
            '.student-list-print-sheet .print-meta strong,.ojt-print-sheet .print-meta strong{min-width:76px;display:inline-block;}' +
            '.student-list-print-sheet table,.ojt-print-sheet table{width:100%;border-collapse:collapse;font-size:12px;}' +
            '.student-list-print-sheet th,.student-list-print-sheet td,.ojt-print-sheet th,.ojt-print-sheet td{border:1px solid #d9d9d9;padding:8px 8px;text-align:left;}' +
            '.student-list-print-sheet th,.ojt-print-sheet th{text-transform:uppercase;font-weight:700;background:#f8f8f8;}' +
            '.student-list-print-sheet .print-index,.ojt-print-sheet .print-index,.student-list-print-sheet td.col-index,.student-list-print-sheet th.col-index,.ojt-print-sheet td.col-index,.ojt-print-sheet th.col-index{width:46px;text-align:center;white-space:nowrap;}' +
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
