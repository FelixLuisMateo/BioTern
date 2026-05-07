(function () {
    "use strict";

    function initCopyDefaultSchedule() {
        var copyDefaultsButton = document.getElementById("copyDefaultScheduleButton");
        var defaultSession = document.querySelector('select[name="attendance_session"]');
        var defaultTimeIn = document.querySelector('input[name="schedule_time_in"]');
        var defaultLateAfter = document.querySelector('input[name="late_after_time"]');
        var defaultTimeOut = document.querySelector('input[name="schedule_time_out"]');

        function copyDefaultsToRows() {
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
            updateScheduleSummary();
        }

        function updateDefaultPreview() {
            var preview = document.getElementById("defaultHoursPreview");
            if (!preview) return;

            var strong = preview.querySelector("strong");
            var span = preview.querySelector("span");
            if (strong) {
                strong.textContent = formatTime(defaultTimeIn ? defaultTimeIn.value : "")
                    + " to "
                    + formatTime(defaultTimeOut ? defaultTimeOut.value : "");
            }
            if (span) {
                span.textContent = labelSession(defaultSession ? defaultSession.value : "whole_day") + " default";
            }
        }

        if (copyDefaultsButton) {
            copyDefaultsButton.addEventListener("click", copyDefaultsToRows);
        }

        document.querySelectorAll("[data-schedule-preset]").forEach(function (button) {
            button.addEventListener("click", function () {
                var preset = button.getAttribute("data-schedule-preset");
                if (preset === "morning") {
                    if (defaultSession) defaultSession.value = "morning_only";
                    if (defaultTimeIn) defaultTimeIn.value = "08:00";
                    if (defaultLateAfter) defaultLateAfter.value = "08:00";
                    if (defaultTimeOut) defaultTimeOut.value = "12:00";
                } else if (preset === "whole") {
                    if (defaultSession) defaultSession.value = "whole_day";
                    if (defaultTimeIn) defaultTimeIn.value = "08:00";
                    if (defaultLateAfter) defaultLateAfter.value = "08:00";
                    if (defaultTimeOut) defaultTimeOut.value = "17:00";
                }
                copyDefaultsToRows();
            });
        });

        document.querySelectorAll(".js-day-session, .js-section-time").forEach(function (input) {
            input.addEventListener("change", function () {
                updateDefaultPreview();
                updateScheduleSummary();
            });
            input.addEventListener("input", function () {
                updateDefaultPreview();
                updateScheduleSummary();
            });
        });

        updateDefaultPreview();
        updateScheduleSummary();
    }

    function labelSession(value) {
        if (value === "morning_only") return "Morning";
        if (value === "afternoon_only") return "Afternoon";
        return "Whole day";
    }

    function labelDay(value) {
        return value ? value.charAt(0).toUpperCase() + value.slice(1) : "Day";
    }

    function formatTime(value) {
        if (!value) return "--:--";
        var parts = value.split(":");
        var hour = parseInt(parts[0], 10);
        var minute = parts[1] || "00";
        if (isNaN(hour)) return value;
        var suffix = hour >= 12 ? "PM" : "AM";
        var displayHour = hour % 12;
        if (displayHour === 0) displayHour = 12;
        return displayHour + ":" + minute + " " + suffix;
    }

    function updateScheduleSummary() {
        var summary = document.getElementById("sectionScheduleSummary");
        if (!summary) {
            return;
        }

        var rows = Array.prototype.slice.call(document.querySelectorAll("[data-weekday-row]")).slice(0, 3);
        if (!rows.length) {
            return;
        }

        summary.innerHTML = "";
        rows.forEach(function (row) {
            var session = row.querySelector(".js-day-session");
            var timeIn = row.querySelector(".js-weekly-time-in");
            var lateAfter = row.querySelector(".js-weekly-late");
            var timeOut = row.querySelector(".js-weekly-time-out");
            var item = document.createElement("span");
            item.textContent = labelDay(row.getAttribute("data-weekday-row"))
                + " | " + labelSession(session ? session.value : "whole_day")
                + " | " + formatTime(timeIn ? timeIn.value : "")
                + " to " + formatTime(timeOut ? timeOut.value : "")
                + " | Late " + formatTime(lateAfter ? lateAfter.value : "");
            summary.appendChild(item);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initCopyDefaultSchedule);
    } else {
        initCopyDefaultSchedule();
    }
})();
