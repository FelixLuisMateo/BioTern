<?php
require_once dirname(__DIR__) . '/config/db.php';

if (!function_exists('biotern_theme_db_connection')) {
    function biotern_theme_db_connection(): ?mysqli
    {
        if (isset($GLOBALS['conn']) && ($GLOBALS['conn'] instanceof mysqli) && !$GLOBALS['conn']->connect_errno) {
            return $GLOBALS['conn'];
        }
        return null;
    }
}

if (!function_exists('biotern_theme_ensure_preferences_table')) {
    function biotern_theme_ensure_preferences_table(mysqli $conn): bool
    {
        return (bool)$conn->query("CREATE TABLE IF NOT EXISTS user_theme_preferences (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            preferences_json TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_theme_preferences_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}

if (!function_exists('biotern_theme_db_load_for_user')) {
    function biotern_theme_db_load_for_user(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $conn = biotern_theme_db_connection();
        if (!($conn instanceof mysqli)) {
            return [];
        }

        if (!biotern_theme_ensure_preferences_table($conn)) {
            return [];
        }

        $stmt = $conn->prepare('SELECT preferences_json FROM user_theme_preferences WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!is_array($row) || !isset($row['preferences_json'])) {
            return [];
        }

        $decoded = json_decode((string)$row['preferences_json'], true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('biotern_theme_db_save_for_user')) {
    function biotern_theme_db_save_for_user(int $userId, array $preferences): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $conn = biotern_theme_db_connection();
        if (!($conn instanceof mysqli)) {
            return false;
        }

        if (!biotern_theme_ensure_preferences_table($conn)) {
            return false;
        }

        $json = json_encode($preferences, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return false;
        }

        $stmt = $conn->prepare('INSERT INTO user_theme_preferences (user_id, preferences_json, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE preferences_json = VALUES(preferences_json), updated_at = NOW()');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('is', $userId, $json);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}

if (!function_exists('biotern_theme_current_user_id')) {
    function biotern_theme_current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('biotern_theme_cookie_name_for_user')) {
    function biotern_theme_cookie_name_for_user(int $userId): string
    {
        return $userId > 0 ? ('biotern_theme_preferences_u_' . $userId) : 'biotern_theme_preferences';
    }
}
if (!function_exists('biotern_theme_defaults')) {
    function biotern_theme_defaults(): array
    {
        return [
            'skin' => 'light',
            'menu' => 'auto',
            'font' => 'default',
            'navigation' => 'light',
            'header' => 'light',
        ];
    }
}

if (!function_exists('biotern_theme_allowed_fonts')) {
    function biotern_theme_allowed_fonts(): array
    {
        return [
            'default',
            'app-font-family-inter',
            'app-font-family-lato',
            'app-font-family-rubik',
            'app-font-family-cinzel',
            'app-font-family-nunito',
            'app-font-family-roboto',
            'app-font-family-ubuntu',
            'app-font-family-poppins',
            'app-font-family-raleway',
            'app-font-family-system-ui',
            'app-font-family-noto-sans',
            'app-font-family-fira-sans',
            'app-font-family-work-sans',
            'app-font-family-open-sans',
            'app-font-family-maven-pro',
            'app-font-family-quicksand',
            'app-font-family-montserrat',
            'app-font-family-josefin-sans',
            'app-font-family-ibm-plex-sans',
            'app-font-family-source-sans-pro',
            'app-font-family-montserrat-alt',
            'app-font-family-roboto-slab',
        ];
    }
}

if (!function_exists('biotern_theme_sanitize')) {
    function biotern_theme_sanitize(array $preferences): array
    {
        $defaults = biotern_theme_defaults();

        $skin = isset($preferences['skin']) ? strtolower(trim((string) $preferences['skin'])) : $defaults['skin'];
        $menu = isset($preferences['menu']) ? strtolower(trim((string) $preferences['menu'])) : $defaults['menu'];
        $font = isset($preferences['font']) ? strtolower(trim((string) $preferences['font'])) : $defaults['font'];
        $navigation = isset($preferences['navigation']) ? strtolower(trim((string) $preferences['navigation'])) : $defaults['navigation'];
        $header = isset($preferences['header']) ? strtolower(trim((string) $preferences['header'])) : $defaults['header'];

        if (!in_array($skin, ['light', 'dark'], true)) {
            $skin = $defaults['skin'];
        }

        if (!in_array($menu, ['auto', 'mini', 'expanded'], true)) {
            $menu = $defaults['menu'];
        }

        if (!in_array($font, biotern_theme_allowed_fonts(), true)) {
            $font = $defaults['font'];
        }

        if (!in_array($navigation, ['light', 'dark'], true)) {
            $navigation = $defaults['navigation'];
        }

        if (!in_array($header, ['light', 'dark'], true)) {
            $header = $defaults['header'];
        }

        return [
            'skin' => $skin,
            'menu' => $menu,
            'font' => $font,
            'navigation' => $navigation,
            'header' => $header,
        ];
    }
}

if (!function_exists('biotern_theme_preferences')) {
    function biotern_theme_preferences(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $defaults = biotern_theme_defaults();
        $userId = biotern_theme_current_user_id();
        $dbPrefs = biotern_theme_db_load_for_user($userId);
        $sessionPrefs = isset($_SESSION['biotern_theme_preferences']) && is_array($_SESSION['biotern_theme_preferences'])
            ? $_SESSION['biotern_theme_preferences']
            : [];

        $cookiePrefs = [];
        $cookieName = biotern_theme_cookie_name_for_user($userId);
        if (!empty($_COOKIE[$cookieName])) {
            $decoded = json_decode((string) $_COOKIE[$cookieName], true);
            if (is_array($decoded)) {
                $cookiePrefs = $decoded;
            }
        } elseif ($cookieName !== 'biotern_theme_preferences' && !empty($_COOKIE['biotern_theme_preferences'])) {
            $decoded = json_decode((string) $_COOKIE['biotern_theme_preferences'], true);
            if (is_array($decoded)) {
                $cookiePrefs = $decoded;
            }
        }

        $merged = array_merge($defaults, $dbPrefs, $cookiePrefs, $sessionPrefs);
        $sanitized = biotern_theme_sanitize($merged);
        $_SESSION['biotern_theme_preferences'] = $sanitized;

        if ($userId > 0) {
            biotern_theme_db_save_for_user($userId, $sanitized);
        }

        return $sanitized;
    }
}

if (!function_exists('biotern_save_theme_preferences')) {
    function biotern_save_theme_preferences(array $preferences): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sanitized = biotern_theme_sanitize($preferences);
        $userId = biotern_theme_current_user_id();
        $_SESSION['biotern_theme_preferences'] = $sanitized;

        if ($userId > 0) {
            biotern_theme_db_save_for_user($userId, $sanitized);
        }

        setcookie(biotern_theme_cookie_name_for_user($userId), json_encode($sanitized), [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'httponly' => false,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);

        return $sanitized;
    }
}

