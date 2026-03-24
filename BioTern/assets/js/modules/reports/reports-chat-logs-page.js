document.addEventListener('click', function (event) {
    var toggleBtn = event.target.closest('.chatlogs-toggle-btn');
    if (!toggleBtn) {
        return;
    }

    var targetId = toggleBtn.getAttribute('data-target');
    var container = document.getElementById(targetId);
    if (!container) {
        return;
    }

    var extraRows = container.querySelectorAll('.chatlogs-thread-extra');
    if (!extraRows.length) {
        return;
    }

    var currentlyExpanded = toggleBtn.getAttribute('data-state') === 'expanded';
    var hiddenCount = parseInt(toggleBtn.getAttribute('data-hidden-count') || '0', 10);
    var labelNode = toggleBtn.querySelector('span');
    var iconNode = toggleBtn.querySelector('i');

    extraRows.forEach(function (row) {
        row.classList.toggle('d-none', currentlyExpanded);
    });

    if (currentlyExpanded) {
        toggleBtn.setAttribute('data-state', 'collapsed');
        if (labelNode) {
            labelNode.textContent = 'Show ' + hiddenCount + ' older';
        }
        if (iconNode) {
            iconNode.className = 'feather-chevron-down me-1';
        }
    } else {
        toggleBtn.setAttribute('data-state', 'expanded');
        if (labelNode) {
            labelNode.textContent = 'Hide older';
        }
        if (iconNode) {
            iconNode.className = 'feather-chevron-up me-1';
        }
    }

    if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
    }
});

document.addEventListener('change', function (event) {
    var pageJump = event.target.closest('#pageJump');
    if (!pageJump || pageJump.tagName !== 'SELECT') {
        return;
    }

    var form = pageJump.form;
    if (form) {
        form.submit();
    }
});
