/* Students page runtime extracted from inline script */
(function () {
  "use strict";

  function initStudentsFilters() {
    if (window.jQuery) {
      ["#filter-course", "#filter-department", "#filter-section"].forEach(function (selector) {
        if ($(selector).length) {
          $(selector).select2({
            width: "100%",
            allowClear: false,
            dropdownAutoWidth: false,
            minimumResultsForSearch: Infinity,
          });
        }
      });

      ["#filter-supervisor", "#filter-coordinator"].forEach(function (selector) {
        if ($(selector).length) {
          $(selector).select2({
            width: "100%",
            allowClear: false,
            dropdownAutoWidth: false,
          });
        }
      });
    }

    var filterForm = document.getElementById("studentsFilterForm");
    function submitFilters() {
      if (filterForm) filterForm.submit();
    }

    [
      "filter-date",
      "filter-course",
      "filter-department",
      "filter-section",
      "filter-supervisor",
      "filter-coordinator",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

    if (window.jQuery) {
      [
        "#filter-course",
        "#filter-department",
        "#filter-section",
        "#filter-supervisor",
        "#filter-coordinator",
      ].forEach(function (selector) {
        if ($(selector).length) {
          $(selector).on("select2:select select2:clear", submitFilters);
        }
      });
    }
  }

  function initPrintActions() {
    document.querySelectorAll(".js-print-page").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
    });
  }

  function initStudentsPageRuntime() {
    initStudentsFilters();
    initPrintActions();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudentsPageRuntime);
  } else {
    initStudentsPageRuntime();
  }
})();
