$ErrorActionPreference = 'Stop'

$repoRoot = (git rev-parse --show-toplevel).Trim()
if ([string]::IsNullOrWhiteSpace($repoRoot)) {
    Write-Host 'Unable to determine repository root.'
    exit 1
}

Set-Location $repoRoot
$stagedPhpFiles = @(git diff --cached --name-only --diff-filter=ACMR -- '*.php')
if ($stagedPhpFiles.Count -eq 0) {
    exit 0
}

$badFiles = @()
foreach ($relativePath in $stagedPhpFiles) {
    if ([string]::IsNullOrWhiteSpace($relativePath)) {
        continue
    }

    $fullPath = Join-Path $repoRoot $relativePath
    if (-not (Test-Path $fullPath -PathType Leaf)) {
        continue
    }

    $stream = [System.IO.File]::OpenRead($fullPath)
    try {
        if ($stream.Length -lt 3) {
            continue
        }

        $b1 = $stream.ReadByte()
        $b2 = $stream.ReadByte()
        $b3 = $stream.ReadByte()
        if ($b1 -eq 0xEF -and $b2 -eq 0xBB -and $b3 -eq 0xBF) {
            $badFiles += $relativePath
        }
    } finally {
        $stream.Dispose()
    }
}

if ($badFiles.Count -gt 0) {
    Write-Host 'ERROR: UTF-8 BOM detected in staged PHP files:'
    foreach ($file in $badFiles) {
        Write-Host " - $file"
    }
    Write-Host 'Please save these files as UTF-8 (without BOM) and stage them again.'
    exit 1
}

exit 0
