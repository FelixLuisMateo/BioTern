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
      "filter-status",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", submitFilters);
    });

  }

  function initStudentsDataTable() {
    if (
      !window.jQuery ||
      !window.jQuery.fn ||
      typeof window.jQuery.fn.DataTable !== "function" ||
      !window.jQuery("#customerList").length
    ) {
      return null;
    }

    var table = window.jQuery.fn.DataTable.isDataTable("#customerList")
      ? window.jQuery("#customerList").DataTable()
      : window.jQuery("#customerList").DataTable({
          pageLength: 10,
          lengthMenu: [
            [10, 25, 50, -1],
            [10, 25, 50, "All"],
          ],
          lengthChange: false,
          dom: "rtip",
          order: [],
          columnDefs: [{ orderable: false, targets: [0] }],
        });

    var viewAllButton = document.querySelector('[data-view-all-table="customerList"]');
    if (viewAllButton) {
      var tableEl = document.getElementById("customerList");
      var wrapper = tableEl ? tableEl.closest(".dataTables_wrapper") : null;
      var paginateHost = wrapper ? wrapper.querySelector(".dataTables_paginate") : null;
      var legacyWrap = viewAllButton.closest(".d-flex.justify-content-end.px-3.py-2");
      if (paginateHost) {
        var viewAllSlot = paginateHost.querySelector(".app-students-pagination-viewall");
        var paginationList = paginateHost.querySelector("ul.pagination");
        if (!viewAllSlot) {
          viewAllSlot = document.createElement("div");
          viewAllSlot.className = "app-students-pagination-viewall";
          if (paginationList && paginationList.nextSibling) {
            paginateHost.insertBefore(viewAllSlot, paginationList.nextSibling);
          } else {
            paginateHost.appendChild(viewAllSlot);
          }
        }
        viewAllSlot.appendChild(viewAllButton);
        if (legacyWrap && legacyWrap !== viewAllSlot && legacyWrap.children.length === 0) {
          legacyWrap.remove();
        }
      }

      function syncLabel() {
        viewAllButton.textContent = table.page.len() === -1 ? "Show paged list" : "View all list";
      }
      viewAllButton.addEventListener("click", function () {
        table.page.len(table.page.len() === -1 ? 10 : -1).draw();
        syncLabel();
      });
      syncLabel();
    }

    return table;
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
      var studentName = (row.dataset.printStudentName || "").trim();
      var academic = (row.dataset.printAcademic || "").trim();
      var section = (row.dataset.printSection || "").trim();
      var mentors = (row.dataset.printMentors || "").trim();

      if (!studentName) {
        studentName = [
          row.dataset.printFirstName || "",
          row.dataset.printMiddleName || "",
          row.dataset.printLastName || "",
        ]
          .join(" ")
          .replace(/\s+/g, " ")
          .trim();
      }

      return (
        "<tr>" +
        '<td class="col-index">' +
        String(index + 1) +
        "</td>" +
        "<td>" +
        escapeHtml(studentId) +
        "</td>" +
        "<td>" +
        escapeHtml(studentName || "-") +
        "</td>" +
        "<td>" +
        escapeHtml(academic || "-") +
        "</td>" +
        "<td>" +
        escapeHtml(section || "-") +
        "</td>" +
        "<td>" +
        escapeHtml(mentors || "-") +
        "</td>" +
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

    function selectedText(selectEl) {
      if (!selectEl || selectEl.selectedIndex < 0) return "";
      var option = selectEl.options[selectEl.selectedIndex];
      return option ? (option.textContent || "").trim() : "";
    }

    function normalizeFilterText(value) {
      return String(value || "")
        .replace(/^--\s*/g, "")
        .replace(/\s*--$/g, "")
        .trim();
    }

    function currentPrintFilterLabel() {
      var form = document.getElementById("studentsFilterForm");
      if (!form) return "All students";

      var parts = [];
      var fields = [
        { id: "filter-school-year", label: "School Year", empty: "All School Years" },
        { id: "filter-semester", label: "Semester", empty: "All Semesters" },
        { id: "filter-date", label: "Date", input: true },
        { id: "filter-course", label: "Course", empty: "All Courses" },
        { id: "filter-department", label: "Department", empty: "All Departments" },
        { id: "filter-section", label: "Section", empty: "All Sections" },
        { id: "filter-supervisor", label: "Supervisor", empty: "Any Supervisor" },
        { id: "filter-coordinator", label: "Coordinator", empty: "Any Coordinator" },
        { id: "filter-status", label: "Status", empty: "All Statuses" },
      ];

      fields.forEach(function (field) {
        var el = document.getElementById(field.id);
        if (!el) return;
        var text = field.input ? (el.value || "").trim() : selectedText(el);
        text = normalizeFilterText(text);
        if (!text || text === field.empty || text === "0" || text === "-1") return;
        parts.push(field.label + ": " + text);
      });

      return parts.length ? parts.join(" | ") : "All students";
    }

    function syncPrintFilterLabel() {
      var target = document.querySelector("[data-students-print-filter]");
      if (target) {
        target.textContent = currentPrintFilterLabel();
      }
    }

    function syncPrintSheetRows(forceSelectedOnly) {
      var printSheetBody = document.querySelector(".student-list-print-sheet tbody");
      if (!printSheetBody) return true;

      syncPrintFilterLabel();

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

  function initStudentDetailsModal() {
    var detailsModal = document.getElementById("studentsDetailsModal");
    if (!detailsModal) return;

    var fields = {
      name: document.getElementById("studentsDetailsName"),
      track: document.getElementById("studentsDetailsTrack"),
      section: document.getElementById("studentsDetailsSection"),
      email: document.getElementById("studentsDetailsEmail"),
      phone: document.getElementById("studentsDetailsPhone"),
    };

    document.addEventListener("click", function (event) {
      var trigger = event.target.closest("[data-student-details-trigger]");
      if (!trigger) return;

      if (fields.name) fields.name.textContent = trigger.getAttribute("data-student-name") || "-";
      if (fields.track) fields.track.textContent = trigger.getAttribute("data-student-track") || "-";
      if (fields.section) fields.section.textContent = trigger.getAttribute("data-student-section") || "-";
      if (fields.email) fields.email.textContent = trigger.getAttribute("data-student-email") || "-";
      if (fields.phone) fields.phone.textContent = trigger.getAttribute("data-student-phone") || "-";
    });
  }

  function initTableCheckboxes() {
    var selectAll = document.getElementById("checkAllStudent");
    function getRowCheckboxes() {
      if (
        window.jQuery &&
        window.jQuery.fn &&
        typeof window.jQuery.fn.DataTable === "function" &&
        window.jQuery.fn.DataTable.isDataTable("#customerList")
      ) {
        return Array.prototype.slice.call(
          window.jQuery("#customerList").DataTable().rows().nodes().to$().find(".checkbox")
        );
      }
      return Array.prototype.slice.call(
        document.querySelectorAll("#customerList tbody .checkbox")
      );
    }
    var rowCheckboxes = getRowCheckboxes();

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
        rowCheckboxes = getRowCheckboxes();
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
        rowCheckboxes = getRowCheckboxes();
        refreshSelectAllState();
      });
      toggleRowState(checkboxEl);
    });

    refreshSelectAllState();

    if (
      window.jQuery &&
      window.jQuery.fn &&
      typeof window.jQuery.fn.DataTable === "function" &&
      window.jQuery.fn.DataTable.isDataTable("#customerList")
    ) {
      window.jQuery("#customerList").on("draw.dt", function () {
        rowCheckboxes = getRowCheckboxes();
        rowCheckboxes.forEach(toggleRowState);
        refreshSelectAllState();
      });
    }
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
    var officeSelect = form.querySelector('select[name="office_id"]');
    var startDateInput = form.querySelector('input[name="start_date"]');
    var summary = modal.querySelector("[data-student-action-summary]");
    var currentInfo = modal.querySelector("[data-student-action-current]");
    var selectedInfo = modal.querySelector("[data-student-action-selected]");
    var editLink = modal.querySelector("[data-action-edit]");
    var printLink = modal.querySelector("[data-action-print]");
    var remindLink = modal.querySelector("[data-action-remind]");

    function draftKey(studentId) {
      return "biotern_students_action_draft_" + String(studentId || "");
    }

    function readDraft(studentId) {
      if (!studentId) return null;
      try {
        var raw = window.localStorage.getItem(draftKey(studentId));
        if (!raw) return null;
        var parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== "object") return null;
        return parsed;
      } catch (e) {
        return null;
      }
    }

    function writeDraft() {
      var studentId = studentIdInput ? String(studentIdInput.value || "") : "";
      if (!studentId) return;

      var payload = {
        assignment_track: trackSelect ? String(trackSelect.value || "") : "",
        department_id: departmentSelect ? String(departmentSelect.value || "") : "",
        supervisor_id: supervisorSelect ? String(supervisorSelect.value || "") : "",
        office_id: officeSelect ? String(officeSelect.value || "") : "",
        start_date: startDateInput ? String(startDateInput.value || "") : "",
        saved_at: Date.now(),
      };

      try {
        window.localStorage.setItem(draftKey(studentId), JSON.stringify(payload));
      } catch (e) {}
    }

    function restoreDraft(studentId) {
      var draft = readDraft(studentId);
      if (!draft) return false;

      if (trackSelect && draft.assignment_track) trackSelect.value = String(draft.assignment_track);
      if (departmentSelect && draft.department_id) departmentSelect.value = String(draft.department_id);
      if (supervisorSelect && draft.supervisor_id) supervisorSelect.value = String(draft.supervisor_id);
      if (officeSelect && draft.office_id) officeSelect.value = String(draft.office_id);
      if (startDateInput && draft.start_date) startDateInput.value = String(draft.start_date);
      filterAssignmentOptions();
      return true;
    }

    function optionText(select, value) {
      if (!select) return "";
      var normalized = String(value || "");
      for (var i = 0; i < select.options.length; i++) {
        if (String(select.options[i].value) === normalized) {
          return (select.options[i].textContent || "").trim();
        }
      }
      return "";
    }

    function updateActionCopy(studentName, studentTrack, supervisorName, coordinatorName, departmentName, summaryText) {
      if (summary) summary.textContent = summaryText || ("Assign track and actions for " + studentName + ".");
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
      if (selectedInfo) {
        selectedInfo.textContent =
          "Department: " +
          (optionText(departmentSelect, departmentSelect && departmentSelect.value) || departmentName || "Not assigned") +
          " | Supervisor: " +
          (optionText(supervisorSelect, supervisorSelect && supervisorSelect.value) || supervisorName || "Not assigned") +
          " | Office: " +
          (optionText(officeSelect, officeSelect && officeSelect.value) || "Auto") +
          " | Coordinator: " +
          (coordinatorName || "Not assigned");
      }
    }

    function csvHas(csv, value) {
      if (!csv || !value) return false;
      return String(csv).split(",").indexOf(String(value)) !== -1;
    }

    function filterAssignmentOptions() {
      var departmentId = departmentSelect ? String(departmentSelect.value || "0") : "0";
      var supervisorId = supervisorSelect ? String(supervisorSelect.value || "0") : "0";
      if (supervisorSelect) {
        Array.prototype.forEach.call(supervisorSelect.options, function (option) {
          if (!option.value || option.value === "0") {
            option.hidden = false;
            return;
          }
          var optionDepartment = option.getAttribute("data-department-id") || "0";
          option.hidden = !(departmentId === "0" || optionDepartment === "0" || optionDepartment === departmentId);
          if (option.hidden && option.selected) supervisorSelect.value = "0";
        });
      }
      if (officeSelect) {
        var visibleOffices = [];
        Array.prototype.forEach.call(officeSelect.options, function (option) {
          if (!option.value || option.value === "0") {
            option.hidden = false;
            return;
          }
          var optionDepartment = option.getAttribute("data-department-id") || "0";
          var supervisorIds = option.getAttribute("data-supervisor-ids") || "";
          var departmentMatches = departmentId === "0" || optionDepartment === "0" || optionDepartment === departmentId;
          var supervisorMatches = supervisorId === "0" || csvHas(supervisorIds, supervisorId);
          option.hidden = !(departmentMatches && supervisorMatches);
          if (!option.hidden) visibleOffices.push(option.value);
          if (option.hidden && option.selected) officeSelect.value = "0";
        });
        if (supervisorId !== "0" && visibleOffices.length === 1 && (!officeSelect.value || officeSelect.value === "0")) {
          officeSelect.value = visibleOffices[0];
        }
      }
    }

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
        var departmentName = trigger.getAttribute("data-student-department-name") || "";

        if (studentIdInput) studentIdInput.value = studentId;
        if (trackSelect) trackSelect.value = studentTrack;
        if (departmentSelect) departmentSelect.value = departmentId;
        if (supervisorSelect) supervisorSelect.value = supervisorId;
        if (officeSelect) officeSelect.value = "0";
        if (departmentSelect && String(departmentSelect.value) !== String(departmentId) && departmentName) {
          for (var di = 0; di < departmentSelect.options.length; di++) {
            if ((departmentSelect.options[di].textContent || "").trim() === departmentName) {
              departmentSelect.value = departmentSelect.options[di].value;
              break;
            }
          }
        }
        if (supervisorSelect && String(supervisorSelect.value) !== String(supervisorId) && supervisorName) {
          for (var si = 0; si < supervisorSelect.options.length; si++) {
            if ((supervisorSelect.options[si].textContent || "").trim() === supervisorName) {
              supervisorSelect.value = supervisorSelect.options[si].value;
              break;
            }
          }
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

        try {
          window.localStorage.removeItem(draftKey(studentId));
        } catch (e) {}
        updateActionCopy(studentName, studentTrack, supervisorName, coordinatorName, departmentName);
        filterAssignmentOptions();

        if (typeof window.BioTernSelectDropdown !== "undefined" &&
            window.BioTernSelectDropdown &&
            typeof window.BioTernSelectDropdown.refresh === "function") {
          window.BioTernSelectDropdown.refresh();
        }
      });
    });

    ["change", "input"].forEach(function (evtName) {
      [trackSelect, departmentSelect, supervisorSelect, officeSelect, startDateInput].forEach(function (field) {
        if (!field) return;
        field.addEventListener(evtName, writeDraft);
        field.addEventListener(evtName, filterAssignmentOptions);
      });
    });

    if (window.bootstrap && window.bootstrap.Modal && typeof modal.addEventListener === "function") {
      modal.addEventListener("shown.bs.modal", function () {
        var studentId = studentIdInput ? String(studentIdInput.value || "") : "";
        if (!studentId) return;
        if (restoreDraft(studentId)) {
          var studentName = "";
          var studentTrack = trackSelect ? String(trackSelect.value || "internal") : "internal";
          var supervisorName = "";
          var coordinatorName = "";
          var departmentName = "";
          var trigger = document.querySelector('[data-student-action-trigger][data-student-id="' + studentId.replace(/"/g, '\\"') + '"]');
          if (trigger) {
            studentName = trigger.getAttribute("data-student-name") || "";
            studentTrack = trigger.getAttribute("data-student-track") || studentTrack;
            supervisorName = trigger.getAttribute("data-student-supervisor-name") || "";
            coordinatorName = trigger.getAttribute("data-student-coordinator-name") || "";
            departmentName = trigger.getAttribute("data-student-department-name") || "";
          }
          if (typeof window.BioTernSelectDropdown !== "undefined" &&
              window.BioTernSelectDropdown &&
              typeof window.BioTernSelectDropdown.refresh === "function") {
            window.BioTernSelectDropdown.refresh();
          }
          if (studentName) {
            updateActionCopy(
              studentName,
              studentTrack,
              supervisorName,
              coordinatorName,
              departmentName,
              "Restored your last saved draft for " + studentName + "."
            );
          }
        }
      });
    }
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
    initStudentsDataTable();
    initHeaderTableSearch();
    initPrintActions();
    initStudentDetailsModal();
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
