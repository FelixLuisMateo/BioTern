<?php
// Generate printable Memorandum of Agreement (MOA)
function q($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$print_date = q('date', date('F j, Y'));
$partner_name = q('partner_name', '__________________________');
$partner_rep = q('partner_rep', '__________________________');
$partner_position = q('partner_position', '__________________________');
$partner_address = q('partner_address', '__________________________');
$school_rep = q('school_rep', 'MR. JOMAR G. SANGIL');

$signed_at = q('signed_at', '__________________');
$signed_day = q('signed_day', '_____');
$signed_month = q('signed_month', '__________________');
$signed_year = q('signed_year', '20__');

$presence_partner_rep = q('presence_partner_rep', '______________________________');
$presence_school_admin = q('presence_school_admin', 'Mr. Ross Carvel Ramirez');

$notary_city = q('notary_city', '__________________');
$notary_appeared_1 = q('notary_appeared_1', '__________________');
$notary_appeared_2 = q('notary_appeared_2', '__________________');
$notary_day = q('notary_day', '_____');
$notary_month = q('notary_month', '__________________');
$notary_year = q('notary_year', '20___');
$notary_place = q('notary_place', '__________________');

$doc_no = q('doc_no', '______');
$page_no = q('page_no', '_____');
$book_no = q('book_no', '_____');
$series_no = q('series_no', '_____');
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>BioTern || Memorandum of Agreement</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        @page { size: A4 portrait; margin: 1in; }
        html, body { margin: 0; padding: 0; color: #111; }
        body { font-family: "Arial Narrow", Arial, sans-serif; font-size: 12pt; background: #eceff3; }
        .container {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            box-sizing: border-box;
            position: relative;
            padding: 1in;
            background: #fff;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.14);
        }
        .header {
            text-align: center;
            margin-top: -80px;
            margin-bottom: 14px;
            border-bottom: 1px solid #9ec1ea;
            padding-bottom: 8px;
            padding-left: 0.95in;
            position: relative;
            min-height: 0.9in;
        }
        .crest {
            position: absolute;
            top: 0;
            left: 0;
            width: 0.82in;
            height: 0.81in;
            object-fit: contain;
        }
        .header .school-name {
            margin-top: 10px;
            margin: 0;
            font-family: "Century Gothic", "CenturyGothic", Arial, sans-serif;
            font-size: 14pt;
            font-weight: 700;
            color: #000;
            text-align: center;
        }
        .header .school-former {
            margin: 2px 0 0;
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            font-style: italic;
            text-align: center;
        }
        .header .school-address {
            margin: 2px 0 0;
            font-family: "Century Gothic", "CenturyGothic", Arial, sans-serif;
            font-size: 10.5pt;
            color: #000;
            text-align: center;
        }
        .header .school-tel {
            margin: 2px 0 0;
            font-family: "Century Gothic", "CenturyGothic", Arial, sans-serif;
            font-size: 10.5pt;
            color: #000;
            text-align: center;
        }
        .doc { font-family: "Arial Narrow", Arial, sans-serif; font-size: 12pt; }
        .doc h4 { text-align:center; margin:5px 0; font-size: 14pt; font-weight: 700; }
        p, li { font-size:12pt; line-height:1.3; }
        ol { margin-top:6px; }
        .row { display:flex; justify-content:space-between; gap:12px; }
        .col { flex:1; }
        .right { text-align:right; }
        .actions { margin-top:18px; display:flex; gap:8px; flex-wrap:wrap; }
        .btn { border:1px solid #333; background:#fff; padding:8px 12px; cursor:pointer; }
        @media print {
            body { background: #fff; }
            .container {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                background: #fff;
            }
            .header { margin-top: 0; }
            .crest { top: 0; left: 0; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
        <p class="school-name">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
        <p class="school-former">(formerly Clark International College of Science &amp; Technology)</p>
        <p class="school-address">SNS Bldg., Aurea St., Samsonville Subd., Dau, Mabalacat City, Pampanga</p>
        <p class="school-tel">Telefax No.: (045) 624-0215</p>
    </div>

    <div class="doc">
        <h4>Memorandum of Agreement</h4>
        <p>
            This Memorandum of Agreement made and executed between: <strong><u>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</u></strong> a Higher Education Institution, duly organized and existing under Philippine Laws with office/business address at <strong><u>AUREA ST. SAMSONVILLE, DAU MABALACAT CITY PAMPANGA</u></strong> represented here in by <strong><u>MR. JOMAR G. SANGIL (IT, DEPARTMENT HEAD),</u></strong> here in after referred to as the Higher Education Institution.
        </p>
        <p style="text-align:center; margin-top:-20px; margin-bottom:5px;">
            And 
        </p>
        <strong><?php echo htmlspecialchars($partner_name); ?></strong> an enterprise duly organized and existing under Philippine Laws with office/business address at <strong><u><?php echo htmlspecialchars($partner_address); ?></u></strong> represented herein by <strong><u><?php echo htmlspecialchars($partner_rep); ?></u></strong> here in after referred to as the PARTNER COMPANY.
        <p style="text-align:center; margin-top:-10px; margin-bottom:-10px;">Witnesseth:</p>
        <p>The parties hereby bind themselves to undertake a Memorandum of Agreement for the purpose of supporting the HEI Internship for Learners under the following terms and condition:</p>
        <p><strong>Clark College of Science and Technology:</strong></p>
        <ol>
            <li>The <b>Clark College of Science and Technology</b> shall be responsible for briefing the Learners as part of the HEI’s and Job Induction Program;</li>
            <li>The <b>Clark College of Science and Technology</b> shall provide the learner undergoing the INTERNSHIP with the basic orientation on work values, behavior, and discipline to ensure smooth cooperation with the <strong><u>PARTNER COMPANY</u></strong>.</li>
            <li>The <b>Clark College of Science and Technology</b> shall issue and official endorsement vouching for the well-being of the Learner which shall be used by the <strong><u>PARTNER COMPANY</u></strong>. for the processing the learner’s application for INTERNSHIP;</li>
            <li>The <b>Clark College of Science and Technology</b> shall voluntarily withdraw a Learner who is found to misbehave and/or act in defiance to existing standards, rules, and regulation of the <strong><u>PARTNER COMPANY</u></strong> can impose necessary HEI sanctions to the said learner;</li>
            <li>The <b>Clark College of Science and Technology</b> through its Industry Coordinator shall make onsite sit/follow ups to the <strong><u>PARTNER COMPANY</u></strong> during the training period and evaluate the Learner’s progress based on the training plan and discuss training problems;</li>
            <li>The <b>Clark College of Science and Technology</b> has the discretion to pull out the Learner if there is an apparent risk and/or exploitation on the rights of the Learner;</li>
            <li>The <b>Clark College of Science and Technology</b> shall ensure that the Learner shall ensure that the Learner has an on-and off the campus insurance coverage within the duration of the training as part of their training fee.</li>
            <li>The <b>Clark College of Science and Technology</b> shall ensure Learner shall be personally responsible for any and all liabilities arising from negligence in the performance of his/her duties and functions while under INTERNSHIP;</li>
            <li>There is no employer-employee relationship between the  <strong><u>PARTNER COMPANY</u></strong> and the Learner;</li>
            <li>The duration of the program shall be equivalent to 250 working hours unless otherwise agreed upon by the <strong><u>PARTNER COMPANY</u></strong> and the <strong>Clark College of Science and Technology;</strong></li>
            <li>Any violation of the foregoing covenants will warrant the cancellation of the Memorandum of Agreement by the <strong><u>PARTNER COMPANY</u></strong> within thirty (30) days upon notice to the <strong>Clark College of Science and Technology.</strong></li>
            <li>The <strong><u>PARTNER COMPANY</u></strong> may grant allowance to Learner in accordance with the PARTNER ENTERPISE’S existing rules and regulations;</li>
            <li>The <strong><u>PARTNER COMPANY</u></strong> is not allowed to employ Learner within the INTERNSHIP period in order for the Learner to graduate from the program he/she is enrolled in.</li>
        </ol>

        <p>This Memorandum of Agreement shall become effective upon signature of both parties and Implementation will begin immediately and shall continue to be valid hereafter until written notice is given by either party thirty (30) days prior to the date of intended termination.</p>
        <p>In witness where of the parties have signed this Memorandum of Agreement at <?php echo htmlspecialchars($signed_at); ?> this <?php echo htmlspecialchars($signed_day); ?> day of <?php echo htmlspecialchars($signed_month); ?>, <?php echo htmlspecialchars($signed_year); ?>.</p>

        <div class="row" style="margin-top:-12px;">
            <div class="col">
                <p><strong>For the PARTNER COMPANY</strong></p>
                <p style="margin-top:16px;"><strong><?php echo htmlspecialchars($partner_rep); ?></strong></p>
                <p style="margin-top:-18px;"><?php echo htmlspecialchars($partner_position); echo htmlspecialchars($partner_name); ?></p>
            </div>
            <div class="col right">
                <p style="margin-right: 60px;"><strong>For the SCHOOL</strong></p>
                <p style="margin-top:16px; margin-right: 82px;"><strong><?php echo htmlspecialchars($school_rep); ?></strong></p>
                <p style="margin-top:-18px;">Head of Information Technology, CCST</p>
            </div>
        </div>

        <p style="margin-top:-5px; text-align:center;"><strong>SIGNED IN THE PRESENCE OF:</strong></p>
        <div class="row">
            <div class="col">
                <p style="margin-top:0px;"><?php echo htmlspecialchars($presence_partner_rep); ?></p>
                <p style="margin-top:-18px;">Representative of the PARTNER COMPANY</p>
            </div>
            <div class="col right">
                <p style="margin-top:0px; margin-right: 40px;"><u><?php echo htmlspecialchars($presence_school_admin); ?></u></p>
                <p style="margin-top:-18px; margin-right: 65px;">School Administrator</p>
            </div>
        </div>

        <p style="margin-top:10px;"><strong>ACKNOWLEDGEMENT</strong></p>
        <p>
            Before me, a Notary Public in the city <?php echo htmlspecialchars($notary_city); ?>, personally appeared <?php echo htmlspecialchars($notary_appeared_1); ?> and <?php echo htmlspecialchars($notary_appeared_2); ?> with Community Tax Certificates indicated above, known to me to be the same persons who executed the foregoing instrument and they acknowledged to me that the same is their free will and voluntary deed and that of the ENTERPRISEs herein represented. Witness my hand and seal on this <?php echo htmlspecialchars($notary_day); ?> day of <?php echo htmlspecialchars($notary_month); ?> <?php echo htmlspecialchars($notary_year); ?> in <?php echo htmlspecialchars($notary_place); ?>.
        </p>
        <p style="margin-bottom: -12px;">Doc No. <?php echo htmlspecialchars($doc_no); ?>:</p>
        <p style="margin-bottom: -12px;">Page No. <?php echo htmlspecialchars($page_no); ?>:</p>
        <p style="margin-bottom: -12px;">Book No. <?php echo htmlspecialchars($book_no); ?>:</p>
        <p style="margin-bottom: -12px;">Series of <?php echo htmlspecialchars($series_no); ?>:</p>
    </div>

    <div class="actions no-print">
        <button class="btn" onclick="window.print()">Print / Save PDF</button>
        <button class="btn" onclick="window.close()">Close</button>
    </div>
</div>
</body>
</html>
