param(
    [string]$Branch = "main",
    [string]$CommitMessage = "chore: update BioTern system"
)

$ErrorActionPreference = "Stop"

Write-Host "[1/6] Checking git repository..."
$inside = git rev-parse --is-inside-work-tree
if ($inside -ne "true") {
    throw "Current folder is not a git repository."
}

Write-Host "[2/6] Fetching latest changes..."
git fetch origin

Write-Host "[3/6] Switching branch: $Branch"
git checkout $Branch

Write-Host "[4/6] Rebase with remote to keep linear history..."
git pull --rebase origin $Branch

Write-Host "[5/6] Staging all changes..."
git add -A

$hasChanges = (git status --porcelain)
if ([string]::IsNullOrWhiteSpace($hasChanges)) {
    Write-Host "No local changes to commit."
    exit 0
}

Write-Host "[6/6] Committing and pushing..."
git commit -m $CommitMessage
git push origin $Branch

Write-Host "Done. Changes pushed to origin/$Branch"
