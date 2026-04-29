(function () {
    'use strict';

    function forceCenterDialog(modal) {
        if (!modal) {
            return;
        }
        var dialog = modal.querySelector('.modal-dialog');
        if (!dialog) {
            return;
        }

        var isDesktopWide = window.matchMedia('(min-width: 992px)').matches;
        var isShellDesktop = window.matchMedia('(min-width: 1025px)').matches;
        var isMobile = window.matchMedia('(max-width: 575.98px)').matches;
        var dialogWidth = isMobile ? 'calc(100vw - 1.5rem)' : (isDesktopWide ? 'min(88vw, 720px)' : 'min(92vw, 640px)');
        var shellOffset = 0;

        if (isShellDesktop) {
            shellOffset = document.documentElement.classList.contains('minimenu') ? 50 : 140;
        }

        dialog.style.setProperty('position', 'fixed', 'important');
        dialog.style.setProperty('top', '50%', 'important');
        dialog.style.setProperty('left', 'calc(50% + ' + shellOffset + 'px)', 'important');
        dialog.style.setProperty('right', 'auto', 'important');
        dialog.style.setProperty('transform', 'translate(-50%, -50%)', 'important');
        dialog.style.setProperty('margin', '0', 'important');
        dialog.style.setProperty('width', dialogWidth, 'important');
        dialog.style.setProperty('max-width', dialogWidth, 'important');
    }

    function collectSelectedStudentIds(table) {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr')).map(function (row) {
            var checkbox = row.querySelector('[data-ojt-row-select]');
            var studentId = parseInt(row.getAttribute('data-ojt-student-row-id') || '0', 10);
            return checkbox && checkbox.checked && studentId > 0 ? studentId : 0;
        }).filter(function (value) {
            return value > 0;
        });
    }

    function collectSelectedStudents(table) {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr')).map(function (row) {
            var checkbox = row.querySelector('[data-ojt-row-select]');
            var studentId = parseInt(row.getAttribute('data-ojt-student-row-id') || '0', 10);
            var label = row.getAttribute('data-ojt-student-label') || '';
            return checkbox && checkbox.checked && studentId > 0 ? {
                id: studentId,
                label: label || ('Student #' + studentId)
            } : null;
        }).filter(function (value) {
            return !!value;
        });
    }

    function initModal() {
        var table = document.getElementById('ojtInternalListTable');
        var modal = document.getElementById('ojtInternalActionModal');
        if (!table || !modal || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        var summaryNode = modal.querySelector('[data-ojt-internal-action-summary]');
        var selectionCountNode = modal.querySelector('[data-ojt-internal-selection-count]');
        var selectionListNode = modal.querySelector('[data-ojt-internal-selection-list]');
        var idsInput = modal.querySelector('input[name="student_ids"]');
        var singleIdInput = modal.querySelector('input[name="student_id"]');
        var dateInput = modal.querySelector('input[name="start_date"]');

        modal.addEventListener('show.bs.modal', function (event) {
            forceCenterDialog(modal);

            var trigger = event.relatedTarget;
            var clickedRow = trigger ? trigger.closest('tr') : null;
            var clickedId = clickedRow ? parseInt(clickedRow.getAttribute('data-ojt-student-row-id') || '0', 10) : 0;
            var clickedLabel = clickedRow ? (clickedRow.getAttribute('data-ojt-student-label') || '') : '';
            var selectedStudents = collectSelectedStudents(table);
            var ids = selectedStudents.length > 0 ? selectedStudents.map(function (student) { return student.id; }) : (clickedId > 0 ? [clickedId] : []);
            var displayStudents = selectedStudents.length > 0 ? selectedStudents : (clickedId > 0 ? [{ id: clickedId, label: clickedLabel || ('Student #' + clickedId) }] : []);

            if (idsInput) {
                idsInput.value = ids.join(',');
            }
            if (singleIdInput) {
                singleIdInput.value = ids.length === 1 ? String(ids[0]) : '';
            }
            if (dateInput && !dateInput.value) {
                dateInput.value = new Date().toISOString().slice(0, 10);
            }
            if (summaryNode) {
                if (ids.length > 1) {
                    summaryNode.textContent = ids.length + ' selected students will be started internally.';
                } else if (clickedLabel) {
                    summaryNode.textContent = 'Start internal for ' + clickedLabel + '.';
                } else {
                    summaryNode.textContent = 'Choose one row or use the checked rows.';
                }
            }
            if (selectionCountNode) {
                selectionCountNode.textContent = ids.length + (ids.length === 1 ? ' student' : ' students') + ' selected';
            }
            if (selectionListNode) {
                selectionListNode.innerHTML = displayStudents.map(function (student, index) {
                    return '<span class="ojt-internal-selection-item">' +
                        '<span class="ojt-internal-selection-index">' + (index + 1) + '</span>' +
                        '<span class="ojt-internal-selection-label">' + String(student.label || '').replace(/[&<>'"]/g, function (char) {
                            return {
                                '&': '&amp;',
                                '<': '&lt;',
                                '>': '&gt;',
                                "'": '&#39;',
                                '"': '&quot;'
                            }[char];
                        }) + '</span>' +
                    '</span>';
                }).join('');
            }
        });

        modal.addEventListener('shown.bs.modal', function () {
            forceCenterDialog(modal);
        });

        window.addEventListener('resize', function () {
            if (modal.classList.contains('show')) {
                forceCenterDialog(modal);
            }
        });

        modal.addEventListener('hidden.bs.modal', function () {
            if (idsInput) {
                idsInput.value = '';
            }
            if (singleIdInput) {
                singleIdInput.value = '';
            }
            if (selectionCountNode) {
                selectionCountNode.textContent = '0 selected';
            }
            if (selectionListNode) {
                selectionListNode.innerHTML = '';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModal);
    } else {
        initModal();
    }
})();
