<?php
// Centralized navigation include (grouped/relabeled).
require_once __DIR__ . '/auth-session.php';
require_once dirname(__DIR__) . '/config/db.php';
biotern_boot_session();
$nav_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'guest')));
$nav_is_admin = ($nav_role === 'admin');
$nav_is_coordinator = ($nav_role === 'coordinator');
$nav_is_supervisor = ($nav_role === 'supervisor');
$nav_is_student = ($nav_role === 'student');
$nav_student_track = 'internal';
$nav_student_id = 0;
$nav_student_has_external_access = false;

if ($nav_is_student && isset($conn) && $conn instanceof mysqli) {
    $nav_student_user_id = (int)($_SESSION['user_id'] ?? 0);
    $nav_session_track = strtolower(trim((string)($_SESSION['assignment_track'] ?? '')));
    if (in_array($nav_session_track, ['internal', 'external'], true)) {
        $nav_student_track = $nav_session_track;
    }

    if ($nav_student_user_id > 0) {
        $nav_track_stmt = $conn->prepare('
            SELECT id, assignment_track
            FROM students
            WHERE user_id = ? OR id = ?
            ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END, id DESC
            LIMIT 1
        ');
        if ($nav_track_stmt) {
            $nav_track_stmt->bind_param('iii', $nav_student_user_id, $nav_student_user_id, $nav_student_user_id);
            $nav_track_stmt->execute();
            $nav_track_row = $nav_track_stmt->get_result()->fetch_assoc() ?: null;
            $nav_track_stmt->close();

            $nav_student_id = (int)($nav_track_row['id'] ?? 0);
            $nav_db_track = strtolower(trim((string)($nav_track_row['assignment_track'] ?? '')));
            if (in_array($nav_db_track, ['internal', 'external'], true)) {
                $nav_student_track = $nav_db_track;
            }
        }

        if (!in_array($nav_student_track, ['internal', 'external'], true) || $nav_student_track === 'internal') {
            $nav_user_stmt = $conn->prepare('SELECT username, email, name FROM users WHERE id = ? LIMIT 1');
            if ($nav_user_stmt) {
                $nav_user_stmt->bind_param('i', $nav_student_user_id);
                $nav_user_stmt->execute();
                $nav_user_row = $nav_user_stmt->get_result()->fetch_assoc() ?: null;
                $nav_user_stmt->close();
                if ($nav_user_row) {
                    $nav_username = trim((string)($nav_user_row['username'] ?? ''));
                    $nav_email = trim((string)($nav_user_row['email'] ?? ''));
                    $nav_name = trim((string)($nav_user_row['name'] ?? ''));
                    if ($nav_username !== '' || $nav_email !== '' || $nav_name !== '') {
                        $nav_fallback_stmt = $conn->prepare("
                            SELECT id, assignment_track
                            FROM students
                            WHERE ((? <> '' AND LOWER(COALESCE(student_id, '')) = LOWER(?))
                                OR (? <> '' AND LOWER(COALESCE(email, '')) = LOWER(?))
                                OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))) = LOWER(?)))
                            ORDER BY
                                CASE
                                    WHEN (? <> '' AND LOWER(COALESCE(student_id, '')) = LOWER(?)) THEN 0
                                    WHEN (? <> '' AND LOWER(COALESCE(email, '')) = LOWER(?)) THEN 1
                                    WHEN (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))) = LOWER(?)) THEN 2
                                    ELSE 3
                                END,
                                id DESC
                            LIMIT 1
                        ");
                        if ($nav_fallback_stmt) {
                            $nav_fallback_stmt->bind_param(
                                'ssssssssssss',
                                $nav_username,
                                $nav_username,
                                $nav_email,
                                $nav_email,
                                $nav_name,
                                $nav_name,
                                $nav_username,
                                $nav_username,
                                $nav_email,
                                $nav_email,
                                $nav_name,
                                $nav_name
                            );
                            $nav_fallback_stmt->execute();
                            $nav_fallback_row = $nav_fallback_stmt->get_result()->fetch_assoc() ?: null;
                            $nav_fallback_stmt->close();
                            $nav_student_id = (int)($nav_fallback_row['id'] ?? $nav_student_id);
                            $nav_fallback_track = strtolower(trim((string)($nav_fallback_row['assignment_track'] ?? '')));
                            if (in_array($nav_fallback_track, ['internal', 'external'], true)) {
                                $nav_student_track = $nav_fallback_track;
                            }
                        }
                    }
                }
            }
        }
    }
}

