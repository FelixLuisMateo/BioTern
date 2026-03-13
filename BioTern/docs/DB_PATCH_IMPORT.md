# Database Patch Import (phpMyAdmin / CLI)

Use this patch file to align your current DB with the updated system code:

- `database/mig/2026-03-09_schema_alignment_patch.sql`

## Option A: phpMyAdmin (localhost)

1. Open `http://localhost/phpmyadmin`.
2. Select database: `biotern_db`.
3. Go to **Import**.
4. Choose file: `database/mig/2026-03-09_schema_alignment_patch.sql`.
5. Click **Go**.

## Option B: MySQL CLI

```powershell
Set-Location "c:\xampp\htdocs\BioTern\BioTern"
mysql -u root biotern_db < database\mig\2026-03-09_schema_alignment_patch.sql
```

## What this patch changes

- Sets `admin.id` as `AUTO_INCREMENT`.
- Adds `coordinator_courses` mapping table and seeds it from `internships`.
- Adds optional `notifications.type` and `notifications.action_url` if missing.
- Ensures `school_years` table exists and seeds from `internships.school_year`.
- Rebuilds `student_profile_with_internship` view to remove invalid `students.is_active` reference.
