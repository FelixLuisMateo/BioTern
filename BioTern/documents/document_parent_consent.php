<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/document_access.php';

biotern_boot_session(isset($conn) ? $conn : null);

function parent_consent_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function parent_consent_q(string $key, string $fallback = ''): string
{
    return trim((string)($_GET[$key] ?? $fallback));
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($currentRole === 'student' && $currentUserId > 0) {
    $studentLookupStmt = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
    if ($studentLookupStmt) {
        $studentLookupStmt->bind_param('i', $currentUserId);
        $studentLookupStmt->execute();
        $studentLookupRow = $studentLookupStmt->get_result()->fetch_assoc() ?: null;
        $studentLookupStmt->close();
        if ($studentLookupRow) {
            $studentId = (int)($studentLookupRow['id'] ?? 0);
        }
    }
}

$student = null;
if ($studentId > 0 && ($currentRole === 'student' || !empty(documents_student_can_generate($conn, $studentId)['allowed']))) {
    $stmt = $conn->prepare("SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON c.id = s.course_id WHERE s.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}

$studentName = parent_consent_q('student_name');
if ($studentName === '' && $student) {
    $studentName = trim((string)(($student['first_name'] ?? '') . ' ' . (!empty($student['middle_name']) ? ($student['middle_name'] . ' ') : '') . ($student['last_name'] ?? '')));
}
$parentName = parent_consent_q('parent_name');
$companyName = parent_consent_q('company_name', '________________________________');
$printDate = parent_consent_q('date', date('F j, Y'));

$studentLine = $studentName !== '' ? $studentName : 'my son/daughter';
$parentLine = $parentName !== '' ? $parentName : '______________________________';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Parent Consent and Waiver</title>
    <style>
        :root {
            --ink: #111827;
            --muted: #475569;
            --line: #1f2937;
            --brand: #1d4ed8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #e8edf5;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }

        .document-actions {
            max-width: 816px;
            margin: 0 auto 16px;
            padding: 16px;
            border-radius: 16px;
            background: #0f172a;
            color: #e5eefc;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.18);
        }

        .document-actions h1 {
            margin: 0 0 12px;
            font-size: 18px;
        }

        .document-actions form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .document-actions label {
            display: grid;
            gap: 5px;
            color: #b8c7e6;
            font-size: 12px;
            font-weight: 700;
        }

        .document-actions input {
            width: 100%;
            min-height: 38px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 10px;
            background: #111c34;
            color: #fff;
            padding: 8px 10px;
        }

        .document-actions .actions-row {
            grid-column: 1 / -1;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 4px;
        }

        .document-actions button,
        .document-actions a {
            border: 0;
            border-radius: 10px;
            padding: 9px 14px;
            color: #fff;
            background: #5b7cfa;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .document-actions a {
            background: #1e293b;
        }

        .page {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            padding: 0.36in 0.55in 0.55in;
            background: #fff;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
            font-size: 11px;
            line-height: 1.28;
        }

        .school-header {
            display: grid;
            grid-template-columns: 72px 1fr 72px;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 7px;
            margin-bottom: 20px;
        }

        .school-header img {
            width: 58px;
            height: auto;
            justify-self: center;
        }

        .school-copy {
            text-align: center;
            color: #1e40af;
            line-height: 1.18;
            font-size: 9px;
            font-weight: 600;
        }

        .school-copy strong {
            display: block;
            font-size: 11px;
            letter-spacing: 0.02em;
        }

        .document-title {
            text-align: center;
            font-size: 11px;
            margin: 0 0 24px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .content {
            max-width: 6.65in;
            margin: 0 auto;
        }

        .content p {
            margin: 0 0 10px;
            text-align: left;
        }

        .content .lead {
            margin-bottom: 12px;
        }

        .content strong {
            font-weight: 700;
        }

        .signature-area {
            margin-top: 42px;
            display: grid;
            grid-template-columns: 1fr 1.1in;
            gap: 34px;
            align-items: end;
        }

        .signature-line {
            border-top: 1px solid var(--line);
            padding-top: 6px;
            font-size: 9px;
            font-weight: 700;
        }

        .signature-date {
            border-top: 1px solid var(--line);
            padding-top: 6px;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
        }

        .student-signature {
            width: 3.35in;
            margin-top: 50px;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .document-actions {
                display: none !important;
            }

            .page {
                width: 100%;
                min-height: 0;
                box-shadow: none;
                margin: 0;
                padding: 0.36in 0.55in 0.55in;
            }
        }

        @media (max-width: 860px) {
            body {
                padding: 12px;
            }

            .document-actions form {
                grid-template-columns: 1fr;
            }

            .page {
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <section class="document-actions">
        <h1>Parent Consent and Waiver</h1>
        <form method="get">
            <?php if ($studentId > 0): ?>
            <input type="hidden" name="id" value="<?php echo (int)$studentId; ?>">
            <?php endif; ?>
            <label>
                Student Name
                <input type="text" name="student_name" value="<?php echo parent_consent_h($studentName); ?>" placeholder="Student full name">
            </label>
            <label>
                Parent / Guardian Name
                <input type="text" name="parent_name" value="<?php echo parent_consent_h($parentName); ?>" placeholder="Parent or guardian full name">
            </label>
            <label>
                Company / Training Site
                <input type="text" name="company_name" value="<?php echo parent_consent_h($companyName === '________________________________' ? '' : $companyName); ?>" placeholder="Company name">
            </label>
            <label>
                Date
                <input type="text" name="date" value="<?php echo parent_consent_h($printDate); ?>" placeholder="May 2, 2026">
            </label>
            <div class="actions-row">
                <a href="homepage.php">Back</a>
                <button type="submit">Update Preview</button>
                <button type="button" onclick="window.print()">Print</button>
            </div>
        </form>
    </section>

    <main class="page">
        <header class="school-header">
            <img src="assets/images/ccstlogo.png" alt="CCST Logo">
            <div class="school-copy">
                <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>
                SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga<br>
                Telefax No.: (045) 624-0215
            </div>
            <span></span>
        </header>

        <section class="content">
            <h2 class="document-title">Parent Consent and Waiver</h2>

            <p class="lead">
                I hereby give my consent for <strong><?php echo parent_consent_h($studentLine); ?></strong> to participate in the On-the-Job Training (OJT)
                required by <strong>Clark College of Science and Technology (CCST)</strong> at the school and/or its partner or host company<?php echo $companyName !== '________________________________' ? ' (' . parent_consent_h($companyName) . ')' : ''; ?>.
            </p>

            <p>
                I understand that participation in OJT involves certain risks, including possible accidents, injuries, or health-related concerns. I voluntarily allow my
                <strong>son/daughter</strong> to undergo OJT and agree not to hold Clark College of Science and Technology, its administrators, faculty, advisers,
                and staff, as well as the host/partner company, liable for any accident or incident.
            </p>

            <p>
                I acknowledge that the school and the faculty adviser will provide proper guidance and supervision, but that they cannot guarantee absolute safety at
                all times during the OJT period.
            </p>

            <p>
                With this, I express my trust that reasonable safety measures and precautions will be observed for the welfare of my
                <strong>son/daughter</strong> throughout the duration of the OJT.
            </p>

            <p>
                I confirm that I have read and understood this consent and that my signature below signifies my approval and agreement.
            </p>

            <div class="student-signature">
                <div class="signature-line">Signature over Printed Name of Student</div>
            </div>

            <div class="signature-area">
                <div class="signature-line">Signature over Printed Name of Parent/Guardian</div>
                <div class="signature-date">Date</div>
            </div>
        </section>
    </main>
</body>
</html>
