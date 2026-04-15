<?php

if (!function_exists('biotern_format_section_code')) {
    function biotern_format_section_code(?string $code): string
    {
        $value = trim((string)$code);
        if ($value === '') {
            return '';
        }

        // Normalize separators first so legacy values like "2A-ACT" or "2A ACT"
        // can be displayed consistently as "ACT 2A".
        $value = preg_replace('/\s*-\s*/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string)$value);
        $value = trim((string)$value);

        if (preg_match('/^(\d+[A-Za-z]*)\s+([A-Za-z][A-Za-z0-9]*)$/', $value, $matches)) {
            return strtoupper((string)$matches[2]) . ' ' . strtoupper((string)$matches[1]);
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9]*)\s+(\d+[A-Za-z]*)$/', $value, $matches)) {
            return strtoupper((string)$matches[1]) . ' ' . strtoupper((string)$matches[2]);
        }

        if (preg_match('/^([A-Za-z]+)([0-9]+[A-Za-z]*)$/', $value, $matches)) {
            return strtoupper((string)$matches[1]) . ' ' . strtoupper((string)$matches[2]);
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
