<?php

// External Biometric DTR page (formerly demo-biometric.php)
// Strictly for external DTR only.
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/attendance_rules.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor', 'student']);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$studentMode = ($currentRole === 'student');

// Only allow students with external assignment track
function get_student_assignment_track(mysqli $conn, int $userId): string {
	$stmt = $conn->prepare("SELECT assignment_track FROM students WHERE user_id = ? LIMIT 1");
	if (!$stmt) return 'internal';
	$stmt->bind_param("i", $userId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return strtolower(trim((string)($row['assignment_track'] ?? 'internal')));
}

if ($studentMode) {
	$track = get_student_assignment_track($conn, $currentUserId);
	if ($track !== 'external') {
		header('Location: homepage.php');
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

	$previousAction = attendance_expected_previous($clockType);
	if ($previousAction !== null) {
		$previousColumn = attendance_action_to_column($previousAction);
		if ($previousColumn !== null && empty($record[$previousColumn])) {
			return true;
		}
	}

	return false;
}

$externalFlash = $_SESSION['external_attendance_flash'] ?? null;
unset($_SESSION['external_attendance_flash']);

$page_title = 'BioTern || External Biometric DTR';
$page_styles = [
	'assets/css/modules/pages/page-external-biometric.css',
];
$page_scripts = [
	'assets/js/theme-customizer-init.min.js',
];
include 'includes/header.php';
?>

<?php
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
		$stmt->bind_param("is", $currentUserId, $today);
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
			<h2>Quick External DTR Punch</h2>
			<div class="card mb-4">
				<div class="card-body">
					<div class="d-flex flex-column align-items-center">
						<div id="externalBiometricClock" style="font-size:2rem;font-weight:bold;letter-spacing:2px;">--:--:--</div>
						<form method="post" action="external-attendance.php" class="mt-3 w-100">
							<input type="hidden" name="external_action" value="quick_clock">
							<input type="hidden" name="clock_date" value="<?php echo htmlspecialchars($today); ?>">
							<input type="hidden" name="return_to" value="external-biometric.php">
							<div class="row g-2 mb-2 flex-wrap">
								<div class="col-12 col-sm-6 col-md-3 mb-2 mb-md-0">
									<button type="submit" name="clock_type" value="morning_in" class="btn btn-lg btn-outline-primary w-100" <?php echo external_biometric_action_locked($todayRecord, 'morning_in') ? 'disabled style=\"opacity:0.5\"' : ''; ?>>Morning In</button>
								</div>
								<div class="col-12 col-sm-6 col-md-3 mb-2 mb-md-0">
									<button type="submit" name="clock_type" value="morning_out" class="btn btn-lg btn-outline-primary w-100" <?php echo external_biometric_action_locked($todayRecord, 'morning_out') ? 'disabled style=\"opacity:0.5\"' : ''; ?>>Morning Out</button>
								</div>
								<div class="col-12 col-sm-6 col-md-3 mb-2 mb-md-0">
									<button type="submit" name="clock_type" value="afternoon_in" class="btn btn-lg btn-outline-primary w-100" <?php echo external_biometric_action_locked($todayRecord, 'afternoon_in') ? 'disabled style=\"opacity:0.5\"' : ''; ?>>Afternoon In</button>
								</div>
								<div class="col-12 col-sm-6 col-md-3 mb-2 mb-md-0">
									<button type="submit" name="clock_type" value="afternoon_out" class="btn btn-lg btn-outline-primary w-100" <?php echo external_biometric_action_locked($todayRecord, 'afternoon_out') ? 'disabled style=\"opacity:0.5\"' : ''; ?>>Afternoon Out</button>
								</div>
							</div>
							<input type="text" name="notes" class="form-control mt-2" placeholder="Optional note for this punch">
						</form>
					</div>
				</div>
			</div>

			<h3 id="manual-dtr">Manual DTR Input (Range)</h3>
			<form id="manualDtrRangeForm" class="mb-3">
				<div class="row g-3 align-items-end">
					<div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
						<label for="manual_date_from" class="form-label">Date From</label>
						<input type="date" class="form-control" name="manual_date_from" id="manual_date_from" required>
					</div>
					<div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
						<label for="manual_date_to" class="form-label">Date To</label>
						<input type="date" class="form-control" name="manual_date_to" id="manual_date_to" required>
					</div>
					<div class="col-12 col-md-4">
						<button type="button" class="btn btn-success w-100" id="generateManualDtrRows">Generate Days</button>
					</div>
				</div>
			</form>
			<form method="post" action="external-attendance.php" id="manualDtrTableForm" style="display:none;overflow-x:auto;">
				<input type="hidden" name="external_action" value="manual_range">
				<input type="hidden" name="return_to" value="external-biometric.php">
				<div class="mb-3">
					<label for="manualDtrNotes" class="form-label">Batch Notes</label>
					<input type="text" class="form-control" name="notes" id="manualDtrNotes" maxlength="255" placeholder="Optional note for this generated external DTR batch">
				</div>
				<div id="manualDtrRows" style="overflow-x:auto;"></div>
				<div class="mt-3">
					<button type="submit" class="btn btn-primary w-100">Submit Manual DTR</button>
				</div>
			</form>
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
			'<td>' + dateStr + '<input type="hidden" name="dates[]" value="' + dateStr + '"></td>' +
			'<td><input type="time" name="morning_time_in[]" class="form-control"></td>' +
			'<td><input type="time" name="morning_time_out[]" class="form-control"></td>' +
			'<td><input type="time" name="afternoon_time_in[]" class="form-control"></td>' +
			'<td><input type="time" name="afternoon_time_out[]" class="form-control"></td>' +
		'</tr>');
		idx++;
	}
	var table = '<div style="overflow-x:auto;"><table class="table table-bordered mt-3"><thead><tr><th>Date</th><th>Morning In</th><th>Morning Out</th><th>Afternoon In</th><th>Afternoon Out</th></tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
	document.getElementById('manualDtrRows').innerHTML = table;
	document.getElementById('manualDtrTableForm').style.display = '';
};
</script>
<?php include 'includes/footer.php'; ?>
