param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$TaskName = 'BioTernBridgeWorker',
    [bool]$PreferLocalConnectorNetwork = $true
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$scriptPath = Join-Path $workspaceRoot 'tools\bridge-worker-autostart.ps1'

if (-not (Test-Path $scriptPath)) {
    throw "Bridge worker script not found at $scriptPath"
}

$pwsh = (Get-Command powershell.exe).Source
$args = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptPath`" -SiteBaseUrl `"$SiteBaseUrl`" -BridgeToken `"$BridgeToken`" -WorkspaceRoot `"$workspaceRoot`" -PreferLocalConnectorNetwork:$PreferLocalConnectorNetwork"

$action = New-ScheduledTaskAction -Execute $pwsh -Argument $args
$triggerStartup = New-ScheduledTaskTrigger -AtStartup
$triggerLogon = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger @($triggerStartup, $triggerLogon) -Principal $principal -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $TaskName

Write-Host "Scheduled task '$TaskName' installed."
Write-Host "Bridge worker task started in background."
Write-Host "Task status:"
Get-ScheduledTask -TaskName $TaskName | Select-Object TaskName, State
