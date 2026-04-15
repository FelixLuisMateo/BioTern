param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$WorkspaceRoot = "",
    [int]$DefaultPollSeconds = 30,
    $PreferLocalConnectorNetwork = $false
)

$ErrorActionPreference = 'Stop'

function Resolve-BridgeBool {
    param($Value, [bool]$Default = $true)

    if ($null -eq $Value) {
        return $Default
    }

    if ($Value -is [bool]) {
        return [bool]$Value
    }

    $text = ([string]$Value).Trim().ToLowerInvariant()
    if ($text -in @('1', 'true', 'yes', 'on')) {
        return $true
    }
    if ($text -in @('0', 'false', 'no', 'off')) {
        return $false
    }

    return $Default
}

$PreferLocalConnectorNetwork = Resolve-BridgeBool -Value $PreferLocalConnectorNetwork -Default $false

if ([string]::IsNullOrWhiteSpace($WorkspaceRoot)) {
    $WorkspaceRoot = Split-Path -Parent $PSScriptRoot
}

$workerPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.ps1'
$logPath = Join-Path $WorkspaceRoot 'tools\bridge-worker-autostart.log'
$mutexNameCandidates = @(
    'Global\BioTernBridgeWorkerAutostart',
    'Local\BioTernBridgeWorkerAutostart'
)

if (-not (Test-Path $workerPath)) {
    throw "Bridge worker script not found at $workerPath"
}

$mutex = $null
$acquired = $false
$mutexEnabled = $false

foreach ($mutexName in $mutexNameCandidates) {
    try {
        $mutex = New-Object System.Threading.Mutex($false, $mutexName)
        $mutexEnabled = $true
        break
    } catch {
        $mutex = $null
    }
}

try {
    if ($mutexEnabled -and $mutex -ne $null) {
        try {
            $acquired = $mutex.WaitOne(0)
        } catch {
            $acquired = $false
        }

        if (-not $acquired) {
            # Another autostart instance is already running.
            exit 0
        }
    } else {
        # If mutex setup fails in restricted user context, continue without mutex.
        $acquired = $false
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
    if ($mutexEnabled -and $mutex -ne $null -and $acquired) {
        try {
            $mutex.ReleaseMutex() | Out-Null
        } catch {
            # no-op
        }
    }

    if ($mutex -ne $null) {
        $mutex.Dispose()
    }
}
