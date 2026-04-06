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

if ([string]::IsNullOrWhiteSpace($WorkspaceRoot)) {
    $WorkspaceRoot = Split-Path -Parent $PSScriptRoot
}

$rootCandidates = @(
    $WorkspaceRoot,
    (Join-Path (Split-Path -Parent $WorkspaceRoot) 'BioTern')
)

$targetScript = $null
$targetWorkspaceRoot = $null

foreach ($candidate in $rootCandidates) {
    $candidateScript = Join-Path $candidate 'tools\bridge-worker.ps1'
    if (Test-Path $candidateScript -and (Resolve-Path $candidateScript).Path -ne (Resolve-Path $PSCommandPath).Path) {
        $targetScript = $candidateScript
        $targetWorkspaceRoot = $candidate
        break
    }
}

if (-not $targetScript) {
    throw 'No usable bridge-worker.ps1 target found (checked BioTern_unified and sibling BioTern).'
}

& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $targetScript -SiteBaseUrl $SiteBaseUrl -BridgeToken $BridgeToken -WorkspaceRoot $targetWorkspaceRoot -DefaultPollSeconds $DefaultPollSeconds -PreferLocalConnectorNetwork $PreferLocalConnectorNetwork
exit $LASTEXITCODE
