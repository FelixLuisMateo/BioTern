param(
    [ValidateSet('install', 'start', 'stop', 'restart', 'status', 'uninstall')]
    [string]$Action = 'status',
    [string]$TaskName = 'BioTernBridgeWorker',
    [string]$SiteBaseUrl = '',
    [string]$BridgeToken = '',
    [bool]$PreferLocalConnectorNetwork = $false
)

$ErrorActionPreference = 'Stop'
$workspaceRoot = Split-Path -Parent $PSScriptRoot
$bridgeLogPath = Join-Path $workspaceRoot 'tools\bridge-worker.log'

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
        $logExists = Test-Path $bridgeLogPath
        $lastLogWrite = $null
        $logAgeSeconds = $null

        if ($logExists) {
            $lastLogWrite = (Get-Item $bridgeLogPath).LastWriteTime
            $logAgeSeconds = [int][Math]::Max(0, ((Get-Date) - $lastLogWrite).TotalSeconds)
        }

        $bridgeProcCount = (Get-CimInstance Win32_Process | Where-Object {
            ($_.CommandLine -like '*bridge-worker.ps1*') -or ($_.CommandLine -like '*bridge-worker-autostart.ps1*')
        } | Measure-Object).Count

        $healthStatus = 'OFFLINE'
        if ($task.State -eq 'Running' -or ($bridgeProcCount -gt 0 -and $logAgeSeconds -ne $null -and $logAgeSeconds -le 180)) {
            $healthStatus = 'ONLINE'
        } elseif ($logAgeSeconds -ne $null -and $logAgeSeconds -le 180) {
            $healthStatus = 'LIKELY ONLINE'
        }

        [pscustomobject]@{
            TaskName = $task.TaskName
            State = $task.State
            LastRunTime = $info.LastRunTime
            LastTaskResult = $info.LastTaskResult
            NextRunTime = $info.NextRunTime
            BridgeHealth = $healthStatus
            BridgeProcesses = $bridgeProcCount
            BridgeLogExists = $logExists
            BridgeLogLastWrite = $lastLogWrite
            BridgeLogAgeSeconds = $logAgeSeconds
        } | Format-List
    }
    'uninstall' {
        try { Stop-ScheduledTask -TaskName $TaskName } catch {}
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
        Write-Host "Uninstalled task '$TaskName'."
    }
}
