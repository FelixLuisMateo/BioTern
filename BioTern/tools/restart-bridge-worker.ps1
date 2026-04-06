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

$bridgeWorkerPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.ps1'
$bridgeLogPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.log'

if (-not (Test-Path $bridgeWorkerPath)) {
    throw "bridge-worker.ps1 not found at: $bridgeWorkerPath"
}

if ([string]::IsNullOrWhiteSpace($BridgeToken)) {
    $BridgeToken = $env:BIOTERN_BRIDGE_TOKEN
}

if ([string]::IsNullOrWhiteSpace($BridgeToken)) {
    throw 'Bridge token is required. Pass -BridgeToken or set BIOTERN_BRIDGE_TOKEN environment variable.'
}

# Stop old worker processes.
Get-CimInstance Win32_Process |
    Where-Object { $_.CommandLine -like '*bridge-worker.ps1*' } |
    ForEach-Object {
        try {
            Stop-Process -Id $_.ProcessId -Force -ErrorAction Stop
        } catch {
            # Ignore races where process is already gone.
        }
    }

Start-Sleep -Seconds 1

$windowStyle = if ($ShowWindow) { 'Normal' } else { 'Hidden' }

$psArgs = ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -SiteBaseUrl "{1}" -BridgeToken "{2}" -WorkspaceRoot "{3}" -DefaultPollSeconds {4}' -f $bridgeWorkerPath, $SiteBaseUrl, $BridgeToken, $WorkspaceRoot, $DefaultPollSeconds)
Start-Process powershell.exe -ArgumentList $psArgs -WindowStyle $windowStyle | Out-Null

Write-Host 'Bridge worker restarted successfully.'
Write-Host ("Worker: {0}" -f $bridgeWorkerPath)
Write-Host ("Log: {0}" -f $bridgeLogPath)
Write-Host 'Tip: Get-Content -Path "$bridgeLogPath" -Tail 40 -Wait'
