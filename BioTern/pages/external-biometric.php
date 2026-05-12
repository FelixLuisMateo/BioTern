<?php

// External Biometric DTR page (formerly demo-biometric.php)
// Strictly for external DTR only.
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/attendance_rules.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor', 'student']);
external_attendance_ensure_schema($conn);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$studentMode = ($currentRole === 'student');
$canManage = in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true);
$selectedStudentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$studentContext = null;
if ($studentMode) {
	$studentContext = external_attendance_student_context($conn, $currentUserId);

	if (!$studentContext) {
		header('Location: student-external-dtr.php');
		exit;
	}
} elseif ($canManage && $selectedStudentId > 0) {
	$studentContext = external_attendance_student_context_by_student_id($conn, $selectedStudentId);
}

function external_biometric_action_locked(array $record, string $clockType): bool {
	$column = attendance_action_to_column($clockType);
	if ($column === null || !empty($record[$column])) {
		return true;
	}

	$order = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
	$currentIndex = array_search($clockType, $order, true);
	if ($currentIndex === false) {
		return true;
	}

	for ($i = $currentIndex + 1; $i < count($order); $i++) {
		$laterColumn = attendance_action_to_column($order[$i]);
		if ($laterColumn !== null && !empty($record[$laterColumn])) {
			return true;
		}
	}

	$previousAction = external_attendance_expected_previous($clockType);
	if ($previousAction !== null) {
		$previousColumn = attendance_action_to_column($previousAction);
		if ($previousColumn !== null && empty($record[$previousColumn])) {
			return true;
		}
	}

	return false;
}

