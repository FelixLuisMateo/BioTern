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
$studentContext = null;
if ($studentMode) {
	$studentContext = external_attendance_student_context($conn, $currentUserId);
	$track = strtolower(trim((string)($studentContext['assignment_track'] ?? 'internal')));
	$allowExternal = ($track === 'external');

	if (!$studentContext || !$allowExternal) {
		header('Location: student-external-dtr.php');
		exit;
	}
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
	'assets/css/modules/pages/page-demo-biometric.css',
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
$clockTypes = [
	'morning_in' => ['Morning In', 'feather-sunrise'],
	'morning_out' => ['Morning Out', 'feather-arrow-up-right'],
	'afternoon_in' => ['Afternoon In', 'feather-sun'],
	'afternoon_out' => ['Afternoon Out', 'feather-sunset'],
];

if ($studentMode) {
	$selectedMonth = date('Y-m');
	$monthStart = $selectedMonth . '-01';
	$monthEnd = date('Y-m-t', strtotime($monthStart));
	$monthRows = external_biometric_month_rows($conn, (int)$studentContext['id'], $monthStart, $monthEnd);
	foreach ($monthRows as $monthRow) {
		$monthHours += (float)($monthRow['total_hours'] ?? 0);
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
if ($studentMode) {
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
			<?php endif; ?>
			<?php if ($studentMode && $studentContext): ?>
			<section class="bio-hero">
				<div class="bio-hero-chip">
					<i class="feather-shield"></i>
					<span>Account-Linked External Biometric</span>
				</div>
				<h2><?php echo htmlspecialchars(trim((string)($studentContext['first_name'] . ' ' . $studentContext['last_name'])), ENT_QUOTES, 'UTF-8'); ?></h2>
				<p>This scanner-style page is tied to your student account, so no student selector is needed. Each punch goes to your own external DTR.</p>
				<div class="student-home-meta mt-3">
					<span><?php echo htmlspecialchars((string)($studentContext['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
					<span><?php echo htmlspecialchars((string)($studentContext['section_code'] ?? 'No section'), ENT_QUOTES, 'UTF-8'); ?></span>
					<span>External target: <?php echo (int)($studentContext['external_total_hours'] ?? 0); ?> hrs</span>
					<span>Remaining: <?php echo (int)($studentContext['external_total_hours_remaining'] ?? 0); ?> hrs</span>
				</div>
			</section>

			<div class="row g-3 mb-4">
				<div class="col-md-4">
					<div class="dtr-summary-card">
						<div class="dtr-summary-label">Month Hours</div>
						<div class="dtr-summary-value"><?php echo number_format($monthHours, 2); ?> hrs</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="dtr-summary-card">
						<div class="dtr-summary-label">Approved Entries</div>
						<div class="dtr-summary-value"><?php echo (int)$approvedCount; ?></div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="dtr-summary-card">
						<div class="dtr-summary-label">Pending Review</div>
						<div class="dtr-summary-value"><?php echo (int)$pendingCount; ?></div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<div class="bio-layout mb-4">
				<aside class="scanner-card">
					<figure class="fingerprint-image">
						<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 250'%3E%3Ccircle cx='100' cy='120' r='80' fill='none' stroke='%2395b6d4' stroke-width='2'/%3E%3Ccircle cx='100' cy='120' r='70' fill='none' stroke='%23aac6df' stroke-width='1.2'/%3E%3Ccircle cx='100' cy='120' r='60' fill='none' stroke='%23bed4e7' stroke-width='1'/%3E%3Cpath d='M 100 50 Q 120 70 140 100 T 150 150' fill='none' stroke='%235b7da2' stroke-width='1.6'/%3E%3Cpath d='M 100 50 Q 80 70 60 100 T 50 150' fill='none' stroke='%235b7da2' stroke-width='1.6'/%3E%3Cpath d='M 100 50 Q 100 75 100 100 L 100 150' fill='none' stroke='%236e8fb1' stroke-width='2'/%3E%3C/svg%3E" alt="Fingerprint">
						<p class="scan-label">EXTERNAL FINGERPRINT DEMO</p>
					</figure>
					<div class="scanner-stat">
						<div><?php echo htmlspecialchars((string)($studentContext['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
						<div>External DTR for <?php echo htmlspecialchars(date('F d, Y', strtotime($today)), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
				</aside>

				<section class="clock-section">
					<h3>Quick External DTR Punch</h3>
					<div class="time-display mb-3" id="externalBiometricClock"><?php echo date('H:i:s'); ?></div>
					<form method="post" action="external-attendance.php" id="externalBiometricForm">
						<input type="hidden" name="external_action" value="quick_clock">
						<input type="hidden" name="clock_date" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
						<input type="hidden" name="clock_type" id="externalBiometricClockType" value="">
						<input type="hidden" name="return_to" value="external-biometric.php">
						<div class="form-group-custom">
							<label>Clock Type</label>
							<div class="clock-type-grid">
								<?php foreach ($clockTypes as $type => [$label, $iconClass]): ?>
								<?php $isLocked = external_biometric_action_locked($todayRecord, $type); ?>
								<button
									type="submit"
									class="clock-btn external-clock-btn<?php echo $isLocked ? ' is-complete' : ''; ?>"
									data-clock-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
									value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
									<?php echo $isLocked ? 'disabled aria-disabled="true"' : ''; ?>
								>
									<i class="<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i><br><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
								</button>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="form-group-custom">
							<label for="externalPunchNotes">Notes</label>
							<input type="text" name="notes" id="externalPunchNotes" maxlength="255" placeholder="Optional note for this punch">
						</div>
					</form>
				</section>
			</div>

			<section class="record-section mb-4" id="manual-dtr">
				<div class="card-header border-0 bg-transparent px-4 pt-4">
					<h5 class="mb-1">Submit Missed External Time</h5>
					<p class="text-muted mb-0">Use this when your external DTR was not captured or you need to encode days from your physical DTR.</p>
				</div>
				<div class="card-body pt-3">
			<div class="external-manual-guide mb-3">
				<strong>How to submit external manual DTR:</strong>
				<span>1. Choose the missed start and end date, then click Generate Date Rows.</span>
				<span>2. Pick the closest time from each dropdown, like 8:00 AM, 12:00 PM, 1:00 PM, and 5:00 PM.</span>
				<span>3. Add a short note if needed, then submit. Entries stay pending until review.</span>
			</div>
			<form id="manualDtrRangeForm" class="mb-3">
				<div class="row g-3 align-items-end">
					<div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
						<label for="manual_date_from" class="form-label">Start Date</label>
						<input type="date" class="form-control" name="manual_date_from" id="manual_date_from" required>
					</div>
					<div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
						<label for="manual_date_to" class="form-label">End Date</label>
						<input type="date" class="form-control" name="manual_date_to" id="manual_date_to" required>
					</div>
					<div class="col-12 col-md-4">
						<button type="button" class="btn btn-success w-100" id="generateManualDtrRows">Generate Date Rows</button>
					</div>
				</div>
			</form>
			<form method="post" action="external-attendance.php" id="manualDtrTableForm" style="display:none;overflow-x:auto;">
				<input type="hidden" name="external_action" value="manual_range">
				<input type="hidden" name="return_to" value="external-biometric.php">
				<div class="mb-3">
					<label for="manualDtrNotes" class="form-label">Reason / Details (Optional)</label>
					<input type="text" class="form-control" name="notes" id="manualDtrNotes" maxlength="255" placeholder="Optional note for the reviewer.">
				</div>
				<div id="manualDtrRows" style="overflow-x:auto;"></div>
				<div class="mt-3">
					<button type="submit" class="btn btn-primary w-100">Submit External DTR for Review</button>
				</div>
			</form>
				</div>
			</section>
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

document.getElementById('generateManualDtrRows').onclick = function() {
	var from = document.getElementById('manual_date_from').value;
	var to = document.getElementById('manual_date_to').value;
	if (!from || !to) return;
	var start = new Date(from);
	var end = new Date(to);
	if (isNaN(start) || isNaN(end) || end < start) return;
	var rows = [];
	var idx = 0;
	for (var d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
		var dateStr = d.toISOString().slice(0,10);
		rows.push('<tr>' +
			'<td data-label="Date"><strong>' + dateStr + '</strong><input type="hidden" name="dates[]" value="' + dateStr + '"></td>' +
			'<td data-label="Morning In">' + buildExternalTimeSelect('morning_time_in[]', '') + '</td>' +
			'<td data-label="Morning Out">' + buildExternalTimeSelect('morning_time_out[]', '') + '</td>' +
			'<td data-label="Afternoon In">' + buildExternalTimeSelect('afternoon_time_in[]', '') + '</td>' +
			'<td data-label="Afternoon Out">' + buildExternalTimeSelect('afternoon_time_out[]', '') + '</td>' +
		'</tr>');
		idx++;
	}
	var table = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 external-manual-table"><thead><tr><th>Date</th><th>Morning In</th><th>Morning Out</th><th>Afternoon In</th><th>Afternoon Out</th></tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
	document.getElementById('manualDtrRows').innerHTML = table;
	document.getElementById('manualDtrTableForm').style.display = '';
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
if (externalBiometricForm && externalBiometricClockType) {
	Array.prototype.forEach.call(externalBiometricForm.querySelectorAll('.external-clock-btn'), function(button) {
		button.addEventListener('click', function() {
			if (button.disabled) return;
			externalBiometricClockType.value = button.getAttribute('data-clock-type') || button.value || '';
			window.setTimeout(function() {
				button.disabled = true;
			}, 0);
		});
	});
	externalBiometricForm.addEventListener('submit', function(event) {
		if (!externalBiometricClockType.value) {
			event.preventDefault();
		}
	});
}
</script>
<?php include 'includes/footer.php'; ?>
