<?php

if (!function_exists('biotern_avatar_normalize_path')) {
    function biotern_avatar_normalize_path(string $rawPath): string
    {
        $rawPath = trim(str_replace('\\', '/', $rawPath));
        if ($rawPath === '') {
            return '';
        }

        $parsed = parse_url($rawPath, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $rawPath = $parsed;
        }

        $normalized = ltrim($rawPath, '/');
        $normalized = preg_replace('#^\./#', '', $normalized);
        $normalized = preg_replace('#^(?:BioTern/)?BioTern_unified/#i', '', (string)$normalized);

        return ltrim((string)$normalized, '/');
    }
}

if (!function_exists('biotern_avatar_resolve_existing_path')) {
    function biotern_avatar_resolve_existing_path(string $rawPath): string
    {
        $normalized = biotern_avatar_normalize_path($rawPath);
        if ($normalized === '') {
            return '';
        }

        $candidates = [$normalized];

        if (strpos($normalized, 'avatar/uploads/') !== false && strpos($normalized, 'assets/') !== 0) {
            $avatarPos = strpos($normalized, 'avatar/uploads/');
            if ($avatarPos !== false) {
                $candidates[] = 'assets/images/' . substr($normalized, $avatarPos);
            }
        }

        if (strpos($normalized, 'uploads/') === 0) {
            $candidates[] = 'assets/images/avatar/' . $normalized;
        }

        if (strpos($normalized, '/') === false) {
            $candidates[] = 'assets/images/avatar/uploads/' . $normalized;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }
            $candidate = ltrim(str_replace('\\', '/', $candidate), '/');
            if (is_file(dirname(__DIR__) . '/' . $candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('biotern_avatar_default_path')) {
    function biotern_avatar_default_path(int $userId = 0): string
    {
        $path = 'assets/images/avatar/' . ((($userId > 0 ? $userId : 1) % 5) + 1) . '.png';
        if (!is_file(dirname(__DIR__) . '/' . $path)) {
            return 'assets/images/avatar/1.png';
        }
        return $path;
    }
}

if (!function_exists('biotern_avatar_public_src')) {
    function biotern_avatar_public_src(string $rawPath, int $userId = 0): string
    {
        $resolved = biotern_avatar_resolve_existing_path($rawPath);
        if ($resolved !== '') {
            return $resolved;
        }

        return biotern_avatar_default_path($userId);
    }
}

if (!function_exists('biotern_avatar_sync_profile_path')) {
    function biotern_avatar_sync_profile_path(mysqli $conn, int $userId, string $profilePath): void
    {
        if ($userId <= 0 || $profilePath === '') {
            return;
        }

        $normalized = biotern_avatar_normalize_path($profilePath);
        if ($normalized === '') {
            return;
        }

        $resolved = biotern_avatar_resolve_existing_path($normalized);
        if ($resolved === '') {
            $resolved = $normalized;
        }

        $userStmt = $conn->prepare('UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?');
        if ($userStmt) {
            $userStmt->bind_param('si', $resolved, $userId);
            $userStmt->execute();
            $userStmt->close();
        }

        $queries = [
            'UPDATE students SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?',
            'UPDATE coordinators SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?',
            'UPDATE supervisors SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?',
        ];

        foreach ($queries as $sql) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('si', $resolved, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('biotern_avatar_normalize_all_users')) {
    function biotern_avatar_normalize_all_users(mysqli $conn): int
    {
        $updated = 0;
        $res = $conn->query('SELECT id, profile_picture FROM users WHERE profile_picture IS NOT NULL AND profile_picture <> ""');
        if (!($res instanceof mysqli_result)) {
            return $updated;
        }

        $stmt = $conn->prepare('UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return $updated;
        }

        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $raw = (string)($row['profile_picture'] ?? '');
            if ($id <= 0 || $raw === '') {
                continue;
            }

            $normalized = biotern_avatar_normalize_path($raw);
            if ($normalized === '') {
                continue;
            }

            $resolved = biotern_avatar_resolve_existing_path($normalized);
            $newValue = $resolved !== '' ? $resolved : $normalized;
            if ($newValue === $raw) {
                continue;
            }

            $stmt->bind_param('si', $newValue, $id);
            if ($stmt->execute()) {
                $updated++;
            }
        }

        $stmt->close();
        return $updated;
    }
}
