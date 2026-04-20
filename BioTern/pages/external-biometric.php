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

// Form for entering a range of days and a specific time
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$student_id = $currentUserId;
	$date_from = trim($_POST['date_from'] ?? '');
	$date_to = trim($_POST['date_to'] ?? '');
	$clock_time = trim($_POST['clock_time'] ?? '');
	$clock_type = trim($_POST['clock_type'] ?? '');

	if (!$date_from || !$date_to || !$clock_time || !$clock_type) {
		$message = 'All fields are required!';
		$message_type = 'danger';
	} else {
		$start = strtotime($date_from);
		$end = strtotime($date_to);
		if ($start === false || $end === false || $end < $start) {
			$message = 'Invalid date range!';
			$message_type = 'danger';
		} else {
			$successCount = 0;
			for ($ts = $start; $ts <= $end; $ts += 86400) {
				$date = date('Y-m-d', $ts);
				// Insert or update external_attendance for this date
				$stmt = $conn->prepare("INSERT INTO external_attendance (student_id, attendance_date, $clock_type, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE $clock_type = VALUES($clock_type), updated_at = NOW()");
				if ($stmt) {
					$stmt->bind_param("iss", $student_id, $date, $clock_time);
					$stmt->execute();
					if ($stmt->affected_rows > 0) $successCount++;
					$stmt->close();
				}
			}
			$message = "Successfully recorded $successCount entries.";
			$message_type = 'success';
		}
	}
}

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
			<h2>Quick External DTR Punch</h2>
			<div class="card mb-4">
				<div class="card-body">
					<div class="d-flex flex-column align-items-center">
						<div id="externalBiometricClock" style="font-size:2rem;font-weight:bold;letter-spacing:2px;">--:--:--</div>
						<form method="post" action="external-attendance.php" class="mt-3">
							<input type="hidden" name="external_action" value="quick_clock">
							<input type="hidden" name="clock_date" value="<?php echo htmlspecialchars($today); ?>">
							<div class="row g-2 mb-2">
								<div class="col">
									<button type="submit" name="clock_type" value="morning_in" class="btn btn-lg btn-outline-primary w-100" <?php echo !empty($todayRecord['morning_time_in']) ? 'disabled style="opacity:0.5"' : ''; ?>>Morning In</button>
								</div>
								<div class="col">
									<button type="submit" name="clock_type" value="morning_out" class="btn btn-lg btn-outline-primary w-100" <?php echo !empty($todayRecord['morning_time_out']) ? 'disabled style="opacity:0.5"' : ''; ?>>Morning Out</button>
								</div>
								<div class="col">
									<button type="submit" name="clock_type" value="afternoon_in" class="btn btn-lg btn-outline-primary w-100" <?php echo !empty($todayRecord['afternoon_time_in']) ? 'disabled style="opacity:0.5"' : ''; ?>>Afternoon In</button>
								</div>
								<div class="col">
									<button type="submit" name="clock_type" value="afternoon_out" class="btn btn-lg btn-outline-primary w-100" <?php echo !empty($todayRecord['afternoon_time_out']) ? 'disabled style="opacity:0.5"' : ''; ?>>Afternoon Out</button>
								</div>
							</div>
							<input type="text" name="notes" class="form-control mt-2" placeholder="Optional note for this punch">
						</form>
					</div>
				</div>
			</div>

			<h3>Manual DTR Input (Range)</h3>
			<form id="manualDtrRangeForm" class="mb-3">
				<div class="row g-3 align-items-end">
					<div class="col-md-4">
						<label for="manual_date_from" class="form-label">Date From</label>
						<input type="date" class="form-control" name="manual_date_from" id="manual_date_from" required>
					</div>
					<div class="col-md-4">
						<label for="manual_date_to" class="form-label">Date To</label>
						<input type="date" class="form-control" name="manual_date_to" id="manual_date_to" required>
					</div>
					<div class="col-md-4">
						<button type="button" class="btn btn-success w-100" id="generateManualDtrRows">Generate Days</button>
					</div>
				</div>
			</form>
			<form method="post" action="external-attendance.php" id="manualDtrTableForm" style="display:none;">
				<input type="hidden" name="external_action" value="manual_range">
				<div id="manualDtrRows"></div>
				<div class="mt-3">
					<button type="submit" class="btn btn-primary">Submit Manual DTR</button>
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
	var table = '<table class="table table-bordered mt-3"><thead><tr><th>Date</th><th>Morning In</th><th>Morning Out</th><th>Afternoon In</th><th>Afternoon Out</th></tr></thead><tbody>' + rows.join('') + '</tbody></table>';
	document.getElementById('manualDtrRows').innerHTML = table;
	document.getElementById('manualDtrTableForm').style.display = '';
};
</script>
<?php include 'includes/footer.php'; ?>
