"use strict";

(function (window, document) {
  if (!window || !document || window.BioTernNotify) {
    return;
  }

  function normalizeType(type) {
    var t = String(type || "info").toLowerCase();
    if (t === "danger") return "error";
    if (t === "warn") return "warning";
    if (t === "ok") return "success";
    if (["success", "info", "warning", "error"].indexOf(t) === -1) {
      return "info";
    }
    return t;
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function iconClassForType(type) {
    var normalized = normalizeType(type);
    if (normalized === "success") return "feather-check-circle";
    if (normalized === "warning") return "feather-alert-triangle";
    if (normalized === "error") return "feather-x-circle";
    return "feather-info";
  }

  function ensureContainer() {
    var node = document.getElementById("bioternToastContainer");
    if (node) {
      return node;
    }
    node = document.createElement("div");
    node.id = "bioternToastContainer";
    node.className = "biotern-system-toast-stack";
    node.setAttribute("aria-live", "polite");
    node.setAttribute("aria-atomic", "true");
    document.body.appendChild(node);
    return node;
  }

  function closeToast(toast) {
    if (!toast || !toast.parentNode) {
      return;
    }
    toast.parentNode.removeChild(toast);
  }

  function notifyBrowser(title, message) {
    if (!("Notification" in window) || document.visibilityState === "visible") {
      return;
    }
    if (window.Notification.permission !== "granted") {
      return;
    }
    try {
      var n = new window.Notification(String(title || "BioTern"), {
        body: String(message || ""),
      });
      window.setTimeout(function () {
        try {
          n.close();
        } catch (e) {}
      }, 5000);
    } catch (err) {}
  }

  function show(options) {
    var payload = options || {};
    var message = String(payload.message || "").trim();
    if (!message) {
      return null;
    }

    var type = normalizeType(payload.type || payload.variant);
    var title = String(payload.title || "");
    var iconClass = String(payload.iconClass || iconClassForType(type));
    var duration = Number(payload.duration || payload.timeout || 5200);
    if (!Number.isFinite(duration) || duration < 1500) {
      duration = 5200;
    }

    var container = ensureContainer();
    var toast = document.createElement("div");
    toast.className = "app-theme-toast-static app-theme-toast-static--" + type + " biotern-system-toast";
    toast.setAttribute("role", "status");
    toast.setAttribute("aria-live", "polite");
    toast.innerHTML =
      '<span class="app-theme-toast-static-icon">' +
        '<span class="app-theme-toast-static-icon-glyph"><i class="' + escapeHtml(iconClass) + '"></i></span>' +
      "</span>" +
      '<span class="biotern-live-toast-body">' +
        (title ? '<span class="biotern-live-toast-title">' + escapeHtml(title) + "</span>" : "") +
        '<span class="biotern-live-toast-message">' + escapeHtml(message) + "</span>" +
      "</span>" +
      '<button type="button" class="biotern-live-toast-dismiss" aria-label="Dismiss notification">&times;</button>';

    toast.addEventListener("click", function (event) {
      var dismiss = event.target && event.target.closest(".biotern-live-toast-dismiss");
      if (dismiss) {
        event.preventDefault();
        event.stopPropagation();
      }
      closeToast(toast);
    });

    container.appendChild(toast);
    window.setTimeout(function () {
      closeToast(toast);
    }, duration);

    notifyBrowser(title || "BioTern", message);
    return toast;
  }

  function alertTypeFromClasses(node) {
    if (!node || !node.classList) {
      return "info";
    }
    if (node.classList.contains("alert-success")) return "success";
    if (node.classList.contains("alert-warning")) return "warning";
    if (node.classList.contains("alert-danger")) return "error";
    return "info";
  }

  function convertAlertNode(node) {
    if (!node || node.nodeType !== 1 || !node.classList || !node.classList.contains("alert")) {
      return;
    }
    if (node.dataset && node.dataset.notifyInline === "1") {
      return;
    }
    if (node.closest(".modal, .swal2-container, .dropdown-menu, .toast, .biotern-live-toast-stack")) {
      return;
    }
    if (
      node.classList.contains("d-none") ||
      node.hidden ||
      node.getAttribute("aria-hidden") === "true"
    ) {
      return;
    }

    var text = String(node.textContent || "").replace(/\s+/g, " ").trim();
    if (!text) {
      node.remove();
      return;
    }

    show({
      type: alertTypeFromClasses(node),
      message: text,
      title: node.getAttribute("data-toast-title") || "",
      duration: 6200,
    });
    node.remove();
  }

  function convertInlineAlerts(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var alerts = scope.querySelectorAll(".main-content .alert, .nxl-content > .alert, main .alert");
    for (var i = 0; i < alerts.length; i += 1) {
      convertAlertNode(alerts[i]);
    }
  }

  function bindMutationObserver() {
    if (!window.MutationObserver) {
      return;
    }
    var observer = new window.MutationObserver(function (records) {
      for (var i = 0; i < records.length; i += 1) {
        var record = records[i];
        if (!record.addedNodes || !record.addedNodes.length) {
          continue;
        }
        for (var j = 0; j < record.addedNodes.length; j += 1) {
          var node = record.addedNodes[j];
          if (!node || node.nodeType !== 1) {
            continue;
          }
          if (node.classList && node.classList.contains("alert")) {
            convertAlertNode(node);
            continue;
          }
          convertInlineAlerts(node);
        }
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  function requestPermission() {
    if (!("Notification" in window) || !window.Notification || typeof window.Notification.requestPermission !== "function") {
      return Promise.resolve("unsupported");
    }
    if (window.Notification.permission !== "default") {
      return Promise.resolve(window.Notification.permission);
    }
    return window.Notification.requestPermission();
  }

  function run() {
    ensureContainer();
    convertInlineAlerts(document);
    bindMutationObserver();
  }

  window.BioTernNotify = {
    show: show,
    success: function (message, options) {
      return show(Object.assign({}, options || {}, { type: "success", message: message }));
    },
    info: function (message, options) {
      return show(Object.assign({}, options || {}, { type: "info", message: message }));
    },
    warning: function (message, options) {
      return show(Object.assign({}, options || {}, { type: "warning", message: message }));
    },
    error: function (message, options) {
      return show(Object.assign({}, options || {}, { type: "error", message: message }));
    },
    convertInlineAlerts: convertInlineAlerts,
    requestPermission: requestPermission,
  };

  window.addEventListener("biotern:notify", function (event) {
    if (!event || !event.detail) {
      return;
    }
    show(event.detail);
  });

  if (window.BioTernRuntimeBoot && typeof window.BioTernRuntimeBoot.boot === "function") {
    window.BioTernRuntimeBoot.boot({
      name: "global-notifier-runtime",
      run: run,
    });
  } else if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }
})(window, document);
