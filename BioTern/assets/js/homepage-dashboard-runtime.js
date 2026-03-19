/* Homepage-specific runtime extracted from inline scripts */
(function () {
  "use strict";

  function initOjtOverviewChart() {
    try {
      if (typeof ApexCharts === "undefined") {
        return;
      }

      var el = document.querySelector("#ojt-overview-pie");
      if (!el) {
        return;
      }

      var cfg = document.getElementById("homepage-runtime-config");
      var pending = Number((cfg && cfg.dataset.ojtPending) || 0);
      var ongoing = Number((cfg && cfg.dataset.ojtOngoing) || 0);
      var completed = Number((cfg && cfg.dataset.ojtCompleted) || 0);
      var cancelled = Number((cfg && cfg.dataset.ojtCancelled) || 0);

      var chart = new ApexCharts(el, {
        chart: { type: "donut", height: 260 },
        series: [pending, ongoing, completed, cancelled],
        labels: ["Pending", "Ongoing", "Completed", "Cancelled"],
        colors: ["#f6c23e", "#36b9cc", "#1cc88a", "#e74a3b"],
        legend: { position: "bottom" },
        responsive: [
          {
            breakpoint: 768,
            options: { chart: { height: 200 }, legend: { position: "bottom" } },
          },
        ],
      });

      chart.render();
    } catch (e) {
      console.error("OJT chart init error", e);
    }
  }

  function initSidebarMiniMenuCollapse() {
    function collapseSidebarMenus() {
      if (!document.documentElement.classList.contains("minimenu")) return;
      document
        .querySelectorAll(
          ".nxl-navigation .nxl-item.nxl-hasmenu.open, .nxl-navigation .nxl-item.nxl-hasmenu.nxl-trigger"
        )
        .forEach(function (item) {
          item.classList.remove("open", "nxl-trigger");
        });
    }

    function runAfterToggle() {
      collapseSidebarMenus();
      setTimeout(collapseSidebarMenus, 80);
      setTimeout(collapseSidebarMenus, 220);
    }

    collapseSidebarMenus();

    ["menu-mini-button", "menu-expend-button", "mobile-collapse"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", runAfterToggle);
    });

    var nav = document.querySelector(".nxl-navigation");
    if (window.MutationObserver && nav) {
      var observer = new MutationObserver(function () {
        if (document.documentElement.classList.contains("minimenu")) {
          collapseSidebarMenus();
        }
      });
      observer.observe(nav, {
        subtree: true,
        attributes: true,
        attributeFilter: ["class"],
      });
    }
  }

  function applyProgressWidths() {
    document.querySelectorAll("[data-progress-width]").forEach(function (bar) {
      var raw = bar.getAttribute("data-progress-width");
      var value = Number(raw);
      if (!isFinite(value)) {
        value = 0;
      }
      value = Math.max(0, Math.min(100, value));
      bar.style.width = value + "%";
      bar.setAttribute("aria-valuenow", String(value));
    });
  }

  function downloadCSV(filename, rows) {
    if (!rows.length) {
      return;
    }
    var csv = rows
      .map(function (row) {
        return row
          .map(function (value) {
            var str = String(value == null ? "" : value);
            if (str.search(/("|,|\n)/g) >= 0) {
              str = '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
          })
          .join(",");
      })
      .join("\n");
    var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  function exportAttendanceCSV() {
    var table = document.getElementById("latest-attendance-table");
    if (!table) {
      return;
    }
    var rows = [];
    rows.push(["Student", "Student ID", "Attendance Date", "Time In", "Status"]);
    table.querySelectorAll("tbody tr").forEach(function (tr) {
      var cols = tr.querySelectorAll("td");
      if (cols.length < 4) return;
      var studentName = cols[0].querySelector(".fw-semibold");
      var studentId = cols[0].querySelector(".text-muted");
      rows.push([
        studentName ? studentName.textContent.trim() : "",
        studentId ? studentId.textContent.trim() : "",
        cols[1].textContent.trim(),
        cols[2].textContent.trim(),
        cols[3].textContent.trim(),
      ]);
    });
    downloadCSV("latest-attendance.csv", rows);
  }

  function exportRecentActivitiesCSV() {
    var rows = [];
    rows.push(["Activity", "Date"]);
    document.querySelectorAll(".recent-activity-item").forEach(function (row) {
      var title = row.querySelector(".fw-semibold");
      var date = row.querySelector(".text-muted.border-bottom-dashed");
      rows.push([
        title ? title.textContent.trim() : "",
        date ? date.textContent.trim() : "",
      ]);
    });
    downloadCSV("recent-activities.csv", rows);
  }

  function toggleAttendanceCompact() {
    var table = document.getElementById("latest-attendance-table");
    if (!table) return;
    table.classList.toggle("table-sm");
  }

  function toggleActivitiesCompact() {
    document.body.classList.toggle("dashboard-activities-compact");
  }

  function goTo(path) {
    window.location.href = path;
  }

  function openAttendanceRecord(id) {
    if (!id) {
      goTo("attendance.php");
      return;
    }
    goTo("attendance.php?attendance_id=" + encodeURIComponent(String(id)));
  }

  function initHomepageRuntime() {
    initOjtOverviewChart();
    initSidebarMiniMenuCollapse();
    applyProgressWidths();
  }

  window.BioTernDashboard = {
    exportAttendanceCSV: exportAttendanceCSV,
    exportRecentActivitiesCSV: exportRecentActivitiesCSV,
    toggleAttendanceCompact: toggleAttendanceCompact,
    toggleActivitiesCompact: toggleActivitiesCompact,
    openAttendanceRecord: openAttendanceRecord,
    goToAttendance: function () {
      goTo("attendance.php");
    },
    goToStudents: function () {
      goTo("students.php");
    },
    goToApplicationsReview: function () {
      goTo("applications-review.php");
    },
    goToReports: function () {
      goTo("reports-timesheets.php");
    },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHomepageRuntime);
  } else {
    initHomepageRuntime();
  }
})();
