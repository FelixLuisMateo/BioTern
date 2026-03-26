// Modern calendar initialization with PH holidays for 2026
// Requires TUI Calendar library

(function () {
    function fetchPHHolidays(callback) {
        fetch('assets/js/ph-holidays-2026.json')
            .then(function (res) { return res.json(); })
            .then(function (data) { callback(data); });
    }

    function initCalendar(events) {
        var cal = new window.tui.Calendar('#tui-calendar-init', {
            defaultView: 'month',
            useCreationPopup: true,
            useDetailPopup: true,
            calendars: [{
                id: '1',
                name: 'Philippines Holidays',
                color: '#fff',
                bgColor: '#2563eb',
                borderColor: '#2563eb'
            }]
        });
        window.cal = cal;
        if (Array.isArray(events) && events.length && typeof cal.createSchedules === 'function') {
            cal.createSchedules(events);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        fetchPHHolidays(function (phEvents) {
            window.ScheduleList = phEvents;
            initCalendar(phEvents);
        });
    });
})();
