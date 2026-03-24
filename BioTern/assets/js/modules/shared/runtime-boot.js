"use strict";

(function (global) {
  if (!global || global.BioTernRuntimeBoot) {
    return;
  }

  function onReady(callback) {
    if (typeof callback !== "function") {
      return;
    }

    var uiStateCore = global.BioTernUiStateCore || null;
    if (
      uiStateCore &&
      uiStateCore.dom &&
      typeof uiStateCore.dom.onReady === "function"
    ) {
      uiStateCore.dom.onReady(callback);
      return;
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
      return;
    }
    callback();
  }

  function safeRun(name, runner) {
    if (typeof runner !== "function") {
      return;
    }
    try {
      runner();
    } catch (err) {
      if (global.console && typeof global.console.warn === "function") {
        global.console.warn((name || "runtime") + " failed", err);
      }
    }
  }

  function boot(options) {
    var cfg = options || {};
    var label = typeof cfg.name === "string" ? cfg.name : "runtime";
    var run = typeof cfg.run === "function" ? cfg.run : null;
    var defer = cfg.deferUntilDomReady !== false;

    if (!run) {
      return;
    }

    if (!defer) {
      safeRun(label, run);
      return;
    }

    onReady(function () {
      safeRun(label, run);
    });
  }

  global.BioTernRuntimeBoot = {
    onReady: onReady,
    safeRun: safeRun,
    boot: boot,
  };
})(window);
