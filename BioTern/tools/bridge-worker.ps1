[Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidAssignmentToAutomaticVariable', '', Scope = 'Script', Justification = 'False positive from static analysis; script does not assign to automatic variables.')]
param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$WorkspaceRoot = "",
    [int]$DefaultPollSeconds = 30,
    $PreferLocalConnectorNetwork = $true
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

$PreferLocalConnectorNetwork = Resolve-BridgeBool -Value $PreferLocalConnectorNetwork -Default $true

if ([string]::IsNullOrWhiteSpace($WorkspaceRoot)) {
    $WorkspaceRoot = Split-Path -Parent $PSScriptRoot
}

$connectorConfigPath = Join-Path $WorkspaceRoot 'tools\biometric_machine_config.json'
$connectorExePath = Join-Path $WorkspaceRoot 'tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.exe'
$connectorDllPath = Join-Path $WorkspaceRoot 'tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.dll'
$bridgeLogPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.log'
$bridgePendingIngestPath = Join-Path $WorkspaceRoot 'tools\bridge-pending-ingest.json'
$bridgeNodeName = $env:COMPUTERNAME
if ([string]::IsNullOrWhiteSpace($bridgeNodeName)) {
    $bridgeNodeName = [System.Net.Dns]::GetHostName()
}

function Write-BridgeLog {
    param([string]$Message)

    $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $line = "[$stamp] $Message"

    # Never let log I/O failures stop the bridge loop.
    $written = $false
    for ($attempt = 0; $attempt -lt 4; $attempt++) {
        try {
            Add-Content -Path $bridgeLogPath -Value $line -ErrorAction Stop
            $written = $true
            break
        } catch {
            Start-Sleep -Milliseconds 150
        }
    }

    if (-not $written) {
        try {
            [System.IO.File]::AppendAllText($bridgeLogPath, $line + [Environment]::NewLine)
            $written = $true
        } catch {
            # Ignore final failure; console output below is still useful.
        }
    }

    Write-Host $line
}

function Get-BridgeConfigRemote {
    $base = $SiteBaseUrl.TrimEnd('/')
    $tokenQuery = [uri]::EscapeDataString($BridgeToken)
    $candidates = @(
        ('{0}/bridge_profile.php?bridge_token={1}' -f $base, $tokenQuery),
        ('{0}/api/bridge_profile.php?bridge_token={1}' -f $base, $tokenQuery)
    )

    $lastError = $null
    foreach ($uri in $candidates) {
        try {
            return Invoke-RestMethod -Method Get -Uri $uri -TimeoutSec 30
        } catch {
            $lastError = $_
        }
    }

    if ($lastError) {
        throw $lastError
    }

    throw 'Unable to load bridge profile from any known endpoint.'
}

function Get-ApiBaseCandidates {
    param($BridgeConfig)

    $bases = @()

    $siteBase = $SiteBaseUrl.TrimEnd('/')
    if (-not [string]::IsNullOrWhiteSpace($siteBase)) {
        $bases += $siteBase
    }

    $profileBase = ([string]($BridgeConfig.cloud_base_url)).TrimEnd('/')
    if (-not [string]::IsNullOrWhiteSpace($profileBase)) {
        $bases += $profileBase
    }

    return $bases | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique
}

function Update-ConnectorConfig {
    param($BridgeConfig)

    $existingConfig = $null
    if ($PreferLocalConnectorNetwork -and (Test-Path $connectorConfigPath)) {
        try {
            $existingRaw = Get-Content -Path $connectorConfigPath -Raw
            if (-not [string]::IsNullOrWhiteSpace($existingRaw)) {
                $existingConfig = $existingRaw | ConvertFrom-Json -ErrorAction Stop
            }
        } catch {
            $existingConfig = $null
        }
    }

    $ipAddress = [string]($BridgeConfig.ip_address)
    $gateway = [string]($BridgeConfig.gateway)
    $mask = [string]($BridgeConfig.mask)
    $port = [int]($BridgeConfig.port)
    $deviceNumber = [int]($BridgeConfig.device_number)
    $communicationPassword = [string]($BridgeConfig.communication_password)
    $outputPath = [string]($BridgeConfig.output_path)

    if ($PreferLocalConnectorNetwork -and $existingConfig) {
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.ipAddress)) { $ipAddress = [string]$existingConfig.ipAddress }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.gateway)) { $gateway = [string]$existingConfig.gateway }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.mask)) { $mask = [string]$existingConfig.mask }
        if ([int]$existingConfig.port -gt 0) { $port = [int]$existingConfig.port }
        if ([int]$existingConfig.deviceNumber -gt 0) { $deviceNumber = [int]$existingConfig.deviceNumber }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.communicationPassword)) { $communicationPassword = [string]$existingConfig.communicationPassword }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.outputPath)) { $outputPath = [string]$existingConfig.outputPath }
    }

    $cfg = @{
        ipAddress = $ipAddress
        gateway = $gateway
        mask = $mask
        port = $port
        deviceNumber = $deviceNumber
        communicationPassword = $communicationPassword
        outputPath = $outputPath
        syncMode = 'connector_fallback'
        autoImportOnIngest = $false
    }

    $json = $cfg | ConvertTo-Json -Depth 5
    Set-Content -Path $connectorConfigPath -Value $json -Encoding UTF8
}

