<?php
// Centralized navigation include copied from index.php (updated)
?>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="index.php" class="b-brand">
                <img src="assets/images/logo-full.png" alt="" class="logo logo-lg" />
                <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm" />
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
                        <li class="nxl-item"><a class="nxl-link" href="index.php">Overview</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="analytics.php">Analytics</a></li>
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
                        <span class="nxl-micon"><i class="feather-users"></i></span>
                        <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="students.php">Students List</a></li>

                        <li class="nxl-item"><a class="nxl-link" href="students-edit.php">Students Edit</a></li>
                        <li class="nxl-divider"></li>
                        <li class="nxl-item"><a class="nxl-link" href="attendance.php">Attendance DTR</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="demo-biometric.php">Demo Biometric</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-book"></i></span>
                        <span class="nxl-mtext">Courses</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="courses.php">Courses</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="courses-create.php">Create Course</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="courses-edit.php">Edit Course</a></li>
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-grid"></i></span>
                        <span class="nxl-mtext">Departments</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="departments.php">Departments</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="departments-create.php">Create Department</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="departments-edit.php">Edit Department</a></li>
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
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                        <span class="nxl-mtext">Assign OJT Designation</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="ojt.php">OJT List</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="ojt-create.php">OJT Create</a></li>
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
                    </ul>
                </li>
                <li class="nxl-item nxl-hasmenu">
                    <a href="javascript:void(0);" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                        <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                    </a>
                    <ul class="nxl-submenu">
                        <li class="nxl-item"><a class="nxl-link" href="#!">Support</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.php">KnowledgeBase</a></li>
                        <li class="nxl-item"><a class="nxl-link" href="/docs/documentations">Documentations</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
