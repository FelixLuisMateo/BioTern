(function () {
    "use strict";

    function bindDragScroll(root) {
        if (!root || root.dataset.mobileDragBound === "1") {
            return;
        }

        var isDown = false;
        var startX = 0;
        var startScrollLeft = 0;

        root.classList.add("is-drag-scroll");
        root.dataset.mobileDragBound = "1";

        root.addEventListener("mousedown", function (event) {
            isDown = true;
            root.classList.add("is-dragging");
            startX = event.pageX - root.offsetLeft;
            startScrollLeft = root.scrollLeft;
        });

        root.addEventListener("mouseleave", function () {
            isDown = false;
            root.classList.remove("is-dragging");
        });

        root.addEventListener("mouseup", function () {
            isDown = false;
            root.classList.remove("is-dragging");
        });

        root.addEventListener("mousemove", function (event) {
            if (!isDown) {
                return;
            }
            event.preventDefault();
            var x = event.pageX - root.offsetLeft;
            var delta = (x - startX) * 1.25;
            root.scrollLeft = startScrollLeft - delta;
        });
    }

    function bindMobileCollapseToggle(button) {
        if (!button || button.dataset.mobileCollapseBound === "1") {
            return;
        }

        var targetSelector = (button.getAttribute("data-mobile-collapse-target") || "").trim();
        if (targetSelector === "") {
            return;
        }

        var target = document.querySelector(targetSelector);
        if (!target) {
            return;
        }

        button.dataset.mobileCollapseBound = "1";
        button.setAttribute("aria-expanded", target.classList.contains("is-open") ? "true" : "false");

        button.addEventListener("click", function () {
            var willOpen = !target.classList.contains("is-open");
            target.classList.toggle("is-open", willOpen);
            button.setAttribute("aria-expanded", willOpen ? "true" : "false");
        });
    }

    function hydrateMobileTableLabels(table) {
        if (!table || table.dataset.mobileLabelsHydrated === "1") {
            return;
        }

        var labels = Array.prototype.map.call(table.querySelectorAll("thead th"), function (header) {
            return (header.textContent || "").replace(/\s+/g, " ").trim();
        });

        if (!labels.length) {
            return;
        }

        table.querySelectorAll("tbody tr").forEach(function (row) {
            Array.prototype.forEach.call(row.children, function (cell, index) {
                if (cell.hasAttribute("data-label")) {
                    return;
                }

                var label = labels[index] || "";
                if (label !== "") {
                    cell.setAttribute("data-label", label);
                }
            });
        });

        table.dataset.mobileLabelsHydrated = "1";
    }

    function initMobileComponents() {
        if (!document.body || !document.body.classList.contains("mobile-bottom-nav")) {
            return;
        }

        document.querySelectorAll(".mobile-chip-row, [data-mobile-scroll]").forEach(function (node) {
            bindDragScroll(node);
        });

        document.querySelectorAll("[data-mobile-collapse-toggle]").forEach(function (button) {
            bindMobileCollapseToggle(button);
        });

        document.querySelectorAll("table[data-mobile-collapse='true']").forEach(function (table) {
            hydrateMobileTableLabels(table);
        });
    }

    if (window.BioTernRuntimeBoot && typeof window.BioTernRuntimeBoot.onReady === "function") {
        window.BioTernRuntimeBoot.onReady(initMobileComponents, {
            name: "mobile-components-runtime",
            id: "mobile-components-runtime"
        });
        return;
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initMobileComponents);
        return;
    }

    initMobileComponents();
})();
