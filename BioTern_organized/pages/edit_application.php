<?php
$force_blank = isset($_GET['blank']) && $_GET['blank'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Edit Application Template</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        body { margin: 0; background: #e8ebf0; font-family: Arial, sans-serif; }
        .topbar { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 1px solid #d8dee8; padding: 10px 14px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn { border: 1px solid #1f2937; background: #fff; color: #111827; padding: 8px 12px; cursor: pointer; border-radius: 6px; font-size: 13px; }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .btn-success { background: #16a34a; border-color: #16a34a; color: #fff; }
        .msg { margin-left: auto; font-size: 12px; color: #374151; }
        .page-wrap { padding: 18px; display: flex; justify-content: center; }
        .paper {
            width: 8.5in;
            min-height: 11in;
            background: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            padding: 0.5in;
            box-sizing: border-box;
            position: relative;
            font-family: "Times New Roman", Times, serif;
            color: #111;
            font-size: 11pt;
        }
        #editor { min-height: calc(11in - 1in); outline: none; }
        #editor:focus { box-shadow: inset 0 0 0 2px #93c5fd; }
        #editor .container { width: 100%; max-width: 7.5in; margin: 0 auto; box-sizing: border-box; position: relative; }
        #editor .crest { position: absolute; top: 0.22in; left: 0.22in; width: 0.77in; height: 0.76in; object-fit: contain; cursor: grab; user-select:none; -webkit-user-drag:none; }
        #editor .crest.dragging { cursor: grabbing; }
        #editor .header {
            position: relative;
            min-height: 0.9in;
            text-align: center;
            border-bottom: 1px solid #8ab0e6;
            padding: 0.08in 0 0.06in 0;
            margin-bottom: 10px;
        }
        #editor .header h2 { font-family: Calibri, Arial, sans-serif; color: #1b4f9c; font-size: 14pt; margin: 6px 0 2px 0; }
        #editor .header .meta { font-family: Calibri, Arial, sans-serif; color: #1b4f9c; font-size: 10pt; }
        #editor .header .tel { font-family: Calibri, Arial, sans-serif; color: #1b4f9c; font-size: 12pt; }
        #editor .content { margin-top: 8px; line-height: 1.45; font-size: 12pt; font-family: "Times New Roman", Times, serif; }
        #editor h3 { font-size: 13pt; margin: 6px 0; text-align: center; line-height: 1.2; }
        .toolbar { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .toolbar .btn { padding: 6px 10px; }
        .toolbar label { font-size: 12px; color: #374151; }
        .toolbar select, .toolbar input[type="color"] {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 4px 6px;
            background: #fff;
            font-size: 12px;
        }
        .toolbar input[type="number"] {
            width: 72px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 4px 6px;
            background: #fff;
            font-size: 12px;
        }
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
            <label for="font_size">Size</label>
            <input id="font_size_pt" type="number" min="6" max="96" step="1" value="12" title="Double-click to enter custom size">
            <button id="btn_apply_size" class="btn" type="button">Apply</button>
            <label for="font_color">Color</label>
            <input id="font_color" type="color" value="#000000">
        </div>
        <span id="msg" class="msg">Editing Application Letter paper layout</span>
    </div>

    <div class="page-wrap">
        <div class="paper">
            <div id="editor" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <template id="default_template">
        <div class="container">
            <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
            <div class="header">
                <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
                <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                <div class="tel">Telefax No.: (045) 624-0215</div>
            </div>
            <div class="content">
                <h3>Application Approval Sheet</h3>
                <p>Date: <span id="ap_date">__________</span></p>
                <p>Mr./Ms.: <span id="ap_name">__________________________</span></p>
                <p>Position: <span id="ap_position">__________________________</span></p>
                <p>Name of Company: <span id="ap_company">__________________________</span></p>
                <p>Company Address: <span id="ap_address">__________________________</span></p>

                <p style="margin-top:30px;">Dear Sir or Madam:</p>
                <p>I am <span id="ap_student">__________________________</span>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong>250 hours</strong>.</p>
                <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>
                <p>Thank you for any consideration that you may give to this letter of application.</p>
                <p style="margin-top:30px;">Very truly yours,</p>
                <p style="margin-top:40px;">Student Name: <span id="ap_student_name">__________________________</span></p>
                <p>Student Home Address: <span id="ap_student_address">__________________________</span></p>
                <p>Contact No.: <span id="ap_student_contact">__________________________</span></p>
            </div>
        </div>
    </template>

    <script>
        (function(){
            const KEY = 'biotern_application_template_html_v1';
            const FORCE_BLANK = <?php echo $force_blank ? 'true' : 'false'; ?>;
            const editor = document.getElementById('editor');
            const msg = document.getElementById('msg');
            const defaultHtml = document.getElementById('default_template').innerHTML.trim();
            let saveTimer = null;
            let savedRange = null;

            function save() {
                try {
                    localStorage.setItem(KEY, editor.innerHTML);
                    msg.textContent = 'Saved';
                } catch (err) {
                    msg.textContent = 'Save failed';
                }
            }

            function load() {
                try {
                    const saved = localStorage.getItem(KEY);
                    if (FORCE_BLANK) {
                        editor.innerHTML = saved && saved.trim() ? saved : defaultHtml;
                        return;
                    }
                    editor.innerHTML = saved && saved.trim() ? saved : defaultHtml;
                } catch (err) {
                    editor.innerHTML = defaultHtml;
                }
            }

            function format(cmd, value) {
                const keepRange = savedRange ? savedRange.cloneRange() : null;
                restoreSelection();
                editor.focus();
                document.execCommand('styleWithCSS', false, true);
                document.execCommand(cmd, false, value || null);
                if (keepRange) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(keepRange);
                    savedRange = keepRange.cloneRange();
                }
                saveDebounced();
            }

            function saveDebounced() {
                msg.textContent = 'Unsaved changes';
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(save, 600);
            }

            function applyFontSizePt(ptValue) {
                const pt = parseFloat(ptValue);
                if (!Number.isFinite(pt) || pt < 6 || pt > 96) return;
                restoreSelection();
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0) return;
                const range = sel.getRangeAt(0);
                if (!editor.contains(range.commonAncestorContainer)) return;
                if (range.collapsed) {
                    const wrapper = document.createElement('span');
                    wrapper.setAttribute('data-font-size', '1');
                    wrapper.style.fontSize = pt + 'pt';
                    wrapper.appendChild(document.createTextNode('\u200b'));
                    range.insertNode(wrapper);
                    range.setStart(wrapper.firstChild, 1);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                    savedRange = range.cloneRange();
                    saveDebounced();
                    return;
                }

                // Word-like behavior: if selection is already inside one size wrapper, just update it.
                const startEl = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentElement;
                const endEl = range.endContainer.nodeType === 1 ? range.endContainer : range.endContainer.parentElement;
                const startSized = startEl && startEl.closest ? startEl.closest('span[data-font-size="1"]') : null;
                const endSized = endEl && endEl.closest ? endEl.closest('span[data-font-size="1"]') : null;
                if (startSized && endSized && startSized === endSized) {
                    startSized.style.fontSize = pt + 'pt';
                    startSized.style.removeProperty('line-height');
                    const keepRange = document.createRange();
                    keepRange.selectNodeContents(startSized);
                    sel.removeAllRanges();
                    sel.addRange(keepRange);
                    savedRange = keepRange.cloneRange();
                    saveDebounced();
                    return;
                }

                // If selection intersects existing sized spans, update them directly to avoid nested wrappers/gaps.
                const intersecting = Array.from(editor.querySelectorAll('span[data-font-size="1"]'))
                    .filter(function(node){
                        try { return range.intersectsNode(node); } catch (e) { return false; }
                    });
                if (intersecting.length) {
                    intersecting.forEach(function(node){
                        node.style.fontSize = pt + 'pt';
                        node.style.removeProperty('line-height');
                    });
                    const keepRange = document.createRange();
                    keepRange.setStartBefore(intersecting[0]);
                    keepRange.setEndAfter(intersecting[intersecting.length - 1]);
                    sel.removeAllRanges();
                    sel.addRange(keepRange);
                    savedRange = keepRange.cloneRange();
                    saveDebounced();
                    return;
                }

                // Deterministic fallback: wrap selected content directly.
                const wrapper = document.createElement('span');
                wrapper.setAttribute('data-font-size', '1');
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

            function initLogoDrag() {
                if (editor.dataset.logoDragReady === '1') return;
                editor.dataset.logoDragReady = '1';

                let dragging = false;
                let targetCrest = null;
                let targetContainer = null;
                let offsetX = 0;
                let offsetY = 0;
                const getPoint = function(evt){
                    if (evt.touches && evt.touches[0]) return { x: evt.touches[0].clientX, y: evt.touches[0].clientY };
                    if (evt.changedTouches && evt.changedTouches[0]) return { x: evt.changedTouches[0].clientX, y: evt.changedTouches[0].clientY };
                    return { x: evt.clientX, y: evt.clientY };
                };
                const startDrag = function(crest, container, evt){
                    crest.setAttribute('draggable', 'false');
                    crest.setAttribute('contenteditable', 'false');
                    targetCrest = crest;
                    targetContainer = container;
                    const p = getPoint(evt);
                    const crestRect = crest.getBoundingClientRect();
                    offsetX = p.x - crestRect.left;
                    offsetY = p.y - crestRect.top;
                    dragging = true;
                    crest.classList.add('dragging');
                    document.body.style.userSelect = 'none';
                    evt.preventDefault();
                };
                const moveDrag = function(evt){
                    if (!dragging || !targetCrest || !targetContainer) return;
                    const p = getPoint(evt);
                    const rect = targetContainer.getBoundingClientRect();
                    let left = p.x - rect.left - offsetX;
                    let top = p.y - rect.top - offsetY;
                    const maxLeft = Math.max(0, rect.width - targetCrest.offsetWidth);
                    const maxTop = Math.max(0, rect.height - targetCrest.offsetHeight);
                    left = Math.max(0, Math.min(left, maxLeft));
                    top = Math.max(0, Math.min(top, maxTop));
                    targetCrest.style.left = Math.round(left) + 'px';
                    targetCrest.style.top = Math.round(top) + 'px';
                    msg.textContent = 'Move logo, then release to save';
                    evt.preventDefault();
                };
                const endDrag = function(){
                    if (!dragging) return;
                    dragging = false;
                    if (targetCrest) targetCrest.classList.remove('dragging');
                    targetCrest = null;
                    targetContainer = null;
                    document.body.style.userSelect = '';
                    saveDebounced();
                };

                const findCrest = function(target){
                    if (!target || !target.closest) return null;
                    const crest = target.closest('.crest');
                    if (!crest) return null;
                    if (!editor.contains(crest)) return null;
                    return crest;
                };

                const onStart = function(e){
                    const crest = findCrest(e.target);
                    if (!crest) return;
                    const container = crest.closest('.container');
                    if (!container) return;
                    startDrag(crest, container, e);
                };

                const onMove = function(e){
                    moveDrag(e);
                };

                const onEnd = function(){
                    endDrag();
                };

                // Use capture phase to beat contenteditable/native image handling.
                document.addEventListener('pointerdown', onStart, true);
                document.addEventListener('pointermove', onMove, true);
                document.addEventListener('pointerup', onEnd, true);
                document.addEventListener('mousedown', onStart, true);
                document.addEventListener('mousemove', onMove, true);
                document.addEventListener('mouseup', onEnd, true);
                document.addEventListener('touchstart', onStart, { passive: false, capture: true });
                document.addEventListener('touchmove', onMove, { passive: false, capture: true });
                document.addEventListener('touchend', onEnd, { passive: false, capture: true });
            }

            document.getElementById('btn_save').addEventListener('click', save);
            document.getElementById('btn_reset').addEventListener('click', function(){
                editor.innerHTML = defaultHtml;
                initLogoDrag();
                save();
            });
            document.getElementById('btn_back').addEventListener('click', function(){
                window.location.href = 'document_application.php';
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
                const current = e.target.value || '12';
                const typed = window.prompt('Enter font size in pt (6-96):', current);
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

            editor.addEventListener('input', function(){
                saveDebounced();
            });
            editor.addEventListener('mouseup', saveSelection);
            editor.addEventListener('keyup', saveSelection);
            document.addEventListener('selectionchange', saveSelection);
            window.addEventListener('beforeunload', save);

            load();
            initLogoDrag();
        })();
    </script>
</body>
</html>


