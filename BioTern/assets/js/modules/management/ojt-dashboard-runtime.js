/* OJT dashboard page runtime extracted from inline script */
(function () {
  "use strict";

  function initOjtDashboardRuntime() {
    var dataTableInstance = null;
    var filterForm = document.getElementById("ojtFilterForm");
    var searchInput = document.getElementById("ojtHeaderSearchInput");
    var forceStackBreakpoint = 1280;

    function submitFilters() {
      if (filterForm) filterForm.submit();
    }

    ["ojtFilterCourse", "ojtFilterSection", "ojtFilterSchoolYear", "ojtFilterSemester", "ojtFilterStage", "ojtFilterRisk", "ojtFilterAccount"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

    var printBtn = document.getElementById("ojtPrintBtn");
    if (printBtn && !printBtn.hasAttribute("data-ojt-print-full")) {
      printBtn.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
    }

    function updateResponsiveWorklistMode() {
      if (!document.body) return;
      var tableCard = document.querySelector(".app-ojt-table-card");
      var tableWrap = document.querySelector(".app-ojt-table-wrap");
      var tableEl = document.getElementById("ojtListTable");
      var contentWidth = tableCard ? tableCard.clientWidth : window.innerWidth;
      var needsStack = contentWidth < forceStackBreakpoint;

      if (tableWrap && tableEl) {
        needsStack = needsStack || tableEl.scrollWidth > tableWrap.clientWidth + 16;
      }

      document.body.classList.toggle("app-ojt-force-stack", needsStack);
    }

    function bindViewAllButton() {
      var button = document.querySelector('[data-view-all-table="ojtListTable"]');
      if (!button || !dataTableInstance) return;

      function syncLabel() {
        button.textContent = dataTableInstance.page.len() === -1 ? "Show paged list" : "View all list";
      }

      button.addEventListener("click", function () {
        dataTableInstance.page.len(dataTableInstance.page.len() === -1 ? 10 : -1).draw();
        syncLabel();
        window.setTimeout(updateResponsiveWorklistMode, 0);
      });
      syncLabel();
    }

    updateResponsiveWorklistMode();

    if (
      window.jQuery &&
      window.jQuery.fn &&
      typeof window.jQuery.fn.DataTable === "function" &&
      window.jQuery("#ojtListTable").length &&
      !window.jQuery.fn.DataTable.isDataTable("#ojtListTable")
    ) {
      var ojtTable = document.getElementById("ojtListTable");
      if (ojtTable) {
        // DataTables expects each body row to match header column count.
        ojtTable.querySelectorAll("tbody tr").forEach(function (row) {
          var firstCell = row.querySelector("td[colspan]");
          if (firstCell) {
            row.remove();
          }
        });
      }

      dataTableInstance = window.jQuery("#ojtListTable").DataTable({
        pageLength: 10,
        lengthMenu: [
          [10, 25, 50, -1],
          [10, 25, 50, "All"],
        ],
        lengthChange: false,
        dom: "rtip",
        order: [[6, "desc"]],
        columnDefs: [
          { orderable: false, targets: [7] },
        ],
      });
    } else if (
      window.jQuery &&
      window.jQuery.fn &&
      typeof window.jQuery.fn.DataTable === "function" &&
      window.jQuery("#ojtListTable").length &&
      window.jQuery.fn.DataTable.isDataTable("#ojtListTable")
    ) {
      dataTableInstance = window.jQuery("#ojtListTable").DataTable();
    }

    bindViewAllButton();

    if (searchInput && dataTableInstance) {
      var searchTimer;
      var initialSearch = searchInput.value || dataTableInstance.search() || "";

      searchInput.value = initialSearch;
      if (initialSearch) {
        dataTableInstance.search(initialSearch).draw();
      }

      searchInput.addEventListener("input", function () {
        clearTimeout(searchTimer);
        var query = searchInput.value || "";
        searchTimer = setTimeout(function () {
          dataTableInstance.search(query).draw();
        }, 120);
      });
    }

    if (
      window.BioTernSelectDropdown &&
      typeof window.BioTernSelectDropdown.refresh === "function"
    ) {
      window.BioTernSelectDropdown.refresh();
    }

    window.addEventListener("resize", updateResponsiveWorklistMode);

    (function initInlineDetailRows() {
      var tableEl = document.getElementById("ojtListTable");
      if (!tableEl) {
        return;
      }

      function syncToggleState(toggle, expanded) {
        if (!toggle) return;
        toggle.textContent = expanded ? "Hide" : "Details";
        toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
      }

      function closeOtherDetails(exceptRowNode) {
        tableEl.querySelectorAll(".app-ojt-inline-toggle[aria-expanded='true']").forEach(function (openToggle) {
          var openRowNode = openToggle.closest("tr");
          if (!openRowNode || openRowNode === exceptRowNode) {
            return;
          }

          if (dataTableInstance && window.jQuery) {
            var openRowApi = dataTableInstance.row(window.jQuery(openRowNode));
            if (openRowApi && openRowApi.child && openRowApi.child.isShown()) {
              openRowApi.child.hide();
            }
          }

          syncToggleState(openToggle, false);
        });
      }

      tableEl.addEventListener("click", function (event) {
        var toggle = event.target.closest(".app-ojt-inline-toggle");
        if (!toggle) {
          return;
        }

        event.preventDefault();
        var rowNode = toggle.closest("tr");
        if (!rowNode) {
          return;
        }

        var template = rowNode.querySelector(".app-ojt-detail-template");
        if (!template) {
          return;
        }

        if (dataTableInstance && window.jQuery) {
          var rowApi = dataTableInstance.row(window.jQuery(rowNode));
          if (!rowApi || !rowApi.child) {
            return;
          }

          if (rowApi.child.isShown()) {
            rowApi.child.hide();
            syncToggleState(toggle, false);
            return;
          }

          closeOtherDetails(rowNode);
          rowApi.child('<div class="app-ojt-inline-collapse">' + template.innerHTML + "</div>", "app-ojt-detail-child-row").show();
          syncToggleState(toggle, true);
          return;
        }

        var fallbackRow = rowNode.nextElementSibling;
        if (fallbackRow && fallbackRow.classList.contains("app-ojt-detail-fallback-row")) {
          fallbackRow.remove();
          syncToggleState(toggle, false);
          return;
        }

        closeOtherDetails(rowNode);
        var detailsRow = document.createElement("tr");
        detailsRow.className = "app-ojt-detail-fallback-row";
        detailsRow.innerHTML = '<td colspan="6"><div class="app-ojt-inline-collapse">' + template.innerHTML + "</div></td>";
        rowNode.insertAdjacentElement("afterend", detailsRow);
        syncToggleState(toggle, true);
      });
    })();

    (function initActionDropdownPortal() {
      var activeActionMenu = null;

      function positionActionMenu(dropdownEl, menuEl) {
        var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
        if (!toggle || !menuEl) return;
        var tRect = toggle.getBoundingClientRect();
        var menuWidth = menuEl.offsetWidth || 220;
        var menuHeight = menuEl.offsetHeight || 220;

        var left = tRect.right - menuWidth;
        if (left < 12) left = 12;
        if (left + menuWidth > window.innerWidth - 12) {
          left = window.innerWidth - menuWidth - 12;
        }

        var top = tRect.bottom + 1;
        if (top + menuHeight > window.innerHeight - 12) {
          top = Math.max(12, tRect.top - menuHeight - 1);
        }

        menuEl.style.position = "fixed";
        menuEl.style.top = top + "px";
        menuEl.style.left = left + "px";
      }

      document.querySelectorAll(".ojt-action-dropdown").forEach(function (dropdownEl) {
        dropdownEl.addEventListener("shown.bs.dropdown", function () {
          var menuEl = dropdownEl.querySelector(".dropdown-menu");
          var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
          if (!menuEl) return;
          if (!menuEl.dataset.portalParentId) {
            if (!dropdownEl.id) {
              dropdownEl.id = "ojt-action-dd-" + Math.random().toString(36).slice(2, 10);
            }
            menuEl.dataset.portalParentId = dropdownEl.id;
          }
          document.body.appendChild(menuEl);
          menuEl.classList.add("ojt-action-menu-portal", "show");
          if (toggle) toggle.classList.add("portal-open");
          positionActionMenu(dropdownEl, menuEl);
          activeActionMenu = { dropdown: dropdownEl, menu: menuEl };
        });

        dropdownEl.addEventListener("hide.bs.dropdown", function () {
          var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
          var menuEl =
            document.querySelector(
              '.ojt-action-menu-portal[data-portal-parent-id="' + dropdownEl.id + '"]'
            ) || dropdownEl.querySelector(".dropdown-menu");
          if (!menuEl) return;
          menuEl.classList.remove("ojt-action-menu-portal", "show");
          menuEl.style.position = "";
          menuEl.style.top = "";
          menuEl.style.left = "";
          dropdownEl.appendChild(menuEl);
          if (toggle) toggle.classList.remove("portal-open");
          if (activeActionMenu && activeActionMenu.dropdown === dropdownEl) {
            activeActionMenu = null;
          }
        });
      });

      window.addEventListener("resize", function () {
        updateResponsiveWorklistMode();
        if (activeActionMenu && activeActionMenu.dropdown && activeActionMenu.menu) {
          positionActionMenu(activeActionMenu.dropdown, activeActionMenu.menu);
        }
      });

      window.addEventListener(
        "scroll",
        function () {
          if (activeActionMenu && activeActionMenu.dropdown && activeActionMenu.menu) {
            positionActionMenu(activeActionMenu.dropdown, activeActionMenu.menu);
          }
        },
        true
      );
    })();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initOjtDashboardRuntime);
  } else {
    initOjtDashboardRuntime();
  }
})();
