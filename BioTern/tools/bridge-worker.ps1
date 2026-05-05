[Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidAssignmentToAutomaticVariable', '', Scope = 'Script', Justification = 'False positive from static analysis; script does not assign to automatic variables.')]
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
$MinPollSeconds = 5

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

$connectorConfigPath = Join-Path $WorkspaceRoot 'tools\biometric_machine_config.json'
$connectorExePath = Join-Path $WorkspaceRoot 'tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.exe'
$connectorDllPath = Join-Path $WorkspaceRoot 'tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.dll'
$bridgeLogPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.log'
$bridgePendingIngestPath = Join-Path $WorkspaceRoot 'tools\bridge-pending-ingest.json'
$bridgeBackfillStatePath = Join-Path $WorkspaceRoot 'tools\bridge-backfill-state.json'
$bridgeProfileCachePath = Join-Path $WorkspaceRoot 'tools\bridge-profile-cache.json'
$bridgeHoldingRootPath = Join-Path $WorkspaceRoot 'tools\bridge-holding'
$bridgeHoldingPendingPath = Join-Path $bridgeHoldingRootPath 'pending'
$bridgeHoldingUploadedPath = Join-Path $bridgeHoldingRootPath 'uploaded'
$bridgeNodeName = $env:COMPUTERNAME
if ([string]::IsNullOrWhiteSpace($bridgeNodeName)) {
    $bridgeNodeName = [System.Net.Dns]::GetHostName()
}

$script:BridgeWorkerMutex = $null
$script:BridgeWorkerMutexAcquired = $false
$script:PreferredNetworkOverride = $null

function Set-PreferredNetworkOverride {
    param(
        [string]$Ip,
        [string]$Gateway,
        [int]$TtlSeconds = 1800
    )

    if ([string]::IsNullOrWhiteSpace($Ip) -or [string]::IsNullOrWhiteSpace($Gateway)) {
        $script:PreferredNetworkOverride = $null
        return
    }

    $script:PreferredNetworkOverride = [pscustomobject]@{
        ip = $Ip
        gateway = $Gateway
        expires_at = (Get-Date).AddSeconds([Math]::Max(60, $TtlSeconds))
    }
}

function Get-PreferredNetworkOverride {
    if ($null -eq $script:PreferredNetworkOverride) {
        return $null
    }

    if ((Get-Date) -gt [datetime]$script:PreferredNetworkOverride.expires_at) {
        $script:PreferredNetworkOverride = $null
        return $null
    }

    return $script:PreferredNetworkOverride
}

function Enter-BridgeWorkerSingleInstance {
    $mutexNameCandidates = @(
        'Global\BioTernBridgeWorkerMainLoop',
        'Local\BioTernBridgeWorkerMainLoop'
    )

    foreach ($mutexName in $mutexNameCandidates) {
        try {
            $script:BridgeWorkerMutex = New-Object System.Threading.Mutex($false, $mutexName)
            $script:BridgeWorkerMutexAcquired = $script:BridgeWorkerMutex.WaitOne(0)
            if ($script:BridgeWorkerMutexAcquired) {
                return $true
            }
        } catch {
            $script:BridgeWorkerMutex = $null
            $script:BridgeWorkerMutexAcquired = $false
        }
    }

    return $false
}

function Exit-BridgeWorkerSingleInstance {
    if ($script:BridgeWorkerMutex -ne $null -and $script:BridgeWorkerMutexAcquired) {
        try {
            $script:BridgeWorkerMutex.ReleaseMutex() | Out-Null
        } catch {
            # no-op
        }
    }

    if ($script:BridgeWorkerMutex -ne $null) {
        try {
            $script:BridgeWorkerMutex.Dispose()
        } catch {
            # no-op
        }
    }

    $script:BridgeWorkerMutex = $null
    $script:BridgeWorkerMutexAcquired = $false
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

    $headers = Get-BridgeRequestHeaders
    $lastError = $null
    foreach ($uri in $candidates) {
        try {
            if ($headers.Count -gt 0) {
                return Invoke-RestMethod -Method Get -Uri $uri -Headers $headers -TimeoutSec 30
            }
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

function Read-BridgeProfileCache {
    if (-not (Test-Path $bridgeProfileCachePath)) {
        return $null
    }

    try {
        $raw = Get-Content -Path $bridgeProfileCachePath -Raw -ErrorAction Stop
        if ([string]::IsNullOrWhiteSpace($raw)) {
            return $null
        }

        return ($raw | ConvertFrom-Json -ErrorAction Stop)
    } catch {
        return $null
    }
}

function Save-BridgeProfileCache {
    param(
        [Parameter(Mandatory = $true)]
        $Profile
    )

    if ($null -eq $Profile) {
        return
    }

    $json = $Profile | ConvertTo-Json -Depth 20
    Set-Content -Path $bridgeProfileCachePath -Value $json -Encoding UTF8
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

function Get-BridgeRequestHeaders {
    $headers = @{}

    if (-not [string]::IsNullOrWhiteSpace($BridgeToken)) {
        $headers['X-BRIDGE-TOKEN'] = $BridgeToken
    }
    if (-not [string]::IsNullOrWhiteSpace($bridgeNodeName)) {
        $headers['X-BRIDGE-NODE'] = $bridgeNodeName
    }

    $bypassToken = $env:BIOTERN_VERCEL_BYPASS_TOKEN
    if ([string]::IsNullOrWhiteSpace($bypassToken)) {
        $bypassToken = $env:VERCEL_PROTECTION_BYPASS
    }
    if (-not [string]::IsNullOrWhiteSpace($bypassToken)) {
        $headers['X-Vercel-Protection-Bypass'] = $bypassToken
    }

    # Some edge protections block requests without a basic user-agent.
    if (-not $headers.ContainsKey('User-Agent')) {
        $headers['User-Agent'] = 'BioTernBridgeWorker/1.0'
    }
    if (-not $headers.ContainsKey('Accept')) {
        $headers['Accept'] = 'application/json'
    }

    return $headers
}

function Read-TextFileWithRetry {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [int]$MaxAttempts = 6,
        [int]$DelayMilliseconds = 150
    )

    if (-not (Test-Path $Path)) {
        return $null
    }

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        try {
            return Get-Content -Path $Path -Raw -ErrorAction Stop
        } catch {
            if ($attempt -ge $MaxAttempts) {
                throw
            }
            Start-Sleep -Milliseconds $DelayMilliseconds
        }
    }

    return $null
}

function Write-TextFileWithRetry {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Content,
        [int]$MaxAttempts = 6,
        [int]$DelayMilliseconds = 150
    )

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        try {
            Set-Content -Path $Path -Value $Content -Encoding UTF8 -ErrorAction Stop
            return
        } catch {
            if ($attempt -ge $MaxAttempts) {
                throw
            }
            Start-Sleep -Milliseconds $DelayMilliseconds
        }
    }
}

function Update-ConnectorConfig {
    param($BridgeConfig)

    $existingConfig = $null
    if (Test-Path $connectorConfigPath) {
        try {
            $existingRaw = Read-TextFileWithRetry -Path $connectorConfigPath
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
    if ([string]::IsNullOrWhiteSpace($outputPath) -or $outputPath -like '/var/task/*') {
        $outputPath = Join-Path $WorkspaceRoot 'attendance.txt'
    }

    if ($PreferLocalConnectorNetwork -and $existingConfig) {
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.ipAddress)) { $ipAddress = [string]$existingConfig.ipAddress }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.gateway)) { $gateway = [string]$existingConfig.gateway }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.mask)) { $mask = [string]$existingConfig.mask }
        if ([int]$existingConfig.port -gt 0) { $port = [int]$existingConfig.port }
        if ([int]$existingConfig.deviceNumber -gt 0) { $deviceNumber = [int]$existingConfig.deviceNumber }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.communicationPassword)) { $communicationPassword = [string]$existingConfig.communicationPassword }
        if (-not [string]::IsNullOrWhiteSpace([string]$existingConfig.outputPath)) { $outputPath = [string]$existingConfig.outputPath }
    }

    $cfg = @{}
    if ($existingConfig) {
        foreach ($prop in $existingConfig.PSObject.Properties) {
            $cfg[$prop.Name] = $prop.Value
        }
    }

    $cfg.ipAddress = $ipAddress
    $cfg.gateway = $gateway
    $cfg.mask = $mask
    $cfg.port = $port
    $cfg.deviceNumber = $deviceNumber
    $cfg.communicationPassword = $communicationPassword
    $cfg.outputPath = $outputPath
    $cfg.syncMode = 'connector_fallback'
    $cfg.autoImportOnIngest = $false

    $json = $cfg | ConvertTo-Json -Depth 5
    Write-TextFileWithRetry -Path $connectorConfigPath -Content $json
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

