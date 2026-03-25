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

    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === "function") {
      var $filterForm = window.jQuery("#ojtFilterForm");
      ["#ojtFilterCourse", "#ojtFilterSection", "#ojtFilterStage"].forEach(function (selector) {
        if (window.jQuery(selector).length) {
          window.jQuery(selector).select2({
            width: "100%",
            allowClear: false,
            dropdownAutoWidth: false,
            minimumResultsForSearch: Infinity,
            dropdownParent: $filterForm,
          });
        }
      });
      ["#ojtFilterRisk"].forEach(function (selector) {
        if (window.jQuery(selector).length) {
          window.jQuery(selector).select2({
            width: "100%",
            allowClear: false,
            dropdownAutoWidth: false,
            dropdownParent: $filterForm,
          });
        }
      });
      ["#ojtFilterCourse", "#ojtFilterSection", "#ojtFilterStage", "#ojtFilterRisk"].forEach(function (selector) {
        if (window.jQuery(selector).length) {
          window.jQuery(selector).on("select2:select select2:clear", submitFilters);
        }
      });
    }

    var printBtn = document.getElementById("ojtPrintBtn");
    if (printBtn) {
      printBtn.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
    }

    if (document.body) {
      document.body.classList.remove("app-ojt-force-stack");
    }

    if (
      window.jQuery &&
      window.jQuery.fn &&
      typeof window.jQuery.fn.DataTable === "function" &&
      window.jQuery("#ojtListTable").length &&
      !window.jQuery.fn.DataTable.isDataTable("#ojtListTable")
    ) {
      window.jQuery("#ojtListTable").DataTable({
        pageLength: 10,
        lengthMenu: [
          [10, 25, 50, 100],
          [10, 25, 50, 100],
        ],
        order: [[0, "asc"]],
        columnDefs: [
          { orderable: false, targets: [7] },
        ],
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initOjtDashboardRuntime);
  } else {
    initOjtDashboardRuntime();
  }
})();
