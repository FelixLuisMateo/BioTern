param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$WorkspaceRoot = "",
    [int]$DefaultPollSeconds = 30,
    [bool]$PreferLocalConnectorNetwork = $true
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($WorkspaceRoot)) {
    $WorkspaceRoot = Split-Path -Parent $PSScriptRoot
}

$workerPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.ps1'
$logPath = Join-Path $WorkspaceRoot 'tools\bridge-worker-autostart.log'
$mutexName = 'Global\BioTernBridgeWorkerAutostart'

if (-not (Test-Path $workerPath)) {
    throw "Bridge worker script not found at $workerPath"
}

$mutex = New-Object System.Threading.Mutex($false, $mutexName)
$acquired = $false

try {
    try {
        $acquired = $mutex.WaitOne(0)
    } catch {
        $acquired = $false
    }

    if (-not $acquired) {
        # Another autostart instance is already running.
        exit 0
    }

    while ($true) {
        $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
        $line = "[$stamp] Starting bridge-worker.ps1"
        try {
            Add-Content -Path $logPath -Value $line
        } catch {
            # Ignore logger failures.
        }

        try {
            & $workerPath -SiteBaseUrl $SiteBaseUrl -BridgeToken $BridgeToken -WorkspaceRoot $WorkspaceRoot -DefaultPollSeconds $DefaultPollSeconds -PreferLocalConnectorNetwork:$PreferLocalConnectorNetwork
            $exitLine = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] bridge-worker.ps1 exited normally. Restarting in 5s."
            try { Add-Content -Path $logPath -Value $exitLine } catch {}
        } catch {
            $errLine = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] bridge-worker.ps1 crashed: $($_.Exception.Message). Restarting in 5s."
            try { Add-Content -Path $logPath -Value $errLine } catch {}
        }

        Start-Sleep -Seconds 5
    }
}
finally {
    if ($acquired) {
        try {
            $mutex.ReleaseMutex() | Out-Null
        } catch {
            # no-op
        }
    }

    $mutex.Dispose()
}
