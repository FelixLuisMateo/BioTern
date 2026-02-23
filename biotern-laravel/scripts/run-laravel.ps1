<#
run-laravel.ps1
Starts Laravel dev server detached (background) and optionally starts `npm run dev`.
Usage:
  .\run-laravel.ps1            # start artisan serve on 127.0.0.1:8000
  .\run-laravel.ps1 -Port 9000 # use custom port
  .\run-laravel.ps1 -StartVite  # also start `npm run dev` if package.json exists
#>
param(
    [string]$Host = '127.0.0.1',
    [int]$Port = 8000,
    [switch]$StartVite
)

# Resolve project root (one level up from scripts folder)
$projectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $projectRoot

Write-Output "Starting Laravel dev server in: $projectRoot"

# Start php artisan serve as a detached process
Start-Process -FilePath 'php' -ArgumentList "artisan","serve","--host=$Host","--port=$Port" -WorkingDirectory $projectRoot -WindowStyle Hidden

if ($StartVite) {
    if (Test-Path (Join-Path $projectRoot 'package.json')) {
        Write-Output "Starting npm dev (vite) in background..."
        # Use npm via cmd to keep it running in a separate window
        Start-Process -FilePath 'cmd.exe' -ArgumentList "/c","npm run dev" -WorkingDirectory $projectRoot
    } else {
        Write-Output "No package.json found; skipping npm run dev."
    }
}

Write-Output "Laravel started at http://$Host`:$Port"
