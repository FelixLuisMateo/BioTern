# GitHub Workflow (BioTern Unified)

This project is already connected to GitHub remote:
- `origin`: `https://github.com/FelixLuisMateo/BioTern.git`

## Daily update flow

1. Open PowerShell in project root.
2. Run:

```powershell
Set-Location "c:\xampp\htdocs\BioTern\BioTern"
.\scripts\github-update.ps1 -Branch main -CommitMessage "feat: your update message"
```

## Manual flow (alternative)

```powershell
Set-Location "c:\xampp\htdocs\BioTern\BioTern"
git checkout main
git pull --rebase origin main
git add -A
git commit -m "feat: your update message"
git push origin main
```

## Recommended branch strategy

- `main`: stable branch.
- Feature work: create short-lived branches (e.g. `feature/notifications`).
- Open Pull Request into `main` before major merges.

## Safety rules

- Never commit `.env`.
- Never commit runtime logs/cache files.
- Keep uploads out of git (`uploads/` ignored).
- Use clear commit messages (`feat:`, `fix:`, `chore:`).
