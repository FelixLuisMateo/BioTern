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
      "filter-semester",
      "filter-supervisor",
      "filter-coordinator",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

  }

  function initHeaderTableSearch() {
    var searchInput = document.getElementById("studentsHeaderSearchInput");
    if (!searchInput) return;
    var inputTimer;
    var bound = false;
    var attempts = 0;

    function tryBind() {
      if (bound) return;
      attempts += 1;

      if (
        window.jQuery &&
        window.jQuery.fn &&
        typeof window.jQuery.fn.DataTable === "function" &&
        window.jQuery.fn.DataTable.isDataTable("#customerList")
      ) {
        var table = window.jQuery("#customerList").DataTable();
        searchInput.value = table.search() || "";
        searchInput.addEventListener("input", function () {
          var query = searchInput.value || "";
          clearTimeout(inputTimer);
          inputTimer = setTimeout(function () {
            table.search(query).draw();
          }, 120);
        });
        bound = true;
        return;
      }

      if (attempts < 12) {
        window.setTimeout(tryBind, 120);
      }
    }

    tryBind();
  }

  function initPrintActions() {
    function getSelectedPrintButton() {
      return document.getElementById("printSelectedStudents");
    }

    function getTableRows() {
      return Array.prototype.slice.call(
        document.querySelectorAll("#customerList tbody tr.app-students-table-row")
      );
    }

    function getSelectedRows() {
      return getTableRows().filter(function (row) {
        var checkbox = row.querySelector(".checkbox");
        return !!(checkbox && checkbox.checked);
      });
    }

    function buildPrintRowMarkup(row, index) {
      var studentId = (row.dataset.printStudentId || "").trim();
      var lastName = (row.dataset.printLastName || "").trim();
      var firstName = (row.dataset.printFirstName || "").trim();
      var middleName = (row.dataset.printMiddleName || "").trim();

      return (
        "<tr>" +
        '<td class="col-index">' +
        String(index + 1) +
        "</td>" +
        "<td>" +
        escapeHtml(studentId) +
        "</td>" +
        "<td>" +
        escapeHtml(lastName) +
        "</td>" +
        "<td>" +
        escapeHtml(firstName) +
        "</td>" +
        "<td>" +
        escapeHtml(middleName) +
        "</td>" +
        "<td></td>" +
        "</tr>"
      );
    }

    function escapeHtml(value) {
      return String(value || "").replace(/[&<>'\"]/g, function (char) {
        return {
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        }[char];
      });
    }

    function syncPrintSheetRows(forceSelectedOnly) {
      var printSheetBody = document.querySelector(".student-list-print-sheet tbody");
      if (!printSheetBody) return true;

      var selectedRows = getSelectedRows();
      var sourceRows = forceSelectedOnly
        ? selectedRows
        : getTableRows();

      if (sourceRows.length === 0) {
        printSheetBody.innerHTML =
          "<tr><td class=\"col-index\">1</td><td colspan=\"5\">No students found for current filter.</td></tr>";
        return false;
      }

      printSheetBody.innerHTML = sourceRows
        .map(function (row, index) {
          return buildPrintRowMarkup(row, index);
        })
        .join("");

      return true;
    }

    function updateSelectedPrintButton() {
      var selectedBtn = getSelectedPrintButton();
      if (!selectedBtn) return;

      var selectedCount = getSelectedRows().length;
      selectedBtn.classList.toggle("d-none", selectedCount === 0);
      selectedBtn.setAttribute("aria-hidden", selectedCount === 0 ? "true" : "false");
      var label = selectedBtn.querySelector("span");
      if (label) {
        label.textContent =
          selectedCount > 0
            ? "Print Selected (" + selectedCount + ")"
            : "Print Selected";
      }
    }

    window.BioTernStudentsSelectionChanged = updateSelectedPrintButton;

    document.querySelectorAll(".js-print-page").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        if (!syncPrintSheetRows(false)) {
          alert("No student rows available to print.");
          return;
        }
        window.print();
      });
    });

    var selectedBtn = getSelectedPrintButton();
    if (selectedBtn) {
      selectedBtn.addEventListener("click", function (e) {
        e.preventDefault();
        if (!syncPrintSheetRows(true)) {
          alert("Select at least one student to print.");
          return;
        }
        window.print();
      });
    }

    updateSelectedPrintButton();
  }

  function initTableCheckboxes() {
    var selectAll = document.getElementById("checkAllStudent");
    var rowCheckboxes = Array.prototype.slice.call(
      document.querySelectorAll("#customerList tbody .checkbox")
    );

    function toggleRowState(checkboxEl) {
      var row = checkboxEl ? checkboxEl.closest("tr") : null;
      if (!row) return;
      row.classList.toggle("selected", !!checkboxEl.checked);
    }

    function refreshSelectAllState() {
      if (!selectAll || rowCheckboxes.length === 0) return;
      var checkedCount = rowCheckboxes.filter(function (cb) {
        return cb.checked;
      }).length;
      selectAll.checked = checkedCount > 0 && checkedCount === rowCheckboxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;

      if (typeof window.BioTernStudentsSelectionChanged === "function") {
        window.BioTernStudentsSelectionChanged();
      }
    }

    if (selectAll) {
      selectAll.addEventListener("change", function () {
        rowCheckboxes.forEach(function (checkboxEl) {
          checkboxEl.checked = !!selectAll.checked;
          toggleRowState(checkboxEl);
        });
        refreshSelectAllState();
      });
    }

    rowCheckboxes.forEach(function (checkboxEl) {
      checkboxEl.addEventListener("change", function () {
        toggleRowState(checkboxEl);
        refreshSelectAllState();
      });
      toggleRowState(checkboxEl);
    });

    refreshSelectAllState();
  }

  function initStudentActionModal() {
    var modal = document.getElementById("studentsActionModal");
    if (!modal) return;
    var form = document.getElementById("studentsActionAssignForm");
    if (!form) return;
    var studentIdInput = form.querySelector('input[name="student_id"]');
    var trackSelect = form.querySelector('select[name="assignment_track"]');
    var departmentSelect = form.querySelector('select[name="department_id"]');
    var supervisorSelect = form.querySelector('select[name="supervisor_id"]');
    var summary = modal.querySelector("[data-student-action-summary]");
    var currentInfo = modal.querySelector("[data-student-action-current]");
    var editLink = modal.querySelector("[data-action-edit]");
    var printLink = modal.querySelector("[data-action-print]");
    var remindLink = modal.querySelector("[data-action-remind]");

    document.querySelectorAll("[data-student-action-trigger]").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var studentId = trigger.getAttribute("data-student-id") || "";
        var studentName = trigger.getAttribute("data-student-name") || "Selected student";
        var studentTrack = trigger.getAttribute("data-student-track") || "internal";
        var departmentId = trigger.getAttribute("data-student-department-id") || "0";
        var supervisorId = trigger.getAttribute("data-student-supervisor-id") || "0";
        var supervisorName = trigger.getAttribute("data-student-supervisor-name") || "";
        var coordinatorName = trigger.getAttribute("data-student-coordinator-name") || "";
        var email = trigger.getAttribute("data-student-email") || "";

        if (studentIdInput) studentIdInput.value = studentId;
        if (trackSelect) trackSelect.value = studentTrack;
        if (departmentSelect) departmentSelect.value = departmentId;
        if (supervisorSelect) supervisorSelect.value = supervisorId;
        if (summary) summary.textContent = "Assign track and actions for " + studentName + ".";
        if (currentInfo) {
          currentInfo.textContent =
            "Current track: " +
            studentTrack.charAt(0).toUpperCase() +
            studentTrack.slice(1) +
            " | Supervisor: " +
            (supervisorName || "Not assigned") +
            " | Coordinator: " +
            (coordinatorName || "Not assigned");
        }
        if (editLink) editLink.href = "students-edit.php?id=" + encodeURIComponent(studentId);
        if (printLink) printLink.href = "students-view.php?id=" + encodeURIComponent(studentId);
        if (remindLink) {
          remindLink.href =
            "mailto:" +
            encodeURIComponent(email) +
            "?subject=" +
            encodeURIComponent("Reminder from BioTern");
        }
      });
    });
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
    initHeaderTableSearch();
    initPrintActions();
    initTableCheckboxes();
    initStudentActionModal();
    initExportActions();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStudentsPageRuntime);
  } else {
    initStudentsPageRuntime();
  }
})();
