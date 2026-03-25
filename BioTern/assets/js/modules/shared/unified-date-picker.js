/* Shared runtime: unified date picker for all date inputs */
(function (global) {
  "use strict";

  if (!global || global.BioTernUnifiedDatePicker) {
    return;
  }

  var initialized = new WeakMap();
  var dateRegex = /^\d{4}-\d{2}-\d{2}$/;

  function normalizeDateValue(value) {
    var raw = typeof value === "string" ? value.trim() : "";
    if (!raw) {
      return "";
    }
    if (dateRegex.test(raw)) {
      return raw;
    }

    var parsed = new Date(raw);
    if (Number.isNaN(parsed.getTime())) {
      return raw;
    }

    var year = String(parsed.getFullYear()).padStart(4, "0");
    var month = String(parsed.getMonth() + 1).padStart(2, "0");
    var day = String(parsed.getDate()).padStart(2, "0");
    return year + "-" + month + "-" + day;
  }

  function getPickerOptions(input) {
    var options = {
      format: "yyyy-mm-dd",
      autohide: true,
      todayBtn: true,
      todayBtnMode: 1,
      clearBtn: true,
      todayHighlight: true,
    };

    var minDate = input.getAttribute("min");
    var maxDate = input.getAttribute("max");
    if (minDate) {
      options.minDate = minDate;
    }
    if (maxDate) {
      options.maxDate = maxDate;
    }

    if (input.dataset.datepickerAutohide === "0") {
      options.autohide = false;
    }
    if (input.dataset.datepickerTodayBtn === "0") {
      options.todayBtn = false;
    }
    if (input.dataset.datepickerClearBtn === "0") {
      options.clearBtn = false;
    }

    return options;
  }

  function setInputValidity(input) {
    if (!input) {
      return;
    }
    var normalized = normalizeDateValue(input.value);
    var minDate = (input.getAttribute("min") || "").trim();
    var maxDate = (input.getAttribute("max") || "").trim();

    if (!normalized) {
      input.setCustomValidity("");
      return;
    }
    if (dateRegex.test(normalized)) {
      if (dateRegex.test(minDate) && normalized < minDate) {
        input.setCustomValidity("Date must be on or after " + minDate + ".");
        return;
      }
      if (dateRegex.test(maxDate) && normalized > maxDate) {
        input.setCustomValidity("Date must be on or before " + maxDate + ".");
        return;
      }
      input.value = normalized;
      input.setCustomValidity("");
      return;
    }
    input.setCustomValidity("Use YYYY-MM-DD date format.");
  }

  function initInput(input) {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    if (initialized.has(input)) {
      return;
    }
    if (input.dataset.datepickerDisabled === "1") {
      return;
    }

    var isLockedField = input.disabled || input.readOnly;

    var initialValue = normalizeDateValue(input.value);
    if (initialValue && initialValue !== input.value) {
      input.value = initialValue;
    }

    if (input.type === "date") {
      input.dataset.originalType = "date";
      input.type = "text";
    }

    input.dataset.unifiedDate = "1";
    input.classList.add("ui-date-input");
    input.autocomplete = "off";
    if (!input.placeholder) {
      input.placeholder = "YYYY-MM-DD";
    }

    if (isLockedField) {
      initialized.set(input, null);
      return;
    }

    var pickerInstance = null;
    if (typeof global.Datepicker === "function") {
      try {
        pickerInstance = new global.Datepicker(input, getPickerOptions(input));
        if (input.value) {
          pickerInstance.setDate(input.value, { render: false });
          pickerInstance.refresh("input", true);
        }
      } catch (err) {
        pickerInstance = null;
      }
    }

    input.addEventListener("changeDate", function () {
      setInputValidity(input);
    });
    input.addEventListener("blur", function () {
      setInputValidity(input);
    });
    input.addEventListener("input", function () {
      input.setCustomValidity("");
    });

    initialized.set(input, pickerInstance);
  }

  function collectInputs(root) {
    var base = root && root.querySelectorAll ? root : document;
    var selector = 'input[type="date"], input[data-unified-date="1"]';
    var nodes = [];

    if (base instanceof HTMLInputElement && (base.type === "date" || base.dataset.unifiedDate === "1")) {
      nodes.push(base);
    }

    var found = base.querySelectorAll ? base.querySelectorAll(selector) : [];
    for (var i = 0; i < found.length; i += 1) {
      nodes.push(found[i]);
    }
    return nodes;
  }

  function refresh(root) {
    var inputs = collectInputs(root);
    for (var i = 0; i < inputs.length; i += 1) {
      initInput(inputs[i]);
    }
  }

  function bindFormValidation() {
    document.addEventListener(
      "submit",
      function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
          return;
        }
        var dateInputs = form.querySelectorAll("input.ui-date-input");
        for (var i = 0; i < dateInputs.length; i += 1) {
          setInputValidity(dateInputs[i]);
          if (!dateInputs[i].checkValidity()) {
            event.preventDefault();
            dateInputs[i].reportValidity();
            dateInputs[i].focus();
            return;
          }
        }
      },
      true
    );
  }

  function observeDom() {
    if (!("MutationObserver" in global)) {
      return;
    }
    var observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i += 1) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j += 1) {
          var node = added[j];
          if (node instanceof HTMLElement) {
            refresh(node);
          }
        }
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function boot() {
    refresh(document);
    bindFormValidation();
    observeDom();
  }

  global.BioTernUnifiedDatePicker = {
    boot: boot,
    refresh: refresh,
  };

  global.AppCore = global.AppCore || {};
  global.AppCore.DatePicker = global.BioTernUnifiedDatePicker;

  if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
    global.BioTernRuntimeBoot.boot({
      name: "unified-date-picker",
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
