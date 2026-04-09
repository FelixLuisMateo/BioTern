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
        $normalized = preg_replace('#^(?:BioTern/)?BioTern/#i', '', (string)$normalized);

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

if (!function_exists('biotern_avatar_db_src')) {
    function biotern_avatar_db_src(string $rawPath, int $userId = 0): string
    {
        if ($userId <= 0) {
            return '';
        }

        $normalized = strtolower(trim((string)$rawPath));
        if ($normalized === 'db-avatar' || $normalized === 'db_avatar') {
            return 'includes/avatar-image.php?uid=' . $userId;
        }

        return '';
    }
}

if (!function_exists('biotern_avatar_public_src')) {
    function biotern_avatar_public_src(string $rawPath, int $userId = 0): string
    {
        $dbSrc = biotern_avatar_db_src($rawPath, $userId);
        if ($dbSrc !== '') {
            return $dbSrc;
        }

        $resolved = biotern_avatar_resolve_existing_path($rawPath);
        if ($resolved !== '') {
            return $resolved;
        }

        return biotern_avatar_default_path($userId);
    }
}
