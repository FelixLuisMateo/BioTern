(function () {
    "use strict";

    function handleReportPrintClick(event) {
        var button = event.target.closest(".js-print-report");
        if (!button) {
            return;
        }

        event.preventDefault();
        window.print();
    }

    document.addEventListener("click", handleReportPrintClick);
})();
