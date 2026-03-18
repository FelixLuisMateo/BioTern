<?php
// Shared footer include.  It closes main container and adds global scripts.
$page_is_public = isset($page_is_public) && $page_is_public === true;
$page_render_container = isset($page_render_container) ? (bool)$page_render_container : !$page_is_public;
$page_render_footer = isset($page_render_footer) ? (bool)$page_render_footer : !$page_is_public;
?>
<?php if ($page_render_footer): ?>
    <!-- [ Footer ] start -->
    <footer class="footer">
        <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
            <span>Copyright &copy;</span>
            <span class="app-current-year"></span>
        </p>
        <p><span>By: <a href="#">ACT 2A</a> </span><span>Distributed by: <a href="#">Group 5</a></span></p>
        <div class="d-flex align-items-center gap-4">
            <a href="#" class="fs-11 fw-semibold text-uppercase">Help</a>
            <a href="#" class="fs-11 fw-semibold text-uppercase">Terms</a>
            <a href="#" class="fs-11 fw-semibold text-uppercase">Privacy</a>
        </div>
    </footer>
    <!-- [ Footer ] end -->
<?php endif; ?>

<?php if (!$page_is_public): ?>
    <?php
    if (!isset($nav_current_file) || $nav_current_file === '') {
        $nav_current_file = '';
        if (isset($_GET['file'])) {
            $nav_current_file = strtolower(basename((string)$_GET['file']));
        }
        if ($nav_current_file === '') {
            $nav_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $nav_current_file = strtolower(basename((string)$nav_request_path));
        }
    }

    $nav_groups = [];
    $nav_groups[] = [
        'key' => 'home',
        'label' => 'Home',
        'icon' => 'feather-home',
        'routes' => ['homepage.php', 'analytics.php'],
        'sections' => [
            [
                'title' => 'Dashboard',
                'items' => [
                    ['label' => 'Overview', 'href' => 'homepage.php', 'icon' => 'feather-grid'],
                    ['label' => 'Analytics', 'href' => 'analytics.php', 'icon' => 'feather-pie-chart'],
                ],
            ],
        ],
    ];

    if (isset($nav_can_internship) && $nav_can_internship) {
        $nav_groups[] = [
            'key' => 'internship',
            'label' => 'Internship',
            'icon' => 'feather-briefcase',
            'routes' => [
                'students.php', 'students-create.php', 'students-edit.php', 'students-view.php', 'students-dtr.php',
                'applications-review.php', 'attendance.php', 'attendance-corrections.php', 'print_attendance.php', 'demo-biometric.php',
                'ojt.php', 'ojt-create.php', 'ojt-edit.php', 'ojt-view.php', 'ojt-workflow-board.php',
                'document_application.php', 'document_endorsement.php', 'document_moa.php', 'document_dau_moa.php', 'document_dtr.php',
                'document_resume.php', 'document_waiver.php',
                'reports-sales.php', 'reports-ojt.php', 'reports-project.php', 'reports-timesheets.php', 'reports-attendance-operations.php',
            ],
            'sections' => [
                [
                    'title' => 'Student Management',
                    'items' => [
                        ['label' => 'Students List', 'href' => 'students.php', 'icon' => 'feather-users'],
                        ['label' => 'Applications Review', 'href' => 'applications-review.php', 'icon' => 'feather-clipboard'],
                        ['label' => 'Attendance DTR', 'href' => 'attendance.php', 'icon' => 'feather-clock'],
                        ['label' => 'Attendance Corrections', 'href' => 'attendance-corrections.php', 'icon' => 'feather-edit'],
                        ['label' => 'Demo Biometric', 'href' => 'demo-biometric.php', 'icon' => 'feather-rss'],
                    ],
                ],
                [
                    'title' => 'OJT Management',
                    'items' => [
                        ['label' => 'OJT List', 'href' => 'ojt.php', 'icon' => 'feather-archive'],
                        ['label' => 'OJT Create', 'href' => 'ojt-create.php', 'icon' => 'feather-plus-circle'],
                        ['label' => 'OJT Workflow', 'href' => 'ojt-workflow-board.php', 'icon' => 'feather-git-branch'],
                    ],
                ],
                [
                    'title' => 'Documents',
                    'items' => [
                        ['label' => 'Application', 'href' => 'document_application.php', 'icon' => 'feather-file-text'],
                        ['label' => 'Endorsement', 'href' => 'document_endorsement.php', 'icon' => 'feather-file-text'],
                        ['label' => 'MOA', 'href' => 'document_moa.php', 'icon' => 'feather-file-text'],
                        ['label' => 'Dau MOA', 'href' => 'document_dau_moa.php', 'icon' => 'feather-file-text'],
                        ['label' => 'DTR', 'href' => 'document_dtr.php', 'icon' => 'feather-file-text'],
                        ['label' => 'Resume', 'href' => 'document_resume.php', 'icon' => 'feather-file-text'],
                        ['label' => 'Waiver', 'href' => 'document_waiver.php', 'icon' => 'feather-file-text'],
                    ],
                ],
                [
                    'title' => 'Reports',
                    'items' => [
                        ['label' => 'Sales Report', 'href' => 'reports-sales.php', 'icon' => 'feather-bar-chart-2'],
                        ['label' => 'OJT Report', 'href' => 'reports-ojt.php', 'icon' => 'feather-pie-chart'],
                        ['label' => 'Project Report', 'href' => 'reports-project.php', 'icon' => 'feather-activity'],
                        ['label' => 'Timesheets Report', 'href' => 'reports-timesheets.php', 'icon' => 'feather-clock'],
                        ['label' => 'Attendance Operations', 'href' => 'reports-attendance-operations.php', 'icon' => 'feather-clipboard'],
                    ],
                ],
            ],
        ];
    }

    if (isset($nav_can_academic) && $nav_can_academic) {
        $nav_groups[] = [
            'key' => 'academic',
            'label' => 'Academic',
            'icon' => 'feather-book',
            'routes' => [
                'courses.php', 'courses-create.php', 'courses-edit.php',
                'departments.php', 'departments-create.php', 'departments-edit.php',
                'sections.php', 'sections-create.php', 'sections-edit.php',
                'coordinators.php', 'coordinators-create.php', 'coordinators-edit.php',
                'supervisors.php', 'supervisors-create.php', 'supervisors-edit.php',
            ],
            'sections' => [
                [
                    'title' => 'Academic Setup',
                    'items' => [
                        ['label' => 'Courses', 'href' => 'courses.php', 'icon' => 'feather-book-open'],
                        ['label' => 'Departments', 'href' => 'departments.php', 'icon' => 'feather-briefcase'],
                        ['label' => 'Sections', 'href' => 'sections.php', 'icon' => 'feather-layers'],
                        ['label' => 'Coordinators', 'href' => 'coordinators.php', 'icon' => 'feather-user-check'],
                        ['label' => 'Supervisors', 'href' => 'supervisors.php', 'icon' => 'feather-user'],
                    ],
                ],
            ],
        ];
    }

    if (isset($nav_can_workspace) && $nav_can_workspace) {
        $nav_groups[] = [
            'key' => 'workspace',
            'label' => 'Workspace',
            'icon' => 'feather-layers',
            'routes' => [
                'apps-chat.php', 'apps-email.php', 'apps-tasks.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
                'widgets-lists.php', 'widgets-tables.php', 'widgets-charts.php', 'widgets-statistics.php', 'widgets-miscellaneous.php',
            ],
            'sections' => [
                [
                    'title' => 'Applications',
                    'items' => [
                        ['label' => 'Chat', 'href' => 'apps-chat.php', 'icon' => 'feather-message-circle'],
                        ['label' => 'Email', 'href' => 'apps-email.php', 'icon' => 'feather-mail'],
                        ['label' => 'Tasks', 'href' => 'apps-tasks.php', 'icon' => 'feather-check-square'],
                        ['label' => 'Notes', 'href' => 'apps-notes.php', 'icon' => 'feather-edit-2'],
                        ['label' => 'Storage', 'href' => 'apps-storage.php', 'icon' => 'feather-hard-drive'],
                        ['label' => 'Calendar', 'href' => 'apps-calendar.php', 'icon' => 'feather-calendar'],
                    ],
                ],
                [
                    'title' => 'Widgets',
                    'items' => [
                        ['label' => 'Lists', 'href' => 'widgets-lists.php', 'icon' => 'feather-list'],
                        ['label' => 'Tables', 'href' => 'widgets-tables.php', 'icon' => 'feather-grid'],
                        ['label' => 'Charts', 'href' => 'widgets-charts.php', 'icon' => 'feather-bar-chart'],
                        ['label' => 'Statistics', 'href' => 'widgets-statistics.php', 'icon' => 'feather-trending-up'],
                        ['label' => 'Miscellaneous', 'href' => 'widgets-miscellaneous.php', 'icon' => 'feather-package'],
                    ],
                ],
            ],
        ];
    }

    if (isset($nav_can_system) && $nav_can_system) {
        $nav_groups[] = [
            'key' => 'system',
            'label' => 'System',
            'icon' => 'feather-settings',
            'routes' => [
                'auth-register-creative.php', 'users.php', 'create_admin.php',
                'settings-general.php', 'settings-seo.php', 'settings-tags.php', 'settings-email.php', 'settings-tasks.php', 'settings-ojt.php',
                'settings-support.php', 'settings-students.php', 'settings-miscellaneous.php', 'settings-localization.php', 'theme-customizer.php',
                'help-knowledgebase.php',
            ],
            'sections' => [
                [
                    'title' => 'Users',
                    'items' => [
                        ['label' => 'Users', 'href' => 'users.php', 'icon' => 'feather-users'],
                        ['label' => 'Register Creative', 'href' => 'auth-register-creative.php', 'icon' => 'feather-user-plus'],
                        ['label' => 'Create Admin', 'href' => 'create_admin.php', 'icon' => 'feather-shield'],
                    ],
                ],
                [
                    'title' => 'Settings',
                    'items' => [
                        ['label' => 'General', 'href' => 'settings-general.php', 'icon' => 'feather-sliders'],
                        ['label' => 'SEO', 'href' => 'settings-seo.php', 'icon' => 'feather-globe'],
                        ['label' => 'Tags', 'href' => 'settings-tags.php', 'icon' => 'feather-tag'],
                        ['label' => 'Email', 'href' => 'settings-email.php', 'icon' => 'feather-mail'],
                        ['label' => 'Tasks', 'href' => 'settings-tasks.php', 'icon' => 'feather-check-square'],
                        ['label' => 'OJT', 'href' => 'settings-ojt.php', 'icon' => 'feather-briefcase'],
                        ['label' => 'Support', 'href' => 'settings-support.php', 'icon' => 'feather-life-buoy'],
                        ['label' => 'Students', 'href' => 'settings-students.php', 'icon' => 'feather-users'],
                        ['label' => 'Misc', 'href' => 'settings-miscellaneous.php', 'icon' => 'feather-layers'],
                        ['label' => 'Localization', 'href' => 'settings-localization.php', 'icon' => 'feather-flag'],
                        ['label' => 'Theme', 'href' => 'theme-customizer.php', 'icon' => 'feather-droplet'],
                    ],
                ],
                [
                    'title' => 'Help',
                    'items' => [
                        ['label' => 'Knowledgebase', 'href' => 'help-knowledgebase.php', 'icon' => 'feather-help-circle'],
                    ],
                ],
            ],
        ];
    }

    $profile_avatar = isset($header_avatar) ? $header_avatar : 'assets/images/avatar/1.png';
    $nav_groups[] = [
        'key' => 'profile',
        'label' => 'Profile',
        'icon' => 'feather-user',
        'routes' => [],
        'sections' => [
            [
                'title' => 'Account',
                'items' => [
                    ['label' => 'Profile Details', 'href' => 'settings-general.php', 'icon' => 'feather-user'],
                    ['label' => 'Account Settings', 'href' => 'settings-general.php', 'icon' => 'feather-settings'],
                    ['label' => 'Logout', 'href' => '/BioTern/BioTern/auth/auth-login-cover.php?logout=1', 'icon' => 'feather-log-out'],
                ],
            ],
        ],
    ];
    ?>

    <?php if (!empty($nav_groups)): ?>
        <nav class="biotern-bottom-nav" aria-label="Primary">
            <?php foreach ($nav_groups as $group): ?>
                <?php
                $routes = isset($group['routes']) ? implode(',', (array)$group['routes']) : '';
                $is_profile = ($group['key'] ?? '') === 'profile';
                ?>
                <button type="button"
                        class="biotern-bottom-link<?php echo $is_profile ? ' is-profile' : ''; ?>"
                        data-panel-target="<?php echo htmlspecialchars((string)$group['key'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-routes="<?php echo htmlspecialchars($routes, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($is_profile): ?>
                        <span class="biotern-bottom-avatar">
                            <img src="<?php echo htmlspecialchars((string)$profile_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                        </span>
                    <?php else: ?>
                        <i class="<?php echo htmlspecialchars((string)$group['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <?php endif; ?>
                    <span class="biotern-bottom-label"><?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="biotern-bottom-sheet" id="bioternBottomSheet" aria-hidden="true">
            <div class="biotern-bottom-sheet-backdrop" data-sheet-close></div>
            <div class="biotern-bottom-sheet-panel" role="dialog" aria-modal="true">
                <div class="biotern-bottom-sheet-handle"></div>
                <?php foreach ($nav_groups as $group): ?>
                    <div class="biotern-bottom-sheet-content" data-panel="<?php echo htmlspecialchars((string)$group['key'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="biotern-bottom-sheet-title"><?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($group['sections'])): ?>
                            <?php foreach ($group['sections'] as $section): ?>
                                <div class="biotern-bottom-sheet-section">
                                    <div class="biotern-bottom-sheet-section-title"><?php echo htmlspecialchars((string)$section['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="biotern-bottom-sheet-links">
                                        <?php foreach ((array)$section['items'] as $item): ?>
                                            <a class="biotern-bottom-sheet-link" href="<?php echo htmlspecialchars((string)$item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="<?php echo htmlspecialchars((string)$item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                <span><?php echo htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/dataTables.min.js"></script>
    <script src="assets/vendors/js/dataTables.bs5.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/global-ui-helpers.js"></script>
    <script src="assets/js/theme-preferences-runtime.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
    <script src="assets/js/customers-init.min.js"></script>
    <?php if (isset($page_vendor_scripts) && is_array($page_vendor_scripts)): ?>
        <?php foreach ($page_vendor_scripts as $vendor_script): ?>
            <?php if (is_string($vendor_script) && trim($vendor_script) !== ''): ?>
                <script src="<?php echo htmlspecialchars($vendor_script, ENT_QUOTES, 'UTF-8'); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (isset($page_scripts) && is_array($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <?php if (is_string($script) && trim($script) !== ''): ?>
                <script src="<?php echo htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <!--! END: Apps Init !-->
    <!-- Theme runtime moved to assets/js/global-ui-helpers.js and assets/js/theme-preferences-runtime.js -->
    <!-- Header search inline bootstrap removed; handled by assets/js/common-init.min.js -->
</body>

</html>





