(function () {
    "use strict";

    function initCopyDefaultSchedule() {
        var copyDefaultsButton = document.getElementById("copyDefaultScheduleButton");
        var printButton = document.getElementById("printSectionScheduleButton");
        var defaultSession = document.querySelector('select[name="attendance_session"]');
        var defaultTimeIn = document.querySelector('input[name="schedule_time_in"]');
        var defaultLateAfter = document.querySelector('input[name="late_after_time"]');
        var defaultTimeOut = document.querySelector('input[name="schedule_time_out"]');

        function copyDefaultsToRows() {
            document.querySelectorAll(".weekly-schedule-row").forEach(function (row) {
                var session = row.querySelector(".js-day-session");
                var dayType = row.querySelector(".js-day-type");
                var timeIn = row.querySelector(".js-weekly-time-in");
                var lateAfter = row.querySelector(".js-weekly-late");
                var timeOut = row.querySelector(".js-weekly-time-out");
                if (session && defaultSession) {
                    session.value = defaultSession.value;
                }
                if (dayType) {
                    dayType.value = "class";
                }
                if (timeIn && defaultTimeIn) {
                    timeIn.value = defaultTimeIn.value;
                }
                if (lateAfter && defaultLateAfter) {
                    lateAfter.value = defaultTimeIn ? defaultTimeIn.value : defaultLateAfter.value;
                }
                if (timeOut && defaultTimeOut) {
                    timeOut.value = defaultTimeOut.value;
                }
            });
            updateDefaultPreview();
            updateScheduleSummary();
            updateScheduleBoard();
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
                span.textContent = "Default periods";
            }
        }

        if (copyDefaultsButton) {
            copyDefaultsButton.addEventListener("click", copyDefaultsToRows);
        }

        if (printButton) {
            printButton.addEventListener("click", function () {
                updateScheduleBoard();
                window.print();
            });
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

        document.querySelectorAll(".js-day-session, .js-day-type, .js-section-time").forEach(function (input) {
            input.addEventListener("change", function () {
                normalizeDayType(input);
                updateDefaultPreview();
                updateScheduleSummary();
                updateScheduleBoard();
            });
            input.addEventListener("input", function () {
                updateDefaultPreview();
                updateScheduleSummary();
                updateScheduleBoard();
            });
        });

        updateDefaultPreview();
        updateScheduleSummary();
        updateScheduleBoard();
    }

    function labelSession(value) {
        if (value === "morning_only") return "Morning";
        if (value === "afternoon_only") return "Afternoon";
        return "Whole day";
    }

    function labelDayType(value) {
        if (value === "no_class") return "No Class";
        if (value === "x2_schedule") return "x2 Schedule";
        return "Class";
    }

    function normalizeDayType(input) {
        if (!input || !input.classList || !input.classList.contains("js-day-type")) return;
        var row = input.closest("[data-weekday-row]");
        var day = row ? row.getAttribute("data-weekday-row") : "";
        if (input.value === "x2_schedule" && day !== "saturday") {
            input.value = "class";
        }
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

    function parseMinutes(value) {
        if (!value) return null;
        var parts = value.split(":");
        var hour = parseInt(parts[0], 10);
        var minute = parseInt(parts[1] || "0", 10);
        if (isNaN(hour) || isNaN(minute)) return null;
        if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return null;
        return (hour * 60) + minute;
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
            var dayType = row.querySelector(".js-day-type");
            var timeIn = row.querySelector(".js-weekly-time-in");
            var lateAfter = row.querySelector(".js-weekly-late");
            var timeOut = row.querySelector(".js-weekly-time-out");
            if (lateAfter && timeIn) lateAfter.value = timeIn.value;
            var item = document.createElement("span");
            item.textContent = labelDay(row.getAttribute("data-weekday-row"))
                + " | " + labelDayType(dayType ? dayType.value : "class")
                + " | " + formatTime(timeIn ? timeIn.value : "")
                + " to " + formatTime(timeOut ? timeOut.value : "");
            summary.appendChild(item);
        });
    }

    function updateScheduleBoard() {
        var board = document.querySelector("[data-schedule-board]");
        if (!board) return;

        var boardStart = parseInt(board.getAttribute("data-board-start") || "420", 10);
        var boardEnd = parseInt(board.getAttribute("data-board-end") || "1260", 10);
        var boardSlot = parseInt(board.getAttribute("data-board-slot") || "30", 10);

        document.querySelectorAll("[data-weekday-row]").forEach(function (row) {
            var day = row.getAttribute("data-weekday-row");
            var block = board.querySelector('[data-schedule-block="' + day + '"]');
            var printBlock = document.querySelector('[data-print-schedule-block="' + day + '"]');
            if (!block && !printBlock) return;

            var session = row.querySelector(".js-day-session");
            var dayType = row.querySelector(".js-day-type");
            var timeIn = row.querySelector(".js-weekly-time-in");
            var timeOut = row.querySelector(".js-weekly-time-out");
            if (dayType && dayType.value === "no_class") {
                setScheduleBlockVisibility(block, false);
                setScheduleBlockVisibility(printBlock, false);
                return;
            }
            var startMinutes = parseMinutes(timeIn ? timeIn.value : "");
            var endMinutes = parseMinutes(timeOut ? timeOut.value : "");

            if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
                setScheduleBlockVisibility(block, false);
                setScheduleBlockVisibility(printBlock, false);
                return;
            }

            var clampedStart = Math.max(boardStart, Math.min(startMinutes, boardEnd));
            var clampedEnd = Math.max(boardStart, Math.min(endMinutes, boardEnd));
            if (clampedEnd <= clampedStart) {
                setScheduleBlockVisibility(block, false);
                setScheduleBlockVisibility(printBlock, false);
                return;
            }

            var rowStart = Math.floor((clampedStart - boardStart) / boardSlot) + 2;
            var rowSpan = Math.max(1, Math.ceil((clampedEnd - clampedStart) / boardSlot));
            updateScheduleBlockElement(block, rowStart, rowSpan, session, dayType, timeIn, timeOut, "[data-schedule-block-session]", "[data-schedule-block-time]");
            updateScheduleBlockElement(printBlock, rowStart, rowSpan, session, dayType, timeIn, timeOut, "[data-print-schedule-session]", "[data-print-schedule-time]");
        });
    }

    function setScheduleBlockVisibility(block, visible) {
        if (!block) return;
        block.style.display = visible ? "" : "none";
    }

    function updateScheduleBlockElement(block, rowStart, rowSpan, session, dayType, timeIn, timeOut, sessionSelector, timeSelector) {
        if (!block) return;

        setScheduleBlockVisibility(block, true);
        block.style.setProperty("--schedule-row-start", rowStart);
        block.style.setProperty("--schedule-row-span", rowSpan);

        var sessionLabel = block.querySelector(sessionSelector);
        var timeLabel = block.querySelector(timeSelector);
        if (sessionLabel) {
            sessionLabel.textContent = labelDayType(dayType ? dayType.value : "class");
        }
        if (timeLabel) {
            timeLabel.textContent = formatTime(timeIn ? timeIn.value : "") + " - " + formatTime(timeOut ? timeOut.value : "");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initCopyDefaultSchedule);
    } else {
        initCopyDefaultSchedule();
    }
})();
