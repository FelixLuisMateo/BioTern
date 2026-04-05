[Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidAssignmentToAutomaticVariable', '', Scope = 'Script', Justification = 'False positive from static analysis; script does not assign to automatic variables.')]
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

$connectorConfigPath = Join-Path $WorkspaceRoot 'tools\biometric_machine_config.json'
$connectorExePath = Join-Path $WorkspaceRoot 'tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.exe'
$connectorDllPath = Join-Path $WorkspaceRoot 'tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.dll'
$bridgeLogPath = Join-Path $WorkspaceRoot 'tools\bridge-worker.log'
$bridgeNodeName = $env:COMPUTERNAME
if ([string]::IsNullOrWhiteSpace($bridgeNodeName)) {
    $bridgeNodeName = [System.Net.Dns]::GetHostName()
}

function Write-BridgeLog {
    param([string]$Message)

    $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $line = "[$stamp] $Message"
    Add-Content -Path $bridgeLogPath -Value $line
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

function Invoke-ConnectorSync {
    if (Test-Path $connectorExePath) {
        $output = & $connectorExePath $connectorConfigPath sync 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Connector sync failed: $($output -join ' ')"
        }
        return $output
    }

    if (Test-Path $connectorDllPath) {
        $output = & dotnet $connectorDllPath $connectorConfigPath sync 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Connector sync failed: $($output -join ' ')"
        }
        return $output
    }

    throw "Connector binary not found. Expected at $connectorExePath or $connectorDllPath"
}

function Get-ConnectorUserListRaw {
    if (Test-Path $connectorExePath) {
        $output = & $connectorExePath $connectorConfigPath get-user-list 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Connector get-user-list failed: $($output -join ' ')"
        }
        return ($output -join "`n")
    }

    if (Test-Path $connectorDllPath) {
        $output = & dotnet $connectorDllPath $connectorConfigPath get-user-list 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Connector get-user-list failed: $($output -join ' ')"
        }
        return ($output -join "`n")
    }

    throw "Connector binary not found. Expected at $connectorExePath or $connectorDllPath"
}

function Get-UsersPayloadJson {
    $raw = Get-ConnectorUserListRaw
    if ([string]::IsNullOrWhiteSpace($raw)) {
        throw 'Connector user list output is empty.'
    }

    $startArray = $raw.IndexOf('[')
    $startObject = $raw.IndexOf('{')
    $start = -1

    if ($startArray -ge 0 -and $startObject -ge 0) {
        $start = [Math]::Min($startArray, $startObject)
    } elseif ($startArray -ge 0) {
        $start = $startArray
    } elseif ($startObject -ge 0) {
        $start = $startObject
    }

    if ($start -lt 0) {
        throw 'Could not locate JSON payload in connector user list output.'
    }

    $jsonCandidate = $raw.Substring($start).Trim()

    # Connector output can append plain-text status lines after JSON (e.g., 'Device disconnected.').
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
        throw 'Connector user list payload is not valid JSON.'
    }

    return ($obj | ConvertTo-Json -Depth 20 -Compress)
}

function Publish-UserCache {
    param($BridgeConfig)

    $base = ([string]($BridgeConfig.cloud_base_url)).TrimEnd('/')
    if ([string]::IsNullOrWhiteSpace($base)) {
        throw 'Bridge profile cloud_base_url is empty.'
    }

    $usersJson = Get-UsersPayloadJson
    $headers = @{
        'X-BRIDGE-TOKEN' = $BridgeToken
        'X-BRIDGE-NODE' = $bridgeNodeName
    }

    $candidates = @(
        ('{0}/bridge_users_sync.php' -f $base),
        ('{0}/api/bridge_users_sync.php' -f $base)
    )

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

function Publish-Ingest {
    param($BridgeConfig)

    $outputPath = [string]($BridgeConfig.output_path)
    if ([string]::IsNullOrWhiteSpace($outputPath)) {
        throw 'Bridge profile output_path is empty.'
    }
    if (-not (Test-Path $outputPath)) {
        Write-BridgeLog "No output file yet: $outputPath"
        return
    }

    $payload = (Get-Content -Path $outputPath -Raw).Trim()
    if ([string]::IsNullOrWhiteSpace($payload) -or $payload -eq '[]') {
        Write-BridgeLog 'No new F20H logs to upload.'
        return
    }

    $ingestToken = [string]($BridgeConfig.ingest_api_token)
    if ([string]::IsNullOrWhiteSpace($ingestToken)) {
        throw 'Bridge profile ingest_api_token is empty.'
    }

    $base = ([string]($BridgeConfig.cloud_base_url)).TrimEnd('/')
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

    $response = Invoke-RestMethod -Method Post -Uri $uri -Headers $headers -ContentType 'application/json' -Body $payload -TimeoutSec 60
    if (-not $response.success) {
        throw "Ingest failed: $($response.message)"
    }

    Set-Content -Path $outputPath -Value '[]' -Encoding UTF8
    Write-BridgeLog "Ingest success. Received=$($response.received) Inserted=$($response.inserted)"
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
            Start-Sleep -Seconds ([Math]::Max(10, $DefaultPollSeconds))
            continue
        }

        Update-ConnectorConfig -BridgeConfig $bridgeConfig
        $connectorOutput = Invoke-ConnectorSync
        Write-BridgeLog (($connectorOutput -join ' ') -replace '\s+', ' ')
        Publish-UserCache -BridgeConfig $bridgeConfig
        Publish-Ingest -BridgeConfig $bridgeConfig

        $pollSeconds = [int]($bridgeConfig.poll_seconds)
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
