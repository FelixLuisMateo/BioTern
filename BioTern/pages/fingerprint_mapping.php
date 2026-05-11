<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_ops.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

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
$filterCourseId = (int)($_GET['filter_course_id'] ?? 0);
$filterSectionId = (int)($_GET['filter_section_id'] ?? 0);
$detectedStatus = strtolower(trim((string)($_GET['detected_status'] ?? 'all')));
if (!in_array($detectedStatus, ['all', 'mapped', 'unmapped'], true)) {
    $detectedStatus = 'all';
}
$detectedQuery = trim((string)($_GET['detected_query'] ?? ''));
$unmappedQuery = trim((string)($_GET['unmapped_query'] ?? ''));
$unmappedAdminOnly = (int)($_GET['unmapped_admin_only'] ?? 0) === 1;
$printMode = strtolower(trim((string)($_GET['print'] ?? '')));
if (!in_array($printMode, ['mapped', 'unmapped_students'], true)) {
    $printMode = '';
}
$authorizedRoles = ['admin', 'coordinator', 'supervisor'];

function fingerprint_mapping_requeue_raw_logs(mysqli $conn, int $fingerId): int
{
    if ($fingerId <= 0) {
        return 0;
    }

    $res = $conn->query('SELECT id, raw_data FROM biometric_raw_logs ORDER BY id DESC LIMIT 5000');
    if (!($res instanceof mysqli_result)) {
        return 0;
    }

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $entry = json_decode((string)($row['raw_data'] ?? ''), true);
        if (!is_array($entry)) {
            continue;
        }

        $entryFingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
        if ($entryFingerId === $fingerId) {
            $ids[] = (int)$row['id'];
        }
    }
    $res->close();

    if ($ids === []) {
        return 0;
    }

    $idList = implode(',', array_map('intval', array_unique($ids)));
    $conn->query("UPDATE biometric_raw_logs SET processed = 0 WHERE id IN ({$idList})");
    return max(0, (int)$conn->affected_rows);
}

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

        $requeuedLogs = fingerprint_mapping_requeue_raw_logs($conn, $fingerId);

        biometric_ops_log_audit(
            $conn,
            (int)($_SESSION['user_id'] ?? 0),
            $role,
            'fingerprint_mapping_saved',
            'fingerprint_mapping',
            (string)$fingerId,
            ['finger_id' => $fingerId, 'user_id' => $userId, 'mapping_action' => $action]
        );

        $_SESSION['fingerprint_mapping_flash'] = [
            'type' => 'success',
            'message' => 'Mapping updated.' . ($requeuedLogs > 0 ? ' Requeued ' . $requeuedLogs . ' raw log(s) for attendance import.' : ''),
        ];
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
$sectionRes = $conn->query("SELECT id, course_id, code, name, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($sectionRes instanceof mysqli_result) {
    while ($row = $sectionRes->fetch_assoc()) {
        $row['section_label'] = biotern_format_section_label((string)($row['code'] ?? ''), (string)($row['name'] ?? ''));
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
        s.assignment_track,
        s.internal_total_hours_remaining,
        s.external_total_hours_remaining,
        s.course_id,
        s.section_id,
        c.name AS course_name,
        sec.code AS section_code,
        sec.name AS section_name,
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
        $row['section_label'] = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
        $mappings[] = $row;
    }
    $mappingRes->close();
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

$unmappedStudents = [];
$unmappedStudentSql = "
    SELECT
        s.id AS student_row_id,
        s.student_id AS student_number,
        s.created_at,
        s.course_id,
        s.section_id,
        c.name AS course_name,
        sec.code AS section_code,
        sec.name AS section_name,
        TRIM(CONCAT(COALESCE(s.last_name, ''), ', ', COALESCE(s.first_name, ''), ' ', COALESCE(s.middle_name, ''))) AS student_name
    FROM students s
    LEFT JOIN fingerprint_user_map m ON m.user_id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE COALESCE(s.user_id, 0) > 0
      AND m.user_id IS NULL
    ORDER BY s.last_name ASC, s.first_name ASC
";
$unmappedStudentRes = $conn->query($unmappedStudentSql);
if ($unmappedStudentRes instanceof mysqli_result) {
    while ($row = $unmappedStudentRes->fetch_assoc()) {
        $row['section_label'] = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
        $unmappedStudents[] = $row;
    }
    $unmappedStudentRes->close();
}
$filteredUnmappedStudents = array_values(array_filter($unmappedStudents, static function (array $row) use ($filterCourseId, $filterSectionId): bool {
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
                'machine_is_admin' => false,
                'machine_admin_label' => '',
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

$extractMachineAdminMeta = static function (array $userRow): array {
    $indicatorKeys = [
        'privilege', 'Privilege', 'role', 'Role', 'user_role', 'userRole',
        'authority', 'Authority', 'admin', 'is_admin', 'isAdmin',
        'user_type', 'UserType', 'type',
    ];

    foreach ($indicatorKeys as $key) {
        if (!array_key_exists($key, $userRow)) {
            continue;
        }

        $raw = $userRow[$key];
        $valueText = strtolower(trim((string)$raw));

        if (is_numeric($raw) && (int)$raw > 0) {
            return ['is_admin' => true, 'label' => $key . ':' . (string)$raw];
        }

        if ($valueText !== '' && preg_match('/admin|manager|super|master|owner/', $valueText)) {
            return ['is_admin' => true, 'label' => $key . ':' . (string)$raw];
        }
    }

    foreach ($userRow as $rawValue) {
        if (!is_scalar($rawValue)) {
            continue;
        }

        $valueText = strtolower(trim((string)$rawValue));
        if ($valueText !== '' && preg_match('/admin|manager|super|master|owner/', $valueText)) {
            return ['is_admin' => true, 'label' => (string)$rawValue];
        }
    }

    return ['is_admin' => false, 'label' => ''];
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
                $machineAdminMeta = $extractMachineAdminMeta($userRow);
                $machineIsAdmin = !empty($machineAdminMeta['is_admin']);
                $machineAdminLabel = trim((string)($machineAdminMeta['label'] ?? ''));

                if (!isset($detectedFingerprints[$fingerId])) {
                    $detectedFingerprints[$fingerId] = [
                        'finger_id' => $fingerId,
                        'last_seen' => '',
                        'machine_user_name' => $machineName,
                        'machine_is_admin' => $machineIsAdmin,
                        'machine_admin_label' => $machineAdminLabel,
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

                if ($machineIsAdmin) {
                    $detectedFingerprints[$fingerId]['machine_is_admin'] = true;
                    if ($machineAdminLabel !== '') {
                        $detectedFingerprints[$fingerId]['machine_admin_label'] = $machineAdminLabel;
                    }
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
            'machine_is_admin' => false,
            'machine_admin_label' => '',
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

$latestDetectedFingerId = empty($detectedFingerprints) ? 0 : (int)max(array_keys($detectedFingerprints));
$latestMappedFingerId = 0;
$machineAdminDetectedCount = 0;
foreach ($detectedFingerprints as $fingerId => $detectedFingerprint) {
    if (!empty($detectedFingerprint['is_mapped']) && (int)$fingerId > $latestMappedFingerId) {
        $latestMappedFingerId = (int)$fingerId;
    }

    if (!empty($detectedFingerprint['machine_is_admin'])) {
        $machineAdminDetectedCount++;
    }
}

$unmappedDetectedFingerprints = array_values(array_filter($detectedFingerprints, static function (array $row) use ($unmappedQuery, $unmappedAdminOnly): bool {
    if (!empty($row['is_mapped'])) {
        return false;
    }

    if ($unmappedAdminOnly && empty($row['machine_is_admin'])) {
        return false;
    }

    if ($unmappedQuery === '') {
        return true;
    }

    $haystack = strtolower(trim(implode(' ', [
        (string)($row['finger_id'] ?? ''),
        (string)($row['last_seen'] ?? ''),
        (string)($row['machine_user_name'] ?? ''),
        (string)($row['machine_admin_label'] ?? ''),
    ])));

    return str_contains($haystack, strtolower($unmappedQuery));
}));

$filteredDetectedFingerprints = array_values(array_filter($detectedFingerprints, static function (array $row) use ($detectedStatus, $detectedQuery): bool {
    $isMapped = !empty($row['is_mapped']);
    if ($detectedStatus === 'mapped' && !$isMapped) {
        return false;
    }
    if ($detectedStatus === 'unmapped' && $isMapped) {
        return false;
    }

    if ($detectedQuery === '') {
        return true;
    }

    $haystack = strtolower(trim(implode(' ', [
        (string)($row['finger_id'] ?? ''),
        (string)($row['last_seen'] ?? ''),
        (string)($row['machine_user_name'] ?? ''),
        (string)($row['mapped_user_name'] ?? ''),
        (string)($row['mapped_user_id'] ?? ''),
        (string)($row['student_name'] ?? ''),
        (string)($row['student_number'] ?? ''),
    ])));

    return str_contains($haystack, strtolower($detectedQuery));
}));

$courseFilterLabel = '';
foreach ($courses as $course) {
    if ((int)($course['id'] ?? 0) === $filterCourseId) {
        $courseFilterLabel = (string)($course['name'] ?? '');
        break;
    }
}
$sectionFilterLabel = '';
foreach ($sections as $section) {
    if ((int)($section['id'] ?? 0) === $filterSectionId) {
        $sectionFilterLabel = (string)($section['section_label'] ?? '');
        break;
    }
}
$fingerprintFilterParts = [];
if ($courseFilterLabel !== '') {
    $fingerprintFilterParts[] = 'Course: ' . $courseFilterLabel;
}
if ($sectionFilterLabel !== '') {
    $fingerprintFilterParts[] = 'Section: ' . $sectionFilterLabel;
}
$fingerprintPrintFilterLabel = $fingerprintFilterParts !== [] ? implode(' / ', $fingerprintFilterParts) : 'All students';

if ($printMode !== '') {
    $printRows = $printMode === 'mapped' ? $filteredStudentMappings : $filteredUnmappedStudents;
    $printTitle = $printMode === 'mapped' ? 'Mapped Fingerprint Students' : 'Students Without Fingerprint Mapping';
    ob_end_clean();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($printTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 18px; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 14px; }
        .toolbar button { border: 0; border-radius: 6px; padding: 9px 14px; font-weight: 700; cursor: pointer; }
        .toolbar .primary { background: #0ea5e9; color: #fff; }
        .toolbar .light { background: #eef2f7; color: #172033; }
        .print-head { display: grid; grid-template-columns: 74px 1fr 74px; align-items: center; border-bottom: 1.5px solid #2f6fca; padding-bottom: 8px; margin-bottom: 12px; color: #0b4aa2; }
        .print-head img { width: 62px; height: 62px; object-fit: contain; justify-self: center; }
        .print-head-copy { text-align: center; }
        .print-head h2 { margin: 0 0 3px; font-size: 17px; color: #0b4aa2; }
        .print-head div { font-size: 11px; color: #0b4aa2; line-height: 1.25; }
        .print-title { text-align: center; font-size: 15px; font-weight: 700; margin: 12px 0 8px; }
        .print-meta { font-size: 12px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        th, td { border: 1px solid #1f2937; padding: 5px 6px; vertical-align: top; word-break: break-word; }
        th { background: #f3f4f6; }
        .col-index { width: 34px; text-align: center; }
        @media print { body { margin: 10mm; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button class="light" type="button" onclick="window.close()">Close</button><button class="primary" type="button" onclick="window.print()">Print</button></div>
    <div class="print-head">
        <img src="assets/images/ccstlogo.png" alt="CCST">
        <div class="print-head-copy">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div>SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
            <div>Telefax No.: (045) 624-0215</div>
        </div>
        <div aria-hidden="true"></div>
    </div>
    <div class="print-title"><?php echo htmlspecialchars(strtoupper($printTitle), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="print-meta"><strong>FILTER:</strong> <?php echo htmlspecialchars($fingerprintPrintFilterLabel, ENT_QUOTES, 'UTF-8'); ?></div>
    <table>
        <thead>
            <tr>
                <th class="col-index">#</th>
                <th>Finger ID</th>
                <th>Student No.</th>
                <th>Name</th>
                <th>Course / Section</th>
                <th>Date Registered</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($printRows === []): ?>
                <tr><td class="col-index">1</td><td colspan="5">No students matched the current filters.</td></tr>
            <?php else: ?>
                <?php foreach ($printRows as $index => $row): ?>
                    <tr>
                        <td class="col-index"><?php echo (int)$index + 1; ?></td>
                        <td><?php echo $printMode === 'mapped' ? (int)($row['finger_id'] ?? 0) : 'Not mapped'; ?></td>
                        <td><?php echo htmlspecialchars((string)($row['student_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(trim((string)($row['student_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(trim((string)($row['course_name'] ?? '-') . ' / ' . (string)($row['section_label'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)($row[$printMode === 'mapped' ? 'created_at' : 'created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
    <?php
    exit;
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

        <div class="card bio-console-hero">
            <div class="card-body">
                <div class="bio-console-hero-grid">
                    <div>
                        <span class="bio-console-eyebrow">Biometric Access</span>
                        <h3>Link each fingerprint to the correct BioTern account</h3>
                        <p>Review detected F20H fingerprint IDs and prioritize unmapped entries, especially machine-admin capable fingerprints that may control device configuration access.</p>
                        <div class="bio-console-pill-list">
                            <span class="bio-console-pill">Latest detected ID: <?php echo $latestDetectedFingerId > 0 ? $latestDetectedFingerId : 'N/A'; ?></span>
                            <span class="bio-console-pill">Latest mapped Finger ID: <?php echo $latestMappedFingerId > 0 ? $latestMappedFingerId : 'N/A'; ?></span>
                            <span class="bio-console-pill">Unmapped detected: <?php echo (int)$unmappedDetectedCount; ?></span>
                            <span class="bio-console-pill">Machine admin-capable: <?php echo (int)$machineAdminDetectedCount; ?></span>
                        </div>
                    </div>
                    <div class="bio-console-note-surface">
                        <span class="badge bg-soft-info text-info">Mapping Workflow</span>
                        <h6>Best results come from the latest machine logs</h6>
                        <p>Read users and logs in the F20H Machine Manager first, then review the unmapped table below. Entries tagged as Admin-capable are likely the fingerprints that can open machine configuration menus.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 bio-console-panel">
            <div class="card-header"><strong>Unmapped Fingerprints (Priority Queue)</strong></div>
            <div class="card-body border-bottom">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="fingerprint-unmapped-internal.php" class="btn btn-sm btn-light-brand">Open Unmapped Internal Students</a>
                    <a href="ojt-external-list.php" class="btn btn-sm btn-outline-secondary">Open External List</a>
                    <a href="import-ojt-internal.php" class="btn btn-sm btn-outline-secondary">Import OJT Internal</a>
                    <a href="import-ojt-external.php" class="btn btn-sm btn-outline-secondary">Import OJT External</a>
                </div>
                <form method="get" class="row g-2 align-items-end fingerprint-form">
                    <input type="hidden" name="unmapped_admin_only" value="<?php echo $unmappedAdminOnly ? '1' : '0'; ?>">
                    <div class="col-12 col-md-8 col-xl-8">
                        <label class="form-label" for="unmapped_query">Search Unmapped Fingerprints</label>
                        <input type="text" class="form-control" id="unmapped_query" name="unmapped_query" value="<?php echo htmlspecialchars($unmappedQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by fingerprint ID, machine name, or admin hint">
                    </div>
                    <div class="col-12 col-xl-4 fm-actions">
                        <button type="submit" class="btn btn-primary">Apply Unmapped Filter</button>
                        <a href="fingerprint_mapping.php" class="btn btn-light">Clear</a>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a href="fingerprint_mapping.php?unmapped_admin_only=1<?php echo $unmappedQuery !== '' ? '&unmapped_query=' . urlencode($unmappedQuery) : ''; ?>" class="btn btn-sm <?php echo $unmappedAdminOnly ? 'btn-primary' : 'btn-outline-primary'; ?>">Admin-Capable Only</a>
                    <?php if ($unmappedAdminOnly): ?>
                        <a href="fingerprint_mapping.php<?php echo $unmappedQuery !== '' ? '?unmapped_query=' . urlencode($unmappedQuery) : ''; ?>" class="btn btn-sm btn-outline-secondary">Show All Unmapped</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bio-console-table">
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>Last Seen</th>
                                <th>Machine User</th>
                                <th>Machine Access Hint</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($unmappedDetectedCount === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No unmapped fingerprints right now.</td>
                            </tr>
                        <?php elseif (empty($unmappedDetectedFingerprints)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No unmapped fingerprints match your search.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($unmappedDetectedFingerprints as $fingerprint): ?>
                                <tr>
                                    <td><?php echo (int)$fingerprint['finger_id']; ?></td>
                                    <td><?php echo htmlspecialchars($fingerprint['last_seen'] !== '' ? $fingerprint['last_seen'] : 'Not seen in raw logs yet', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if (!empty($fingerprint['machine_user_name'])): ?>
                                            <?php echo htmlspecialchars((string)$fingerprint['machine_user_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($fingerprint['machine_is_admin'])): ?>
                                            <span class="badge bg-soft-danger text-danger">Admin-capable</span>
                                            <?php if (!empty($fingerprint['machine_admin_label'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars((string)$fingerprint['machine_admin_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-soft-secondary text-secondary">Standard/Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-soft-warning text-warning">Needs Mapping</span></td>
                                    <td class="text-end">
                                        <a href="fingerprint-unmapped-internal.php?map_finger_id=<?php echo (int)$fingerprint['finger_id']; ?>" class="btn btn-sm btn-outline-primary">Map To Student</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                <?php $fingerprintPrintQuery = http_build_query(array_filter([
                    'filter_course_id' => $filterCourseId > 0 ? (string)$filterCourseId : '',
                    'filter_section_id' => $filterSectionId > 0 ? (string)$filterSectionId : '',
                ], static fn($value): bool => $value !== '')); ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="fingerprint_mapping.php?print=mapped<?php echo $fingerprintPrintQuery !== '' ? '&' . htmlspecialchars($fingerprintPrintQuery, ENT_QUOTES, 'UTF-8') : ''; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="feather-printer me-1"></i> Print Mapped Students
                    </a>
                    <a href="fingerprint_mapping.php?print=unmapped_students<?php echo $fingerprintPrintQuery !== '' ? '&' . htmlspecialchars($fingerprintPrintQuery, ENT_QUOTES, 'UTF-8') : ''; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="feather-printer me-1"></i> Print Not Mapped Students
                    </a>
                </div>
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
                                <th>OJT Status</th>
                                <th>Mapped Info</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($filteredStudentMappings)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No student mappings match the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredStudentMappings as $map): ?>
                                <?php
                                $track = strtolower(trim((string)($map['assignment_track'] ?? 'internal')));
                                $remainingHours = $track === 'external'
                                    ? (int)($map['external_total_hours_remaining'] ?? 0)
                                    : (int)($map['internal_total_hours_remaining'] ?? 0);
                                $ojtCompleted = $remainingHours <= 0 && (int)($map['student_row_id'] ?? 0) > 0;
                                ?>
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
                                        <?php if ((int)($map['student_row_id'] ?? 0) <= 0): ?>
                                            <span class="badge bg-soft-secondary text-secondary">N/A</span>
                                        <?php elseif ($ojtCompleted): ?>
                                            <span class="badge bg-soft-success text-success">Completed</span>
                                            <div class="text-muted small">Ready for fingerprint slot reuse</div>
                                        <?php else: ?>
                                            <span class="badge bg-soft-warning text-warning">In Progress</span>
                                            <div class="text-muted small">Remaining: <?php echo (int)$remainingHours; ?>h</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $createdAt = (string)($map['created_at'] ?? ''); ?>
                                        <?php $updatedAt = (string)($map['updated_at'] ?? ''); ?>
                                        <div class="text-muted small">Created: <?php echo $createdAt !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime($createdAt)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                        <div class="text-muted small">Updated: <?php echo $updatedAt !== '' ? htmlspecialchars(date('M d, Y h:i A', strtotime($updatedAt)), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fingerprint-actions ms-auto">
                                            <?php if ($ojtCompleted): ?>
                                                <a href="biometric-machine.php?selected_user_id=<?php echo (int)$map['finger_id']; ?>&load_users=1&load_user=1" class="btn btn-sm btn-outline-warning">Cleanup Fingerprint Slot</a>
                                            <?php endif; ?>
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
            <div class="card-body border-bottom">
                <form method="get" class="row g-2 align-items-end fingerprint-form">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="detected_status">Filter by Status</label>
                        <select class="form-select" name="detected_status" id="detected_status">
                            <option value="all" <?php echo $detectedStatus === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="mapped" <?php echo $detectedStatus === 'mapped' ? 'selected' : ''; ?>>Mapped</option>
                            <option value="unmapped" <?php echo $detectedStatus === 'unmapped' ? 'selected' : ''; ?>>Needs Mapping</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-5">
                        <label class="form-label" for="detected_query">Search Fingerprint/User/Student</label>
                        <input type="text" class="form-control" id="detected_query" name="detected_query" value="<?php echo htmlspecialchars($detectedQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. ID, name, student number">
                    </div>
                    <div class="col-12 col-xl-4 fm-actions">
                        <button type="submit" class="btn btn-primary">Apply Table Filters</button>
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
                        <?php elseif (empty($filteredDetectedFingerprints)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No fingerprints match your current filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredDetectedFingerprints as $fingerprint): ?>
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
                                            <span class="text-muted small"><?php echo !empty($fingerprint['is_mapped']) ? 'Mapped' : 'Pending Mapping'; ?></span>
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
