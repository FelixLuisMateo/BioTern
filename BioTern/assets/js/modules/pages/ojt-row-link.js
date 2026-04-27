(function () {
  "use strict";

  function isInteractive(target) {
    return !!(
      target &&
      target.closest(
        "a, button, input, select, textarea, label, [data-print-exclude='1']"
      )
    );
  }

  document.addEventListener("click", function (event) {
    var row = event.target && event.target.closest("tr[data-row-href]");
    if (!row || isInteractive(event.target)) return;
    var href = row.getAttribute("data-row-href");
    if (href) {
      window.location.href = href;
    }
  });
})();
