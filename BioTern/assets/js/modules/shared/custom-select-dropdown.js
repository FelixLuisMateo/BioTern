(function (global) {
  "use strict";

  if (!global || !global.document) {
    return;
  }

  var document = global.document;
  var states = [];
  var INIT_FLAG = "__bioternCustomSelectDropdownInit";
  var MODE_STORAGE_KEY = "biotern_ui_select_mode";
  var SELECT2_PATCH_FLAG = "__bioternSelect2CompatPatched";
  var OBSERVER_FLAG = "__bioternSelectDropdownObserver";
  var THEME_VARS = [
    "--biotern-select-trigger-bg",
    "--biotern-select-trigger-border",
    "--biotern-select-trigger-text",
    "--biotern-select-trigger-shadow",
    "--biotern-select-menu-bg",
    "--biotern-select-menu-border",
    "--biotern-select-menu-shadow",
    "--biotern-select-option-text",
    "--biotern-select-option-hover-bg",
    "--biotern-select-option-hover-border",
    "--biotern-select-option-selected-bg",
    "--biotern-select-option-selected-border",
    "--biotern-select-option-selected-text",
    "--biotern-select-focus-ring",
    "--biotern-select-icon-color"
  ];

  function normalizeMode(mode) {
    var value = (mode || "").toString().trim().toLowerCase();
    if (value === "compat" || value === "native" || value === "fallback") {
      return "compat";
    }
    return "custom";
  }

  function getStoredMode() {
    try {
      return global.localStorage ? global.localStorage.getItem(MODE_STORAGE_KEY) : "";
    } catch (err) {
      return "";
    }
  }

  function getConfiguredMode() {
    var htmlMode = document.documentElement
      ? document.documentElement.getAttribute("data-ui-select-mode")
      : "";
    var bodyMode =
      document.body && document.body.getAttribute
        ? document.body.getAttribute("data-ui-select-mode")
        : "";
    return normalizeMode(htmlMode || bodyMode || getStoredMode());
  }

  function applyModeAttribute(mode) {
    var normalized = normalizeMode(mode);
    if (document.documentElement) {
      document.documentElement.setAttribute("data-ui-select-mode", normalized);
    }
    if (document.body) {
      document.body.setAttribute("data-ui-select-mode", normalized);
    }
  }

  function syncMenuTheme(state) {
    if (!state || !state.wrap || !state.menu || !global.getComputedStyle) {
      return;
    }

    var computed = global.getComputedStyle(state.wrap);
    for (var i = 0; i < THEME_VARS.length; i += 1) {
      var name = THEME_VARS[i];
      var value = computed.getPropertyValue(name);
      if (value) {
        state.menu.style.setProperty(name, value.trim());
      }
    }
  }

  function positionMenu(state) {
    if (!state || !state.menu || !state.trigger) {
      return;
    }

    var rect = state.trigger.getBoundingClientRect();
    var viewportWidth = global.innerWidth || document.documentElement.clientWidth || 0;
    var viewportHeight = global.innerHeight || document.documentElement.clientHeight || 0;
    var menuWidth = Math.max(rect.width, 180);

    state.menu.style.position = "fixed";
    state.menu.style.width = menuWidth + "px";
    state.menu.style.minWidth = menuWidth + "px";
    state.menu.style.maxWidth = menuWidth + "px";
    state.menu.style.left = Math.max(12, Math.min(rect.left, viewportWidth - menuWidth - 12)) + "px";
    state.menu.style.top = Math.min(rect.bottom + 6, viewportHeight - 12) + "px";

    var menuHeight = state.menu.offsetHeight || 0;
    if (menuHeight > 0 && rect.bottom + 6 + menuHeight > viewportHeight - 12) {
      var aboveTop = rect.top - menuHeight - 6;
      if (aboveTop >= 12) {
        state.menu.style.top = aboveTop + "px";
      } else {
        state.menu.style.top = "12px";
        state.menu.style.maxHeight = Math.max(120, viewportHeight - 24) + "px";
      }
    } else {
      state.menu.style.maxHeight = "280px";
    }
  }

  function attachMenuToBody(state) {
    if (!state || !state.menu) {
      return;
    }

    if (state.menu.parentNode !== document.body) {
      document.body.appendChild(state.menu);
    }
    syncMenuTheme(state);
    state.menu.classList.add("biotern-select-menu-portal");
    positionMenu(state);
  }

  function restoreMenu(state) {
    if (!state || !state.wrap || !state.menu) {
      return;
    }

    if (state.menu.parentNode !== state.wrap) {
      state.wrap.appendChild(state.menu);
    }
    state.menu.classList.remove("biotern-select-menu-portal");
    for (var i = 0; i < THEME_VARS.length; i += 1) {
      state.menu.style.removeProperty(THEME_VARS[i]);
    }
    state.menu.style.position = "";
    state.menu.style.top = "";
    state.menu.style.left = "";
    state.menu.style.width = "";
    state.menu.style.minWidth = "";
    state.menu.style.maxWidth = "";
    state.menu.style.maxHeight = "";
  }

  function shouldEnhance(select) {
    if (!select) {
      return false;
    }

    var mode = (select.getAttribute("data-ui-select") || "").toLowerCase();
    if (mode === "native" || mode === "compat") {
      return false;
    }
    if (getConfiguredMode() === "compat") {
      return false;
    }
    return true;
  }

  function isEligible(select) {
    if (!select || select.tagName !== "SELECT") {
      return false;
    }
    if (select.multiple || select.size > 1) {
      return false;
    }
    if (
      select.classList.contains("biotern-time-native") ||
      select.classList.contains("external-manual-time-select") ||
      select.classList.contains("student-dtr-time-select") ||
      select.dataset.unifiedTime === "1"
    ) {
      return false;
    }
    if (select.classList.contains("select2-hidden-accessible")) {
      return false;
    }
    if (select.closest(".theme-select-wrap")) {
      return false;
    }
    if (select.getAttribute("data-ui-select-ready") === "1") {
      return false;
    }
    return shouldEnhance(select);
  }

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
      state.trigger.setAttribute("aria-expanded", "false");
      restoreMenu(state);
    }
  }

  function findStateBySelect(select) {
    for (var i = 0; i < states.length; i += 1) {
      if (states[i] && states[i].select === select) {
        return states[i];
      }
    }
    return null;
  }

  function getSelectedOption(select) {
    if (!select || !select.options || !select.options.length) {
      return null;
    }
    var index = select.selectedIndex >= 0 ? select.selectedIndex : 0;
    return select.options[index] || null;
  }

  function syncState(state) {
    if (!state || !state.select) {
      return;
    }

    var select = state.select;
    var selectedOption = getSelectedOption(select);
    var selectedValue = selectedOption ? selectedOption.value : "";

    state.label.textContent = selectedOption ? selectedOption.text : "Select";
    state.trigger.disabled = !!select.disabled;
    state.trigger.classList.toggle("is-disabled", !!select.disabled);

    for (var i = 0; i < state.optionButtons.length; i += 1) {
      var button = state.optionButtons[i];
      var isSelected = button.getAttribute("data-value") === selectedValue;
      button.classList.toggle("is-selected", isSelected);
      button.setAttribute("aria-selected", isSelected ? "true" : "false");
    }
  }

  function buildOptions(state) {
    if (!state || !state.menu || !state.select) {
      return;
    }

    state.menu.innerHTML = "";
    state.optionButtons = [];

    var options = state.select.options || [];
    for (var i = 0; i < options.length; i += 1) {
      var option = options[i];
      var optionButton = document.createElement("button");
      optionButton.type = "button";
      optionButton.className = "biotern-select-option";
      optionButton.setAttribute("role", "option");
      optionButton.setAttribute("data-value", option.value);
      optionButton.disabled = !!option.disabled;
      optionButton.textContent = option.text;

      optionButton.addEventListener("click", function (event) {
        var current = event.currentTarget;
        if (!current || current.disabled) {
          return;
        }
        var value = current.getAttribute("data-value");
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

  function ensureWrapper(select) {
    var currentParent = select.parentElement;
    if (currentParent && currentParent.classList.contains("biotern-select-wrap")) {
      return currentParent;
    }

    var wrapper = document.createElement("div");
    wrapper.className = "biotern-select-wrap";
    if (select.classList.contains("form-control-sm") || select.classList.contains("form-select-sm")) {
      wrapper.classList.add("is-small");
    }

    if (currentParent) {
      currentParent.insertBefore(wrapper, select);
    }
    wrapper.appendChild(select);
    return wrapper;
  }

  function createState(select) {
    var wrap = ensureWrapper(select);

    var originalTabindex = select.getAttribute("tabindex");
    var originalAriaHidden = select.getAttribute("aria-hidden");

    select.classList.add("biotern-select-native");
    select.setAttribute("tabindex", "-1");
    select.setAttribute("aria-hidden", "true");

    var trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "biotern-select-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");

    var label = document.createElement("span");
    label.className = "biotern-select-trigger-label";
    trigger.appendChild(label);

    var icon = document.createElement("span");
    icon.className = "biotern-select-trigger-icon";
    icon.innerHTML = '<i class="feather-chevron-down"></i>';
    trigger.appendChild(icon);

    var menu = document.createElement("div");
    menu.className = "biotern-select-menu";
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
      originalTabindex: originalTabindex,
      originalAriaHidden: originalAriaHidden,
    };

    buildOptions(state);
    syncState(state);
    return state;
  }

  function destroyState(state) {
    if (!state || !state.select || !state.wrap) {
      return;
    }

    restoreMenu(state);

    if (state.trigger && state.trigger.parentNode) {
      state.trigger.parentNode.removeChild(state.trigger);
    }
    if (state.menu && state.menu.parentNode) {
      state.menu.parentNode.removeChild(state.menu);
    }

    state.select.classList.remove("biotern-select-native");
    state.select.removeAttribute("data-ui-select-ready");

    if (state.originalTabindex === null) {
      state.select.removeAttribute("tabindex");
    } else {
      state.select.setAttribute("tabindex", state.originalTabindex);
    }

    if (state.originalAriaHidden === null) {
      state.select.removeAttribute("aria-hidden");
    } else {
      state.select.setAttribute("aria-hidden", state.originalAriaHidden);
    }

    var wrapParent = state.wrap.parentNode;
    if (wrapParent) {
      wrapParent.insertBefore(state.select, state.wrap);
      wrapParent.removeChild(state.wrap);
    }

    for (var i = states.length - 1; i >= 0; i -= 1) {
      if (states[i] === state) {
        states.splice(i, 1);
      }
    }
  }

  function destroySelect(select) {
    var state = findStateBySelect(select);
    if (state) {
      destroyState(state);
    }
  }

  function destroyAll() {
    closeAll();
    for (var i = states.length - 1; i >= 0; i -= 1) {
      destroyState(states[i]);
    }
  }

  function refreshState(state) {
    if (!state || !state.select) {
      return;
    }

    if (!document.body || !document.body.contains(state.select)) {
      destroyState(state);
      return;
    }

    if (
      state.select.classList.contains("select2-hidden-accessible") ||
      !shouldEnhance(state.select)
    ) {
      destroyState(state);
      return;
    }

    buildOptions(state);
    syncState(state);

    if (state.wrap && state.wrap.classList.contains("is-open")) {
      attachMenuToBody(state);
    }
  }

  function initSelect(select) {
    if (!isEligible(select)) {
      return;
    }

    var state = createState(select);
    states.push(state);
    select.setAttribute("data-ui-select-ready", "1");

    state.trigger.addEventListener("click", function () {
      if (state.trigger.disabled) {
        return;
      }
      var isOpen = state.wrap.classList.contains("is-open");
      if (isOpen) {
        closeAll();
        return;
      }
      closeAll(state);
      state.wrap.classList.add("is-open");
      state.trigger.setAttribute("aria-expanded", "true");
      attachMenuToBody(state);
    });

    state.trigger.addEventListener("keydown", function (event) {
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

  function initAll() {
    var selects = document.querySelectorAll("select");
    for (var i = 0; i < selects.length; i += 1) {
      initSelect(selects[i]);
    }
  }

  function bindSelect2Compat() {
    if (
      !global.jQuery ||
      !global.jQuery.fn ||
      typeof global.jQuery.fn.select2 !== "function" ||
      global.jQuery.fn.select2[SELECT2_PATCH_FLAG]
    ) {
      return;
    }

    var originalSelect2 = global.jQuery.fn.select2;
    var wrapped = function () {
      this.each(function () {
        destroySelect(this);
      });
      return originalSelect2.apply(this, arguments);
    };

    wrapped[SELECT2_PATCH_FLAG] = true;
    global.jQuery.fn.select2 = wrapped;
  }

  function watchDom() {
    if (!global.MutationObserver || global[OBSERVER_FLAG] || !document.body) {
      return;
    }

    var observer = new global.MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i += 1) {
        var mutation = mutations[i];

        if (mutation.type === "attributes" && mutation.target && mutation.target.tagName === "SELECT") {
          if (mutation.target.classList.contains("select2-hidden-accessible")) {
            destroySelect(mutation.target);
          } else {
            var attributeState = findStateBySelect(mutation.target);
            if (attributeState) {
              refreshState(attributeState);
            } else {
              initSelect(mutation.target);
            }
          }
          continue;
        }

        if (mutation.type !== "childList") {
          continue;
        }

        if (mutation.target && mutation.target.tagName === "SELECT") {
          var targetState = findStateBySelect(mutation.target);
          if (targetState) {
            refreshState(targetState);
          } else {
            initSelect(mutation.target);
          }
          continue;
        }

        for (var j = 0; j < mutation.addedNodes.length; j += 1) {
          var node = mutation.addedNodes[j];
          if (!node || node.nodeType !== 1) {
            continue;
          }

          if (node.tagName === "SELECT") {
            initSelect(node);
          }

          if (node.querySelectorAll) {
            var nestedSelects = node.querySelectorAll("select");
            for (var k = 0; k < nestedSelects.length; k += 1) {
              initSelect(nestedSelects[k]);
            }
          }
        }
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["class", "data-ui-select"]
    });

    global[OBSERVER_FLAG] = observer;
  }

  function exposeApi() {
    global.BioTernSelectDropdown = {
      getMode: function () {
        return getConfiguredMode();
      },
      setMode: function (mode, options) {
        var normalized = normalizeMode(mode);
        try {
          if (global.localStorage) {
            if (normalized === "custom") {
              global.localStorage.removeItem(MODE_STORAGE_KEY);
            } else {
              global.localStorage.setItem(MODE_STORAGE_KEY, normalized);
            }
          }
        } catch (err) {}

        applyModeAttribute(normalized);

        if (options && options.reload === false) {
          if (normalized === "compat") {
            destroyAll();
          } else {
            bindSelect2Compat();
            initAll();
          }
          return normalized;
        }

        global.location.reload();
        return normalized;
      },
      refresh: function () {
        if (getConfiguredMode() === "compat") {
          destroyAll();
          return;
        }
        bindSelect2Compat();
        for (var i = states.length - 1; i >= 0; i -= 1) {
          refreshState(states[i]);
        }
        initAll();
      }
    };
  }

  function bindGlobalClose() {
    document.addEventListener("click", function (event) {
      var target = event.target;
      if (
        !target ||
        (!target.closest(".biotern-select-wrap") &&
          !target.closest(".biotern-select-menu-portal"))
      ) {
        closeAll();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeAll();
      }
    });

    global.addEventListener("resize", function () {
      for (var i = 0; i < states.length; i += 1) {
        if (states[i] && states[i].wrap && states[i].wrap.classList.contains("is-open")) {
          positionMenu(states[i]);
        }
      }
    });

    global.addEventListener(
      "scroll",
      function () {
        for (var i = 0; i < states.length; i += 1) {
          if (states[i] && states[i].wrap && states[i].wrap.classList.contains("is-open")) {
            positionMenu(states[i]);
          }
        }
      },
      true
    );
  }

  function boot() {
    if (global[INIT_FLAG]) {
      return;
    }
    global[INIT_FLAG] = true;
    applyModeAttribute(getConfiguredMode());
    exposeApi();
    bindSelect2Compat();
    watchDom();
    bindGlobalClose();
    if (getConfiguredMode() === "compat") {
      return;
    }
    initAll();
  }

  if (
    global.BioTernRuntimeBoot &&
    typeof global.BioTernRuntimeBoot.boot === "function"
  ) {
    global.BioTernRuntimeBoot.boot({
      name: "custom select dropdown",
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
