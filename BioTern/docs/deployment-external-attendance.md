# External Attendance Deployment Notes

## Railway MySQL

Run these SQL files:

- `database/external_attendance.sql`
- `database/calendar_events.sql`

## Vercel File Uploads

Local disk uploads are not reliable on Vercel. External DTR photo uploads now support Cloudinary when these env vars are set:

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_UPLOAD_PRESET`

Optional signed-upload alternative:

- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`

Optional folder override:

- `CLOUDINARY_EXTERNAL_ATTENDANCE_FOLDER`

If Cloudinary vars are missing, the app falls back to local disk storage in `uploads/external_attendance`, which is suitable for local/XAMPP but not for production on Vercel.

## Student Mobile View

Student-facing external attendance pages optimized for mobile:

- `pages/external-attendance.php`
- `pages/external-attendance-manual.php`
- `assets/css/modules/pages/page-external-attendance-student.css`
