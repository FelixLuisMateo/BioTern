param(
    [Parameter(Mandatory = $true)]
    [string]$DbHost,

    [Parameter(Mandatory = $true)]
    [int]$Port,

    [Parameter(Mandatory = $true)]
    [string]$User,

    [Parameter(Mandatory = $true)]
    [SecureString]$DbPassword,

    [Parameter(Mandatory = $true)]
    [string]$Database
)

$ErrorActionPreference = 'Stop'

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$schemaFile = Join-Path $baseDir 'biotern_selected_tables_schema.sql'
$mergeDataFile = Join-Path $baseDir 'biotern_selected_tables_data_merge.sql'
$mysqlExe = 'C:\xampp\mysql\bin\mysql.exe'

if (!(Test-Path $mysqlExe)) {
    throw "mysql.exe not found at $mysqlExe"
}
if (!(Test-Path $schemaFile)) {
    throw "Schema file missing: $schemaFile"
}
if (!(Test-Path $mergeDataFile)) {
    throw "Merge data file missing: $mergeDataFile"
}

$bstr = [IntPtr]::Zero
try {
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($DbPassword)
    $plainDbPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
} finally {
    if ($bstr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
}

Write-Host "Applying schema to ${DbHost}:$Port/$Database ..."
$schemaSource = 'source "' + ($schemaFile -replace '\\', '/') + '"'
& $mysqlExe -h $DbHost -P $Port -u $User --password="$plainDbPassword" -D $Database --default-character-set=utf8mb4 --execute=$schemaSource
if ($LASTEXITCODE -ne 0) {
    throw "Schema import failed with mysql exit code $LASTEXITCODE"
}

Write-Host "Applying merge data to ${DbHost}:$Port/$Database ..."
$mergeSource = 'source "' + ($mergeDataFile -replace '\\', '/') + '"'
& $mysqlExe -h $DbHost -P $Port -u $User --password="$plainDbPassword" -D $Database --default-character-set=utf8mb4 --execute=$mergeSource
if ($LASTEXITCODE -ne 0) {
    throw "Merge data import failed with mysql exit code $LASTEXITCODE"
}

Write-Host "Railway sync finished for biometric_raw_logs, ojt_masterlist, and ojt_partner_companies."
