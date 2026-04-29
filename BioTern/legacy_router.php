<?php
$request_uri_path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$script_dir = rtrim(str_replace('\\', '/', (string)dirname($script_name)), '/');
$relative_request_path = '/' . ltrim(rawurldecode($request_uri_path), '/');

if ($script_dir !== '' && $script_dir !== '/' && stripos($relative_request_path, $script_dir . '/') === 0) {
  $relative_request_path = substr($relative_request_path, strlen($script_dir));
  $relative_request_path = '/' . ltrim((string)$relative_request_path, '/');
}

if ($relative_request_path !== '/' && $relative_request_path !== '/legacy_router.php') {
  $static_candidate = realpath(__DIR__ . $relative_request_path);
  $base_dir_real = realpath(__DIR__);
  $is_inside_base = ($static_candidate !== false && $base_dir_real !== false)
    ? str_starts_with(str_replace('\\', '/', $static_candidate), str_replace('\\', '/', $base_dir_real))
    : false;

  if ($is_inside_base && is_file($static_candidate)) {
    $ext = strtolower((string)pathinfo($static_candidate, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
      $content_types = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'map' => 'application/json; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'pdf' => 'application/pdf',
      ];

      if (!headers_sent()) {
        header('Content-Type: ' . ($content_types[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($static_candidate));
        header('Cache-Control: public, max-age=3600');
      }

      readfile($static_candidate);
      exit;
    }
  }
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth-session.php';
biotern_boot_session($conn);

$map = [
  'students.php' => 'management/students.php',
  'applications-review.php' => 'management/applications-review.php',
  'students-create.php' => 'management/students-create.php',
  'students-edit.php' => 'management/students-edit.php',
  'students-view.php' => 'management/students-view.php',
  'students-dtr.php' => 'management/students-dtr.php',
  'students-internal-dtr.php' => 'management/students-dtr.php',
  'students-external-dtr.php' => 'management/students-external-dtr.php',
  'courses.php' => 'management/courses.php',
  'courses-create.php' => 'management/courses-create.php',
  'courses-edit.php' => 'management/courses-edit.php',
  'departments.php' => 'management/departments.php',
  'departments-create.php' => 'management/departments-create.php',
  'departments-edit.php' => 'management/departments-edit.php',
  'sections.php' => 'management/sections.php',
  'sections-create.php' => 'management/sections-create.php',
  'sections-edit.php' => 'management/sections-edit.php',
  'companies.php' => 'management/companies.php',
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
  'edit_application.php' => 'documents/document_application.php',
  'edit_attendance.php' => 'pages/edit_attendance.php',
  'edit_dau_moa.php' => 'documents/document_dau_moa.php',
  'edit_endorsement.php' => 'documents/document_endorsement.php',
  'edit_moa.php' => 'documents/document_moa.php',
  'generate_application.php' => 'documents/document_application.php',
  'generate_application_letter.php' => 'documents/document_application.php',
  'generate_dau_moa.php' => 'documents/document_dau_moa.php',
  'generate_endorsement_letter.php' => 'documents/document_endorsement.php',
  'generate_moa.php' => 'documents/document_moa.php',
  'generate_resume.php' => 'pages/generate_resume.php',
  'import_database.php' => 'pages/import_database.php',
  'external-biometric.php' => 'pages/external-biometric.php',
  'fingerprint_mapping.php' => 'pages/fingerprint_mapping.php',
  'ojt-internal-list.php' => 'pages/ojt-internal-list.php',
  'ojt-external-list.php' => 'pages/ojt-external-list.php',
  'biometric-machine.php' => 'pages/biometric-machine.php',
  'bridge-setup-manual.php' => 'pages/bridge-setup-manual.php',
  'biometric_machine_sync.php' => 'tools/biometric_machine_sync.php',
  'import-sql.php' => 'tools/import-sql.php',
  'import-students-excel.php' => 'tools/import-students-excel.php',
  'import-ojt-internal.php' => 'tools/import-ojt-internal.php',
  'import-ojt-external.php' => 'tools/import-ojt-external.php',
  'download-internal-template.php' => 'tools/download-internal-template.php',
  'download-external-template.php' => 'tools/download-external-template.php',
  'print_attendance.php' => 'pages/print_attendance.php',
  'external-attendance.php' => 'pages/external-attendance.php',
  'external-attendance-manual.php' => 'pages/external-attendance-manual.php',
  'student-external-dtr.php' => 'pages/student-external-dtr.php',
  'student-manual-dtr.php' => 'pages/student-manual-dtr.php',

  'document_application.php' => 'documents/document_application.php',
  'document_endorsement.php' => 'documents/document_endorsement.php',
  'document_moa.php' => 'documents/document_moa.php',
  'document_dau_moa.php' => 'documents/document_dau_moa.php',
  'document_resume.php' => 'documents/document_resume.php',
  'document_dtr.php' => 'documents/document_dtr.php',
  'document_waiver.php' => 'documents/document_waiver.php',
  'student-profile.php' => 'pages/student-profile.php',
  'student-dtr.php' => 'pages/student-dtr.php',
  'student-internal-dtr.php' => 'pages/student-dtr.php',
  'student-documents.php' => 'pages/student-documents.php',
  'student-application-letter.php' => 'pages/student-application-letter.php',
  'student-endorsement-letter.php' => 'pages/student-endorsement-letter.php',
  'student-moa.php' => 'pages/student-moa.php',
  'student-dau-moa.php' => 'pages/student-dau-moa.php',

  'reports-ojt.php' => 'reports/reports-ojt.php',
  'reports-project.php' => 'reports/reports-project.php',
  'reports-timesheets.php' => 'reports/reports-timesheets.php',
  'reports-attendance-operations.php' => 'reports/reports-attendance-operations.php',
  'reports-attendance-exceptions.php' => 'reports/reports-attendance-exceptions.php',
  'reports-login-logs.php' => 'reports/reports-login-logs.php',
  'reports-chat-logs.php' => 'reports/reports-chat-logs.php',
  'reports-chat-reports.php' => 'reports/reports-chat-reports.php',

  'settings-general.php' => 'settings/settings-general.php',
  'settings-email.php' => 'settings/settings-email.php',
  'settings-ojt.php' => 'settings/settings-ojt.php',
  'settings-students.php' => 'settings/settings-students.php',
  'settings-support.php' => 'settings/settings-support.php',
  'account-settings.php' => 'settings/account-settings.php',
  'notifications.php' => 'pages/notifications.php',
  'profile-details.php' => 'pages/profile-details.php',
  'activity-feed.php' => 'pages/activity-feed.php',
  'theme-customizer.php' => 'pages/theme-customizer.php',


  'apps-chat.php' => 'apps/apps-chat.php',
  'apps-email.php' => 'apps/apps-email.php',
  'apps-notes.php' => 'apps/apps-notes.php',
  'apps-storage.php' => 'apps/apps-storage.php',
  'apps-calendar.php' => 'apps/apps-calendar.php',
  'notes.php' => 'api/notes.php',
  'storage_files.php' => 'api/storage_files.php',
  'calendar_events.php' => 'api/calendar_events.php',
  'avatar-image.php' => 'includes/avatar-image.php',
  'chat-media.php' => 'includes/chat-media.php',

  'api-biometric-event.php' => 'api/api-biometric-event.php',
  'f20h_ingest.php' => 'api/f20h_ingest.php',
  'bridge_profile.php' => 'api/bridge_profile.php',
  'bridge_health.php' => 'api/bridge_health.php',
  'bridge_users_sync.php' => 'api/bridge_users_sync.php',
  'bridge_commands_claim.php' => 'api/bridge_commands_claim.php',
  'bridge_commands_complete.php' => 'api/bridge_commands_complete.php',
  'get_clock_status.php' => 'api/get_clock_status.php',
  'process_attendance.php' => 'api/process_attendance.php',
  'register_fingerprint.php' => 'api/register_fingerprint.php',
  'register_submit.php' => 'api/register_submit.php',
  'notifications-actions.php' => 'api/notifications-actions.php',

  'create_admin.php' => 'auth/create_admin.php',
  'users.php' => 'auth/users.php',
  'idnotfound-404.php' => 'auth/idnotfound-404.php',
  'auth-404-minimal.php' => 'auth/auth-404-minimal.php',
  'auth-login-cover.php' => 'auth/auth-login.php',
  'auth-login.php' => 'auth/auth-login.php',
  'auth-maintenance-cover.php' => 'auth/auth-maintenance-cover.php',
  'auth-register.php' => 'auth/auth-register.php',
  'auth-register-verify.php' => 'auth/auth-register-verify.php',
  'auth-two-factor.php' => 'auth/auth-two-factor.php',
  'auth-reset-cover.php' => 'auth/auth-reset-cover.php',
  'auth-resetting-minimal.php' => 'auth/auth-resetting-minimal.php',
  'auth-verify-cover.php' => 'auth/auth-verify-cover.php',
];

$file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
$request_path_for_route = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
if ($file === 'theme-customizer.php' && preg_match('#/api/theme-customizer\.php$#', $request_path_for_route) === 1) {
  $map[$file] = 'api/theme-customizer.php';
}
if ($file === '' || !isset($map[$file])) {
    http_response_code(404);
    exit('Not found');
}

$request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
if ($request_uri !== '' && stripos($request_uri, 'legacy_router.php') !== false) {
  $query = $_GET;
  unset($query['file']);
  $destination = $file;
  if (!empty($query)) {
    $destination .= '?' . http_build_query($query);
  }
  header('Location: ' . $destination, true, 302);
  exit;
}

$target = __DIR__ . '/' . $map[$file];
if (!is_file($target)) {
    http_response_code(404);
    exit('Not found');
}

$target_dir = dirname($target);
$router_include_paths = [
  __DIR__,
  __DIR__ . '/includes',
  __DIR__ . '/config',
  $target_dir,
];

$router_existing_include_path = get_include_path();
if ($router_existing_include_path !== false && $router_existing_include_path !== '') {
  $router_include_paths[] = $router_existing_include_path;
}

$router_normalized_paths = [];
foreach ($router_include_paths as $include_path_entry) {
  if (!is_string($include_path_entry) || $include_path_entry === '') {
    continue;
  }

  if (is_dir($include_path_entry)) {
    $real_entry = realpath($include_path_entry);
    $router_normalized_paths[] = $real_entry !== false ? $real_entry : $include_path_entry;
    continue;
  }

  $router_normalized_paths[] = $include_path_entry;
}

set_include_path(implode(PATH_SEPARATOR, array_values(array_unique($router_normalized_paths))));
@chdir(__DIR__);

// Global auth guard so all routed pages are tied to a logged-in account.
$public_files = [
  'index.php',
  'f20h_ingest.php',
  'bridge_profile.php',
  'bridge_health.php',
  'bridge_users_sync.php',
  'bridge_commands_claim.php',
  'bridge_commands_complete.php',
  'auth-login.php',
  'auth-login-cover.php',
  'auth-register.php',
  'auth-register-verify.php',
  'auth-two-factor.php',
  'auth-reset-cover.php',
  'auth-resetting-minimal.php',
  'auth-verify-cover.php',
  'auth-404-minimal.php',
  'idnotfound-404.php',
  'create_admin.php',
];
$is_public = in_array($file, $public_files, true);
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$is_logged_in = ($current_user_id > 0);
$is_logout_request = (($file === 'auth-login.php' || $file === 'auth-login-cover.php') && isset($_GET['logout']) && (string)$_GET['logout'] === '1');

if (!$is_public && !$is_logged_in) {
  $next = urlencode($file);
  header('Location: auth-login.php?next=' . $next);
  exit;
}

// If already logged in, keep login page blocked.
if ($is_logged_in && ($file === 'auth-login.php' || $file === 'auth-login-cover.php') && !$is_logout_request) {
  header('Location: homepage.php');
  exit;
}

// Allow account registration page only for privileged logged-in users.
if ($is_logged_in && $file === 'auth-register.php') {
  $current_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
  if (!in_array($current_role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
  }
}

// Refresh session fields from DB to keep account info consistent across all pages.
if ($is_logged_in) {
  $db = null;
  try {
    $db = new mysqli(
      defined('DB_HOST') ? DB_HOST : '127.0.0.1',
      defined('DB_USER') ? DB_USER : 'root',
      defined('DB_PASS') ? DB_PASS : '',
      defined('DB_NAME') ? DB_NAME : 'biotern_db',
      defined('DB_PORT') ? (int)DB_PORT : 3306
    );
  } catch (mysqli_sql_exception $e) {
    $db = null;
  }

  if (!$db->connect_errno) {
    $stmt = $db->prepare("SELECT id, name, username, email, role, is_active, profile_picture FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('i', $current_user_id);
      $stmt->execute();
      $user = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
          $params = session_get_cookie_params();
          setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: auth-login.php');
        exit;
      }

      $_SESSION['user_id'] = (int)$user['id'];
      $_SESSION['name'] = (string)($user['name'] ?? '');
      $_SESSION['username'] = (string)($user['username'] ?? '');
      $_SESSION['email'] = (string)($user['email'] ?? '');
      $_SESSION['role'] = (string)($user['role'] ?? '');
      $_SESSION['profile_picture'] = (string)($user['profile_picture'] ?? '');
      $_SESSION['logged_in'] = true;
    }
    $db->close();
  }
}

// Role-based route access control to keep sections tied to account type.
if ($is_logged_in) {
  $current_role = strtolower(trim((string)($_SESSION['role'] ?? '')));

  $internship_files = [
    'students.php', 'students-create.php', 'students-edit.php', 'students-view.php', 'students-dtr.php',
    'applications-review.php',
    'attendance.php', 'attendance-corrections.php', 'edit_attendance.php', 'print_attendance.php',
    'external-attendance.php', 'external-attendance-manual.php',
    'external-biometric.php', 'fingerprint_mapping.php', 'biometric-machine.php', 'bridge-setup-manual.php', 'biometric_machine_sync.php',
    'ojt-internal-list.php', 'ojt-external-list.php',
    'ojt.php', 'ojt-create.php', 'ojt-edit.php', 'ojt-view.php', 'ojt-workflow-board.php',
    'reports-ojt.php', 'reports-project.php', 'reports-timesheets.php', 'reports-attendance-operations.php', 'reports-attendance-exceptions.php',
    'reports-login-logs.php', 'reports-chat-logs.php', 'reports-chat-reports.php',
  ];
  $academic_files = [
    'courses.php', 'courses-create.php', 'courses-edit.php',
    'departments.php', 'departments-create.php', 'departments-edit.php',
    'sections.php', 'sections-create.php', 'sections-edit.php',
    'coordinators.php', 'coordinators-create.php', 'coordinators-edit.php',
    'supervisors.php', 'supervisors-create.php', 'supervisors-edit.php',
  ];
  $workspace_files = [
    'apps-chat.php', 'apps-email.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
  ];
  $student_workspace_files = [
    'apps-chat.php', 'apps-email.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
  ];
  $system_files = [
    'auth-register.php', 'users.php', 'create_admin.php',
    'import-sql.php', 'import-students-excel.php', 'import-ojt-internal.php', 'import-ojt-external.php',
  ];
  $settings_files = [
    'settings-general.php', 'settings-email.php',
    'settings-ojt.php', 'settings-students.php', 'settings-support.php',
  ];

  $deny = false;
  if (in_array($file, $internship_files, true) && !in_array($current_role, ['admin', 'coordinator', 'supervisor'], true)) {
    $student_internship_allowed_files = [
      'external-biometric.php',
      'student-external-dtr.php',
      'student-manual-dtr.php',
      'student-dtr.php',
      'student-internal-dtr.php',
      'student-profile.php',
      'student-documents.php',
    ];
    if (!($current_role === 'student' && in_array($file, $student_internship_allowed_files, true))) {
      $deny = true;
    }
  }
  if (in_array($file, $academic_files, true) && !in_array($current_role, ['admin', 'coordinator'], true)) {
    $deny = true;
  }
  if (in_array($file, $workspace_files, true)) {
    $workspace_allowed_roles = ['admin', 'coordinator'];
    $student_workspace_allowed = ($current_role === 'student' && in_array($file, $student_workspace_files, true));
    if (!in_array($current_role, $workspace_allowed_roles, true) && !$student_workspace_allowed) {
      $deny = true;
    }
  }
  if (in_array($file, $system_files, true) && $current_role !== 'admin') {
    $deny = true;
  }
  if (in_array($file, $settings_files, true) && !in_array($current_role, ['admin', 'coordinator'], true)) {
    $deny = true;
  }

  if ($deny) {
    http_response_code(403);
    header('Location: homepage.php');
    exit;
  }
}

if ($file === 'homepage.php') {
  ob_start();
  require $target;
  $html = ob_get_clean();
  $guard = <<<'HTML'
<script>
  (function () {
    function collapseSidebarMenus() {
      if (!document.documentElement.classList.contains('minimenu')) return;
      document.querySelectorAll('.nxl-navigation .nxl-item.nxl-hasmenu.open, .nxl-navigation .nxl-item.nxl-hasmenu.nxl-trigger').forEach(function (item) {
        item.classList.remove('open', 'nxl-trigger');
      });
    }

    function runAfterToggle() {
      collapseSidebarMenus();
      setTimeout(collapseSidebarMenus, 80);
      setTimeout(collapseSidebarMenus, 220);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', collapseSidebarMenus);
    } else {
      collapseSidebarMenus();
    }

    ['menu-mini-button', 'menu-expend-button', 'mobile-collapse'].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener('click', runAfterToggle);
    });

    var nav = document.querySelector('.nxl-navigation');
    if (window.MutationObserver && nav) {
      var observer = new MutationObserver(function () {
        if (document.documentElement.classList.contains('minimenu')) {
          collapseSidebarMenus();
        }
      });
      observer.observe(nav, { subtree: true, attributes: true, attributeFilter: ['class'] });
    }
  })();
</script>
HTML;
  if (stripos($html, '</body>') !== false) {
    $html = preg_replace('/<\/body>/i', $guard . "\n</body>", $html, 1);
  } else {
    $html .= "\n" . $guard;
  }
  echo $html;
  exit;
}

require $target;


