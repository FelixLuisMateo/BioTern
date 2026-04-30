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
        dialog.style.removeProperty('position');
        dialog.style.removeProperty('top');
        dialog.style.removeProperty('left');
        dialog.style.removeProperty('right');
        dialog.style.removeProperty('transform');
        dialog.style.removeProperty('margin');
        dialog.style.removeProperty('width');
        dialog.style.removeProperty('max-width');
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

    function formatLocalDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function addMonths(date, months) {
        var next = new Date(date.getTime());
        next.setMonth(next.getMonth() + months);
        return next;
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
        var endDateInput = modal.querySelector('input[name="end_date"]');

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
            if (dateInput && endDateInput) {
                var startDateValue = dateInput.value || new Date().toISOString().slice(0, 10);
                var startDate = new Date(startDateValue + 'T00:00:00');
                var defaultEndDate = formatLocalDate(addMonths(startDate, 1));
                endDateInput.min = startDateValue;
                if (!endDateInput.value || endDateInput.value < startDateValue) {
                    endDateInput.value = defaultEndDate;
                }
                dateInput.addEventListener('change', function () {
                    var currentStart = dateInput.value || new Date().toISOString().slice(0, 10);
                    var currentStartDate = new Date(currentStart + 'T00:00:00');
                    endDateInput.min = currentStart;
                    if (!endDateInput.value || endDateInput.value < currentStart) {
                        endDateInput.value = formatLocalDate(addMonths(currentStartDate, 1));
                    }
                }, { once: true });
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
            if (endDateInput) {
                endDateInput.value = '';
                endDateInput.min = '';
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
