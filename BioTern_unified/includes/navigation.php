<?php
require_once dirname(__DIR__) . '/config/db.php';
// Centralized navigation include (grouped/relabeled).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nav_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'guest')));
$nav_is_admin = ($nav_role === 'admin');
$nav_is_coordinator = ($nav_role === 'coordinator');
$nav_is_supervisor = ($nav_role === 'supervisor');
$nav_is_student = ($nav_role === 'student');

$nav_can_internship = ($nav_is_admin || $nav_is_coordinator || $nav_is_supervisor);
$nav_can_academic = ($nav_is_admin || $nav_is_coordinator);
$nav_can_workspace = ($nav_is_admin || $nav_is_coordinator);
$nav_can_system = $nav_is_admin;
$nav_can_reports = ($nav_is_admin || $nav_is_coordinator || $nav_is_supervisor);
$nav_root = isset($base_href) && is_string($base_href) ? $base_href : '';

if (!function_exists('biotern_normalize_web_root')) {
    function biotern_normalize_web_root(string $root): string
    {
        $root = str_replace('\\', '/', trim($root));
        if ($root === '') {
            return '';
        }

        // If a filesystem path leaks into href generation, convert it to a web path.
        if (preg_match('#^/?[A-Za-z]:/#', $root) === 1) {
            $htdocs_pos = stripos($root, '/htdocs/');
            if ($htdocs_pos !== false) {
                $root = substr($root, $htdocs_pos + strlen('/htdocs'));
            }
        }

        if ($root !== '' && $root[0] !== '/') {
            $root = '/' . $root;
        }

        $root = preg_replace('#/+#', '/', $root);
        return rtrim((string)$root, '/') . '/';
    }
}

if (!function_exists('biotern_resolve_nav_root')) {
    function biotern_resolve_nav_root(string $root): string
    {
        $root = biotern_normalize_web_root($root);
        if ($root !== '') {
            return $root;
        }

        $script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $project_segment = '/' . basename(dirname(__DIR__)) . '/';
        $project_pos = stripos($script_name, $project_segment);
        if ($project_pos !== false) {
            return substr($script_name, 0, $project_pos) . $project_segment;
        }

        return '/';
    }
}

$nav_root = biotern_resolve_nav_root($nav_root);
if ($nav_root !== '' && substr($nav_root, -1) !== '/') {
    $nav_root .= '/';
}
$nav_asset_base = $nav_root . 'assets';
$nav_asset_fallback = $nav_asset_base;

if (!function_exists('nav_page_href')) {
    function nav_page_href(string $root, string $file): string
    {
        return htmlspecialchars($root . ltrim($file, '/'), ENT_QUOTES, 'UTF-8');
    }
}
?>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="<?php echo nav_page_href($nav_root, 'homepage.php'); ?>" class="b-brand">
                    <img src="<?php echo htmlspecialchars($nav_asset_base); ?>/images/logo-full.png" alt="BioTern" class="logo logo-lg logo-lg-contained nav-fallback-img" data-fallback-src="<?php echo htmlspecialchars($nav_asset_fallback); ?>/images/logo-full.png" />
                    <img src="<?php echo htmlspecialchars($nav_asset_base); ?>/images/logo-abbr.png" alt="" class="logo logo-sm nav-fallback-img" data-fallback-src="<?php echo htmlspecialchars($nav_asset_fallback); ?>/images/logo-abbr.png" />
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <li class="nxl-item nxl-caption">
                    <span>Main</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-airplay"></i></span>
                        <span class="nxl-mtext">Dashboard</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'homepage.php'); ?>">Overview</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'analytics.php'); ?>">Analytics</a></li>
                    </ul>
                </li>

                <?php if ($nav_can_internship): ?>
                <li class="nxl-item nxl-caption">
                    <span>Internship</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-users"></i></span>
                        <span class="nxl-mtext">Student Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'students.php'); ?>">Students List</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'applications-review.php'); ?>">Applications Review</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'attendance.php'); ?>">Attendance DTR</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'demo-biometric.php'); ?>">Demo Biometric</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                        <span class="nxl-mtext">OJT Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'ojt.php'); ?>">OJT List</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'ojt-create.php'); ?>">OJT Create</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">Documents</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_application.php'); ?>">Application</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_endorsement.php'); ?>">Endorsement</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_moa.php'); ?>">MOA</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_dau_moa.php'); ?>">Dau MOA</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-cast"></i></span>
                        <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'reports-ojt.php'); ?>">OJT Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'reports-project.php'); ?>">Project Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'reports-timesheets.php'); ?>">Timesheets Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'reports-chat-logs.php'); ?>">Chat Logs</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'reports-chat-reports.php'); ?>">Reported Chats</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'reports-login-logs.php'); ?>">Login Logs</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_is_student): ?>
                <li class="nxl-item nxl-caption">
                    <span>Student</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-user"></i></span>
                        <span class="nxl-mtext">My Account</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'student-profile.php'); ?>">My Profile</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'student-dtr.php'); ?>">My DTR</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">My Documents</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_application.php'); ?>">Application</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_endorsement.php'); ?>">Endorsement</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_moa.php'); ?>">MOA</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'document_dau_moa.php'); ?>">Dau MOA</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_academic): ?>
                <li class="nxl-item nxl-caption">
                    <span>Academic</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-book"></i></span>
                        <span class="nxl-mtext">Academic Setup</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'courses.php'); ?>">Courses</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'departments.php'); ?>">Departments</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'sections.php'); ?>">Sections</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'coordinators.php'); ?>">Coordinators</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'supervisors.php'); ?>">Supervisors</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_workspace): ?>
                <li class="nxl-item nxl-caption">
                    <span>Workspace</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-send"></i></span>
                        <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'apps-chat.php'); ?>">Chat</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'apps-email.php'); ?>">Email</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'apps-notes.php'); ?>">Notes</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'apps-storage.php'); ?>">Storage</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'apps-calendar.php'); ?>">Calendar</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($nav_can_system): ?>
                <li class="nxl-item nxl-caption">
                    <span>System</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                        <span class="nxl-mtext">User Accounts</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="users.php">Users</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="create_admin.php">Create Admin</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-settings"></i></span>
                        <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-general.php'); ?>">General</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-tags.php'); ?>">Tags</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-email.php'); ?>">Email</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-tasks.php'); ?>">Tasks</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-ojt.php'); ?>">Leads</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-support.php'); ?>">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-students.php'); ?>">Students</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'import-sql.php'); ?>">Data Transfer</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'theme-customizer.php'); ?>">Theme Customizer</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                        <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'settings-support.php'); ?>">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?php echo nav_page_href($nav_root, 'help-knowledgebase.php'); ?>">Knowledge Base</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="/docs/documentations">Documentations</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<script src="assets/js/navigation-state.js"></script>

