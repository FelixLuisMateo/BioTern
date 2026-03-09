/* OJT dashboard page runtime extracted from inline script */
(function () {
  "use strict";

  function initOjtDashboardRuntime() {
    var filterForm = document.getElementById("ojtFilterForm");
    var searchInput = document.getElementById("ojtFilterSearch");
    var submitTimer;

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
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initOjtDashboardRuntime);
  } else {
    initOjtDashboardRuntime();
  }
})();
