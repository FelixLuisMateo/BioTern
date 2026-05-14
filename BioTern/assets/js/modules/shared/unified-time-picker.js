/* Shared runtime: unified clock-style time picker for select-based time fields */
(function (global) {
  "use strict";

  if (!global || global.BioTernUnifiedTimePicker) {
    return;
  }

  var initialized = new WeakMap();
  var openPicker = null;
  var timeValuePattern = /^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/;

  function pad(value) {
    return String(value).padStart(2, "0");
  }

  function parseTimeValue(value) {
    var match = String(value || "").trim().match(timeValuePattern);
    if (!match) {
      return null;
    }
    return {
      hour: Number(match[1]),
      minute: Number(match[2]),
      value: match[1] + ":" + match[2],
    };
  }

  function formatTimeLabel(value, fallback) {
    var parsed = parseTimeValue(value);
    if (!parsed) {
      return fallback || "Select time";
    }
    var hour12 = parsed.hour % 12 || 12;
    return hour12 + ":" + pad(parsed.minute) + " " + (parsed.hour < 12 ? "AM" : "PM");
  }

  function shouldEnhance(select) {
    if (!(select instanceof HTMLSelectElement)) {
      return false;
    }
    if (select.dataset.timepickerDisabled === "1") {
      return false;
    }
    if (select.dataset.uiSelect === "custom") {
      return false;
    }
    if (select.classList.contains("external-manual-time-select") || select.classList.contains("student-dtr-time-select")) {
      return true;
    }
    if (select.dataset.unifiedTime === "1") {
      return true;
    }

    var name = (select.getAttribute("name") || "").toLowerCase();
    var id = (select.id || "").toLowerCase();
    var combined = name + " " + id;

    if (combined.indexOf("timezone") !== -1) {
      return false;
    }
    return /(^|[_-])(start|end)?_?time($|[_-])|(_time$)|(^|[_-])(morning|afternoon|break)_time_(in|out)$/.test(combined);
  }

  function getOptions(select) {
    var options = [];
    Array.prototype.slice.call(select.options || []).forEach(function (option) {
      var value = String(option.value || "").trim();
      var parsed = parseTimeValue(value);
      if (!value || !parsed) {
        return;
      }
      options.push({
        value: parsed.value,
        hour: parsed.hour,
        minute: parsed.minute,
        label: option.textContent.trim() || formatTimeLabel(value),
        disabled: option.disabled,
      });
    });
    return options;
  }

  function uniqueNumbers(values) {
    return Array.from(new Set(values)).sort(function (a, b) {
      return a - b;
    });
  }

  function findOption(options, hour, minute) {
    var value = pad(hour) + ":" + pad(minute);
    return options.find(function (option) {
      return option.value === value && !option.disabled;
    }) || null;
  }

  function firstEnabled(options) {
    return options.find(function (option) {
      return !option.disabled;
    }) || null;
  }

  function dispatchNativeEvents(select) {
    select.dispatchEvent(new Event("input", { bubbles: true }));
    select.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function createButton(className, text, type) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.textContent = text;
    if (type) {
      button.dataset.timePart = type;
    }
    return button;
  }

  function closeCurrent() {
    if (openPicker && typeof openPicker.close === "function") {
      openPicker.close();
    }
  }

  function buildPicker(select) {
    var wrapper = document.createElement("div");
    var trigger = document.createElement("button");
    var triggerMain = document.createElement("span");
    var triggerIcon = document.createElement("i");
    var triggerLabel = document.createElement("span");
    var triggerChevron = document.createElement("i");
    var popover = document.createElement("div");
    var head = document.createElement("div");
    var title = document.createElement("p");
    var preview = document.createElement("span");
    var periods = document.createElement("div");
    var clock = document.createElement("div");
    var minutes = document.createElement("div");
    var actions = document.createElement("div");
    var state = {
      hour: null,
      minute: null,
      period: "AM",
    };

    wrapper.className = "biotern-time-picker";
    trigger.type = "button";
    trigger.className = "biotern-time-trigger";
    trigger.setAttribute("aria-haspopup", "dialog");
    trigger.setAttribute("aria-expanded", "false");
    triggerMain.className = "biotern-time-trigger-main";
    triggerIcon.className = "feather-clock biotern-time-trigger-icon";
    triggerLabel.className = "biotern-time-trigger-label";
    triggerChevron.className = "feather-chevron-down biotern-time-trigger-chevron";
    triggerMain.append(triggerIcon, triggerLabel);
    trigger.append(triggerMain, triggerChevron);

    popover.className = "biotern-time-popover";
    popover.hidden = true;
    popover.setAttribute("role", "dialog");
    title.className = "biotern-time-title";
    title.textContent = "Select time";
    preview.className = "biotern-time-preview";
    head.className = "biotern-time-head";
    head.append(title, preview);
    periods.className = "biotern-time-periods";
    clock.className = "biotern-time-clock";
    minutes.className = "biotern-time-minutes";
    actions.className = "biotern-time-actions";
    actions.append(
      createButton("biotern-time-action", "Now", "now"),
      createButton("biotern-time-action", "Clear", "clear")
    );
    popover.append(head, periods, clock, minutes, actions);
    wrapper.append(trigger, popover);

    function setStateFromValue(value) {
      var parsed = parseTimeValue(value);
      if (!parsed) {
        var fallback = firstEnabled(getOptions(select));
        parsed = fallback ? { hour: fallback.hour, minute: fallback.minute, value: fallback.value } : null;
      }
      if (parsed) {
        state.hour = parsed.hour;
        state.minute = parsed.minute;
        state.period = parsed.hour >= 12 ? "PM" : "AM";
      }
    }

    function updateTrigger() {
      var selected = select.options[select.selectedIndex];
      var label = selected && selected.value ? selected.textContent.trim() : "";
      triggerLabel.textContent = label || formatTimeLabel(select.value, "Select time");
      trigger.disabled = select.disabled;
    }

    function commitValue(value) {
      select.value = value || "";
      updateTrigger();
      setStateFromValue(select.value);
      render();
      dispatchNativeEvents(select);
    }

    function getHourForPeriod(hour12, period) {
      var normalized = Number(hour12) % 12;
      return period === "PM" ? normalized + 12 : normalized;
    }

    function chooseNearestForState(nextHour, nextMinute, nextPeriod) {
      var options = getOptions(select).filter(function (option) {
        return !option.disabled;
      });
      if (!options.length) {
        return null;
      }
      var targetHour = typeof nextHour === "number" ? nextHour : state.hour;
      var targetMinute = typeof nextMinute === "number" ? nextMinute : state.minute;
      var period = nextPeriod || state.period || "AM";
      if (targetHour === null) {
        targetHour = period === "PM" ? 13 : 8;
      }
      if (targetHour <= 12 && nextPeriod) {
        targetHour = getHourForPeriod(targetHour, period);
      }
      if (targetMinute === null) {
        targetMinute = 0;
      }
      var targetValue = targetHour * 60 + targetMinute;
      return options
        .slice()
        .sort(function (a, b) {
          var aScore = Math.abs(a.hour * 60 + a.minute - targetValue);
          var bScore = Math.abs(b.hour * 60 + b.minute - targetValue);
          return aScore - bScore;
        })[0] || null;
    }

    function renderPeriods(options) {
      periods.innerHTML = "";
      ["AM", "PM"].forEach(function (period) {
        var hasPeriod = options.some(function (option) {
          return (period === "PM") === (option.hour >= 12) && !option.disabled;
        });
        var button = createButton("biotern-time-period", period, "period");
        button.dataset.period = period;
        button.disabled = !hasPeriod;
        button.classList.toggle("is-active", state.period === period);
        periods.appendChild(button);
      });
    }

    function renderClock(options) {
      var enabledHours = new Set(options.filter(function (option) {
        return !option.disabled && ((state.period === "PM") === (option.hour >= 12));
      }).map(function (option) {
        return option.hour % 12 || 12;
      }));
      clock.innerHTML = "";
      for (var hour = 1; hour <= 12; hour += 1) {
        var button = createButton("biotern-time-hour", String(hour), "hour");
        var angle = ((hour % 12) * 30 - 90) * (Math.PI / 180);
        var radius = 82;
        button.style.left = (107 + Math.cos(angle) * radius) + "px";
        button.style.top = (107 + Math.sin(angle) * radius) + "px";
        button.dataset.hour = String(hour);
        button.disabled = !enabledHours.has(hour);
        button.classList.toggle("is-active", state.hour !== null && (state.hour % 12 || 12) === hour);
        clock.appendChild(button);
      }
    }

    function renderMinutes(options) {
      var activeHour = state.hour;
      var minuteValues = uniqueNumbers(options.filter(function (option) {
        return !option.disabled && (activeHour === null || option.hour === activeHour);
      }).map(function (option) {
        return option.minute;
      }));
      if (!minuteValues.length) {
        minuteValues = uniqueNumbers(options.filter(function (option) {
          return !option.disabled;
        }).map(function (option) {
          return option.minute;
        }));
      }
      minutes.innerHTML = "";
      minuteValues.forEach(function (minute) {
        var button = createButton("biotern-time-minute", ":" + pad(minute), "minute");
        button.dataset.minute = String(minute);
        button.classList.toggle("is-active", state.minute === minute);
        minutes.appendChild(button);
      });
    }

    function render() {
      var options = getOptions(select);
      var parsed = parseTimeValue(select.value);
      if (parsed) {
        state.hour = parsed.hour;
        state.minute = parsed.minute;
        state.period = parsed.hour >= 12 ? "PM" : "AM";
      }
      updateTrigger();
      preview.textContent = parsed ? formatTimeLabel(parsed.value) : "Select time";
      renderPeriods(options);
      renderClock(options);
      renderMinutes(options);
    }

    function clearPopoverPosition() {
      popover.style.top = "";
      popover.style.right = "";
      popover.style.bottom = "";
      popover.style.left = "";
      popover.style.width = "";
    }

    function placePopover() {
      if (popover.hidden) {
        return;
      }
      if (global.matchMedia && global.matchMedia("(max-width: 575.98px)").matches) {
        clearPopoverPosition();
        return;
      }

      var viewportPadding = 12;
      var rect = trigger.getBoundingClientRect();
      var width = Math.min(340, Math.max(260, window.innerWidth - (viewportPadding * 2)));
      var left = Math.min(Math.max(viewportPadding, rect.left), window.innerWidth - width - viewportPadding);
      var top = rect.bottom + 8;
      var height = popover.offsetHeight || 420;

      if (top + height > window.innerHeight - viewportPadding) {
        top = Math.max(viewportPadding, rect.top - height - 8);
      }

      popover.style.width = width + "px";
      popover.style.left = left + "px";
      popover.style.top = top + "px";
      popover.style.right = "auto";
      popover.style.bottom = "auto";
    }

    function open() {
      if (select.disabled) {
        return;
      }
      closeCurrent();
      openPicker = api;
      setStateFromValue(select.value);
      render();
      popover.hidden = false;
      wrapper.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
      requestAnimationFrame(placePopover);
    }

    function close() {
      popover.hidden = true;
      clearPopoverPosition();
      wrapper.classList.remove("is-open");
      trigger.setAttribute("aria-expanded", "false");
      if (openPicker === api) {
        openPicker = null;
      }
    }

    function chooseHour(hour12) {
      var hour = getHourForPeriod(hour12, state.period);
      var option = findOption(getOptions(select), hour, state.minute);
      if (!option) {
        option = chooseNearestForState(hour, state.minute, state.period);
      }
      if (option) {
        commitValue(option.value);
      }
    }

    function chooseMinute(minute) {
      var option = findOption(getOptions(select), state.hour, minute);
      if (!option) {
        option = chooseNearestForState(state.hour, minute, state.period);
      }
      if (option) {
        commitValue(option.value);
      }
    }

    function choosePeriod(period) {
      var hour12 = state.hour === null ? 8 : (state.hour % 12 || 12);
      var hour = getHourForPeriod(hour12, period);
      var option = findOption(getOptions(select), hour, state.minute);
      if (!option) {
        option = chooseNearestForState(hour, state.minute, period);
      }
      if (option) {
        commitValue(option.value);
      }
    }

    function chooseNow() {
      var now = new Date();
      var minutesAvailable = uniqueNumbers(getOptions(select).filter(function (option) {
        return !option.disabled;
      }).map(function (option) {
        return option.minute;
      }));
      var minute = now.getMinutes();
      if (minutesAvailable.length) {
        minute = minutesAvailable.slice().sort(function (a, b) {
          return Math.abs(a - now.getMinutes()) - Math.abs(b - now.getMinutes());
        })[0];
      }
      var option = chooseNearestForState(now.getHours(), minute, now.getHours() >= 12 ? "PM" : "AM");
      if (option) {
        commitValue(option.value);
        close();
      }
    }

    trigger.addEventListener("click", function () {
      if (popover.hidden) {
        open();
      } else {
        close();
      }
    });

    popover.addEventListener("click", function (event) {
      var button = event.target.closest("button[data-time-part]");
      if (!button || button.disabled) {
        return;
      }
      var part = button.dataset.timePart;
      if (part === "hour") {
        chooseHour(Number(button.dataset.hour));
      } else if (part === "minute") {
        chooseMinute(Number(button.dataset.minute));
      } else if (part === "period") {
        choosePeriod(button.dataset.period);
      } else if (part === "now") {
        chooseNow();
      } else if (part === "clear") {
        commitValue("");
        close();
      }
    });

    select.addEventListener("change", render);

    var optionObserver = new MutationObserver(render);
    optionObserver.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ["selected", "disabled"] });

    var api = {
      close: close,
      place: placePopover,
      refresh: render,
      destroy: function () {
        optionObserver.disconnect();
        wrapper.remove();
        select.classList.remove("biotern-time-native");
        initialized.delete(select);
      },
    };

    select.classList.add("biotern-time-native");
    select.dataset.unifiedTime = "1";
    select.insertAdjacentElement("afterend", wrapper);
    render();
    return api;
  }

  function initSelect(select) {
    if (!shouldEnhance(select) || initialized.has(select)) {
      return;
    }
    initialized.set(select, buildPicker(select));
  }

  function collectSelects(root) {
    var base = root && root.querySelectorAll ? root : document;
    var selector = [
      "select.external-manual-time-select",
      "select.student-dtr-time-select",
      "select[data-unified-time='1']",
      "select[name$='_time']",
      "select[name*='time_']",
      "select[name='start_time']",
      "select[name='end_time']",
    ].join(",");
    var nodes = [];

    if (base instanceof HTMLSelectElement) {
      nodes.push(base);
    }

    Array.prototype.slice.call(base.querySelectorAll ? base.querySelectorAll(selector) : []).forEach(function (node) {
      nodes.push(node);
    });
    return nodes;
  }

  function refresh(root) {
    collectSelects(root).forEach(initSelect);
  }

  function observeDom() {
    if (!("MutationObserver" in global)) {
      return;
    }
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.slice.call(mutation.addedNodes || []).forEach(function (node) {
          if (node instanceof HTMLElement) {
            refresh(node);
          }
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function boot() {
    refresh(document);
    observeDom();
    document.addEventListener("pointerdown", function (event) {
      if (openPicker && !event.target.closest(".biotern-time-picker")) {
        closeCurrent();
      }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeCurrent();
      }
    });
    global.addEventListener("resize", function () {
      if (openPicker && typeof openPicker.place === "function") {
        openPicker.place();
      }
    });
    global.addEventListener("scroll", function () {
      if (openPicker && typeof openPicker.place === "function") {
        openPicker.place();
      }
    }, true);
  }

  global.BioTernUnifiedTimePicker = {
    boot: boot,
    refresh: refresh,
  };

  global.AppCore = global.AppCore || {};
  global.AppCore.TimePicker = global.BioTernUnifiedTimePicker;

  if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
    global.BioTernRuntimeBoot.boot({
      name: "unified-time-picker",
      run: boot,
    });
    return;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(window);
