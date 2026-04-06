param(
    [string]$SiteBaseUrl = "https://biotern-ccst.vercel.app",
    [string]$BridgeToken = "",
    [string]$WorkspaceRoot = "",
    [int]$DefaultPollSeconds = 30,
    [switch]$ShowWindow
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($WorkspaceRoot)) {
    $WorkspaceRoot = Split-Path -Parent $PSScriptRoot
}

$rootCandidates = @(
    $WorkspaceRoot,
    (Join-Path (Split-Path -Parent $WorkspaceRoot) 'BioTern')
)

$resolvedRoot = $null
$bridgeWorkerPath = $null

foreach ($candidate in $rootCandidates) {
    $candidateWorker = Join-Path $candidate 'tools\bridge-worker.ps1'
    if (Test-Path $candidateWorker) {
        $resolvedRoot = $candidate
        $bridgeWorkerPath = $candidateWorker
        break
    }
}

if (-not $bridgeWorkerPath) {
    throw 'bridge-worker.ps1 not found in BioTern_unified/tools or sibling BioTern/tools.'
}

$bridgeLogPath = Join-Path $resolvedRoot 'tools\bridge-worker.log'

if ([string]::IsNullOrWhiteSpace($BridgeToken)) {
    $BridgeToken = $env:BIOTERN_BRIDGE_TOKEN
}

if ([string]::IsNullOrWhiteSpace($BridgeToken)) {
    throw 'Bridge token is required. Pass -BridgeToken or set BIOTERN_BRIDGE_TOKEN environment variable.'
}

Get-CimInstance Win32_Process |
    Where-Object { $_.CommandLine -like '*bridge-worker.ps1*' } |
    ForEach-Object {
        try {
            Stop-Process -Id $_.ProcessId -Force -ErrorAction Stop
        } catch {
        }
    }

Start-Sleep -Seconds 1

$windowStyle = if ($ShowWindow) { 'Normal' } else { 'Hidden' }
$psArgs = ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -SiteBaseUrl "{1}" -BridgeToken "{2}" -WorkspaceRoot "{3}" -DefaultPollSeconds {4}' -f $bridgeWorkerPath, $SiteBaseUrl, $BridgeToken, $resolvedRoot, $DefaultPollSeconds)
Start-Process powershell.exe -ArgumentList $psArgs -WindowStyle $windowStyle | Out-Null

Write-Host 'Bridge worker restarted successfully.'
Write-Host ("Worker: {0}" -f $bridgeWorkerPath)
Write-Host ("Log: {0}" -f $bridgeLogPath)
Write-Host 'Tip: Get-Content -Path "$bridgeLogPath" -Tail 40 -Wait'
