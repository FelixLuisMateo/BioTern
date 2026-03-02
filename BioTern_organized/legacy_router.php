<?php
$map = [
  'students.php' => 'management/students.php',
  'students-create.php' => 'management/students-create.php',
  'students-edit.php' => 'management/students-edit.php',
  'students-view.php' => 'management/students-view.php',
  'students-dtr.php' => 'management/students-dtr.php',
  'courses.php' => 'management/courses.php',
  'courses-create.php' => 'management/courses-create.php',
  'courses-edit.php' => 'management/courses-edit.php',
  'departments.php' => 'management/departments.php',
  'departments-create.php' => 'management/departments-create.php',
  'departments-edit.php' => 'management/departments-edit.php',
  'coordinators.php' => 'management/coordinators.php',
  'coordinators-create.php' => 'management/coordinators-create.php',
  'coordinators-edit.php' => 'management/coordinators-edit.php',
  'supervisors.php' => 'management/supervisors.php',
  'supervisors-create.php' => 'management/supervisors-create.php',
  'supervisors-edit.php' => 'management/supervisors-edit.php',
  'ojt.php' => 'management/ojt.php',
  'ojt-create.php' => 'management/ojt-create.php',
  'ojt-edit.php' => 'management/ojt-edit.php',
  'ojt-view.php' => 'management/ojt-view.php',
  'ojt-workflow-board.php' => 'management/ojt-workflow-board.php',

  'attendance.php' => 'pages/attendance.php',
  'analytics.php' => 'pages/analytics.php',
  'homepage.php' => 'pages/homepage.php',
  'attendance-corrections.php' => 'pages/attendance-corrections.php',
  'edit_application.php' => 'pages/edit_application.php',
  'edit_attendance.php' => 'pages/edit_attendance.php',
  'edit_dau_moa.php' => 'pages/edit_dau_moa.php',
  'edit_endorsement.php' => 'pages/edit_endorsement.php',
  'edit_moa.php' => 'pages/edit_moa.php',
  'generate_application.php' => 'pages/generate_application.php',
  'generate_application_letter.php' => 'pages/generate_application_letter.php',
  'generate_dau_moa.php' => 'pages/generate_dau_moa.php',
  'generate_endorsement_letter.php' => 'pages/generate_endorsement_letter.php',
  'generate_moa.php' => 'pages/generate_moa.php',
  'generate_resume.php' => 'pages/generate_resume.php',
  'import_database.php' => 'pages/import_database.php',
  'demo-biometric.php' => 'pages/demo-biometric.php',
  'print_attendance.php' => 'pages/print_attendance.php',

  'document_application.php' => 'documents/document_application.php',
  'document_endorsement.php' => 'documents/document_endorsement.php',
  'document_moa.php' => 'documents/document_moa.php',
  'document_dau_moa.php' => 'documents/document_dau_moa.php',

  'reports-sales.php' => 'reports/reports-sales.php',
  'reports-ojt.php' => 'reports/reports-ojt.php',
  'reports-project.php' => 'reports/reports-project.php',
  'reports-timesheets.php' => 'reports/reports-timesheets.php',
  'reports-attendance-operations.php' => 'reports/reports-attendance-operations.php',

  'settings-general.php' => 'settings/settings-general.php',
  'settings-seo.php' => 'settings/settings-seo.php',
  'settings-tags.php' => 'settings/settings-tags.php',
  'settings-email.php' => 'settings/settings-email.php',
  'settings-tasks.php' => 'settings/settings-tasks.php',
  'settings-ojt.php' => 'settings/settings-ojt.php',
  'settings-support.php' => 'settings/settings-support.php',
  'settings-students.php' => 'settings/settings-students.php',
  'settings-miscellaneous.php' => 'settings/settings-miscellaneous.php',
  'settings-localization.php' => 'settings/settings-localization.php',

  'widgets-lists.php' => 'widgets/widgets-lists.php',
  'widgets-tables.php' => 'widgets/widgets-tables.php',
  'widgets-charts.php' => 'widgets/widgets-charts.php',
  'widgets-statistics.php' => 'widgets/widgets-statistics.php',
  'widgets-miscellaneous.php' => 'widgets/widgets-miscellaneous.php',

  'apps-chat.php' => 'apps/apps-chat.php',
  'apps-email.php' => 'apps/apps-email.php',
  'apps-tasks.php' => 'apps/apps-tasks.php',
  'apps-notes.php' => 'apps/apps-notes.php',
  'apps-storage.php' => 'apps/apps-storage.php',
  'apps-calendar.php' => 'apps/apps-calendar.php',

  'api-biometric-event.php' => 'api/api-biometric-event.php',
  'get_clock_status.php' => 'api/get_clock_status.php',
  'process_attendance.php' => 'api/process_attendance.php',
  'register_fingerprint.php' => 'api/register_fingerprint.php',
  'register_submit.php' => 'api/register_submit.php',

  'help-knowledgebase.php' => 'public/help-knowledgebase.php',
  'create_admin.php' => 'auth/create_admin.php',
  'idnotfound-404.php' => 'auth/idnotfound-404.php',
  'auth-404-minimal.php' => 'auth/auth-404-minimal.php',
  'auth-login-cover.php' => 'auth/auth-login-cover.php',
  'auth-maintenance-cover.php' => 'auth/auth-maintenance-cover.php',
  'auth-register-creative.php' => 'auth/auth-register-creative.php',
  'auth-reset-cover.php' => 'auth/auth-reset-cover.php',
  'auth-resetting-minimal.php' => 'auth/auth-resetting-minimal.php',
  'auth-verify-cover.php' => 'auth/auth-verify-cover.php',
  'diagnose.php' => 'tools/diagnose.php',
  'setup_db.php' => 'tools/setup_db.php',
  'test_data.php' => 'tools/test_data.php',
  'test_db.php' => 'tools/test_db.php',
  'update_remaining_hours.php' => 'tools/update_remaining_hours.php',
];

$file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
if ($file === '' || !isset($map[$file])) {
    http_response_code(404);
    exit('Not found');
}

$target = __DIR__ . '/' . $map[$file];
if (!is_file($target)) {
    http_response_code(404);
    exit('Not found');
}

require $target;
