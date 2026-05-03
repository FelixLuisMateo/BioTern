(function () {
    "use strict";

    function initCopyDefaultSchedule() {
        var copyDefaultsButton = document.getElementById("copyDefaultScheduleButton");
        if (!copyDefaultsButton) {
            return;
        }

        copyDefaultsButton.addEventListener("click", function () {
            var defaultSession = document.querySelector('select[name="attendance_session"]');
            var defaultTimeIn = document.querySelector('input[name="schedule_time_in"]');
            var defaultLateAfter = document.querySelector('input[name="late_after_time"]');
            var defaultTimeOut = document.querySelector('input[name="schedule_time_out"]');

            document.querySelectorAll(".weekly-schedule-row").forEach(function (row) {
                var session = row.querySelector(".js-day-session");
                var timeIn = row.querySelector(".js-weekly-time-in");
                var lateAfter = row.querySelector(".js-weekly-late");
                var timeOut = row.querySelector(".js-weekly-time-out");
                if (session && defaultSession) {
                    session.value = defaultSession.value;
                }
                if (timeIn && defaultTimeIn) {
                    timeIn.value = defaultTimeIn.value;
                }
                if (lateAfter && defaultLateAfter) {
                    lateAfter.value = defaultLateAfter.value;
                }
                if (timeOut && defaultTimeOut) {
                    timeOut.value = defaultTimeOut.value;
                }
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initCopyDefaultSchedule);
    } else {
        initCopyDefaultSchedule();
    }
})();
