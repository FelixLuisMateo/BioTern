(function () {
    "use strict";

    if (!window.AppCore || !window.AppCore.TemplateEditor) {
        return;
    }

    var configNode = document.getElementById("app-editor-config");
    if (!configNode) {
        return;
    }

    var config = {};
    try {
        var raw = configNode.textContent || configNode.innerText || "{}";
        config = JSON.parse(raw);
    } catch (err) {
        config = {};
    }

    if (!config || !config.storageKey) {
        return;
    }

    var enableLogoDrag = !!config.enableLogoDrag;

    function mountLogoDrag(editor, api) {
        if (!enableLogoDrag || !window.AppCore.TemplateEditor.attachLogoDrag) {
            return;
        }
        window.AppCore.TemplateEditor.attachLogoDrag(editor, {
            setStatus: api.setStatus,
            onChange: api.saveDebounced,
            moveStatusText: config.logoDragStatusText || "Move logo, then release to save",
        });
    }

    var options = {
        storageKey: config.storageKey,
        loadMode: config.loadMode || "storage-or-default",
        defaultTemplateId: config.defaultTemplateId || "default_template",
        resetMode: config.resetMode || "storage-or-default",
        resetConfirmMessage: config.resetConfirmMessage || "",
        resetStatusMessage: config.resetStatusMessage || "",
        backHref: config.backHref || "",
        hideBrokenImagesOnError: config.hideBrokenImagesOnError === true,
        preserveSelectionOnFormat: config.preserveSelectionOnFormat === true,
        fontSizeMode: config.fontSizeMode || "",
        fetchUrl: config.fetchUrl || "",
        fetchContentSelector: config.fetchContentSelector || "",
        fetchFallbackHtml: config.fetchFallbackHtml || "",
    };

    if (enableLogoDrag) {
        options.onAfterLoad = function (editor, api) {
            mountLogoDrag(editor, api);
        };

        options.onReset = function (editor, api) {
            if (config.resetWithDefaultTemplate) {
                editor.innerHTML = api.getDefaultTemplateHtml();
            }
            mountLogoDrag(editor, api);
            if (config.saveOnReset) {
                api.save();
            }
        };
    }

    var runtime = window.AppCore.TemplateEditor.create(options);
    if (runtime) {
        runtime.init();
    }
})();
