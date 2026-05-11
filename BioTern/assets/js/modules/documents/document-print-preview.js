(function () {
  "use strict";

  function collectStyles() {
    return Array.prototype.slice
      .call(document.querySelectorAll('link[rel="stylesheet"], style'))
      .map(function (node) {
        return node.outerHTML;
      })
      .join("\n");
  }

  function previewCss() {
    return [
      "html,body{background:#eef1f6;margin:0;padding:0;color:#111827;font-family:Arial,sans-serif;}",
      ".print-preview-toolbar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;background:#111827;color:#fff;box-shadow:0 8px 24px rgba(15,23,42,.22);}",
      ".print-preview-toolbar strong{font-size:14px;}",
      ".print-preview-actions{display:flex;gap:8px;}",
      ".print-preview-actions button{border:0;border-radius:6px;padding:8px 14px;font-weight:700;cursor:pointer;}",
      ".print-preview-actions .print{background:#16a34a;color:#fff;}",
      ".print-preview-actions .close{background:#e5e7eb;color:#111827;}",
      ".print-preview-stage{padding:22px;}",
      ".print-preview-stage .a4-pages-stack{display:flex;flex-direction:column;align-items:center;gap:18px;}",
      ".print-preview-stage .a4-page{box-shadow:0 18px 42px rgba(15,23,42,.22);}",
      "@media print{html,body{background:#fff!important;margin:0!important;padding:0!important;}.print-preview-toolbar{display:none!important;}.print-preview-stage{padding:0!important;}.print-preview-stage .a4-pages-stack{display:block!important;gap:0!important;}.print-preview-stage .a4-page{box-shadow:none!important;margin:0!important;page-break-after:always!important;break-after:page!important;}.print-preview-stage .a4-page:last-child{page-break-after:auto!important;break-after:auto!important;}}"
    ].join("");
  }

  function open(options) {
    options = options || {};
    var source = options.element || document.querySelector(options.selector || "");
    if (!source) {
      window.print();
      return null;
    }

    var title = options.title || document.title || "Document Preview";
    var bodyClass = options.bodyClass || document.body.className || "";
    var html =
      "<!doctype html><html><head><meta charset=\"utf-8\">" +
      "<title>" + title.replace(/[<>]/g, "") + "</title>" +
      "<base href=\"" + document.baseURI.replace(/"/g, "&quot;") + "\">" +
      collectStyles() +
      "<style>" + previewCss() + "</style>" +
      "</head><body class=\"" + String(bodyClass).replace(/"/g, "&quot;") + "\">" +
      "<div class=\"print-preview-toolbar\"><strong>" + title.replace(/[<>]/g, "") + "</strong>" +
      "<div class=\"print-preview-actions\"><button class=\"print\" type=\"button\" onclick=\"window.print()\">Print</button>" +
      "<button class=\"close\" type=\"button\" onclick=\"window.close()\">Close</button></div></div>" +
      "<main class=\"print-preview-stage\">" + source.outerHTML + "</main>" +
      "</body></html>";

    var win = window.open("", "_blank");
    if (!win) {
      window.print();
      return null;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
    return win;
  }

  window.BioTernDocumentPrintPreview = { open: open };
})();
