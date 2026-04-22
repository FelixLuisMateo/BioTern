"use strict";

(function (window, document) {
  if (!window || !document) {
    return;
  }

  function run() {
    var menu = document.querySelector(".nxl-notifications-menu[data-notification-feed-url]");
    if (!menu || menu.dataset.notificationRuntimeReady === "1") {
      return;
    }
    menu.dataset.notificationRuntimeReady = "1";

    var feedUrl = (menu.getAttribute("data-notification-feed-url") || "").trim();
    if (!feedUrl || !window.fetch) {
      return;
    }

    var notificationsUrl = (menu.getAttribute("data-notifications-url") || "notifications.php").trim() || "notifications.php";
    var currentUserId = parsePositiveInt(menu.getAttribute("data-current-user-id"));
    var browserIcon = (menu.getAttribute("data-notification-browser-icon") || "").trim();
    var browserBadge = (menu.getAttribute("data-notification-browser-badge") || "").trim();
    var serviceWorkerUrl = (menu.getAttribute("data-notification-service-worker-url") || "").trim();
    var dropdown = menu.closest(".dropdown");
    var bellLink = dropdown ? dropdown.querySelector(".nxl-head-link") : null;
    var list = menu.querySelector(".header-notifications-list");
    var emptyState = menu.querySelector(".header-notifications-empty");
    var removeAllButton = menu.querySelector("[data-notification-remove-all]");
    var permissionButton = menu.querySelector("[data-notification-browser-enable]");
    var permissionLabel = permissionButton ? permissionButton.querySelector("span") : null;
    var unreadPill = menu.querySelector(".header-notifications-unread-pill");
    var profileUnreadPill = document.querySelector(".header-profile-notifications-pill");
    var permissionSupported = "Notification" in window;
    var storageKey = currentUserId > 0 ? "biotern:notifications:last-notified:" + currentUserId : "";
    var deliveredId = Math.max(readLatestNotificationId(menu), readStoredDeliveredId(storageKey));
    var lastFeedSignature = "";
    var pollTimer = 0;
    var pollInFlight = false;

    registerServiceWorker(serviceWorkerUrl);
    syncPermissionButton();
    renderUnreadBadges(parsePositiveInt((bellLink && bellLink.querySelector(".nxl-h-badge") && bellLink.querySelector(".nxl-h-badge").textContent) || "0"));
    updateRemoveAllState();

    fetchFeed({ announce: false });
    scheduleNextPoll();

    if (permissionButton) {
      permissionButton.addEventListener("click", function () {
        if (!permissionSupported || !window.Notification || typeof window.Notification.requestPermission !== "function") {
          syncPermissionButton();
          return;
        }

        window.Notification.requestPermission().then(function () {
          syncPermissionButton();
        }).catch(function () {
          syncPermissionButton();
        });
      });
    }

    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "visible") {
        fetchFeed({ announce: false });
      }
      scheduleNextPoll();
    });

    window.addEventListener("focus", function () {
      fetchFeed({ announce: false });
    });

    if (storageKey) {
      window.addEventListener("storage", function (event) {
        if (event.key !== storageKey) {
          return;
        }
        deliveredId = Math.max(deliveredId, parsePositiveInt(event.newValue));
      });
    }

    function parsePositiveInt(value) {
      var parsed = parseInt(String(value || ""), 10);
      return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function escapeHtml(value) {
      return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function notificationVariant(type) {
      var normalized = String(type || "").toLowerCase();
      if (normalized === "attendance") {
        return "success";
      }
      if (normalized === "account") {
        return "warning";
      }
      if (normalized === "error" || normalized === "danger") {
        return "error";
      }
      return "info";
    }

    function readLatestNotificationId(root) {
      var rows = (root || document).querySelectorAll(".header-notification-row[data-notification-id]");
      var latest = 0;
      rows.forEach(function (row) {
        latest = Math.max(latest, parsePositiveInt(row.getAttribute("data-notification-id")));
      });
      return latest;
    }

    function readStoredDeliveredId(key) {
      if (!key || !window.localStorage) {
        return 0;
      }
      try {
        return parsePositiveInt(window.localStorage.getItem(key));
      } catch (error) {
        return 0;
      }
    }

    function persistDeliveredId(id) {
      if (!storageKey || !window.localStorage || id <= 0) {
        return;
      }
      try {
        window.localStorage.setItem(storageKey, String(id));
      } catch (error) {
        return;
      }
    }

    function registerServiceWorker(url) {
      if (!url || !("serviceWorker" in navigator)) {
        return;
      }
      navigator.serviceWorker.register(url).catch(function () {
        return;
      });
    }

    function syncPermissionButton() {
      if (!permissionButton) {
        return;
      }

      permissionButton.classList.remove("is-enabled", "is-blocked", "is-unsupported");
      permissionButton.disabled = false;

      if (!permissionSupported) {
        permissionButton.classList.add("is-unsupported");
        permissionButton.disabled = true;
        permissionButton.title = "Browser alerts are not supported here.";
        if (permissionLabel) {
          permissionLabel.textContent = "Alerts Unsupported";
        }
        return;
      }

      var permission = window.Notification.permission;
      if (permission === "granted") {
        permissionButton.classList.add("is-enabled");
        permissionButton.title = "Browser alerts are enabled.";
        if (permissionLabel) {
          permissionLabel.textContent = "Alerts On";
        }
        return;
      }

      if (permission === "denied") {
        permissionButton.classList.add("is-blocked");
        permissionButton.disabled = true;
        permissionButton.title = "Browser alerts are blocked in this browser.";
        if (permissionLabel) {
          permissionLabel.textContent = "Alerts Blocked";
        }
        return;
      }

      permissionButton.title = "Enable browser alerts for new notifications.";
      if (permissionLabel) {
        permissionLabel.textContent = "Enable Alerts";
      }
    }

    function buildSignature(items, unreadCount) {
      var rows = Array.isArray(items) ? items : [];
      return rows.map(function (item) {
        return [
          item.id || 0,
          item.is_read || 0,
          item.title || "",
          item.message || "",
          item.created_at || ""
        ].join(":");
      }).join("|") + "|" + String(unreadCount || 0);
    }

    function renderUnreadBadges(unreadCount) {
      var count = parsePositiveInt(unreadCount);

      if (unreadPill) {
        unreadPill.textContent = count + " unread";
      }

      if (bellLink) {
        var bellBadge = bellLink.querySelector(".nxl-h-badge");
        if (count > 0) {
          if (!bellBadge) {
            bellBadge = document.createElement("span");
            bellBadge.className = "badge bg-danger nxl-h-badge";
            bellLink.appendChild(bellBadge);
          }
          bellBadge.textContent = String(count);
        } else if (bellBadge && bellBadge.parentNode) {
          bellBadge.parentNode.removeChild(bellBadge);
        }
      }

      if (profileUnreadPill) {
        profileUnreadPill.className = "badge header-profile-notifications-pill " + (count > 0 ? "bg-soft-warning text-warning" : "bg-soft-secondary text-secondary");
        profileUnreadPill.textContent = count > 0 ? (count + " unread") : "All read";
      }
    }

    function renderNotifications(items, unreadCount) {
      var rows = Array.isArray(items) ? items : [];

      if (!list) {
        list = document.createElement("div");
        list.className = "header-notifications-list";
        if (emptyState && emptyState.parentNode) {
          emptyState.parentNode.insertBefore(list, emptyState);
        } else {
          menu.appendChild(list);
        }
      }

      if (!rows.length) {
        list.innerHTML = "";
        list.classList.add("d-none");
        if (emptyState) {
          emptyState.classList.remove("d-none");
        }
      } else {
        list.innerHTML = rows.map(function (item) {
          var notificationId = parsePositiveInt(item.id);
          var isUnread = parsePositiveInt(item.is_read) === 0;
          var removeLink = isUnread ? "" : (
            '<a href="#" class="header-notification-remove-link" data-notification-remove-one title="Remove notification" aria-label="Remove notification">' +
              '<i class="feather-trash-2"></i>' +
            "</a>"
          );

          return (
            '<div class="header-notification-row' + (isUnread ? " unread" : " read") + '" data-notification-id="' + notificationId + '" data-notification-read="' + (isUnread ? "0" : "1") + '">' +
              '<a href="' + escapeHtml(item.open_url || notificationsUrl) + '" class="notifications-item header-notification-item' + (isUnread ? " unread" : "") + '">' +
                '<div class="header-notification-badge">' +
                  '<i class="' + escapeHtml(item.icon || "feather-bell") + '"></i>' +
                "</div>" +
                '<div class="notifications-desc header-notification-desc">' +
                  '<div class="header-notification-meta-row">' +
                    '<div class="header-notification-title">' + escapeHtml(item.title || "Notification") + "</div>" +
                    '<div class="header-notification-time" title="' + escapeHtml(item.created_at || "") + '">' + escapeHtml(item.time_ago || "Just now") + "</div>" +
                  "</div>" +
                  '<div class="header-notification-type">' + escapeHtml(item.type_label || "System") + "</div>" +
                  '<div class="header-notification-message">' + escapeHtml(item.message || "") + "</div>" +
                "</div>" +
              "</a>" +
              removeLink +
            "</div>"
          );
        }).join("");
        list.classList.remove("d-none");
        if (emptyState) {
          emptyState.classList.add("d-none");
        }
      }

      renderUnreadBadges(unreadCount);
      updateRemoveAllState();
    }

    function updateRemoveAllState() {
      if (!removeAllButton) {
        return;
      }

      var hasReadRows = !!menu.querySelector(".header-notification-row.read");
      removeAllButton.classList.toggle("is-disabled", !hasReadRows);
      removeAllButton.disabled = !hasReadRows;
      removeAllButton.setAttribute("aria-disabled", hasReadRows ? "false" : "true");
    }

    function ensureToastStack() {
      var stack = document.getElementById("bioternLiveToastStack");
      if (stack) {
        return stack;
      }

      stack = document.createElement("div");
      stack.id = "bioternLiveToastStack";
      stack.className = "biotern-live-toast-stack";
      document.body.appendChild(stack);
      return stack;
    }

    function showInAppToast(notification) {
      var stack = ensureToastStack();
      var toast = document.createElement("div");
      var variant = notificationVariant(notification.type);
      toast.className = "app-theme-toast-static biotern-live-toast app-theme-toast-static--" + variant;
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");
      toast.innerHTML =
        '<span class="app-theme-toast-static-icon">' +
          '<span class="app-theme-toast-static-icon-glyph"><i class="' + escapeHtml(notification.icon || "feather-bell") + '"></i></span>' +
        "</span>" +
        '<span class="biotern-live-toast-body">' +
          '<span class="biotern-live-toast-title">' + escapeHtml(notification.title || "Notification") + "</span>" +
          '<span class="biotern-live-toast-message">' + escapeHtml(notification.message || "") + "</span>" +
          '<span class="biotern-live-toast-meta">' + escapeHtml(notification.type_label || "System") + " - " + escapeHtml(notification.time_ago || "Just now") + "</span>" +
        "</span>" +
        '<button type="button" class="biotern-live-toast-dismiss" aria-label="Dismiss notification">&times;</button>';

      var closed = false;
      function closeToast() {
        if (closed) {
          return;
        }
        closed = true;
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }

      toast.addEventListener("click", function (event) {
        if (event.target && event.target.closest(".biotern-live-toast-dismiss")) {
          event.preventDefault();
          event.stopPropagation();
          closeToast();
          return;
        }

        if (notification.open_url) {
          window.location.href = notification.open_url;
        }
      });

      stack.appendChild(toast);
      window.setTimeout(closeToast, 6000);
    }

    function spawnWindowNotification(title, options) {
      try {
        var instance = new window.Notification(title, options || {});
        instance.onclick = function () {
          window.focus();
          if (options && options.data && options.data.url) {
            window.location.href = options.data.url;
          }
          instance.close();
        };
      } catch (error) {
        return;
      }
    }

    function notifyBrowser(notification) {
      if (!permissionSupported || !window.Notification || window.Notification.permission !== "granted") {
        return;
      }

      var title = String(notification.title || "BioTern");
      var options = {
        body: String(notification.message || ""),
        tag: "biotern-notification-" + String(notification.id || ""),
        icon: browserIcon || undefined,
        badge: browserBadge || browserIcon || undefined,
        data: {
          url: notification.open_url || notificationsUrl
        }
      };

      if ("serviceWorker" in navigator) {
        navigator.serviceWorker.getRegistration().then(function (registration) {
          if (registration && typeof registration.showNotification === "function") {
            registration.showNotification(title, options);
            return;
          }

          spawnWindowNotification(title, options);
        }).catch(function () {
          spawnWindowNotification(title, options);
        });
        return;
      }

      spawnWindowNotification(title, options);
    }

    function announceNotifications(items) {
      var rows = Array.isArray(items) ? items : [];
      if (!rows.length) {
        return;
      }

      rows.slice().reverse().forEach(function (item) {
        if (document.visibilityState === "visible") {
          showInAppToast(item);
        } else {
          notifyBrowser(item);
        }
      });
    }

    function fetchFeed(options) {
      var opts = options || {};
      if (pollInFlight) {
        return;
      }
      pollInFlight = true;

      fetch(feedUrl + (feedUrl.indexOf("?") === -1 ? "?" : "&") + "limit=6", {
        method: "GET",
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest"
        },
        cache: "no-store"
      }).then(function (response) {
        return response.json();
      }).then(function (payload) {
        if (!payload || payload.ok !== true) {
          return;
        }

        var notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
        var unreadCount = parsePositiveInt(payload.unread_count);
        var latestId = parsePositiveInt(payload.latest_id);
        var feedSignature = buildSignature(notifications, unreadCount);
        var newItems = notifications.filter(function (item) {
          return parsePositiveInt(item.id) > deliveredId;
        });

        if (feedSignature !== lastFeedSignature) {
          renderNotifications(notifications, unreadCount);
          lastFeedSignature = feedSignature;
        } else {
          renderUnreadBadges(unreadCount);
        }

        if (opts.announce !== false && newItems.length) {
          announceNotifications(newItems);
        }

        if (latestId > deliveredId) {
          deliveredId = latestId;
          persistDeliveredId(deliveredId);
        }
      }).catch(function () {
        return;
      }).finally(function () {
        pollInFlight = false;
        scheduleNextPoll();
      });
    }

    function scheduleNextPoll() {
      if (pollTimer) {
        window.clearTimeout(pollTimer);
      }
      var delay = document.visibilityState === "visible" ? 20000 : 12000;
      pollTimer = window.setTimeout(function () {
        fetchFeed({ announce: true });
      }, delay);
    }
  }

  if (window.BioTernRuntimeBoot && typeof window.BioTernRuntimeBoot.boot === "function") {
    window.BioTernRuntimeBoot.boot({
      name: "header-notifications-runtime",
      run: run
    });
  } else if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }
})(window, document);
