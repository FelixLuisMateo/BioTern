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
    var externalTotalHours = toInt(cfg && cfg.dataset.externalTotalHours, 0);
    var activeTotalHours = toInt(cfg && cfg.dataset.activeTotalHours, internalTotalHours);
    var activeTrack = String((cfg && cfg.dataset.activeTrack) || "internal").toLowerCase();
    var studentId = toInt(cfg && cfg.dataset.studentId, 0);
    var remainingSeconds = toInt(cfg && cfg.dataset.remainingSeconds, 0);
    var remainingSecondsWithoutOpen = toInt(
      cfg && cfg.dataset.remainingSecondsWithoutOpen,
      remainingSeconds
    );
    var isClockedIn = String((cfg && cfg.dataset.isClockedIn) || "0") === "1";
    var openClockInRaw = (cfg && cfg.dataset.openClockInRaw) || "";
    var sessionCutoffRaw = (cfg && cfg.dataset.sessionCutoffRaw) || "";

    var storageKey = "student_timer_state_" + String(studentId) + "_" + activeTrack;
    var nowRef = new Date();
    var todayKey = [
      nowRef.getFullYear(),
      String(nowRef.getMonth() + 1).padStart(2, "0"),
      String(nowRef.getDate()).padStart(2, "0"),
    ].join("-");

    function formatHMS(totalSeconds) {
      var safe = Math.max(0, Math.floor(totalSeconds));
      var h = Math.floor(safe / 3600);
      var m = Math.floor((safe % 3600) / 60);
      var s = safe % 60;
      return h + "h:" + String(m).padStart(2, "0") + "m:" + String(s).padStart(2, "0") + "s";
    }

    function updateCompletionFromSeconds() {
      if (!completionElement || !Number.isFinite(activeTotalHours) || activeTotalHours <= 0) return;
      var remainingHoursPrecise = Math.max(0, remainingSeconds / 3600);
      var completed = Math.max(0, activeTotalHours - remainingHoursPrecise);
      var pct = (completed / activeTotalHours) * 100;
      if (pct > 100) pct = 100;
      completionElement.textContent = pct.toFixed(2) + "%";
    }

    function updateInternalHoursFromSeconds() {
      var remainingWholeHours = Math.max(0, Math.floor(remainingSeconds / 3600));
      if (activeTrack === "external") {
        var externalHoursElement = document.querySelector(".stat-card:nth-child(4) h6");
        if (externalHoursElement && Number.isFinite(externalTotalHours) && externalTotalHours > 0) {
          externalHoursElement.textContent = remainingWholeHours + "/" + externalTotalHours;
        }
        if (internalHoursElement && Number.isFinite(internalTotalHours) && internalTotalHours > 0) {
          internalHoursElement.textContent =
            toInt(cfg && cfg.dataset.internalRemainingDisplay, 0) + "/" + internalTotalHours;
        }
        if (internalHoursDetailElement && Number.isFinite(internalTotalHours) && internalTotalHours > 0) {
          internalHoursDetailElement.textContent =
            toInt(cfg && cfg.dataset.internalRemainingDisplay, 0) + " / " + internalTotalHours;
        }
      } else {
        if (!internalHoursElement || !Number.isFinite(internalTotalHours) || internalTotalHours <= 0) return;
        internalHoursElement.textContent = remainingWholeHours + "/" + internalTotalHours;
        if (internalHoursDetailElement) {
          internalHoursDetailElement.textContent = remainingWholeHours + " / " + internalTotalHours;
        }
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

    function secondsUntilCutoff() {
      if (!isClockedIn || !sessionCutoffRaw) return 0;
      var now = new Date();
      var parts = String(sessionCutoffRaw).split(":");
      if (parts.length < 2) return 0;
      var cutoff = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
        parseInt(parts[0], 10),
        parseInt(parts[1], 10),
        parseInt(parts[2] || "0", 10)
      );
      return Math.max(0, Math.floor((cutoff.getTime() - now.getTime()) / 1000));
    }

    function maxPreviewSeconds() {
      if (!isClockedIn || !openClockInRaw || !sessionCutoffRaw) return 0;
      var now = new Date();
      var startParts = String(openClockInRaw).split(":");
      var cutoffParts = String(sessionCutoffRaw).split(":");
      if (startParts.length < 2 || cutoffParts.length < 2) return 0;
      var start = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
        parseInt(startParts[0], 10),
        parseInt(startParts[1], 10),
        parseInt(startParts[2] || "0", 10)
      );
      var cutoff = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
        parseInt(cutoffParts[0], 10),
        parseInt(cutoffParts[1], 10),
        parseInt(cutoffParts[2] || "0", 10)
      );
      return Math.max(0, Math.floor((cutoff.getTime() - start.getTime()) / 1000));
    }

    var saved = loadState();
    if (isClockedIn) {
      var elapsed = Math.min(elapsedSinceOpenClockIn(), maxPreviewSeconds());
      if (elapsed > 0) {
        remainingSeconds = Math.max(0, remainingSecondsWithoutOpen - elapsed);
      }
    }

    if (saved) {
      var sameSession =
        isClockedIn && saved.sessionDate === todayKey && saved.clockInRaw === openClockInRaw;
      if (sameSession || !isClockedIn) {
        if (
          saved.seconds <= 0 &&
          remainingSecondsWithoutOpen > 0 &&
          (!sameSession || !isClockedIn)
        ) {
          remainingSeconds = remainingSecondsWithoutOpen;
        } else {
          remainingSeconds = Math.min(remainingSeconds, saved.seconds);
        }
      }
    }

    function updateTimer() {
      timerElement.textContent = formatHMS(remainingSeconds);
      updateInternalHoursFromSeconds();
      updateCompletionFromSeconds();

      if (isClockedIn && remainingSeconds > 0 && secondsUntilCutoff() > 0) {
        remainingSeconds--;
      }

      if (isClockedIn && remainingSeconds % 10 === 0) {
        saveState();
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