function Get-BridgeFallbackConfig {
    param($BridgeConfig)

    $ip = ([string]($BridgeConfig.ip_address)).Trim()
    $gateway = ([string]($BridgeConfig.gateway)).Trim()

    if ([string]::IsNullOrWhiteSpace($ip) -or [string]::IsNullOrWhiteSpace($gateway)) {
        return $null
    }

    $fallbackIp = ''
    $fallbackGateway = ''
    $fallbackPreset = ''

    if ($ip -eq '192.168.110.201' -or $gateway -eq '192.168.110.1') {
        $fallbackIp = '192.168.100.201'
        $fallbackGateway = '192.168.100.1'
        $fallbackPreset = 'laptop_router_1'
    } elseif ($ip -eq '192.168.100.201' -or $gateway -eq '192.168.100.1') {
        $fallbackIp = '192.168.110.201'
        $fallbackGateway = '192.168.110.1'
        $fallbackPreset = 'laptop_router_2'
    } else {
        return $null
    }

    $fallback = $BridgeConfig | ConvertTo-Json -Depth 20 | ConvertFrom-Json -ErrorAction SilentlyContinue
    if ($null -eq $fallback) {
        return $null
    }

    if ($fallback.PSObject.Properties['ip_address']) {
        $fallback.ip_address = $fallbackIp
    } else {
        $fallback | Add-Member -NotePropertyName 'ip_address' -NotePropertyValue $fallbackIp -Force
    }

    if ($fallback.PSObject.Properties['gateway']) {
        $fallback.gateway = $fallbackGateway
    } else {
        $fallback | Add-Member -NotePropertyName 'gateway' -NotePropertyValue $fallbackGateway -Force
    }

    # selected_bridge_preset is informational for UI; network IP/gateway drive connector behavior.

    return $fallback
}

