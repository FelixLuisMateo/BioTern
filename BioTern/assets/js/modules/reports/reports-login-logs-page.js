(function () {
    function initLoginLogsAutoFilter() {
        var form = document.querySelector('.login-logs-auto-filter');
        if (!form) {
            return;
        }

        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                form.requestSubmit();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initLoginLogsAutoFilter();
        });
        return;
    }

    initLoginLogsAutoFilter();
})();
