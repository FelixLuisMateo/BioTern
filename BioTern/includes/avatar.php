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

if (!function_exists('biotern_avatar_discover_web_base')) {
    function biotern_avatar_discover_web_base(): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        $cursor = str_replace('\\', '/', dirname($scriptName));
        if ($cursor === '\\' || $cursor === '.') {
            $cursor = '/';
        }

        for ($i = 0; $i < 8; $i++) {
            $candidate = rtrim($cursor, '/');
            if ($candidate === '') {
                $candidate = '/';
            }

            if ($docRoot !== '') {
                $probe = $docRoot . ($candidate === '/' ? '' : $candidate) . '/includes/avatar-image.php';
                if (is_file($probe)) {
                    return $candidate === '/' ? '' : $candidate;
                }
            }

            if ($candidate === '/' || $candidate === '') {
                break;
            }

            $parent = dirname($candidate);
            $cursor = ($parent === '\\' || $parent === '.') ? '/' : str_replace('\\', '/', $parent);
        }

        // Last-resort fallback keeps behavior from previous implementation.
        $legacyBase = rtrim(dirname(dirname($scriptName)), '/');
        if ($legacyBase === '' || $legacyBase === '.') {
            return '';
        }
        return $legacyBase;
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
            $baseDir = biotern_avatar_discover_web_base();
            return $baseDir . '/includes/avatar-image.php?uid=' . $userId;
        }

        return '';
    }
}

if (!function_exists('biotern_avatar_has_db_picture')) {
    function biotern_avatar_has_db_picture(int $userId): bool
    {
        static $cache = [];

        if ($userId <= 0) {
            return false;
        }

        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }

        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli) || $GLOBALS['conn']->connect_errno) {
            $dbConfig = dirname(__DIR__) . '/config/db.php';
            if (is_file($dbConfig)) {
                require_once $dbConfig;
            }
        }

        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli) || $GLOBALS['conn']->connect_errno) {
            $cache[$userId] = false;
            return false;
        }

        $conn = $GLOBALS['conn'];
        $conn->query("CREATE TABLE IF NOT EXISTS user_profile_pictures (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            image_mime VARCHAR(64) NOT NULL,
            image_data LONGBLOB NOT NULL,
            image_size INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_profile_picture (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare('SELECT 1 FROM user_profile_pictures WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            $cache[$userId] = false;
            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();

        $cache[$userId] = $exists;
        return $exists;
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

        if ($userId > 0 && biotern_avatar_has_db_picture($userId)) {
            return biotern_avatar_db_src('db-avatar', $userId);
        }

        return biotern_avatar_default_path($userId);
    }
}
