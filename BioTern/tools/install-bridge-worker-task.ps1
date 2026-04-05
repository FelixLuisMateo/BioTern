param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$TaskName = 'BioTernBridgeWorker'
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$scriptPath = Join-Path $workspaceRoot 'tools\bridge-worker.ps1'

if (-not (Test-Path $scriptPath)) {
    throw "Bridge worker script not found at $scriptPath"
}

$pwsh = (Get-Command powershell.exe).Source
$args = "-NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" -SiteBaseUrl `"$SiteBaseUrl`" -BridgeToken `"$BridgeToken`" -WorkspaceRoot `"$workspaceRoot`""

$action = New-ScheduledTaskAction -Execute $pwsh -Argument $args
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
Write-Host "Scheduled task '$TaskName' installed."
Write-Host "Run now with: Start-ScheduledTask -TaskName '$TaskName'"
