"use strict";

(function () {
    function onReady(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }
        callback();
    }

    function toLocalInputValue(dateLike) {
        var d = new Date(dateLike);
        if (Number.isNaN(d.getTime())) {
            return "";
        }

        var year = d.getFullYear();
        var month = String(d.getMonth() + 1).padStart(2, "0");
        var day = String(d.getDate()).padStart(2, "0");
        var hour = String(d.getHours()).padStart(2, "0");
        var minute = String(d.getMinutes()).padStart(2, "0");

        return year + "-" + month + "-" + day + "T" + hour + ":" + minute;
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
        var deleteBtn = document.getElementById("calendarEventDeleteBtn");
        var saveBtn = document.getElementById("calendarEventSaveBtn");
        var statusEl = document.getElementById("calendarEventStatus");

        if (!modalEl || !eventForm) {
            return;
        }

        var modal = new bootstrap.Modal(modalEl);

        var idInput = document.getElementById("calendarEventId");
        var titleInput = document.getElementById("calendarEventTitle");
        var locationInput = document.getElementById("calendarEventLocation");
        var startInput = document.getElementById("calendarEventStart");
        var endInput = document.getElementById("calendarEventEnd");
        var allDayInput = document.getElementById("calendarEventAllDay");
        var colorInput = document.getElementById("calendarEventColor");
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

        function clearForm() {
            idInput.value = "";
            titleInput.value = "";
            locationInput.value = "";
            descriptionInput.value = "";
            colorInput.value = "#0d6efd";
            allDayInput.checked = false;

            var now = new Date();
            var oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);
            startInput.value = toLocalInputValue(now);
            endInput.value = toLocalInputValue(oneHourLater);

            if (deleteBtn) {
                deleteBtn.classList.add("d-none");
            }
            setStatus("");
        }

        function fillFormFromSchedule(schedule) {
            idInput.value = String(schedule.id || "");
            titleInput.value = String(schedule.title || "");
            locationInput.value = String(schedule.location || "");
            descriptionInput.value = String(schedule.body || "");
            colorInput.value = String(schedule.bgColor || "#0d6efd");

            var allDay = schedule.category === "allday";
            allDayInput.checked = allDay;

            startInput.value = toLocalInputValue(schedule.start);
            endInput.value = toLocalInputValue(schedule.end);

            if (deleteBtn) {
                deleteBtn.classList.remove("d-none");
            }
            setStatus("");
        }

        function openCreateModalFromRange(startDate, endDate) {
            clearForm();
            startInput.value = toLocalInputValue(startDate || new Date());
            endInput.value = toLocalInputValue(endDate || new Date(Date.now() + 60 * 60 * 1000));
            modal.show();
            titleInput.focus();
        }

        function scheduleFromEvent(event) {
            return {
                id: String(event.id),
                calendarId: "1",
                title: String(event.title || "(No title)"),
                category: Number(event.is_all_day) === 1 ? "allday" : "time",
                start: normalizeApiDateTime(event.start_at),
                end: normalizeApiDateTime(event.end_at),
                location: String(event.location || ""),
                body: String(event.description || ""),
                bgColor: String(event.color || "#0d6efd"),
                borderColor: String(event.color || "#0d6efd")
            };
        }

        function fetchEvents() {
            var from = "";
            var to = "";
            if (typeof cal.getDateRangeStart === "function" && typeof cal.getDateRangeEnd === "function") {
                from = cal.getDateRangeStart().toISOString();
                to = cal.getDateRangeEnd().toISOString();
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

        eventForm.addEventListener("submit", function (event) {
            event.preventDefault();

            var eventId = parseInt(idInput.value || "0", 10);
            var allDay = !!allDayInput.checked;
            var payload = {
                action: eventId > 0 ? "update" : "create",
                id: eventId,
                title: titleInput.value.trim(),
                location: locationInput.value.trim(),
                description: descriptionInput.value.trim(),
                color: colorInput.value,
                is_all_day: allDay ? 1 : 0,
                start_at: toApiDateTime(startInput.value, allDay),
                end_at: toApiDateTime(endInput.value, allDay)
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

        reloadEvents();
    });
})();
