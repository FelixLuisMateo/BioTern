param(
    [ValidateSet('install', 'start', 'stop', 'restart', 'status', 'uninstall')]
    [string]$Action = 'status',
    [string]$TaskName = 'BioTernBridgeWorker',
    [string]$SiteBaseUrl = '',
    [string]$BridgeToken = '',
    [bool]$PreferLocalConnectorNetwork = $true
)

$ErrorActionPreference = 'Stop'
$workspaceRoot = Split-Path -Parent $PSScriptRoot

function Invoke-Install {
    param($taskName, $baseUrl, $token, $preferLocal)

    if ([string]::IsNullOrWhiteSpace($baseUrl) -or [string]::IsNullOrWhiteSpace($token)) {
        throw 'SiteBaseUrl and BridgeToken are required for install.'
    }

    $installer = Join-Path $workspaceRoot 'tools\install-bridge-worker-task.ps1'
    & $installer -SiteBaseUrl $baseUrl -BridgeToken $token -TaskName $taskName -PreferLocalConnectorNetwork:$preferLocal
}

switch ($Action) {
    'install' {
        Invoke-Install -taskName $TaskName -baseUrl $SiteBaseUrl -token $BridgeToken -preferLocal $PreferLocalConnectorNetwork
    }
    'start' {
        Start-ScheduledTask -TaskName $TaskName
        Write-Host "Started task '$TaskName'."
    }
    'stop' {
        Stop-ScheduledTask -TaskName $TaskName
        Write-Host "Stopped task '$TaskName'."
    }
    'restart' {
        try { Stop-ScheduledTask -TaskName $TaskName } catch {}
        Start-Sleep -Seconds 1
        Start-ScheduledTask -TaskName $TaskName
        Write-Host "Restarted task '$TaskName'."
    }
    'status' {
        $task = Get-ScheduledTask -TaskName $TaskName
        $info = Get-ScheduledTaskInfo -TaskName $TaskName
        [pscustomobject]@{
            TaskName = $task.TaskName
            State = $task.State
            LastRunTime = $info.LastRunTime
            LastTaskResult = $info.LastTaskResult
            NextRunTime = $info.NextRunTime
        } | Format-List
    }
    'uninstall' {
        try { Stop-ScheduledTask -TaskName $TaskName } catch {}
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
        Write-Host "Uninstalled task '$TaskName'."
    }
}
