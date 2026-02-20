<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Edit MOA</title>
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
        <button id="btn_reset" class="btn" type="button">Reset to Generate MOA</button>
        <a class="btn btn-success" href="document_moa.php">Back to MOA</a>
        <span id="msg" class="msg">Edit MOA template and click Save</span>
    </div>

    <div class="page-wrap">
        <div class="paper">
            <div id="editor" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <script>
        (function(){
            var KEY = 'biotern_moa_template_html_v1';
            var editor = document.getElementById('editor');
            var msg = document.getElementById('msg');

            function save() {
                try {
                    localStorage.setItem(KEY, editor.innerHTML);
                    msg.textContent = 'Saved';
                } catch (err) {
                    msg.textContent = 'Save failed';
                }
            }

            function loadFromGenerate() {
                var params = new URLSearchParams();
                params.set('partner_rep', '');
                params.set('partner_position', '');
                params.set('school_rep', '');
                params.set('school_position', '');
                params.set('presence_school_admin', '');
                params.set('presence_school_admin_position', '');
                params.set('use_saved_template', '0');
                return fetch('generate_moa.php?' + params.toString(), { credentials: 'same-origin' })
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
            editor.addEventListener('input', function(){ msg.textContent = 'Unsaved changes'; });

            init();
        })();
    </script>
</body>
</html>
