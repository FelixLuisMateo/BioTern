document.addEventListener('DOMContentLoaded', function () {
    var bars = document.querySelectorAll('.report-progress-bar[data-progress]');
    if (!bars.length) {
        return;
    }

    bars.forEach(function (bar) {
        var raw = bar.getAttribute('data-progress');
        var value = parseFloat(raw || '0');
        if (!Number.isFinite(value)) {
            value = 0;
        }

        if (value < 0) {
            value = 0;
        } else if (value > 100) {
            value = 100;
        }

        bar.style.width = value + '%';
    });
});
