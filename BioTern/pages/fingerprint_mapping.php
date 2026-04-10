<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_ops.php';

require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

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

$conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (\n    finger_id INT NOT NULL,\n    user_id INT NOT NULL,\n    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n    PRIMARY KEY (finger_id),\n    UNIQUE KEY uniq_fingerprint_user_map_user_id (user_id),\n    KEY idx_fingerprint_user_map_user_id (user_id)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$colCreatedAt = $conn->query("SHOW COLUMNS FROM fingerprint_user_map LIKE 'created_at'");
if ($colCreatedAt instanceof mysqli_result) {
    if ($colCreatedAt->num_rows === 0) {
        $conn->query("ALTER TABLE fingerprint_user_map ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    $colCreatedAt->close();
}

$colUpdatedAt = $conn->query("SHOW COLUMNS FROM fingerprint_user_map LIKE 'updated_at'");
if ($colUpdatedAt instanceof mysqli_result) {
    if ($colUpdatedAt->num_rows === 0) {
        $conn->query("ALTER TABLE fingerprint_user_map ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    $colUpdatedAt->close();
}

$idxUniqueUser = $conn->query("SHOW INDEX FROM fingerprint_user_map WHERE Key_name = 'uniq_fingerprint_user_map_user_id'");
if ($idxUniqueUser instanceof mysqli_result) {
    if ($idxUniqueUser->num_rows === 0) {
        $conn->query("ALTER TABLE fingerprint_user_map ADD UNIQUE KEY uniq_fingerprint_user_map_user_id (user_id)");
    }
    $idxUniqueUser->close();
}

$idxUser = $conn->query("SHOW INDEX FROM fingerprint_user_map WHERE Key_name = 'idx_fingerprint_user_map_user_id'");
if ($idxUser instanceof mysqli_result) {
    if ($idxUser->num_rows === 0) {
        $conn->query("ALTER TABLE fingerprint_user_map ADD KEY idx_fingerprint_user_map_user_id (user_id)");
    }
    $idxUser->close();
}

$flashType = 'success';
$msg = '';
$editFingerId = (int)($_GET['edit_finger_id'] ?? 0);
$editStudentUserId = 0;
$editPersonnelUserId = 0;
$filterCourseId = (int)($_GET['filter_course_id'] ?? 0);
$filterSectionId = (int)($_GET['filter_section_id'] ?? 0);
$addStudentCourseId = (int)($_GET['add_course_id'] ?? 0);
$addStudentSectionId = (int)($_GET['add_section_id'] ?? 0);
$authorizedRoles = ['admin', 'coordinator', 'supervisor'];

if (isset($_SESSION['fingerprint_mapping_flash']) && is_array($_SESSION['fingerprint_mapping_flash'])) {
    $flashType = (string)($_SESSION['fingerprint_mapping_flash']['type'] ?? 'success');
    $msg = (string)($_SESSION['fingerprint_mapping_flash']['message'] ?? '');
    unset($_SESSION['fingerprint_mapping_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['mapping_action'] ?? 'save_student'));

    try {
        if ($action === 'delete') {
            $fingerId = (int)($_POST['finger_id'] ?? 0);
            if ($fingerId <= 0) {
                throw new RuntimeException('Invalid fingerprint ID.');
            }

            $stmt = $conn->prepare('DELETE FROM fingerprint_user_map WHERE finger_id = ?');
            $stmt->bind_param('i', $fingerId);
            $stmt->execute();
            $stmt->close();

            biometric_ops_log_audit(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                $role,
                'fingerprint_mapping_removed',
                'fingerprint_mapping',
                (string)$fingerId,
                ['finger_id' => $fingerId]
            );

            $_SESSION['fingerprint_mapping_flash'] = ['type' => 'success', 'message' => 'Mapping removed.'];
            header('Location: fingerprint_mapping.php');
            exit;
        }

        $fingerId = (int)($_POST['finger_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($fingerId <= 0 || $userId <= 0) {
            throw new RuntimeException('Fingerprint ID and user are required.');
        }

        $userStmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        $userRow = $userRes ? $userRes->fetch_assoc() : null;
        $userStmt->close();

        if (!$userRow) {
            throw new RuntimeException('Selected user does not exist.');
        }

        $targetRole = strtolower(trim((string)($userRow['role'] ?? '')));

        $studentCheck = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
        $studentCheck->bind_param('i', $userId);
        $studentCheck->execute();
        $studentResult = $studentCheck->get_result();
        $studentRow = $studentResult ? $studentResult->fetch_assoc() : null;
        $studentCheck->close();

        if ($action === 'save_student' && !$studentRow) {
            throw new RuntimeException('Selected user is not linked to a student record yet.');
        }

        if ($action === 'save_personnel' && !in_array($targetRole, $authorizedRoles, true)) {
            throw new RuntimeException('Selected user is not an authorized personnel account.');
        }

        if (!$studentRow && !in_array($targetRole, $authorizedRoles, true)) {
            throw new RuntimeException('Selected user must be either a student-linked user or authorized personnel.');
        }

        $duplicateCheck = $conn->prepare('SELECT finger_id FROM fingerprint_user_map WHERE user_id = ? AND finger_id <> ? LIMIT 1');
        $duplicateCheck->bind_param('ii', $userId, $fingerId);
        $duplicateCheck->execute();
        $duplicateResult = $duplicateCheck->get_result();
        $duplicateRow = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
        $duplicateCheck->close();

        if ($duplicateRow) {
            throw new RuntimeException('That user is already mapped to another fingerprint.');
        }

        $stmt = $conn->prepare('INSERT INTO fingerprint_user_map (finger_id, user_id, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), updated_at = CURRENT_TIMESTAMP');
        $stmt->bind_param('ii', $fingerId, $userId);
        $stmt->execute();
        $stmt->close();

        biometric_ops_log_audit(
            $conn,
            (int)($_SESSION['user_id'] ?? 0),
            $role,
            'fingerprint_mapping_saved',
            'fingerprint_mapping',
            (string)$fingerId,
            ['finger_id' => $fingerId, 'user_id' => $userId, 'mapping_action' => $action]
        );

        $_SESSION['fingerprint_mapping_flash'] = ['type' => 'success', 'message' => 'Mapping updated.'];
        header('Location: fingerprint_mapping.php');
        exit;
    } catch (Throwable $e) {
        $_SESSION['fingerprint_mapping_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        $redirect = 'fingerprint_mapping.php';
        if ($editFingerId > 0) {
            $redirect .= '?edit_finger_id=' . $editFingerId;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$courses = [];
$courseRes = $conn->query('SELECT id, name FROM courses ORDER BY name ASC');
if ($courseRes instanceof mysqli_result) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
    $courseRes->close();
}

$sections = [];
$sectionRes = $conn->query("SELECT id, course_id, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($sectionRes instanceof mysqli_result) {
    while ($row = $sectionRes->fetch_assoc()) {
        $sections[] = $row;
    }
    $sectionRes->close();
}

$mappings = [];
$mappingSql = "
    SELECT
        m.finger_id,
        m.user_id,
        m.created_at,
        m.updated_at,
        u.name AS user_name,
        LOWER(TRIM(COALESCE(u.role, ''))) AS user_role,
        s.id AS student_row_id,
        s.student_id AS student_number,
        s.created_at AS enrolled_at,
        s.course_id,
        s.section_id,
        c.name AS course_name,
        COALESCE(NULLIF(sec.code, ''), sec.name) AS section_label,
        CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS student_name
    FROM fingerprint_user_map m
    LEFT JOIN users u ON u.id = m.user_id
    LEFT JOIN students s ON s.user_id = m.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    ORDER BY m.finger_id ASC
";
$mappingRes = $conn->query($mappingSql);
if ($mappingRes instanceof mysqli_result) {
    while ($row = $mappingRes->fetch_assoc()) {
        $mappings[] = $row;

        if ($editFingerId > 0 && (int)($row['finger_id'] ?? 0) === $editFingerId) {
            if ((int)($row['student_row_id'] ?? 0) > 0) {
                $editStudentUserId = (int)($row['user_id'] ?? 0);
            } elseif (in_array((string)($row['user_role'] ?? ''), $authorizedRoles, true)) {
                $editPersonnelUserId = (int)($row['user_id'] ?? 0);
            }
        }
    }
    $mappingRes->close();
}

$mappedUserIds = [];
foreach ($mappings as $mapping) {
    $mappedUserIds[] = (int)($mapping['user_id'] ?? 0);
}

$latestMappedFingerId = 0;
foreach ($mappings as $mapping) {
    $candidateFingerId = (int)($mapping['finger_id'] ?? 0);
    if ($candidateFingerId > $latestMappedFingerId) {
        $latestMappedFingerId = $candidateFingerId;
    }
}

$availableStudentUsers = [];
$availableStudentSql = "
    SELECT
        u.id,
        u.name,
        s.id AS student_row_id,
        s.student_id AS student_number,
        s.created_at AS enrolled_at,
        s.course_id,
        s.section_id,
        c.name AS course_name,
        COALESCE(NULLIF(sec.code, ''), sec.name) AS section_label,
        CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS student_name
    FROM users u
    INNER JOIN students s ON s.user_id = u.id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    ORDER BY u.name ASC, u.id ASC
";
$availableStudentRes = $conn->query($availableStudentSql);
if ($availableStudentRes instanceof mysqli_result) {
    while ($row = $availableStudentRes->fetch_assoc()) {
        $userId = (int)($row['id'] ?? 0);
        if ($addStudentCourseId > 0 && (int)($row['course_id'] ?? 0) !== $addStudentCourseId) {
            continue;
        }

        if ($addStudentSectionId > 0 && (int)($row['section_id'] ?? 0) !== $addStudentSectionId) {
            continue;
        }

        if ($userId > 0 && (!in_array($userId, $mappedUserIds, true) || $userId === $editStudentUserId || $userId === $editPersonnelUserId)) {
            $availableStudentUsers[] = $row;
        }
    }
    $availableStudentRes->close();
}

$availablePersonnelUsers = [];
$availablePersonnelSql = "
    SELECT
        u.id,
        u.name,
        LOWER(TRIM(COALESCE(u.role, ''))) AS role_name
    FROM users u
    WHERE LOWER(TRIM(COALESCE(u.role, ''))) IN ('admin', 'coordinator', 'supervisor')
    ORDER BY u.name ASC, u.id ASC
";
$availablePersonnelRes = $conn->query($availablePersonnelSql);
if ($availablePersonnelRes instanceof mysqli_result) {
    while ($row = $availablePersonnelRes->fetch_assoc()) {
        $userId = (int)($row['id'] ?? 0);
        if ($userId > 0 && (!in_array($userId, $mappedUserIds, true) || $userId === $editPersonnelUserId || $userId === $editStudentUserId)) {
            $availablePersonnelUsers[] = $row;
        }
    }
    $availablePersonnelRes->close();
}

$studentMappings = [];
$personnelMappings = [];
foreach ($mappings as $row) {
    if ((int)($row['student_row_id'] ?? 0) > 0) {
        $studentMappings[] = $row;
        continue;
    }

    if (in_array((string)($row['user_role'] ?? ''), $authorizedRoles, true)) {
        $personnelMappings[] = $row;
        continue;
    }

    $studentMappings[] = $row;
}

$filteredStudentMappings = array_values(array_filter($studentMappings, static function (array $row) use ($filterCourseId, $filterSectionId): bool {
    if ($filterCourseId > 0 && (int)($row['course_id'] ?? 0) !== $filterCourseId) {
        return false;
    }

    if ($filterSectionId > 0 && (int)($row['section_id'] ?? 0) !== $filterSectionId) {
        return false;
    }

    return true;
}));

$orphanMappings = array_values(array_filter($studentMappings, static function (array $row) use ($authorizedRoles): bool {
    return (int)($row['student_row_id'] ?? 0) <= 0 && !in_array((string)($row['user_role'] ?? ''), $authorizedRoles, true);
}));

$detectedFingerprints = [];
$extractFingerId = static function (array $entry): int {
    $candidates = [
        $entry['finger_id'] ?? null,
        $entry['fingerId'] ?? null,
        $entry['id'] ?? null,
        $entry['user_id'] ?? null,
        $entry['userId'] ?? null,
        $entry['uid'] ?? null,
        $entry['enroll_number'] ?? null,
        $entry['enrollNumber'] ?? null,
        $entry['EnrollNumber'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === null) {
            continue;
        }
        $parsed = (int)trim((string)$candidate);
        if ($parsed > 0) {
            return $parsed;
        }
    }

    return 0;
};

$rawLogRes = $conn->query('SELECT raw_data FROM biometric_raw_logs ORDER BY id DESC');
if ($rawLogRes instanceof mysqli_result) {
    while ($row = $rawLogRes->fetch_assoc()) {
        $entry = json_decode((string)($row['raw_data'] ?? ''), true);
        if (!is_array($entry)) {
            continue;
        }

        if (isset($entry['data']) && is_array($entry['data'])) {
            $entry = $entry['data'];
        }

        $fingerId = $extractFingerId($entry);
        $timeValue = trim((string)($entry['time'] ?? ''));
        if ($fingerId <= 0) {
            continue;
        }

        if (!isset($detectedFingerprints[$fingerId])) {
            $detectedFingerprints[$fingerId] = [
                'finger_id' => $fingerId,
                'last_seen' => $timeValue,
                'machine_user_name' => '',
                'is_mapped' => false,
                'mapped_user_name' => '',
                'mapped_user_id' => 0,
                'student_name' => '',
                'student_number' => '',
                'mapped_created_at' => '',
            ];
        }
    }
    $rawLogRes->close();
}

$extractBridgeRows = static function ($decoded): array {
    if (!is_array($decoded)) {
        return [];
    }

    foreach (['rows', 'users', 'data', 'items', 'list'] as $key) {
        if (isset($decoded[$key]) && is_array($decoded[$key])) {
            return $decoded[$key];
        }
    }

    $isList = array_keys($decoded) === range(0, count($decoded) - 1);
    return $isList ? $decoded : [$decoded];
};

$bridgeCacheRes = $conn->query('SELECT users_json FROM biometric_bridge_user_cache ORDER BY id DESC LIMIT 1');
if ($bridgeCacheRes instanceof mysqli_result) {
    $bridgeCacheRow = $bridgeCacheRes->fetch_assoc();
    $bridgeCacheRes->close();

    $usersJson = trim((string)($bridgeCacheRow['users_json'] ?? ''));
    if ($usersJson !== '') {
        $decodedUsers = json_decode($usersJson, true);
        if (is_array($decodedUsers)) {
            foreach ($extractBridgeRows($decodedUsers) as $userRow) {
                if (!is_array($userRow)) {
                    continue;
                }

                $fingerId = (int)trim((string)($userRow['id'] ?? $userRow['ID'] ?? $userRow['user_id'] ?? $userRow['userId'] ?? $userRow['EnrollNumber'] ?? 0));
                if ($fingerId <= 0) {
                    continue;
                }

                $machineName = trim((string)($userRow['name'] ?? $userRow['Name'] ?? $userRow['user_name'] ?? ''));

                if (!isset($detectedFingerprints[$fingerId])) {
                    $detectedFingerprints[$fingerId] = [
                        'finger_id' => $fingerId,
                        'last_seen' => '',
                        'machine_user_name' => $machineName,
                        'is_mapped' => false,
                        'mapped_user_name' => '',
                        'mapped_user_id' => 0,
                        'student_name' => '',
                        'student_number' => '',
                        'mapped_created_at' => '',
                    ];
                } elseif ($machineName !== '' && trim((string)($detectedFingerprints[$fingerId]['machine_user_name'] ?? '')) === '') {
                    $detectedFingerprints[$fingerId]['machine_user_name'] = $machineName;
                }
            }
        }
    }
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
            'machine_user_name' => '',
            'is_mapped' => false,
            'mapped_user_name' => '',
            'mapped_user_id' => 0,
            'student_name' => '',
            'student_number' => '',
            'mapped_created_at' => '',
        ];
    }

    $detectedFingerprints[$fingerId]['is_mapped'] = true;
    $detectedFingerprints[$fingerId]['mapped_user_name'] = (string)($mapping['user_name'] ?? '');
    $detectedFingerprints[$fingerId]['mapped_user_id'] = (int)($mapping['user_id'] ?? 0);
    $detectedFingerprints[$fingerId]['student_name'] = trim((string)($mapping['student_name'] ?? ''));
    $detectedFingerprints[$fingerId]['student_number'] = (string)($mapping['student_number'] ?? '');
    $detectedFingerprints[$fingerId]['mapped_created_at'] = (string)($mapping['created_at'] ?? '');
}

ksort($detectedFingerprints);
$unmappedDetectedCount = 0;
foreach ($detectedFingerprints as $detectedFingerprint) {
    if (empty($detectedFingerprint['is_mapped'])) {
        $unmappedDetectedCount++;
    }
}

$availableFingerIds = [];
foreach ($detectedFingerprints as $detectedFingerprint) {
    if (!empty($detectedFingerprint['is_mapped'])) {
        continue;
    }
    $availableFingerIds[] = [
        'finger_id' => (int)($detectedFingerprint['finger_id'] ?? 0),
        'last_seen' => (string)($detectedFingerprint['last_seen'] ?? ''),
        'machine_user_name' => (string)($detectedFingerprint['machine_user_name'] ?? ''),
    ];
}

$latestDetectedFingerId = empty($detectedFingerprints) ? 0 : (int)max(array_keys($detectedFingerprints));

$fingerprintSelectOptions = [];
foreach ($detectedFingerprints as $detectedFingerprint) {
    $fingerId = (int)($detectedFingerprint['finger_id'] ?? 0);
    if ($fingerId <= 0) {
        continue;
    }

    $isMapped = !empty($detectedFingerprint['is_mapped']);
    if ($isMapped && $fingerId !== $editFingerId) {
        continue;
    }

    $fingerprintSelectOptions[] = [
        'finger_id' => $fingerId,
        'last_seen' => (string)($detectedFingerprint['last_seen'] ?? ''),
        'machine_user_name' => (string)($detectedFingerprint['machine_user_name'] ?? ''),
        'is_mapped' => $isMapped,
    ];
}

if ($editFingerId > 0 && !isset($detectedFingerprints[$editFingerId])) {
    $fingerprintSelectOptions[] = [
        'finger_id' => $editFingerId,
        'last_seen' => '',
        'machine_user_name' => '',
        'is_mapped' => false,
    ];
}

$page_title = 'Fingerprint Mapping';
$page_body_class = 'page-fingerprint-mapping';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
];
$page_scripts = [
    'assets/js/modules/pages/biometric-management-runtime.js',
];
$base_href = '';
include __DIR__ . '/../includes/header.php';
ob_end_flush();
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Fingerprint Mapping</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Fingerprint Mapping</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto bio-console-header-actions">
                <div class="page-header-right-items">
                    <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                        <a href="biometric-machine.php" class="btn btn-light-brand">
                            <i class="feather-cpu me-2"></i>
                            <span>F20H Manager</span>
                        </a>
                        <a href="attendance.php" class="btn btn-outline-secondary">
                            <i class="feather-clock me-2"></i>
                            <span>Attendance DTR</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bio-console-shell">
        <div class="card bio-console-hero">
            <div class="card-body">
                <div class="bio-console-hero-grid">
                    <div>
                        <span class="bio-console-eyebrow">Biometric Access</span>
                        <h3>Link each fingerprint to the correct BioTern account</h3>
                        <p>Review detected F20H fingerprint IDs, assign them to students or authorized personnel, and keep unmapped scans from turning into attendance confusion.</p>
                        <div class="bio-console-pill-list">
                            <span class="bio-console-pill">Latest detected ID: <?php echo $latestDetectedFingerId > 0 ? $latestDetectedFingerId : 'N/A'; ?></span>
                            <span class="bio-console-pill">Latest mapped ID: <?php echo $latestMappedFingerId > 0 ? $latestMappedFingerId : 'N/A'; ?></span>
                            <span class="bio-console-pill">Unmapped detected: <?php echo (int)$unmappedDetectedCount; ?></span>
                        </div>
                    </div>
                    <div class="bio-console-note-surface">
                        <span class="badge bg-soft-info text-info">Mapping Workflow</span>
                        <h6>Best results come from the latest machine logs</h6>
                        <p>Read users and logs in the F20H Machine Manager first, then finish the mapping here so attendance sync can recognize each fingerprint quickly.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?> py-2">
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($orphanMappings)): ?>
            <div class="alert alert-warning">
                Some mapped fingerprints are attached to users with no student profile and no authorized role. Please remap those records.
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-xl-3">
                <div class="card stretch stretch-full bio-console-stat-card">
                    <div class="card-body">
                        <span class="bio-console-stat-label">Student Mappings</span>
                        <span class="bio-console-stat-value"><?php echo count($studentMappings); ?></span>
                        <span class="bio-console-stat-meta">Students already matched with a machine fingerprint.</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card stretch stretch-full bio-console-stat-card">
                    <div class="card-body">
                        <span class="bio-console-stat-label">Authorized Personnel</span>
                        <span class="bio-console-stat-value"><?php echo count($personnelMappings); ?></span>
                        <span class="bio-console-stat-meta">Admins, coordinators, and supervisors with linked prints.</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card stretch stretch-full bio-console-stat-card">
                    <div class="card-body">
                        <span class="bio-console-stat-label">Available Students</span>
                        <span class="bio-console-stat-value"><?php echo count($availableStudentUsers); ?></span>
                        <span class="bio-console-stat-meta">Student-linked accounts still waiting for a fingerprint.</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card stretch stretch-full bio-console-stat-card">
                    <div class="card-body">
                        <span class="bio-console-stat-label">Detected Unmapped Prints</span>
                        <span class="bio-console-stat-value"><?php echo $unmappedDetectedCount; ?></span>
                        <span class="bio-console-stat-meta">Finger IDs seen in device logs that still need assignment.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fingerprint-map-card bio-console-panel">
            <div class="card-header"><strong>Add or Update Student Mapping</strong></div>
            <div class="card-body">
            <?php if (!empty($availableFingerIds)): ?>
                <div class="bio-console-inline-note mb-3">
                    <div class="bio-console-inline-note-title">Available Finger IDs To Map</div>
                    <div class="bio-console-chip-list">
                        <?php foreach ($availableFingerIds as $item): ?>
                            <span class="bio-console-chip">
                                ID <?php echo (int)$item['finger_id']; ?>
                                <?php if ($item['machine_user_name'] !== ''): ?>
                                    | Name: <?php echo htmlspecialchars($item['machine_user_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                                <?php if ($item['last_seen'] !== ''): ?>
                                    | Last seen: <?php echo htmlspecialchars($item['last_seen'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <form method="get" class="row g-2 align-items-end mb-3 fingerprint-form">
                    <input type="hidden" name="filter_course_id" value="<?php echo $filterCourseId; ?>">
                    <input type="hidden" name="filter_section_id" value="<?php echo $filterSectionId; ?>">
                    <?php if ($editFingerId > 0): ?>
                        <input type="hidden" name="edit_finger_id" value="<?php echo $editFingerId; ?>">
                    <?php endif; ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="add_course_id">Filter Student Users by Course</label>
                        <select class="form-select" name="add_course_id" id="add_course_id">
                            <option value="0">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" <?php echo ((int)$course['id'] === $addStudentCourseId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$course['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="add_section_id">Filter Student Users by Section</label>
                        <select class="form-select" name="add_section_id" id="add_section_id">
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo (int)$section['id']; ?>" data-course-id="<?php echo (int)($section['course_id'] ?? 0); ?>" <?php echo ((int)$section['id'] === $addStudentSectionId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$section['section_label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-xl-4 fm-actions">
                        <button type="submit" class="btn btn-primary">Apply Student Filters</button>
                        <a href="fingerprint_mapping.php<?php echo ($editFingerId > 0) ? '?edit_finger_id=' . $editFingerId : ''; ?>" class="btn btn-light">Clear</a>
                    </div>
                </form>

                <?php if ($editFingerId > 0): ?>
                    <div class="alert alert-info py-2">
                        Editing fingerprint <strong><?php echo $editFingerId; ?></strong> for student mapping.
                        <a href="fingerprint_mapping.php" class="ms-2">Cancel edit</a>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-2 align-items-start fingerprint-form">
                    <input type="hidden" name="mapping_action" value="save_student">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label" for="student_finger_id">Fingerprint ID</label>
                        <select class="form-select" name="finger_id" id="student_finger_id" required>
                            <option value="">Select detected fingerprint</option>
                            <?php foreach ($fingerprintSelectOptions as $fingerprintOption): ?>
                                <option value="<?php echo (int)$fingerprintOption['finger_id']; ?>" <?php echo ((int)$fingerprintOption['finger_id'] === $editFingerId) ? 'selected' : ''; ?>>
                                    ID <?php echo (int)$fingerprintOption['finger_id']; ?>
                                    <?php if ($fingerprintOption['machine_user_name'] !== ''): ?> - Name: <?php echo htmlspecialchars($fingerprintOption['machine_user_name'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    <?php if ($fingerprintOption['last_seen'] !== ''): ?> - Last seen: <?php echo htmlspecialchars($fingerprintOption['last_seen'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    <?php if (!empty($fingerprintOption['is_mapped'])): ?> [currently mapped]<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">This list comes from fingerprints received from the machine.</small>
                    </div>
                    <div class="col-12 col-md-8 col-xl-7">
                        <label class="form-label" for="student_user_id">Available Student User</label>
                        <select class="form-select" name="user_id" id="student_user_id" required>
                            <option value="">Select student-linked user</option>
                            <?php foreach ($availableStudentUsers as $user): ?>
                                <option value="<?php echo (int)$user['id']; ?>" <?php echo ((int)$user['id'] === $editStudentUserId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    - <?php echo htmlspecialchars(trim((string)$user['student_name']), ENT_QUOTES, 'UTF-8'); ?>
                                    (Course: <?php echo htmlspecialchars((string)($user['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>,
                                    Section: <?php echo htmlspecialchars((string)($user['section_label'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>,
                                    Student #: <?php echo htmlspecialchars((string)$user['student_number'], ENT_QUOTES, 'UTF-8'); ?>,
                                    Enrolled: <?php echo !empty($user['enrolled_at']) ? htmlspecialchars(date('M d, Y', strtotime((string)$user['enrolled_at'])), ENT_QUOTES, 'UTF-8') : 'N/A'; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Mapped users are removed from this list automatically.</small>
                    </div>
                    <div class="col-12 col-xl-2 submit-wrap">
                        <button type="submit" class="btn btn-primary w-100"><?php echo $editStudentUserId > 0 ? 'Update Student Mapping' : 'Save Student Mapping'; ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4 fingerprint-map-card bio-console-panel">
            <div class="card-header"><strong>Authorized Personnel Mapping (Admin, Coordinator, Supervisor)</strong></div>
            <div class="card-body">
                <?php if ($editFingerId > 0): ?>
                    <div class="alert alert-info py-2">
                        Use this section when the fingerprint belongs to authorized personnel.
                        <a href="fingerprint_mapping.php" class="ms-2">Cancel edit</a>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-2 align-items-start fingerprint-form">
                    <input type="hidden" name="mapping_action" value="save_personnel">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label" for="personnel_finger_id">Fingerprint ID</label>
                        <select class="form-select" name="finger_id" id="personnel_finger_id" required>
                            <option value="">Select detected fingerprint</option>
                            <?php foreach ($fingerprintSelectOptions as $fingerprintOption): ?>
                                <option value="<?php echo (int)$fingerprintOption['finger_id']; ?>" <?php echo ((int)$fingerprintOption['finger_id'] === $editFingerId) ? 'selected' : ''; ?>>
                                    ID <?php echo (int)$fingerprintOption['finger_id']; ?>
                                    <?php if ($fingerprintOption['machine_user_name'] !== ''): ?> - Name: <?php echo htmlspecialchars($fingerprintOption['machine_user_name'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    <?php if ($fingerprintOption['last_seen'] !== ''): ?> - Last seen: <?php echo htmlspecialchars($fingerprintOption['last_seen'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    <?php if (!empty($fingerprintOption['is_mapped'])): ?> [currently mapped]<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">This list comes from fingerprints received from the machine.</small>
                    </div>
                    <div class="col-12 col-md-8 col-xl-7">
                        <label class="form-label" for="personnel_user_id">Authorized Personnel User</label>
                        <select class="form-select" name="user_id" id="personnel_user_id" required>
                            <option value="">Select authorized personnel</option>
                            <?php foreach ($availablePersonnelUsers as $user): ?>
                                <option value="<?php echo (int)$user['id']; ?>" <?php echo ((int)$user['id'] === $editPersonnelUserId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo htmlspecialchars(ucfirst((string)$user['role_name']), ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only Admin, Coordinator, and Supervisor accounts are listed here.</small>
                    </div>
                    <div class="col-12 col-xl-2 submit-wrap">
                        <button type="submit" class="btn btn-primary w-100"><?php echo $editPersonnelUserId > 0 ? 'Update Personnel Mapping' : 'Save Personnel Mapping'; ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4 fingerprint-map-card bio-console-panel">
            <div class="card-header"><strong>Current Student Mappings</strong></div>
            <div class="card-body border-bottom">
                <form method="get" class="row g-2 align-items-end fingerprint-form">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="filter_course_id">Filter by Course</label>
                        <select class="form-select" name="filter_course_id" id="filter_course_id">
                            <option value="0">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" <?php echo ((int)$course['id'] === $filterCourseId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$course['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="filter_section_id">Filter by Section</label>
                        <select class="form-select" name="filter_section_id" id="filter_section_id">
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo (int)$section['id']; ?>" data-course-id="<?php echo (int)($section['course_id'] ?? 0); ?>" <?php echo ((int)$section['id'] === $filterSectionId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$section['section_label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-xl-4 fm-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="fingerprint_mapping.php" class="btn btn-light">Clear</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bio-console-table">
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>User</th>
                                <th>Student Link</th>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Mapped Info</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($filteredStudentMappings)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No student mappings match the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredStudentMappings as $map): ?>
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
                                            <br>
                                            <small class="text-muted">Enrolled: <?php echo !empty($map['enrolled_at']) ? htmlspecialchars(date('M d, Y', strtotime((string)$map['enrolled_at'])), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-soft-warning text-warning">No student profile linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($map['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($map['section_label'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php $createdAt = (string)($map['created_at'] ?? ''); ?>
                                        <?php $updatedAt = (string)($map['updated_at'] ?? ''); ?>
                                        <div class="text-muted small">Created: <?php echo $createdAt !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime($createdAt)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                        <div class="text-muted small">Updated: <?php echo $updatedAt !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime($updatedAt)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fingerprint-actions ms-auto">
                                            <a href="fingerprint_mapping.php?edit_finger_id=<?php echo (int)$map['finger_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="post" class="d-inline" data-confirm="Remove this fingerprint mapping?">
                                                <input type="hidden" name="mapping_action" value="delete">
                                                <input type="hidden" name="finger_id" value="<?php echo (int)$map['finger_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4 bio-console-panel">
            <div class="card-header"><strong>Authorized Personnel Mappings</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bio-console-table">
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Mapped Info</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($personnelMappings)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No authorized personnel mappings yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personnelMappings as $map): ?>
                                <tr>
                                    <td><?php echo (int)$map['finger_id']; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($map['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted">User ID: <?php echo (int)$map['user_id']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-info text-info"><?php echo htmlspecialchars(ucfirst((string)($map['user_role'] ?? 'Unknown')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td>
                                        <?php $createdAt = (string)($map['created_at'] ?? ''); ?>
                                        <?php $updatedAt = (string)($map['updated_at'] ?? ''); ?>
                                        <div class="text-muted small">Created: <?php echo $createdAt !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime($createdAt)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                        <div class="text-muted small">Updated: <?php echo $updatedAt !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime($updatedAt)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fingerprint-actions ms-auto">
                                            <a href="fingerprint_mapping.php?edit_finger_id=<?php echo (int)$map['finger_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="post" class="d-inline" data-confirm="Remove this fingerprint mapping?">
                                                <input type="hidden" name="mapping_action" value="delete">
                                                <input type="hidden" name="finger_id" value="<?php echo (int)$map['finger_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4 bio-console-panel">
            <div class="card-header"><strong>Detected Fingerprints From Machine Logs</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bio-console-table">
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
                                            <div class="text-muted small">Mapped: <?php echo $fingerprint['mapped_created_at'] !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime((string)$fingerprint['mapped_created_at'])), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Not mapped yet</span>
                                            <?php if (!empty($fingerprint['machine_user_name'])): ?>
                                                <div class="text-muted small">Machine Name: <?php echo htmlspecialchars((string)$fingerprint['machine_user_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="fingerprint-actions ms-auto">
                                            <a href="fingerprint_mapping.php?edit_finger_id=<?php echo (int)$fingerprint['finger_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <?php echo !empty($fingerprint['is_mapped']) ? 'Edit Mapping' : 'Map Fingerprint'; ?>
                                            </a>
                                        </div>
                                    </td>
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
</main>
<?php
include __DIR__ . '/../includes/footer.php';
$conn->close();
?>
