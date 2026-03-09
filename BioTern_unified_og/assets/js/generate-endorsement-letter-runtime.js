(function () {
  "use strict";

  var docs = window.AppCore && window.AppCore.Documents ? window.AppCore.Documents : null;

  if (docs) {
    docs.hideBrokenImagesOnError();
  } else {
    document.addEventListener("error", function (event) {
      var target = event.target;
      if (target && target.matches && target.matches("img[data-hide-onerror='1']")) {
        target.style.display = "none";
      }
    }, true);
  }

  if (docs) {
    docs.bindPrintButton("btn_print");
    docs.bindCloseButton("btn_close", "documents/document_endorsement.php");
  } else {
    var printBtn = document.getElementById("btn_print");
    var closeBtn = document.getElementById("btn_close");

    if (printBtn) {
      printBtn.addEventListener("click", function () {
        window.print();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        if (window.opener && !window.opener.closed) {
          window.close();
          return;
        }
        if (window.history.length > 1) {
          window.history.back();
          return;
        }
        window.location.href = "documents/document_endorsement.php";
      });
    }
  }

  if (document.body && document.body.dataset.useSavedTemplate === "1") {
    try {
      if (docs) {
        docs.loadSavedTemplateHtml("biotern_endorsement_template_html_v1", "endorsement_doc_content", {
          parseContentSelector: ".content",
        });
      } else {
        var saved = localStorage.getItem("biotern_endorsement_template_html_v1");
        if (saved) {
          var temp = document.createElement("div");
          temp.innerHTML = saved;
          var content = temp.querySelector(".content") || temp;
          var out = document.getElementById("endorsement_doc_content");
          if (out && content) {
            out.innerHTML = content.innerHTML;
          }
        }
      }
    } catch (e) {
      // Keep default server-rendered content when storage access fails.
    }
  }
})();
