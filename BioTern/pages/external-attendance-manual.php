<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

biotern_boot_session(isset($conn) ? $conn : null);
external_attendance_ensure_schema($conn);
section_schedule_ensure_columns($conn);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentUserId <= 0 || $currentRole !== 'student') {
    header('Location: homepage.php');
    exit;
}

$student = external_attendance_student_context($conn, $currentUserId);
if (!$student) {
    $_SESSION['external_attendance_flash'] = ['message' => 'Student profile not found for manual external DTR.', 'type' => 'danger'];
    header('Location: external-attendance.php');
    exit;
}

$externalFlash = $_SESSION['external_attendance_flash'] ?? null;
unset($_SESSION['external_attendance_flash']);

function external_attendance_manual_range_days_page(string $startDate, string $endDate): array
{
    $days = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        return $days;
    }

    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);
    if ($startTs === false || $endTs === false || $endTs < $startTs) {
        return $days;
    }

    for ($cursor = $startTs; $cursor <= $endTs; $cursor += 86400) {
        if ((int)date('w', $cursor) === 0) {
            continue;
        }
        $days[] = date('Y-m-d', $cursor);
    }

    return $days;
}

function external_attendance_manual_time_options_page(string $selected = ''): string
{
    $selected = substr(trim($selected), 0, 5);
    $html = '<option value="">Select time</option>';
    for ($hour = 0; $hour < 24; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 30) {
            $value = sprintf('%02d:%02d', $hour, $minute);
            $label = date('g:i A', strtotime($value . ':00'));
            $isSelected = $value === $selected ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
    }
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rangeStart = trim((string)($_POST['range_start'] ?? ''));
    $rangeEnd = trim((string)($_POST['range_end'] ?? ''));
    $notes = trim((string)($_POST['range_notes'] ?? ''));
    $entries = isset($_POST['entries']) && is_array($_POST['entries']) ? $_POST['entries'] : [];
    $rangeDays = external_attendance_manual_range_days_page($rangeStart, $rangeEnd);

    if ($rangeDays === []) {
        $_SESSION['external_attendance_flash'] = ['message' => 'Choose a valid date range for the manual external DTR upload.', 'type' => 'danger'];
        header('Location: external-attendance-manual.php');
        exit;
    }

    $photoPath = null;
    if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = external_attendance_store_photo($_FILES['photo'], (int)$student['id'], $rangeStart);
        if (!($upload['ok'] ?? false)) {
            $_SESSION['external_attendance_flash'] = ['message' => (string)($upload['message'] ?? 'Could not upload verification photo.'), 'type' => 'danger'];
            header('Location: external-attendance-manual.php?range_start=' . urlencode($rangeStart) . '&range_end=' . urlencode($rangeEnd));
            exit;
        }
        $photoPath = (string)($upload['path'] ?? '');
    }

    $savedCount = 0;
    foreach ($rangeDays as $day) {
        $row = isset($entries[$day]) && is_array($entries[$day]) ? $entries[$day] : [];
        $payload = [
            'morning_time_in' => external_attendance_normalize_time((string)($row['morning_time_in'] ?? '')),
            'morning_time_out' => external_attendance_normalize_time((string)($row['morning_time_out'] ?? '')),
            'afternoon_time_in' => external_attendance_normalize_time((string)($row['afternoon_time_in'] ?? '')),
            'afternoon_time_out' => external_attendance_normalize_time((string)($row['afternoon_time_out'] ?? '')),
        ];

        $hasPunch = false;
        foreach ($payload as $value) {
            if ($value !== null && $value !== '') {
                $hasPunch = true;
                break;
            }
        }
        if (!$hasPunch) {
            continue;
        }

        $save = external_attendance_upsert_day(
            $conn,
            $student,
            $day,
            $payload,
            $photoPath,
            $notes,
            $currentUserId,
            false,
            'manual'
        );
        if (!empty($save['ok'])) {
            $savedCount++;
        }
    }

    if ($savedCount <= 0) {
        $_SESSION['external_attendance_flash'] = ['message' => 'No manual external DTR rows were saved. Fill at least one day first.', 'type' => 'warning'];
        header('Location: external-attendance-manual.php?range_start=' . urlencode($rangeStart) . '&range_end=' . urlencode($rangeEnd));
        exit;
    }

    $_SESSION['external_attendance_flash'] = ['message' => 'Manual external DTR saved for ' . $savedCount . ' day(s).', 'type' => 'success'];
    header('Location: external-attendance.php');
    exit;
}

$selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthRows = [];
$monthStmt = $conn->prepare("
    SELECT *
    FROM external_attendance
    WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
    ORDER BY attendance_date DESC, id DESC
");
if ($monthStmt) {
    $monthStmt->bind_param('iss', $student['id'], $monthStart, $monthEnd);
    $monthStmt->execute();
    $monthResult = $monthStmt->get_result();
    while ($row = $monthResult->fetch_assoc()) {
        $monthRows[] = $row;
    }
    $monthStmt->close();
}

$manualRangeStart = trim((string)($_GET['range_start'] ?? date('Y-m-01')));
$manualRangeEnd = trim((string)($_GET['range_end'] ?? date('Y-m-d')));
$manualRangeDays = external_attendance_manual_range_days_page($manualRangeStart, $manualRangeEnd);
$manualRangeRecords = [];
foreach ($manualRangeDays as $day) {
    $manualRangeRecords[$day] = external_attendance_student_record($conn, (int)$student['id'], $day) ?: [];
}

$page_title = 'BioTern || Manual External DTR';
$page_styles = [
    'assets/css/homepage-student.css',
    'assets/css/student-dtr.css',
    'assets/css/modules/pages/page-demo-biometric.css',
    'assets/css/modules/pages/page-external-attendance-student.css',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="main-content">
            <div class="student-home-shell student-dtr-shell biometric-container">
                <?php if (is_array($externalFlash) && !empty($externalFlash['message'])): ?>
                    <div class="alert alert-<?php echo htmlspecialchars((string)($externalFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$externalFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <section class="bio-hero">
                    <div class="bio-hero-chip">
                        <i class="feather-edit-3"></i>
                        <span>Manual External DTR Encoder</span>
                    </div>
                    <h2><?php echo htmlspecialchars(trim((string)($student['first_name'] . ' ' . $student['last_name'])), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p>Generate a long date range, then encode one row per day from your physical DTR. This page is built for bigger batches so the main external attendance screen stays uncluttered.</p>
                    <div class="student-home-meta mt-3">
                        <span><?php echo htmlspecialchars((string)($student['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars(biotern_format_section_code((string)($student['section_code'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>Rows in current range: <?php echo count($manualRangeDays); ?></span>
                    </div>
                </section>

                <section class="record-section mb-4">
                    <div class="card-header border-0 bg-transparent px-4 pt-4">
                        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Date-Range External DTR Capture</h5>
                                <p class="text-muted mb-0">Use this for larger external DTR batches from your physical record.</p>
                            </div>
                            <a href="external-attendance.php" class="btn btn-outline-secondary">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back to External DTR</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        <div class="external-manual-guide mb-4">
                            <strong>Before saving, follow this flow:</strong>
                            <span>1. Pick the date range and generate the rows. Sundays are skipped automatically.</span>
                            <span>2. Pick the closest time from each dropdown, like 8:00 AM, 12:00 PM, 1:00 PM, and 5:00 PM.</span>
                            <span>3. Upload your physical DTR photo if available, add notes, then save for review.</span>
                        </div>
                        <form method="get" class="row g-3 align-items-end mb-4">
                            <div class="col-md-4">
                                <label class="form-label" for="externalRangeStart">Start Date</label>
                                <input id="externalRangeStart" type="date" name="range_start" class="form-control" value="<?php echo htmlspecialchars($manualRangeStart, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="externalRangeEnd">End Date</label>
                                <input id="externalRangeEnd" type="date" name="range_end" class="form-control" value="<?php echo htmlspecialchars($manualRangeEnd, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Generate DTR Rows</button>
                            </div>
                        </form>

                        <?php if ($manualRangeDays === []): ?>
                            <div class="alert alert-warning mb-0">No fillable dates found in that range. Check the dates and try again.</div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="range_start" value="<?php echo htmlspecialchars($manualRangeStart, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="range_end" value="<?php echo htmlspecialchars($manualRangeEnd, ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="externalRangePhoto">Physical DTR Photo</label>
                                        <input id="externalRangePhoto" type="file" name="photo" class="form-control" accept="image/*" capture="environment">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="externalRangeNotes">Notes</label>
                                        <input id="externalRangeNotes" type="text" name="range_notes" class="form-control" maxlength="255" placeholder="Example: Encoded from my physical external DTR.">
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-3 external-manual-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Morning In</th>
                                                <th>Morning Out</th>
                                                <th>Afternoon In</th>
                                                <th>Afternoon Out</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($manualRangeDays as $index => $day): ?>
                                                <?php $existingRow = $manualRangeRecords[$day] ?? []; ?>
                                                <tr>
                                                    <td class="fw-semibold" data-label="Row"><?php echo (int)($index + 1); ?></td>
                                                    <td data-label="Date">
                                                        <div class="fw-semibold"><?php echo htmlspecialchars(date('M d, Y', strtotime($day)), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars(date('l', strtotime($day)), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </td>
                                                    <td data-label="Morning In"><select class="form-select external-manual-time-select" name="entries[<?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>][morning_time_in]"><?php echo external_attendance_manual_time_options_page((string)($existingRow['morning_time_in'] ?? '08:00')); ?></select></td>
                                                    <td data-label="Morning Out"><select class="form-select external-manual-time-select" name="entries[<?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>][morning_time_out]"><?php echo external_attendance_manual_time_options_page((string)($existingRow['morning_time_out'] ?? '12:00')); ?></select></td>
                                                    <td data-label="Afternoon In"><select class="form-select external-manual-time-select" name="entries[<?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>][afternoon_time_in]"><?php echo external_attendance_manual_time_options_page((string)($existingRow['afternoon_time_in'] ?? '13:00')); ?></select></td>
                                                    <td data-label="Afternoon Out"><select class="form-select external-manual-time-select" name="entries[<?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>][afternoon_time_out]"><?php echo external_attendance_manual_time_options_page((string)($existingRow['afternoon_time_out'] ?? '17:00')); ?></select></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success">Save External DTR for Review</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Current Month External Attendance</h5>
                        <form method="get" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="range_start" value="<?php echo htmlspecialchars($manualRangeStart, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="range_end" value="<?php echo htmlspecialchars($manualRangeEnd, ENT_QUOTES, 'UTF-8'); ?>">
                            <label class="small text-muted" for="externalMonth">Month</label>
                            <input id="externalMonth" type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-light-brand">Load</button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 external-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Morning</th>
                                        <th>Afternoon</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($monthRows === []): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No external attendance records yet for this month.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($monthRows as $row): ?>
                                            <tr>
                                                <td data-label="Date"><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$row['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td data-label="Morning"><?php echo htmlspecialchars(trim((string)($row['morning_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['morning_time_in'])) . ' - ' . (trim((string)($row['morning_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['morning_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td data-label="Afternoon"><?php echo htmlspecialchars(trim((string)($row['afternoon_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['afternoon_time_in'])) . ' - ' . (trim((string)($row['afternoon_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['afternoon_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td data-label="Total"><?php echo number_format((float)($row['total_hours'] ?? 0), 2); ?> hrs</td>
                                                <td data-label="Status"><?php echo htmlspecialchars(ucfirst((string)($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td data-label="Notes"><?php echo htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    function enhanceExternalManualTimeFields(scope) {
        Array.prototype.slice.call((scope || document).querySelectorAll('.external-manual-time-field')).forEach(function (input) {
            if (input.dataset.timeEnhanced === '1') {
                return;
            }
            input.dataset.timeEnhanced = '1';
            input.addEventListener('input', function () {
                var digits = input.value.replace(/\D/g, '').slice(0, 4);
                input.value = digits.length >= 3 ? digits.slice(0, digits.length - 2).padStart(2, '0') + ':' + digits.slice(-2) : digits;
            });
            input.addEventListener('blur', function () {
                var match = input.value.match(/^(\d{1,2}):(\d{2})$/);
                if (!match) {
                    input.value = '';
                    return;
                }
                var hour = Math.max(0, Math.min(23, parseInt(match[1], 10) || 0));
                var minute = Math.max(0, Math.min(59, parseInt(match[2], 10) || 0));
                input.value = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
            });
        });
    }

    enhanceExternalManualTimeFields(document);
}());
</script>
<?php include 'includes/footer.php'; ?>
