/* Appearance page: custom themed dropdowns for select controls */
(function (global) {
  "use strict";

  if (!global || !global.document) {
    return;
  }

  var document = global.document;
  var states = [];

  function closeAll(exceptState) {
    for (var i = 0; i < states.length; i += 1) {
      var state = states[i];
      if (!state || !state.wrap) {
        continue;
      }
      if (exceptState && state === exceptState) {
        continue;
      }
      state.wrap.classList.remove("is-open");
      if (state.trigger) {
        state.trigger.setAttribute("aria-expanded", "false");
      }
    }
  }

  function syncState(state) {
    if (!state || !state.select) {
      return;
    }

    var select = state.select;
    var selectedIndex = select.selectedIndex >= 0 ? select.selectedIndex : 0;
    var selectedOption = select.options[selectedIndex] || null;
    var selectedValue = selectedOption ? selectedOption.value : "";

    if (state.label) {
      state.label.textContent = selectedOption ? selectedOption.text : "Select";
    }

    if (state.optionButtons && state.optionButtons.length) {
      for (var i = 0; i < state.optionButtons.length; i += 1) {
        var btn = state.optionButtons[i];
        var isSelected = btn.getAttribute("data-value") === selectedValue;
        btn.classList.toggle("is-selected", isSelected);
        btn.setAttribute("aria-selected", isSelected ? "true" : "false");
      }
    }

    if (state.trigger) {
      var isDisabled = !!select.disabled;
      state.trigger.disabled = isDisabled;
      state.trigger.classList.toggle("is-disabled", isDisabled);
    }
  }

  function buildOptions(state) {
    if (!state || !state.select || !state.menu) {
      return;
    }

    state.menu.innerHTML = "";
    state.optionButtons = [];

    var options = state.select.options || [];
    for (var i = 0; i < options.length; i += 1) {
      var option = options[i];
      var optionButton = document.createElement("button");
      optionButton.type = "button";
      optionButton.className = "theme-select-option";
      optionButton.setAttribute("role", "option");
      optionButton.setAttribute("data-value", option.value);
      optionButton.textContent = option.text;

      optionButton.addEventListener("click", function (event) {
        var target = event.currentTarget;
        var value = target ? target.getAttribute("data-value") : "";
        if (value === null) {
          return;
        }
        state.select.value = value;
        state.select.dispatchEvent(new Event("change", { bubbles: true }));
        state.select.dispatchEvent(new Event("input", { bubbles: true }));
        syncState(state);
        closeAll();
      });

      state.menu.appendChild(optionButton);
      state.optionButtons.push(optionButton);
    }
  }

  function initSelect(select) {
    if (!select || select.getAttribute("data-theme-custom-ready") === "1") {
      return;
    }

    var wrap = select.closest(".theme-select-wrap");
    if (!wrap) {
      return;
    }

    wrap.classList.add("is-enhanced");
    select.classList.add("theme-select-native");
    select.setAttribute("tabindex", "-1");
    select.setAttribute("aria-hidden", "true");

    var trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "theme-select-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");
    trigger.setAttribute("aria-label", "Choose option");

    var label = document.createElement("span");
    label.className = "theme-select-trigger-label";
    trigger.appendChild(label);

    var icon = document.createElement("span");
    icon.className = "theme-select-trigger-icon";
    icon.innerHTML = '<i class="feather-chevron-down"></i>';
    trigger.appendChild(icon);

    var menu = document.createElement("div");
    menu.className = "theme-select-menu";
    menu.setAttribute("role", "listbox");

    wrap.appendChild(trigger);
    wrap.appendChild(menu);

    var state = {
      select: select,
      wrap: wrap,
      trigger: trigger,
      label: label,
      menu: menu,
      optionButtons: [],
    };

    buildOptions(state);
    syncState(state);
    states.push(state);
    select.setAttribute("data-theme-custom-ready", "1");

    trigger.addEventListener("click", function () {
      if (trigger.disabled) {
        return;
      }
      var isOpen = wrap.classList.contains("is-open");
      if (isOpen) {
        closeAll();
        return;
      }
      closeAll(state);
      wrap.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
    });

    trigger.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeAll();
      }
    });

    select.addEventListener("change", function () {
      syncState(state);
    });
    select.addEventListener("input", function () {
      syncState(state);
    });
  }

  function syncAll() {
    for (var i = 0; i < states.length; i += 1) {
      syncState(states[i]);
    }
  }

  function initAll() {
    var selects = document.querySelectorAll(
      ".appearance-settings-page select.form-select[data-theme-custom-select='1']"
    );
    for (var i = 0; i < selects.length; i += 1) {
      initSelect(selects[i]);
    }
    syncAll();
  }

  function bindGlobalHandlers() {
    document.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest(".theme-select-wrap.is-enhanced")) {
        closeAll();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeAll();
      }
    });

    document.addEventListener("biotern:theme-customizer-sync", function () {
      syncAll();
    });
  }

  function boot() {
    initAll();
    bindGlobalHandlers();
  }

  if (
    global.BioTernRuntimeBoot &&
    typeof global.BioTernRuntimeBoot.boot === "function"
  ) {
    global.BioTernRuntimeBoot.boot({
      name: "appearance custom dropdowns",
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
