<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Edit DAU MOA</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        body { margin: 0; background: #eceff3; font-family: Arial, sans-serif; color: #111; }
        .topbar {
            position: sticky; top: 0; z-index: 1000;
            background: #fff; border-bottom: 1px solid #d8dee8;
            padding: 10px 14px; display: flex; gap: 8px; align-items: center;
        }
        .btn { border: 1px solid #1f2937; background: #fff; color: #111827; padding: 8px 12px; cursor: pointer; border-radius: 6px; font-size: 13px; text-decoration: none; }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .btn-success { background: #16a34a; border-color: #16a34a; color: #fff; }
        .msg { margin-left: auto; font-size: 12px; color: #374151; }
        .toolbar { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .toolbar .btn { padding: 6px 10px; }
        .toolbar label { font-size: 12px; color: #374151; }
        .toolbar input[type="number"],
        .toolbar input[type="color"] {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 4px 6px;
            background: #fff;
            font-size: 12px;
        }
        .toolbar input[type="number"] { width: 72px; }
        .page-wrap { padding: 18px; display: flex; justify-content: center; }
        .paper {
            width: 210mm; min-height: 297mm; background: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,.18); padding: 0.35in 1in 1in 1in; box-sizing: border-box;
        }
        #editor { min-height: 250mm; outline: none; font-family: "Arial Narrow", Arial, sans-serif; font-size: 12pt; }
        #editor:focus { box-shadow: inset 0 0 0 2px #93c5fd; }
        #editor h4 { text-align: center; margin: 5px 0; font-size: 14pt; font-weight: 700; }
        #editor p, #editor li { font-size: 12pt; line-height: 1.3; }
        #editor ol { margin-top: 6px; }
        #editor .row { display: flex; justify-content: space-between; gap: 12px; }
        #editor .col { flex: 1; }
        #editor .right { text-align: right; }
    </style>
</head>
<body>
    <div class="topbar">
        <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
        <button id="btn_reset" class="btn" type="button">Reset to Generate DAU MOA</button>
        <a class="btn btn-success" href="document_dau_moa.php">Back to DAU MOA</a>
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
        <span id="msg" class="msg">Edit DAU MOA template and click Save</span>
    </div>

    <div class="page-wrap">
        <div class="paper">
            <div id="editor" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <script>
        (function(){
            var KEY = 'biotern_dau_moa_template_html_v1';
            var editor = document.getElementById('editor');
            var msg = document.getElementById('msg');
            var saveTimer = null;
            var savedRange = null;

            function save() {
                try {
                    localStorage.setItem(KEY, editor.innerHTML);
                    msg.textContent = 'Saved';
                } catch (err) {
                    msg.textContent = 'Save failed';
                }
            }

            function saveDebounced() {
                msg.textContent = 'Unsaved changes';
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(save, 600);
            }

            function saveSelection() {
                var sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return;
                var range = sel.getRangeAt(0);
                if (!editor.contains(range.commonAncestorContainer)) return;
                savedRange = range.cloneRange();
            }

            function restoreSelection() {
                if (!savedRange) return;
                var sel = window.getSelection();
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
                var pt = parseFloat(ptValue);
                if (!Number.isFinite(pt) || pt < 6 || pt > 96) return;
                restoreSelection();
                var sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return;
                var range = sel.getRangeAt(0);
                if (!editor.contains(range.commonAncestorContainer)) return;
                if (range.collapsed) return;
                var wrapper = document.createElement('span');
                wrapper.style.fontSize = pt + 'pt';
                try {
                    range.surroundContents(wrapper);
                } catch (err) {
                    var frag = range.extractContents();
                    wrapper.appendChild(frag);
                    range.insertNode(wrapper);
                }
                var newRange = document.createRange();
                newRange.selectNodeContents(wrapper);
                sel.removeAllRanges();
                sel.addRange(newRange);
                savedRange = newRange.cloneRange();
                saveDebounced();
            }

            function loadFromGenerate() {
                var params = new URLSearchParams();
                // Keep generate_dau_moa.php defaults by not forcing empty query values.
                params.set('use_saved_template', '0');
                return fetch('generate_dau_moa.php?' + params.toString(), { credentials: 'same-origin' })
                    .then(function(r){ return r.text(); })
                    .then(function(html){
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var source = doc.getElementById('moa_doc_content');
                        return source && source.innerHTML ? source.innerHTML : '<p>Unable to load template.</p>';
                    })
                    .catch(function(){ return '<p>Unable to load template.</p>'; });
            }

            function init() {
                try {
                    var saved = localStorage.getItem(KEY);
                    if (saved && saved.trim()) {
                        editor.innerHTML = saved;
                        return;
                    }
                } catch (err) {}
                loadFromGenerate().then(function(html){ editor.innerHTML = html; });
            }

            document.getElementById('btn_save').addEventListener('click', save);
            document.getElementById('btn_reset').addEventListener('click', function(){
                loadFromGenerate().then(function(html){
                    editor.innerHTML = html;
                    save();
                });
            });
            ['btn_bold','btn_italic','btn_underline','btn_indent','btn_outdent','btn_left','btn_center','btn_right','btn_justify','btn_apply_size']
                .forEach(function(id){
                    var el = document.getElementById(id);
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
                var typed = window.prompt('Enter font size in pt (6-96):', e.target.value || '12');
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

            init();
        })();
    </script>
</body>
</html>

