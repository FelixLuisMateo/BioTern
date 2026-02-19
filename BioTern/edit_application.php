<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Edit Application Template</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        body { margin: 0; background: #e8ebf0; font-family: Arial, sans-serif; }
        .topbar { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 1px solid #d8dee8; padding: 10px 14px; display: flex; gap: 8px; align-items: center; }
        .btn { border: 1px solid #1f2937; background: #fff; color: #111827; padding: 8px 12px; cursor: pointer; border-radius: 6px; font-size: 13px; }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .btn-success { background: #16a34a; border-color: #16a34a; color: #fff; }
        .msg { margin-left: auto; font-size: 12px; color: #374151; }
        .page-wrap { padding: 18px; display: flex; justify-content: center; }
        .paper { width: 210mm; min-height: 297mm; background: #fff; box-shadow: 0 8px 24px rgba(0,0,0,.18); padding: 1in; box-sizing: border-box; }
        #editor { min-height: 250mm; outline: none; }
        #editor:focus { box-shadow: inset 0 0 0 2px #93c5fd; }
    </style>
</head>
<body>
    <div class="topbar">
        <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
        <button id="btn_reset" class="btn" type="button">Reset Default</button>
        <button id="btn_back" class="btn btn-success" type="button">Open Documents Page</button>
        <span id="msg" class="msg">Editing Application Letter paper layout</span>
    </div>

    <div class="page-wrap">
        <div class="paper">
            <div id="editor" contenteditable="true" spellcheck="true"></div>
        </div>
    </div>

    <template id="default_template">
        <p><strong>Application Approval Sheet</strong></p>
        <p>Date: <span id="ap_date">__________</span></p>
        <p>Mr./Ms.: <span id="ap_name">__________________________</span></p>
        <p>Position: <span id="ap_position">__________________________</span></p>
        <p>Name of Company: <span id="ap_company">__________________________</span></p>
        <p>Company Address: <span id="ap_address">__________________________</span></p>

        <p>Dear Sir or Madam:</p>
        <p>I am <span id="ap_student">__________________________</span> student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong>250 hours</strong>.</p>

        <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>
        <p>Thank you for any consideration that you may give to this letter of application.</p>
        <p>Very truly yours,</p>

        <p>Student Name: <span id="ap_student_name">__________________________</span></p>
        <p>Student Home Address: <span id="ap_student_address">__________________________</span></p>
        <p>Contact No.: <span id="ap_student_contact">__________________________</span></p>
    </template>

    <script>
        (function(){
            const KEY = 'biotern_application_template_html_v1';
            const editor = document.getElementById('editor');
            const msg = document.getElementById('msg');
            const defaultHtml = document.getElementById('default_template').innerHTML.trim();

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
                    editor.innerHTML = saved && saved.trim() ? saved : defaultHtml;
                } catch (err) {
                    editor.innerHTML = defaultHtml;
                }
            }

            document.getElementById('btn_save').addEventListener('click', save);
            document.getElementById('btn_reset').addEventListener('click', function(){
                editor.innerHTML = defaultHtml;
                save();
            });
            document.getElementById('btn_back').addEventListener('click', function(){
                window.location.href = 'document_application.php';
            });
            editor.addEventListener('input', function(){ msg.textContent = 'Unsaved changes'; });

            load();
        })();
    </script>
</body>
</html>
