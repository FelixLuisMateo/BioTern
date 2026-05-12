(function () {
    document.body.classList.add('apps-account-page');

    var initProfilePasswordToggle = function () {
        var toggle = document.getElementById('toggle-password-visibility');
        if (!toggle) {
            return;
        }

        var form = toggle.closest('form');
        if (!form) {
            return;
        }

        var passwordFields = form.querySelectorAll('input[name="current_password"], input[name="new_password"], input[name="confirm_password"]');
        var toggleText = form.querySelector('.profile-password-toggle-text');
        var syncFieldTypes = function (show) {
            var type = show ? 'text' : 'password';
            passwordFields.forEach(function (field) {
                field.type = type;
            });

            if (toggleText) {
                toggleText.textContent = show ? 'Hide passwords' : 'Show passwords';
            }
        };

        toggle.addEventListener('change', function () {
            syncFieldTypes(toggle.checked);
        });

        syncFieldTypes(toggle.checked);
    };

    var initAccountSettingsLinks = function () {
        document.querySelectorAll('[data-profile-account-settings-link]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                window.location.assign('account-settings.php');
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initProfilePasswordToggle();
            initAccountSettingsLinks();
        });
    } else {
        initProfilePasswordToggle();
        initAccountSettingsLinks();
    }
})();
