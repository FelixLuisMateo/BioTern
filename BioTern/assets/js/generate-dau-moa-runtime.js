(function () {
    "use strict";

    var docs = window.AppCore && window.AppCore.Documents ? window.AppCore.Documents : null;
    if (!docs || !docs.initMoaDocument) {
        return;
    }

    docs.initMoaDocument({
        contentId: "moa_doc_content",
        storageKey: "biotern_dau_moa_template_html_v1",
        printButtonId: "btn_print_moa",
        closeButtonId: "btn_close_moa",
        fallbackHref: "documents/document_dau_moa.php",
        closeDelayMs: 80,
        normalizeColors: false,
    });
})();
