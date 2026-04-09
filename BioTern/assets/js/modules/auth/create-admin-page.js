(function () {
    'use strict';

    var toggle = document.getElementById('togglePassword');
    var username = document.getElementById('ca_username');
    var password = document.getElementById('ca_password');
    var icon = document.getElementById('toggleIcon');
    var postRequestFlag = document.body ? document.body.getAttribute('data-ca-post-request') : null;
    var isPostRequest = postRequestFlag === null ? true : postRequestFlag === '1';

    var eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    var eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"/><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    if (!isPostRequest) {
        if (username) {
            username.value = '';
        }
        if (password) {
            password.value = '';
        }
    }

    if (icon) {
        icon.innerHTML = eyeSVG;
    }

    if (toggle && password) {
        toggle.addEventListener('click', function () {
            var shouldShow = password.type === 'password';
            password.type = shouldShow ? 'text' : 'password';
            if (icon) {
                icon.innerHTML = shouldShow ? eyeOffSVG : eyeSVG;
            }
            toggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
        });
    }
})();
