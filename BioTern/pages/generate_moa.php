<?php
// Generate printable Memorandum of Agreement (MOA)
function q($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$print_date = q('date', date('F j, Y'));
$partner_name = q('partner_name', '____________');
$partner_rep = q('partner_rep', '__________________________');
$partner_position = q('partner_position', '______________ ');
$partner_address = q('partner_address', '__________________________');
$company_receipt = q('company_receipt', '__________________________');
$total_hours = q('total_hours', '250');
$school_rep = q('school_rep', '__________________');
$school_position = q('school_position', '__________________');

$signed_at = q('signed_at', '__________________');
$signed_day = q('signed_day', '_____');
$signed_month = q('signed_month', '__________________');
$signed_year = q('signed_year', '20__');

$presence_partner_rep = q('presence_partner_rep', '______________________________');
$presence_school_admin = q('presence_school_admin', '______________________');
$presence_school_admin_position = q('presence_school_admin_position', '______________________');

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
$use_saved_template = q('use_saved_template', '0') === '1';

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Memorandum of Agreement';
$base_href = $asset_prefix;
$page_body_class = 'app-generate-page';
$page_styles = [
    'assets/css/modules/documents/generate-shell-clean.css',
    'assets/css/modules/documents/generate-moa-common-page.css',
    'assets/css/modules/documents/generate-moa-page.css',
];
$page_scripts = [
    'assets/js/modules/documents/generate-moa-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="main-content" data-use-saved-template="<?php echo $use_saved_template ? '1' : '0'; ?>">
    <div class="container app-moa-container">
        <div class="doc app-moa-doc" id="moa_doc_content">
            <h4>Memorandum of Agreement</h4>
            <p>
                This Memorandum of Agreement made and executed between: <strong><u>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</u></strong> a Higher Education Institution, duly organized and existing under Philippine Laws with office/business address at <strong><u>AUREA ST. SAMSONVILLE, DAU MABALACAT CITY PAMPANGA</u></strong> represented here in by <strong><u>MR. JOMAR G. SANGIL (IT, DEPARTMENT HEAD),</u></strong> here in after referred to as the Higher Education Institution.
            </p>
            <p class="and-line center-neg-20 app-moa-center-neg-20">
                And
            </p>
            <strong><?php echo htmlspecialchars($partner_name); ?></strong> an enterprise duly organized and existing under Philippine Laws with office/business address at <strong><u><?php echo htmlspecialchars($partner_address); ?></u></strong> represented herein by <strong><u><?php echo htmlspecialchars($partner_rep); ?></u></strong> here in after referred to as the PARTNER COMPANY.
            <p class="center-top-10-bottom-neg-10 app-moa-center-top-10-bottom-neg-10">Witnesseth:</p>
            <p>The parties hereby bind themselves to undertake a Memorandum of Agreement for the purpose of supporting the HEI Internship for Learners under the following terms and condition:</p>
            <p><strong>Clark College of Science and Technology:</strong></p>
            <ol>
                <li>The <b>Clark College of Science and Technology</b> shall be responsible for briefing the Learners as part of the HEI's and Job Induction Program;</li>
                <li>The <b>Clark College of Science and Technology</b> shall provide the learner undergoing the INTERNSHIP with the basic orientation on work values, behavior, and discipline to ensure smooth cooperation with the <strong><u>PARTNER COMPANY</u></strong>.</li>
                <li>The <b>Clark College of Science and Technology</b> shall issue and official endorsement vouching for the well-being of the Learner which shall be used by the <strong><u>PARTNER COMPANY</u></strong>. for the processing the learner's application for INTERNSHIP;</li>
                <li>The <b>Clark College of Science and Technology</b> shall voluntarily withdraw a Learner who is found to misbehave and/or act in defiance to existing standards, rules, and regulation of the <strong><u>PARTNER COMPANY</u></strong> can impose necessary HEI sanctions to the said learner;</li>
                <li>The <b>Clark College of Science and Technology</b> through its Industry Coordinator shall make onsite sit/follow ups to the <strong><u>PARTNER COMPANY</u></strong> during the training period and evaluate the Learner's progress based on the training plan and discuss training problems;</li>
                <li>The <b>Clark College of Science and Technology</b> has the discretion to pull out the Learner if there is an apparent risk and/or exploitation on the rights of the Learner;</li>
                <li>The <b>Clark College of Science and Technology</b> shall ensure that the Learner shall ensure that the Learner has an on-and off the campus insurance coverage within the duration of the training as part of their training fee.</li>
                <li>The <b>Clark College of Science and Technology</b> shall ensure Learner shall be personally responsible for any and all liabilities arising from negligence in the performance of his/her duties and functions while under INTERNSHIP;</li>
                <li>There is no employer-employee relationship between the <strong><u>PARTNER COMPANY</u></strong> and the Learner;</li>
                <li>The duration of the program shall be equivalent to <?php echo htmlspecialchars($total_hours); ?> working hours unless otherwise agreed upon by the <strong><u>PARTNER COMPANY</u></strong> and the <strong>Clark College of Science and Technology;</strong></li>
                <li>Any violation of the foregoing covenants will warrant the cancellation of the Memorandum of Agreement by the <strong><u>PARTNER COMPANY</u></strong> within thirty (30) days upon notice to the <strong>Clark College of Science and Technology.</strong></li>
                <li>The <strong><u>PARTNER COMPANY</u></strong> may grant allowance to Learner in accordance with the PARTNER ENTERPISE existing rules and regulations;</li>
                <li>The <strong><u>PARTNER COMPANY</u></strong> is not allowed to employ Learner within the INTERNSHIP period in order for the Learner to graduate from the program he/she is enrolled in.</li>
            </ol>

            <p>This Memorandum of Agreement shall become effective upon signature of both parties and Implementation will begin immediately and shall continue to be valid hereafter until written notice is given by either party thirty (30) days prior to the date of intended termination.</p>
            <p>In witness where of the parties have signed this Memorandum of Agreement at <?php echo htmlspecialchars($signed_at); ?> this <?php echo htmlspecialchars($signed_day); ?> day of <?php echo htmlspecialchars($signed_month); ?>, <?php echo htmlspecialchars($signed_year); ?>.</p>

            <div class="row app-moa-row row-top-neg-12 app-moa-row-top-neg-12">
                <div class="col app-moa-col">
                    <p><strong>For the PARTNER COMPANY</strong></p>
                    <p class="mt-16 app-moa-mt-16"><strong><u><?php echo htmlspecialchars($partner_rep); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18"><u><?php echo htmlspecialchars($partner_position); ?></u>, <u><?php echo htmlspecialchars($partner_name); ?></u></p>
                </div>
                <div class="col app-moa-col right app-moa-right">
                    <p class="text-right app-moa-text-right"><strong>For the SCHOOL</strong></p>
                    <p class="mt-16 app-moa-mt-16 text-right app-moa-text-right"><strong><u><?php echo htmlspecialchars($school_rep); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18 text-right app-moa-text-right"><u><?php echo htmlspecialchars($school_position); ?></u></p>
                </div>
            </div>

            <p class="mt-neg-5 app-moa-mt-neg-5 text-center app-moa-text-center"><strong>SIGNED IN THE PRESENCE OF:</strong></p>
            <div class="row app-moa-row">
                <div class="col app-moa-col">
                    <p class="mt-0 app-moa-mt-0"><strong><u><?php echo htmlspecialchars($company_receipt); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18">Representative for the PARTNER COMPANY</p>
                </div>
                <div class="col app-moa-col right app-moa-right">
                    <p class="mt-0 app-moa-mt-0 text-right app-moa-text-right"><strong><u><?php echo htmlspecialchars($presence_school_admin); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18 text-right app-moa-text-right"><u><?php echo htmlspecialchars($presence_school_admin_position); ?></u></p>
                </div>
            </div>

            <p>
                ACKNOWLEDGEMENT Before me, a Notary Public in the city <strong><u><?php echo htmlspecialchars($notary_city); ?></u></strong>, personally appeared <strong><u><?php echo htmlspecialchars($presence_partner_rep); ?></u></strong>, known to me to be the same persons who executed the foregoing instrument and they acknowledged to me that the same is their free will and voluntary deed and that of the ENTERPRISEs herein represented. Witness my hand and seal on this <strong><u><?php echo htmlspecialchars($notary_day); ?></u></strong> day of <strong><u><?php echo htmlspecialchars($notary_month); ?></u></strong> <strong><u><?php echo htmlspecialchars($notary_year); ?></u></strong> in <strong><u><?php echo htmlspecialchars($notary_place); ?></u></strong>.
            </p>
            <p class="mb-neg-4 app-moa-mb-neg-4">Doc No. : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($doc_no); ?></span></p>
            <p class="mb-neg-4 app-moa-mb-neg-4">Page No. : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($page_no); ?></span></p>
            <p class="mb-neg-4 app-moa-mb-neg-4">Book No. : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($book_no); ?></span></p>
            <p class="mb-neg-4 app-moa-mb-neg-4">Series of : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($series_no); ?></span></p>
        </div>

        <div class="actions app-moa-actions no-print">
            <div class="tip-box app-moa-tip-box">Tip: Use A4 paper. In your print settings, set the margins to Top: 0, Bottom: 0.2, Left: 0.5, Right: 0.5, and uncheck "Headers and footers" or "    </div> <!-- .nxl-content -->
</main>
Include headers and footers".</div>
            <button class="btn app-moa-btn action-btn app-moa-action-btn" id="btn_print_moa" type="button">Print</button>
            <button class="btn app-moa-btn action-btn app-moa-action-btn" id="btn_close_moa" type="button">Close</button>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>




