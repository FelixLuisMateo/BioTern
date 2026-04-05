param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$TaskName = 'BioTernBridgeWorker',
    [bool]$PreferLocalConnectorNetwork = $true,
    [switch]$ForceUserTask
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$scriptPath = Join-Path $workspaceRoot 'tools\bridge-worker-autostart.ps1'

if (-not (Test-Path $scriptPath)) {
    throw "Bridge worker script not found at $scriptPath"
}

$pwsh = (Get-Command powershell.exe).Source
$taskArguments = "-NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" -SiteBaseUrl `"$SiteBaseUrl`" -BridgeToken `"$BridgeToken`" -WorkspaceRoot `"$workspaceRoot`" -PreferLocalConnectorNetwork:$PreferLocalConnectorNetwork"

$action = New-ScheduledTaskAction -Execute $pwsh -Argument $taskArguments
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

function Install-BridgeTaskElevated {
    param($TaskName, $Action, $Settings)

    $triggerStartup = New-ScheduledTaskTrigger -AtStartup
    $triggerLogon = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest

    Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger @($triggerStartup, $triggerLogon) -Principal $principal -Settings $Settings -Force | Out-Null
}

function Install-BridgeTaskUserOnly {
    param($TaskName, $Action, $Settings)

    $triggerLogon = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

    Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $triggerLogon -Principal $principal -Settings $Settings -Force | Out-Null
}

$installedMode = ''

if ($ForceUserTask) {
    Install-BridgeTaskUserOnly -TaskName $TaskName -Action $action -Settings $settings
    $installedMode = 'user-logon'
} else {
    try {
        Install-BridgeTaskElevated -TaskName $TaskName -Action $action -Settings $settings
        $installedMode = 'elevated-startup-logon'
    } catch {
        $message = [string]$_.Exception.Message
        if ($message -match 'Access is denied|0x80070005') {
            Write-Host "No admin permission for elevated startup task. Falling back to user logon task..."
            Install-BridgeTaskUserOnly -TaskName $TaskName -Action $action -Settings $settings
            $installedMode = 'user-logon'
        } else {
            throw
        }
    }
}

Start-ScheduledTask -TaskName $TaskName

Write-Host "Scheduled task '$TaskName' installed."
Write-Host "Install mode: $installedMode"
Write-Host "Bridge worker task started in background."
Write-Host "Task status:"
Get-ScheduledTask -TaskName $TaskName | Select-Object TaskName, State
