<?php
$host = '127.0.0.1';
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

$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS internal_hours INT(11) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS external_hours INT(11) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS school_year VARCHAR(50) NULL");

$courseColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM courses");
if ($columnResult) {
	while ($column = $columnResult->fetch_assoc()) {
		$courseColumns[] = strtolower((string)$column['Field']);
	}
}

$hasColumn = function ($columnName) use ($courseColumns) {
	return in_array(strtolower($columnName), $courseColumns, true);
};

$hasCourseHead = $hasColumn('course_head');
$hasDeletedAt = $hasColumn('deleted_at');

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
	die('Invalid course id.');
}

$course = null;
$stmt = $conn->prepare("SELECT id, name, code" . ($hasCourseHead ? ", course_head" : "") . ($hasColumn('internal_hours') ? ", internal_hours" : "") . ($hasColumn('external_hours') ? ", external_hours" : "") . ($hasColumn('school_year') ? ", school_year" : "") . " FROM courses WHERE id = ? LIMIT 1");
if ($stmt) {
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$course = $stmt->get_result()->fetch_assoc();
	$stmt->close();
}

if (!$course) {
	die('Course not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim((string)($_POST['name'] ?? ''));
	$code = strtoupper(trim((string)($_POST['code'] ?? '')));
	$course_head = trim((string)($_POST['course_head'] ?? ''));
	$internal_hours = max(0, (int)($_POST['internal_hours'] ?? 0));
	$external_hours = max(0, (int)($_POST['external_hours'] ?? 0));
	$school_year = trim((string)($_POST['school_year'] ?? ''));

	if ($name === '' || $code === '') {
		$message = 'Course name and code are required.';
		$message_type = 'danger';
	} elseif ($hasCourseHead && $course_head === '') {
		$message = 'Course head is required for this database schema.';
		$message_type = 'danger';
	} else {
		$checkQuery = "SELECT id FROM courses WHERE code = ? AND id <> ?" . ($hasDeletedAt ? " AND deleted_at IS NULL" : "") . " LIMIT 1";
		$checkStmt = $conn->prepare($checkQuery);
		if ($checkStmt) {
			$checkStmt->bind_param("si", $code, $id);
			$checkStmt->execute();
			$exists = $checkStmt->get_result()->fetch_assoc();
			$checkStmt->close();

			if ($exists) {
				$message = 'Course code already exists.';
				$message_type = 'warning';
			} else {
				if ($hasCourseHead) {
					$updateQuery = "UPDATE courses SET name = ?, code = ?, course_head = ?, internal_hours = ?, external_hours = ?, school_year = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
					$updateStmt = $conn->prepare($updateQuery);
					if ($updateStmt) {
						$updateStmt->bind_param("sssiisi", $name, $code, $course_head, $internal_hours, $external_hours, $school_year, $id);
					}
				} else {
					$updateQuery = "UPDATE courses SET name = ?, code = ?, internal_hours = ?, external_hours = ?, school_year = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
					$updateStmt = $conn->prepare($updateQuery);
					if ($updateStmt) {
						$updateStmt->bind_param("ssiisi", $name, $code, $internal_hours, $external_hours, $school_year, $id);
					}
				}

				if (!isset($updateStmt) || !$updateStmt) {
					$message = 'Failed to prepare update statement.';
					$message_type = 'danger';
				} elseif ($updateStmt->execute()) {
					$message = 'Course updated successfully.';
					$message_type = 'success';
					$course['name'] = $name;
					$course['code'] = $code;
					if ($hasCourseHead) $course['course_head'] = $course_head;
					$course['internal_hours'] = $internal_hours;
					$course['external_hours'] = $external_hours;
					$course['school_year'] = $school_year;
				} else {
					$message = 'Failed to update course: ' . $updateStmt->error;
					$message_type = 'danger';
				}

				if (isset($updateStmt) && $updateStmt) {
					$updateStmt->close();
				}
			}
		} else {
			$message = 'Failed to prepare duplicate-check statement.';
			$message_type = 'danger';
		}
	}
}

$selectFields = ['id', 'name', 'code'];
if ($hasColumn('course_head')) {
	$selectFields[] = 'course_head';
}
if ($hasColumn('internal_hours')) {
	$selectFields[] = 'internal_hours';
}
if ($hasColumn('external_hours')) {
	$selectFields[] = 'external_hours';
}
if ($hasColumn('school_year')) {
	$selectFields[] = 'school_year';
}
if ($hasColumn('created_at')) {
	$selectFields[] = 'created_at';
}

$whereClause = $hasDeletedAt ? " WHERE deleted_at IS NULL" : "";
$orderBy = $hasColumn('created_at') ? "created_at DESC" : "id DESC";

$courses = [];
$listQuery = "SELECT " . implode(', ', $selectFields) . " FROM courses" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 200";
$listResult = $conn->query($listQuery);
if ($listResult) {
	while ($row = $listResult->fetch_assoc()) {
		$courses[] = $row;
	}
}

$page_title = 'Edit Course';
include 'includes/header.php';
?>
<div class="page-header">
	<div class="page-header-left d-flex align-items-center">
		<div class="page-header-title">
			<h5 class="m-b-10">Edit Course</h5>
		</div>
		<ul class="breadcrumb">
			<li class="breadcrumb-item"><a href="index.php">Home</a></li>
			<li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
			<li class="breadcrumb-item">Edit</li>
		</ul>
	</div>
	<div class="page-header-right ms-auto">
		<a href="courses.php" class="btn btn-outline-secondary">Back to List</a>
	</div>
</div>

<div class="main-content">
	<div class="row">
		<div class="col-lg-5">
			<div class="card stretch stretch-full">
				<div class="card-header">
					<h5 class="card-title mb-0">Course Form</h5>
				</div>
				<div class="card-body">
					<?php if ($message !== ''): ?>
						<div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
							<?php echo htmlspecialchars($message); ?>
						</div>
					<?php endif; ?>
					<form method="post" action="">
						<input type="hidden" name="id" value="<?php echo (int)$course['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Course Name *</label>
							<input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars((string)$course['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Course Code *</label>
							<input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string)$course['code']); ?>" required>
						</div>
						<?php if ($hasCourseHead): ?>
							<div class="mb-3">
								<label class="form-label">Course Head *</label>
								<input type="text" name="course_head" class="form-control" value="<?php echo htmlspecialchars((string)($course['course_head'] ?? '')); ?>" required>
							</div>
						<?php endif; ?>
						<div class="mb-3">
							<label class="form-label">Internal Hours</label>
							<input type="number" min="0" name="internal_hours" class="form-control" value="<?php echo (int)($course['internal_hours'] ?? 0); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">External Hours</label>
							<input type="number" min="0" name="external_hours" class="form-control" value="<?php echo (int)($course['external_hours'] ?? 0); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">School Year</label>
							<input type="text" name="school_year" class="form-control" value="<?php echo htmlspecialchars((string)($course['school_year'] ?? '')); ?>" placeholder="2026-2027">
						</div>
						<button type="submit" class="btn btn-primary">Save Course</button>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7">
			<div class="card stretch stretch-full">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="card-title mb-0">Recent Courses</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover mb-0">
							<thead>
								<tr>
									<th>ID</th>
									<th>Code</th>
									<th>Name</th>
									<?php if ($hasCourseHead): ?><th>Course Head</th><?php endif; ?>
									<?php if ($hasColumn('internal_hours')): ?><th>Internal</th><?php endif; ?>
									<?php if ($hasColumn('external_hours')): ?><th>External</th><?php endif; ?>
									<?php if ($hasColumn('school_year')): ?><th>School Year</th><?php endif; ?>
									<?php if ($hasColumn('created_at')): ?><th>Created</th><?php endif; ?>
									<th></th>
								</tr>
							</thead>
							<tbody>
							<?php if (!empty($courses)): ?>
								<?php foreach ($courses as $c): ?>
									<tr>
										<td><?php echo (int)$c['id']; ?></td>
										<td><?php echo htmlspecialchars((string)($c['code'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars((string)($c['name'] ?? '')); ?></td>
										<?php if ($hasCourseHead): ?>
											<td><?php echo htmlspecialchars((string)($c['course_head'] ?? '-')); ?></td>
										<?php endif; ?>
										<?php if ($hasColumn('internal_hours')): ?>
											<td><?php echo (int)($c['internal_hours'] ?? 0); ?></td>
										<?php endif; ?>
										<?php if ($hasColumn('external_hours')): ?>
											<td><?php echo (int)($c['external_hours'] ?? 0); ?></td>
										<?php endif; ?>
										<?php if ($hasColumn('school_year')): ?>
											<td><?php echo htmlspecialchars((string)($c['school_year'] ?? '-')); ?></td>
										<?php endif; ?>
										<?php if ($hasColumn('created_at')): ?>
											<td><?php echo htmlspecialchars((string)($c['created_at'] ?? '-')); ?></td>
										<?php endif; ?>
										<td><a href="courses-edit.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr><td colspan="6" class="text-center py-4 text-muted">No courses found.</td></tr>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
				<div class="card-footer bg-transparent border-top-0 pt-3 pb-4 px-3">
					<a href="courses.php" class="btn btn-outline-secondary w-100">View All</a>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
include 'includes/footer.php';
$conn->close();
?>