function Invoke-ConnectorCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,
        [string[]]$Arguments = @()
    )

    if (Test-Path $connectorExePath) {
        $output = & $connectorExePath $connectorConfigPath $Command @Arguments 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Connector command '$Command' failed: $($output -join ' ')"
        }
        return $output
    }

    if (Test-Path $connectorDllPath) {
        $output = & dotnet $connectorDllPath $connectorConfigPath $Command @Arguments 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Connector command '$Command' failed: $($output -join ' ')"
        }
        return $output
    }

    throw "Connector binary not found. Expected at $connectorExePath or $connectorDllPath"
}

function Invoke-ConnectorSync {
    return (Invoke-ConnectorCommand -Command 'sync')
}

function Get-ConnectorUserListRaw {
    $output = Invoke-ConnectorCommand -Command 'get-user-list'
    return ($output -join "`n")
}

function Extract-JsonPayloadFromRaw {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RawText
    )

    if ([string]::IsNullOrWhiteSpace($RawText)) {
        throw 'Connector output is empty.'
    }

    $startArray = $RawText.IndexOf('[')
    $startObject = $RawText.IndexOf('{')
    $start = -1

    if ($startArray -ge 0 -and $startObject -ge 0) {
        $start = [Math]::Min($startArray, $startObject)
    } elseif ($startArray -ge 0) {
        $start = $startArray
    } elseif ($startObject -ge 0) {
        $start = $startObject
    }

    if ($start -lt 0) {
        throw 'Could not locate JSON payload in connector output.'
    }

    $jsonCandidate = $RawText.Substring($start).Trim()
    if ($jsonCandidate.StartsWith('[')) {
        $endIndex = $jsonCandidate.LastIndexOf(']')
        if ($endIndex -ge 0) {
            $jsonCandidate = $jsonCandidate.Substring(0, $endIndex + 1)
        }
    } elseif ($jsonCandidate.StartsWith('{')) {
        $endIndex = $jsonCandidate.LastIndexOf('}')
        if ($endIndex -ge 0) {
            $jsonCandidate = $jsonCandidate.Substring(0, $endIndex + 1)
        }
    }

    $jsonCandidate = $jsonCandidate.Trim()
    try {
        $obj = $jsonCandidate | ConvertFrom-Json -ErrorAction Stop
    } catch {
        throw 'Connector payload is not valid JSON.'
    }

    return ($obj | ConvertTo-Json -Depth 20 -Compress)
}

function Get-UsersPayloadJson {
    $raw = Get-ConnectorUserListRaw
    return (Extract-JsonPayloadFromRaw -RawText $raw)
}

