<?php
$outputDir = dirname(__DIR__) . '/uploads/manuals';

$manuals = [
    'admin-manual.pdf' => [
        'title' => 'Admin Manual',
        'sections' => [
            'Purpose:' => [
                'This manual explains the main BioTern tasks for administrators. Admin accounts can manage users, settings, student records, attendance, documents, reports, and import tools.',
            ],
            'Dashboard:' => [
                'Use Dashboard > Overview for daily system status.',
                'Use Dashboard > Analytics to review student, attendance, and OJT activity summaries.',
            ],
            'System Setup:' => [
                'Open Settings > General to review school and system details.',
                'Open Settings > Email to set the sender email and app password used for verification and notifications.',
                'Open Settings > OJT Settings, Student Settings, and Attendance Settings before active deployment.',
                'Use Notifications and Account Settings for your own account preferences and password.',
            ],
            'Academic Setup:' => [
                'Open Academic Setup > Courses to add or update course records.',
                'Open Departments to manage department lists.',
                'Open Sections to manage student sections and class groupings.',
            ],
            'User Accounts:' => [
                'Open User Accounts > Users to review all accounts.',
                'Use Create Admin only for trusted system administrators.',
                'Use Coordinators and Supervisors to create or update higher role accounts.',
                'Keep inactive or unused accounts disabled.',
            ],
            'Student And OJT Management:' => [
                'Open Student Management > Students List to create, edit, and review student profiles.',
                'Use Applications Review to approve or reject student application records.',
                'Use Internal Attendance for internal DTR review.',
                'Use External Attendance for external DTR review.',
                'Use OJT Management for OJT lists, companies, internal list, and external list.',
            ],
            'Attendance Review:' => [
                'Review pending internal and external DTR entries regularly.',
                'Check proof images and remarks before approving.',
                'Approve valid entries so hours can count.',
                'Decline entries with unclear proof, wrong dates, or wrong times.',
                'Use Attendance Settings to adjust rules only when needed.',
            ],
            'Documents And Reports:' => [
                'Use Documents to generate application, endorsement, MOA, DAU MOA, and parent consent forms.',
                'Use Reports to export student status, attendance, hours completion, section, department, company, evaluation, unassigned students, and document reports.',
            ],
            'Import And Tools:' => [
                'Use Student Masterlist Import/Export for student data migration.',
                'Use Import OJT Internal and Import OJT External for OJT data.',
                'Review Import Error Report after every import.',
                'Use Manual DTR Input only for controlled corrections.',
                'Use Data Transfer carefully and keep backups before importing SQL.',
            ],
            'Security Reminders:' => [
                'Do not share admin passwords.',
                'Use app passwords for email instead of personal email passwords.',
                'Review logs when suspicious activity is reported.',
                'Back up the database before major imports or settings changes.',
            ],
        ],
    ],
    'coordinator-manual.pdf' => [
        'title' => 'Coordinator Manual',
        'sections' => [
            'Purpose:' => [
                'This manual explains the main BioTern tasks for coordinators. Coordinator accounts help manage students, OJT records, attendance review, communication, documents, and reports.',
            ],
            'Dashboard:' => [
                'Open Dashboard to review current student and OJT activity.',
                'Use the search bar to find pages quickly.',
            ],
            'Student Management:' => [
                'Open Student Management > Students List to review student records.',
                'Check profile details, course, section, contact information, and assignment track.',
                'Use Applications Review to review submitted applications.',
                'Coordinate with admin if a student record needs account or system-level correction.',
            ],
            'OJT Management:' => [
                'Open OJT Management > OJT List to monitor internal and external OJT records.',
                'Use Internal List for internal OJT students.',
                'Use External List for external OJT students.',
                'Use Companies to verify company information for external OJT.',
                'Confirm start dates and required hours before attendance computation.',
            ],
            'Attendance Review:' => [
                'Open Internal Attendance to review internal DTR entries.',
                'Open External Attendance to review external DTR entries.',
                'Check the date, time, proof image, reason, and student identity before making a decision.',
                'Approve only valid entries.',
                'Decline entries that have missing proof, wrong dates, wrong time ranges, or unclear explanations.',
            ],
            'Documents:' => [
                'Use the Documents menu to prepare application, endorsement, MOA, DAU MOA, and parent consent forms.',
                'Verify student information before generating or printing documents.',
                'Ask admin to update templates or system settings if document content is incorrect.',
            ],
            'Workspace:' => [
                'Use Chat for direct communication inside BioTern.',
                'Use Email for system messages to BioTern accounts.',
                'Use Notes, Storage, and Calendar for workspace organization if enabled.',
            ],
            'Reports:' => [
                'Use reports to monitor student progress, attendance, and completion.',
                'Export reports when needed for coordinator review or school records.',
                'Check filters before exporting to avoid wrong date ranges or sections.',
            ],
            'Good Practice:' => [
                'Review pending DTR requests daily.',
                'Keep student information updated.',
                'Do not approve attendance without proof when proof is required.',
                'Escalate account, email, or system setting issues to the admin.',
            ],
        ],
    ],
    'supervisor-manual.pdf' => [
        'title' => 'Supervisor Manual',
        'sections' => [
            'Purpose:' => [
                'This manual explains the main BioTern tasks for supervisors. Supervisor accounts focus on reviewing assigned students, attendance, OJT progress, communication, and reports.',
            ],
            'Dashboard:' => [
                'Open Dashboard to view available summaries and assigned work.',
                'Use search to quickly open attendance, student, or report pages.',
            ],
            'Student Review:' => [
                'Open Student Management > Students List to review student profiles available to your role.',
                'Check course, section, assignment track, coordinator, and supervisor assignment.',
                'Use profile information to confirm the correct student before reviewing DTR records.',
            ],
            'Internal Attendance:' => [
                'Open Internal Attendance to review internal DTR records.',
                'Check proof image, date, time entries, and reason.',
                'Approve valid entries when the time and proof match.',
                'Decline entries when proof is missing, unclear, or the time is incorrect.',
            ],
            'External Attendance:' => [
                'Open External Attendance to review external DTR records.',
                'Check if external OJT is approved or allowed to start counting hours.',
                'Verify the proof image and the encoded time entries.',
                'Approve or decline based on school policy and evidence.',
            ],
            'OJT Monitoring:' => [
                'Use OJT List, Internal List, and External List to monitor student placement and status.',
                'Check company information when reviewing external OJT.',
                'Coordinate with the coordinator or admin when records need correction.',
            ],
            'Communication:' => [
                'Use Chat for quick discussion with students or staff.',
                'Use Email for formal messages inside the system.',
                'Keep communication professional and related to OJT or school activity.',
            ],
            'Reports:' => [
                'Use attendance and student progress reports when available.',
                'Confirm filters before exporting or reviewing a report.',
                'Use reports to identify students with missing DTR entries or incomplete hours.',
            ],
            'Good Practice:' => [
                'Review pending attendance requests regularly.',
                'Do not approve entries without checking proof.',
                'Leave clear reasons when declining an entry if the system asks for remarks.',
                'Report suspicious or repeated incorrect submissions to the coordinator or admin.',
            ],
        ],
    ],
];

function pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function wrap_text(string $text, int $maxChars): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (strlen($candidate) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    return $lines;
}

function build_manual_lines(array $manual): array
{
    $lines = [
        ['text' => (string)$manual['title'], 'size' => 18, 'bold' => true],
        ['text' => 'BioTern User Manual', 'size' => 12, 'bold' => false],
        ['text' => 'Updated: May 11, 2026', 'size' => 10, 'bold' => false],
        ['text' => '', 'size' => 10, 'bold' => false],
    ];

    foreach ($manual['sections'] as $heading => $items) {
        $lines[] = ['text' => (string)$heading, 'size' => 12, 'bold' => true];
        foreach ($items as $index => $item) {
            $prefix = ((int)$index + 1) . '. ';
            $wrapped = wrap_text($prefix . (string)$item, 92);
            foreach ($wrapped as $lineIndex => $wrappedLine) {
                $lines[] = [
                    'text' => $lineIndex === 0 ? $wrappedLine : '   ' . $wrappedLine,
                    'size' => 10,
                    'bold' => false,
                ];
            }
        }
        $lines[] = ['text' => '', 'size' => 10, 'bold' => false];
    }

    return $lines;
}

function render_pdf(array $lines): string
{
    $pages = [];
    $pageLines = [];
    $y = 760;
    foreach ($lines as $line) {
        $lineHeight = ((int)$line['size'] >= 18) ? 24 : (((int)$line['size'] >= 12) ? 18 : 14);
        if ($y - $lineHeight < 50 && $pageLines !== []) {
            $pages[] = $pageLines;
            $pageLines = [];
            $y = 760;
        }
        $pageLines[] = $line + ['y' => $y];
        $y -= $lineHeight;
    }
    if ($pageLines !== []) {
        $pages[] = $pageLines;
    }

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    ];
    $pageObjectIds = [];
    $nextObjectId = 5;
    foreach ($pages as $pageLines) {
        $content = "BT\n";
        foreach ($pageLines as $line) {
            $font = !empty($line['bold']) ? '/F2' : '/F1';
            $size = (int)$line['size'];
            $content .= sprintf("%s %d Tf 1 0 0 1 54 %d Tm (%s) Tj\n", $font, $size, (int)$line['y'], pdf_escape((string)$line['text']));
        }
        $content .= "ET\n";
        $contentObjectId = $nextObjectId++;
        $pageObjectId = $nextObjectId++;
        $objects[$contentObjectId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        $objects[$pageObjectId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjectId} 0 R >>";
        $pageObjectIds[] = $pageObjectId;
    }

    $kids = implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageObjectIds));
    $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjectIds) . " >>";
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $objectId => $object) {
        $offsets[$objectId] = strlen($pdf);
        $pdf .= $objectId . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $maxObjectId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObjectId; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create manuals directory.\n");
    exit(1);
}

foreach ($manuals as $fileName => $manual) {
    $pdf = render_pdf(build_manual_lines($manual));
    file_put_contents($outputDir . '/' . $fileName, $pdf);
    echo "wrote {$fileName}\n";
}
