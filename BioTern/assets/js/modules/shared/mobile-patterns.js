(function () {
  "use strict";

  function isMobileViewport() {
    return window.matchMedia("(max-width: 991.98px)").matches;
  }

  function addClass(el, name) {
    if (el && !el.classList.contains(name)) {
      el.classList.add(name);
    }
  }

  function toggleClass(el, name, enabled) {
    if (!el) {
      return;
    }
    if (enabled) {
      addClass(el, name);
    } else {
      el.classList.remove(name);
    }
  }

  function enhanceKpiRows(root) {
    root.querySelectorAll(".dashboard-shell .row, .dashboard-shell .g-3").forEach(function (row) {
      var directKpiChildren = Array.prototype.some.call(row.children || [], function (child) {
        return !!child.querySelector(".kpi-card, .kpi-tile");
      });
      var isKpiStripRow = !!row.closest(".kpi-strip, [data-move-key='kpi-strip']");

      if (directKpiChildren && isKpiStripRow) {
        addClass(row, "app-mobile-kpi-track");
      } else {
        row.classList.remove("app-mobile-kpi-track");
      }
    });
  }

  function enhanceFilterForms(root) {
    root.querySelectorAll(".filter-form").forEach(function (form) {
      addClass(form, "app-mobile-form-stack");
    });
  }

  function enhanceActionGroups(root) {
    root.querySelectorAll(".dashboard-actions-grid, .card-header-btn").forEach(function (group) {
      addClass(group, "app-mobile-action-dock");
    });
  }

  function enhanceDataWrappers(root) {
    root.querySelectorAll(".app-data-card, .app-mobile-inline-list-card, .app-students-table-card, .app-ojt-table-card").forEach(function (card) {
      var hasMobileList = !!card.querySelector(".app-mobile-list, .app-students-mobile-list, .app-ojt-mobile-list");
      toggleClass(card, "app-has-mobile-list", hasMobileList);
      addClass(card, "app-mobile-surface");
      var wrapper = card.querySelector(".dataTables_wrapper");
      if (wrapper) {
        addClass(wrapper, "app-mobile-inline-list");
      }
    });
  }

  function enhanceScrollableChips(root) {
    root.querySelectorAll(".page-header-quick, .app-ojt-risk-list, .app-students-status-list").forEach(function (list) {
      addClass(list, "app-mobile-chip-row");
    });
  }

  function enhanceMobilePatterns() {
    if (!document.body || !document.body.classList.contains("mobile-bottom-nav")) {
      return;
    }

    if (!isMobileViewport()) {
      return;
    }

    var root = document;
    enhanceKpiRows(root);
    enhanceFilterForms(root);
    enhanceActionGroups(root);
    enhanceDataWrappers(root);
    enhanceScrollableChips(root);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", enhanceMobilePatterns);
  } else {
    enhanceMobilePatterns();
  }

  var resizeTimer = 0;
  window.addEventListener("resize", function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(enhanceMobilePatterns, 120);
  });
})();
