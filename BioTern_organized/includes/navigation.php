<?php
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
?>
<style>
    @media (min-width: 1025px) {
        html.minimenu .nxl-navigation .navbar-content .nxl-caption {
            display: block !important;
            padding: 10px 8px 6px;
            text-align: center;
        }

        html.minimenu .nxl-navigation .navbar-content .nxl-caption:before {
            content: none !important;
            display: none !important;
        }

        html.minimenu .nxl-navigation .navbar-content .nxl-caption span,
        html.minimenu .nxl-navigation .navbar-content .nxl-caption label {
            display: block !important;
        }

        html.minimenu .nxl-navigation:hover .navbar-content .nxl-caption span {
            display: block !important;
        }

        html.minimenu .nxl-navigation:hover .m-header {
            padding-left: 20px;
            padding-right: 20px;
            justify-content: flex-start;
        }

        html.minimenu .nxl-navigation:hover .m-header .logo.logo-lg {
            display: block !important;
            width: 210px !important;
            height: auto !important;
        }

        html.minimenu .nxl-navigation:hover .m-header .logo.logo-sm {
            display: none !important;
        }

        html.minimenu .nxl-navigation {
            overflow: visible;
        }

        html.minimenu .nxl-navigation:hover {
            width: 280px !important;
            z-index: 1027;
        }

        html.minimenu .nxl-navigation:hover .navbar-wrapper {
            position: relative !important;
            width: 280px;
            height: 100vh;
        }

        html.minimenu .nxl-navigation:hover .navbar-content {
            position: relative !important;
            width: 280px !important;
            height: calc(100vh - 80px);
            overflow-x: hidden !important;
            overflow-y: auto !important;
            overscroll-behavior: contain;
        }
    }

</style>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="/BioTern/BioTern_organized/legacy_router.php?file=homepage.php" class="b-brand">
                <img src="assets/images/logo-full.png" alt="BioTern" class="logo logo-lg" style="width:210px;height:auto;object-fit:contain;" />
                <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm" />
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
                        <li class="nxl-item"><a class="nxl-link" href="students.php">Students List</a></li>
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
                        <li class="nxl-item"><a class="nxl-link" href="courses.php">Courses</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="departments.php">Departments</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="sections.php">Sections</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="coordinators.php">Coordinators</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="supervisors.php">Supervisors</a></li>
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
                        <li class="nxl-item"><a class="nxl-link" href="#!">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.php">Knowledge Base</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="/docs/documentations">Documentations</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<script>
    (function () {
        var KEY_SCROLL = 'biotern.sidebar.scrollTop';

        function getPathname(href) {
            try {
                return new URL(href, window.location.origin).pathname.toLowerCase();
            } catch (e) {
                return '';
            }
        }

        function getRouteKeyFromUrl(urlObj) {
            var qp = (urlObj.searchParams.get('file') || '').toLowerCase();
            if (qp && qp.endsWith('.php')) return qp;
            var path = (urlObj.pathname || '').toLowerCase();
            var parts = path.split('/');
            var last = parts[parts.length - 1] || '';
            if (last.endsWith('.php')) return last;
            return '';
        }

        function getCurrentRouteKey() {
            try {
                return getRouteKeyFromUrl(new URL(window.location.href));
            } catch (e) {
                return '';
            }
        }

        function getLinkRouteKey(href) {
            try {
                return getRouteKeyFromUrl(new URL(href, window.location.origin));
            } catch (e) {
                return '';
            }
        }

        function getNav() {
            return document.querySelector('.nxl-navigation .nxl-navbar');
        }

        function getScrollContainer() {
            return document.querySelector('.nxl-navigation .navbar-content');
        }

        function persistState() {
            var nav = getNav();
            if (!nav) return;
            try {
                var sc = getScrollContainer();
                if (sc) localStorage.setItem(KEY_SCROLL, String(sc.scrollTop || 0));
            } catch (e) {}
        }

        function restoreState() {
            var nav = getNav();
            if (!nav) return;

            var currentRoute = getCurrentRouteKey();
            nav.querySelectorAll('.nxl-item.active').forEach(function (item) {
                item.classList.remove('active');
            });
            nav.querySelectorAll('.nxl-item.nxl-hasmenu.nxl-trigger').forEach(function (item) {
                item.classList.remove('nxl-trigger');
            });

            nav.querySelectorAll('.nxl-item .nxl-link[href]').forEach(function (a) {
                var linkKey = getLinkRouteKey(a.getAttribute('href') || '');
                if (linkKey && currentRoute && linkKey === currentRoute) {
                    var item = a.closest('.nxl-item');
                    if (item) item.classList.add('active');
                    var parentMenu = a.closest('.nxl-item.nxl-hasmenu');
                    if (parentMenu) parentMenu.classList.add('active', 'nxl-trigger');
                }
            });

            try {
                var sc = getScrollContainer();
                var savedTop = parseInt(localStorage.getItem(KEY_SCROLL) || '0', 10);
                if (sc && !isNaN(savedTop) && savedTop > 0) {
                    requestAnimationFrame(function () {
                        sc.scrollTop = savedTop;
                    });
                }
            } catch (e) {}
        }

        restoreState();

        document.querySelectorAll('.nxl-navigation .nxl-item.nxl-hasmenu > .nxl-link').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                setTimeout(persistState, 0);
            });
        }

    })();
</script>
