<?php

require_once __DIR__ . '/ops_helpers.php';

if (!function_exists('biotern_scope_current_role')) {
    function biotern_scope_current_role(): string
    {
        return strtolower(trim((string)(
            $_SESSION['role'] ??
            $_SESSION['user_role'] ??
            $_SESSION['account_role'] ??
            $_SESSION['user_type'] ??
            $_SESSION['type'] ??
            ''
        )));
    }
}

if (!function_exists('biotern_scope_current_user_id')) {
    function biotern_scope_current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('biotern_scope_supervisor_profile_id')) {
    function biotern_scope_supervisor_profile_id(mysqli $conn, int $userId): int
    {
        static $cache = [];
        if ($userId <= 0) {
            return 0;
        }
        if (array_key_exists($userId, $cache)) {
            return (int)$cache[$userId];
        }

        $profileId = 0;
        $stmt = $conn->prepare('SELECT id FROM supervisors WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $profileId = (int)($row['id'] ?? 0);
            $stmt->close();
        }

        $cache[$userId] = $profileId;
        return $profileId;
    }
}

if (!function_exists('biotern_scope_supervisor_ids')) {
    function biotern_scope_supervisor_ids(mysqli $conn): array
    {
        if (biotern_scope_current_role() !== 'supervisor') {
            return [];
        }

        $ids = [];
        $userId = biotern_scope_current_user_id();
        if ($userId > 0) {
            $ids[] = $userId;
            $profileId = biotern_scope_supervisor_profile_id($conn, $userId);
            if ($profileId > 0) {
                $ids[] = $profileId;
            }
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }
}

if (!function_exists('biotern_scope_coordinator_course_ids')) {
    function biotern_scope_coordinator_course_ids(mysqli $conn): array
    {
        if (biotern_scope_current_role() !== 'coordinator') {
            return [];
        }

        $userId = biotern_scope_current_user_id();
        if ($userId <= 0 || !function_exists('coordinator_course_ids')) {
            return [];
        }

        return coordinator_course_ids($conn, $userId);
    }
}

if (!function_exists('biotern_scope_student_sql')) {
    function biotern_scope_student_sql(mysqli $conn, string $studentAlias = 's', ?string $internshipAlias = null): string
    {
        $role = biotern_scope_current_role();
        $studentAlias = preg_replace('/[^A-Za-z0-9_]/', '', $studentAlias) ?: 's';

        if ($role === 'coordinator') {
            $courseIds = biotern_scope_coordinator_course_ids($conn);
            if (empty($courseIds)) {
                return '1 = 0';
            }
            return "{$studentAlias}.course_id IN (" . implode(',', array_map('intval', $courseIds)) . ")";
        }

        if ($role !== 'supervisor') {
            return '1 = 1';
        }

        $ids = biotern_scope_supervisor_ids($conn);
        if (empty($ids)) {
            return '1 = 0';
        }

        $idList = implode(',', $ids);
        $parts = ["{$studentAlias}.supervisor_id IN ({$idList})"];

        if ($internshipAlias !== null && $internshipAlias !== '') {
            $internshipAlias = preg_replace('/[^A-Za-z0-9_]/', '', $internshipAlias);
            if ($internshipAlias !== '') {
                $parts[] = "{$internshipAlias}.supervisor_id IN ({$idList})";
            }
        } else {
            $parts[] = "EXISTS (
                SELECT 1
                FROM internships scope_i
                WHERE scope_i.student_id = {$studentAlias}.id
                  AND scope_i.supervisor_id IN ({$idList})
                  AND (scope_i.deleted_at IS NULL OR scope_i.deleted_at = '0000-00-00 00:00:00')
            )";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}
