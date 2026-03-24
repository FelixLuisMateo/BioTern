(function () {
  "use strict";

  function initCertificatePrint() {
    var btn = document.querySelector(".js-print-certificate");
    if (!btn) return;
    btn.addEventListener("click", function () {
      window.print();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCertificatePrint);
  } else {
    initCertificatePrint();
  }
})();
