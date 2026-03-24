<?php

if (!function_exists('biotern_build_bottom_nav_groups')) {
    function biotern_build_bottom_nav_groups(array $context = []): array
    {
        $canInternship = !empty($context['can_internship']);
        $canAcademic = !empty($context['can_academic']);
        $canWorkspace = !empty($context['can_workspace']);
        $canSystem = !empty($context['can_system']);

        $profileAvatar = isset($context['profile_avatar']) && is_string($context['profile_avatar']) && trim($context['profile_avatar']) !== ''
            ? trim($context['profile_avatar'])
            : 'assets/images/avatar/1.png';

        $navGroups = [];

        $navGroups[] = [
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

        if ($canInternship) {
            $navGroups[] = [
                'key' => 'internship',
                'label' => 'Internship',
                'icon' => 'feather-briefcase',
                'routes' => [
                    'students.php', 'students-create.php', 'students-edit.php', 'students-view.php', 'students-dtr.php',
                    'applications-review.php', 'attendance.php', 'attendance-corrections.php', 'print_attendance.php', 'demo-biometric.php',
                    'ojt.php', 'ojt-create.php', 'ojt-edit.php', 'ojt-view.php', 'ojt-workflow-board.php',
                    'document_application.php', 'document_endorsement.php', 'document_moa.php', 'document_dau_moa.php', 'document_dtr.php',
                    'document_resume.php', 'document_waiver.php',
                    'reports-ojt.php', 'reports-project.php', 'reports-timesheets.php', 'reports-attendance-operations.php',
                    'reports-chat-logs.php', 'reports-chat-reports.php', 'reports-login-logs.php',
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
                            ['label' => 'OJT Report', 'href' => 'reports-ojt.php', 'icon' => 'feather-pie-chart'],
                            ['label' => 'Project Report', 'href' => 'reports-project.php', 'icon' => 'feather-activity'],
                            ['label' => 'Timesheets Report', 'href' => 'reports-timesheets.php', 'icon' => 'feather-clock'],
                            ['label' => 'Attendance Operations', 'href' => 'reports-attendance-operations.php', 'icon' => 'feather-clipboard'],
                            ['label' => 'Chat Logs', 'href' => 'reports-chat-logs.php', 'icon' => 'feather-message-circle'],
                            ['label' => 'Reported Chats', 'href' => 'reports-chat-reports.php', 'icon' => 'feather-flag'],
                            ['label' => 'Login Logs', 'href' => 'reports-login-logs.php', 'icon' => 'feather-log-in'],
                        ],
                    ],
                ],
            ];
        }

        if ($canAcademic) {
            $navGroups[] = [
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

        if ($canWorkspace) {
            $navGroups[] = [
                'key' => 'workspace',
                'label' => 'Workspace',
                'icon' => 'feather-layers',
                'routes' => [
                    'apps-chat.php', 'apps-email.php', 'apps-tasks.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
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
                ],
            ];
        }

        if ($canSystem) {
            $navGroups[] = [
                'key' => 'system',
                'label' => 'System',
                'icon' => 'feather-settings',
                'routes' => [
                    'auth-register-creative.php', 'users.php', 'create_admin.php',
                    'settings-support.php', 'theme-customizer.php',
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
                            ['label' => 'Support', 'href' => 'settings-support.php', 'icon' => 'feather-life-buoy'],
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

        $navGroups[] = [
            'key' => 'profile',
            'label' => 'Profile',
            'icon' => 'feather-user',
            'routes' => [],
            'avatar' => $profileAvatar,
            'sections' => [
                [
                    'title' => 'Account',
                    'items' => [
                        ['label' => 'Profile Details', 'href' => 'settings-support.php', 'icon' => 'feather-user'],
                        ['label' => 'Account Settings', 'href' => 'settings-support.php', 'icon' => 'feather-settings'],
                        ['label' => 'Logout', 'href' => '/BioTern/BioTern/auth/auth-login-cover.php?logout=1', 'icon' => 'feather-log-out'],
                    ],
                ],
            ],
        ];

        return $navGroups;
    }
}
