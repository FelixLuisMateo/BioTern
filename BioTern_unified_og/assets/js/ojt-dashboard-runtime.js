/* OJT dashboard page runtime extracted from inline script */
(function () {
  "use strict";

  function initOjtDashboardRuntime() {
    var filterForm = document.getElementById("ojtFilterForm");
    var searchInput = document.getElementById("ojtFilterSearch");
    var submitTimer;
    var resizeTimer;

    function submitFilters() {
      if (filterForm) filterForm.submit();
    }

    function debounceSubmit() {
      clearTimeout(submitTimer);
      submitTimer = setTimeout(submitFilters, 350);
    }

    ["ojtFilterCourse", "ojtFilterSection", "ojtFilterStage", "ojtFilterRisk"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

    if (searchInput) searchInput.addEventListener("input", debounceSubmit);

    var printBtn = document.getElementById("ojtPrintBtn");
    if (printBtn) {
      printBtn.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
    }

    function applyOjtTableMode() {
      var table = document.getElementById("ojtListTable");
      if (!table) return;
      var wrap = table.closest(".table-responsive") || table.parentElement;
      if (!wrap) return;
      var body = document.body;
      if (!body) return;

      var hadStack = body.classList.contains("app-ojt-force-stack");
      if (hadStack) body.classList.remove("app-ojt-force-stack");

      var needsStack = table.scrollWidth > wrap.clientWidth + 2;
      body.classList.toggle("app-ojt-force-stack", needsStack);
    }

    function scheduleTableCheck() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        window.requestAnimationFrame(applyOjtTableMode);
      }, 120);
    }

    applyOjtTableMode();
    window.addEventListener("resize", scheduleTableCheck);
    window.addEventListener("orientationchange", scheduleTableCheck);
    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", scheduleTableCheck);
      window.visualViewport.addEventListener("scroll", scheduleTableCheck);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initOjtDashboardRuntime);
  } else {
    initOjtDashboardRuntime();
  }
})();
