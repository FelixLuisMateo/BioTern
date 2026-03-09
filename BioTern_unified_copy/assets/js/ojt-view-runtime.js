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
    var printFrame = null;
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

    function getPrintFrame() {
      if (printFrame) return printFrame;
      printFrame = document.createElement("iframe");
      printFrame.id = "batchPrintFrame";
      printFrame.style.position = "fixed";
      printFrame.style.width = "0";
      printFrame.style.height = "0";
      printFrame.style.border = "0";
      printFrame.style.opacity = "0";
      printFrame.style.pointerEvents = "none";
      document.body.appendChild(printFrame);
      return printFrame;
    }

    function runBatchPrint(checkedNodes) {
      if (!checkedNodes.length) {
        if (hint) hint.textContent = "Select at least one document to print.";
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
        if (hint) hint.textContent = "No printable document URL found.";
        return;
      }

      if (hint) {
        hint.textContent = "Preparing " + queue.length + " document(s) for printing...";
      }

      var frame = getPrintFrame();
      var index = 0;

      var printNext = function () {
        if (index >= queue.length) {
          if (hint) hint.textContent = "Done. Printed " + queue.length + " document(s).";
          return;
        }

        var url = queue[index];
        if (hint) hint.textContent = "Printing " + (index + 1) + " of " + queue.length + "...";

        frame.onload = function () {
          setTimeout(function () {
            try {
              frame.contentWindow.focus();
              frame.contentWindow.print();
            } catch (e) {
              // Ignore and continue with next document.
            }
            index += 1;
            setTimeout(printNext, 500);
          }, 450);
        };

        frame.src = url + (url.indexOf("?") >= 0 ? "&" : "?") + "batch_print=1";
      };

      printNext();
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
        if (!printOptions.length) {
          if (hint) hint.textContent = "No documents available to print.";
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
