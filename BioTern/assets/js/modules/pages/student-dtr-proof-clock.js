document.addEventListener('DOMContentLoaded', function () {
    const clockDisplay = document.getElementById('proofClockDisplay');
    const clockDate = document.getElementById('proofClockDate');
    const clockInput = document.getElementById('proofClockTime');

    if (!clockDisplay || !clockDate || !clockInput) {
        return;
    }

    const updateClock = function () {
        const now = new Date();
        const pad = function (value) {
            return value.toString().padStart(2, '0');
        };

        clockDisplay.textContent = now.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        clockDate.textContent = now.toLocaleDateString([], {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        });
        clockInput.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    };

    updateClock();
    window.setInterval(updateClock, 1000);
});