function Get-BridgeConfigWithNetwork {
    param(
        $BridgeConfig,
        [Parameter(Mandatory = $true)]
        [string]$Ip,
        [Parameter(Mandatory = $true)]
        [string]$Gateway
    )

    $patched = $BridgeConfig | ConvertTo-Json -Depth 20 | ConvertFrom-Json -ErrorAction SilentlyContinue
    if ($null -eq $patched) {
        return $null
    }

    if ($patched.PSObject.Properties['ip_address']) {
        $patched.ip_address = $Ip
    } else {
        $patched | Add-Member -NotePropertyName 'ip_address' -NotePropertyValue $Ip -Force
    }

    if ($patched.PSObject.Properties['gateway']) {
        $patched.gateway = $Gateway
    } else {
        $patched | Add-Member -NotePropertyName 'gateway' -NotePropertyValue $Gateway -Force
    }

    return $patched
}

function Invoke-ConnectorSyncWithFallback {
    param($BridgeConfig)

    $override = Get-PreferredNetworkOverride
    if ($null -ne $override) {
        $overrideConfig = Get-BridgeConfigWithNetwork -BridgeConfig $BridgeConfig -Ip ([string]$override.ip) -Gateway ([string]$override.gateway)
        if ($null -ne $overrideConfig) {
            Update-ConnectorConfig -BridgeConfig $overrideConfig
            try {
                return (Invoke-ConnectorSync)
            } catch {
                Write-BridgeLog ("Preferred network override failed on {0}; clearing override." -f [string]$override.ip)
                Set-PreferredNetworkOverride -Ip '' -Gateway ''
                Update-ConnectorConfig -BridgeConfig $BridgeConfig
            }
        }
    }

    try {
        return (Invoke-ConnectorSync)
    } catch {
        $primaryError = [string]$_.Exception.Message
        if ($primaryError -notmatch 'Device connection failed|Device disconnected') {
            throw
        }

        $fallbackConfig = Get-BridgeFallbackConfig -BridgeConfig $BridgeConfig
        if ($null -eq $fallbackConfig) {
            throw
        }

        Write-BridgeLog ("Primary connector network failed: {0}. Retrying fallback network {1} via gateway {2}." -f $primaryError, [string]$fallbackConfig.ip_address, [string]$fallbackConfig.gateway)
        Update-ConnectorConfig -BridgeConfig $fallbackConfig

        try {
            $fallbackOutput = Invoke-ConnectorSync
            Write-BridgeLog ("Fallback connector network succeeded on {0}." -f [string]$fallbackConfig.ip_address)
            Set-PreferredNetworkOverride -Ip ([string]$fallbackConfig.ip_address) -Gateway ([string]$fallbackConfig.gateway) -TtlSeconds 1800
            return $fallbackOutput
        } catch {
            $fallbackError = [string]$_.Exception.Message
            throw ("Primary network failed: {0} | Fallback network failed: {1}" -f $primaryError, $fallbackError)
        }
    }
}

