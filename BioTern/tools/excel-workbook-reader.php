<?php

function ojt_import_header_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function ojt_import_sheet_key(string $value): string
{
    return ojt_import_header_key($value);
}

function ojt_import_detect_workbook_extension(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $hasWorkbookXml = is_string($zip->getFromName('xl/workbook.xml'));
            $zip->close();
            if ($hasWorkbookXml) {
                return 'xlsx';
            }
        }
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }

    $signature = fread($handle, 8);
    fclose($handle);

    if (strncmp((string)$signature, "\xD0\xCF\x11\xE0", 4) === 0) {
        return 'xls';
    }

    return '';
}

function ojt_import_load_workbook_rows(string $path, string $sourceWorkbook, string &$errorMessage): array
{
    $errorMessage = '';
    $extension = strtolower((string)pathinfo($sourceWorkbook, PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = ojt_import_detect_workbook_extension($path);
    }

    if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (Throwable $e) {
            $errorMessage = 'Unable to read workbook: ' . $e->getMessage();
            return [];
        }

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = $worksheet->toArray('', true, true, false);
            if (empty($rows)) {
                continue;
            }

            $headerRow = array_shift($rows);
            if (!is_array($headerRow)) {
                continue;
            }

            $headers = [];
            foreach ($headerRow as $cell) {
                $headers[] = ojt_import_header_key((string)$cell);
            }

            $normalizedRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $assoc = [];
                $hasContent = false;
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $value = isset($row[$index]) ? trim((string)$row[$index]) : '';
                    if ($value !== '') {
                        $hasContent = true;
                    }
                    $assoc[$header] = $value;
                }
                if ($hasContent) {
                    $normalizedRows[] = $assoc;
                }
            }

            if ($normalizedRows !== []) {
                return $normalizedRows;
            }
        }

        $errorMessage = 'Workbook opened, but no readable worksheet rows were found.';
        return [];
    }

    if ($extension === 'xlsx') {
        $sheets = ojt_import_load_xlsx_workbook($path, $errorMessage);
        if ($sheets === []) {
            return [];
        }
        foreach ($sheets as $rows) {
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }
        }
        $errorMessage = 'Workbook opened, but no readable worksheet rows were found.';
        return [];
    }

    if ($extension === 'xls') {
        $errorMessage = 'Legacy .xls files require PhpSpreadsheet on this server. Please save the workbook as .xlsx first.';
        return [];
    }

    $errorMessage = 'Unsupported workbook format. Please upload an .xlsx file.';
    return [];
}

function ojt_import_load_xlsx_workbook(string $path, string &$errorMessage): array
{
    $errorMessage = '';
    if (!class_exists('ZipArchive')) {
        $errorMessage = 'Unable to read workbook: ZipArchive is not available on this PHP setup.';
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        $errorMessage = 'Unable to open workbook archive.';
        return [];
    }

    $sharedStrings = ojt_import_xlsx_shared_strings($zip);
    $sheetFiles = ojt_import_xlsx_sheet_files($zip, $errorMessage);
    if ($sheetFiles === []) {
        $zip->close();
        if ($errorMessage === '') {
            $errorMessage = 'No worksheets found in workbook.';
        }
        return [];
    }

    $sheets = [];
    foreach ($sheetFiles as $sheetTitle => $sheetPath) {
        $sheetXml = $zip->getFromName($sheetPath);
        if (!is_string($sheetXml) || $sheetXml === '') {
            continue;
        }

        $worksheet = @simplexml_load_string($sheetXml);
        if (!$worksheet) {
            continue;
        }

        $worksheet->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $worksheet->xpath('//main:sheetData/main:row');
        if (!is_array($rowNodes) || $rowNodes === []) {
            continue;
        }

        $rows = [];
        $maxColumnIndex = -1;
        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cellNodes = $rowNode->xpath('./main:c');
            if (!is_array($cellNodes)) {
                $cellNodes = [];
            }

            foreach ($cellNodes as $cellNode) {
                $reference = (string)($cellNode['r'] ?? '');
                $columnIndex = ojt_import_cell_reference_to_index($reference);
                if ($columnIndex < 0) {
                    continue;
                }
                $cells[$columnIndex] = trim(ojt_import_xlsx_cell_value($cellNode, $sharedStrings));
                if ($columnIndex > $maxColumnIndex) {
                    $maxColumnIndex = $columnIndex;
                }
            }

            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === [] || $maxColumnIndex < 0) {
            continue;
        }

        $headerCells = array_shift($rows);
        $headers = [];
        for ($i = 0; $i <= $maxColumnIndex; $i++) {
            $headers[$i] = ojt_import_header_key((string)($headerCells[$i] ?? ''));
        }

        $normalizedRows = [];
        foreach ($rows as $rowCells) {
            $assoc = [];
            $hasContent = false;
            for ($i = 0; $i <= $maxColumnIndex; $i++) {
                $header = $headers[$i] ?? '';
                if ($header === '') {
                    continue;
                }
                $value = trim((string)($rowCells[$i] ?? ''));
                if ($value !== '') {
                    $hasContent = true;
                }
                $assoc[$header] = $value;
            }
            if ($hasContent) {
                $normalizedRows[] = $assoc;
            }
        }

        $sheets[ojt_import_sheet_key($sheetTitle)] = $normalizedRows;
    }

    $zip->close();
    if ($sheets === []) {
        $errorMessage = 'Workbook could be opened, but no readable worksheet data was found.';
    }

    return $sheets;
}

