(function () {
    "use strict";

    var docs = window.AppCore && window.AppCore.Documents ? window.AppCore.Documents : null;
    if (docs) {
        docs.bindPrintButton("btn_print_resume");
        return;
    }

    var printButton = document.getElementById("btn_print_resume");
    if (printButton) {
        printButton.addEventListener("click", function () {
            window.print();
        });
    }
})();
