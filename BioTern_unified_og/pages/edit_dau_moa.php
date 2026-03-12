<?php
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Edit DAU MOA';
$base_href = $asset_prefix;
$page_body_class = 'app-editor-page';
$page_styles = [
    'assets/css/page-editor-shell.css',
    'assets/css/edit-moa-dau-template-page.css',
];
$page_scripts = [
    'assets/js/template-editor-page-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<script type="application/json" id="app-editor-config">
{
    "storageKey": "biotern_dau_moa_template_html_v1",
    "loadMode": "storage-or-fetch",
    "resetMode": "storage-or-fetch",
    "fetchUrl": "generate_dau_moa.php?use_saved_template=0",
    "fetchContentSelector": "#moa_doc_content",
    "fetchFallbackHtml": "<p>Unable to load template.</p>"
}
</script>
<div class="main-content app-editor-main-content">
    <div class="topbar app-editor-topbar">
        <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
        <button id="btn_reset" class="btn" type="button">Reset to Generate DAU MOA</button>
        <a class="btn btn-success" href="documents/document_dau_moa.php">Back to DAU MOA</a>
        <div class="toolbar app-editor-toolbar">
            <button id="btn_bold" class="btn" type="button"><strong>B</strong></button>
            <button id="btn_italic" class="btn" type="button"><em>I</em></button>
            <button id="btn_underline" class="btn" type="button"><u>U</u></button>
            <button id="btn_indent" class="btn" type="button">Indent</button>
            <button id="btn_outdent" class="btn" type="button">Outdent</button>
            <button id="btn_left" class="btn" type="button">Left</button>
            <button id="btn_center" class="btn" type="button">Center</button>
            <button id="btn_right" class="btn" type="button">Right</button>
            <button id="btn_justify" class="btn" type="button">Justify</button>
            <label for="font_size_pt">Size</label>
            <input id="font_size_pt" type="number" min="6" max="96" step="1" value="12" title="Double-click for custom size">
            <button id="btn_apply_size" class="btn" type="button">Apply</button>
            <label for="font_color">Color</label>
            <input id="font_color" type="color" value="#000000">
        </div>
        <span id="msg" class="msg app-editor-status">Edit DAU MOA template and click Save</span>
    </div>

    <div class="page-wrap app-editor-page-wrap">
        <div class="paper app-editor-paper">
            <div id="editor" class="app-editor-canvas" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


