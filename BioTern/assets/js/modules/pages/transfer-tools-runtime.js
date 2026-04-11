(function () {
  "use strict";

  function initStatusToast() {
    if (typeof window.bootstrap === "undefined" || !window.bootstrap.Toast) {
      return;
    }

    var toastEl = document.getElementById("importStatusToast");
    if (!toastEl) {
      return;
    }

    var toast = new window.bootstrap.Toast(toastEl);
    toast.show();
  }

  function initExcelSectionReview() {
    var reviewRoot = document.querySelector("[data-excel-review-root]");
    if (!reviewRoot) {
      return;
    }

    var rowTable = reviewRoot.querySelector("#excelImportRowsTable");
    var heading = reviewRoot.querySelector("#excelImportRowsHeading");
    var subheading = reviewRoot.querySelector("#excelImportRowsSubheading");
    var emptyState = reviewRoot.querySelector("#excelImportEmptyState");
    if (!rowTable || !heading || !subheading || !emptyState) {
      return;
    }

    var selectedSection = reviewRoot.getAttribute("data-selected-section") || "";
    var rows = Array.prototype.slice.call(rowTable.querySelectorAll("tbody tr"));
    var controls = Array.prototype.slice.call(
      reviewRoot.querySelectorAll("[data-excel-section-control]")
    );

    function setActive(section) {
      controls.forEach(function (control) {
        var controlSection = control.getAttribute("data-section") || "";
        control.classList.toggle("active", controlSection === section);
      });
    }

    function render(section) {
      var visibleCount = 0;

      rows.forEach(function (row) {
        var rowSection = row.getAttribute("data-section") || "";
        var isMatch = section === "" || rowSection === section;
        row.style.display = isMatch ? "" : "none";
        if (isMatch) {
          visibleCount += 1;
        }
      });

      setActive(section);

      if (section === "") {
        heading.textContent = "All imported rows";
        subheading.textContent =
          "Showing the full imported student list for this school year.";
      } else {
        heading.textContent = "Students in " + section;
        subheading.textContent = "Showing the full student list for the selected section.";
      }

      emptyState.style.display = visibleCount === 0 ? "block" : "none";
    }

    controls.forEach(function (control) {
      control.addEventListener("click", function () {
        selectedSection = control.getAttribute("data-section") || "";
        render(selectedSection);
      });
    });

    render(selectedSection);
  }

  function initTransferTools() {
    initStatusToast();
    initExcelSectionReview();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTransferTools);
  } else {
    initTransferTools();
  }
})();
