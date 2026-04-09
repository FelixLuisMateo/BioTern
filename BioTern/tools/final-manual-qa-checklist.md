# BioTern Final Manual QA Checklist

Date: 2026-04-09
Scope: Final verification of resolved concerns (navigation, profile consistency, analytics, students, applications, attendance, fingerprint, OJT, documents, reports).

## Preconditions

1. Log in as admin.
2. Ensure test data includes:
- at least one approved student with internship
- at least one pending application
- at least one attendance row
- at least one student with uploaded profile picture
3. Open localhost app root.

## 1. Navbar Notifications

1. Open Homepage, Students, Attendance, OJT, Reports, Documents pages.
Expected:
- notification bell visible in header on each page
- unread count is consistent
- opening a notification and marking read updates count

## 2. Profile Image and Details Consistency

1. Open Students list.
2. Open Applications Review.
3. Open Attendance list.
4. Open Resume generator for same student.
Expected:
- same profile picture appears consistently or same fallback avatar behavior
- no broken image icon
- student name and core details are consistent across views

## 3. Internal and External Track Visibility

1. Open Applications Review list and detail row expansion.
2. Open student DTR and OJT pages.
Expected:
- assignment track is visible and labeled clearly
- track values are only Internal or External

## 4. Analytics

1. Open Analytics dashboard.
2. Verify counts against known records.
Expected:
- internship metrics load correctly
- pending student applications are excluded from approved student analytics totals

## 5. Students Page

1. Open Students list.
2. Click Select All checkbox, then uncheck one row.
Expected:
- row checkboxes sync with Select All
- indeterminate state behaves correctly

3. Trigger Print Student List.
Expected:
- print layout renders without floating action UI overlays in print output

## 6. Resume Generation

1. Open Resume for a student with profile image.
2. Open Resume for a student without profile image.
Expected:
- layout is stable and printable
- image student shows profile image
- non-image student shows fallback placeholder

## 7. Evaluation and Certificate Access

1. Open Evaluate page as assigned supervisor or coordinator.
2. Submit evaluation with valid token session.
Expected:
- access gating works correctly
- CSRF-protected submit works
- lock message shows rendered vs required hours and track context when blocked

3. Open Certificate page with same student.
Expected:
- access check matches evaluation role-mapping behavior

## 8. Student DTR

1. Open Student DTR.
2. Enter invalid month manually in query string.
Expected:
- info alert appears and current month is loaded

3. Click Print.
Expected:
- print view renders DTR table cleanly

## 9. Applications Review

1. Open Applications Review.
2. Toggle Actions button and filter controls.
Expected:
- actions panel opens and closes correctly
- filters remain usable and do not glitch

## 10. Attendance

1. Open Attendance list.
Expected:
- student click target goes to Student DTR
- reports cell shows DTR and Print links
- status quick filters available
- old Today/Week/Month quick menu items are absent

2. Click Print link per row.
Expected:
- attendance print page opens correctly

## 11. Fingerprint Mapping

1. Open Fingerprint Mapping page.
2. Check mapped and detected finger IDs.
Expected:
- finger IDs are parsed and shown correctly from available payload variants

## 12. OJT List

1. Open OJT dashboard.
Expected:
- each student resolves to latest internship context
- stage includes Dropped where applicable
- stage filter includes Dropped
- risk score calculation remains stable

## 13. Documents and Excel Sync

1. Open Import Students Excel tool.
2. Import masterlist with matching students.
Expected:
- summary shows internships created or synced
- matching students appear in OJT list with internship context

3. Import student workbook and re-open OJT list.
Expected:
- student account links to internship when masterlist match exists

4. Open Application, Endorsement, MOA, DAU MOA pages.
Expected:
- student search and get actions respect document access policy
- locked students return access-denied message
- eligible students proceed normally

5. Open central document generation endpoint through UI flow.
Expected:
- generation is blocked for ineligible students
- generation proceeds for eligible or unlocked students

## 14. Reports

1. Open Disciplinary Acts report.
Expected:
- records load with filters and status/source behavior

2. Open Manual DTR Input report.
Expected:
- create and delete manual DTR entries work
- records list updates correctly

## Quick Sign-off Template

- Navbar notifications: Pass or Fail
- Profile consistency: Pass or Fail
- Analytics pending exclusion: Pass or Fail
- Students checkbox and print: Pass or Fail
- Resume rendering: Pass or Fail
- Evaluation and certificate access: Pass or Fail
- Student DTR and print: Pass or Fail
- Applications Review actions or filter UX: Pass or Fail
- Attendance routing and reports links: Pass or Fail
- Fingerprint mapping IDs: Pass or Fail
- OJT dashboard and dropped stage: Pass or Fail
- Documents unlock and generation gating: Pass or Fail
- Reports disciplinary and manual DTR: Pass or Fail

Final result:
- Approved for deployment: Yes or No
- Notes:
