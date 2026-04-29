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

    function initModal() {
        var modal = document.getElementById('ojtExternalActionModal');
        if (!modal || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        var summaryNode = modal.querySelector('[data-ojt-external-action-summary]');
        var currentNode = modal.querySelector('[data-ojt-external-action-current]');
        var viewLink = modal.querySelector('[data-ojt-external-action-view]');

        modal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            var row = trigger ? trigger.closest('tr') : null;
            var rowId = row ? parseInt(row.getAttribute('data-ojt-external-row-id') || '0', 10) : 0;
            var studentNo = row ? (row.getAttribute('data-ojt-external-row-no') || '') : '';
            var label = row ? (row.getAttribute('data-ojt-external-row-label') || '') : '';
            var course = row ? (row.getAttribute('data-ojt-external-row-course') || '') : '';
            var section = row ? (row.getAttribute('data-ojt-external-row-section') || '') : '';
            var viewHref = trigger ? (trigger.getAttribute('data-ojt-row-href') || '') : '';

            if (summaryNode) {
                summaryNode.textContent = label ? ('Actions for ' + label + '.') : 'Choose an action for this student.';
            }

            if (currentNode) {
                currentNode.innerHTML = [
                    '<div><strong>' + escapeHtml(label || 'External Student') + '</strong></div>',
                    studentNo ? '<div class="mt-1">Student No: ' + escapeHtml(studentNo) + '</div>' : '',
                    course ? '<div>Course: ' + escapeHtml(course) + '</div>' : '',
                    section ? '<div>Section: ' + escapeHtml(section) + '</div>' : '',
                    rowId > 0 ? '<div>Record ID: ' + rowId + '</div>' : ''
                ].join('');
            }

            if (viewLink) {
                viewLink.setAttribute('href', viewHref || (rowId > 0 ? ('ojt-external-view.php?id=' + rowId) : '#'));
            }
        });

        modal.addEventListener('hidden.bs.modal', function () {
            if (summaryNode) {
                summaryNode.textContent = 'Choose an action for this student.';
            }
            if (currentNode) {
                currentNode.textContent = 'Student details will show here.';
            }
            if (viewLink) {
                viewLink.setAttribute('href', '#');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModal);
    } else {
        initModal();
    }
})();
