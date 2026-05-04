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

    function labelTextForControl(control) {
        if (!control) {
            return '';
        }

        var label = control.id ? document.querySelector('label[for="' + control.id.replace(/"/g, '\\"') + '"]') : null;
        var text = label ? label.textContent : (control.getAttribute('aria-label') || control.name || 'Filter');
        return String(text || 'Filter').replace(/\s+/g, ' ').replace(/[:*]+$/g, '').trim();
    }

    function controlDisplayValue(control) {
        if (!control || control.disabled || !control.name || control.type === 'hidden') {
            return '';
        }

        var tag = String(control.tagName || '').toLowerCase();
        var type = String(control.type || '').toLowerCase();

        if (['button', 'submit', 'reset', 'file', 'password'].indexOf(type) !== -1) {
            return '';
        }

        if ((type === 'checkbox' || type === 'radio') && !control.checked) {
            return '';
        }

        var rawValue = String(control.value || '').trim();
        if (rawValue === '' || rawValue === '0' || rawValue.toLowerCase() === 'all') {
            return '';
        }

        if (tag === 'select') {
            var option = control.options && control.selectedIndex >= 0 ? control.options[control.selectedIndex] : null;
            var optionText = option ? String(option.textContent || '').replace(/\s+/g, ' ').trim() : rawValue;
            if (/^all\s+/i.test(optionText)) {
                return '';
            }
            return optionText || rawValue;
        }

        return rawValue;
    }

    function currentFilterSubtitle(table) {
        var fallback = table.getAttribute('data-print-subtitle') || '';
        var form = null;
        var formSelector = table.getAttribute('data-print-filter-form');

        if (formSelector) {
            try {
                form = document.querySelector(formSelector);
            } catch (error) {
                form = null;
            }
        }

        if (!form) {
            var scope = table.closest('.card, .app-data-card, main, body');
            form = scope ? scope.querySelector('form.fingerprint-form, form.app-ojt-filter-form, form.filter-form, form') : null;
        }

        if (!form) {
            form = document.querySelector('form.fingerprint-form, form.app-ojt-filter-form, form.filter-form');
        }

        if (!form) {
            return fallback;
        }

        var parts = [];
        Array.prototype.slice.call(form.elements || []).forEach(function (control) {
            var value = controlDisplayValue(control);
            if (!value) {
                return;
            }
            parts.push(labelTextForControl(control) + ': ' + value);
        });

        return parts.length ? parts.join(' / ') : fallback;
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
        var printMode = table.getAttribute('data-print-mode') || '';

        if (printMode === 'student-section') {
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

        if (printMode === 'ojt-student-list') {
            var ojtRows = rows.map(function (row, index) {
                return (
                    '<tr>' +
                    '<td class="print-index">' + (index + 1) + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-student-no') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-last-name') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-first-name') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-middle-name') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-course-section') || '') + '</td>' +
                    '<td></td>' +
                    '</tr>'
                );
            }).join('');

            if (!ojtRows) {
                ojtRows = '<tr><td class="print-index">1</td><td colspan="6">' + escapeHtml(emptyMessage || 'No rows found.') + '</td></tr>';
            }

            return {
                headers: '<th>Student No.</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Course / Section</th><th>Remarks</th>',
                rows: ojtRows
            };
        }

        if (printMode === 'external-student-list' || printMode === 'internal-student-list') {
            var externalRows = rows.map(function (row, index) {
                return (
                    '<tr>' +
                    '<td class="print-index">' + (index + 1) + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-student-no') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-name') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-course-section') || '') + '</td>' +
                    '<td>' + escapeHtml(row.getAttribute('data-print-status') || '') + '</td>' +
                    '</tr>'
                );
            }).join('');

            if (!externalRows) {
                externalRows = '<tr><td class="print-index">1</td><td colspan="4">' + escapeHtml(emptyMessage || 'No rows found.') + '</td></tr>';
            }

            return {
                headers: '<th>Student ID</th><th>Name</th><th>Course / Section</th><th>Status</th>',
                rows: externalRows
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
            subtitleNode.textContent = currentFilterSubtitle(table);
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
        if (!printSheet) {
            return;
        }

        document.body.classList.add('app-ojt-print-selected-mode');
        printSheet.setAttribute('aria-hidden', 'false');

        var cleanup = function () {
            document.body.classList.remove('app-ojt-print-selected-mode');
            printSheet.setAttribute('aria-hidden', 'true');
            window.removeEventListener('afterprint', cleanup);
        };

        window.addEventListener('afterprint', cleanup);
        window.print();
        window.setTimeout(cleanup, 1200);
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
