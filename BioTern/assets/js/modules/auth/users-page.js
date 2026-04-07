(function () {
  "use strict";

  function initFilterControls() {
    if (
      window.BioTernSelectDropdown &&
      typeof window.BioTernSelectDropdown.refresh === "function"
    ) {
      window.BioTernSelectDropdown.refresh();
    }
  }

  function initFilterAutoSubmit() {
    var filterForm = document.getElementById("usersFilterForm");
    var searchInput = document.getElementById("usersFilterSearch");
    var submitTimer;

    function submitFilters() {
      if (filterForm) filterForm.submit();
    }

    function debounceSubmit() {
      clearTimeout(submitTimer);
      submitTimer = setTimeout(submitFilters, 350);
    }

    ["usersFilterRole", "usersFilterStatus"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

    if (searchInput) searchInput.addEventListener("input", debounceSubmit);
  }

  function initDataTable() {
    if (
      window.jQuery &&
      window.jQuery.fn &&
      typeof window.jQuery.fn.DataTable === "function" &&
      window.jQuery("#usersListTable").length &&
      !window.jQuery.fn.DataTable.isDataTable("#usersListTable")
    ) {
      window.jQuery("#usersListTable").DataTable({
        pageLength: 10,
        lengthMenu: [
          [10, 25, 50, 100],
          [10, 25, 50, 100],
        ],
        order: [[3, "desc"]],
        columnDefs: [{ orderable: false, targets: [4] }],
      });
    }

    var dataTableSearchInput = document.querySelector("#usersListTable_filter input");
    if (dataTableSearchInput) {
      dataTableSearchInput.setAttribute("placeholder", "Search user, email, or role");
    }

    initFilterControls();
  }

  function initInlineDetails() {
    document.querySelectorAll(".app-users-inline-toggle").forEach(function (toggle) {
      var targetSelector = toggle.getAttribute("data-bs-target");
      if (!targetSelector) return;
      var target = document.querySelector(targetSelector);
      if (!target) return;

      function syncToggleState() {
        var expanded = target.classList.contains("show");
        toggle.textContent = expanded ? "Hide" : "Details";
        toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
      }

      target.addEventListener("show.bs.collapse", syncToggleState);
      target.addEventListener("hide.bs.collapse", syncToggleState);
      syncToggleState();
    });
  }

  function initActionDropdownPortal() {
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

    document.querySelectorAll(".users-action-dropdown").forEach(function (dropdownEl) {
      dropdownEl.addEventListener("shown.bs.dropdown", function () {
        var menuEl = dropdownEl.querySelector(".dropdown-menu");
        var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
        if (!menuEl) return;
        if (!menuEl.dataset.portalParentId) {
          if (!dropdownEl.id) {
            dropdownEl.id = "users-action-dd-" + Math.random().toString(36).slice(2, 10);
          }
          menuEl.dataset.portalParentId = dropdownEl.id;
        }
        document.body.appendChild(menuEl);
        menuEl.classList.add("users-action-menu-portal", "show");
        if (toggle) toggle.classList.add("portal-open");
        positionActionMenu(dropdownEl, menuEl);
        activeActionMenu = { dropdown: dropdownEl, menu: menuEl };
      });

      dropdownEl.addEventListener("hide.bs.dropdown", function () {
        var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
        var menuEl =
          document.querySelector(
            '.users-action-menu-portal[data-portal-parent-id="' + dropdownEl.id + '"]'
          ) || dropdownEl.querySelector(".dropdown-menu");
        if (!menuEl) return;
        menuEl.classList.remove("users-action-menu-portal", "show");
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
  }

  function initUsersPage() {
    initFilterControls();
    initFilterAutoSubmit();
    initDataTable();
    initInlineDetails();
    initActionDropdownPortal();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initUsersPage);
  } else {
    initUsersPage();
  }
})();
