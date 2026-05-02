(function () {
  "use strict";

  var table = document.querySelector("[data-required-report-table]");
  if (!table) {
    return;
  }

  var searchInput = document.querySelector("[data-required-report-search]");
  var filterInputs = Array.prototype.slice.call(document.querySelectorAll("[data-required-report-filter]"));
  var resetButton = document.querySelector("[data-required-report-reset]");
  var exportButtons = Array.prototype.slice.call(document.querySelectorAll("[data-required-report-export]"));
  var countEl = document.querySelector("[data-required-report-count]");
  var rows = Array.prototype.slice.call(table.querySelectorAll("[data-required-report-row]"));
  var emptyRow = table.querySelector("[data-required-report-empty]");
  var pageSummary = document.querySelector("[data-required-report-page-summary]");
  var pageJump = document.querySelector("[data-required-report-page-jump]");
  var prevButton = document.querySelector("[data-required-report-prev]");
  var nextButton = document.querySelector("[data-required-report-next]");
  var pageSize = 10;
  var currentPage = 1;
  var matchedRows = rows.slice();

  function normalize(value) {
    return String(value || "").trim().toLowerCase();
  }

  function visibleRows() {
    return rows.filter(function (row) {
      return row.classList.contains("d-none") === false;
    });
  }

  function setRowVisibility() {
    var total = matchedRows.length;
    var totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) {
      currentPage = totalPages;
    }
    if (currentPage < 1) {
      currentPage = 1;
    }

    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;
    rows.forEach(function (row) {
      row.classList.add("d-none");
    });
    matchedRows.slice(start, end).forEach(function (row) {
      row.classList.remove("d-none");
    });

    if (emptyRow) {
      emptyRow.classList.toggle("d-none", total !== 0);
    }
    if (countEl) {
      countEl.textContent = total + (total === 1 ? " row" : " rows");
    }
    if (pageSummary) {
      if (!total) {
        pageSummary.textContent = "No rows to show";
      } else {
        pageSummary.textContent = "Showing " + (start + 1) + "-" + Math.min(end, total) + " of " + total;
      }
    }
    if (pageJump) {
      var currentValue = String(currentPage);
      var options = "";
      for (var page = 1; page <= totalPages; page += 1) {
        options += '<option value="' + page + '"' + (page === currentPage ? " selected" : "") + ">Page " + page + "</option>";
      }
      pageJump.innerHTML = options;
      pageJump.value = currentValue;
    }
    if (prevButton) {
      prevButton.disabled = currentPage <= 1 || total === 0;
    }
    if (nextButton) {
      nextButton.disabled = currentPage >= totalPages || total === 0;
    }
  }

  function updateFilters() {
    var search = normalize(searchInput ? searchInput.value : "");
    var activeFilters = filterInputs.map(function (input) {
      return {
        key: input.getAttribute("data-required-report-filter") || "",
        value: normalize(input.value)
      };
    }).filter(function (filter) {
      return filter.key && filter.value;
    });

    matchedRows = [];
    rows.forEach(function (row) {
      var searchText = normalize(row.getAttribute("data-search"));
      var matchesSearch = !search || searchText.indexOf(search) !== -1;
      var matchesFilters = activeFilters.every(function (filter) {
        return normalize(row.getAttribute("data-filter-" + filter.key)) === filter.value;
      });
      if (matchesSearch && matchesFilters) {
        matchedRows.push(row);
      }
    });
    currentPage = 1;
    setRowVisibility();
  }

  function csvEscape(value) {
    var text = String(value || "").replace(/\s+/g, " ").trim();
    if (/[",\n]/.test(text)) {
      return '"' + text.replace(/"/g, '""') + '"';
    }
    return text;
  }

  function exportCsv() {
    var headers = Array.prototype.slice.call(table.querySelectorAll("thead th")).map(function (cell) {
      return cell.textContent;
    });
    var body = visibleRows().map(function (row) {
      return Array.prototype.slice.call(row.querySelectorAll("td")).map(function (cell) {
        return cell.textContent;
      });
    });

    if (!body.length) {
      window.alert("No report rows to export.");
      return;
    }

    var titleText = table.getAttribute("data-report-title") || "Report";
    var generatedLabel = table.getAttribute("data-report-generated-label") || "";
    var filterSummary = table.getAttribute("data-report-filters") || "No server filters applied";
    var lines = [
      ["Report", titleText],
      ["Generated", generatedLabel],
      ["Filters", filterSummary],
      []
    ].concat([headers], body).map(function (line) {
      return line.map(csvEscape).join(",");
    });
    var title = normalize(titleText).replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") || "report";
    var generated = normalize(table.getAttribute("data-report-generated")).replace(/[^0-9-]+/g, "") || "";
    var blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8" });
    var url = URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = title + (generated ? "-" + generated : "") + ".csv";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  if (searchInput) {
    searchInput.addEventListener("input", updateFilters);
  }
  filterInputs.forEach(function (input) {
    input.addEventListener("change", updateFilters);
  });
  if (resetButton) {
    resetButton.addEventListener("click", function () {
      if (searchInput) {
        searchInput.value = "";
      }
      filterInputs.forEach(function (input) {
        input.value = "";
      });
      updateFilters();
    });
  }
  if (pageJump) {
    pageJump.addEventListener("change", function () {
      currentPage = parseInt(pageJump.value, 10) || 1;
      setRowVisibility();
    });
  }
  if (prevButton) {
    prevButton.addEventListener("click", function () {
      currentPage -= 1;
      setRowVisibility();
    });
  }
  if (nextButton) {
    nextButton.addEventListener("click", function () {
      currentPage += 1;
      setRowVisibility();
    });
  }
  exportButtons.forEach(function (button) {
    button.addEventListener("click", exportCsv);
  });

  updateFilters();
})();