function Invoke-BridgeCommandResultPublish {
    param(
        [Parameter(Mandatory = $true)]
        $BridgeConfig,
        [Parameter(Mandatory = $true)]
        [int]$CommandId,
        [Parameter(Mandatory = $true)]
        [string]$Status,
        [Parameter(Mandatory = $true)]
        [string]$ResultText
    )

    $bases = Get-ApiBaseCandidates -BridgeConfig $BridgeConfig
    if (-not $bases -or $bases.Count -eq 0) {
        throw 'Bridge profile cloud_base_url is empty.'
    }

    $bodyObj = @{
        command_id = $CommandId
        status = $Status
        result_text = $ResultText
    }
    $bodyJson = $bodyObj | ConvertTo-Json -Depth 5 -Compress

    $headers = @{
        'X-BRIDGE-TOKEN' = $BridgeToken
        'X-BRIDGE-NODE' = $bridgeNodeName
    }

    $candidates = @()
    foreach ($base in $bases) {
        $candidates += ('{0}/bridge_commands_complete.php' -f $base)
        $candidates += ('{0}/api/bridge_commands_complete.php' -f $base)
    }

    $lastError = $null
    foreach ($uri in $candidates) {
        try {
            $response = Invoke-RestMethod -Method Post -Uri $uri -Headers $headers -ContentType 'application/json' -Body $bodyJson -TimeoutSec 60
            if ($response.success) {
                return
            }
            throw "Bridge command completion failed: $($response.message)"
        } catch {
            $lastError = $_
        }
    }

    if ($lastError) {
        throw $lastError
    }

    throw 'Unable to publish bridge command result to any known endpoint.'
}

function Get-NextBridgeCommand {
    param($BridgeConfig)

    $bases = Get-ApiBaseCandidates -BridgeConfig $BridgeConfig
    if (-not $bases -or $bases.Count -eq 0) {
        throw 'Bridge profile cloud_base_url is empty.'
    }

    $headers = @{
        'X-BRIDGE-TOKEN' = $BridgeToken
        'X-BRIDGE-NODE' = $bridgeNodeName
    }

    $candidates = @()
    foreach ($base in $bases) {
        $candidates += ('{0}/bridge_commands_claim.php' -f $base)
        $candidates += ('{0}/api/bridge_commands_claim.php' -f $base)
    }

    $lastError = $null
    foreach ($uri in $candidates) {
        try {
            return Invoke-RestMethod -Method Post -Uri $uri -Headers $headers -ContentType 'application/json' -Body '{}' -TimeoutSec 60
        } catch {
            $lastError = $_
        }
    }

    if ($lastError) {
        throw $lastError
    }

    throw 'Unable to claim bridge command from any known endpoint.'
}

function Invoke-BridgeRenameUser {
    param($Payload)

    $userId = [int]($Payload.user_id)
    $newName = [string]($Payload.new_name)
    if ($userId -le 0) {
        throw 'rename_user requires user_id.'
    }
    if ([string]::IsNullOrWhiteSpace($newName)) {
        throw 'rename_user requires new_name.'
    }

    $userRaw = (Invoke-ConnectorCommand -Command 'get-user' -Arguments @([string]$userId)) -join "`n"
    $userJson = Extract-JsonPayloadFromRaw -RawText $userRaw
    $userObj = $userJson | ConvertFrom-Json -ErrorAction Stop

    if ($null -eq $userObj) {
        throw 'Connector returned empty user payload.'
    }

    if ($userObj.PSObject.Properties['name']) {
        $userObj.name = $newName
    }
    if ($userObj.PSObject.Properties['Name']) {
        $userObj.Name = $newName
    }
    if (-not $userObj.PSObject.Properties['name'] -and -not $userObj.PSObject.Properties['Name']) {
        $userObj | Add-Member -NotePropertyName 'name' -NotePropertyValue $newName -Force
    }

    $tmp = [System.IO.Path]::GetTempFileName()
    try {
        $patchedJson = $userObj | ConvertTo-Json -Depth 30
        Set-Content -Path $tmp -Value $patchedJson -Encoding UTF8
        $setOutput = Invoke-ConnectorCommand -Command 'set-user' -Arguments @($tmp)
        return ($setOutput -join "`n")
    } finally {
        if (Test-Path $tmp) {
            Remove-Item -Path $tmp -Force -ErrorAction SilentlyContinue
        }
    }
}

