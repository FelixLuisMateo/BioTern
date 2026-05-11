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
      ".print-preview-toolbar-title{display:flex;flex-direction:column;gap:3px;min-width:0;}",
      ".print-preview-toolbar strong{font-size:14px;}",
      ".print-preview-tip{font-size:12px;line-height:1.35;color:#cbd5e1;}",
      ".print-preview-actions{display:flex;gap:8px;}",
      ".print-preview-actions button{border:0;border-radius:6px;padding:8px 14px;font-weight:700;cursor:pointer;}",
      ".print-preview-actions .print{background:#16a34a;color:#fff;}",
      ".print-preview-actions .close{background:#e5e7eb;color:#111827;}",
      ".print-preview-stage{box-sizing:border-box;min-height:calc(100vh - 58px);padding:22px;display:flex;justify-content:center;align-items:flex-start;overflow:auto;}",
      ".print-preview-stage>#editor,.print-preview-stage>.builder-editor-surface{margin:0 auto!important;}",
      ".print-preview-stage .a4-pages-stack{display:flex;flex-direction:column;align-items:center;gap:18px;}",
      ".print-preview-stage .a4-page{box-shadow:0 18px 42px rgba(15,23,42,.22);}",
      "@media(max-width:720px){.print-preview-toolbar{align-items:flex-start;flex-direction:column;}.print-preview-actions{width:100%;}.print-preview-actions button{flex:1;}}",
      "@media print{html,body{background:#fff!important;margin:0!important;padding:0!important;}.print-preview-toolbar{display:none!important;}.print-preview-stage{display:block!important;min-height:0!important;padding:0!important;overflow:visible!important;}.print-preview-stage>#editor,.print-preview-stage>.builder-editor-surface{margin:0!important;border:0!important;box-shadow:none!important;}.print-preview-stage .a4-pages-stack{display:block!important;gap:0!important;}.print-preview-stage .a4-page{box-shadow:none!important;margin:0 auto!important;page-break-after:always!important;break-after:page!important;}.print-preview-stage .a4-page:last-child{page-break-after:auto!important;break-after:auto!important;}}"
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
      "<div class=\"print-preview-toolbar\"><div class=\"print-preview-toolbar-title\"><strong>" + title.replace(/[<>]/g, "") + "</strong>" +
      "<span class=\"print-preview-tip\">Print settings: set margins to None / No margin and turn off Headers and footers.</span></div>" +
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