function external_biometric_month_rows(mysqli $conn, int $studentId, string $monthStart, string $monthEnd): array {
	$rows = [];
	$stmt = $conn->prepare("
		SELECT *
		FROM external_attendance
		WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
		ORDER BY attendance_date DESC, id DESC
	");
	if (!$stmt) {
		return $rows;
	}
	$stmt->bind_param('iss', $studentId, $monthStart, $monthEnd);
	$stmt->execute();
	$result = $stmt->get_result();
	while ($result && ($row = $result->fetch_assoc())) {
		$rows[] = $row;
	}
	$stmt->close();
	return $rows;
}

$externalFlash = $_SESSION['external_attendance_flash'] ?? null;
unset($_SESSION['external_attendance_flash']);

$page_title = 'BioTern || External Biometric DTR';
$page_styles = [
	'assets/css/homepage-student.css',
	'assets/css/student-dtr.css',
	'assets/css/modules/pages/page-external-attendance-student.css',
];
$page_scripts = [
	'assets/js/theme-customizer-init.min.js',
];
include 'includes/header.php';
?>

<?php
$monthHours = 0.0;
$approvedCount = 0;
$pendingCount = 0;
$monthRows = [];
$externalHoursCounting = $studentContext ? external_attendance_can_compute_hours($studentContext) : false;
$clockTypes = [
	'morning_in' => ['Morning In', 'feather-sunrise'],
	'morning_out' => ['Morning Out', 'feather-arrow-up-right'],
	'afternoon_in' => ['Afternoon In', 'feather-sun'],
	'afternoon_out' => ['Afternoon Out', 'feather-sunset'],
];

if ($studentContext) {
	$selectedMonth = date('Y-m');
	$monthStart = $selectedMonth . '-01';
	$monthEnd = date('Y-m-t', strtotime($monthStart));
	$monthRows = external_biometric_month_rows($conn, (int)$studentContext['id'], $monthStart, $monthEnd);
	foreach ($monthRows as $monthRow) {
		if ($externalHoursCounting) {
			$monthHours += (float)($monthRow['total_hours'] ?? 0);
		}
		$status = strtolower(trim((string)($monthRow['status'] ?? 'pending')));
		if ($status === 'approved') {
			$approvedCount++;
		} elseif ($status === 'pending') {
			$pendingCount++;
		}
	}
}

// Fetch today's external attendance for disabling buttons
$today = date('Y-m-d');
$todayRecord = [
	'morning_time_in' => null,
	'morning_time_out' => null,
	'afternoon_time_in' => null,
	'afternoon_time_out' => null,
];
if ($studentContext) {
	$stmt = $conn->prepare("SELECT morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out FROM external_attendance WHERE student_id = ? AND attendance_date = ? LIMIT 1");
	if ($stmt) {
		$studentId = (int)$studentContext['id'];
		$stmt->bind_param("is", $studentId, $today);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($row) $todayRecord = $row;
	}
}
?>
<main class="nxl-container">
	<div class="nxl-content">
		<div class="main-content">
			<?php if (is_array($externalFlash) && !empty($externalFlash['message'])): ?>
				<div class="alert alert-<?php echo htmlspecialchars((string)($externalFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
					<?php echo htmlspecialchars((string)$externalFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
				</div>
				<script>
				(function () {
					var flash = <?php echo json_encode($externalFlash); ?>;
					function showExternalFlash() {
						var container = document.getElementById('bioternToastContainer');
						if (!container || !flash || !flash.message) return;
						var type = (flash.type || 'info');
						var title = flash.title || '';
						var message = flash.message || '';
						var bg = type === 'success' ? '#155724' : (type === 'danger' ? '#721c24' : (type === 'warning' ? '#856404' : '#0c5460'));
						var toast = document.createElement('div');
						toast.className = 'biotern-toast';
						toast.setAttribute('style', 'pointer-events:auto;background:' + bg + ';color:#fff;padding:12px;border-radius:8px;margin-top:8px;box-shadow:0 6px 18px rgba(0,0,0,0.12)');
						toast.innerHTML = '<div style="display:flex;align-items:flex-start;gap:10px">'
							+ '<div style="flex:1"><div style="font-weight:600;margin-bottom:2px">' + (title ? String(title) : '') + '</div>'
							+ '<div style="font-size:0.95rem">' + String(message) + '</div></div>'
							+ '<button type="button" class="biotern-toast-close" aria-label="Close" style="background:transparent;border:0;color:inherit;font-size:18px;line-height:1;padding:0 6px;">&times;</button>'
							+ '</div>';
						container.appendChild(toast);
						var btn = toast.querySelector('.biotern-toast-close');
						if (btn) btn.addEventListener('click', function () { toast.remove(); });
						setTimeout(function () { try { toast.remove(); } catch (e) {} }, 6000);
					}
					document.addEventListener('DOMContentLoaded', showExternalFlash);
				}());
				</script>
			<?php endif; ?>
			<?php if ($studentContext): ?>
			<section class="external-dtr-hero">
				<div class="external-dtr-hero-main">
					<div class="external-dtr-eyebrow">
						<i class="feather-briefcase"></i>
						<span><?php echo $studentMode ? 'My External DTR' : 'Managed External DTR'; ?></span>
					</div>
					<h2><?php echo htmlspecialchars(trim((string)($studentContext['first_name'] . ' ' . $studentContext['last_name'])), ENT_QUOTES, 'UTF-8'); ?></h2>
					<p><?php echo $studentMode ? 'Record external attendance from your account. If external start is not yet approved, entries can be saved but approved hours will not reduce your remaining total.' : 'Record or review external attendance for this student. Hours count only when the student is on external track or external start override is enabled.'; ?></p>
					<div class="external-dtr-meta">
						<span><?php echo htmlspecialchars((string)($studentContext['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
						<span><?php echo htmlspecialchars((string)($studentContext['section_code'] ?? 'No section'), ENT_QUOTES, 'UTF-8'); ?></span>
						<span><?php echo htmlspecialchars((string)($studentContext['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
						<span><?php echo htmlspecialchars(trim((string)($studentContext['company_name'] ?? '')) !== '' ? (string)$studentContext['company_name'] : 'No company linked', ENT_QUOTES, 'UTF-8'); ?></span>
						<span class="<?php echo $externalHoursCounting ? 'is-counting' : 'is-paused'; ?>"><?php echo $externalHoursCounting ? 'Hours counting' : 'Hours not counting yet'; ?></span>
					</div>
				</div>
				<div class="external-dtr-clock-card">
					<div class="external-dtr-clock-label">Today</div>
					<div class="external-dtr-clock" id="externalBiometricClock"><?php echo date('h:i:s A'); ?></div>
					<div class="external-dtr-clock-date"><?php echo htmlspecialchars(date('M d, Y', strtotime($today)), ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
			</section>

			<div class="external-dtr-stats">
				<div class="external-dtr-stat">
					<span>Month Hours</span>
					<strong><?php echo number_format($monthHours, 2); ?> hrs</strong>
				</div>
				<div class="external-dtr-stat">
					<span>Remaining</span>
					<strong><?php echo (int)($studentContext['external_total_hours_remaining'] ?? 0); ?> hrs</strong>
				</div>
				<div class="external-dtr-stat">
					<span>Approved</span>
					<strong><?php echo (int)$approvedCount; ?></strong>
				</div>
				<div class="external-dtr-stat">
					<span>Pending</span>
					<strong><?php echo (int)$pendingCount; ?></strong>
				</div>
			</div>
			<?php endif; ?>

			<?php if ($studentContext): ?>
			<section class="external-quick-card mb-4">
				<div class="external-quick-heading">
					<div>
						<h3>Quick External DTR</h3>
						<p>Tap the next punch for today. Completed punches are locked automatically.</p>
					</div>
					<span><?php echo htmlspecialchars(date('F d, Y', strtotime($today)), ENT_QUOTES, 'UTF-8'); ?></span>
				</div>
					<form method="post" action="external-attendance.php" id="externalBiometricForm">
						<input type="hidden" name="external_action" value="quick_clock">
						<input type="hidden" name="clock_date" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
						<input type="hidden" name="clock_type" id="externalBiometricClockType" value="">
						<input type="hidden" name="return_to" value="external-biometric.php">
						<input type="hidden" name="student_id" value="<?php echo (int)($studentContext['id'] ?? 0); ?>">
						<input type="hidden" name="return_student_id" value="<?php echo (int)($studentContext['id'] ?? 0); ?>">
						<div class="external-clock-grid">
								<?php foreach ($clockTypes as $type => [$label, $iconClass]): ?>
								<?php $isLocked = external_biometric_action_locked($todayRecord, $type); ?>
								<button
									type="button"
									name="clock_type"
									class="clock-btn external-clock-btn<?php echo $isLocked ? ' is-complete' : ''; ?>"
									data-clock-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
									value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
									<?php echo $isLocked ? 'disabled aria-disabled="true"' : ''; ?>
								>
									<i class="<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i>
									<span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
								</button>
								<?php endforeach; ?>
						</div>
						<div class="external-note-field">
							<label for="externalPunchNotes">Notes</label>
							<input type="text" name="notes" id="externalPunchNotes" maxlength="255" placeholder="Optional note for this punch">
						</div>
					</form>
			</section>

			<section class="record-section external-manual-card mb-4" id="manual-dtr">
				<div class="external-manual-header">
					<h5 class="mb-1">Submit Missed External Time</h5>
					<p class="text-muted mb-0">Use this when your external DTR was not captured or you need to encode days from your physical DTR.</p>
				</div>
				<div class="external-manual-body">
			<div class="external-manual-guide mb-3">
				<strong>How to submit external manual DTR:</strong>
				<span>1. Choose one missed date or a date range, then click Create Time Rows.</span>
				<span>2. Pick the closest time from each dropdown, like 8:00 AM, 12:00 PM, 1:00 PM, and 5:00 PM.</span>
				<span>3. Add a short note if needed, then submit. Entries stay pending until review.</span>
			</div>
			<form id="manualDtrRangeForm" class="mb-3">
				<div class="row g-3 align-items-end">
					<div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
						<label for="manual_date_from" class="form-label">Start Date</label>
						<input type="date" class="form-control" name="manual_date_from" id="manual_date_from" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" required>
					</div>
					<div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
						<label for="manual_date_to" class="form-label">End Date</label>
						<input type="date" class="form-control" name="manual_date_to" id="manual_date_to" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" required>
					</div>
					<div class="col-12 col-md-4 d-flex align-items-end">
						<button type="button" class="btn btn-success w-100" id="generateManualDtrRows">Create Time Rows</button>
					</div>
				</div>
				<div class="external-date-range-hint" id="manualDateRangeHint">
					Start and end are both today, so this will create 1 row. Choose a later end date to create more rows.
				</div>
			</form>
			<form method="post" action="api/external-attendance.php" id="manualDtrTableForm" enctype="multipart/form-data">
				<input type="hidden" name="action" value="manual_table">
				<input type="hidden" name="external_action" value="manual_range">
				<input type="hidden" name="return_to" value="external-biometric.php">
				<input type="hidden" name="student_id" value="<?php echo (int)($studentContext['id'] ?? 0); ?>">
				<input type="hidden" name="return_student_id" value="<?php echo (int)($studentContext['id'] ?? 0); ?>">
				<div class="external-manual-extra">
					<div>
						<label for="externalProofImage" class="form-label">Proof Image (Optional)</label>
						<div class="external-file-upload">
							<input type="file" name="proof_image" id="externalProofImage" accept="image/jpeg,image/png,image/webp" data-file-name-target="externalProofImageName">
							<label for="externalProofImage"><i class="feather-upload"></i><span>Upload proof</span></label>
							<span id="externalProofImageName">No file selected</span>
						</div>
						<div class="form-text">Upload JPG, PNG, or WEBP proof up to 6MB.</div>
					</div>
					<div>
						<label for="manualDtrNotes" class="form-label">Reason / Details (Optional)</label>
						<input type="text" class="form-control" name="notes" id="manualDtrNotes" maxlength="255" placeholder="Optional note for the reviewer.">
					</div>
				</div>
				<div id="manualDtrRows" class="external-manual-rows-placeholder">
					Choose the date range, then create time rows to enter missed times.
				</div>
				<div class="mt-3" id="manualDtrSubmitWrap" style="display:none;">
					<button type="submit" class="btn btn-primary w-100">Submit External DTR for Review</button>
				</div>
			</form>
				</div>
			</section>
			<?php else: ?>
			<div class="alert alert-info">
				<?php if ($canManage && $selectedStudentId > 0): ?>
					Student ID <?php echo (int)$selectedStudentId; ?> could not be loaded for external biometric. Confirm the student still exists, then open it from the student's External DTR page.
				<?php else: ?>
					Select a student first from the Students module, then open the student's External DTR page and launch External Biometric from there.
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</main>
<script>
// Live clock
function updateExternalBiometricClock() {
	var el = document.getElementById('externalBiometricClock');
	if (!el) return;
	var now = new Date();
	el.textContent = now.toLocaleTimeString();
}
setInterval(updateExternalBiometricClock, 1000);
updateExternalBiometricClock();

Array.prototype.slice.call(document.querySelectorAll('input[type="file"][data-file-name-target]')).forEach(function(input) {
	input.addEventListener('change', function() {
		var target = document.getElementById(input.getAttribute('data-file-name-target'));
		if (!target) return;
		target.textContent = input.files && input.files.length ? input.files[0].name : 'No file selected';
	});
});

// Manual DTR range table generation
function buildExternalTimeOptions(selected) {
	var options = ['<option value="">Select time</option>'];
	for (var hour = 0; hour < 24; hour++) {
		for (var minute = 0; minute < 60; minute += 30) {
			var value = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
			var hour12 = hour % 12 || 12;
			var label = hour12 + ':' + String(minute).padStart(2, '0') + ' ' + (hour < 12 ? 'AM' : 'PM');
			options.push('<option value="' + value + '"' + (value === selected ? ' selected' : '') + '>' + label + '</option>');
		}
	}
	return options.join('');
}

function buildExternalTimeSelect(name, selected) {
	return '<select class="form-select external-manual-time-select" name="' + name + '">' + buildExternalTimeOptions(selected || '') + '</select>';
}

function parseExternalLocalDate(value) {
	var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
	if (!match) return null;
	return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

function formatExternalLocalDate(date) {
	return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
}

function externalDateDiffDays(start, end) {
	var oneDay = 24 * 60 * 60 * 1000;
	return Math.round((end.getTime() - start.getTime()) / oneDay) + 1;
}

function updateManualDateRangeHint() {
	var fromInput = document.getElementById('manual_date_from');
	var toInput = document.getElementById('manual_date_to');
	var hint = document.getElementById('manualDateRangeHint');
	if (!fromInput || !toInput || !hint) return;
	toInput.min = fromInput.value || '';
	hint.classList.remove('is-warning', 'is-success');
	var start = parseExternalLocalDate(fromInput.value);
	var end = parseExternalLocalDate(toInput.value);
	if (!start || !end) {
		hint.textContent = 'Choose both dates first. Same start and end date creates 1 row.';
		return;
	}
	if (end < start) {
		toInput.value = fromInput.value;
		end = parseExternalLocalDate(toInput.value);
	}
	var dayCount = externalDateDiffDays(start, end);
	hint.textContent = dayCount === 1
		? 'This will create 1 row for ' + fromInput.value + '. Pick a later End Date if you need more days.'
		: 'This will create ' + dayCount + ' rows, one for each date from ' + fromInput.value + ' to ' + toInput.value + '.';
}

['manual_date_from', 'manual_date_to'].forEach(function(id) {
	var input = document.getElementById(id);
	if (input) {
		input.addEventListener('input', updateManualDateRangeHint);
		input.addEventListener('change', updateManualDateRangeHint);
	}
});
updateManualDateRangeHint();

var generateManualDtrRowsButton = document.getElementById('generateManualDtrRows');
if (generateManualDtrRowsButton) generateManualDtrRowsButton.onclick = function() {
	var from = document.getElementById('manual_date_from').value;
	var to = document.getElementById('manual_date_to').value;
	var start = parseExternalLocalDate(from);
	var end = parseExternalLocalDate(to);
	if (!start || !end) {
		updateManualDateRangeHint();
		return;
	}
	if (end < start) {
		document.getElementById('manual_date_to').value = from;
		end = parseExternalLocalDate(from);
		updateManualDateRangeHint();
	}
	var dayCount = externalDateDiffDays(start, end);
	if (dayCount > 31) {
		var rangeHint = document.getElementById('manualDateRangeHint');
		rangeHint.classList.remove('is-success');
		rangeHint.classList.add('is-warning');
		rangeHint.textContent = 'Please create rows for 31 days or fewer at a time.';
		return;
	}
	var rows = [];
	for (var d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
		var dateStr = formatExternalLocalDate(d);
		rows.push('<tr>' +
			'<td data-label="Date"><strong>' + dateStr + '</strong><input type="hidden" name="dates[]" value="' + dateStr + '"></td>' +
			'<td data-label="Morning In">' + buildExternalTimeSelect('morning_time_in[]', '') + '</td>' +
			'<td data-label="Morning Out">' + buildExternalTimeSelect('morning_time_out[]', '') + '</td>' +
			'<td data-label="Afternoon In">' + buildExternalTimeSelect('afternoon_time_in[]', '') + '</td>' +
			'<td data-label="Afternoon Out">' + buildExternalTimeSelect('afternoon_time_out[]', '') + '</td>' +
		'</tr>');
	}
	var table = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 external-manual-table"><thead><tr><th>Date</th><th>Morning In</th><th>Morning Out</th><th>Afternoon In</th><th>Afternoon Out</th></tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
	var rowsWrap = document.getElementById('manualDtrRows');
	rowsWrap.className = 'external-manual-rows-generated';
	rowsWrap.innerHTML = table;
	var rangeHint = document.getElementById('manualDateRangeHint');
	rangeHint.classList.remove('is-warning');
	rangeHint.classList.add('is-success');
	rangeHint.textContent = 'Created ' + rows.length + ' time row' + (rows.length === 1 ? '' : 's') + '. Fill the missing times below, then submit for review.';
	var submitWrap = document.getElementById('manualDtrSubmitWrap');
	if (submitWrap) submitWrap.style.display = '';
};

function enhanceExternalManualTimeFields(scope) {
	Array.prototype.slice.call((scope || document).querySelectorAll('.external-manual-time-field')).forEach(function(input) {
		if (input.dataset.timeEnhanced === '1') return;
		input.dataset.timeEnhanced = '1';
		input.addEventListener('input', function() {
			var digits = input.value.replace(/\D/g, '').slice(0, 4);
			input.value = digits.length >= 3 ? digits.slice(0, digits.length - 2).padStart(2, '0') + ':' + digits.slice(-2) : digits;
		});
		input.addEventListener('blur', function() {
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

var externalBiometricForm = document.getElementById('externalBiometricForm');
var externalBiometricClockType = document.getElementById('externalBiometricClockType');
function showExternalPageToast(type, title, message) {
	if (window.BioTernNotify && typeof window.BioTernNotify.show === 'function') {
		window.BioTernNotify.show({
			type: type === 'danger' ? 'error' : type,
			title: title || '',
			message: message || '',
			duration: 5200
		});
		return;
	}
	var container = document.getElementById('bioternToastContainer');
	if (!container) return;
	var bg = type === 'success' ? '#155724' : (type === 'danger' ? '#721c24' : (type === 'warning' ? '#856404' : '#0c5460'));
	var toast = document.createElement('div');
	toast.className = 'biotern-toast';
	toast.setAttribute('style', 'pointer-events:auto;background:' + bg + ';color:#fff;padding:12px;border-radius:8px;margin-top:8px;box-shadow:0 6px 18px rgba(0,0,0,0.12)');
	toast.innerHTML = '<div style="display:flex;align-items:flex-start;gap:10px">' +
		'<div style="flex:1"><div style="font-weight:600;margin-bottom:2px">' + (title ? String(title) : '') + '</div>' +
		'<div style="font-size:0.95rem">' + String(message || '') + '</div></div>' +
		'<button type="button" class="biotern-toast-close" aria-label="Close" style="background:transparent;border:0;color:inherit;font-size:18px;line-height:1;padding:0 6px;">&times;</button>' +
		'</div>';
	container.appendChild(toast);
	var btnClose = toast.querySelector('.biotern-toast-close');
	if (btnClose) btnClose.addEventListener('click', function(){ toast.remove(); });
	setTimeout(function(){ try { toast.remove(); } catch(e){} }, 5200);
}

if (externalBiometricForm && externalBiometricClockType) {
	Array.prototype.forEach.call(externalBiometricForm.querySelectorAll('.external-clock-btn'), function(button) {
		button.addEventListener('click', function() {
			if (button.disabled) return;
			var clockType = button.getAttribute('data-clock-type') || button.value || '';
			externalBiometricClockType.value = clockType;
			<?php if ($studentMode): ?>
			(function(btn, clkType){
				btn.disabled = true;
				var fd = new FormData();
				fd.append('action', 'clock');
				fd.append('clock_type', clkType);
				fd.append('attendance_date', '<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>');
				var now = new Date();
				var hh = String(now.getHours()).padStart(2,'0');
				var mm = String(now.getMinutes()).padStart(2,'0');
				var ss = String(now.getSeconds()).padStart(2,'0');
				fd.append('time', hh + ':' + mm + ':' + ss);
				var notesInput = externalBiometricForm.querySelector('input[name="notes"]');
				if (notesInput) fd.append('notes', notesInput.value || '');
				fd.append('student_id', '<?php echo (int)($studentContext['id'] ?? 0); ?>');
				fd.append('return_to', 'external-biometric.php');
				fetch('api/external-attendance.php', { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function(res){ return res.json().catch(function(){ return null; }); })
				.then(function(json){
					if (json && (json.success || json.ok)) {
						var msg = json.message || json.msg || 'Punch saved.';
						showExternalPageToast('success', '', msg);
						setTimeout(function(){ window.location.reload(); }, 800);
					} else {
						var emsg = (json && (json.message || json.error || json.msg)) || 'Failed to save punch.';
						showExternalPageToast('danger', '', emsg);
						btn.disabled = false;
					}
				}).catch(function(){
					showExternalPageToast('danger', 'Error', 'Network error while saving punch.');
					btn.disabled = false;
				});
			})(button, clockType);
			<?php else: ?>
			if (typeof externalBiometricForm.requestSubmit === 'function') {
				externalBiometricForm.requestSubmit();
			} else {
				externalBiometricForm.submit();
			}
			window.setTimeout(function() { button.disabled = true; }, 0);
			<?php endif; ?>
		});
	});

	externalBiometricForm.addEventListener('submit', function(event) {
		if (!externalBiometricClockType.value) {
			event.preventDefault();
		}
	});
}

var manualDtrTableForm = document.getElementById('manualDtrTableForm');
if (manualDtrTableForm) {
	manualDtrTableForm.addEventListener('submit', function(event) {
		event.preventDefault();
		var submitButton = manualDtrTableForm.querySelector('button[type="submit"]');
		if (submitButton) submitButton.disabled = true;

		var formData = new FormData(manualDtrTableForm);

		fetch('api/external-attendance.php', {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function(res) {
			return res.json().catch(function() { return null; });
		}).then(function(json) {
			if (json && (json.success || json.ok)) {
				showExternalPageToast('success', 'External DTR', json.message || 'Manual external DTR saved.');
				if (submitButton) submitButton.disabled = false;
				return;
			}
			var message = (json && (json.message || json.error || json.msg)) || 'Failed to save manual external DTR.';
			showExternalPageToast('danger', 'External DTR', message);
			if (submitButton) submitButton.disabled = false;
		}).catch(function() {
			showExternalPageToast('danger', 'External DTR', 'Network error while saving manual external DTR.');
			if (submitButton) submitButton.disabled = false;
		});
	});
}
</script>
<?php include 'includes/footer.php'; ?>
