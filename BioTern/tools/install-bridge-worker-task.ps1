param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$TaskName = 'BioTernBridgeWorker',
    $PreferLocalConnectorNetwork = $true,
    [switch]$ForceUserTask
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$scriptPath = Join-Path $workspaceRoot 'tools\bridge-worker-autostart.ps1'

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

$PreferLocalConnectorNetwork = Resolve-BridgeBool -Value $PreferLocalConnectorNetwork -Default $true

if (-not (Test-Path $scriptPath)) {
    throw "Bridge worker script not found at $scriptPath"
}

$pwsh = (Get-Command powershell.exe).Source
$preferLocalNumeric = if ($PreferLocalConnectorNetwork) { 1 } else { 0 }
$taskArguments = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptPath`" -SiteBaseUrl `"$SiteBaseUrl`" -BridgeToken `"$BridgeToken`" -WorkspaceRoot `"$workspaceRoot`" -PreferLocalConnectorNetwork $preferLocalNumeric"

$action = New-ScheduledTaskAction -Execute $pwsh -Argument $taskArguments
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)

function Remove-ExistingTaskIfPresent {
    param($TaskName)

    try {
        $existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
        if ($null -ne $existing) {
            try { Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue } catch {}
            Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction Stop
        }
    } catch {
        # If removal fails, registration below will surface the real failure.
    }
}

function Install-BridgeTaskElevated {
    param($TaskName, $Action, $Settings)

    $triggerStartup = New-ScheduledTaskTrigger -AtStartup
    $triggerLogon = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest

    Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger @($triggerStartup, $triggerLogon) -Principal $principal -Settings $Settings -Force | Out-Null
}

function Install-BridgeTaskSystemStartup {
    param($TaskName, $Action, $Settings)

    $triggerStartup = New-ScheduledTaskTrigger -AtStartup
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

    Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $triggerStartup -Principal $principal -Settings $Settings -Force | Out-Null
}

function Install-BridgeTaskUserOnly {
    param($TaskName, $Action, $Settings)

    $triggerLogon = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

    Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $triggerLogon -Principal $principal -Settings $Settings -Force | Out-Null
}

$installedMode = ''

Remove-ExistingTaskIfPresent -TaskName $TaskName

if ($ForceUserTask) {
    Install-BridgeTaskUserOnly -TaskName $TaskName -Action $action -Settings $settings
    $installedMode = 'user-logon'
} else {
    try {
        Install-BridgeTaskSystemStartup -TaskName $TaskName -Action $action -Settings $settings
        $installedMode = 'system-startup'
    } catch {
        $message = [string]$_.Exception.Message
        if ($message -match 'Access is denied|0x80070005') {
            Write-Host "No admin permission for SYSTEM startup task. Trying elevated startup/logon task..."
            try {
                Install-BridgeTaskElevated -TaskName $TaskName -Action $action -Settings $settings
                $installedMode = 'elevated-startup-logon'
            } catch {
                $message2 = [string]$_.Exception.Message
                if ($message2 -match 'Access is denied|0x80070005') {
                    Write-Host "No admin permission for elevated task. Falling back to user logon task..."
                    Install-BridgeTaskUserOnly -TaskName $TaskName -Action $action -Settings $settings
                    $installedMode = 'user-logon'
                } else {
                    throw
                }
            }
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
