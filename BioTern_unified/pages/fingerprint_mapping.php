<?php
ob_start();
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? DB_PORT : 3306;

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
    if ($conn->connect_error) {
        ob_end_clean();
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
}

$conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");

$flashType = 'success';
$msg = '';
$editFingerId = (int)($_GET['edit_finger_id'] ?? 0);
$editUserId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['mapping_action'] ?? 'save'));

    try {
        if ($action === 'delete') {
            $fingerId = (int)($_POST['finger_id'] ?? 0);
            if ($fingerId <= 0) {
                throw new RuntimeException('Invalid fingerprint ID.');
            }

            $stmt = $conn->prepare("DELETE FROM fingerprint_user_map WHERE finger_id = ?");
            $stmt->bind_param('i', $fingerId);
            $stmt->execute();
            $stmt->close();
            $msg = 'Mapping removed.';
        } else {
            $fingerId = (int)($_POST['finger_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($fingerId <= 0 || $userId <= 0) {
                throw new RuntimeException('Fingerprint ID and user are required.');
            }

            $studentCheck = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $studentCheck->bind_param('i', $userId);
            $studentCheck->execute();
            $studentResult = $studentCheck->get_result();
            $studentRow = $studentResult ? $studentResult->fetch_assoc() : null;
            $studentCheck->close();

            if (!$studentRow) {
                throw new RuntimeException('Selected user is not linked to a student record yet, so attendance cannot be shown for that fingerprint.');
            }

            $duplicateCheck = $conn->prepare("SELECT finger_id FROM fingerprint_user_map WHERE user_id = ? AND finger_id <> ? LIMIT 1");
            $duplicateCheck->bind_param('ii', $userId, $fingerId);
            $duplicateCheck->execute();
            $duplicateResult = $duplicateCheck->get_result();
            $duplicateRow = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
            $duplicateCheck->close();

            if ($duplicateRow) {
                throw new RuntimeException('That user is already mapped to another fingerprint.');
            }

            $stmt = $conn->prepare("REPLACE INTO fingerprint_user_map (finger_id, user_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $fingerId, $userId);
            $stmt->execute();
            $stmt->close();
            $msg = 'Mapping updated.';
        }
    } catch (Throwable $e) {
        $flashType = 'danger';
        $msg = $e->getMessage();
    }
}

$mappings = [];
$mappingSql = "
    SELECT
        m.finger_id,
        m.user_id,
        u.name AS user_name,
        s.id AS student_row_id,
        s.student_id AS student_number,
        CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS student_name
    FROM fingerprint_user_map m
    LEFT JOIN users u ON u.id = m.user_id
    LEFT JOIN students s ON s.user_id = m.user_id
    ORDER BY m.finger_id ASC
";
$mappingRes = $conn->query($mappingSql);
if ($mappingRes instanceof mysqli_result) {
    while ($row = $mappingRes->fetch_assoc()) {
        $mappings[] = $row;
        if ($editFingerId > 0 && (int)($row['finger_id'] ?? 0) === $editFingerId) {
            $editUserId = (int)($row['user_id'] ?? 0);
        }
    }
    $mappingRes->close();
}

$mappedUserIds = [];
foreach ($mappings as $mapping) {
    $mappedUserIds[] = (int)($mapping['user_id'] ?? 0);
}

$availableUsers = [];
$availableSql = "
    SELECT
        u.id,
        u.name,
        s.id AS student_row_id,
        s.student_id AS student_number,
        CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS student_name
    FROM users u
    INNER JOIN students s ON s.user_id = u.id
    ORDER BY u.name ASC, u.id ASC
";
$availableRes = $conn->query($availableSql);
if ($availableRes instanceof mysqli_result) {
    while ($row = $availableRes->fetch_assoc()) {
        $userId = (int)($row['id'] ?? 0);
        if ($userId > 0 && (!in_array($userId, $mappedUserIds, true) || $userId === $editUserId)) {
            $availableUsers[] = $row;
        }
    }
    $availableRes->close();
}

$orphanMappings = array_values(array_filter($mappings, static function (array $row): bool {
    return (int)($row['student_row_id'] ?? 0) <= 0;
}));

$detectedFingerprints = [];
$rawLogRes = $conn->query("SELECT raw_data FROM biometric_raw_logs ORDER BY id DESC");
if ($rawLogRes instanceof mysqli_result) {
    while ($row = $rawLogRes->fetch_assoc()) {
        $entry = json_decode((string)($row['raw_data'] ?? ''), true);
        if (!is_array($entry)) {
            continue;
        }

        $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
        $timeValue = trim((string)($entry['time'] ?? ''));
        if ($fingerId <= 0) {
            continue;
        }

        if (!isset($detectedFingerprints[$fingerId])) {
            $detectedFingerprints[$fingerId] = [
                'finger_id' => $fingerId,
                'last_seen' => $timeValue,
                'is_mapped' => false,
                'mapped_user_name' => '',
                'mapped_user_id' => 0,
                'student_name' => '',
                'student_number' => '',
            ];
        }
    }
    $rawLogRes->close();
}

foreach ($mappings as $mapping) {
    $fingerId = (int)($mapping['finger_id'] ?? 0);
    if ($fingerId <= 0) {
        continue;
    }

    if (!isset($detectedFingerprints[$fingerId])) {
        $detectedFingerprints[$fingerId] = [
            'finger_id' => $fingerId,
            'last_seen' => '',
            'is_mapped' => false,
            'mapped_user_name' => '',
            'mapped_user_id' => 0,
            'student_name' => '',
            'student_number' => '',
        ];
    }

    $detectedFingerprints[$fingerId]['is_mapped'] = true;
    $detectedFingerprints[$fingerId]['mapped_user_name'] = (string)($mapping['user_name'] ?? '');
    $detectedFingerprints[$fingerId]['mapped_user_id'] = (int)($mapping['user_id'] ?? 0);
    $detectedFingerprints[$fingerId]['student_name'] = trim((string)($mapping['student_name'] ?? ''));
    $detectedFingerprints[$fingerId]['student_number'] = (string)($mapping['student_number'] ?? '');
}

ksort($detectedFingerprints);
$unmappedDetectedCount = 0;
foreach ($detectedFingerprints as $detectedFingerprint) {
    if (empty($detectedFingerprint['is_mapped'])) {
        $unmappedDetectedCount++;
    }
}

$page_title = 'Fingerprint Mapping';
$base_href = '';
include __DIR__ . '/../includes/header.php';
ob_end_flush();
?>
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Fingerprint to Student Mapping</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Fingerprint Mapping</li>
                </ul>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> py-2">
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($orphanMappings)): ?>
            <div class="alert alert-warning">
                Some mapped fingerprints are attached to users with no student profile yet. Those fingerprints will not appear in `attendance.php` until the user is linked in the `students` table.
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Mapped Fingerprints</div>
                        <div class="fs-3 fw-bold"><?php echo count($mappings); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Available Student Users</div>
                        <div class="fs-3 fw-bold"><?php echo count($availableUsers); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Detected Unmapped Prints</div>
                        <div class="fs-3 fw-bold"><?php echo $unmappedDetectedCount; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Add / Update Mapping</strong></div>
            <div class="card-body">
                <?php if ($editFingerId > 0): ?>
                    <div class="alert alert-info py-2">
                        Editing fingerprint <strong><?php echo $editFingerId; ?></strong>. Choose a different student user to rewire it, then save.
                        <a href="legacy_router.php?file=fingerprint_mapping.php" class="ms-2">Cancel edit</a>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="mapping_action" value="save">
                    <div class="col-md-3">
                        <label class="form-label" for="finger_id">Fingerprint ID</label>
                        <input type="number" class="form-control" name="finger_id" id="finger_id" min="1" value="<?php echo $editFingerId > 0 ? $editFingerId : ''; ?>" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="user_id">Available Student User</label>
                        <select class="form-select" name="user_id" id="user_id" required>
                            <option value="">Select student-linked user</option>
                            <?php foreach ($availableUsers as $user): ?>
                                <option value="<?php echo (int)$user['id']; ?>" <?php echo ((int)$user['id'] === $editUserId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    - <?php echo htmlspecialchars(trim((string)$user['student_name']), ENT_QUOTES, 'UTF-8'); ?>
                                    (Student #: <?php echo htmlspecialchars((string)$user['student_number'], ENT_QUOTES, 'UTF-8'); ?>, User ID: <?php echo (int)$user['id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Mapped users are removed from this list automatically.</small>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><?php echo $editFingerId > 0 ? 'Update Mapping' : 'Save Mapping'; ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Current Mappings</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>User</th>
                                <th>Student Link</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($mappings)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No fingerprint mappings yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mappings as $map): ?>
                                <tr>
                                    <td><?php echo (int)$map['finger_id']; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($map['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted">User ID: <?php echo (int)$map['user_id']; ?></small>
                                    </td>
                                    <td>
                                        <?php if ((int)($map['student_row_id'] ?? 0) > 0): ?>
                                            <div class="fw-semibold"><?php echo htmlspecialchars(trim((string)$map['student_name']), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted">Student #: <?php echo htmlspecialchars((string)$map['student_number'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-soft-warning text-warning">No student profile linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="legacy_router.php?file=fingerprint_mapping.php&edit_finger_id=<?php echo (int)$map['finger_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this fingerprint mapping?');">
                                            <input type="hidden" name="mapping_action" value="delete">
                                            <input type="hidden" name="finger_id" value="<?php echo (int)$map['finger_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><strong>Detected Fingerprints From Machine Logs</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>Last Seen</th>
                                <th>Status</th>
                                <th>Mapped To</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($detectedFingerprints)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No fingerprints detected from machine logs yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detectedFingerprints as $fingerprint): ?>
                                <tr>
                                    <td><?php echo (int)$fingerprint['finger_id']; ?></td>
                                    <td><?php echo htmlspecialchars($fingerprint['last_seen'] !== '' ? $fingerprint['last_seen'] : 'Not seen in raw logs yet', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if (!empty($fingerprint['is_mapped'])): ?>
                                            <span class="badge bg-soft-success text-success">Mapped</span>
                                        <?php else: ?>
                                            <span class="badge bg-soft-warning text-warning">Needs Mapping</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($fingerprint['is_mapped'])): ?>
                                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$fingerprint['mapped_user_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small class="text-muted">
                                                User ID: <?php echo (int)$fingerprint['mapped_user_id']; ?>
                                                <?php if ($fingerprint['student_name'] !== ''): ?>
                                                    | <?php echo htmlspecialchars((string)$fingerprint['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                                <?php if ($fingerprint['student_number'] !== ''): ?>
                                                    (<?php echo htmlspecialchars((string)$fingerprint['student_number'], ENT_QUOTES, 'UTF-8'); ?>)
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Not mapped yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="legacy_router.php?file=fingerprint_mapping.php&edit_finger_id=<?php echo (int)$fingerprint['finger_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <?php echo !empty($fingerprint['is_mapped']) ? 'Edit Mapping' : 'Map Fingerprint'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>
