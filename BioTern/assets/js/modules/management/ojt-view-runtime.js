/* OJT view page runtime extracted from inline script */
(function () {
  "use strict";

  function initTabs() {
    var cfg = document.getElementById("ojt-view-runtime-config");
    var preferredTabId = (cfg && cfg.dataset.activeTab) || "";

    if (
      window.location.hash &&
      document.querySelector('button[data-bs-target="' + window.location.hash + '"]')
    ) {
      preferredTabId = window.location.hash.replace("#", "");
    }

    var targetBtn = document.querySelector(
      'button[data-bs-target="#' + preferredTabId + '"]'
    );

    if (targetBtn && window.bootstrap && window.bootstrap.Tab) {
      var tab = new window.bootstrap.Tab(targetBtn);
      tab.show();
    }

    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function (btn) {
      btn.addEventListener("shown.bs.tab", function (e) {
        var selector = e.target.getAttribute("data-bs-target");
        if (selector) {
          history.replaceState(null, "", selector);
        }
      });
    });
  }

  function initBatchPrint() {
    var printBtn = document.getElementById("printSelectedDocsBtn");
    var printAllBtn = document.getElementById("printAllDocsBtn");
    var toggleAllBtn = document.getElementById("toggleAllPrintDocs");
    var hint = document.getElementById("printDocsHint");
    var printOptions = Array.prototype.slice.call(
      document.querySelectorAll(".print-doc-option")
    );

    function syncPrintOptionState() {
      printOptions.forEach(function (option) {
        var state = option.querySelector(".print-doc-state");
        if (!state) return;
        state.textContent = option.classList.contains("is-checked")
          ? "Selected"
          : "Select";
      });
    }

    syncPrintOptionState();

    printOptions.forEach(function (option) {
      option.addEventListener("click", function () {
        option.classList.toggle("is-checked");
        syncPrintOptionState();
      });
    });

    function runBatchPrint(checkedNodes) {
      if (!checkedNodes.length) {
        if (hint) hint.textContent = "Select at least one document to open.";
        return;
      }

      var queue = checkedNodes
        .map(function (cb) {
          return cb.getAttribute("data-doc-url") || "";
        })
        .filter(function (u) {
          return !!u;
        });

      if (!queue.length) {
        if (hint) hint.textContent = "No document URL found.";
        return;
      }

      if (hint) {
        hint.textContent = "Opening " + queue.length + " document preview tab(s)...";
      }

      queue.forEach(function (url) {
        window.open(url, "_blank", "noopener");
      });

      if (hint) {
        hint.textContent = "Opened " + queue.length + " preview tab(s). Use the Print button inside each tab.";
      }
    }

    if (printBtn) {
      printBtn.addEventListener("click", function () {
        var selected = Array.prototype.slice.call(
          document.querySelectorAll(".print-doc-option.is-checked")
        );
        runBatchPrint(selected);
      });
    }

    if (toggleAllBtn) {
      toggleAllBtn.addEventListener("click", function () {
        if (!printOptions.length) return;
        var shouldSelectAll = printOptions.some(function (option) {
          return !option.classList.contains("is-checked");
        });
        printOptions.forEach(function (option) {
          option.classList.toggle("is-checked", shouldSelectAll);
        });
        toggleAllBtn.textContent = shouldSelectAll ? "Clear All" : "Select All";
        syncPrintOptionState();
      });
    }

    if (printAllBtn) {
      printAllBtn.addEventListener("click", function () {
        if (printAllBtn.tagName === "A") {
          return;
        }
        if (!printOptions.length) {
          if (hint) hint.textContent = "No documents available to open.";
          return;
        }
        printOptions.forEach(function (option) {
          option.classList.add("is-checked");
        });
        if (toggleAllBtn) toggleAllBtn.textContent = "Clear All";
        syncPrintOptionState();
        runBatchPrint(printOptions);
      });
    }
  }

  function initOjtViewRuntime() {
    initTabs();
    initBatchPrint();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initOjtViewRuntime);
  } else {
    initOjtViewRuntime();
  }
})();
