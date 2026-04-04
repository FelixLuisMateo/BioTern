[Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidAssignmentToAutomaticVariable', '', Scope = 'Script', Justification = 'False positive from static analysis; script does not assign to automatic variables.')]
param(
    [Parameter(Mandatory = $true)]
    [string]$SiteBaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$BridgeToken,
    [string]$WorkspaceRoot = "",
    [int]$DefaultPollSeconds = 30
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
        "$base/bridge_profile.php?bridge_token=$tokenQuery",
        "$base/api/bridge_profile.php?bridge_token=$tokenQuery"
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
    param([hashtable]$BridgeConfig)

    $cfg = @{
        ipAddress = [string]($BridgeConfig.ip_address)
        gateway = [string]($BridgeConfig.gateway)
        mask = [string]($BridgeConfig.mask)
        port = [int]($BridgeConfig.port)
        deviceNumber = [int]($BridgeConfig.device_number)
        communicationPassword = [string]($BridgeConfig.communication_password)
        outputPath = [string]($BridgeConfig.output_path)
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

function Publish-Ingest {
    param([hashtable]$BridgeConfig)

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