$nav_can_internship = ($nav_is_admin || $nav_is_coordinator || $nav_is_supervisor);
$nav_can_academic = $nav_is_admin;
$nav_can_workspace = ($nav_is_admin || $nav_is_coordinator || $nav_is_supervisor || $nav_is_student);
$nav_can_system = $nav_is_admin;
$nav_can_user_accounts = $nav_is_admin;

$nav_current_file = '';
if (isset($_GET['file'])) {
    $nav_current_file = strtolower(basename((string)$_GET['file']));
}
if ($nav_current_file === '') {
    $nav_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $nav_current_file = strtolower(basename((string)$nav_request_path));
}
$nav_student_has_external_access = ($nav_student_track === 'external') || ($nav_is_student && $nav_current_file === 'external-biometric.php');
if ($nav_is_student && !$nav_student_has_external_access && $nav_student_id > 0 && isset($conn) && $conn instanceof mysqli) {
    $nav_external_stmt = $conn->prepare('SELECT 1 FROM external_attendance WHERE student_id = ? LIMIT 1');
    if ($nav_external_stmt) {
        $nav_external_stmt->bind_param('i', $nav_student_id);
        $nav_external_stmt->execute();
        $nav_student_has_external_access = (bool)($nav_external_stmt->get_result()->fetch_assoc() ?: null);
        $nav_external_stmt->close();
    }
}