function Get-ConnectorUserListRaw {
    $output = Invoke-ConnectorCommand -Command 'get-user-list'
    return ($output -join "`n")
}

function Invoke-BridgeHeartbeat {
    param($BridgeConfig, [string]$Status = 'running')

    $bases = Get-ApiBaseCandidates -BridgeConfig $BridgeConfig
    if (-not $bases -or $bases.Count -eq 0) {
        return $false
    }

    $bodyObj = @{
        node_name = $bridgeNodeName
        status_text = $Status
    }
    $bodyJson = $bodyObj | ConvertTo-Json -Depth 5 -Compress

    $headers = Get-BridgeRequestHeaders

    $candidates = @()
    foreach ($base in $bases) {
        $candidates += ('{0}/bridge_heartbeat.php' -f $base)
        $candidates += ('{0}/api/bridge_heartbeat.php' -f $base)
    }

    foreach ($uri in $candidates) {
        try {
            $response = Invoke-RestMethod -Method Post -Uri $uri -Headers $headers -ContentType 'application/json' -Body $bodyJson -TimeoutSec 30
            if ($response.success) {
                return $true
            }
        } catch {
            # Try the next endpoint candidate.
        }
    }

    return $false
}

function ConvertFrom-BridgeRawJsonPayload {
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
    return (ConvertFrom-BridgeRawJsonPayload -RawText $raw)
}

function Get-ConnectorHistoricalLogRaw {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BeginTime,
        [Parameter(Mandatory = $true)]
        [string]$EndTime
    )

    $output = Invoke-ConnectorCommand -Command 'get-log-range' -Arguments @($BeginTime, $EndTime)
    return ($output -join "`n")
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

    $headers = Get-BridgeRequestHeaders

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

    $headers = Get-BridgeRequestHeaders

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
    $userJson = ConvertFrom-BridgeRawJsonPayload -RawText $userRaw
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

