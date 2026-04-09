(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if (document.body) {
            document.body.classList.add('apps-email-page');
        }

        var root = document.querySelector('[data-app-email-root]');
        var composeOpen = root && root.getAttribute('data-compose-open') === '1';
        var composeModal = document.getElementById('composeMail');

        if (composeModal && document.body && composeModal.parentElement !== document.body) {
            document.body.appendChild(composeModal);
        }

        window.setTimeout(function () {
            var err = document.getElementById('emailAlertError');
            if (err) {
                err.classList.remove('show');
            }
            var ok = document.getElementById('emailAlertSuccess');
            if (ok) {
                ok.classList.remove('show');
            }
        }, 4000);

        var composeForm = composeModal ? composeModal.querySelector('form') : null;
        if (composeForm) {
            composeForm.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    event.preventDefault();
                    var sendBtn = document.getElementById('composeSendBtn');
                    if (sendBtn) {
                        sendBtn.click();
                    }
                }
            });
        }

        if (composeOpen && composeModal && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(composeModal).show();
        }
    });
})();
