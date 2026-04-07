/* Students page runtime extracted from inline script */
(function () {
  "use strict";

  function initFilterSelects() {
    if (
      window.BioTernSelectDropdown &&
      typeof window.BioTernSelectDropdown.refresh === "function"
    ) {
      window.BioTernSelectDropdown.refresh();
    }
  }

  function initFilterAutoSubmit() {
    var filterForm = document.getElementById("studentsFilterForm");
    function submitFilters() {
      if (filterForm) filterForm.submit();
    }

    [
      "filter-date",
      "filter-course",
      "filter-department",
      "filter-section",
      "filter-school-year",
      "filter-supervisor",
      "filter-coordinator",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

  }

  function initPrintActions() {
    document.querySelectorAll(".js-print-page").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
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

    document.querySelectorAll(".students-action-dropdown").forEach(function (dropdownEl) {
      dropdownEl.addEventListener("shown.bs.dropdown", function () {
        var menuEl = dropdownEl.querySelector(".dropdown-menu");
        var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
        if (!menuEl) return;
        if (!menuEl.dataset.portalParentId) {
          if (!dropdownEl.id) {
            dropdownEl.id = "students-action-dd-" + Math.random().toString(36).slice(2, 10);
          }
          menuEl.dataset.portalParentId = dropdownEl.id;
        }
        document.body.appendChild(menuEl);
        menuEl.classList.add("students-action-menu-portal", "show");
        if (toggle) {
          toggle.classList.add("portal-open");
        }
        positionActionMenu(dropdownEl, menuEl);
        activeActionMenu = { dropdown: dropdownEl, menu: menuEl };
      });

      dropdownEl.addEventListener("hide.bs.dropdown", function () {
        var toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
        var menuEl =
          document.querySelector(
            '.students-action-menu-portal[data-portal-parent-id="' + dropdownEl.id + '"]'
          ) || dropdownEl.querySelector(".dropdown-menu");
        if (!menuEl) return;
        menuEl.classList.remove("students-action-menu-portal", "show");
        menuEl.style.position = "";
        menuEl.style.top = "";
        menuEl.style.left = "";
        dropdownEl.appendChild(menuEl);
        if (toggle) {
          toggle.classList.remove("portal-open");
        }
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

  function initExportActions() {
    function tableToRows() {
      var rows = [];
      var table = document.getElementById("customerList");
      if (!table) return rows;

      var bodyRows = table.querySelectorAll("tbody tr");
      bodyRows.forEach(function (tr) {
        var row = {
          name: (tr.dataset.exportName || "").trim(),
          student_id: (tr.dataset.exportStudentId || "").trim(),
          course: (tr.dataset.exportCourse || "").trim(),
          section: (tr.dataset.exportSection || "").trim(),
          supervisor: (tr.dataset.exportSupervisor || "").trim(),
          coordinator: (tr.dataset.exportCoordinator || "").trim(),
          last_logged: (tr.dataset.exportLastLogged || "").trim(),
          status: (tr.dataset.exportStatus || "").trim(),
        };
        if (!row.name) {
          var cells = tr.querySelectorAll("td");
          if (!cells || cells.length < 6) return;
          row = {
            name: (cells[1].innerText || "").trim(),
            student_id: "",
            course: (cells[2].innerText || "").trim(),
            section: "",
            supervisor: (cells[3].innerText || "").trim(),
            coordinator: "",
            last_logged: (cells[4].innerText || "").trim(),
            status: (cells[5].innerText || "").trim(),
          };
        }
        if (row.name !== "" && row.name.toLowerCase() !== "no students found") {
          rows.push(row);
        }
      });
      return rows;
    }

    function downloadTextFile(filename, mimeType, content) {
      var blob = new Blob([content], { type: mimeType });
      var url = URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    function toCsv(rows) {
      var headers = [
        "Name",
        "Student ID",
        "Course",
        "Section",
        "Supervisor",
        "Coordinator",
        "Last Logged",
        "Status",
      ];
      var lines = [headers.join(",")];
      rows.forEach(function (r) {
        var vals = [
          r.name,
          r.student_id,
          r.course,
          r.section,
          r.supervisor,
          r.coordinator,
          r.last_logged,
          r.status,
        ].map(function (v) {
          var s = String(v || "").replace(/"/g, '""');
          return '"' + s + '"';
        });
        lines.push(vals.join(","));
      });
      return lines.join("\n");
    }

    function toTxt(rows) {
      var lines = [
        "Name | Student ID | Course | Section | Supervisor | Coordinator | Last Logged | Status",
      ];
      rows.forEach(function (r) {
        lines.push(
          [
            r.name,
            r.student_id,
            r.course,
            r.section,
            r.supervisor,
            r.coordinator,
            r.last_logged,
            r.status,
          ].join(" | ")
        );
      });
      return lines.join("\n");
    }

    function toXml(rows) {
      var xml = ['<?xml version="1.0" encoding="UTF-8"?>', "<students>"];
      rows.forEach(function (r) {
        function escXml(v) {
          return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&apos;");
        }
        xml.push("  <student>");
        xml.push("    <name>" + escXml(r.name) + "</name>");
        xml.push("    <student_id>" + escXml(r.student_id) + "</student_id>");
        xml.push("    <course>" + escXml(r.course) + "</course>");
        xml.push("    <section>" + escXml(r.section) + "</section>");
        xml.push("    <supervisor>" + escXml(r.supervisor) + "</supervisor>");
        xml.push("    <coordinator>" + escXml(r.coordinator) + "</coordinator>");
        xml.push("    <last_logged>" + escXml(r.last_logged) + "</last_logged>");
        xml.push("    <status>" + escXml(r.status) + "</status>");
        xml.push("  </student>");
      });
      xml.push("</students>");
      return xml.join("\n");
    }

    document.querySelectorAll(".js-export").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        var type = (this.getAttribute("data-export") || "").toLowerCase();
        var rows = tableToRows();
        var ts = new Date().toISOString().slice(0, 10);

        if (type === "pdf") {
          window.print();
          return;
        }

        if (rows.length === 0) {
          alert("No student rows available to export.");
          return;
        }

        if (type === "csv" || type === "excel") {
          var csv = toCsv(rows);
          var ext = type === "excel" ? "xls" : "csv";
          var mime =
            type === "excel"
              ? "application/vnd.ms-excel;charset=utf-8;"
              : "text/csv;charset=utf-8;";
          downloadTextFile("students_" + ts + "." + ext, mime, csv);
          return;
        }

        if (type === "txt") {
          downloadTextFile("students_" + ts + ".txt", "text/plain;charset=utf-8;", toTxt(rows));
          return;
        }

        if (type === "xml") {
          downloadTextFile("students_" + ts + ".xml", "application/xml;charset=utf-8;", toXml(rows));
        }
      });
    });
  }

  function initStudentsPageRuntime() {
    initFilterSelects();
    initFilterAutoSubmit();
    initPrintActions();
    initActionDropdownPortal();
    initExportActions();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudentsPageRuntime);
  } else {
    initStudentsPageRuntime();
  }
})();
