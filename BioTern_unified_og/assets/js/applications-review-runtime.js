(function () {
  "use strict";

  function initAlertAutoDismiss() {
    var alertEl = document.querySelector(".alert-auto-dismiss");
    if (!alertEl) return;

    setTimeout(function () {
      alertEl.classList.add("is-hiding");
      setTimeout(function () {
        if (alertEl && alertEl.parentNode) {
          alertEl.parentNode.removeChild(alertEl);
        }
      }, 400);
    }, 3500);
  }

  function initToggleButtons() {
    document.querySelectorAll(".application-toggle-btn").forEach(function (button) {
      var targetSelector = button.getAttribute("data-bs-target");
      if (!targetSelector) return;
      var collapseTarget = document.querySelector(targetSelector);
      if (!collapseTarget) return;

      var expandText = button.getAttribute("data-expand-text") || "Show Details";
      var collapseText = button.getAttribute("data-collapse-text") || "Hide Details";
      button.textContent = collapseTarget.classList.contains("show") ? collapseText : expandText;

      collapseTarget.addEventListener("show.bs.collapse", function () {
        button.textContent = collapseText;
        button.setAttribute("aria-expanded", "true");
      });

      collapseTarget.addEventListener("hide.bs.collapse", function () {
        button.textContent = expandText;
        button.setAttribute("aria-expanded", "false");
      });
    });
  }

  function initApplicationsReview() {
    initAlertAutoDismiss();
    initToggleButtons();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initApplicationsReview);
  } else {
    initApplicationsReview();
  }
})();