function Invoke-BridgeQueuedCommand {
    param($Command)

    $commandName = [string]($Command.command_name)
    $payloadRaw = [string]($Command.command_payload)
    $payload = @{}
    if (-not [string]::IsNullOrWhiteSpace($payloadRaw)) {
        try {
            $payload = $payloadRaw | ConvertFrom-Json -ErrorAction Stop
        } catch {
            throw "Invalid command payload JSON for command '$commandName'."
        }
    }

    switch ($commandName) {
        'rename_user' {
            return (Invoke-BridgeRenameUser -Payload $payload)
        }
        'delete_user' {
            $userId = [int]($payload.user_id)
            if ($userId -le 0) {
                throw 'delete_user requires user_id.'
            }
            $out = Invoke-ConnectorCommand -Command 'delete-user' -Arguments @([string]$userId)
            return ($out -join "`n")
        }
        'set_time' {
            $timeValue = [string]($payload.time_value)
            if ([string]::IsNullOrWhiteSpace($timeValue)) {
                throw 'set_time requires time_value.'
            }
            $out = Invoke-ConnectorCommand -Command 'set-time' -Arguments @($timeValue)
            return ($out -join "`n")
        }
        'clear_records' {
            $out = Invoke-ConnectorCommand -Command 'clear-records'
            return ($out -join "`n")
        }
        'clear_users' {
            $out = Invoke-ConnectorCommand -Command 'clear-users'
            return ($out -join "`n")
        }
        'clear_admin' {
            $out = Invoke-ConnectorCommand -Command 'clear-admin'
            return ($out -join "`n")
        }
        'restart' {
            $out = Invoke-ConnectorCommand -Command 'restart'
            return ($out -join "`n")
        }
        'save_device_identity' {
            $messages = @()
            $deviceNo = [string]($payload.device_number)
            $password = [string]($payload.communication_password)
            if (-not [string]::IsNullOrWhiteSpace($deviceNo)) {
                $messages += (Invoke-ConnectorCommand -Command 'set-device-no' -Arguments @($deviceNo))
            }
            if (-not [string]::IsNullOrWhiteSpace($password)) {
                $messages += (Invoke-ConnectorCommand -Command 'set-password' -Arguments @($password))
            }
            if ($messages.Count -eq 0) {
                throw 'save_device_identity requires device_number and/or communication_password.'
            }
            return ($messages -join "`n")
        }
        default {
            throw "Unsupported bridge command '$commandName'."
        }
    }
}

function Process-BridgeCommandQueue {
    param($BridgeConfig)

    for ($i = 0; $i -lt 3; $i++) {
        $claim = Get-NextBridgeCommand -BridgeConfig $BridgeConfig
        if (-not $claim.success) {
            throw "Bridge command claim failed: $($claim.message)"
        }

        if ($null -eq $claim.command) {
            break
        }

        $command = $claim.command
        $commandId = [int]($command.id)
        if ($commandId -le 0) {
            continue
        }

        try {
            Write-BridgeLog ("Executing bridge command #{0}: {1}" -f $commandId, [string]$command.command_name)
            $execResult = Invoke-BridgeQueuedCommand -Command $command
            $resultText = [string]$execResult
            if ([string]::IsNullOrWhiteSpace($resultText)) {
                $resultText = 'Command completed successfully.'
            }
            Invoke-BridgeCommandResultPublish -BridgeConfig $BridgeConfig -CommandId $commandId -Status 'succeeded' -ResultText $resultText
            Write-BridgeLog ("Bridge command #{0} completed." -f $commandId)
        } catch {
            $errorText = [string]$_.Exception.Message
            if ([string]::IsNullOrWhiteSpace($errorText)) {
                $errorText = 'Bridge command execution failed.'
            }
            try {
                Invoke-BridgeCommandResultPublish -BridgeConfig $BridgeConfig -CommandId $commandId -Status 'failed' -ResultText $errorText
            } catch {
                Write-BridgeLog ("Bridge command #{0} failed and completion publish also failed: {1}" -f $commandId, [string]$_.Exception.Message)
            }
            Write-BridgeLog ("Bridge command #{0} failed: {1}" -f $commandId, $errorText)
        }
    }
}