function Invoke-BridgeCommandQueue {
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
    $headers = Get-BridgeRequestHeaders

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
    param($BridgeEvent)

    if ($null -eq $BridgeEvent) {
        return ''
    }

    $fingerId = [string]($BridgeEvent.finger_id)
    if ([string]::IsNullOrWhiteSpace($fingerId)) {
        $fingerId = [string]($BridgeEvent.id)
    }

    $clockType = [string]($BridgeEvent.type)
    if ([string]::IsNullOrWhiteSpace($clockType)) {
        $clockType = [string]($BridgeEvent.clock_type)
    }

    $clockTime = [string]($BridgeEvent.time)
    if ([string]::IsNullOrWhiteSpace($clockTime)) {
        $clockTime = [string]($BridgeEvent.record_time)
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

function Initialize-BridgeHoldingDirectories {
    foreach ($dir in @($bridgeHoldingRootPath, $bridgeHoldingPendingPath, $bridgeHoldingUploadedPath)) {
        if (-not (Test-Path $dir)) {
            New-Item -Path $dir -ItemType Directory -Force | Out-Null
        }
    }
}

function Read-BridgeHoldingPendingEvents {
    Initialize-BridgeHoldingDirectories

    $all = @()
    $files = Get-ChildItem -Path $bridgeHoldingPendingPath -Filter 'holding-*.json' -File -ErrorAction SilentlyContinue |
        Sort-Object Name

    foreach ($file in $files) {
        $rows = @(Read-BridgeEventsFromFile -Path $file.FullName)
        if ($rows.Count -eq 0) {
            continue
        }
        $all = Merge-BridgeEventsUnique -First $all -Second $rows
    }

    return $all
}

function Save-BridgeHoldingPendingBatch {
    param(
        [AllowEmptyCollection()]
        [object[]]$Events = @()
    )

    if ($Events.Count -eq 0) {
        return
    }

    Initialize-BridgeHoldingDirectories

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
    $path = Join-Path $bridgeHoldingPendingPath ("holding-{0}.json" -f $stamp)
    $json = $Events | ConvertTo-Json -Depth 20
    Set-Content -Path $path -Value $json -Encoding UTF8
}

function Move-BridgeHoldingPendingToUploaded {
    Initialize-BridgeHoldingDirectories

    $pendingFiles = Get-ChildItem -Path $bridgeHoldingPendingPath -Filter 'holding-*.json' -File -ErrorAction SilentlyContinue
    foreach ($file in $pendingFiles) {
        $dest = Join-Path $bridgeHoldingUploadedPath $file.Name
        Move-Item -Path $file.FullName -Destination $dest -Force -ErrorAction SilentlyContinue
    }

    # Keep a large but bounded archive of already uploaded holding files.
    $uploaded = Get-ChildItem -Path $bridgeHoldingUploadedPath -Filter 'holding-*.json' -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending
    if ($uploaded.Count -gt 2000) {
        $uploaded | Select-Object -Skip 2000 | Remove-Item -Force -ErrorAction SilentlyContinue
    }
}

function Convert-BridgeDecodedPayloadToEvents {
    param($Decoded)

    if ($null -eq $Decoded) {
        return @()
    }

    if ($Decoded -is [System.Collections.IEnumerable] -and -not ($Decoded -is [string])) {
        return @($Decoded)
    }

    foreach ($propName in @('events', 'logs', 'data', 'rows', 'items', 'list')) {
        if ($Decoded.PSObject -and $Decoded.PSObject.Properties[$propName]) {
            $candidate = $Decoded.PSObject.Properties[$propName].Value
            if ($candidate -is [System.Collections.IEnumerable] -and -not ($candidate -is [string])) {
                return @($candidate)
            }
        }
    }

    return @($Decoded)
}

function Read-BridgeBackfillState {
    if (-not (Test-Path $bridgeBackfillStatePath)) {
        return @{}
    }

    try {
        $raw = Get-Content -Path $bridgeBackfillStatePath -Raw -ErrorAction Stop
        if ([string]::IsNullOrWhiteSpace($raw)) {
            return @{}
        }

        $parsed = $raw | ConvertFrom-Json -ErrorAction Stop
        if ($null -eq $parsed) {
            return @{}
        }

        $state = @{}
        foreach ($prop in $parsed.PSObject.Properties) {
            $state[$prop.Name] = $prop.Value
        }
        return $state
    } catch {
        return @{}
    }
}

function Save-BridgeBackfillState {
    param([hashtable]$State)

    $payload = @{}
    if ($State) {
        foreach ($key in $State.Keys) {
            $payload[$key] = $State[$key]
        }
    }

    $json = $payload | ConvertTo-Json -Depth 8
    Set-Content -Path $bridgeBackfillStatePath -Value $json -Encoding UTF8
}

function Invoke-BridgeHistoricalBackfill {
    param($BridgeConfig)

    $state = Read-BridgeBackfillState

    $scanIntervalMinutes = 10
    if ($BridgeConfig.PSObject -and $BridgeConfig.PSObject.Properties['backfill_scan_interval_minutes']) {
        $scanIntervalMinutes = [int]$BridgeConfig.backfill_scan_interval_minutes
    }
    if ($scanIntervalMinutes -lt 5) {
        $scanIntervalMinutes = 5
    }

    $lastScanText = ''
    if ($state -and $state.ContainsKey('last_scan_utc') -and $null -ne $state['last_scan_utc']) {
        $lastScanText = [string]$state['last_scan_utc']
    }
    if (-not [string]::IsNullOrWhiteSpace($lastScanText)) {
        try {
            $lastScanUtc = [DateTime]::Parse($lastScanText, [System.Globalization.CultureInfo]::InvariantCulture, [System.Globalization.DateTimeStyles]::AssumeUniversal)
            $elapsedMinutes = ((Get-Date).ToUniversalTime() - $lastScanUtc.ToUniversalTime()).TotalMinutes
            if ($elapsedMinutes -lt $scanIntervalMinutes) {
                return
            }
        } catch {
            # Invalid state timestamp should not block backfill.
        }
    }

    $endTime = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    $beginTime = ''

    if ($state -and $state.ContainsKey('last_scan_end_time') -and $null -ne $state['last_scan_end_time']) {
        $lastEndText = [string]$state['last_scan_end_time']
        if (-not [string]::IsNullOrWhiteSpace($lastEndText)) {
            try {
                $lastEnd = [DateTime]::Parse($lastEndText, [System.Globalization.CultureInfo]::InvariantCulture)
                $beginTime = $lastEnd.AddMinutes(-15).ToString('yyyy-MM-dd HH:mm:ss')
            } catch {
                $beginTime = ''
            }
        }
    }

    if ([string]::IsNullOrWhiteSpace($beginTime)) {
        if ($BridgeConfig.PSObject -and $BridgeConfig.PSObject.Properties['backfill_start_time']) {
            $candidateBegin = [string]$BridgeConfig.backfill_start_time
            if (-not [string]::IsNullOrWhiteSpace($candidateBegin)) {
                $beginTime = $candidateBegin.Trim()
            }
        }
    }

    if ([string]::IsNullOrWhiteSpace($beginTime)) {
        $beginTime = (Get-Date).AddDays(-7).ToString('yyyy-MM-dd 00:00:00')
    }

    $rawHistory = Get-ConnectorHistoricalLogRaw -BeginTime $beginTime -EndTime $endTime
    $historyJson = ConvertFrom-BridgeRawJsonPayload -RawText $rawHistory
    $decodedHistory = $historyJson | ConvertFrom-Json -ErrorAction Stop
    $historyEvents = Convert-BridgeDecodedPayloadToEvents -Decoded $decodedHistory

    if ($historyEvents.Count -eq 0) {
        $state['last_scan_utc'] = (Get-Date).ToUniversalTime().ToString('o')
        $state['last_scan_end_time'] = $endTime
        $state['last_scan_count'] = 0
        Save-BridgeBackfillState -State $state
        return
    }

    $pendingEvents = @(Read-BridgeEventsFromFile -Path $bridgePendingIngestPath)
    if ($null -eq $pendingEvents) {
        $pendingEvents = @()
    }

    $beforeCount = $pendingEvents.Count
    $mergedEvents = Merge-BridgeEventsUnique -First $pendingEvents -Second @($historyEvents)

    Save-BridgePendingEvents -Events $mergedEvents

    $state['last_scan_utc'] = (Get-Date).ToUniversalTime().ToString('o')
    $state['last_scan_end_time'] = $endTime
    $state['last_scan_count'] = $historyEvents.Count
    $state['last_scan_added'] = [Math]::Max(0, ($mergedEvents.Count - $beforeCount))
    Save-BridgeBackfillState -State $state

    Write-BridgeLog ("Historical backfill scan complete. SourceEvents={0} AddedToPending={1}" -f $historyEvents.Count, [Math]::Max(0, ($mergedEvents.Count - $beforeCount)))
}

function Merge-BridgeEventsUnique {
    param(
        [object[]]$First = @(),
        [object[]]$Second = @()
    )

    $merged = @()
    $seen = @{}

    foreach ($eventRecord in @($First) + @($Second)) {
        if ($null -eq $eventRecord) {
            continue
        }

        $key = Get-BridgeEventKey -BridgeEvent $eventRecord
        if ([string]::IsNullOrWhiteSpace($key)) {
            $merged += $eventRecord
            continue
        }

        if ($seen.ContainsKey($key)) {
            continue
        }

        $seen[$key] = $true
        $merged += $eventRecord
    }

    return $merged
}

function Save-BridgePendingEvents {
    param(
        [AllowEmptyCollection()]
        [object[]]$Events = @()
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

function Save-BridgeRecoverySnapshot {
    param($BridgeConfig)

    $outputPath = [string]($BridgeConfig.output_path)
    if ([string]::IsNullOrWhiteSpace($outputPath) -or -not (Test-Path $outputPath)) {
        return
    }

    $raw = Get-Content -Path $outputPath -Raw -ErrorAction SilentlyContinue
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return
    }

    $trimmed = $raw.Trim()
    if ($trimmed -eq '[]') {
        return
    }

    $recoveryDir = Join-Path $WorkspaceRoot 'tools\bridge-recovery'
    if (-not (Test-Path $recoveryDir)) {
        New-Item -Path $recoveryDir -ItemType Directory -Force | Out-Null
    }

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
    $snapshotPath = Join-Path $recoveryDir ("attendance-{0}.json" -f $stamp)
    Set-Content -Path $snapshotPath -Value $trimmed -Encoding UTF8

    # Keep recent snapshots only to avoid uncontrolled disk growth.
    $snapshots = Get-ChildItem -Path $recoveryDir -Filter 'attendance-*.json' -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending
    if ($snapshots.Count -gt 200) {
        $snapshots | Select-Object -Skip 200 | Remove-Item -Force -ErrorAction SilentlyContinue
    }
}

function Publish-Ingest {
    param($BridgeConfig)

    $outputPath = [string]($BridgeConfig.output_path)
    if ([string]::IsNullOrWhiteSpace($outputPath)) {
        throw 'Bridge profile output_path is empty.'
    }

    $pendingEvents = @(Read-BridgeEventsFromFile -Path $bridgePendingIngestPath)
    if ($null -eq $pendingEvents) {
        $pendingEvents = @()
    }

    $newEvents = @(Read-BridgeEventsFromFile -Path $outputPath)
    if ($null -eq $newEvents) {
        $newEvents = @()
    }

    if ($newEvents.Count -gt 0) {
        try {
            Save-BridgeHoldingPendingBatch -Events $newEvents
        } catch {
            Write-BridgeLog ("Holding station write warning: " + $_.Exception.Message)
        }
    }

    $holdingPendingEvents = @(Read-BridgeHoldingPendingEvents)
    if ($null -eq $holdingPendingEvents) {
        $holdingPendingEvents = @()
    }

    if ($holdingPendingEvents.Count -gt 0) {
        Write-BridgeLog ("Holding station replay queued events: {0}" -f $holdingPendingEvents.Count)
    }

    $eventsToUpload = Merge-BridgeEventsUnique -First $pendingEvents -Second $holdingPendingEvents
    $eventsToUpload = Merge-BridgeEventsUnique -First $eventsToUpload -Second $newEvents

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
    Move-BridgeHoldingPendingToUploaded
    Write-BridgeLog "Ingest success. Uploaded=$($eventsToUpload.Count) Received=$($response.received) Inserted=$($response.inserted)"
}

if (-not (Enter-BridgeWorkerSingleInstance)) {
    Write-Host 'Another bridge-worker instance is already running. Exiting this duplicate instance.'
    exit 0
}

try {
    Write-BridgeLog 'Bridge worker started.'

    $bridgeConfig = Read-BridgeProfileCache
    $lastProfileRefreshAt = [datetime]::MinValue
    $lastHeartbeatAt = [datetime]::MinValue
    $lastCommandQueueAt = [datetime]::MinValue
    $lastHistoricalBackfillAt = [datetime]::MinValue
    $lastUserCacheAt = [datetime]::MinValue
    $profileRefreshSeconds = 60
    $heartbeatSeconds = 30
    $commandQueueSeconds = 30
    $historicalBackfillSeconds = 300
    $userCacheSeconds = 120

    while ($true) {
        try {
            $now = Get-Date
            if ($null -eq $bridgeConfig -or (($now - $lastProfileRefreshAt).TotalSeconds -ge $profileRefreshSeconds)) {
                $lastProfileRefreshAt = $now
                try {
                    $apiResult = Get-BridgeConfigRemote
                    if (-not $apiResult.success) {
                        throw "Bridge profile fetch failed: $($apiResult.message)"
                    }

                    $bridgeConfig = $apiResult.PSObject.Properties['profile'].Value
                    Save-BridgeProfileCache -Profile $bridgeConfig
                } catch {
                    $cachedProfile = Read-BridgeProfileCache
                    if ($null -eq $cachedProfile) {
                        throw
                    }

                    $bridgeConfig = $cachedProfile
                    Write-BridgeLog ("Bridge profile fetch failed; using cached profile. " + $_.Exception.Message)
                }
            }

            if (-not $bridgeConfig.bridge_enabled) {
                Write-BridgeLog 'Bridge disabled in cloud profile. Sleeping.'
                Start-Sleep -Seconds ([Math]::Max($MinPollSeconds, $DefaultPollSeconds))
                continue
            }

            Update-ConnectorConfig -BridgeConfig $bridgeConfig
            if (($now - $lastHeartbeatAt).TotalSeconds -ge $heartbeatSeconds) {
                if (Invoke-BridgeHeartbeat -BridgeConfig $bridgeConfig) {
                    $lastHeartbeatAt = $now
                }
            }
            if (($now - $lastCommandQueueAt).TotalSeconds -ge $commandQueueSeconds) {
                Invoke-BridgeCommandQueue -BridgeConfig $bridgeConfig
                $lastCommandQueueAt = $now
            }
            $connectorOutput = Invoke-ConnectorSyncWithFallback -BridgeConfig $bridgeConfig
            Write-BridgeLog (($connectorOutput -join ' ') -replace '\s+', ' ')
            Save-BridgeRecoverySnapshot -BridgeConfig $bridgeConfig
            if (($now - $lastHistoricalBackfillAt).TotalSeconds -ge $historicalBackfillSeconds) {
                Invoke-BridgeHistoricalBackfill -BridgeConfig $bridgeConfig
                $lastHistoricalBackfillAt = $now
            }
            Publish-Ingest -BridgeConfig $bridgeConfig
            if (($now - $lastUserCacheAt).TotalSeconds -ge $userCacheSeconds) {
                try {
                    Publish-UserCache -BridgeConfig $bridgeConfig
                    $lastUserCacheAt = $now
                } catch {
                    Write-BridgeLog ("User cache sync skipped: " + $_.Exception.Message)
                }
            }

            $pollSeconds = [int]($bridgeConfig.poll_seconds)
            if ($pollSeconds -lt $MinPollSeconds) {
                $pollSeconds = $MinPollSeconds
            }
            if ($pollSeconds -lt 10) {
                $pollSeconds = 10
            }
            Start-Sleep -Seconds $pollSeconds
        }
        catch {
            Write-BridgeLog ("Bridge loop error: " + $_.Exception.Message)
            Start-Sleep -Seconds 20
        }
    }
}
finally {
    Exit-BridgeWorkerSingleInstance
}
