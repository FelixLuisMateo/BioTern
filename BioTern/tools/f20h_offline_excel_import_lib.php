<?php

function f20h_import_read_u16(string $data, int $offset): int
{
    if ($offset < 0 || $offset + 2 > strlen($data)) {
        return 0;
    }
    $v = unpack('v', substr($data, $offset, 2));
    return (int)($v[1] ?? 0);
}

function f20h_import_read_u32(string $data, int $offset): int
{
    if ($offset < 0 || $offset + 4 > strlen($data)) {
        return 0;
    }
    $v = unpack('V', substr($data, $offset, 4));
    return (int)($v[1] ?? 0);
}

function f20h_import_read_u64_size(string $data, int $offset): int
{
    $low = f20h_import_read_u32($data, $offset);
    $high = f20h_import_read_u32($data, $offset + 4);
    if ($high <= 0) {
        return $low;
    }
    return (int)min(PHP_INT_MAX, ($high * 4294967296) + $low);
}

function f20h_import_sector(string $data, int $sectorId, int $sectorSize): string
{
    if ($sectorId < 0 || $sectorId >= 0xFFFFFFF0) {
        return '';
    }
    $offset = 512 + ($sectorId * $sectorSize);
    return substr($data, $offset, $sectorSize);
}

function f20h_import_chain(array $fat, int $start): array
{
    $chain = [];
    $seen = [];
    $sector = $start;
    while ($sector >= 0 && $sector < 0xFFFFFFF0 && !isset($seen[$sector])) {
        $seen[$sector] = true;
        $chain[] = $sector;
        if (!array_key_exists($sector, $fat)) {
            break;
        }
        $sector = (int)$fat[$sector];
    }
    return $chain;
}

function f20h_import_decode_utf16le(string $raw): string
{
    if (function_exists('mb_convert_encoding')) {
        return (string)mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw);
        if (is_string($converted)) {
            return $converted;
        }
    }
    return preg_replace('/\x00/', '', $raw) ?? '';
}

function f20h_import_decode_xls_string(string $record, int $offset): string
{
    $length = f20h_import_read_u16($record, $offset);
    $flagsOffset = $offset + 2;
    if ($length <= 0 || $flagsOffset >= strlen($record)) {
        return '';
    }

    $flags = ord($record[$flagsOffset]);
    $pos = $offset + 3;
    if (($flags & 0x08) !== 0) {
        $pos += 2;
    }
    if (($flags & 0x04) !== 0) {
        $pos += 4;
    }

    $isUtf16 = (($flags & 0x01) !== 0);
    $byteLength = $length * ($isUtf16 ? 2 : 1);
    $raw = substr($record, $pos, $byteLength);
    $text = $isUtf16 ? f20h_import_decode_utf16le($raw) : $raw;
    return trim(str_replace("\r\n", "\n", $text));
}

function f20h_import_decode_rk(int $raw)
{
    $multiplied = ($raw & 0x01) !== 0;
    $isInteger = ($raw & 0x02) !== 0;
    $valueBits = $raw & 0xFFFFFFFC;

    if ($isInteger) {
        if (($valueBits & 0x80000000) !== 0) {
            $valueBits -= 0x100000000;
        }
        $value = $valueBits >> 2;
    } else {
        $packed = pack('V2', 0, $valueBits);
        $unpacked = unpack('d', $packed);
        $value = (float)($unpacked[1] ?? 0.0);
    }

    return $multiplied ? ($value / 100) : $value;
}