function Publish-UserCache {
    param($BridgeConfig)

    $bases = Get-ApiBaseCandidates -BridgeConfig $BridgeConfig
    if (-not $bases -or $bases.Count -eq 0) {
        throw 'Bridge profile cloud_base_url is empty.'
    }

    $usersJson = Get-UsersPayloadJson
    $headers = @{
        'X-BRIDGE-TOKEN' = $BridgeToken
        'X-BRIDGE-NODE' = $bridgeNodeName
    }

    $candidates = @()
    foreach ($base in $bases) {
        $candidates += ('{0}/bridge_users_sync.php' -f $base)
        $candidates += ('{0}/api/bridge_users_sync.php' -f $base)
    }

    $lastError = $null
    foreach ($uri in $candidates) {
        try {
            $response = Invoke-RestMethod -Method Post -Uri $uri -Headers $headers -ContentType 'application/json' -Body $usersJson -TimeoutSec 60
            if ($response.success) {
                Write-BridgeLog "User cache sync success. Users=$($response.users_count)"
                return
            }
            throw "User cache sync failed: $($response.message)"
        } catch {
            $lastError = $_
        }
    }

    if ($lastError) {
        throw $lastError
    }

    throw 'Unable to upload user cache to any known endpoint.'
}

function Get-BridgeEventKey {
    param($Event)

    if ($null -eq $Event) {
        return ''
    }

    $fingerId = [string]($Event.finger_id)
    if ([string]::IsNullOrWhiteSpace($fingerId)) {
        $fingerId = [string]($Event.id)
    }

    $clockType = [string]($Event.type)
    if ([string]::IsNullOrWhiteSpace($clockType)) {
        $clockType = [string]($Event.clock_type)
    }

    $clockTime = [string]($Event.time)
    if ([string]::IsNullOrWhiteSpace($clockTime)) {
        $clockTime = [string]($Event.record_time)
    }

    return ('{0}|{1}|{2}' -f $fingerId, $clockType, $clockTime)
}

function Read-BridgeEventsFromFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if (-not (Test-Path $Path)) {
        return @()
    }

    $raw = (Get-Content -Path $Path -Raw -ErrorAction SilentlyContinue)
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return @()
    }

    try {
        $parsed = $raw | ConvertFrom-Json -ErrorAction Stop
    } catch {
        Write-BridgeLog ("Pending ingest file is invalid JSON and will be ignored: {0}" -f $Path)
        return @()
    }

    if ($parsed -is [System.Collections.IEnumerable] -and -not ($parsed -is [string])) {
        return @($parsed)
    }

    return @($parsed)
}

function Merge-BridgeEventsUnique {
    param(
        [Parameter(Mandatory = $true)]
        [object[]]$First,
        [Parameter(Mandatory = $true)]
        [object[]]$Second
    )

    $merged = @()
    $seen = @{}

    foreach ($event in @($First) + @($Second)) {
        if ($null -eq $event) {
            continue
        }

        $key = Get-BridgeEventKey -Event $event
        if ([string]::IsNullOrWhiteSpace($key)) {
            $merged += $event
            continue
        }

        if ($seen.ContainsKey($key)) {
            continue
        }

        $seen[$key] = $true
        $merged += $event
    }

    return $merged
}

function Save-BridgePendingEvents {
    param(
        [Parameter(Mandatory = $true)]
        [object[]]$Events
    )

    if ($Events.Count -eq 0) {
        if (Test-Path $bridgePendingIngestPath) {
            Remove-Item -Path $bridgePendingIngestPath -Force -ErrorAction SilentlyContinue
        }
        return
    }

    $json = $Events | ConvertTo-Json -Depth 20
    Set-Content -Path $bridgePendingIngestPath -Value $json -Encoding UTF8
}

function Clear-BridgeOutputFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if (Test-Path $Path) {
        Set-Content -Path $Path -Value '[]' -Encoding UTF8
    }
}

