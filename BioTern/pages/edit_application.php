<?php
$force_blank = isset($_GET['blank']) && $_GET['blank'] === '1';
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Edit Application Template';
$base_href = $asset_prefix;
$page_body_class = 'app-editor-page';
$page_styles = [
    'assets/css/layout/page-editor-shell.css',
    'assets/css/modules/documents/edit-application-template-page.css',
];
$page_scripts = [
    'assets/js/modules/documents/template-editor-page-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<script type="application/json" id="app-editor-config">
{
    "storageKey": "biotern_application_template_html_v1",
    "loadMode": "storage-or-default",
    "defaultTemplateId": "default_template",
    "resetMode": "storage-or-default",
    "backHref": "documents/document_application.php",
    "hideBrokenImagesOnError": true,
    "preserveSelectionOnFormat": true,
    "fontSizeMode": "rich-span",
    "enableLogoDrag": true,
    "logoDragStatusText": "Move logo, then release to save",
    "resetWithDefaultTemplate": true,
    "saveOnReset": true
}
</script>
<div class="main-content app-editor-main-content" data-force-blank="<?php echo $force_blank ? '1' : '0'; ?>">
    <div class="topbar app-editor-topbar">
        <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
        <button id="btn_reset" class="btn" type="button">Reset Default</button>
        <button id="btn_back" class="btn btn-success" type="button">Open Documents Page</button>
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
            <label for="font_size">Size</label>
            <input id="font_size_pt" type="number" min="6" max="96" step="1" value="12" title="Double-click to enter custom size">
            <button id="btn_apply_size" class="btn" type="button">Apply</button>
            <label for="font_color">Color</label>
            <input id="font_color" type="color" value="#000000">
        </div>
        <span id="msg" class="msg app-editor-status">Editing Application Letter paper layout</span>
    </div>

    <div class="page-wrap app-editor-page-wrap">
        <div class="paper app-editor-paper">
            <div id="editor" class="app-editor-canvas" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <template id="default_template">
        <div class="container app-application-container">
            <img class="crest app-application-crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
            <div class="header app-application-header">
                <h2 class="app-application-title">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
                <div class="meta app-application-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                <div class="tel app-application-tel">Telefax No.: (045) 624-0215</div>
            </div>
            <div class="content app-application-content">
                <h3 class="app-application-heading">Application Approval Sheet</h3>
                <p>Date: <span id="ap_date">__________</span></p>
                <p>Mr./Ms.: <span id="ap_name">__________________________</span></p>
                <p>Position: <span id="ap_position">__________________________</span></p>
                <p>Name of Company: <span id="ap_company">__________________________</span></p>
                <p>Company Address: <span id="ap_address">__________________________</span></p>

                <p class="mt-30 app-application-mt-30">Dear Sir or Madam:</p>
                <p>I am <span id="ap_student">__________________________</span>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong>250 hours</strong>.</p>
                <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>
                <p>Thank you for any consideration that you may give to this letter of application.</p>
                <p class="mt-30 app-application-mt-30">Very truly yours,</p>
                <p class="mt-40 app-application-mt-40">Student Name: <span id="ap_student_name">__________________________</span></p>
                <p>Student Home Address: <span id="ap_student_address">__________________________</span></p>
                <p>Contact No.: <span id="ap_student_contact">__________________________</span></p>
            </div>
        </div>
    </template>
</div>

</div> <!-- .nxl-content -->
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>








