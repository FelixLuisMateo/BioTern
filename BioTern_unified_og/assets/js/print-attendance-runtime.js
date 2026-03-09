(function () {
    "use strict";

    var printButton = document.getElementById("btn_print_attendance");
    if (printButton) {
        printButton.addEventListener("click", function () {
            window.print();
        });
    }

    window.addEventListener("load", function () {
        setTimeout(function () {
            window.print();
        }, 500);
    });
})();
