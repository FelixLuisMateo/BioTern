<?php
// Generate printable Dau Barangay Hall Memorandum of Agreement (MOA)
function q($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$print_date = q('date', date('F j, Y'));
$partner_name = q('partner_name', 'DAU BARANGAY HALL');
$partner_rep = q('partner_rep', '__________________');
$partner_position = q('partner_position', '__________________');
$partner_address = q('partner_address', '__________________');
$company_receipt = q('company_receipt', '______________________');
$total_hours = q('total_hours', '250');
$school_rep = q('school_rep', '__________________');
$school_position = q('school_position', '__________________');

$signed_at = q('signed_at', '__________________');
$signed_day = q('signed_day', '_____');
$signed_month = q('signed_month', '__________________');
$signed_year = q('signed_year', '20__');

$presence_partner_rep = q('presence_partner_rep', '______________________');
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
$page_title = 'BioTern || DAU Memorandum of Agreement';
$base_href = $asset_prefix;
$page_body_class = 'app-generate-page';
$page_styles = [
    'assets/css/generate-shell-clean.css',
    'assets/css/generate-moa-common-page.css',
    'assets/css/generate-dau-moa-page.css',
];
$page_scripts = [
    'assets/js/generate-dau-moa-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<div class="main-content" data-use-saved-template="<?php echo $use_saved_template ? '1' : '0'; ?>">
    <div class="container app-moa-container">
        <div class="doc app-moa-doc" id="moa_doc_content">
            <h4>Memorandum of Agreement</h4>
            <p>
                This Memorandum of Agreement made and executed between: <strong><u>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</u></strong>, duly organized and existing under Philippine Laws with office/business address at <strong><u>AUREA ST. SAMSONVILLE, DAU MABALACAT CITY, PAMPANGA</u></strong> represented herein by <strong><u>MR. JOMAR G. SANGIL</u></strong>, here in after referred to as the Higher Education Institution.
            </p>
            <p class="center-gap-8 app-moa-center-gap-8">And</p>
            <p>
                <strong><u><?php echo htmlspecialchars($partner_name); ?></u></strong> a LOCAL GOVERNMENT UNIT duly organized and existing under Philippine Laws with office/business address at <strong><u><?php echo htmlspecialchars($partner_address); ?></u></strong> represented herein by <strong><u><?php echo htmlspecialchars($partner_rep); ?></u></strong> herein after referred to as the PARTNER LOCAL GOVERNMENT UNIT.
            </p>
            <p class="center-gap-6 app-moa-center-gap-6">Witnesseth:</p>
            <p>The parties hereby bind themselves to undertake a Memorandum of Agreement for the purpose of supporting the Internship for Learners under the following terms and condition</p>
            <p><strong>School:</strong></p>
            <ol>
                <li>The Clark College of Science and Technology shall provide the learner undergoing the OJT/Internship with the basic orientation on work values, behavior, and discipline to ensure smooth cooperation with the PARTNER LOCAL GOVERNMENT UNIT;</li>
                <li>The Clark College of Science and Technology shall issue and official endorsement vouching for the well-being of the Learner which shall be used by the PARTNER LOCAL GOVERNMENT UNIT for the processing the learner's application for OJT/Internship;</li>
                <li>The Clark College of Science and Technology shall voluntarily withdraw a Learner who is found to misbehave and/or act in defiance to existing standards, rules, and regulations of the PARTNER LOCAL GOVERNMENT UNIT and impose necessary CCST sanctions to the said learner;</li>
                <li>The Clark College of Science and Technology through its Industry Coordinator shall make onsite sit/follow ups to the PARTNER LOCAL GOVERNMENT UNIT during the training period and evaluate the Learner's progress based on the training plan and discuss training problems;</li>
                <li>The Clark College of Science and Technology have the discretion to pull out the Learner if there is an apparent risk and/or exploitation on the rights of the Learner;</li>
                <li>The Clark College of Science and Technology shall ensure that the Learner shall ensure that the Learner has an on-and off the campus insurance coverage within the duration of the training as part of their training fee.</li>
                <li>The Clark College of Science and Technology shall ensure Learner shall be personally responsible for any and all liabilities arising from negligence in the performance of his/her duties and functions while under OJT/Internship;</li>
                <li>There is no employer-employee relationship between the PARTNER LOCAL GOVERNMENT UNIT and the Learner;</li>
                <li>The duration of the program shall be equivalent to <?php echo htmlspecialchars($total_hours); ?> working hours unless otherwise agreed upon by the PARTNER LOCAL GOVERNMENT UNIT and the School;</li>
                <li>Any violation of the foregoing covenants will warrant the cancellation of the Memorandum of Agreement by the PARTNER LOCAL GOVERNMENT UNIT within thirty (30) days upon notice to the school.</li>
            </ol>

            <p class="second-page-start app-moa-second-page-start"><strong>PARTNER LOCAL GOVERNMENT UNIT:</strong></p>
            <ol start="11">
                <li>The PARTNER LOCAL GOVERNMENT UNIT may grant allowance to Learner in accordance with the PARTNER LOCAL GOVERNMENT UNIT existing rules and regulations;</li>
                <li>The PARTNER LOCAL GOVERNMENT UNIT is not allowed to employ Learner within the OJT/Internship period in order for the Learner to graduate from the program he/she is enrolled. PARTNER LOCAL GOVERNMENT UNIT, however, upon consultation with HIGHER EDUCATION INSTITUTION, may invite qualified students to submit themselves to examinations, interviews, and file pertinent documents in support of their application, after the end of their learnership program.</li>
            </ol>

            <p>The Parties shall not divulge any information that it may have access to, and any such information will only be used for academic purposes. All Parties shall implement all reasonable security measures to maintain the confidentiality of all information exchanged between the parties during the term of this Agreement. This confidentiality clause shall continue for the duration of this Agreement and after its termination, unless it has become public knowledge or is already in the public domain. All parties are under obligation to return or appropriately dispose of any proprietary materials furnished during the tenure of the agreement.</p>
            <p>This Memorandum of Agreement shall be effective from the date of its signing and shall be valid for a period of one (1) year. The Agreement may be renewed by giving the other party 30-day notice before the end of this Agreement. Either party may terminate this agreement, with or without cause, at any time by serving written notice to the other party, giving thirty (30) days lead-time before the intended date of termination. Any pre-termination of this Agreement shall be without prejudice to completion of the internship of student learners undergoing training as of the date of termination. In witness whereof the parties have signed this Memorandum of Agreement at <?php echo htmlspecialchars($signed_at); ?> this <?php echo htmlspecialchars($signed_day); ?> day of <?php echo htmlspecialchars($signed_month); ?>, <?php echo htmlspecialchars($signed_year); ?>.</p>

            <div class="row app-moa-row row-top-neg-12 app-moa-row-top-neg-12">
                <div class="col app-moa-col">
                    <p><strong>For the PARTNER LOCAL GOVERNMENT UNIT</strong></p>
                    <p class="mt-16 app-moa-mt-16"><strong><u><?php echo htmlspecialchars($partner_rep); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18"><strong><u><?php echo htmlspecialchars($partner_position); ?></u></strong></p>
                </div>
                <div class="col app-moa-col right app-moa-right">
                    <p class="mt-16 app-moa-mt-16 text-right app-moa-text-right"><strong><u><?php echo htmlspecialchars($school_rep); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18 text-right app-moa-text-right"><strong><u><?php echo htmlspecialchars($school_position); ?></u></strong></p>
                </div>
            </div>

            <div class="row app-moa-row">
                <div class="col app-moa-col">
                    <p class="mt-0 app-moa-mt-0"><strong><u><?php echo htmlspecialchars($company_receipt); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18 nowrap app-moa-nowrap">Representative of the Partner LOCAL GOVERNMENT UNIT</p>
                </div>
                <div class="col app-moa-col right app-moa-right">
                    <p class="mt-0 app-moa-mt-0 text-right app-moa-text-right"><strong><u><?php echo htmlspecialchars($presence_school_admin); ?></u></strong></p>
                    <p class="mt-neg-18 app-moa-mt-neg-18 text-right app-moa-text-right"><strong><u><?php echo htmlspecialchars($presence_school_admin_position); ?></u></strong></p>
                </div>
            </div>
            <p>
                ACKNOWLEDGEMENT Before me, a Notary Public in the city <strong><u><?php echo htmlspecialchars($notary_city); ?></u></strong>, personally appeared <strong><u><?php echo htmlspecialchars($notary_appeared_1); ?></u></strong> and <strong><u><?php echo htmlspecialchars($notary_appeared_2); ?></u></strong> known to me to be the same persons who executed the foregoing instrument and they acknowledged to me that the same is their free will and voluntary deed and that of the LOCAL GOVERNMENT UNITs herein represented. Witness my hand and seal on this <strong><u><?php echo htmlspecialchars($notary_day); ?></u></strong> day of <strong><u><?php echo htmlspecialchars($notary_month); ?></u></strong> <strong><u><?php echo htmlspecialchars($notary_year); ?></u></strong> in <strong><u><?php echo htmlspecialchars($notary_place); ?></u></strong>.
            </p>
            <p class="mb-neg-12 app-moa-mb-neg-12">Doc No. : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($doc_no); ?></span></p>
            <p class="mb-neg-12 app-moa-mb-neg-12">Page No. : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($page_no); ?></span></p>
            <p class="mb-neg-12 app-moa-mb-neg-12">Book No. : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($book_no); ?></span></p>
            <p class="mb-neg-12 app-moa-mb-neg-12">Series of : <span class="notary-line app-moa-notary-line"><?php echo htmlspecialchars($series_no); ?></span></p>
        </div>

        <div class="actions app-moa-actions no-print">
            <div class="tip-box app-moa-tip-box">Tip: Use A4 paper. In your print settings, set the margins to Top: 0, Bottom: 0.2, Left: 0.5, Right: 0.5, and uncheck "Headers and footers" or "Include headers and footers".</div>
            <button class="btn app-moa-btn action-btn app-moa-action-btn" id="btn_print_moa" type="button">Print</button>
            <button class="btn app-moa-btn action-btn app-moa-action-btn" id="btn_close_moa" type="button">Close</button>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

