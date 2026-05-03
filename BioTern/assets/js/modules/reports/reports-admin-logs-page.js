(function () {
    function initAdminLogsAutoFilter() {
        var form = document.querySelector('.admin-logs-auto-filter');
        if (!form) {
            return;
        }

        var search = form.querySelector('input[name="search"]');
        var timer = null;

        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                form.requestSubmit();
            });
        });

        if (search) {
            search.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    form.requestSubmit();
                }, 450);
            });
        }

        var pageJump = document.getElementById('adminLogsPageJump');
        if (pageJump) {
            pageJump.addEventListener('change', function () {
                if (pageJump.form) {
                    pageJump.form.submit();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAdminLogsAutoFilter();
        });
        return;
    }

    initAdminLogsAutoFilter();
})();