function f20h_import_extract_workbook_stream(string $path): string
{
    $data = (string)file_get_contents($path);
    if ($data === '' || substr($data, 0, 4) !== "\xD0\xCF\x11\xE0") {
        throw new RuntimeException('All Reports.xls must be a legacy Excel .xls file from the F20H machine.');
    }

    $sectorSize = 1 << f20h_import_read_u16($data, 30);
    $miniSectorSize = 1 << f20h_import_read_u16($data, 32);
    $fatSectorCount = f20h_import_read_u32($data, 44);
    $firstDirectorySector = f20h_import_read_u32($data, 48);
    $miniCutoffSize = f20h_import_read_u32($data, 56);
    $firstMiniFatSector = f20h_import_read_u32($data, 60);

    if ($sectorSize <= 0 || $fatSectorCount <= 0) {
        throw new RuntimeException('Unable to read the Excel compound file header.');
    }

    $difat = [];
    for ($i = 0; $i < min(109, $fatSectorCount); $i++) {
        $sector = f20h_import_read_u32($data, 76 + ($i * 4));
        if ($sector < 0xFFFFFFF0) {
            $difat[] = $sector;
        }
    }

    $fat = [];
    foreach ($difat as $fatSector) {
        $sectorData = f20h_import_sector($data, $fatSector, $sectorSize);
        for ($i = 0; $i + 4 <= strlen($sectorData); $i += 4) {
            $fat[] = f20h_import_read_u32($sectorData, $i);
        }
    }

    $directory = '';
    foreach (f20h_import_chain($fat, $firstDirectorySector) as $sector) {
        $directory .= f20h_import_sector($data, $sector, $sectorSize);
    }

    $entries = [];
    for ($offset = 0; $offset + 128 <= strlen($directory); $offset += 128) {
        $entry = substr($directory, $offset, 128);
        $nameLength = f20h_import_read_u16($entry, 64);
        if ($nameLength < 2) {
            continue;
        }
        $name = f20h_import_decode_utf16le(substr($entry, 0, $nameLength - 2));
        $entries[$name] = [
            'type' => ord($entry[66]),
            'start' => f20h_import_read_u32($entry, 116),
            'size' => f20h_import_read_u64_size($entry, 120),
        ];
    }

    $workbook = $entries['Workbook'] ?? ($entries['Book'] ?? null);
    if (!is_array($workbook)) {
        throw new RuntimeException('Workbook stream was not found inside All Reports.xls.');
    }

    $root = $entries['Root Entry'] ?? null;
    $miniStream = '';
    $miniFat = [];
    if (is_array($root)) {
        foreach (f20h_import_chain($fat, (int)$root['start']) as $sector) {
            $miniStream .= f20h_import_sector($data, $sector, $sectorSize);
        }
        $miniStream = substr($miniStream, 0, (int)$root['size']);
    }
    if ($firstMiniFatSector < 0xFFFFFFF0) {
        foreach (f20h_import_chain($fat, $firstMiniFatSector) as $sector) {
            $sectorData = f20h_import_sector($data, $sector, $sectorSize);
            for ($i = 0; $i + 4 <= strlen($sectorData); $i += 4) {
                $miniFat[] = f20h_import_read_u32($sectorData, $i);
            }
        }
    }

    if ((int)$workbook['size'] < $miniCutoffSize && $miniStream !== '' && $miniFat !== []) {
        $stream = '';
        foreach (f20h_import_chain($miniFat, (int)$workbook['start']) as $sector) {
            $stream .= substr($miniStream, $sector * $miniSectorSize, $miniSectorSize);
        }
        return substr($stream, 0, (int)$workbook['size']);
    }

    $stream = '';
    foreach (f20h_import_chain($fat, (int)$workbook['start']) as $sector) {
        $stream .= f20h_import_sector($data, $sector, $sectorSize);
    }
    return substr($stream, 0, (int)$workbook['size']);
}

function f20h_import_parse_biff_sheets(string $workbook): array
{
    $sheets = [];
    $offset = 0;
    $length = strlen($workbook);
    while ($offset + 4 <= $length) {
        $recordType = f20h_import_read_u16($workbook, $offset);
        $recordLength = f20h_import_read_u16($workbook, $offset + 2);
        $record = substr($workbook, $offset + 4, $recordLength);
        $offset += 4 + $recordLength;

        if ($recordType !== 0x0085 || strlen($record) < 8) {
            continue;
        }

        $sheetOffset = f20h_import_read_u32($record, 0);
        $nameLength = ord($record[6]);
        $flags = ord($record[7]);
        $raw = substr($record, 8, $nameLength * (($flags & 0x01) ? 2 : 1));
        $name = (($flags & 0x01) !== 0) ? f20h_import_decode_utf16le($raw) : $raw;
        $sheets[$name] = $sheetOffset;
    }
    return $sheets;
}

function f20h_import_parse_sheet_cells(string $workbook, int $sheetOffset): array
{
    $cells = [];
    $offset = $sheetOffset;
    $length = strlen($workbook);
    while ($offset + 4 <= $length) {
        $recordType = f20h_import_read_u16($workbook, $offset);
        $recordLength = f20h_import_read_u16($workbook, $offset + 2);
        $record = substr($workbook, $offset + 4, $recordLength);
        $offset += 4 + $recordLength;

        if ($recordType === 0x000A) {
            break;
        }

        if ($recordType === 0x0204 && strlen($record) >= 9) {
            $row = f20h_import_read_u16($record, 0);
            $col = f20h_import_read_u16($record, 2);
            $cells[$row][$col] = f20h_import_decode_xls_string($record, 6);
            continue;
        }

        if ($recordType === 0x027E && strlen($record) >= 10) {
            $row = f20h_import_read_u16($record, 0);
            $col = f20h_import_read_u16($record, 2);
            $cells[$row][$col] = f20h_import_decode_rk(f20h_import_read_u32($record, 6));
            continue;
        }

        if ($recordType === 0x00D6 && strlen($record) >= 6) {
            $row = f20h_import_read_u16($record, 0);
            $firstCol = f20h_import_read_u16($record, 2);
            $lastCol = f20h_import_read_u16($record, 4);
            $pos = 6;
            for ($col = $firstCol; $col <= $lastCol && $pos + 6 <= strlen($record); $col++, $pos += 6) {
                $cells[$row][$col] = f20h_import_decode_rk(f20h_import_read_u32($record, $pos + 2));
            }
            continue;
        }

        if ($recordType === 0x0203 && strlen($record) >= 14) {
            $row = f20h_import_read_u16($record, 0);
            $col = f20h_import_read_u16($record, 2);
            $v = unpack('d', substr($record, 6, 8));
            $cells[$row][$col] = (float)($v[1] ?? 0);
        }
    }
    ksort($cells);
    return $cells;
}

