/* Students view page runtime extracted from inline script */
(function () {
  "use strict";

  function toInt(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function initializeTimer() {
    var timerElement = document.getElementById("hoursRemaining");
    if (!timerElement) return;

    var completionElement = document.getElementById("completionValue");
    var internalHoursElement = document.getElementById("internalHoursValue");
    var internalHoursDetailElement = document.getElementById("internalHoursDetailValue");

    var cfg = document.getElementById("students-view-runtime-config");
    var internalTotalHours = toInt(cfg && cfg.dataset.internalTotalHours, 0);
    var studentId = toInt(cfg && cfg.dataset.studentId, 0);
    var remainingSeconds = toInt(cfg && cfg.dataset.remainingSeconds, 0);
    var remainingSecondsWithoutOpen = toInt(
      cfg && cfg.dataset.remainingSecondsWithoutOpen,
      remainingSeconds
    );
    var isClockedIn = String((cfg && cfg.dataset.isClockedIn) || "0") === "1";
    var openClockInRaw = (cfg && cfg.dataset.openClockInRaw) || "";

    var storageKey = "student_timer_state_" + String(studentId);
    var nowRef = new Date();
    var todayKey = [
      nowRef.getFullYear(),
      String(nowRef.getMonth() + 1).padStart(2, "0"),
      String(nowRef.getDate()).padStart(2, "0"),
    ].join("-");

    var lastSyncedHour = null;
    var syncInFlight = false;

    function formatHMS(totalSeconds) {
      var safe = Math.max(0, Math.floor(totalSeconds));
      var h = Math.floor(safe / 3600);
      var m = Math.floor((safe % 3600) / 60);
      var s = safe % 60;
      return h + "h:" + String(m).padStart(2, "0") + "m:" + String(s).padStart(2, "0") + "s";
    }

    function updateCompletionFromSeconds() {
      if (!completionElement || !Number.isFinite(internalTotalHours) || internalTotalHours <= 0) return;
      var remainingHoursPrecise = Math.max(0, remainingSeconds / 3600);
      var completed = Math.max(0, internalTotalHours - remainingHoursPrecise);
      var pct = (completed / internalTotalHours) * 100;
      if (pct > 100) pct = 100;
      completionElement.textContent = pct.toFixed(2) + "%";
    }

    function updateInternalHoursFromSeconds() {
      if (!internalHoursElement || !Number.isFinite(internalTotalHours) || internalTotalHours <= 0) return;
      var remainingWholeHours = Math.max(0, Math.floor(remainingSeconds / 3600));
      internalHoursElement.textContent = remainingWholeHours + "/" + internalTotalHours;
      if (internalHoursDetailElement) {
        internalHoursDetailElement.textContent = remainingWholeHours + " / " + internalTotalHours;
      }
    }

    function loadState() {
      try {
        var raw = localStorage.getItem(storageKey);
        if (!raw) return null;
        var parsed = JSON.parse(raw);
        if (!parsed || typeof parsed.seconds === "undefined") return null;
        var sec = parseInt(parsed.seconds, 10);
        if (!Number.isFinite(sec)) return null;
        return {
          seconds: Math.max(0, sec),
          savedAt: parsed.savedAt ? parseInt(parsed.savedAt, 10) : null,
          sessionDate: parsed.sessionDate || null,
          clockInRaw: parsed.clockInRaw || null,
        };
      } catch (e) {
        return null;
      }
    }

    function saveState() {
      try {
        localStorage.setItem(
          storageKey,
          JSON.stringify({
            seconds: Math.max(0, Math.floor(remainingSeconds)),
            savedAt: Date.now(),
            sessionDate: isClockedIn ? todayKey : null,
            clockInRaw: isClockedIn ? openClockInRaw : null,
          })
        );
      } catch (e) {}
    }

    function syncRemainingHourToDb() {
      if (!isClockedIn) return;
      var currentHour = Math.max(0, Math.floor(remainingSeconds / 3600));
      if (lastSyncedHour === currentHour) return;
      if (syncInFlight) return;
      syncInFlight = true;

      var body = new URLSearchParams();
      body.set("student_id", String(studentId));
      body.set("remaining_hours", String(currentHour));

      fetch("update_remaining_hours.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data && data.ok) {
            lastSyncedHour = currentHour;
          }
        })
        .catch(function () {})
        .finally(function () {
          syncInFlight = false;
        });
    }

    function elapsedSinceOpenClockIn() {
      if (!isClockedIn || !openClockInRaw) return 0;
      var now = new Date();
      var parts = String(openClockInRaw).split(":");
      if (parts.length < 2) return 0;
      var start = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
        parseInt(parts[0], 10),
        parseInt(parts[1], 10),
        parseInt(parts[2] || "0", 10)
      );
      return Math.max(0, Math.floor((now.getTime() - start.getTime()) / 1000));
    }

    var saved = loadState();
    if (isClockedIn) {
      var elapsed = elapsedSinceOpenClockIn();
      if (elapsed > 0) {
        remainingSeconds = Math.max(0, remainingSecondsWithoutOpen - elapsed);
      }
    }

    if (saved) {
      var sameSession =
        isClockedIn && saved.sessionDate === todayKey && saved.clockInRaw === openClockInRaw;
      if (sameSession || !isClockedIn) {
        remainingSeconds = Math.min(remainingSeconds, saved.seconds);
      }
    }

    function updateTimer() {
      timerElement.textContent = formatHMS(remainingSeconds);
      updateInternalHoursFromSeconds();
      updateCompletionFromSeconds();

      if (isClockedIn && remainingSeconds > 0) {
        remainingSeconds--;
      }

      if (isClockedIn && remainingSeconds % 10 === 0) {
        saveState();
      }

      if (isClockedIn && remainingSeconds > 0 && remainingSeconds % 3600 === 0) {
        syncRemainingHourToDb();
      }
    }

    updateTimer();
    setInterval(updateTimer, 1000);

    saveState();
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) {
        saveState();
      }
    });
    window.addEventListener("beforeunload", function () {
      saveState();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeTimer);
  } else {
    initializeTimer();
  }
})();
