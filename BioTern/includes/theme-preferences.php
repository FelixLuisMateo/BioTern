<?php

if (!function_exists('biotern_theme_defaults')) {
    function biotern_theme_defaults(): array
    {
        return [
            'skin' => 'light',
            'menu' => 'auto',
            'font' => 'app-font-family-montserrat',
            'navigation' => 'light',
            'header' => 'light',
            'scheme' => 'blue',
            'surfaces' => 'linked',
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

if (!function_exists('biotern_theme_normalize_scheme')) {
    function biotern_theme_normalize_scheme($scheme): string
    {
        $value = strtolower(trim((string) $scheme));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value);
        $value = trim((string) $value, '-');
        return $value !== '' ? $value : 'blue';
    }
}

if (!function_exists('biotern_theme_registered_schemes')) {
    function biotern_theme_registered_schemes(): array
    {
        // Register selectable schemes here as slug => label.
        return [
            'blue' => 'Blue (Default)',
            'gray' => 'Gray',
        ];
    }
}

if (!function_exists('biotern_theme_scheme_label')) {
    function biotern_theme_scheme_label(string $scheme): string
    {
        $normalized = biotern_theme_normalize_scheme($scheme);
        $registered = biotern_theme_registered_schemes();
        if (isset($registered[$normalized]) && trim((string) $registered[$normalized]) !== '') {
            return (string) $registered[$normalized];
        }
        return ucwords(str_replace('-', ' ', $normalized));
    }
}

if (!function_exists('biotern_theme_allowed_schemes')) {
    function biotern_theme_allowed_schemes(): array
    {
        return array_keys(biotern_theme_registered_schemes());
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
        $scheme = biotern_theme_normalize_scheme($preferences['scheme'] ?? $defaults['scheme']);
        $surfaces = isset($preferences['surfaces']) ? strtolower(trim((string) $preferences['surfaces'])) : $defaults['surfaces'];

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

        if (!in_array($surfaces, ['linked', 'independent'], true)) {
            $surfaces = $defaults['surfaces'];
        }

        return [
            'skin' => $skin,
            'menu' => $menu,
            'font' => $font,
            'navigation' => $navigation,
            'header' => $header,
            'scheme' => $scheme,
            'surfaces' => $surfaces,
        ];
    }
}

if (!function_exists('biotern_theme_preferences')) {
    function biotern_theme_current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    function biotern_theme_cookie_name_for_user(int $userId): string
    {
        return $userId > 0 ? ('biotern_theme_preferences_u_' . $userId) : 'biotern_theme_preferences';
    }

    function biotern_theme_preferences(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $defaults = biotern_theme_defaults();
        $userId = biotern_theme_current_user_id();

        $sessionPrefs = [];
        $legacySessionPrefs = [];
        if ($userId > 0
            && isset($_SESSION['biotern_theme_preferences_by_user'])
            && is_array($_SESSION['biotern_theme_preferences_by_user'])
            && isset($_SESSION['biotern_theme_preferences_by_user'][$userId])
            && is_array($_SESSION['biotern_theme_preferences_by_user'][$userId])) {
            $sessionPrefs = $_SESSION['biotern_theme_preferences_by_user'][$userId];
        } elseif (isset($_SESSION['biotern_theme_preferences']) && is_array($_SESSION['biotern_theme_preferences'])) {
            // Backward compatibility with previous single-session key.
            $legacySessionPrefs = $_SESSION['biotern_theme_preferences'];
        }

        $cookiePrefs = [];
        $cookieName = biotern_theme_cookie_name_for_user($userId);
        if (!empty($_COOKIE[$cookieName])) {
            $decoded = json_decode((string) $_COOKIE[$cookieName], true);
            if (is_array($decoded)) {
                $cookiePrefs = $decoded;
            }
        } elseif ($cookieName !== 'biotern_theme_preferences' && !empty($_COOKIE['biotern_theme_preferences'])) {
            // Fallback for older builds that used one global cookie.
            $decoded = json_decode((string) $_COOKIE['biotern_theme_preferences'], true);
            if (is_array($decoded)) {
                $cookiePrefs = $decoded;
            }
        }

        // Preference precedence:
        // 1) defaults
        // 2) legacy session fallback (only when per-user session is absent)
        // 3) cookie (per-user cookie should win over legacy fallback)
        // 4) per-user session (authoritative when present)
        $merged = array_merge($defaults, $legacySessionPrefs, $cookiePrefs, $sessionPrefs);
        $sanitized = biotern_theme_sanitize($merged);
        $_SESSION['biotern_theme_preferences'] = $sanitized;
        if (!isset($_SESSION['biotern_theme_preferences_by_user']) || !is_array($_SESSION['biotern_theme_preferences_by_user'])) {
            $_SESSION['biotern_theme_preferences_by_user'] = [];
        }
        if ($userId > 0) {
            $_SESSION['biotern_theme_preferences_by_user'][$userId] = $sanitized;
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
        if (!isset($_SESSION['biotern_theme_preferences_by_user']) || !is_array($_SESSION['biotern_theme_preferences_by_user'])) {
            $_SESSION['biotern_theme_preferences_by_user'] = [];
        }
        if ($userId > 0) {
            $_SESSION['biotern_theme_preferences_by_user'][$userId] = $sanitized;
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


