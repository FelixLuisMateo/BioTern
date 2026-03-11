<?php

if (!function_exists('biotern_theme_defaults')) {
    function biotern_theme_defaults(): array
    {
        return [
            'skin' => 'light',
            'menu' => 'auto',
            'font' => 'default',
            'navigation' => 'light',
            'header' => 'light',
            'scheme' => 'blue',
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

if (!function_exists('biotern_theme_allowed_schemes')) {
    function biotern_theme_allowed_schemes(): array
    {
        return [
            'blue',
            'gray',
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
        $scheme = isset($preferences['scheme']) ? strtolower(trim((string) $preferences['scheme'])) : $defaults['scheme'];

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

        if (!in_array($scheme, biotern_theme_allowed_schemes(), true)) {
            $scheme = $defaults['scheme'];
        }

        return [
            'skin' => $skin,
            'menu' => $menu,
            'font' => $font,
            'navigation' => $navigation,
            'header' => $header,
            'scheme' => $scheme,
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
        $sessionPrefs = isset($_SESSION['biotern_theme_preferences']) && is_array($_SESSION['biotern_theme_preferences'])
            ? $_SESSION['biotern_theme_preferences']
            : [];

        $cookiePrefs = [];
        if (!empty($_COOKIE['biotern_theme_preferences'])) {
            $decoded = json_decode((string) $_COOKIE['biotern_theme_preferences'], true);
            if (is_array($decoded)) {
                $cookiePrefs = $decoded;
            }
        }

        $merged = array_merge($defaults, $cookiePrefs, $sessionPrefs);
        $sanitized = biotern_theme_sanitize($merged);
        $_SESSION['biotern_theme_preferences'] = $sanitized;

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
        $_SESSION['biotern_theme_preferences'] = $sanitized;

        setcookie('biotern_theme_preferences', json_encode($sanitized), [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'httponly' => false,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);

        return $sanitized;
    }
}
