(function () {
    function initChatPenaltiesAutoFilter() {
        var form = document.querySelector('.chat-penalties-auto-filter');
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChatPenaltiesAutoFilter);
        return;
    }

    initChatPenaltiesAutoFilter();
})();