function f20h_import_date_range_from_cells(array $cells): array
{
    $text = '';
    foreach ($cells as $row) {
        foreach ($row as $value) {
            $candidate = trim((string)$value);
            if (preg_match('/(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/', $candidate, $m)) {
                $text = $m[0];
                $start = new DateTimeImmutable($m[1]);
                $end = new DateTimeImmutable($m[2]);
                $dates = [];
                for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
                    $dates[] = $d->format('Y-m-d');
                }
                return $dates;
            }
        }
    }
    throw new RuntimeException('Could not find the report date range in Attendance Logs.');
}

function f20h_import_normalize_time(string $time): string
{
    $time = trim($time);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
        return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 0);
    }
    return '';
}

function f20h_import_time_minutes(string $time): ?int
{
    if (!preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $time, $m)) {
        return null;
    }
    return ((int)$m[1] * 60) + (int)$m[2];
}

function f20h_import_extract_time_in_events(string $path): array
{
    $workbook = f20h_import_extract_workbook_stream($path);
    $sheets = f20h_import_parse_biff_sheets($workbook);
    if (!isset($sheets['Attendance Logs'])) {
        throw new RuntimeException('The workbook does not contain an Attendance Logs sheet.');
    }

    $cells = f20h_import_parse_sheet_cells($workbook, (int)$sheets['Attendance Logs']);
    $dates = f20h_import_date_range_from_cells($cells);
    $events = [];

    foreach ($cells as $rowIndex => $row) {
        $label = strtolower(trim((string)($row[0] ?? '')));
        if ($label !== 'id') {
            continue;
        }

        $fingerId = (int)round((float)($row[2] ?? 0));
        if ($fingerId <= 0) {
            continue;
        }

        $dateRow = $cells[$rowIndex + 1] ?? [];
        $timeRow = $cells[$rowIndex + 3] ?? [];
        foreach ($timeRow as $col => $value) {
            $dateIndex = (int)$col;
            $date = $dates[$dateIndex] ?? '';
            if ($date === '') {
                continue;
            }

            $rawTimes = preg_split('/\R+/', trim((string)$value)) ?: [];
            $acceptedTimes = [];
            $lastAcceptedMinutes = null;
            foreach ($rawTimes as $rawTime) {
                $normalized = f20h_import_normalize_time($rawTime);
                if ($normalized === '') {
                    continue;
                }

                $minutes = f20h_import_time_minutes($normalized);
                if ($minutes === null) {
                    continue;
                }

                if ($lastAcceptedMinutes !== null && abs($minutes - $lastAcceptedMinutes) < 60) {
                    continue;
                }

                $acceptedTimes[] = $normalized;
                $lastAcceptedMinutes = $minutes;
            }
            if ($acceptedTimes === []) {
                continue;
            }

            if (isset($dateRow[$col]) && (int)round((float)$dateRow[$col]) > 0) {
                $dayFromSheet = (int)round((float)$dateRow[$col]);
                if ((int)substr($date, 8, 2) !== $dayFromSheet) {
                    continue;
                }
            }

            foreach ($acceptedTimes as $time) {
                $events[] = [
                    'finger_id' => $fingerId,
                    'id' => $fingerId,
                    'type' => 1,
                    'time' => $date . ' ' . $time,
                    'source' => 'f20h_offline_excel',
                ];
            }
        }
    }

    return $events;
}

function f20h_import_offline_excel(mysqli $conn, string $path): array
{
    require_once __DIR__ . '/biometric_auto_import.php';

    $events = f20h_import_extract_time_in_events($path);
    $machineConfig = loadBiometricMachineConfig();
    $inserted = biometricInsertRawLogEntries($conn, $events, $machineConfig);
    $stats = run_biometric_auto_import_stats();

    return [
        'events_found' => count($events),
        'raw_inserted' => $inserted,
        'import_stats' => $stats,
        'message' => 'Offline F20H Excel import complete. Time-in events found: ' . count($events)
            . ', new raw logs inserted: ' . $inserted
            . ', processed logs: ' . (int)($stats['processed_logs'] ?? 0)
            . ', attendance rows changed: ' . (int)($stats['attendance_changed'] ?? 0) . '.',
    ];
}
