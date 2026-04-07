/*
 * Shared page-header action menu scheme runtime.
 * Reads active theme classes and exposes lightweight body data attributes
 * that CSS can use for scheme-specific styling.
 */
(function (global) {
  "use strict";

  if (!global || global.BioTernPageHeaderActionsScheme) {
    return;
  }

  function resolveTone() {
    var html = document.documentElement;
    if (html.classList.contains("app-theme-gray")) {
      return "gray";
    }
    return "blue";
  }

  function resolveSkin() {
    var html = document.documentElement;
    return html.classList.contains("app-skin-dark") ? "dark" : "light";
  }

  function applySchemeState() {
    if (!document.body) {
      return;
    }
    document.body.setAttribute("data-page-header-action-tone", resolveTone());
    document.body.setAttribute("data-page-header-action-skin", resolveSkin());
  }

  function observeThemeClassChanges() {
    if (!("MutationObserver" in global)) {
      return;
    }
    var observer = new MutationObserver(function () {
      applySchemeState();
    });
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"],
    });
  }

  function boot() {
    applySchemeState();
    observeThemeClassChanges();
  }

  global.BioTernPageHeaderActionsScheme = {
    boot: boot,
    refresh: applySchemeState,
  };

  global.AppCore = global.AppCore || {};
  global.AppCore.PageHeaderActionsScheme = global.BioTernPageHeaderActionsScheme;

  if (global.BioTernRuntimeBoot && typeof global.BioTernRuntimeBoot.boot === "function") {
    global.BioTernRuntimeBoot.boot({
      name: "page-header-actions-scheme",
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
