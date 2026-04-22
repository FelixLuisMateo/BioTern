<?php

if (!function_exists('biotern_section_parts')) {
    function biotern_section_parts(?string $code, ?string $name = null): array
    {
        $rawCode = trim((string)$code);
        $rawName = trim((string)$name);

        $program = '';
        $section = '';

        $normalizedCode = preg_replace('/\s*[-|]\s*/', ' ', $rawCode);
        $normalizedCode = preg_replace('/\s+/', ' ', (string)$normalizedCode);
        $normalizedCode = trim((string)$normalizedCode);

        if ($normalizedCode !== '') {
            if (preg_match('/^(\d+[A-Za-z]*)\s+([A-Za-z][A-Za-z0-9]*)$/', $normalizedCode, $matches)) {
                $program = strtoupper((string)$matches[2]);
                $section = strtoupper((string)$matches[1]);
            } elseif (preg_match('/^([A-Za-z][A-Za-z0-9]*)\s+(\d+[A-Za-z]*)$/', $normalizedCode, $matches)) {
                $program = strtoupper((string)$matches[1]);
                $section = strtoupper((string)$matches[2]);
            } elseif (preg_match('/^([A-Za-z]+)([0-9]+[A-Za-z]*)$/', $normalizedCode, $matches)) {
                $program = strtoupper((string)$matches[1]);
                $section = strtoupper((string)$matches[2]);
            } else {
                $program = $normalizedCode;
            }
        }

        $normalizedName = preg_replace('/\s*[-|]\s*/', ' ', $rawName);
        $normalizedName = preg_replace('/\s+/', ' ', (string)$normalizedName);
        $normalizedName = trim((string)$normalizedName);

        if ($normalizedName !== '') {
            $nameProgram = '';
            $nameSection = '';
            $nameCandidate = $normalizedName;

            if ($program !== '' && $nameCandidate !== '') {
                $pattern = '/^' . preg_quote($program, '/') . '(?:\s+|\s*[-|]\s*)/i';
                $nameCandidate = preg_replace($pattern, '', $nameCandidate);
                $nameCandidate = trim((string)$nameCandidate);
            }

            if (preg_match('/^(\d+[A-Za-z]*)\s+([A-Za-z][A-Za-z0-9]*)$/', $normalizedName, $matches)) {
                $nameProgram = strtoupper((string)$matches[2]);
                $nameSection = strtoupper((string)$matches[1]);
            } elseif (preg_match('/^([A-Za-z][A-Za-z0-9]*)\s+(\d+[A-Za-z]*)$/', $normalizedName, $matches)) {
                $nameProgram = strtoupper((string)$matches[1]);
                $nameSection = strtoupper((string)$matches[2]);
            } elseif (preg_match('/^([A-Za-z]+)([0-9]+[A-Za-z]*)$/', $normalizedName, $matches)) {
                $nameProgram = strtoupper((string)$matches[1]);
                $nameSection = strtoupper((string)$matches[2]);
            }

            if ($nameSection === '' && $nameCandidate !== '') {
                if (preg_match('/^(\d+[A-Za-z]*)$/', $nameCandidate, $matches)) {
                    $nameSection = strtoupper((string)$matches[1]);
                } elseif (preg_match('/^([A-Za-z0-9]+)$/', $nameCandidate, $matches)) {
                    $nameSection = strtoupper((string)$matches[1]);
                }
            }

            if ($program !== '' && $section !== '') {
                if ($nameSection !== '' && ($nameProgram === '' || $nameProgram === $program)) {
                    $section = $nameSection;
                } elseif (strcasecmp($normalizedName, $section) === 0) {
                    $section = $rawName !== '' ? $rawName : $section;
                } elseif ($nameCandidate !== '' && strcasecmp($nameCandidate, $section) !== 0) {
                    $section = $nameCandidate;
                }
            } elseif ($program !== '' && $section === '') {
                if ($nameSection !== '' && ($nameProgram === '' || $nameProgram === $program)) {
                    $section = $nameSection;
                } elseif (strcasecmp($normalizedName, $program) !== 0) {
                    $section = $nameCandidate !== '' ? $nameCandidate : ($rawName !== '' ? $rawName : $normalizedName);
                }
            } elseif ($program === '' && $section === '') {
                if ($nameProgram !== '') {
                    $program = $nameProgram;
                    $section = $nameSection;
                } else {
                    $section = $nameCandidate !== '' ? $nameCandidate : ($rawName !== '' ? $rawName : $normalizedName);
                }
            }
        }

        return [
            'program' => trim((string)$program),
            'section' => trim((string)$section),
        ];
    }
}

if (!function_exists('biotern_normalize_section_code')) {
    function biotern_normalize_section_code(?string $code): string
    {
        $parts = biotern_section_parts($code, null);
        $program = strtoupper(trim((string)($parts['program'] ?? '')));
        $section = strtoupper(trim((string)($parts['section'] ?? '')));

        if ($program !== '' && $section !== '') {
            return $program . '-' . $section;
        }

        if ($program !== '') {
            return $program;
        }

        return $section;
    }
}

if (!function_exists('biotern_format_section_code')) {
    function biotern_format_section_code(?string $code): string
    {
        $parts = biotern_section_parts($code, null);
        $program = trim((string)($parts['program'] ?? ''));
        $section = trim((string)($parts['section'] ?? ''));

        if ($program === '' && $section === '') {
            return '';
        }

        if ($program !== '' && $section !== '') {
            return $program . ' | ' . strtoupper((string)$section);
        }

        return $program !== '' ? $program : $section;
    }
}

if (!function_exists('biotern_format_section_label')) {
    function biotern_format_section_label(?string $code, ?string $name = null): string
    {
        $parts = biotern_section_parts($code, $name);
        $program = trim((string)($parts['program'] ?? ''));
        $section = trim((string)($parts['section'] ?? ''));

        if ($program !== '' && $section !== '') {
            return $program . ' | ' . $section;
        }

        if ($program !== '') {
            return $program;
        }

        if ($section !== '') {
            return $section;
        }

        return trim((string)$name);
    }
}
