<?php
require_once __DIR__ . '/biometric_db.php';
require_once __DIR__ . '/biometric_ops.php';
require_once __DIR__ . '/f20h_offline_excel_import_lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: attendance.php');
    exit;
}

try {
    if (empty($_FILES['f20h_report_file']) || !is_array($_FILES['f20h_report_file'])) {
        throw new RuntimeException('Choose the F20H All Reports.xls file first.');
    }

    $upload = $_FILES['f20h_report_file'];
    if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please choose All Reports.xls again.');
    }

    $originalName = trim((string)($upload['name'] ?? 'All Reports.xls'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'xls') {
        throw new RuntimeException('Please upload the F20H legacy Excel file named All Reports.xls.');
    }

    $tmpPath = (string)($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Uploaded file could not be read.');
    }

    $conn = biometric_shared_db();
    if ($conn->connect_error) {
        throw new RuntimeException('Database connection failed.');
    }
    $conn->set_charset('utf8mb4');

    $result = f20h_import_offline_excel($conn, $tmpPath);
    biometric_ops_log_audit(
        $conn,
        (int)($_SESSION['user_id'] ?? 0),
        (string)($_SESSION['role'] ?? ''),
        'offline_f20h_excel_import',
        'attendance',
        null,
        [
            'file_name' => $originalName,
            'events_found' => (int)($result['events_found'] ?? 0),
            'raw_inserted' => (int)($result['raw_inserted'] ?? 0),
            'import_stats' => $result['import_stats'] ?? [],
        ]
    );
    $conn->close();

    $_SESSION['attendance_sync_flash'] = [
        'type' => 'success',
        'message' => (string)($result['message'] ?? 'Offline F20H Excel import complete.'),
    ];
} catch (Throwable $e) {
    $_SESSION['attendance_sync_flash'] = [
        'type' => 'danger',
        'message' => 'Offline F20H Excel import failed. ' . $e->getMessage(),
    ];
}

header('Location: attendance.php');
exit;
