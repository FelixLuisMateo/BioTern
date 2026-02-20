<?php
$force_blank = isset($_GET['blank']) && $_GET['blank'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Edit Endorsement Template</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        body { margin:0; background:#e8ebf0; font-family:Arial, sans-serif; }
        .topbar { position:sticky; top:0; z-index:10; background:#fff; border-bottom:1px solid #d8dee8; padding:10px 14px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { border:1px solid #1f2937; background:#fff; color:#111827; padding:8px 12px; cursor:pointer; border-radius:6px; font-size:13px; }
        .btn-primary { background:#2563eb; border-color:#2563eb; color:#fff; }
        .btn-success { background:#16a34a; border-color:#16a34a; color:#fff; }
        .msg { margin-left:auto; font-size:12px; color:#374151; }
        .page-wrap { padding:18px; display:flex; justify-content:center; }
        .paper { width:8.5in; min-height:11in; background:#fff; box-shadow:0 8px 24px rgba(0,0,0,.18); padding:0.5in; box-sizing:border-box; }
        #editor { min-height:calc(11in - 1in); outline:none; font-family:"Times New Roman", Times, serif; font-size:12pt; }
        #editor:focus { box-shadow: inset 0 0 0 2px #93c5fd; }
        .toolbar { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .toolbar .btn { padding:6px 10px; }
        .toolbar label { font-size:12px; color:#374151; }
        .toolbar input[type="number"],
        .toolbar input[type="color"] {
            border:1px solid #d1d5db;
            border-radius:6px;
            padding:4px 6px;
            background:#fff;
            font-size:12px;
        }
        .toolbar input[type="number"] { width:72px; }
    </style>
</head>
<body>
    <div class="topbar">
        <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
        <button id="btn_reset" class="btn" type="button">Reset Default</button>
        <button id="btn_back" class="btn btn-success" type="button">Open Documents Page</button>
        <div class="toolbar">
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
        <span id="msg" class="msg">Editing Endorsement Letter layout</span>
    </div>

    <div class="page-wrap">
        <div class="paper">
            <div id="editor" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <template id="default_template">
        <div class="header" style="position:relative; min-height:72px; text-align:center; border-bottom:1px solid #8ab0e6; padding:8px 0 6px; margin-bottom:10px;">
            <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" style="position:absolute; left:6px; top:2px; width:70px; height:70px; object-fit:contain;" onerror="this.style.display='none'">
            <p style="font-family:Calibri,Arial,sans-serif;color:#1b4f9c;font-size:20px;margin:0;font-weight:700;">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
            <div style="font-family:Calibri,Arial,sans-serif;color:#1b4f9c;font-size:14px;">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
            <div style="font-family:Calibri,Arial,sans-serif;color:#1b4f9c;font-size:14px;">Telefax No.: (045) 624-0215</div>
        </div>
        <div class="content" style="font-family:'Times New Roman',Times,serif; font-size:12pt; line-height:1.45;">
            <h3 style="text-align:center; margin:8px 0 12px;">ENDORSEMENT LETTER</h3>
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
            <p style="margin-top:40px;"><strong>MR. JOMAR G. SANGIL</strong><br><strong>ICT DEPARTMENT HEAD</strong><br><strong>Clark College of Science and Technology</strong></p>
        </div>
    </template>

    <script>
    (function(){
        const KEY = 'biotern_endorsement_template_html_v1';
        const FORCE_BLANK = <?php echo $force_blank ? 'true' : 'false'; ?>;
        const editor = document.getElementById('editor');
        const msg = document.getElementById('msg');
        const defaultHtml = document.getElementById('default_template').innerHTML.trim();
        let saveTimer = null;
        let savedRange = null;

        function load() {
            const saved = localStorage.getItem(KEY);
            editor.innerHTML = saved && saved.trim() ? saved : defaultHtml;
        }
        function save() {
            try {
                localStorage.setItem(KEY, editor.innerHTML);
                msg.textContent = 'Saved';
            } catch (e) {
                msg.textContent = 'Save failed';
            }
        }
        function saveDebounced() {
            msg.textContent = 'Unsaved changes';
            if (saveTimer) clearTimeout(saveTimer);
            saveTimer = setTimeout(save, 600);
        }
        function saveSelection() {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return;
            savedRange = range.cloneRange();
        }
        function restoreSelection() {
            if (!savedRange) return;
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
        function format(cmd, value) {
            restoreSelection();
            editor.focus();
            document.execCommand('styleWithCSS', false, true);
            document.execCommand(cmd, false, value || null);
            saveDebounced();
        }
        function applyFontSizePt(ptValue) {
            const pt = parseFloat(ptValue);
            if (!Number.isFinite(pt) || pt < 6 || pt > 96) return;
            restoreSelection();
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return;
            if (range.collapsed) return;
            const wrapper = document.createElement('span');
            wrapper.style.fontSize = pt + 'pt';
            try {
                range.surroundContents(wrapper);
            } catch (err) {
                const frag = range.extractContents();
                wrapper.appendChild(frag);
                range.insertNode(wrapper);
            }
            const newRange = document.createRange();
            newRange.selectNodeContents(wrapper);
            sel.removeAllRanges();
            sel.addRange(newRange);
            savedRange = newRange.cloneRange();
            saveDebounced();
        }

        document.getElementById('btn_save').addEventListener('click', save);
        document.getElementById('btn_reset').addEventListener('click', function(){
            if (!confirm('Reset endorsement template to default?')) return;
            localStorage.removeItem(KEY);
            editor.innerHTML = defaultHtml;
            msg.textContent = 'Reset to default';
        });
        document.getElementById('btn_back').addEventListener('click', function(){
            window.location.href = 'document_endorsement.php';
        });

        ['btn_bold','btn_italic','btn_underline','btn_indent','btn_outdent','btn_left','btn_center','btn_right','btn_justify','btn_apply_size']
            .forEach(function(id){
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('mousedown', function(e){ e.preventDefault(); });
            });
        document.getElementById('btn_bold').addEventListener('click', function(){ format('bold'); });
        document.getElementById('btn_italic').addEventListener('click', function(){ format('italic'); });
        document.getElementById('btn_underline').addEventListener('click', function(){ format('underline'); });
        document.getElementById('btn_indent').addEventListener('click', function(){ format('indent'); });
        document.getElementById('btn_outdent').addEventListener('click', function(){ format('outdent'); });
        document.getElementById('btn_left').addEventListener('click', function(){ format('justifyLeft'); });
        document.getElementById('btn_center').addEventListener('click', function(){ format('justifyCenter'); });
        document.getElementById('btn_right').addEventListener('click', function(){ format('justifyRight'); });
        document.getElementById('btn_justify').addEventListener('click', function(){ format('justifyFull'); });
        document.getElementById('font_color').addEventListener('input', function(e){ format('foreColor', e.target.value); });
        document.getElementById('font_color').addEventListener('click', function(){ restoreSelection(); });
        document.getElementById('btn_apply_size').addEventListener('click', function(){
            applyFontSizePt(document.getElementById('font_size_pt').value);
        });
        document.getElementById('font_size_pt').addEventListener('dblclick', function(e){
            const typed = window.prompt('Enter font size in pt (6-96):', e.target.value || '12');
            if (typed === null) return;
            e.target.value = typed;
            applyFontSizePt(typed);
        });
        document.getElementById('font_size_pt').addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFontSizePt(e.target.value);
            }
        });

        editor.addEventListener('input', saveDebounced);
        editor.addEventListener('mouseup', saveSelection);
        editor.addEventListener('keyup', saveSelection);
        document.addEventListener('selectionchange', saveSelection);
        window.addEventListener('beforeunload', save);

        load();
    })();
    </script>
</body>
</html>
