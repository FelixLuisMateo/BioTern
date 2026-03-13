/* Shared UI helpers used across pages */
(function () {
  "use strict";

  var imageErrorHandlerBound = false;

  function safeStorageGet(keys) {
    try {
      for (var i = 0; i < keys.length; i += 1) {
        var value = localStorage.getItem(keys[i]);
        if (value) {
          return value;
        }
      }
    } catch (err) {
      return null;
    }
    return null;
  }

  function applyStoredSkinClass() {
    var skin = safeStorageGet(["app-skin", "app_skin", "theme"]);
    if (!skin) {
      return;
    }
    if (skin.indexOf("dark") !== -1) {
      document.documentElement.classList.add("app-skin-dark");
    }
  }

  function injectPersistentMaximizeStyle() {
    if (document.getElementById("app-maximize-persist-style")) {
      return;
    }
    var style = document.createElement("style");
    style.id = "app-maximize-persist-style";
    style.textContent =
      "html.app-maximize-persist .nxl-navigation{transform:none !important;pointer-events:auto !important;}" +
      "html.app-maximize-persist .full-screen-switcher .maximize{display:none !important;}" +
      "html.app-maximize-persist .full-screen-switcher .minimize{display:inline-block !important;}" +
      "html:not(.app-maximize-persist) .full-screen-switcher .maximize{display:inline-block !important;}" +
      "html:not(.app-maximize-persist) .full-screen-switcher .minimize{display:none !important;}";
    document.head.appendChild(style);
  }

  function getPersistentMaximizePreference() {
    return getStorageItem("app-maximize-persist") === "1";
  }

  function setPersistentMaximizePreference(enabled) {
    setStorageItem("app-maximize-persist", enabled ? "1" : "0");
  }

  function applyPersistentMaximizeState(enabled) {
    injectPersistentMaximizeStyle();
    document.documentElement.classList.toggle("app-maximize-persist", !!enabled);
  }

  function syncFullscreenButtonsState() {
    var enabled = document.documentElement.classList.contains("app-maximize-persist");
    var toggles = document.querySelectorAll('.full-screen-switcher .nxl-head-link, [data-action="toggle-fullscreen"]');
    for (var i = 0; i < toggles.length; i += 1) {
      toggles[i].classList.toggle("is-maximized", enabled);
      toggles[i].setAttribute("aria-pressed", enabled ? "true" : "false");
      toggles[i].setAttribute("title", enabled ? "Exit maximize" : "Maximize");
    }
  }

  function togglePersistentMaximizeState() {
    var nextState = !document.documentElement.classList.contains("app-maximize-persist");
    setPersistentMaximizePreference(nextState);
    applyPersistentMaximizeState(nextState);
    syncFullscreenButtonsState();
    return nextState;
  }

  function showToast(container, html, timeoutMs) {
    if (!container) {
      return;
    }
    container.insertAdjacentHTML("beforeend", html);
    if (!timeoutMs || timeoutMs <= 0) {
      return;
    }
    window.setTimeout(function () {
      var last = container.lastElementChild;
      if (last && last.parentElement === container) {
        last.remove();
      }
    }, timeoutMs);
  }

  function applyCurrentYear() {
    var yearNodes = document.querySelectorAll(".app-current-year");
    if (!yearNodes.length) {
      return;
    }
    var year = String(new Date().getFullYear());
    for (var i = 0; i < yearNodes.length; i += 1) {
      yearNodes[i].textContent = year;
    }
  }

  function getStorageItem(key) {
    if (!key) {
      return null;
    }
    try {
      return localStorage.getItem(key);
    } catch (err) {
      return null;
    }
  }

  function setStorageItem(key, value) {
    if (!key) {
      return false;
    }
    try {
      localStorage.setItem(key, String(value));
      return true;
    } catch (err) {
      return false;
    }
  }

  function onImageLoadErrorHide() {
    if (imageErrorHandlerBound) {
      return;
    }
    imageErrorHandlerBound = true;
    document.addEventListener(
      "error",
      function (event) {
        var target = event.target;
        if (target && target.matches("img[data-hide-onerror='1']")) {
          target.style.display = "none";
        }
      },
      true
    );
  }

  function bindPrintButton(buttonId) {
    var button = document.getElementById(buttonId);
    if (!button) {
      return;
    }
    button.addEventListener("click", function (event) {
      if (event && typeof event.preventDefault === "function") {
        event.preventDefault();
      }
      window.print();
    });
  }

  function bindCloseButton(buttonId, fallbackHref, options) {
    var button = document.getElementById(buttonId);
    if (!button) {
      return;
    }
    var delayMs =
      options && typeof options.delayMs === "number" && options.delayMs >= 0
        ? options.delayMs
        : 0;

    button.addEventListener("click", function (event) {
      if (event && typeof event.preventDefault === "function") {
        event.preventDefault();
      }

      if (window.opener && !window.opener.closed) {
        window.close();
        return;
      }

      try {
        window.close();
      } catch (err) {}

      window.setTimeout(function () {
        if (window.closed) {
          return;
        }
        if (window.history.length > 1) {
          window.history.back();
          return;
        }
        if (fallbackHref) {
          window.location.href = fallbackHref;
        }
      }, delayMs);
    });
  }

  function loadSavedTemplateHtml(storageKey, containerId, options) {
    var html = getStorageItem(storageKey);
    if (!html) {
      return false;
    }

    var container = document.getElementById(containerId);
    if (!container) {
      return false;
    }

    if (options && options.parseContentSelector) {
      var temp = document.createElement("div");
      temp.innerHTML = html;
      var extracted = temp.querySelector(options.parseContentSelector);
      container.innerHTML = extracted ? extracted.innerHTML : temp.innerHTML;
    } else {
      container.innerHTML = html;
    }

    return true;
  }

  function initMoaDocument(options) {
    var cfg = options || {};
    var useSavedTemplate =
      document.body && document.body.dataset ? document.body.dataset.useSavedTemplate === "1" : false;
    var doc = document.getElementById(cfg.contentId || "moa_doc_content");

    function stripLegacyColorStyles(container) {
      if (!container) {
        return;
      }
      var withStyle = container.querySelectorAll("[style]");
      withStyle.forEach(function (el) {
        var style = el.getAttribute("style") || "";
        style = style.replace(/(^|;)\s*color\s*:[^;]+;?/gi, "$1");
        style = style.replace(/(^|;)\s*text-decoration-color\s*:[^;]+;?/gi, "$1");
        style = style.replace(/;;+/g, ";").trim();
        if (style === "" || style === ";") {
          el.removeAttribute("style");
          return;
        }
        el.setAttribute("style", style);
      });

      var fontNodes = container.querySelectorAll("font[color]");
      fontNodes.forEach(function (el) {
        el.removeAttribute("color");
      });
    }

    if (useSavedTemplate && doc && cfg.storageKey) {
      var loaded = loadSavedTemplateHtml(cfg.storageKey, cfg.contentId || "moa_doc_content");
      if (!loaded) {
        var saved = getStorageItem(cfg.storageKey);
        if (saved) {
          doc.innerHTML = saved;
        }
      }
    }

    if (doc && cfg.normalizeColors) {
      stripLegacyColorStyles(doc);
    }

    bindPrintButton(cfg.printButtonId || "btn_print_moa");
    bindCloseButton(cfg.closeButtonId || "btn_close_moa", cfg.fallbackHref || "", {
      delayMs: typeof cfg.closeDelayMs === "number" ? cfg.closeDelayMs : 80,
    });
  }

  function revealOnLoad(selector, className) {
    var nodes = selector ? document.querySelectorAll(selector) : [];
    if (!nodes.length) {
      return;
    }
    var revealClass = className || "app-is-revealed";
    window.requestAnimationFrame(function () {
      for (var i = 0; i < nodes.length; i += 1) {
        nodes[i].classList.add(revealClass);
      }
    });
  }

  function initCircleProgressBatch(items) {
    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.circleProgress !== "function") {
      return;
    }
    var configs = Array.isArray(items) ? items : [];
    for (var i = 0; i < configs.length; i += 1) {
      var entry = configs[i] || {};
      if (!entry.selector) {
        continue;
      }
      var options = {
        max: typeof entry.max === "number" ? entry.max : 100,
        value: typeof entry.value === "number" ? entry.value : 0,
      };
      if (entry.textFormat) {
        options.textFormat = entry.textFormat;
      }
      window.jQuery(entry.selector).circleProgress(options);
    }
  }

  function initApexAreaSparklineBatch(items) {
    if (typeof window.ApexCharts !== "function") {
      return;
    }
    var configs = Array.isArray(items) ? items : [];
    for (var i = 0; i < configs.length; i += 1) {
      var entry = configs[i] || {};
      if (!entry.selector) {
        continue;
      }
      var node = document.querySelector(entry.selector);
      if (!node) {
        continue;
      }
      var data = Array.isArray(entry.data) ? entry.data : [];
      var chart = new window.ApexCharts(node, {
        chart: {
          type: "area",
          height: typeof entry.height === "number" ? entry.height : 80,
          sparkline: { enabled: true },
        },
        series: [
          {
            name: entry.name || "Series",
            data: data,
          },
        ],
        stroke: {
          width: typeof entry.strokeWidth === "number" ? entry.strokeWidth : 1,
          curve: entry.curve || "smooth",
        },
        fill: {
          opacity: [0.85, 0.25, 1, 1],
          gradient: {
            inverseColors: false,
            shade: "light",
            type: "vertical",
            opacityFrom: 0.5,
            opacityTo: 0.1,
            stops: [0, 100, 100, 100],
          },
        },
        yaxis: {
          min: typeof entry.yMin === "number" ? entry.yMin : 0,
        },
        colors: [entry.color || "#3454d1"],
      });
      chart.render();
    }
  }

  function createTemplateEditor(options) {
    var cfg = options || {};
    var editor = document.getElementById(cfg.editorId || "editor");
    if (!editor) {
      return null;
    }

    var msg = document.getElementById(cfg.statusId || "msg");
    var saveTimer = null;
    var savedRange = null;

    function getApi() {
      return {
        init: init,
        load: load,
        save: save,
        saveDebounced: saveDebounced,
        format: format,
        applyFontSizePt: applyFontSizePt,
        restoreSelection: restoreSelection,
        setStatus: setStatus,
        getEditor: function () {
          return editor;
        },
        getDefaultTemplateHtml: getDefaultTemplateHtml,
      };
    }

    function setStatus(text) {
      if (msg) {
        msg.textContent = text || "";
      }
    }

    function save() {
      if (!cfg.storageKey) {
        return false;
      }
      var storageApi = window.AppCore && window.AppCore.Storage ? window.AppCore.Storage : null;
      var ok = storageApi ? storageApi.set(cfg.storageKey, editor.innerHTML) : false;
      if (!ok) {
        try {
          localStorage.setItem(cfg.storageKey, editor.innerHTML);
          ok = true;
        } catch (err) {
          ok = false;
        }
      }
      setStatus(ok ? cfg.savedMessage || "Saved" : cfg.saveFailedMessage || "Save failed");
      if (typeof cfg.onAfterSave === "function") {
        cfg.onAfterSave(editor, getApi(), ok);
      }
      return ok;
    }

    function saveDebounced() {
      setStatus(cfg.unsavedMessage || "Unsaved changes");
      if (saveTimer) {
        clearTimeout(saveTimer);
      }
      saveTimer = setTimeout(save, cfg.saveDelayMs || 600);
    }

    function saveSelection() {
      var sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) {
        return;
      }
      var range = sel.getRangeAt(0);
      if (!editor.contains(range.commonAncestorContainer)) {
        return;
      }
      savedRange = range.cloneRange();
    }

    function restoreSelection() {
      if (!savedRange) {
        return;
      }
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }

    function format(cmd, value) {
      var keepRange = cfg.preserveSelectionOnFormat && savedRange ? savedRange.cloneRange() : null;
      restoreSelection();
      editor.focus();
      document.execCommand("styleWithCSS", false, true);
      document.execCommand(cmd, false, value || null);
      if (keepRange) {
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(keepRange);
        savedRange = keepRange.cloneRange();
      }
      saveDebounced();
    }

    function applyFontSizePt(ptValue) {
      var pt = parseFloat(ptValue);
      if (!Number.isFinite(pt) || pt < 6 || pt > 96) {
        return;
      }
      restoreSelection();
      var sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) {
        return;
      }
      var range = sel.getRangeAt(0);
      if (!editor.contains(range.commonAncestorContainer)) {
        return;
      }

      if (cfg.fontSizeMode === "rich-span") {
        if (range.collapsed) {
          var collapsedWrapper = document.createElement("span");
          collapsedWrapper.setAttribute("data-font-size", "1");
          collapsedWrapper.style.fontSize = pt + "pt";
          collapsedWrapper.appendChild(document.createTextNode("\u200b"));
          range.insertNode(collapsedWrapper);
          range.setStart(collapsedWrapper.firstChild, 1);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
          savedRange = range.cloneRange();
          saveDebounced();
          return;
        }

        var startEl =
          range.startContainer.nodeType === 1
            ? range.startContainer
            : range.startContainer.parentElement;
        var endEl =
          range.endContainer.nodeType === 1 ? range.endContainer : range.endContainer.parentElement;
        var startSized =
          startEl && startEl.closest ? startEl.closest('span[data-font-size="1"]') : null;
        var endSized = endEl && endEl.closest ? endEl.closest('span[data-font-size="1"]') : null;
        if (startSized && endSized && startSized === endSized) {
          startSized.style.fontSize = pt + "pt";
          startSized.style.removeProperty("line-height");
          var singleRange = document.createRange();
          singleRange.selectNodeContents(startSized);
          sel.removeAllRanges();
          sel.addRange(singleRange);
          savedRange = singleRange.cloneRange();
          saveDebounced();
          return;
        }

        var intersecting = Array.from(editor.querySelectorAll('span[data-font-size="1"]')).filter(
          function (node) {
            try {
              return range.intersectsNode(node);
            } catch (e) {
              return false;
            }
          }
        );
        if (intersecting.length) {
          intersecting.forEach(function (node) {
            node.style.fontSize = pt + "pt";
            node.style.removeProperty("line-height");
          });
          var interRange = document.createRange();
          interRange.setStartBefore(intersecting[0]);
          interRange.setEndAfter(intersecting[intersecting.length - 1]);
          sel.removeAllRanges();
          sel.addRange(interRange);
          savedRange = interRange.cloneRange();
          saveDebounced();
          return;
        }
      }

      if (range.collapsed) {
        return;
      }

      var wrapper = document.createElement("span");
      if (cfg.fontSizeMode === "rich-span") {
        wrapper.setAttribute("data-font-size", "1");
      }
      wrapper.style.fontSize = pt + "pt";
      try {
        range.surroundContents(wrapper);
      } catch (err) {
        var frag = range.extractContents();
        wrapper.appendChild(frag);
        range.insertNode(wrapper);
      }

      var newRange = document.createRange();
      newRange.selectNodeContents(wrapper);
      sel.removeAllRanges();
      sel.addRange(newRange);
      savedRange = newRange.cloneRange();
      saveDebounced();
    }

    function getSavedHtml() {
      if (!cfg.storageKey) {
        return null;
      }
      var storageApi = window.AppCore && window.AppCore.Storage ? window.AppCore.Storage : null;
      var saved = storageApi ? storageApi.get(cfg.storageKey) : null;
      if (!saved) {
        try {
          saved = localStorage.getItem(cfg.storageKey);
        } catch (err) {
          saved = null;
        }
      }
      return saved && saved.trim() ? saved : null;
    }

    function getDefaultTemplateHtml() {
      var templateId = cfg.defaultTemplateId || "default_template";
      var templateEl = document.getElementById(templateId);
      if (!templateEl) {
        return cfg.defaultHtml || "";
      }
      return (templateEl.innerHTML || "").trim();
    }

    function fetchTemplateHtml() {
      if (!cfg.fetchUrl) {
        return Promise.resolve(cfg.fetchFallbackHtml || "<p>Unable to load template.</p>");
      }
      return fetch(cfg.fetchUrl, { credentials: "same-origin" })
        .then(function (resp) {
          return resp.text();
        })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, "text/html");
          if (cfg.fetchContentSelector) {
            var source = doc.querySelector(cfg.fetchContentSelector);
            if (source && source.innerHTML) {
              return source.innerHTML;
            }
          }
          return cfg.fetchFallbackHtml || "<p>Unable to load template.</p>";
        })
        .catch(function () {
          return cfg.fetchFallbackHtml || "<p>Unable to load template.</p>";
        });
    }

    function load() {
      var mode = cfg.loadMode || "storage-or-default";
      var saved = getSavedHtml();

      if (mode === "storage-or-fetch") {
        if (saved) {
          editor.innerHTML = saved;
          if (typeof cfg.onAfterLoad === "function") {
            cfg.onAfterLoad(editor, getApi());
          }
          return Promise.resolve();
        }
        return fetchTemplateHtml().then(function (html) {
          editor.innerHTML = html;
          if (typeof cfg.onAfterLoad === "function") {
            cfg.onAfterLoad(editor, getApi());
          }
        });
      }

      editor.innerHTML = saved || getDefaultTemplateHtml();
      if (typeof cfg.onAfterLoad === "function") {
        cfg.onAfterLoad(editor, getApi());
      }
      return Promise.resolve();
    }

    function bindFormattingControls() {
      var preventIds =
        cfg.preventMouseDownIds ||
        [
          "btn_bold",
          "btn_italic",
          "btn_underline",
          "btn_indent",
          "btn_outdent",
          "btn_left",
          "btn_center",
          "btn_right",
          "btn_justify",
          "btn_apply_size",
        ];
      for (var i = 0; i < preventIds.length; i += 1) {
        var preventEl = document.getElementById(preventIds[i]);
        if (preventEl) {
          preventEl.addEventListener("mousedown", function (event) {
            event.preventDefault();
          });
        }
      }

      var commandMap = [
        ["btn_bold", "bold"],
        ["btn_italic", "italic"],
        ["btn_underline", "underline"],
        ["btn_indent", "indent"],
        ["btn_outdent", "outdent"],
        ["btn_left", "justifyLeft"],
        ["btn_center", "justifyCenter"],
        ["btn_right", "justifyRight"],
        ["btn_justify", "justifyFull"],
      ];
      for (var j = 0; j < commandMap.length; j += 1) {
        var pair = commandMap[j];
        var commandEl = document.getElementById(pair[0]);
        if (!commandEl) {
          continue;
        }
        (function (cmd) {
          commandEl.addEventListener("click", function () {
            format(cmd);
          });
        })(pair[1]);
      }

      var colorInput = document.getElementById("font_color");
      if (colorInput) {
        colorInput.addEventListener("input", function (event) {
          format("foreColor", event.target.value);
        });
        colorInput.addEventListener("click", function () {
          restoreSelection();
        });
      }

      var sizeInput = document.getElementById("font_size_pt");
      var applySizeButton = document.getElementById("btn_apply_size");
      if (applySizeButton && sizeInput) {
        applySizeButton.addEventListener("click", function () {
          applyFontSizePt(sizeInput.value);
        });
      }
      if (sizeInput) {
        sizeInput.addEventListener("dblclick", function (event) {
          var typed = window.prompt(
            cfg.fontSizePrompt || "Enter font size in pt (6-96):",
            event.target.value || "12"
          );
          if (typed === null) {
            return;
          }
          event.target.value = typed;
          applyFontSizePt(typed);
        });
        sizeInput.addEventListener("keydown", function (event) {
          if (event.key === "Enter") {
            event.preventDefault();
            applyFontSizePt(event.target.value);
          }
        });
      }
    }

    function bindActionButtons() {
      var saveButton = document.getElementById(cfg.saveButtonId || "btn_save");
      if (saveButton) {
        saveButton.addEventListener("click", save);
      }

      var resetButton = document.getElementById(cfg.resetButtonId || "btn_reset");
      if (resetButton) {
        resetButton.addEventListener("click", function () {
          if (cfg.resetConfirmMessage && !window.confirm(cfg.resetConfirmMessage)) {
            return;
          }

          if (typeof cfg.onReset === "function") {
            cfg.onReset(editor, getApi());
            return;
          }

          if (cfg.resetMode === "storage-or-default") {
            if (cfg.storageKey) {
              try {
                localStorage.removeItem(cfg.storageKey);
              } catch (err) {}
            }
            editor.innerHTML = getDefaultTemplateHtml();
            setStatus(cfg.resetStatusMessage || "Reset to default");
            return;
          }

          load().then(function () {
            save();
          });
        });
      }

      var backButton = document.getElementById(cfg.backButtonId || "btn_back");
      if (backButton && cfg.backHref) {
        backButton.addEventListener("click", function () {
          window.location.href = cfg.backHref;
        });
      }
    }

    function bindEditorListeners() {
      editor.addEventListener("input", saveDebounced);
      editor.addEventListener("mouseup", saveSelection);
      editor.addEventListener("keyup", saveSelection);
      document.addEventListener("selectionchange", saveSelection);
      window.addEventListener("beforeunload", save);
    }

    function init() {
      if (cfg.hideBrokenImagesOnError) {
        onImageLoadErrorHide();
      }
      bindFormattingControls();
      bindActionButtons();
      bindEditorListeners();
      return load();
    }

    return {
      init: init,
      load: load,
      save: save,
      saveDebounced: saveDebounced,
      format: format,
      applyFontSizePt: applyFontSizePt,
      restoreSelection: restoreSelection,
      setStatus: setStatus,
      getEditor: function () {
        return editor;
      },
      getDefaultTemplateHtml: getDefaultTemplateHtml,
    };
  }

  function attachLogoDrag(editor, options) {
    if (!editor || editor.dataset.logoDragReady === "1") {
      return;
    }
    editor.dataset.logoDragReady = "1";

    var cfg = options || {};
    var dragging = false;
    var targetCrest = null;
    var targetContainer = null;
    var offsetX = 0;
    var offsetY = 0;

    function setMoveStatus() {
      if (typeof cfg.setStatus === "function") {
        cfg.setStatus(cfg.moveStatusText || "Move logo, then release to save");
      }
    }

    function onChanged() {
      if (typeof cfg.onChange === "function") {
        cfg.onChange();
      }
    }

    function getPoint(evt) {
      if (evt.touches && evt.touches[0]) {
        return { x: evt.touches[0].clientX, y: evt.touches[0].clientY };
      }
      if (evt.changedTouches && evt.changedTouches[0]) {
        return { x: evt.changedTouches[0].clientX, y: evt.changedTouches[0].clientY };
      }
      return { x: evt.clientX, y: evt.clientY };
    }

    function startDrag(crest, container, evt) {
      crest.setAttribute("draggable", "false");
      crest.setAttribute("contenteditable", "false");
      targetCrest = crest;
      targetContainer = container;
      var point = getPoint(evt);
      var crestRect = crest.getBoundingClientRect();
      offsetX = point.x - crestRect.left;
      offsetY = point.y - crestRect.top;
      dragging = true;
      crest.classList.add("dragging");
      document.body.style.userSelect = "none";
      evt.preventDefault();
    }

    function moveDrag(evt) {
      if (!dragging || !targetCrest || !targetContainer) {
        return;
      }
      var point = getPoint(evt);
      var rect = targetContainer.getBoundingClientRect();
      var left = point.x - rect.left - offsetX;
      var top = point.y - rect.top - offsetY;
      var maxLeft = Math.max(0, rect.width - targetCrest.offsetWidth);
      var maxTop = Math.max(0, rect.height - targetCrest.offsetHeight);
      left = Math.max(0, Math.min(left, maxLeft));
      top = Math.max(0, Math.min(top, maxTop));
      targetCrest.style.left = Math.round(left) + "px";
      targetCrest.style.top = Math.round(top) + "px";
      setMoveStatus();
      evt.preventDefault();
    }

    function endDrag() {
      if (!dragging) {
        return;
      }
      dragging = false;
      if (targetCrest) {
        targetCrest.classList.remove("dragging");
      }
      targetCrest = null;
      targetContainer = null;
      document.body.style.userSelect = "";
      onChanged();
    }

    function findCrest(target) {
      if (!target || !target.closest) {
        return null;
      }
      var crest = target.closest(".crest");
      if (!crest || !editor.contains(crest)) {
        return null;
      }
      return crest;
    }

    function onStart(evt) {
      var crest = findCrest(evt.target);
      if (!crest) {
        return;
      }
      var container = crest.closest(".container");
      if (!container) {
        return;
      }
      startDrag(crest, container, evt);
    }

    document.addEventListener("pointerdown", onStart, true);
    document.addEventListener("pointermove", moveDrag, true);
    document.addEventListener("pointerup", endDrag, true);
    document.addEventListener("mousedown", onStart, true);
    document.addEventListener("mousemove", moveDrag, true);
    document.addEventListener("mouseup", endDrag, true);
    document.addEventListener("touchstart", onStart, { passive: false, capture: true });
    document.addEventListener("touchmove", moveDrag, { passive: false, capture: true });
    document.addEventListener("touchend", endDrag, { passive: false, capture: true });
  }

  function toggleFullscreenSafe() {
    var root = document.documentElement;
    var isFull = !!(
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement ||
      document.msFullscreenElement
    );

    if (!isFull) {
      var request =
        root.requestFullscreen ||
        root.webkitRequestFullscreen ||
        root.mozRequestFullScreen ||
        root.msRequestFullscreen;
      if (typeof request === "function") {
        request.call(root);
      }
      return;
    }

    var exit =
      document.exitFullscreen ||
      document.webkitExitFullscreen ||
      document.mozCancelFullScreen ||
      document.msExitFullscreen;
    if (typeof exit === "function") {
      exit.call(document);
    }
  }

  function ensureJqueryFullscreenHelper() {
    if (!window.jQuery || !window.jQuery.fn) {
      return;
    }
    if (typeof window.jQuery.fn.fullScreenHelper === "function") {
      return;
    }

    window.jQuery.fn.fullScreenHelper = function (action) {
      if (action === "toggle" || typeof action === "undefined") {
        toggleFullscreenSafe();
      }
      return this;
    };
  }

  function initDelegatedUiHandlers() {
    ensureJqueryFullscreenHelper();
    applyPersistentMaximizeState(getPersistentMaximizePreference());
    syncFullscreenButtonsState();

    document.addEventListener(
      "click",
      function (event) {
        var toggle = event.target.closest(
          '[data-action="toggle-fullscreen"], .full-screen-switcher .nxl-head-link'
        );
        if (!toggle) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === "function") {
          event.stopImmediatePropagation();
        }

        var enabled = togglePersistentMaximizeState();
        var isFullscreenNow = !!(
          document.fullscreenElement ||
          document.webkitFullscreenElement ||
          document.mozFullScreenElement ||
          document.msFullscreenElement
        );

        if (enabled !== isFullscreenNow) {
          toggleFullscreenSafe();
        }
      },
      true
    );

    document.addEventListener("click", function (event) {
      var toggle = event.target.closest(
        '[data-action="toggle-fullscreen"], .full-screen-switcher .nxl-head-link'
      );
      if (toggle) {
        return;
      }

      var printBtn = event.target.closest('[data-action="print-page"]');
      if (printBtn) {
        event.preventDefault();
        window.print();
      }
    });

    document.addEventListener(
      "submit",
      function (event) {
        var form = event.target;
        if (!form || !form.matches("form[data-confirm-message]")) {
          return;
        }
        var msg = form.getAttribute("data-confirm-message") || "Proceed?";
        if (!window.confirm(msg)) {
          event.preventDefault();
        }
      },
      true
    );

    onImageLoadErrorHide();
  }

  applyStoredSkinClass();

  window.AppCore = window.AppCore || {};
  window.AppCore.applyStoredSkinClass = applyStoredSkinClass;
  window.AppCore.showToast = showToast;
  window.AppCore.applyCurrentYear = applyCurrentYear;
  window.AppCore.initDelegatedUiHandlers = initDelegatedUiHandlers;
  window.AppCore.Storage = {
    get: getStorageItem,
    set: setStorageItem,
  };
  window.AppCore.Documents = {
    bindPrintButton: bindPrintButton,
    bindCloseButton: bindCloseButton,
    hideBrokenImagesOnError: onImageLoadErrorHide,
    loadSavedTemplateHtml: loadSavedTemplateHtml,
    initMoaDocument: initMoaDocument,
  };
  window.AppCore.Animations = {
    revealOnLoad: revealOnLoad,
  };
  window.AppCore.Widgets = {
    initCircleProgressBatch: initCircleProgressBatch,
    initApexAreaSparklineBatch: initApexAreaSparklineBatch,
  };
  window.AppCore.TemplateEditor = {
    create: createTemplateEditor,
    attachLogoDrag: attachLogoDrag,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      applyCurrentYear();
      initDelegatedUiHandlers();
    });
  } else {
    applyCurrentYear();
    initDelegatedUiHandlers();
  }
})();
