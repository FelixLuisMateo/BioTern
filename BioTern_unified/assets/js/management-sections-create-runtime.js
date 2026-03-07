(function () {
    "use strict";

    function initPage() {
        var courseSelect = document.getElementById("courseSelect");
        var rangeStartInput = document.getElementById("rangeStartInput");
        var rangeEndInput = document.getElementById("rangeEndInput");

        if (!courseSelect || !rangeStartInput || !rangeEndInput) {
            return;
        }

        rangeStartInput.setAttribute("pattern", "[0-9]+[A-Z]");
        rangeEndInput.setAttribute("pattern", "[0-9]+[A-Z]");
        rangeStartInput.setAttribute("title", "Use format like 2A");
        rangeEndInput.setAttribute("title", "Use format like 2D");

        function applyCourseDefaults() {
            var selected = courseSelect.options[courseSelect.selectedIndex];
            var courseCode = selected ? (selected.getAttribute("data-course-code") || "").toUpperCase() : "";
            var yearThreeCourses = ["HTM", "HMT", "BSOA", "BSE"];
            var baseYear = yearThreeCourses.indexOf(courseCode) !== -1 ? "3" : "2";

            if (rangeStartInput.value.trim() === "") {
                rangeStartInput.value = baseYear + "A";
            }
            if (rangeEndInput.value.trim() === "") {
                rangeEndInput.value = baseYear + "Z";
            }
        }

        courseSelect.addEventListener("change", applyCourseDefaults);

        rangeStartInput.addEventListener("input", function () {
            rangeStartInput.value = rangeStartInput.value.toUpperCase();
        });

        rangeEndInput.addEventListener("input", function () {
            rangeEndInput.value = rangeEndInput.value.toUpperCase();
        });

        applyCourseDefaults();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initPage);
    } else {
        initPage();
    }
})();
