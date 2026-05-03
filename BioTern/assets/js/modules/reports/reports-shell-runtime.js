(function () {
    "use strict";

    function isMobileViewport() {
        return window.matchMedia && window.matchMedia("(max-width: 991.98px)").matches;
    }

    function handleReportPrintClick(event) {
        var button = event.target.closest(".js-print-report");
        if (!button) {
            return;
        }

        event.preventDefault();
        window.print();
    }

    function getHeaderLabels(table) {
        var labels = [];
        var headerRow = table.querySelector("thead tr");
        if (!headerRow) {
            return labels;
        }
        var headerCells = headerRow.querySelectorAll("th, td");
        headerCells.forEach(function (cell, index) {
            var text = (cell.textContent || "").trim();
            labels[index] = text || ("Column " + (index + 1));
        });
        return labels;
    }

    function initGlobalReportMobileTables() {
        if (!document.body || !document.body.classList.contains("reports-page")) {
            return;
        }

        var tables = document.querySelectorAll(".nxl-content table.table, .main-content table.table");
        if (!tables.length) {
            return;
        }

        tables.forEach(function (table) {
            if (table.closest(".modal")) {
                return;
            }
            if (table.matches("[data-report-mobile-ignore='true']")) {
                return;
            }

            var hasHead = !!table.querySelector("thead");
            var bodyRows = table.querySelectorAll("tbody tr");
            if (!hasHead || !bodyRows.length) {
                return;
            }

            table.classList.add("reports-mobile-table");
            var visibleCells = parseInt(table.getAttribute("data-mobile-visible-cells") || "3", 10);
            if (!Number.isFinite(visibleCells) || visibleCells < 1) {
                visibleCells = 3;
            }

            var labels = getHeaderLabels(table);
            bodyRows.forEach(function (row) {
                if (!row || row.querySelector("td[colspan]")) {
                    return;
                }

                var cells = Array.prototype.slice.call(row.querySelectorAll("td"));
                if (!cells.length) {
                    return;
                }

                cells.forEach(function (cell, index) {
                    if (!cell.getAttribute("data-label")) {
                        cell.setAttribute("data-label", labels[index] || ("Column " + (index + 1)));
                    }
                    if (cells.length > visibleCells && index >= visibleCells) {
                        cell.classList.add("reports-mobile-extra-cell");
                    }
                });

                if (cells.length <= visibleCells) {
                    row.classList.remove("reports-mobile-row-collapsed");
                    return;
                }

                var toggleCell = row.querySelector(".reports-mobile-collapse-cell");
                if (!toggleCell) {
                    toggleCell = document.createElement("td");
                    toggleCell.className = "reports-mobile-collapse-cell";
                    toggleCell.setAttribute("colspan", String(cells.length));

                    var btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "reports-mobile-collapse-btn";
                    btn.textContent = "Show more";
                    btn.setAttribute("aria-expanded", "false");
                    btn.addEventListener("click", function () {
                        var collapsed = row.classList.contains("reports-mobile-row-collapsed");
                        row.classList.toggle("reports-mobile-row-collapsed", !collapsed);
                        btn.setAttribute("aria-expanded", collapsed ? "true" : "false");
                        btn.textContent = collapsed ? "Show less" : "Show more";
                    });

                    toggleCell.appendChild(btn);
                    row.appendChild(toggleCell);
                }

                var toggleBtn = row.querySelector(".reports-mobile-collapse-btn");
                if (isMobileViewport()) {
                    row.classList.add("reports-mobile-row-collapsed");
                    if (toggleBtn) {
                        toggleBtn.textContent = "Show more";
                        toggleBtn.setAttribute("aria-expanded", "false");
                    }
                } else {
                    row.classList.remove("reports-mobile-row-collapsed");
                    if (toggleBtn) {
                        toggleBtn.textContent = "Show less";
                        toggleBtn.setAttribute("aria-expanded", "true");
                    }
                }
            });
        });
    }

    document.addEventListener("click", handleReportPrintClick);
    document.addEventListener("DOMContentLoaded", initGlobalReportMobileTables);
    window.addEventListener("resize", function () {
        window.clearTimeout(window.__bioternReportMobileTableResizeTimer);
        window.__bioternReportMobileTableResizeTimer = window.setTimeout(initGlobalReportMobileTables, 160);
    });
})();
