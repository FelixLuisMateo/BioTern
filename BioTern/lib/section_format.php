<?php

if (!function_exists('biotern_format_section_code')) {
    function biotern_format_section_code(?string $code): string
    {
        $value = trim((string)$code);
        if ($value === '') {
            return '';
        }

        if (strpos($value, ' - ') !== false) {
            return $value;
        }

        if (preg_match('/^([A-Za-z]+)([0-9]+[A-Za-z]*)$/', $value, $matches)) {
            return strtoupper((string)$matches[1]) . ' - ' . strtoupper((string)$matches[2]);
        }

        return $value;
    }
}

if (!function_exists('biotern_format_section_label')) {
    function biotern_format_section_label(?string $code, ?string $name = null): string
    {
        $formattedCode = biotern_format_section_code($code);
        if ($formattedCode !== '') {
            return $formattedCode;
        }

        return trim((string)$name);
    }
}
