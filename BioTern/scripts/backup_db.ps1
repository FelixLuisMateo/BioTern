param(
    [string]$MysqlDumpPath = "C:\xampp\mysql\bin\mysqldump.exe",
    [string]$Database = "biotern_db",
    [string]$User = "root",
    [string]$Password = "",
    [string]$BackupDir = "C:\xampp\htdocs\BioTern\BioTern\backups"
)

if (!(Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$fileName = "$Database`_$timestamp.sql"
$backupPath = Join-Path $BackupDir $fileName

if ($Password -eq "") {
    & $MysqlDumpPath --user=$User $Database > $backupPath
} else {
    & $MysqlDumpPath --user=$User --password=$Password $Database > $backupPath
}

if ($LASTEXITCODE -eq 0) {
    Write-Output "Backup created: $backupPath"
} else {
    Write-Error "Backup failed"
    exit 1
}

