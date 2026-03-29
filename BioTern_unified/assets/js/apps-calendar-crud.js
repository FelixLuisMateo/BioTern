"use strict";

(function () {
    var PH_TIMEZONE = "Asia/Manila";
    var PH_LOCALE = "en-PH";

    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }
        callback();
    }

    function formatDateTimeParts(dateLike, useInputSeparator) {
        var d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) {
            return "";
        }

        var formatter = new Intl.DateTimeFormat("en-US", {
            timeZone: PH_TIMEZONE,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: false
        });

        var parts = formatter.formatToParts(d).reduce(function (acc, part) {
            if (part.type !== "literal") {
                acc[part.type] = part.value;
            }
            return acc;
        }, {});

        var separator = useInputSeparator ? "T" : " ";
        return parts.year + "-" + parts.month + "-" + parts.day + separator + parts.hour + ":" + parts.minute + ":" + parts.second;
    }

    function toLocalInputValue(dateLike) {
        var value = formatDateTimeParts(dateLike, true);
        if (!value) {
            return "";
        }
        return value.slice(0, 16);
    }

    function toPhilippinesDateTime(dateLike) {
        return formatDateTimeParts(dateLike, false);
    }

    function toPhilippinesDate(dateLike) {
        var d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) {
            return null;
        }

        var parts = new Intl.DateTimeFormat(PH_LOCALE, {
            timeZone: PH_TIMEZONE,
            year: "numeric",
            month: "2-digit",
            day: "2-digit"
        }).formatToParts(d).reduce(function (acc, part) {
            if (part.type !== "literal") {
                acc[part.type] = part.value;
            }
            return acc;
        }, {});

        return {
            year: parts.year,
            month: parts.month,
            day: parts.day
        };
    }

    function toPhilippinesDisplayTime(dateLike) {
        var d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) {
            return "";
        }

        return new Intl.DateTimeFormat(PH_LOCALE, {
            timeZone: PH_TIMEZONE,
            hour: "numeric",
            minute: "2-digit",
            hour12: true
        }).format(d).toLowerCase();
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function toApiDateTime(localValue, allDay) {
        if (!localValue) {
            return "";
        }

        if (allDay && localValue.length === 10) {
            return localValue + " 00:00:00";
        }

        return localValue.replace("T", " ") + ":00";
    }

    function normalizeApiDateTime(value) {
        if (!value) {
            return "";
        }

        return String(value).replace(" ", "T");
    }

    onReady(function () {
        var cal = window.cal;
        if (!cal) {
            return;
        }

        var modalEl = document.getElementById("calendarEventModal");
        var eventForm = document.getElementById("calendarEventForm");
        var openBtn = document.getElementById("openEventModalBtn");
        var importCelebrationsBtn = document.getElementById("importCelebrationsBtn");
        var deleteBtn = document.getElementById("calendarEventDeleteBtn");
        var saveBtn = document.getElementById("calendarEventSaveBtn");
        var statusEl = document.getElementById("calendarEventStatus");

        if (!modalEl || !eventForm) {
            return;
        }

        var modal = new bootstrap.Modal(modalEl);

        var idInput = document.getElementById("calendarEventId");
        var titleInput = document.getElementById("calendarEventTitle");
        var startInput = document.getElementById("calendarEventStart");
        var endInput = document.getElementById("calendarEventEnd");
        var startDateInput = document.getElementById("calendarEventStartDate");
        var endDateInput = document.getElementById("calendarEventEndDate");
        var colorInput = document.getElementById("calendarEventColor");
        var colorSwatch = document.getElementById("calendarEventColorSwatch");
        var descriptionInput = document.getElementById("calendarEventDescription");

        var eventApiUrl = "api/calendar_events.php";

        function setStatus(message, isError) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = message || "";
            statusEl.classList.toggle("text-danger", !!isError);
            statusEl.classList.toggle("text-success", !isError && !!message);
        }

        function setSavingState(isSaving) {
            if (saveBtn) {
                saveBtn.disabled = !!isSaving;
            }
            if (deleteBtn) {
                deleteBtn.disabled = !!isSaving;
            }
        }

        function syncColorSwatch() {
            if (!colorInput || !colorSwatch) {
                return;
            }

            var color = colorInput.value || "#0d6efd";
            colorSwatch.style.background = "linear-gradient(135deg, " + color + " 0%, " + color + "cc 100%)";
        }

        function splitDateTimeValue(value) {
            if (!value) {
                return { date: "", time: "" };
            }

            var normalized = String(value).replace(" ", "T");
            var parts = normalized.split("T");
            return {
                date: parts[0] || "",
                time: (parts[1] || "").slice(0, 5)
            };
        }

        function syncSegmentsFromHiddenInputs() {
            var startParts = splitDateTimeValue(startInput.value);
            var endParts = splitDateTimeValue(endInput.value);

            if (startDateInput) startDateInput.value = startParts.date;
            if (endDateInput) endDateInput.value = endParts.date;
        }

        function syncHiddenInputsFromSegments() {
            var startDate = startDateInput ? startDateInput.value : "";
            var endDate = endDateInput ? endDateInput.value : "";
            startInput.value = startDate ? (startDate + "T00:00") : "";
            endInput.value = (endDate || startDate) ? ((endDate || startDate) + "T23:59") : "";
        }

        function clearForm() {
            idInput.value = "";
            titleInput.value = "";
            descriptionInput.value = "";
            colorInput.value = "#0d6efd";

            var now = new Date();
            startInput.value = toLocalInputValue(now);
            endInput.value = toLocalInputValue(now);
            syncSegmentsFromHiddenInputs();
            syncHiddenInputsFromSegments();

            if (deleteBtn) {
                deleteBtn.classList.add("d-none");
            }
            setStatus("");
            syncColorSwatch();
        }

        function fillFormFromSchedule(schedule) {
            idInput.value = String(schedule.id || "");
            titleInput.value = String(schedule.title || "");
            descriptionInput.value = String(schedule.body || "");
            colorInput.value = String(schedule.bgColor || "#0d6efd");

            startInput.value = toLocalInputValue(schedule.start);
            endInput.value = toLocalInputValue(schedule.end);
            syncSegmentsFromHiddenInputs();
            syncHiddenInputsFromSegments();

            if (deleteBtn) {
                deleteBtn.classList.remove("d-none");
            }
            setStatus("");
            syncColorSwatch();
        }

        function openCreateModalFromRange(startDate, endDate) {
            clearForm();
            startInput.value = toLocalInputValue(startDate || new Date());
            endInput.value = toLocalInputValue(endDate || startDate || new Date());
            syncSegmentsFromHiddenInputs();
            syncHiddenInputsFromSegments();
            modal.show();
            titleInput.focus();
        }

        function openCreateModalForDate(dateLike) {
            var baseDate = new Date(dateLike);
            if (Number.isNaN(baseDate.getTime())) {
                baseDate = new Date();
            }

            var startDate = new Date(baseDate);
            startDate.setHours(9, 0, 0, 0);

            var endDate = new Date(startDate.getTime() + 60 * 60 * 1000);
            openCreateModalFromRange(startDate, endDate);
        }

        function getDateFromMonthPosition(targetEl, clientX) {
            if (!targetEl || typeof cal.getDateRangeStart !== "function") {
                return null;
            }

            var weekRow = targetEl.closest(".tui-full-calendar-month-week-item");
            if (!weekRow) {
                return null;
            }

            var weekRows = Array.prototype.slice.call(document.querySelectorAll("#tui-calendar-init .tui-full-calendar-month-week-item"));
            var rowIndex = weekRows.indexOf(weekRow);
            if (rowIndex < 0) {
                return null;
            }

            var bounds = weekRow.getBoundingClientRect();
            if (!bounds.width) {
                return null;
            }

            var relativeX = clientX - bounds.left;
            if (relativeX < 0 || relativeX > bounds.width) {
                return null;
            }

            var columnWidth = bounds.width / 7;
            var columnIndex = Math.max(0, Math.min(6, Math.floor(relativeX / columnWidth)));
            var cellIndex = (rowIndex * 7) + columnIndex;

            var rangeStart = new Date(cal.getDateRangeStart());
            if (Number.isNaN(rangeStart.getTime())) {
                return null;
            }

            rangeStart.setHours(0, 0, 0, 0);
            rangeStart.setDate(rangeStart.getDate() + cellIndex);
            return rangeStart;
        }

        function scheduleFromEvent(event) {
            return {
                id: String(event.id),
                calendarId: "1",
                title: String(event.title || "(No title)"),
                category: Number(event.is_all_day) === 1 ? "allday" : "time",
                start: normalizeApiDateTime(event.start_at),
                end: normalizeApiDateTime(event.end_at),
                body: String(event.description || ""),
                bgColor: String(event.color || "#0d6efd"),
                borderColor: String(event.color || "#0d6efd")
            };
        }

        function renderSidebarEvents(events) {
            var sidebarBody = document.querySelector(".content-sidebar-body");
            if (!sidebarBody) {
                return;
            }

            if (!Array.isArray(events) || events.length === 0) {
                sidebarBody.innerHTML = "<div class=\"p-4 text-muted small\">No events yet. Click Add Event to create one.</div>";
                return;
            }

            var sorted = events.slice().sort(function (a, b) {
                return new Date(a.start_at).getTime() - new Date(b.start_at).getTime();
            });

            var topEvents = sorted.slice(0, 8);
            var html = topEvents.map(function (event, idx) {
                var dateParts = toPhilippinesDate(event.start_at);
                var monthShort = "";
                if (dateParts) {
                    monthShort = new Intl.DateTimeFormat(PH_LOCALE, {
                        timeZone: PH_TIMEZONE,
                        month: "short"
                    }).format(new Date(event.start_at)).toUpperCase();
                }

                var startTime = Number(event.is_all_day) === 1 ? "All day" : toPhilippinesDisplayTime(event.start_at);
                var endTime = Number(event.is_all_day) === 1 ? "" : toPhilippinesDisplayTime(event.end_at);
                var timeLabel = Number(event.is_all_day) === 1 ? "All day" : (startTime + " - " + endTime + (event.location ? ", " + event.location : ""));
                var color = String(event.color || "#0d6efd");
                var bgSoft = idx % 2 === 0 ? "bg-soft-primary text-primary" : "bg-soft-danger text-danger";

                return ""
                    + "<div class=\"p-4 " + (idx > 0 ? "border-top " : "") + "c-pointer single-item schedule-item\" data-event-id=\"" + escapeHtml(String(event.id)) + "\">"
                    + "<div class=\"d-flex align-items-start\">"
                    + "<div class=\"wd-50 ht-50 " + bgSoft + " lh-1 d-flex align-items-center justify-content-center flex-column rounded-2 schedule-date\" style=\"border-left:3px solid " + color + ";\">"
                    + "<span class=\"fs-18 fw-bold mb-1 d-block\">" + (dateParts ? dateParts.day : "--") + "</span>"
                    + "<span class=\"fs-10 text-semibold text-uppercase d-block\">" + monthShort + "</span>"
                    + "</div>"
                    + "<div class=\"ms-3 schedule-body\">"
                    + "<div class=\"text-dark\">"
                    + "<h6 class=\"fw-bold my-1 text-truncate-1-line\">" + escapeHtml(String(event.title || "(No title)")) + "</h6>"
                    + "<span class=\"fs-11 fw-normal text-muted\">" + escapeHtml(timeLabel) + "</span>"
                    + "<p class=\"fs-12 fw-normal text-muted my-2 text-truncate-2-line\">" + escapeHtml(String(event.description || "No description")) + "</p>"
                    + "</div>"
                    + "</div>"
                    + "</div>"
                    + "</div>";
            }).join("");

            sidebarBody.innerHTML = html;
        }

        function fetchEvents() {
            var from = "";
            var to = "";
            if (typeof cal.getDateRangeStart === "function" && typeof cal.getDateRangeEnd === "function") {
                from = toPhilippinesDateTime(cal.getDateRangeStart());
                to = toPhilippinesDateTime(cal.getDateRangeEnd());
            }

            var url = eventApiUrl + "?from=" + encodeURIComponent(from) + "&to=" + encodeURIComponent(to);

            return fetch(url, { credentials: "same-origin" })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success || !Array.isArray(payload.events)) {
                        throw new Error(payload && payload.message ? payload.message : "Failed to load events");
                    }
                    return payload.events;
                });
        }

        function renderEvents(events) {
            if (typeof cal.clear === "function") {
                cal.clear();
            }
            if (typeof cal.createSchedules !== "function") {
                return;
            }
            var schedules = events.map(scheduleFromEvent);
            cal.createSchedules(schedules);
        }

        function reloadEvents() {
            return fetchEvents()
                .then(function (events) {
                    renderEvents(events);
                    renderSidebarEvents(events);
                })
                .catch(function (err) {
                    console.error(err);
                });
        }

        function sendAction(body) {
            return fetch(eventApiUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(body)
            }).then(function (res) { return res.json(); });
        }

        if (openBtn) {
            openBtn.addEventListener("click", function () {
                openCreateModalFromRange(new Date(), new Date(Date.now() + 60 * 60 * 1000));
            });
        }

        if (colorInput) {
            colorInput.addEventListener("input", syncColorSwatch);
            colorInput.addEventListener("change", syncColorSwatch);
        }

        [startDateInput, endDateInput].forEach(function (input) {
            if (!input) {
                return;
            }
            input.addEventListener("input", syncHiddenInputsFromSegments);
            input.addEventListener("change", syncHiddenInputsFromSegments);
        });

        function performCelebrationImport(showAlert) {
            showAlert = showAlert === undefined ? false : !!showAlert;
            var year = new Date().getFullYear();
            if (typeof cal.getDateRangeStart === "function") {
                var rangeStart = cal.getDateRangeStart();
                var dateParts = toPhilippinesDate(rangeStart);
                if (dateParts && dateParts.year) {
                    year = parseInt(dateParts.year, 10);
                }
            }

            if (importCelebrationsBtn) {
                importCelebrationsBtn.disabled = true;
            }

            return sendAction({ action: "seed_celebrations", year: year })
                .then(function (result) {
                    if (!result || !result.success) {
                        throw new Error(result && result.message ? result.message : "Failed to import celebration events");
                    }
                    return reloadEvents().then(function () {
                        if (showAlert) {
                            var inserted = Number(result.inserted_count || 0);
                            if (inserted > 0) {
                                window.alert("Added " + inserted + " Philippines holiday/celebration events for " + String(year) + ".");
                            } else {
                                window.alert("Philippines holiday/celebration events for " + String(year) + " already exist.");
                            }
                        }
                    });
                })
                .catch(function (err) {
                    if (showAlert) {
                        window.alert(err && err.message ? err.message : "Failed to import celebration events");
                    }
                    console.error("Auto-import error:", err);
                })
                .finally(function () {
                    if (importCelebrationsBtn) {
                        importCelebrationsBtn.disabled = false;
                    }
                });
        }

        if (importCelebrationsBtn) {
            importCelebrationsBtn.addEventListener("click", function () {
                performCelebrationImport(true);
            });
        }

        // Auto-import celebrations on first load (only once per year per browser)
        var storageCelebrationKey = "biotern_celebrations_imported_" + new Date().getFullYear();
        if (!localStorage.getItem(storageCelebrationKey)) {
            performCelebrationImport(false).then(function () {
                localStorage.setItem(storageCelebrationKey, "1");
            }).catch(function () {
                // Silent fail on auto-import
            });
        }

        eventForm.addEventListener("submit", function (event) {
            event.preventDefault();

            var eventId = parseInt(idInput.value || "0", 10);
            syncHiddenInputsFromSegments();
            var payload = {
                action: eventId > 0 ? "update" : "create",
                id: eventId,
                title: titleInput.value.trim(),
                description: descriptionInput.value.trim(),
                color: colorInput.value,
                is_all_day: 1,
                start_at: toApiDateTime((startDateInput ? startDateInput.value : ""), true),
                end_at: toApiDateTime((endDateInput ? (endDateInput.value || (startDateInput ? startDateInput.value : "")) : ""), true)
            };

            setSavingState(true);
            setStatus("");

            sendAction(payload)
                .then(function (result) {
                    if (!result || !result.success) {
                        throw new Error(result && result.message ? result.message : "Failed to save event");
                    }
                    return reloadEvents();
                })
                .then(function () {
                    modal.hide();
                })
                .catch(function (err) {
                    setStatus(err.message || "Failed to save event", true);
                })
                .finally(function () {
                    setSavingState(false);
                });
        });

        if (deleteBtn) {
            deleteBtn.addEventListener("click", function () {
                var eventId = parseInt(idInput.value || "0", 10);
                if (eventId <= 0) {
                    return;
                }

                if (!window.confirm("Delete this event?")) {
                    return;
                }

                setSavingState(true);
                setStatus("");

                sendAction({ action: "delete", id: eventId })
                    .then(function (result) {
                        if (!result || !result.success) {
                            throw new Error(result && result.message ? result.message : "Failed to delete event");
                        }
                        return reloadEvents();
                    })
                    .then(function () {
                        modal.hide();
                    })
                    .catch(function (err) {
                        setStatus(err.message || "Failed to delete event", true);
                    })
                    .finally(function () {
                        setSavingState(false);
                    });
            });
        }

        if (typeof cal.on === "function") {
            cal.on("beforeCreateSchedule", function (e) {
                openCreateModalFromRange(e.start, e.end);
            });

            cal.on("clickSchedule", function (e) {
                if (!e || !e.schedule) {
                    return;
                }
                clearForm();
                fillFormFromSchedule(e.schedule);
                modal.show();
                titleInput.focus();
            });
        }

        document.addEventListener("click", function (event) {
            var sidebarEventEl = event.target.closest(".schedule-item");
            if (!sidebarEventEl) {
                return;
            }

            var eventId = sidebarEventEl.getAttribute("data-event-id");
            if (!eventId) {
                return;
            }

            setSavingState(true);
            fetch(eventApiUrl + "?id=" + encodeURIComponent(eventId), { credentials: "same-origin" })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.event) {
                        throw new Error(payload && payload.message ? payload.message : "Failed to load event");
                    }
                    clearForm();
                    fillFormFromSchedule({
                        id: payload.event.id,
                        title: payload.event.title,
                        body: payload.event.description,
                        start: payload.event.start_at,
                        end: payload.event.end_at,
                        bgColor: payload.event.color,
                        category: payload.event.is_all_day === 1 ? "allday" : "time"
                    });
                    modal.show();
                    titleInput.focus();
                })
                .catch(function (err) {
                    window.alert(err && err.message ? err.message : "Failed to load event");
                })
                .finally(function () {
                    setSavingState(false);
                });
        });

        document.addEventListener("click", function (event) {
            var action = event.target && event.target.getAttribute ? event.target.getAttribute("data-action") : "";
            if (action === "move-prev" || action === "move-next" || action === "move-today") {
                setTimeout(function () {
                    reloadEvents();
                }, 50);
            }
        });

        document.addEventListener("click", function (event) {
            var actionEl = event.target.closest("[data-action]");
            if (!actionEl) {
                return;
            }
            var action = actionEl.getAttribute("data-action") || "";
            if (action.indexOf("toggle-") !== 0) {
                return;
            }
            setTimeout(function () {
                reloadEvents();
            }, 80);
        });

        function handleMonthCellQuickCreate(event) {
            var withinMonthGrid = event.target.closest("#tui-calendar-init .tui-full-calendar-month-week-item");
            if (!withinMonthGrid) {
                return;
            }

            if (event.target.closest(".tui-full-calendar-weekday-schedule")
                || event.target.closest(".tui-full-calendar-month-more")
                || event.target.closest(".tui-full-calendar-popup")
                || event.target.closest(".tui-full-calendar-weekday-grid-more-schedules")
                || event.target.closest(".tui-full-calendar-weekday-exceed-in-month")) {
                return;
            }

            var clickedDate = getDateFromMonthPosition(event.target, event.clientX);
            if (!clickedDate) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openCreateModalForDate(clickedDate);
        }

        document.addEventListener("click", handleMonthCellQuickCreate, true);

        syncColorSwatch();
        reloadEvents();
    });
})();
