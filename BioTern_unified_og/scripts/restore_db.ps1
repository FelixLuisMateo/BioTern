param(
    [Parameter(Mandatory=$true)]
    [string]$SqlFile,
    [string]$MysqlPath = "C:\xampp\mysql\bin\mysql.exe",
    [string]$Database = "biotern_db",
    [string]$User = "root",
    [string]$Password = ""
)

if (!(Test-Path $SqlFile)) {
    Write-Error "SQL file not found: $SqlFile"
    exit 1
}

if ($Password -eq "") {
    Get-Content $SqlFile | & $MysqlPath --user=$User $Database
} else {
    Get-Content $SqlFile | & $MysqlPath --user=$User --password=$Password $Database
}

if ($LASTEXITCODE -eq 0) {
    Write-Output "Restore completed from: $SqlFile"
} else {
    Write-Error "Restore failed"
    exit 1
}

