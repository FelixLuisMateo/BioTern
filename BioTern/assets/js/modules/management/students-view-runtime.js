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
      if (activeTrack === "internal") {
        if (!internalHoursDetailElement || !Number.isFinite(internalTotalHours) || internalTotalHours <= 0) return;
        internalHoursDetailElement.textContent = remainingWholeHours + " / " + internalTotalHours;
      } else if (internalHoursDetailElement && Number.isFinite(internalTotalHours) && internalTotalHours > 0) {
        internalHoursDetailElement.textContent =
          toInt(cfg && cfg.dataset.internalRemainingDisplay, 0) + " / " + internalTotalHours;
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

  function initializeFollowToggle() {
    var button = document.querySelector(".app-students-view-follow-toggle");
    if (!button) return;

    var studentId = String(button.getAttribute("data-student-id") || "");
    if (!studentId) return;

    var studentName = String(button.getAttribute("data-student-name") || "this student");
    var icon = button.querySelector(".app-students-view-follow-icon");
    var label = button.querySelector("span");
    var storageKey = "biotern_followed_students";

    function loadFollowed() {
      try {
        var raw = localStorage.getItem(storageKey);
        var parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.map(String) : [];
      } catch (e) {
        return [];
      }
    }

    function saveFollowed(values) {
      try {
        localStorage.setItem(storageKey, JSON.stringify(values));
      } catch (e) {}
    }

    function setState(isFollowing) {
      button.setAttribute("aria-pressed", isFollowing ? "true" : "false");
      button.classList.toggle("is-followed", isFollowing);
      if (icon) {
        icon.className = isFollowing
          ? "feather-eye-off me-2 app-students-view-follow-icon"
          : "feather-eye me-2 app-students-view-follow-icon";
      }
      if (label) {
        label.textContent = isFollowing ? "Following" : "Follow";
      }
    }

    var followed = loadFollowed();
    var isFollowing = followed.indexOf(studentId) !== -1;
    setState(isFollowing);

    button.addEventListener("click", function (event) {
      event.preventDefault();
      followed = loadFollowed();
      var index = followed.indexOf(studentId);
      var nowFollowing = index === -1;

      if (nowFollowing) {
        followed.push(studentId);
      } else {
        followed.splice(index, 1);
      }

      saveFollowed(followed);
      setState(nowFollowing);

      if (window.Swal && typeof window.Swal.fire === "function") {
        window.Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 2500,
          timerProgressBar: true,
          icon: nowFollowing ? "success" : "info",
          title: nowFollowing
            ? "Now following " + studentName
            : "Unfollowed " + studentName,
        });
      }
    });
  }

  function initializeInternalEvaluation() {
    var root = document.getElementById("studentInternalEval");
    var sheet = document.getElementById("studentInternalEvalPrintSheet");
    if (!root || !sheet) return;

    var ratings = Array.prototype.slice.call(
      root.querySelectorAll(".student-internal-eval-rating")
    );
    var totalCard = document.getElementById("studentInternalEvalTotal");
    var totalTable = document.getElementById("studentInternalEvalTableTotal");
    var hiddenTotal = document.getElementById("studentInternalEvalHiddenTotal");
    var recommendation = root.querySelector(".student-internal-eval-recommendation");
    var cfg = document.getElementById("students-view-runtime-config");
    var studentId = toInt(cfg && cfg.dataset.studentId, 0);
    var storageKey = "student_internal_evaluation_" + String(studentId || "draft");

    function saveDraft() {
      try {
        var draft = {
          ratings: {},
          meta: {},
          recommendation: recommendation ? recommendation.value : "",
        };
        ratings.forEach(function (input) {
          draft.ratings[input.getAttribute("data-eval-index")] = input.value || "";
        });
        Array.prototype.slice.call(root.querySelectorAll(".student-internal-eval-meta")).forEach(
          function (input) {
            draft.meta[input.getAttribute("data-eval-meta")] = input.value || "";
          }
        );
        localStorage.setItem(storageKey, JSON.stringify(draft));
      } catch (e) {}
    }

    function loadDraft() {
      try {
        var raw = localStorage.getItem(storageKey);
        var draft = raw ? JSON.parse(raw) : null;
        if (!draft || typeof draft !== "object") return;
        ratings.forEach(function (input) {
          var key = input.getAttribute("data-eval-index");
          if (draft.ratings && Object.prototype.hasOwnProperty.call(draft.ratings, key)) {
            input.value = draft.ratings[key] || "";
          }
        });
        Array.prototype.slice.call(root.querySelectorAll(".student-internal-eval-meta")).forEach(
          function (input) {
            var key = input.getAttribute("data-eval-meta");
            if (draft.meta && Object.prototype.hasOwnProperty.call(draft.meta, key)) {
              input.value = draft.meta[key] || "";
            }
          }
        );
        if (recommendation && typeof draft.recommendation === "string") {
          recommendation.value = draft.recommendation;
        }
      } catch (e) {}
    }

    function clampRating(input) {
      var max = toInt(input.getAttribute("data-eval-max"), 0);
      var value = toInt(input.value, 0);
      if (value < 0) value = 0;
      if (max > 0 && value > max) value = max;
      input.value = input.value === "" ? "" : String(value);
      return input.value === "" ? 0 : value;
    }

    function updatePrintValues() {
      var total = 0;
      ratings.forEach(function (input) {
        var index = input.getAttribute("data-eval-index");
        var value = clampRating(input);
        total += value;
        var target = sheet.querySelector('[data-eval-rating-output="' + index + '"]');
        if (target) target.textContent = value ? String(value) + "%" : "";
      });

      if (totalCard) totalCard.textContent = String(total);
      if (totalTable) totalTable.textContent = String(total);
      if (hiddenTotal) hiddenTotal.value = String(total);
      var totalOutput = sheet.querySelector('[data-eval-output="total"]');
      if (totalOutput) totalOutput.textContent = String(total) + "%";

      Array.prototype.slice.call(root.querySelectorAll(".student-internal-eval-meta")).forEach(
        function (input) {
          var key = input.getAttribute("data-eval-meta");
          var output = key ? sheet.querySelector('[data-eval-output="' + key + '"]') : null;
          if (output) output.textContent = input.value || "";
        }
      );

      var recommendationOutput = sheet.querySelector('[data-eval-output="recommendation"]');
      if (recommendationOutput) {
        recommendationOutput.textContent = recommendation ? recommendation.value : "";
      }
      saveDraft();
    }

    function printEvaluation() {
      updatePrintValues();

      var clone = sheet.cloneNode(true);
      Array.prototype.slice.call(clone.querySelectorAll("img")).forEach(function (img) {
        var raw = img.getAttribute("src");
        if (raw) img.setAttribute("src", new URL(raw, window.location.href).href);
      });

      var iframe = document.createElement("iframe");
      iframe.setAttribute("title", "Print Internal Evaluation");
      iframe.style.position = "fixed";
      iframe.style.right = "0";
      iframe.style.bottom = "0";
      iframe.style.width = "0";
      iframe.style.height = "0";
      iframe.style.border = "0";
      document.body.appendChild(iframe);

      var doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open();
      doc.write(
        '<!doctype html><html><head><meta charset="utf-8"><title>Internal Evaluation</title>' +
          '<style>@page{size:A4;margin:0}html,body{margin:0;background:#fff}.student-internal-eval-print-sheet{display:block}.student-internal-eval-paper{width:210mm;height:297mm;box-sizing:border-box;padding:12mm 15mm;background:#fff;color:#000;font-family:Arial,sans-serif;font-size:11px;line-height:1.18;overflow:hidden}.student-internal-eval-paper--page-1{page-break-after:always;break-after:page}.student-internal-eval-paper--page-2{padding-top:12mm}.student-internal-eval-print-header{display:flex;align-items:center;gap:18px;border-bottom:2px solid #111;padding-bottom:5px;margin-bottom:9px}.student-internal-eval-print-header img{width:58px;height:auto}.student-internal-eval-print-header h2,.student-internal-eval-print-header p{margin:0;text-align:center}.student-internal-eval-print-header h2{font-size:13.2px;font-weight:900}.student-internal-eval-print-header p{font-size:8.4px}.student-internal-eval-paper h3{margin:8px 0 13px;font-size:11.6px;line-height:1.15;text-align:center}.student-internal-eval-print-meta{width:48%;margin-bottom:17px}.student-internal-eval-print-meta p{display:grid;grid-template-columns:105px 1fr;gap:8px;margin:4px 0}.student-internal-eval-print-meta span{min-height:15px;border-bottom:1px solid #111}.student-internal-eval-print-purpose{margin:14px 0 14px}.student-internal-eval-print-scale{width:54%;margin:8px auto 15px}.student-internal-eval-print-scale p{display:grid;grid-template-columns:96px 1fr;margin:3px 0}.student-internal-eval-print-part-title{margin:0 0 4px;font-weight:700}.student-internal-eval-print-table{width:100%;border-collapse:collapse}.student-internal-eval-print-table th,.student-internal-eval-print-table td{border:1px solid #111;padding:3px 5px;vertical-align:top}.student-internal-eval-print-table th{text-align:center;text-transform:uppercase}.student-internal-eval-print-table td:nth-child(2),.student-internal-eval-print-table td:nth-child(3){width:86px;text-align:center}.student-internal-eval-print-group td{font-weight:900;text-transform:uppercase}.student-internal-eval-print-recommendation{margin-top:18px}.student-internal-eval-print-recommendation p{margin-bottom:8px}.student-internal-eval-print-recommendation div{min-height:82px;white-space:pre-wrap;background-image:repeating-linear-gradient(to bottom,transparent 0,transparent 18px,#111 19px);line-height:19px}.student-internal-eval-print-recommendation span{display:block;min-height:19px}.student-internal-eval-print-signature{width:190px;margin:78px 58px 0 auto;padding-top:6px;border-top:1px solid #111;text-align:center}</style>' +
          '</head><body>' +
          clone.innerHTML +
          "</body></html>"
      );
      doc.close();

      setTimeout(function () {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(function () {
          iframe.remove();
        }, 500);
      }, 250);
    }

    ratings.forEach(function (input) {
      input.addEventListener("input", updatePrintValues);
      input.addEventListener("blur", updatePrintValues);
    });
    Array.prototype.slice.call(root.querySelectorAll(".student-internal-eval-meta")).forEach(
      function (input) {
        input.addEventListener("input", updatePrintValues);
      }
    );
    if (recommendation) recommendation.addEventListener("input", updatePrintValues);

    var resetButton = document.getElementById("studentInternalEvalReset");
    if (resetButton) {
      resetButton.addEventListener("click", function () {
        ratings.forEach(function (input) {
          input.value = "";
        });
        if (recommendation) recommendation.value = "";
        try {
          localStorage.removeItem(storageKey);
        } catch (e) {}
        updatePrintValues();
      });
    }

    var printButton = document.getElementById("studentInternalEvalPrint");
    if (printButton) printButton.addEventListener("click", printEvaluation);
    root.addEventListener("submit", updatePrintValues);

    loadDraft();
    updatePrintValues();
  }

  function initializeRequestedTab() {
    var params;
    try {
      params = new URLSearchParams(window.location.search);
    } catch (e) {
      return;
    }
    if (params.get("tab") !== "evaluation") return;

    var trigger = document.querySelector('[data-bs-target="#evaluationTab"]');
    if (!trigger) return;

    if (window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    } else {
      trigger.click();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeTimer);
  } else {
    initializeTimer();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeFollowToggle);
  } else {
    initializeFollowToggle();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeInternalEvaluation);
  } else {
    initializeInternalEvaluation();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeRequestedTab);
  } else {
    initializeRequestedTab();
  }
})();
