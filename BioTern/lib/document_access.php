<?php

require_once __DIR__ . '/ops_helpers.php';

function documents_ensure_external_start_allowed_column(mysqli $conn): void
{
    if (table_exists($conn, 'students') && !biotern_table_has_column($conn, 'students', 'external_start_allowed')) {
        $conn->query("ALTER TABLE students ADD COLUMN external_start_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_track");
    }
}

function documents_setting_allows_early_generation(mysqli $conn): bool
{
    if (!table_exists($conn, 'system_settings')) {
        return false;
    }

    $stmt = $conn->prepare("SELECT `value` FROM system_settings WHERE `key` = 'allow_document_generation_before_assignment' LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $value = strtolower(trim((string)($row['value'] ?? '0')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function documents_students_search_gate_sql(mysqli $conn, string $studentAlias = 's'): string
{
    documents_ensure_external_start_allowed_column($conn);

    if (documents_setting_allows_early_generation($conn)) {
        return '1 = 1';
    }

    $safeAlias = preg_replace('/[^a-z0-9_]/i', '', $studentAlias);
    if ($safeAlias === '') {
        $safeAlias = 's';
    }

    $pieces = [];

    if (table_exists($conn, 'internships')) {
        $pieces[] = "EXISTS (
            SELECT 1 FROM internships i
            WHERE i.student_id = {$safeAlias}.id
              AND i.status IN ('ongoing', 'completed')
            LIMIT 1
        )";
    }

    $pieces[] = "((COALESCE({$safeAlias}.assignment_track, 'internal') = 'external' AND COALESCE({$safeAlias}.internal_total_hours_remaining, 0) <= 0) OR COALESCE({$safeAlias}.external_start_allowed, 0) = 1)";

    if (table_exists($conn, 'evaluation_unlocks')) {
        $pieces[] = "EXISTS (
            SELECT 1 FROM evaluation_unlocks eu
            WHERE eu.student_id = {$safeAlias}.id
              AND eu.is_unlocked = 1
            LIMIT 1
        )";
    }

    if ($pieces === []) {
        return '1 = 0';
    }

    return '(' . implode(' OR ', $pieces) . ')';
}

function documents_student_can_generate(mysqli $conn, int $studentId): array
{
    documents_ensure_external_start_allowed_column($conn);

    if ($studentId <= 0) {
        return ['allowed' => false, 'reason' => 'Student not specified.'];
    }

    if (documents_setting_allows_early_generation($conn)) {
        return ['allowed' => true, 'reason' => ''];
    }

    $stmt = $conn->prepare("SELECT s.id, s.user_id, s.assignment_track, s.internal_total_hours_remaining,
            COALESCE(s.external_start_allowed, 0) AS external_start_allowed,
            COALESCE(u.application_status, 'approved') AS application_status
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
        LIMIT 1");
    if (!$stmt) {
        return ['allowed' => false, 'reason' => 'Unable to verify student document access.'];
    }

    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        return ['allowed' => false, 'reason' => 'Student not found.'];
    }

    $appStatus = strtolower(trim((string)($student['application_status'] ?? 'approved')));
    if (!in_array($appStatus, ['approved', 'all'], true)) {
        return ['allowed' => false, 'reason' => 'Student application is not approved yet.'];
    }

    if (table_exists($conn, 'internships')) {
        $internStmt = $conn->prepare("SELECT id FROM internships WHERE student_id = ? AND status IN ('ongoing', 'completed') ORDER BY id DESC LIMIT 1");
        if ($internStmt) {
            $internStmt->bind_param('i', $studentId);
            $internStmt->execute();
            $internRow = $internStmt->get_result()->fetch_assoc();
            $internStmt->close();
            if ($internRow) {
                return ['allowed' => true, 'reason' => ''];
            }
        }
    }

    $track = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
    $internalRemaining = (int)($student['internal_total_hours_remaining'] ?? 0);
    if (($track === 'external' && $internalRemaining <= 0) || (int)($student['external_start_allowed'] ?? 0) === 1) {
        return ['allowed' => true, 'reason' => ''];
    }

    if (table_exists($conn, 'evaluation_unlocks')) {
        $unlockStmt = $conn->prepare("SELECT is_unlocked FROM evaluation_unlocks WHERE student_id = ? LIMIT 1");
        if ($unlockStmt) {
            $unlockStmt->bind_param('i', $studentId);
            $unlockStmt->execute();
            $unlockRow = $unlockStmt->get_result()->fetch_assoc();
            $unlockStmt->close();
            if ((int)($unlockRow['is_unlocked'] ?? 0) === 1) {
                return ['allowed' => true, 'reason' => ''];
            }
        }
    }

    return [
        'allowed' => false,
        'reason' => 'Document generation is locked until internal phase is completed or an OJT assignment is active/completed.',
    ];
}
