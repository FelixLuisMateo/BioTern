(function () {
    "use strict";

    if (!window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var runtime = window.AppCore.TemplateEditor.create({
        storageKey: "biotern_moa_template_html_v1",
        loadMode: "storage-or-fetch",
        fetchUrl: "generate_moa.php?use_saved_template=0",
        fetchContentSelector: "#moa_doc_content",
        fetchFallbackHtml: "<p>Unable to load template.</p>",
        resetMode: "storage-or-fetch",
    });

    if (runtime) {
        runtime.init();
    }
})();
