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

        function initRecipientCombobox() {
            var box = document.querySelector('[data-email-recipient-combobox]');
            if (!box) {
                return;
            }

            var trigger = box.querySelector('[data-email-recipient-trigger]');
            var menu = box.querySelector('[data-email-recipient-menu]');
            var search = box.querySelector('[data-email-recipient-search]');
            var value = box.querySelector('[data-email-recipient-value]');
            var label = box.querySelector('[data-email-recipient-label]');
            var options = Array.prototype.slice.call(box.querySelectorAll('[data-email-recipient-option]'));
            var groups = Array.prototype.slice.call(box.querySelectorAll('[data-recipient-group]'));
            var empty = box.querySelector('[data-email-recipient-empty]');

            function openMenu() {
                box.classList.add('is-open');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'true');
                }
                window.setTimeout(function () {
                    if (search) {
                        search.focus();
                        search.select();
                    }
                }, 0);
            }

            function closeMenu() {
                box.classList.remove('is-open');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            }

            function filterOptions() {
                var query = (search && search.value ? search.value : '').toLowerCase().trim();
                var visibleCount = 0;
                options.forEach(function (option) {
                    var haystack = (option.getAttribute('data-recipient-search') || '').toLowerCase();
                    var visible = !query || haystack.indexOf(query) !== -1;
                    option.hidden = !visible;
                    if (visible) {
                        visibleCount += 1;
                    }
                });

                groups.forEach(function (group) {
                    var hasVisible = Array.prototype.slice.call(group.querySelectorAll('[data-email-recipient-option]')).some(function (option) {
                        return !option.hidden;
                    });
                    group.hidden = !hasVisible;
                });

                if (empty) {
                    empty.hidden = visibleCount !== 0;
                }
            }

            if (trigger) {
                trigger.addEventListener('click', function () {
                    if (box.classList.contains('is-open')) {
                        closeMenu();
                    } else {
                        openMenu();
                    }
                });
            }

            if (search) {
                search.addEventListener('input', filterOptions);
                search.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeMenu();
                    }
                });
            }

            options.forEach(function (option) {
                option.addEventListener('click', function () {
                    options.forEach(function (item) {
                        item.classList.remove('is-selected');
                    });
                    option.classList.add('is-selected');
                    if (value) {
                        value.value = option.getAttribute('data-recipient-id') || '';
                    }
                    if (label) {
                        label.textContent = option.getAttribute('data-recipient-label') || option.textContent.trim();
                    }
                    closeMenu();
                });
            });

            document.addEventListener('click', function (event) {
                if (!box.contains(event.target)) {
                    closeMenu();
                }
            });

            filterOptions();
        }

        initRecipientCombobox();

        if (composeModal) {
            composeModal.addEventListener('shown.bs.modal', function () {
                var trigger = composeModal.querySelector('[data-email-recipient-trigger]');
                if (trigger) {
                    trigger.focus();
                }
            });
        }

        var toolbarForm = document.querySelector('.email-toolbar-form');
        if (toolbarForm) {
            toolbarForm.querySelectorAll('select').forEach(function (field) {
                field.addEventListener('change', function () {
                    toolbarForm.submit();
                });
            });
        }

        if (composeOpen && composeModal && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(composeModal).show();
        }
    });
})();