if (!function_exists('biotern_nav_route_key')) {
    function biotern_nav_route_key($href) {
        $href = trim((string)$href);
        if ($href === '' || stripos($href, 'javascript:') === 0 || $href === '#') {
            return '';
        }
        $parts = parse_url($href);
        $path = (string)($parts['path'] ?? '');
        $key = strtolower(basename($path));
        if ($key !== '') {
            return $key;
        }
        if (isset($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            if (!empty($query['file'])) {
                return strtolower(basename((string)$query['file']));
            }
        }
        return '';
    }
}

if (!function_exists('biotern_nav_is_active')) {
    function biotern_nav_is_active($href, $current) {
        $key = biotern_nav_route_key($href);
        return $key !== '' && $current !== '' && $key === $current;
    }
}

if (!function_exists('biotern_nav_any_active')) {
    function biotern_nav_any_active($current, $hrefs) {
        foreach ((array)$hrefs as $href) {
            if (biotern_nav_is_active($href, $current)) {
                return true;
            }
        }
        return false;
    }
}

$nav_active_dashboard = biotern_nav_any_active($nav_current_file, ['homepage.php', 'analytics.php']);
$nav_active_students = biotern_nav_any_active($nav_current_file, [
    'students.php', 'students-create.php', 'students-edit.php', 'students-view.php', 'students-dtr.php', 'students-internal-dtr.php',
    'applications-review.php', 'attendance.php', 'external-attendance.php', 'attendance-corrections.php', 'print_attendance.php',
]);
$nav_active_ojt = biotern_nav_any_active($nav_current_file, [
    'ojt.php', 'ojt-create.php', 'ojt-edit.php', 'ojt-view.php', 'ojt-workflow-board.php',
    'ojt-internal-list.php', 'ojt-external-list.php', 'companies.php',
]);
$nav_active_machine = biotern_nav_any_active($nav_current_file, [
    'external-biometric.php', 'fingerprint_mapping.php', 'biometric-machine.php', 'biometric_machine_sync.php',
]);
$nav_active_documents = biotern_nav_any_active($nav_current_file, [
    'document_application.php',
    'document_endorsement.php',
    'document_moa.php',
    'document_dau_moa.php',
    'document_resume.php',
    'document_dtr.php',
    'document_waiver.php',
]);
$nav_active_student = biotern_nav_any_active($nav_current_file, [
    'student-profile.php',
    'student-dtr.php', 'student-internal-dtr.php',
    'student-external-dtr.php', 'external-biometric.php',
    'student-manual-dtr.php',
    'student-documents.php',
]);
$nav_active_reports = biotern_nav_any_active($nav_current_file, [
    'reports-dtr-manual-input.php',
    'reports-chat-logs.php', 'reports-chat-reports.php', 'reports-login-logs.php',
]);
$nav_active_academic = biotern_nav_any_active($nav_current_file, [
    'courses.php', 'courses-create.php', 'courses-edit.php',
    'departments.php', 'departments-create.php', 'departments-edit.php',
    'sections.php', 'sections-create.php', 'sections-edit.php',
    'coordinators.php', 'coordinators-create.php', 'coordinators-edit.php',
    'supervisors.php', 'supervisors-create.php', 'supervisors-edit.php',
]);
$nav_active_apps = biotern_nav_any_active($nav_current_file, [
    'apps-chat.php', 'apps-email.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
]);
$nav_active_users = biotern_nav_any_active($nav_current_file, [
    'auth-register.php', 'users.php', 'create_admin.php',
]);
$nav_active_settings = biotern_nav_any_active($nav_current_file, [
    'settings-general.php', 'settings-email.php', 'settings-ojt.php', 'settings-students.php',
    'settings-support.php',
    'notifications.php', 'account-settings.php',
]);
$nav_active_student_settings = biotern_nav_any_active($nav_current_file, [
    'notifications.php', 'account-settings.php',
]);
$nav_active_student_tools = biotern_nav_any_active($nav_current_file, [
    'theme-customizer.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
]);
$nav_active_tools = biotern_nav_any_active($nav_current_file, [
    'theme-customizer.php', 'import-sql.php', 'import-students-excel.php', 'import-ojt-internal.php', 'import-ojt-external.php',
]);
?>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="homepage.php" class="b-brand">
                <img src="assets/images/logo-full.png" alt="BioTern" class="logo logo-lg app-logo-lg" />
                <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm" />
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <li class="nxl-item nxl-caption">
                    <span>Main</span>
                </li>
                <?php if ($nav_is_student): ?>
                <li class="nxl-item<?php echo biotern_nav_is_active('homepage.php', $nav_current_file) ? ' active' : ''; ?>">
                    <a href="homepage.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-airplay"></i></span>
                        <span class="nxl-mtext">Dashboard</span>
                    </a>
                </li>
                <?php else: ?>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_dashboard ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-airplay"></i></span>
                        <span class="nxl-mtext">Dashboard</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('homepage.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="homepage.php">Overview</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('analytics.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="analytics.php">Analytics</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_internship): ?>
                <li class="nxl-item nxl-caption">
                    <span>Internship</span>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_students ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-users"></i></span>
                        <span class="nxl-mtext">Student Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('students.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="students.php">Students List</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('applications-review.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="applications-review.php">Applications Review</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('attendance.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="attendance.php">Internal DTR</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('external-attendance.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="external-attendance.php">External DTR</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_ojt ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                        <span class="nxl-mtext">OJT Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('ojt.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="ojt.php">OJT List</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('ojt-create.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="ojt-create.php">OJT Create</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('companies.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="companies.php">Companies</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('ojt-internal-list.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="ojt-internal-list.php">Internal List</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('ojt-external-list.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="ojt-external-list.php">External List</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_machine ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-cpu"></i></span>
                        <span class="nxl-mtext">Machine Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('fingerprint_mapping.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="fingerprint_mapping.php">Fingerprint Mapping</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('biometric-machine.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="biometric-machine.php">F20H Machine Manager</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('biometric_machine_sync.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="biometric_machine_sync.php?redirect=biometric-machine.php">Sync Biometric Machine</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_documents ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">Documents</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('document_application.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="document_application.php">Application</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('document_endorsement.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="document_endorsement.php">Endorsement</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('document_moa.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="document_moa.php">MOA</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('document_dau_moa.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="document_dau_moa.php">DAU MOA</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_reports ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-cast"></i></span>
                        <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        
                        <li class="nxl-item<?php echo biotern_nav_is_active('reports-dtr-manual-input.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="reports-dtr-manual-input.php">Manual DTR Input</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('reports-chat-logs.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="reports-chat-logs.php">Chat Logs</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('reports-chat-reports.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="reports-chat-reports.php">Reported Chats</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('reports-login-logs.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="reports-login-logs.php">Login Logs</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_is_student): ?>
                <li class="nxl-item nxl-caption">
                    <span>Student</span>
                </li>
                <li class="nxl-item<?php echo biotern_nav_is_active('student-profile.php', $nav_current_file) ? ' active' : ''; ?>">
                    <a href="student-profile.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-user"></i></span>
                        <span class="nxl-mtext">My Profile</span>
                    </a>
                </li>
                <li class="nxl-item<?php echo biotern_nav_any_active($nav_current_file, ['student-dtr.php', 'student-internal-dtr.php']) ? ' active' : ''; ?>">
                    <a href="student-internal-dtr.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-clock"></i></span>
                        <span class="nxl-mtext">My Internal DTR</span>
                    </a>
                </li>
                <?php if ($nav_student_has_external_access): ?>
                <li class="nxl-item<?php echo biotern_nav_any_active($nav_current_file, ['student-external-dtr.php', 'external-biometric.php']) ? ' active' : ''; ?>">
                    <a href="external-biometric.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                        <span class="nxl-mtext">My External DTR</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nxl-item<?php echo biotern_nav_is_active('student-manual-dtr.php', $nav_current_file) ? ' active' : ''; ?>">
                    <a href="student-manual-dtr.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-edit-3"></i></span>
                        <span class="nxl-mtext">Manual DTR</span>
                    </a>
                </li>
                <li class="nxl-item<?php echo biotern_nav_is_active('student-documents.php', $nav_current_file) ? ' active' : ''; ?>">
                    <a href="student-documents.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">My Documents</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_academic): ?>
                <li class="nxl-item nxl-caption">
                    <span>Academic</span>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_academic ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-book"></i></span>
                        <span class="nxl-mtext">Academic Setup</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('courses.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="courses.php">Courses</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('departments.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="departments.php">Departments</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('sections.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="sections.php">Sections</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('coordinators.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="coordinators.php">Coordinators</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('supervisors.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="supervisors.php">Supervisors</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_workspace): ?>
                <li class="nxl-item nxl-caption">
                    <span>Workspace</span>
                </li>
                <?php if ($nav_is_student): ?>
                <li class="nxl-item<?php echo biotern_nav_is_active('apps-chat.php', $nav_current_file) ? ' active' : ''; ?>">
                    <a href="apps-chat.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-message-circle"></i></span>
                        <span class="nxl-mtext">Chat</span>
                    </a>
                </li>
                <li class="nxl-item<?php echo biotern_nav_is_active('apps-email.php', $nav_current_file) ? ' active' : ''; ?>">
                    <a href="apps-email.php" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-mail"></i></span>
                        <span class="nxl-mtext">Email</span>
                    </a>
                </li>
                <?php else: ?>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_apps ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-send"></i></span>
                        <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-chat.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-chat.php">Chat</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-email.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-email.php">Email</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-notes.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-notes.php">Notes</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-storage.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-storage.php">Storage</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-calendar.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-calendar.php">Calendar</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($nav_is_student): ?>
                <li class="nxl-item nxl-caption">
                    <span>Settings</span>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_student_settings ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-settings"></i></span>
                        <span class="nxl-mtext">My Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('notifications.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="notifications.php">Notifications</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('account-settings.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="account-settings.php">Account Settings</a></li>
                    </ul>
                </li>

                <li class="nxl-item nxl-caption">
                    <span>Tools</span>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_student_tools ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-tool"></i></span>
                        <span class="nxl-mtext">Student Tools</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-notes.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-notes.php">Notes</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-storage.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-storage.php">Storage</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('apps-calendar.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="apps-calendar.php">Calendar</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('theme-customizer.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="theme-customizer.php">Appearance</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_system): ?>
                <li class="nxl-item nxl-caption">
                    <span>System</span>
                </li>
                <?php if ($nav_can_user_accounts): ?>
                    <li class="nxl-item nxl-hasmenu<?php echo $nav_active_users ? ' active nxl-trigger' : ''; ?>">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                            <span class="nxl-mtext">User Accounts</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item<?php echo biotern_nav_is_active('auth-register.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="auth-register.php">User Registration</a></li>
                            <li class="nxl-item<?php echo biotern_nav_is_active('users.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="users.php">Users</a></li>
                            <li class="nxl-item<?php echo biotern_nav_is_active('create_admin.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="create_admin.php">Create Admin</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_settings ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-settings"></i></span>
                        <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('settings-general.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="settings-general.php">General</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('settings-email.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="settings-email.php">Email</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('settings-ojt.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="settings-ojt.php">OJT Settings</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('settings-students.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="settings-students.php">Student Settings</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('settings-support.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="settings-support.php">Support</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('notifications.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="notifications.php">Notifications</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('account-settings.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="account-settings.php">Account Settings</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu<?php echo $nav_active_tools ? ' active nxl-trigger' : ''; ?>">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-tool"></i></span>
                        <span class="nxl-mtext">Tools</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item<?php echo biotern_nav_is_active('import-students-excel.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="import-students-excel.php">Excel Import</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('import-ojt-internal.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="import-ojt-internal.php">Import OJT Internal</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('import-ojt-external.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="import-ojt-external.php">Import OJT External</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('import-sql.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="import-sql.php">Data Transfer</a></li>
                        <li class="nxl-item<?php echo biotern_nav_is_active('theme-customizer.php', $nav_current_file) ? ' active' : ''; ?>"><a class="nxl-link" href="theme-customizer.php">Appearance</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>


