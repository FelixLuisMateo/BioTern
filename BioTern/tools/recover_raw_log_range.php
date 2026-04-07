<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/biometric_auto_import.php';

$options = getopt('', [
    'from:',
    'to:',
    'remote-url::',
    'remote-host::',
    'remote-port::',
    'remote-user::',
    'remote-pass::',
    'remote-db::',
    'reprocess-existing::',
]);

$from = isset($options['from']) ? (int)$options['from'] : 0;
$to = isset($options['to']) ? (int)$options['to'] : 0;
if ($from <= 0 || $to <= 0 || $to < $from) {
    fwrite(STDERR, "Usage: php tools/recover_raw_log_range.php --from=65 --to=107 [--remote-url=mysql://user:pass@host:port/db] [--reprocess-existing=1]\n");
    exit(1);
}

$reprocessExisting = false;
if (isset($options['reprocess-existing'])) {
    $v = strtolower(trim((string)$options['reprocess-existing']));
    $reprocessExisting = in_array($v, ['1', 'true', 'yes', 'on', ''], true);
}

$local = $conn;
if (!($local instanceof mysqli) || $local->connect_errno) {
    fwrite(STDERR, "Local DB connection failed.\n");
    exit(1);
}
$local->set_charset('utf8mb4');

$remoteHost = (string)($options['remote-host'] ?? '');
$remotePort = isset($options['remote-port']) ? (int)$options['remote-port'] : 3306;
$remoteUser = (string)($options['remote-user'] ?? '');
$remotePass = (string)($options['remote-pass'] ?? '');
$remoteDb = (string)($options['remote-db'] ?? '');

$remoteUrl = trim((string)($options['remote-url'] ?? ''));
if ($remoteUrl !== '') {
    $parts = @parse_url($remoteUrl);
    if (!is_array($parts)) {
        fwrite(STDERR, "Invalid --remote-url format.\n");
        exit(1);
    }

    $remoteHost = (string)($parts['host'] ?? $remoteHost);
    $remotePort = isset($parts['port']) ? (int)$parts['port'] : $remotePort;
    $remoteUser = isset($parts['user']) ? urldecode((string)$parts['user']) : $remoteUser;
    $remotePass = isset($parts['pass']) ? urldecode((string)$parts['pass']) : $remotePass;
    if (isset($parts['path'])) {
        $remoteDb = ltrim((string)$parts['path'], '/');
    }
}

$remoteRows = [];
$remoteUsed = false;
if ($remoteHost !== '' && $remoteUser !== '' && $remoteDb !== '') {
    $remoteUsed = true;
    mysqli_report(MYSQLI_REPORT_OFF);
    $remote = @new mysqli($remoteHost, $remoteUser, $remotePass, $remoteDb, $remotePort > 0 ? $remotePort : 3306);
    if (!($remote instanceof mysqli) || $remote->connect_errno) {
        fwrite(STDERR, 'Remote DB connection failed: ' . ($remote instanceof mysqli ? $remote->connect_error : 'unknown') . "\n");
        exit(1);
    }
    $remote->set_charset('utf8mb4');

    $stmtRemote = $remote->prepare('SELECT id, raw_data, imported_at, processed FROM biometric_raw_logs WHERE id BETWEEN ? AND ? ORDER BY id ASC');
    if (!$stmtRemote) {
        fwrite(STDERR, 'Remote query prepare failed: ' . $remote->error . "\n");
        $remote->close();
        exit(1);
    }
    $stmtRemote->bind_param('ii', $from, $to);
    $stmtRemote->execute();
    $resRemote = $stmtRemote->get_result();
    while ($row = $resRemote->fetch_assoc()) {
        $remoteRows[] = $row;
    }
    $stmtRemote->close();
    $remote->close();
}

$inserted = 0;
$updated = 0;
$reprocessMarked = 0;

if ($remoteUsed) {
    if ($remoteRows === []) {
        echo "remote_rows=0\n";
    } else {
        $stmtUpsert = $local->prepare('INSERT INTO biometric_raw_logs (id, raw_data, imported_at, processed) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE raw_data = VALUES(raw_data), imported_at = VALUES(imported_at), processed = 0');
        if (!$stmtUpsert) {
            fwrite(STDERR, 'Local upsert prepare failed: ' . $local->error . "\n");
            exit(1);
        }

        foreach ($remoteRows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rawData = (string)($row['raw_data'] ?? '');
            $importedAt = (string)($row['imported_at'] ?? date('Y-m-d H:i:s'));

            $stmtCheck = $local->prepare('SELECT COUNT(*) c FROM biometric_raw_logs WHERE id = ?');
            if ($stmtCheck) {
                $stmtCheck->bind_param('i', $id);
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result();
                $exists = (int)(($resCheck->fetch_assoc()['c'] ?? 0));
                $stmtCheck->close();
                if ($exists > 0) {
                    $updated++;
                } else {
                    $inserted++;
                }
            }

            $stmtUpsert->bind_param('iss', $id, $rawData, $importedAt);
            $stmtUpsert->execute();
        }

        $stmtUpsert->close();
    }
}

if ($reprocessExisting) {
    $stmtMark = $local->prepare('UPDATE biometric_raw_logs SET processed = 0 WHERE id BETWEEN ? AND ?');
    if (!$stmtMark) {
        fwrite(STDERR, 'Local mark prepare failed: ' . $local->error . "\n");
        exit(1);
    }
    $stmtMark->bind_param('ii', $from, $to);
    $stmtMark->execute();
    $reprocessMarked = (int)$stmtMark->affected_rows;
    $stmtMark->close();
}

$stats = run_biometric_auto_import_stats();

echo 'range=' . $from . '-' . $to . PHP_EOL;
echo 'remote_used=' . ($remoteUsed ? '1' : '0') . PHP_EOL;
echo 'remote_rows=' . count($remoteRows) . PHP_EOL;
echo 'inserted=' . $inserted . PHP_EOL;
echo 'updated=' . $updated . PHP_EOL;
echo 'marked_processed_zero=' . $reprocessMarked . PHP_EOL;
echo 'import_raw_inserted=' . (int)($stats['raw_inserted'] ?? 0) . PHP_EOL;
echo 'import_processed_logs=' . (int)($stats['processed_logs'] ?? 0) . PHP_EOL;
echo 'import_attendance_changed=' . (int)($stats['attendance_changed'] ?? 0) . PHP_EOL;
echo 'import_anomalies_found=' . (int)($stats['anomalies_found'] ?? 0) . PHP_EOL;
echo 'import_message=' . (string)($stats['message'] ?? '') . PHP_EOL;
