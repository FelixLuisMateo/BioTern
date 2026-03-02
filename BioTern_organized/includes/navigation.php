<?php
// Centralized navigation include for BioTern_organized.
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$projectDir = basename(dirname(__DIR__));
$marker = '/' . $projectDir . '/';
$markerPos = strpos(strtolower($scriptName), strtolower($marker));

if ($markerPos !== false) {
    $baseUrl = substr($scriptName, 0, $markerPos + strlen($marker));
} else {
    $baseUrl = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/';
}

$navUrl = static function (string $path) use ($baseUrl): string {
    return $baseUrl . ltrim($path, '/');
};
?>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="<?= $navUrl('index.php') ?>" class="b-brand">
                <img src="<?= $navUrl('assets/images/logo-full.png') ?>" alt="" class="logo logo-lg" width="21100" height="3100" style="width:210px;height:2100px;object-fit:contain;" />
                <img src="<?= $navUrl('assets/images/logo-abbr.png') ?>" alt="" class="logo logo-sm" />
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <li class="nxl-item nxl-caption">
                    <label>Navigation</label>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-airplay"></i></span>
                        <span class="nxl-mtext">Home</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('analytics.php') ?>">Dashboard</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('analytics.php') ?>">Analytics</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-cast"></i></span>
                        <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('reports/reports-sales.php') ?>">Sales Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('reports/reports-ojt.php') ?>">OJT Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('reports/reports-project.php') ?>">Project Report</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('reports/reports-timesheets.php') ?>">Timesheets Report</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-send"></i></span>
                        <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('apps/apps-chat.php') ?>">Chat</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('apps/apps-email.php') ?>">Email</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('apps/apps-tasks.php') ?>">Tasks</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('apps/apps-notes.php') ?>">Notes</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('apps/apps-storage.php') ?>">Storage</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('apps/apps-calendar.php') ?>">Calendar</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-users"></i></span>
                        <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('management/students.php') ?>">Students List</a></li>
                        <li class="nxl-divider"></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('pages/attendance.php') ?>">Attendance DTR</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('api/register_fingerprint.php') ?>">Demo Biometric</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-book"></i></span>
                        <span class="nxl-mtext">Courses</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('management/courses.php') ?>">Courses</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-grid"></i></span>
                        <span class="nxl-mtext">Departments</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('management/departments.php') ?>">Departments</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-file-text"></i></span>
                        <span class="nxl-mtext">Documents</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('documents/document_application.php') ?>">Application</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('documents/document_endorsement.php') ?>">Endorsement</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('documents/document_moa.php') ?>">MOA</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('documents/document_dau_moa.php') ?>">Dau MOA</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                        <span class="nxl-mtext">Assign OJT Designation</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('management/ojt.php') ?>">OJT List</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('management/ojt-create.php') ?>">OJT Create</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-layout"></i></span>
                        <span class="nxl-mtext">Widgets</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('widgets/widgets-lists.php') ?>">Lists</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('widgets/widgets-tables.php') ?>">Tables</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('widgets/widgets-charts.php') ?>">Charts</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('widgets/widgets-statistics.php') ?>">Statistics</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('widgets/widgets-miscellaneous.php') ?>">Miscellaneous</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                        <span class="nxl-mtext">Registration</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('auth-register-creative.php') ?>">User Registration</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-settings"></i></span>
                        <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-general.php') ?>">General</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-seo.php') ?>">SEO</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-tags.php') ?>">Tags</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-email.php') ?>">Email</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-tasks.php') ?>">Tasks</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-ojt.php') ?>">Leads</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-support.php') ?>">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-students.php') ?>">Students</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('settings/settings-miscellaneous.php') ?>">Miscellaneous</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                        <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="#!">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('public/help-knowledgebase.php') ?>">KnowledgeBase</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="<?= $navUrl('docs/documentations.html') ?>">Documentations</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
