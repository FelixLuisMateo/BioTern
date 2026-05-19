param(
    [string]$Configuration = "Release"
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectPath = Join-Path $projectRoot 'BioTernMachineConnector.csproj'
$x64SdkDll = Join-Path $projectRoot 'DevCtrl.x64.dll'

if (-not (Test-Path -LiteralPath $x64SdkDll -PathType Leaf)) {
    Write-Error "Missing DevCtrl.x64.dll. Get the x64 DevCtrl SDK DLL from the biometric device vendor and place it in $projectRoot first."
}

dotnet build $projectPath -c $Configuration -p:ConnectorArch=x64
