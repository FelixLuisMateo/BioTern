(function () {
    function initChatReportsPage() {
        document.querySelectorAll('.chatreports-auto-filter').forEach(function (form) {
            var timer = null;
            form.querySelectorAll('select, input[type="date"], input[type="number"]').forEach(function (field) {
                field.addEventListener('change', function () {
                    form.requestSubmit();
                });
                field.addEventListener('input', function () {
                    if (field.type !== 'number') {
                        return;
                    }
                    window.clearTimeout(timer);
                    timer = window.setTimeout(function () {
                        form.requestSubmit();
                    }, 550);
                });
            });
        });

        function syncPunishmentFields(form) {
            var status = form.querySelector('.chatreports-status-select');
            var fields = form.querySelector('.chatreports-punishment-fields');
            if (!status || !fields) {
                return;
            }

            var isResolved = status.value === 'resolved';
            fields.classList.toggle('is-hidden', !isResolved);
            fields.querySelectorAll('select, input').forEach(function (field) {
                field.disabled = !isResolved;
            });
        }

        document.querySelectorAll('.chatreports-action-form').forEach(function (form) {
            syncPunishmentFields(form);
            var status = form.querySelector('.chatreports-status-select');
            if (status) {
                status.addEventListener('change', function () {
                    syncPunishmentFields(form);
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChatReportsPage);
        return;
    }

    initChatReportsPage();
})();
