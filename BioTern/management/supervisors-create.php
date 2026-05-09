<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/offices.php';
/** @var mysqli $conn */

require_roles_page(['admin']);

$message = '';
$message_type = 'info';

biotern_offices_ensure_schema($conn);

$supervisorColumns = [];
$supervisorColumnResult = $conn->query("SHOW COLUMNS FROM supervisors");
if ($supervisorColumnResult) {
    while ($column = $supervisorColumnResult->fetch_assoc()) {
        $supervisorColumns[] = strtolower((string)$column['Field']);
    }
}

$hasSupervisorColumn = function (string $columnName) use ($supervisorColumns): bool {
    return in_array(strtolower($columnName), $supervisorColumns, true);
};

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$offices = biotern_offices_all($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $middle_name = trim((string)($_POST['middle_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department_id_raw = trim((string)($_POST['department_id'] ?? ''));
    $department_id = $department_id_raw !== '' ? (int)$department_id_raw : null;
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $office_location = trim((string)($_POST['office_location'] ?? ''));
    $office_ids = isset($_POST['office_ids']) && is_array($_POST['office_ids']) ? $_POST['office_ids'] : [];
    $bio = trim((string)($_POST['bio'] ?? ''));
    $profile_picture = '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || $password === '' || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $message_type = 'danger';
    } else {
        if ($message !== '' && $message_type === 'danger') {
            // keep message and do not insert
        } else {
            $deptForInsert = $department_id ?? 0;
            $profileFieldValue = $specialization;
            $displayName = trim($first_name . ' ' . $last_name);

            $duplicateStmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            if (!$duplicateStmt) {
                $message = 'Failed to prepare user lookup.';
                $message_type = 'danger';
            } else {
                $duplicateStmt->bind_param('ss', $username, $email);
                $duplicateStmt->execute();
                $duplicateUser = $duplicateStmt->get_result()->fetch_assoc();
                $duplicateStmt->close();

                if ($duplicateUser) {
                    $message = 'A user with that username or email already exists.';
                    $message_type = 'warning';
                }
            }
        }

        if ($message !== '' && ($message_type === 'danger' || $message_type === 'warning')) {
        } else {
            $deptForInsert = $department_id ?? 0;
            $profileFieldValue = $specialization;
            $displayName = trim($first_name . ' ' . $last_name);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $insertSql = '';
            if ($hasSupervisorColumn('specialization') && $hasSupervisorColumn('office_location')) {
                $insertSql = 'INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, office_location, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?)';
            } elseif ($hasSupervisorColumn('specialization') && $hasSupervisorColumn('office')) {
                $insertSql = 'INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, office, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?)';
            } elseif ($hasSupervisorColumn('specialization')) {
                $insertSql = 'INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, specialization, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?)';
            } elseif ($hasSupervisorColumn('office')) {
                $insertSql = 'INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, office, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?)';
                $profileFieldValue = $office_location;
            } elseif ($hasSupervisorColumn('office_location')) {
                $insertSql = 'INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?)';
                $profileFieldValue = $office_location;
            } else {
                $insertSql = 'INSERT INTO supervisors (user_id, first_name, last_name, middle_name, email, phone, department_id, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?)';
            }

            $conn->begin_transaction();
            $user_id = 0;

            try {
                $userStmt = $conn->prepare('INSERT INTO users (name, username, email, password, role, is_active, profile_picture, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                if (!$userStmt) {
                    throw new RuntimeException('Failed to prepare user insert statement.');
                }
                $role = 'supervisor';
                $userProfilePicture = '';
                $userStmt->bind_param('sssssis', $displayName, $username, $email, $passwordHash, $role, $is_active, $userProfilePicture);
                if (!$userStmt->execute()) {
                    $err = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Failed to create linked user: ' . $err);
                }
                $user_id = (int)$userStmt->insert_id;
                $userStmt->close();

                $stmt = $conn->prepare($insertSql);
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare supervisor insert statement.');
                }
                if ($hasSupervisorColumn('specialization') && ($hasSupervisorColumn('office') || $hasSupervisorColumn('office_location'))) {
                    $stmt->bind_param('isssssissssi', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForInsert, $specialization, $office_location, $bio, $profile_picture, $is_active);
                } elseif ($hasSupervisorColumn('specialization') || $hasSupervisorColumn('office') || $hasSupervisorColumn('office_location')) {
                    $stmt->bind_param('isssssisssi', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForInsert, $profileFieldValue, $bio, $profile_picture, $is_active);
                } else {
                    $stmt->bind_param('isssssissi', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForInsert, $bio, $profile_picture, $is_active);
                }

                if ($stmt->execute()) {
                    $supervisorId = (int)$stmt->insert_id;
                    $stmt->close();
                    biotern_supervisor_sync_offices($conn, $supervisorId, $office_ids, $office_location);
                    $conn->commit();
                    header('Location: supervisors.php');
                    exit;
                }
                $errorText = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Failed to create supervisor: ' . $errorText);
            } catch (Throwable $e) {
                $conn->rollback();
                if ((int)$e->getCode() === 1062 || stripos($e->getMessage(), 'Duplicate') !== false) {
                    $message = 'Duplicate supervisor record detected (username/email already used).';
                    $message_type = 'warning';
                } else {
                    $message = $e->getMessage();
                    $message_type = 'danger';
                }
            }
        }
    }
}

$page_title = 'Create Supervisor';
$page_styles = ['assets/css/modules/management/management-create-shared.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Create Supervisor</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="supervisors.php">Supervisors</a></li>
            <li class="breadcrumb-item">Create</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title mb-0">Supervisor Form</h5></div>
        <div class="card-body">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" autocomplete="off" autocapitalize="off" spellcheck="false" required>
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>"><?php echo h($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control"></div>
                <div class="col-md-4">
                    <label class="form-label">Office Assignments</label>
                    <select name="office_ids[]" class="form-select" multiple size="4">
                        <?php foreach ($offices as $office): ?>
                            <option value="<?php echo (int)$office['id']; ?>"><?php echo h($office['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hold Ctrl to select multiple offices.</small>
                </div>
                <div class="col-md-4"><label class="form-label">Add Office</label><input type="text" name="office_location" class="form-control" placeholder="Example: ComLab 2"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_create" checked><label class="form-check-label" for="is_active_create">Active</label></div>
                <div class="col-12 create-form-actions app-form-actions">
                    <button type="submit" class="btn btn-primary">Save Supervisor</button>
                    <a href="supervisors.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; $conn->close(); ?>