function Publish-Ingest {
    param($BridgeConfig)

    $outputPath = [string]($BridgeConfig.output_path)
    if ([string]::IsNullOrWhiteSpace($outputPath)) {
        throw 'Bridge profile output_path is empty.'
    }

    $pendingEvents = Read-BridgeEventsFromFile -Path $bridgePendingIngestPath
    $newEvents = Read-BridgeEventsFromFile -Path $outputPath
    $eventsToUpload = Merge-BridgeEventsUnique -First $pendingEvents -Second $newEvents

    if ($eventsToUpload.Count -eq 0) {
        Write-BridgeLog 'No new F20H logs to upload.'
        return
    }

    # Stage all unsent events to durable local storage before network upload.
    Save-BridgePendingEvents -Events $eventsToUpload
    Clear-BridgeOutputFile -Path $outputPath

    $ingestToken = [string]($BridgeConfig.ingest_api_token)
    if ([string]::IsNullOrWhiteSpace($ingestToken)) {
        throw 'Bridge profile ingest_api_token is empty.'
    }

    $bases = Get-ApiBaseCandidates -BridgeConfig $BridgeConfig
    if (-not $bases -or $bases.Count -eq 0) {
        throw 'Bridge profile cloud_base_url is empty.'
    }
    $base = [string]$bases[0]
    $path = [string]($BridgeConfig.ingest_path)
    if ([string]::IsNullOrWhiteSpace($path)) {
        $path = '/api/f20h_ingest.php'
    }
    if (-not $path.StartsWith('/')) {
        $path = '/' + $path
    }

    $uri = "$base$path"
    $headers = @{
        'X-API-TOKEN' = $ingestToken
        'X-BRIDGE-NODE' = $bridgeNodeName
    }

    $candidates = @($uri)
    foreach ($extraBase in $bases) {
        $extraUri = "{0}{1}" -f $extraBase, $path
        if (-not ($candidates -contains $extraUri)) {
            $candidates += $extraUri
        }
    }

    $payload = $eventsToUpload | ConvertTo-Json -Depth 20 -Compress

    $lastError = $null
    $response = $null
    foreach ($candidateUri in $candidates) {
        try {
            $response = Invoke-RestMethod -Method Post -Uri $candidateUri -Headers $headers -ContentType 'application/json' -Body $payload -TimeoutSec 60
            if ($response.success) {
                break
            }
            throw "Ingest failed: $($response.message)"
        } catch {
            $lastError = $_
            $response = $null
        }
    }

    if ($null -eq $response) {
        if ($lastError) {
            throw $lastError
        }
        throw 'Ingest failed on all candidate endpoints.'
    }

    Save-BridgePendingEvents -Events @()
    Write-BridgeLog "Ingest success. Uploaded=$($eventsToUpload.Count) Received=$($response.received) Inserted=$($response.inserted)"
}

Write-BridgeLog 'Bridge worker started.'

while ($true) {
    try {
        $apiResult = Get-BridgeConfigRemote
        if (-not $apiResult.success) {
            throw "Bridge profile fetch failed: $($apiResult.message)"
        }

        $bridgeConfig = $apiResult.PSObject.Properties['profile'].Value
        if (-not $bridgeConfig.bridge_enabled) {
            Write-BridgeLog 'Bridge disabled in cloud profile. Sleeping.'
            Start-Sleep -Seconds ([Math]::Max(3, $DefaultPollSeconds))
            continue
        }

        Update-ConnectorConfig -BridgeConfig $bridgeConfig
        Process-BridgeCommandQueue -BridgeConfig $bridgeConfig
        $connectorOutput = Invoke-ConnectorSync
        Write-BridgeLog (($connectorOutput -join ' ') -replace '\s+', ' ')
        Publish-UserCache -BridgeConfig $bridgeConfig
        Publish-Ingest -BridgeConfig $bridgeConfig

        $pollSeconds = [int]($bridgeConfig.poll_seconds)
        if ($pollSeconds -lt 3) {
            $pollSeconds = 3
        }
        Start-Sleep -Seconds $pollSeconds
    }
    catch {
        Write-BridgeLog ("Bridge loop error: " + $_.Exception.Message)
        Start-Sleep -Seconds 20
    }
}
