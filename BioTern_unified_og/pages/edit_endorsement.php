<?php
$force_blank = isset($_GET['blank']) && $_GET['blank'] === '1';
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Edit Endorsement Template';
$base_href = $asset_prefix;
$page_body_class = 'app-editor-page';
$page_styles = [
    'assets/css/page-editor-shell.css',
    'assets/css/edit-endorsement-template-page.css',
];
$page_scripts = [
    'assets/js/template-editor-page-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<script type="application/json" id="app-editor-config">
{
    "storageKey": "biotern_endorsement_template_html_v1",
    "loadMode": "storage-or-default",
    "defaultTemplateId": "default_template",
    "resetMode": "storage-or-default",
    "resetConfirmMessage": "Reset endorsement template to default?",
    "resetStatusMessage": "Reset to default",
    "backHref": "documents/document_endorsement.php",
    "hideBrokenImagesOnError": true
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
            <label for="font_size_pt">Size</label>
            <input id="font_size_pt" type="number" min="6" max="96" step="1" value="12" title="Double-click for custom size">
            <button id="btn_apply_size" class="btn" type="button">Apply</button>
            <label for="font_color">Color</label>
            <input id="font_color" type="color" value="#000000">
        </div>
        <span id="msg" class="msg app-editor-status">Editing Endorsement Letter layout</span>
    </div>

    <div class="page-wrap app-editor-page-wrap">
        <div class="paper app-editor-paper">
            <div id="editor" class="app-editor-canvas" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <template id="default_template">
        <div class="header endorsement-doc-header app-endorsement-doc-header">
            <img class="crest endorsement-doc-crest app-endorsement-doc-crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
            <p class="endorsement-doc-title app-endorsement-doc-title">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
            <div class="endorsement-doc-meta app-endorsement-doc-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
            <div class="endorsement-doc-meta app-endorsement-doc-meta">Telefax No.: (045) 624-0215</div>
        </div>
        <div class="content endorsement-doc-content app-endorsement-doc-content">
            <h3 class="endorsement-doc-heading app-endorsement-doc-heading">ENDORSEMENT LETTER</h3>
            <p><strong id="ed_recipient">__________________________</strong><br>
            <span id="ed_position">__________________________</span><br>
            <span id="ed_company">__________________________</span><br>
            <span id="ed_company_address">__________________________</span></p>
            <p>Dear Ma'am,</p>
            <p>Greetings from Clark College of Science and Technology!</p>
            <p>We are pleased to introduce our Associate in Computer Technology program, designed to promote student success by developing competencies in core Information Technology disciplines. Our curriculum emphasizes practical experience through internships and on-the-job training, fostering a strong foundation in current industry practices.</p>
            <p>In this regard, we are seeking your esteemed company's support in accommodating the following students:</p>
            <ul><li id="ed_students">__________________________</li></ul>
            <p>These students are required to complete 250 training hours. We believe that your organization can provide them with invaluable knowledge and skills, helping them to maximize their potential for future careers in IT.</p>
            <p>Our teacher-in-charge will coordinate with you to monitor the students' progress and performance.</p>
            <p>We look forward to a productive partnership with your organization. Thank you for your consideration and support.</p>
            <p>Sincerely,</p>
            <p class="endorsement-doc-signoff app-endorsement-doc-signoff"><strong>MR. JOMAR G. SANGIL</strong><br><strong>ICT DEPARTMENT HEAD</strong><br><strong>Clark College of Science and Technology</strong></p>
        </div>
    </template>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

