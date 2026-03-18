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
$nav_dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$nav_dir = rtrim($nav_dir, '/');
if ($nav_dir === '' || $nav_dir === '.') {
    $nav_dir = '';
}
$nav_dir = preg_replace('#/(management|pages|auth)$#i', '', $nav_dir);
$nav_root = ($nav_dir === '' ? '' : $nav_dir) . '/';
$nav_asset_base = ($nav_dir === '' ? '' : $nav_dir) . '/assets';
$nav_asset_fallback = $nav_root . 'assets';
?>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="<?php echo htmlspecialchars($nav_root, ENT_QUOTES, 'UTF-8'); ?>homepage.php" class="b-brand">
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
                        <li class="nxl-item"><a class="nxl-link" href="homepage.php">Overview</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="analytics.php">Analytics</a></li>
                    </ul>
                </li>

                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($nav_can_internship): ?>
                <li class="nxl-item nxl-caption">
                    <span>Internship</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-users"></i></span>
                        <span class="nxl-mtext">Student Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="students.php">Students List</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="applications-review.php">Applications Review</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="attendance.php">Attendance DTR</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="demo-biometric.php">Demo Biometric</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                        <span class="nxl-mtext">OJT Management</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="ojt.php">OJT List</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="ojt-create.php">OJT Create</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">Documents</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="document_application.php">Application</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="document_endorsement.php">Endorsement</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="document_moa.php">MOA</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="document_dau_moa.php">Dau MOA</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-cast"></i></span>
                        <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="reports-sales.php">Sales Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="reports-ojt.php">OJT Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="reports-project.php">Project Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="reports-timesheets.php">Timesheets Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="reports-chat-logs.php">Chat Logs</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="reports-chat-reports.php">Reported Chats</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="reports-login-logs.php">Login Logs</a></li>
                    </ul>
                </li>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>

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
                        <li class="nxl-item"><a class="nxl-link" href="student-profile.php">My Profile</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="student-dtr.php">My DTR</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">My Documents</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="document_application.php">Application</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="document_endorsement.php">Endorsement</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="document_moa.php">MOA</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="document_dau_moa.php">Dau MOA</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($nav_can_academic): ?>
                <li class="nxl-item nxl-caption">
                    <span>Academic</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-book"></i></span>
                        <span class="nxl-mtext">Academic Setup</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="courses.php">Courses</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="departments.php">Departments</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="sections.php">Sections</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="coordinators.php">Coordinators</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="supervisors.php">Supervisors</a></li>
                    </ul>
                </li>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>

                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($nav_can_workspace): ?>
                <li class="nxl-item nxl-caption">
                    <span>Workspace</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-send"></i></span>
                        <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="apps-chat.php">Chat</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="apps-email.php">Email</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="apps-tasks.php">Tasks</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="apps-notes.php">Notes</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="apps-storage.php">Storage</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="apps-calendar.php">Calendar</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-layout"></i></span>
                        <span class="nxl-mtext">Widgets</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="widgets-lists.php">Lists</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="widgets-tables.php">Tables</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="widgets-charts.php">Charts</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="widgets-statistics.php">Statistics</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="widgets-miscellaneous.php">Miscellaneous</a></li>
                    </ul>
                </li>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>

                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($nav_can_system): ?>
                <li class="nxl-item nxl-caption">
                    <span>System</span>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                        <span class="nxl-mtext">User Accounts</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="auth-register-creative.php">User Registration</a></li>
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
                        <li class="nxl-item"><a class="nxl-link" href="settings-general.php">General</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-seo.php">SEO</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-tags.php">Tags</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-email.php">Email</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-tasks.php">Tasks</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-ojt.php">Leads</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-support.php">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="settings-miscellaneous.php">Miscellaneous</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="theme-customizer.php">Theme Customizer</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                        <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="settings-support.php">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.php">Knowledge Base</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="/docs/documentations">Documentations</a></li>
                    </ul>
                </li>
                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
            </ul>
        </div>
    </div>
</nav>
<script src="assets/js/navigation-state.js"></script>

