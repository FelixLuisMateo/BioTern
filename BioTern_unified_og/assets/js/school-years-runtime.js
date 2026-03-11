(function () {
  "use strict";

  function initConfirmLinks() {
    document.querySelectorAll(".js-confirm-action").forEach(function (link) {
      link.addEventListener("click", function (event) {
        var message = link.getAttribute("data-confirm") || "Are you sure?";
        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initConfirmLinks);
  } else {
    initConfirmLinks();
  }
})();
