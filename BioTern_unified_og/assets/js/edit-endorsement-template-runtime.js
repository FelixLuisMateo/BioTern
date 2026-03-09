(function () {
    "use strict";

    if (!window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var runtime = window.AppCore.TemplateEditor.create({
        storageKey: "biotern_endorsement_template_html_v1",
        loadMode: "storage-or-default",
        defaultTemplateId: "default_template",
        resetMode: "storage-or-default",
        resetConfirmMessage: "Reset endorsement template to default?",
        resetStatusMessage: "Reset to default",
        backHref: "documents/document_endorsement.php",
        hideBrokenImagesOnError: true,
    });

    if (runtime) {
        runtime.init();
    }
})();
