(function () {
    "use strict";

    if (!window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var mountLogoDrag = function (editor, api) {
        if (!window.AppCore.TemplateEditor.attachLogoDrag) {
            return;
        }
        window.AppCore.TemplateEditor.attachLogoDrag(editor, {
            setStatus: api.setStatus,
            onChange: api.saveDebounced,
            moveStatusText: "Move logo, then release to save",
        });
    };

    var runtime = window.AppCore.TemplateEditor.create({
        storageKey: "biotern_application_template_html_v1",
        loadMode: "storage-or-default",
        defaultTemplateId: "default_template",
        resetMode: "storage-or-default",
        backHref: "documents/document_application.php",
        hideBrokenImagesOnError: true,
        preserveSelectionOnFormat: true,
        fontSizeMode: "rich-span",
        onAfterLoad: function (editor, api) {
            mountLogoDrag(editor, api);
        },
        onReset: function (editor, api) {
            editor.innerHTML = api.getDefaultTemplateHtml();
            mountLogoDrag(editor, api);
            api.save();
        },
    });

    if (runtime) {
        runtime.init();
    }
})();
