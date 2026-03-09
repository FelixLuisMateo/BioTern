(function () {
    "use strict";

    function initSelects() {
        if (!window.jQuery) {
            return;
        }
        ["#filter-course", "#filter-department", "#filter-section", "#filter-status"].forEach(function (selector) {
            if (window.jQuery(selector).length) {
                window.jQuery(selector).select2({
                    width: "100%",
                    allowClear: false,
                    dropdownAutoWidth: false,
                    minimumResultsForSearch: Infinity
                });
            }
        });
    }

    function initFilters() {
        var filterForm = document.getElementById("sectionsFilterForm");
        var searchInput = document.getElementById("filter-q");
        var timer;

        function submitFilters() {
            if (filterForm) {
                filterForm.submit();
            }
        }

        function debounceSubmit() {
            clearTimeout(timer);
            timer = setTimeout(submitFilters, 350);
        }

        ["filter-course", "filter-department", "filter-section", "filter-status"].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener("change", submitFilters);
            }
        });

        if (searchInput) {
            searchInput.addEventListener("input", debounceSubmit);
        }

        if (!window.jQuery) {
            return;
        }

        ["#filter-course", "#filter-department", "#filter-section", "#filter-status"].forEach(function (selector) {
            if (window.jQuery(selector).length) {
                window.jQuery(selector).on("select2:select select2:clear", submitFilters);
            }
        });
    }

    function initPage() {
        initSelects();
        initFilters();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initPage);
    } else {
        initPage();
    }
})();
