<?php

if (!function_exists('biometric_machine_paths')) {
    function biometric_machine_paths(): array
    {
        $base = __DIR__;
        return [
            'project' => $base . '/device_connector/BioTernMachineConnector.csproj',
            'dll' => $base . '/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.dll',
            'exe' => $base . '/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.exe',
            'config' => $base . '/biometric_machine_config.json',
            'dotnet_home' => dirname(__DIR__) . '/.dotnet_cli',
        ];
    }
}

if (!function_exists('biometric_machine_is_windows')) {
    function biometric_machine_is_windows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}

if (!function_exists('biometric_machine_dotnet_env_prefix')) {
    function biometric_machine_dotnet_env_prefix(string $dotnetHome): string
    {
        if (biometric_machine_is_windows()) {
            return 'set DOTNET_CLI_HOME=' . escapeshellarg($dotnetHome) . ' && ';
        }

        return 'DOTNET_CLI_HOME=' . escapeshellarg($dotnetHome) . ' ';
    }
}

if (!function_exists('biometric_machine_ensure_connector_built')) {
    function biometric_machine_ensure_connector_built(): array
    {
        $paths = biometric_machine_paths();
        $hasExe = file_exists($paths['exe']);
        $hasDll = file_exists($paths['dll']);

        if ((biometric_machine_is_windows() && ($hasExe || $hasDll)) || (!biometric_machine_is_windows() && $hasDll)) {
            return ['success' => true, 'output' => []];
        }

        $command = biometric_machine_dotnet_env_prefix($paths['dotnet_home'])
            . sprintf('dotnet build %s -c Release 2>&1', escapeshellarg($paths['project']));

        $output = [];
        $code = 0;
        exec($command, $output, $code);

        $hasExe = file_exists($paths['exe']);
        $hasDll = file_exists($paths['dll']);

        return [
            'success' => $code === 0 && ((biometric_machine_is_windows() && ($hasExe || $hasDll)) || (!biometric_machine_is_windows() && $hasDll)),
            'output' => $output,
            'code' => $code,
        ];
    }
}

if (!function_exists('biometric_machine_run_command')) {
    function biometric_machine_run_command(string $command = 'sync', array $args = []): array
    {
        $paths = biometric_machine_paths();

        if (!biometric_machine_is_windows() && !file_exists($paths['dll'])) {
            return [
                'success' => false,
                'stage' => 'runtime',
                'output' => [
                    'Machine connector is Windows-only for direct LAN sync.',
                    'Run this page from your local Windows XAMPP host, or use direct ingest endpoint /api/f20h_ingest.php for cloud deployments.',
                ],
                'code' => 1,
                'text' => 'Machine connector is Windows-only for direct LAN sync. Run this page from your local Windows XAMPP host, or use direct ingest endpoint /api/f20h_ingest.php for cloud deployments.',
            ];
        }

        $build = biometric_machine_ensure_connector_built();
        if (!$build['success']) {
            return [
                'success' => false,
                'stage' => 'build',
                'output' => $build['output'] ?? [],
                'code' => $build['code'] ?? 1,
            ];
        }

        $parts = [];
        if (biometric_machine_is_windows() && file_exists($paths['exe'])) {
            $parts[] = escapeshellarg($paths['exe']);
        } else {
            $parts[] = biometric_machine_dotnet_env_prefix($paths['dotnet_home']);
            $parts[] = 'dotnet';
            $parts[] = escapeshellarg($paths['dll']);
        }

        $parts[] = escapeshellarg($paths['config']);
        $parts[] = escapeshellarg($command);
        foreach ($args as $arg) {
            $parts[] = escapeshellarg((string)$arg);
        }

        $shellCommand = implode(' ', $parts) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($shellCommand, $output, $code);

        return [
            'success' => $code === 0,
            'stage' => 'run',
            'output' => $output,
            'code' => $code,
            'text' => trim(implode("\n", $output)),
        ];
    }
}

if (!function_exists('biometric_machine_decode_data')) {
    function biometric_machine_decode_data(string $text)
    {
        $trimmed = biometric_machine_clean_output($text);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return null;
    }
}

if (!function_exists('biometric_machine_clean_output')) {
    function biometric_machine_clean_output(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        $trimmed = preg_replace('/\s*Device disconnected\.\s*$/i', '', $trimmed);
        return trim((string)$trimmed);
    }
}

if (!function_exists('biometric_machine_patch_user_name')) {
    function biometric_machine_patch_user_name(string $rawUserJson, string $newName): string
    {
        $decoded = json_decode($rawUserJson, true);
        if (!is_array($decoded)) {
            return $rawUserJson;
        }

        $keys = ['name', 'Name', 'username', 'userName', 'UserName'];
        $patched = false;
        foreach ($keys as $key) {
            if (array_key_exists($key, $decoded)) {
                $decoded[$key] = $newName;
                $patched = true;
                break;
            }
        }

        if (!$patched) {
            $decoded['name'] = $newName;
        }

        $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : $rawUserJson;
    }
}
