<?php
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$message = '';
$message_type = 'info';

try {
	$conn = new mysqli($host, $db_user, $db_password, $db_name);
	if ($conn->connect_error) {
		throw new Exception("Connection failed: " . $conn->connect_error);
	}
} catch (Exception $e) {
	die("Database Error: " . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

if ($id <= 0) {
	die('Invalid department id.');
}

$dept = null;
$stmt = $conn->prepare("SELECT id, name, code, department_head, contact_email FROM departments WHERE id = ? LIMIT 1");
if ($stmt) {
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$dept = $stmt->get_result()->fetch_assoc();
	$stmt->close();
}

if (!$dept) {
	die('Department not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim((string)($_POST['name'] ?? ''));
	$code = strtoupper(trim((string)($_POST['code'] ?? '')));
	$department_head = trim((string)($_POST['department_head'] ?? ''));
	$contact_email = trim((string)($_POST['contact_email'] ?? ''));

	if ($name === '' || $code === '') {
		$message = 'Department name and code are required.';
		$message_type = 'danger';
	} else {
		// check duplicate code excluding current id
		$check_stmt = $conn->prepare("SELECT id FROM departments WHERE code = ? AND id <> ? LIMIT 1");
		if ($check_stmt) {
			$check_stmt->bind_param('si', $code, $id);
			$check_stmt->execute();
			$exists = $check_stmt->get_result()->fetch_assoc();
			$check_stmt->close();

			if ($exists) {
				$message = 'Department code already exists.';
				$message_type = 'warning';
			} else {
				$update_stmt = $conn->prepare(
					"UPDATE departments SET name = ?, code = ?, department_head = ?, contact_email = ?, updated_at = NOW() WHERE id = ? LIMIT 1"
				);
				if ($update_stmt) {
					$update_stmt->bind_param('ssssi', $name, $code, $department_head, $contact_email, $id);
					if ($update_stmt->execute()) {
						$message = 'Department updated successfully.';
						$message_type = 'success';
						// refresh $dept values
						$dept['name'] = $name;
						$dept['code'] = $code;
						$dept['department_head'] = $department_head;
						$dept['contact_email'] = $contact_email;
					} else {
						$message = 'Failed to update department: ' . $update_stmt->error;
						$message_type = 'danger';
					}
					$update_stmt->close();
				} else {
					$message = 'Failed to prepare update statement.';
					$message_type = 'danger';
				}
			}
		} else {
			$message = 'Failed to prepare duplicate-check statement.';
			$message_type = 'danger';
		}
	}
}

$departments = [];
$list_result = $conn->query(
	"SELECT id, name, code, department_head, contact_email, created_at FROM departments WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 200"
);
if ($list_result) {
	while ($row = $list_result->fetch_assoc()) {
		$departments[] = $row;
	}
}

ob_start();
?>
<div class="page-header">
	<div class="page-header-left d-flex align-items-center">
		<div class="page-header-title">
			<h5 class="m-b-10">Edit Department</h5>
		</div>
		<ul class="breadcrumb">
			<li class="breadcrumb-item"><a href="index.php">Home</a></li>
			<li class="breadcrumb-item"><a href="departments.php">Departments</a></li>
			<li class="breadcrumb-item">Edit</li>
		</ul>
	</div>
	<div class="page-header-right ms-auto">
		<a href="departments.php" class="btn btn-outline-secondary">Back to List</a>
	</div>
</div>

<div class="main-content">
	<div class="row">
		<div class="col-lg-5">
			<div class="card stretch stretch-full">
				<div class="card-header">
					<h5 class="card-title mb-0">Department Form</h5>
				</div>
				<div class="card-body">
					<?php if ($message !== ''): ?>
						<div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>
					<form method="post" action="">
						<input type="hidden" name="id" value="<?php echo (int)$dept['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Department Name *</label>
							<input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars((string)$dept['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Department Code *</label>
							<input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string)$dept['code']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Department Head</label>
							<input type="text" name="department_head" class="form-control" value="<?php echo htmlspecialchars((string)($dept['department_head'] ?? '')); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Contact Email</label>
							<input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars((string)($dept['contact_email'] ?? '')); ?>">
						</div>
						<button type="submit" class="btn btn-primary">Save Department</button>
						<a href="departments.php" class="btn btn-outline-secondary ms-2">View All</a>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7">
			<div class="card stretch stretch-full">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="card-title mb-0">Existing Departments</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover mb-0">
							<thead>
								<tr>
									<th>ID</th>
									<th>Name</th>
									<th>Code</th>
									<th>Head</th>
									<th>Email</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							<?php if (!empty($departments)): ?>
								<?php foreach ($departments as $d): ?>
									<tr>
										<td><?php echo (int)$d['id']; ?></td>
										<td><?php echo htmlspecialchars((string)$d['name']); ?></td>
										<td><?php echo htmlspecialchars((string)$d['code']); ?></td>
										<td><?php echo htmlspecialchars((string)($d['department_head'] ?? '-')); ?></td>
										<td><?php echo htmlspecialchars((string)($d['contact_email'] ?? '-')); ?></td>
										<td><a href="departments-edit.php?id=<?php echo (int)$d['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr><td colspan="6" class="text-center py-4 text-muted">No departments found.</td></tr>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
$template_page_content = ob_get_clean();
include 'template.php';
$conn->close();
?>

