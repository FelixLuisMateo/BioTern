/* Homepage-specific runtime extracted from inline scripts */
(function () {
  "use strict";

  function initOjtOverviewChart() {
    try {
      if (typeof ApexCharts === "undefined") {
        return;
      }

      var el = document.querySelector("#ojt-overview-pie");
      if (!el) {
        return;
      }

      var cfg = document.getElementById("homepage-runtime-config");
      var pending = Number((cfg && cfg.dataset.ojtPending) || 0);
      var ongoing = Number((cfg && cfg.dataset.ojtOngoing) || 0);
      var completed = Number((cfg && cfg.dataset.ojtCompleted) || 0);
      var cancelled = Number((cfg && cfg.dataset.ojtCancelled) || 0);

      var chart = new ApexCharts(el, {
        chart: { type: "donut", height: 260 },
        series: [pending, ongoing, completed, cancelled],
        labels: ["Pending", "Ongoing", "Completed", "Cancelled"],
        colors: ["#f6c23e", "#36b9cc", "#1cc88a", "#e74a3b"],
        legend: { position: "bottom" },
        responsive: [
          {
            breakpoint: 768,
            options: { chart: { height: 200 }, legend: { position: "bottom" } },
          },
        ],
      });

      chart.render();
    } catch (e) {
      console.error("OJT chart init error", e);
    }
  }

  function initSidebarMiniMenuCollapse() {
    function collapseSidebarMenus() {
      if (!document.documentElement.classList.contains("minimenu")) return;
      document
        .querySelectorAll(
          ".nxl-navigation .nxl-item.nxl-hasmenu.open, .nxl-navigation .nxl-item.nxl-hasmenu.nxl-trigger"
        )
        .forEach(function (item) {
          item.classList.remove("open", "nxl-trigger");
        });
    }

    function runAfterToggle() {
      collapseSidebarMenus();
      setTimeout(collapseSidebarMenus, 80);
      setTimeout(collapseSidebarMenus, 220);
    }

    collapseSidebarMenus();

    ["menu-mini-button", "menu-expend-button", "mobile-collapse"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", runAfterToggle);
    });

    var nav = document.querySelector(".nxl-navigation");
    if (window.MutationObserver && nav) {
      var observer = new MutationObserver(function () {
        if (document.documentElement.classList.contains("minimenu")) {
          collapseSidebarMenus();
        }
      });
      observer.observe(nav, {
        subtree: true,
        attributes: true,
        attributeFilter: ["class"],
      });
    }
  }

  function initHomepageRuntime() {
    initOjtOverviewChart();
    initSidebarMiniMenuCollapse();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHomepageRuntime);
  } else {
    initHomepageRuntime();
  }
})();
