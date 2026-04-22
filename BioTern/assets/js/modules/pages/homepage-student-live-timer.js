(function () {
    'use strict';

    function formatClock(totalSeconds) {
        var safeSeconds = Math.max(0, Math.round(Number(totalSeconds) || 0));
        var hours = Math.floor(safeSeconds / 3600);
        var minutes = Math.floor((safeSeconds % 3600) / 60);
        var seconds = safeSeconds % 60;

        return {
            hours: String(hours).padStart(3, '0'),
            minutes: String(minutes).padStart(2, '0'),
            seconds: String(seconds).padStart(2, '0')
        };
    }

    function renderTimer(root) {
        var parts = formatClock(root.getAttribute('data-remaining-seconds'));
        var hoursNode = root.querySelector('[data-student-hours-part="hours"]');
        var minutesNode = root.querySelector('[data-student-hours-part="minutes"]');
        var secondsNode = root.querySelector('[data-student-hours-part="seconds"]');

        if (hoursNode) {
            hoursNode.textContent = parts.hours;
        }

        if (minutesNode) {
            minutesNode.textContent = parts.minutes;
        }

        if (secondsNode) {
            secondsNode.textContent = parts.seconds;
        }
    }

    function updateSyncStamp(root, formatter) {
        var syncNode = root.querySelector('[data-student-hours-sync]');
        if (!syncNode) {
            return;
        }

        syncNode.textContent = formatter.format(new Date());
    }

    var formatter = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit'
    });

    document.querySelectorAll('[data-student-hours-timer]').forEach(function (root) {
        renderTimer(root);
        updateSyncStamp(root, formatter);

        window.setInterval(function () {
            updateSyncStamp(root, formatter);
        }, 1000);
    });
})();