function ojt_import_xlsx_shared_strings(ZipArchive $zip): array
{
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if (!is_string($sharedStringsXml) || $sharedStringsXml === '') {
        return [];
    }

    $sharedStringsDoc = @simplexml_load_string($sharedStringsXml);
    if (!$sharedStringsDoc) {
        return [];
    }

    $sharedStringsDoc->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $stringNodes = $sharedStringsDoc->xpath('//main:si');
    if (!is_array($stringNodes)) {
        return [];
    }

    $values = [];
    foreach ($stringNodes as $stringNode) {
        $stringNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $parts = $stringNode->xpath('.//main:t');
        if (!is_array($parts) || $parts === []) {
            $values[] = '';
            continue;
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string)$part;
        }
        $values[] = $text;
    }

    return $values;
}

function ojt_import_xlsx_sheet_files(ZipArchive $zip, string &$errorMessage): array
{
    $errorMessage = '';
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (!is_string($workbookXml) || $workbookXml === '' || !is_string($relsXml) || $relsXml === '') {
        $errorMessage = 'Workbook metadata is incomplete.';
        return [];
    }

    $workbook = @simplexml_load_string($workbookXml);
    $relationships = @simplexml_load_string($relsXml);
    if (!$workbook || !$relationships) {
        $errorMessage = 'Workbook metadata could not be parsed.';
        return [];
    }

    $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $relationships->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $relationshipMap = [];
    $relationshipNodes = $relationships->xpath('//rel:Relationship');
    if (is_array($relationshipNodes)) {
        foreach ($relationshipNodes as $relationshipNode) {
            $id = (string)($relationshipNode['Id'] ?? '');
            $target = (string)($relationshipNode['Target'] ?? '');
            if ($id === '' || $target === '') {
                continue;
            }
            $relationshipMap[$id] = 'xl/' . ltrim(str_replace('\\', '/', $target), '/');
        }
    }

    $sheetFiles = [];
    $sheetNodes = $workbook->xpath('//main:sheets/main:sheet');
    if (!is_array($sheetNodes)) {
        return $sheetFiles;
    }

    foreach ($sheetNodes as $sheetNode) {
        $title = trim((string)($sheetNode['name'] ?? ''));
        $relationshipId = trim((string)($sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? ''));
        if ($title === '' || $relationshipId === '' || !isset($relationshipMap[$relationshipId])) {
            continue;
        }
        $sheetFiles[$title] = $relationshipMap[$relationshipId];
    }

    return $sheetFiles;
}

function ojt_import_cell_reference_to_index(string $reference): int
{
    if (!preg_match('/^[A-Z]+/i', $reference, $matches)) {
        return -1;
    }

    $letters = strtoupper($matches[0]);
    $index = 0;
    $length = strlen($letters);
    for ($i = 0; $i < $length; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

function ojt_import_xlsx_cell_value(SimpleXMLElement $cellNode, array $sharedStrings): string
{
    $type = (string)($cellNode['t'] ?? '');
    $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    if ($type === 'inlineStr') {
        $parts = $cellNode->xpath('./main:is//main:t');
        if (!is_array($parts) || $parts === []) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string)$part;
        }
        return $text;
    }

    $value = trim((string)($cellNode->v ?? ''));
    if ($type === 's') {
        $index = (int)$value;
        return (string)($sharedStrings[$index] ?? '');
    }
    if ($type === 'b') {
        return $value === '1' ? '1' : '0';
    }

    return $value;
}
