<?php

if (!function_exists('biotern_build_bottom_nav_groups')) {
    function biotern_build_bottom_nav_groups(array $context = []): array
    {
        $canInternship = !empty($context['can_internship']);
        $canAcademic = !empty($context['can_academic']);
        $canWorkspace = !empty($context['can_workspace']);
        $canSystem = !empty($context['can_system']);
        $isStudent = !empty($context['is_student']);
        $studentHasExternal = !empty($context['student_external']);

        $profileAvatar = isset($context['profile_avatar']) && is_string($context['profile_avatar']) && trim($context['profile_avatar']) !== ''
            ? trim($context['profile_avatar'])
            : 'assets/images/avatar/1.png';

        $navGroups = [];

        $homeRoutes = $isStudent ? ['homepage.php'] : ['homepage.php', 'analytics.php'];
        $homeItems = [
            ['label' => 'Overview', 'href' => 'homepage.php', 'icon' => 'feather-grid'],
        ];
        if (!$isStudent) {
            $homeItems[] = ['label' => 'Analytics', 'href' => 'analytics.php', 'icon' => 'feather-bar-chart-2'];
        }

        $navGroups[] = [
            'key' => 'home',
            'label' => $isStudent ? 'Dashboard' : 'Home',
            'icon' => 'feather-home',
            'routes' => $homeRoutes,
            'sections' => [
                [
                    'title' => 'Dashboard',
                    'items' => $homeItems,
                ],
            ],
        ];

        if ($isStudent) {
            $studentDtrItems = [
                ['label' => 'My Internal DTR', 'href' => 'student-internal-dtr.php', 'icon' => 'feather-clock'],
                ['label' => 'Manual Internal DTR', 'href' => 'student-manual-dtr.php', 'icon' => 'feather-edit-3'],
            ];

            if ($studentHasExternal) {
                $studentDtrItems[] = ['label' => 'My External DTR', 'href' => 'external-biometric.php', 'icon' => 'feather-briefcase'];
            }

            $navGroups[] = [
                'key' => 'student',
                'label' => 'Student',
                'icon' => 'feather-user-check',
                'routes' => [
                    'student-profile.php',
                    'student-dtr.php', 'student-internal-dtr.php',
                    'student-external-dtr.php', 'external-biometric.php',
                    'student-manual-dtr.php',
                    'student-documents.php',
                ],
                'sections' => [
                    [
                        'title' => 'My Profile',
                        'items' => [
                            ['label' => 'My Profile', 'href' => 'student-profile.php', 'icon' => 'feather-user'],
                        ],
                    ],
                    [
                        'title' => 'DTR',
                        'items' => $studentDtrItems,
                    ],
                    [
                        'title' => 'Documents',
                        'items' => [
                            ['label' => 'My Documents', 'href' => 'student-documents.php', 'icon' => 'feather-file-text'],
                        ],
                    ],
                ],
            ];

            $navGroups[] = [
                'key' => 'student-documents',
                'label' => 'Documents',
                'icon' => 'feather-file-text',
                'routes' => ['student-documents.php', 'document_application.php', 'document_endorsement.php', 'document_moa.php', 'document_dau_moa.php', 'document_parent_consent.php', 'generate_resume.php'],
                'sections' => [
                    [
                        'title' => 'Documents',
                        'items' => [
                            ['label' => 'My Documents', 'href' => 'student-documents.php', 'icon' => 'feather-file-text'],
                        ],
                    ],
                ],
            ];
        }

        if ($canInternship) {
            $navGroups[] = [
                'key' => 'internship',
                'label' => 'Internship',
                'icon' => 'feather-briefcase',
                'routes' => [
                    'students.php', 'students-create.php', 'students-edit.php', 'students-view.php', 'students-dtr.php', 'students-internal-dtr.php',
                    'applications-review.php', 'attendance.php', 'external-attendance.php', 'attendance-corrections.php', 'print_attendance.php', 'external-biometric.php',
                    'fingerprint_mapping.php', 'biometric-machine.php', 'biometric_machine_sync.php',
                    'ojt.php', 'ojt-create.php', 'ojt-edit.php', 'ojt-view.php', 'ojt-workflow-board.php',
                    'import-ojt-internal.php', 'import-ojt-external.php',
                    'reports-student-status.php', 'reports-attendance-dtr.php', 'reports-hours-completion.php',
                    'reports-section.php', 'reports-department.php', 'reports-company.php', 'reports-evaluation.php',
                    'reports-unassigned-students.php', 'reports-document.php',
                    'document_application.php', 'document_endorsement.php', 'document_moa.php', 'document_dau_moa.php', 'document_parent_consent.php',
                ],
                'sections' => [
                    [
                        'title' => 'Student Management',
                        'items' => [
                            ['label' => 'Students List', 'href' => 'students.php', 'icon' => 'feather-users'],
                            ['label' => 'Applications Review', 'href' => 'applications-review.php', 'icon' => 'feather-clipboard'],
                            ['label' => 'Internal DTR', 'href' => 'attendance.php', 'icon' => 'feather-clock'],
                            ['label' => 'External DTR', 'href' => 'external-attendance.php', 'icon' => 'feather-briefcase'],
                            ['label' => 'Fingerprint Mapping', 'href' => 'fingerprint_mapping.php', 'icon' => 'feather-link'],
                            ['label' => 'F20H Machine Manager', 'href' => 'biometric-machine.php', 'icon' => 'feather-cpu'],
                            ['label' => 'Sync Biometric Machine', 'href' => 'biometric_machine_sync.php?redirect=biometric-machine.php', 'icon' => 'feather-refresh-cw'],
                        ],
                    ],
                    [
                        'title' => 'OJT Management',
                        'items' => [
                            ['label' => 'OJT List', 'href' => 'ojt.php', 'icon' => 'feather-archive'],
                            ['label' => 'OJT Create', 'href' => 'ojt-create.php', 'icon' => 'feather-plus-circle'],
                            ['label' => 'Import OJT Internal', 'href' => 'import-ojt-internal.php', 'icon' => 'feather-upload'],
                            ['label' => 'Import OJT External', 'href' => 'import-ojt-external.php', 'icon' => 'feather-upload-cloud'],
                        ],
                    ],
                    [
                        'title' => 'Documents',
                        'items' => [
                            ['label' => 'Application', 'href' => 'document_application.php', 'icon' => 'feather-file-text'],
                            ['label' => 'Endorsement', 'href' => 'document_endorsement.php', 'icon' => 'feather-file-text'],
                            ['label' => 'MOA', 'href' => 'document_moa.php', 'icon' => 'feather-file-text'],
                            ['label' => 'DAU MOA', 'href' => 'document_dau_moa.php', 'icon' => 'feather-file-text'],
                            ['label' => 'Parent Consent', 'href' => 'document_parent_consent.php', 'icon' => 'feather-file-text'],
                        ],
                    ],
                    [
                        'title' => 'Reports',
                        'items' => [
                            ...($canSystem ? [
                                ['label' => 'Student Status Report', 'href' => 'reports-student-status.php', 'icon' => 'feather-users'],
                                ['label' => 'Attendance Report (DTR)', 'href' => 'reports-attendance-dtr.php', 'icon' => 'feather-clock'],
                                ['label' => 'Hours Completion Report', 'href' => 'reports-hours-completion.php', 'icon' => 'feather-bar-chart-2'],
                                ['label' => 'Section Report', 'href' => 'reports-section.php', 'icon' => 'feather-layers'],
                                ['label' => 'Department Report', 'href' => 'reports-department.php', 'icon' => 'feather-briefcase'],
                                ['label' => 'Company Report', 'href' => 'reports-company.php', 'icon' => 'feather-map-pin'],
                                ['label' => 'Evaluation Report', 'href' => 'reports-evaluation.php', 'icon' => 'feather-star'],
                                ['label' => 'Unassigned Students Report', 'href' => 'reports-unassigned-students.php', 'icon' => 'feather-user-x'],
                                ['label' => 'Document Report', 'href' => 'reports-document.php', 'icon' => 'feather-file-text'],
                            ] : []),
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
                ],
                'sections' => [
                    [
                        'title' => 'Academic Setup',
                        'items' => [
                            ['label' => 'Courses', 'href' => 'courses.php', 'icon' => 'feather-book-open'],
                            ['label' => 'Departments', 'href' => 'departments.php', 'icon' => 'feather-briefcase'],
                            ['label' => 'Sections', 'href' => 'sections.php', 'icon' => 'feather-layers'],
                        ],
                    ],
                ],
            ];
        }

        if ($canWorkspace) {
            $workspaceItems = [
                ['label' => 'Chat', 'href' => 'apps-chat.php', 'icon' => 'feather-message-circle'],
                ['label' => 'Email', 'href' => 'apps-email.php', 'icon' => 'feather-mail'],
                ['label' => 'Notes', 'href' => 'apps-notes.php', 'icon' => 'feather-edit-2'],
                ['label' => 'Storage', 'href' => 'apps-storage.php', 'icon' => 'feather-hard-drive'],
                ['label' => 'Calendar', 'href' => 'apps-calendar.php', 'icon' => 'feather-calendar'],
            ];
            $workspaceRoutes = [
                'apps-chat.php', 'apps-email.php', 'apps-notes.php', 'apps-storage.php', 'apps-calendar.php',
            ];

            if ($isStudent) {
                $workspaceItems = [
                    ['label' => 'Chat', 'href' => 'apps-chat.php', 'icon' => 'feather-message-circle'],
                    ['label' => 'Email', 'href' => 'apps-email.php', 'icon' => 'feather-mail'],
                ];
                $workspaceRoutes = ['apps-chat.php', 'apps-email.php'];
            }

            $navGroups[] = [
                'key' => 'workspace',
                'label' => 'Workspace',
                'icon' => 'feather-layers',
                'routes' => $workspaceRoutes,
                'sections' => [
                    [
                        'title' => 'Applications',
                        'items' => $workspaceItems,
                    ],
                ],
            ];
        }

        if ($isStudent) {
            $navGroups[] = [
                'key' => 'student-settings',
                'label' => 'My Settings',
                'icon' => 'feather-settings',
                'routes' => ['notifications.php', 'account-settings.php'],
                'sections' => [
                    [
                        'title' => 'Settings',
                        'items' => [
                            ['label' => 'Notifications', 'href' => 'notifications.php', 'icon' => 'feather-bell'],
                            ['label' => 'Account Settings', 'href' => 'account-settings.php', 'icon' => 'feather-user'],
                        ],
                    ],
                ],
            ];

            $navGroups[] = [
                'key' => 'student-tools',
                'label' => 'Student Tools',
                'icon' => 'feather-tool',
                'routes' => ['apps-notes.php', 'apps-storage.php', 'apps-calendar.php', 'theme-customizer.php'],
                'sections' => [
                    [
                        'title' => 'Tools',
                        'items' => [
                            ['label' => 'Notes', 'href' => 'apps-notes.php', 'icon' => 'feather-edit-2'],
                            ['label' => 'Storage', 'href' => 'apps-storage.php', 'icon' => 'feather-hard-drive'],
                            ['label' => 'Calendar', 'href' => 'apps-calendar.php', 'icon' => 'feather-calendar'],
                            ['label' => 'Appearance', 'href' => 'theme-customizer.php', 'icon' => 'feather-droplet'],
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
                    'users.php', 'create_admin.php',
                    'coordinators.php', 'coordinators-create.php', 'coordinators-edit.php',
                    'supervisors.php', 'supervisors-create.php', 'supervisors-edit.php',
                    'settings-general.php', 'settings-email.php', 'settings-ojt.php', 'settings-students.php',
                    'settings-support.php',
                    'theme-customizer.php', 'import-students-excel.php', 'import-sql.php',
                    'reports-admin-logs.php', 'reports-login-logs.php', 'reports-chat-logs.php', 'reports-chat-reports.php', 'reports-chat-penalties.php',
                    'reports-import-errors.php', 'reports-dtr-manual-input.php',
                ],
                'sections' => [
                    [
                        'title' => 'Users',
                        'items' => [
                            ['label' => 'Users', 'href' => 'users.php', 'icon' => 'feather-users'],
                            ['label' => 'Create Admin', 'href' => 'create_admin.php', 'icon' => 'feather-shield'],
                            ['label' => 'Coordinators', 'href' => 'coordinators.php', 'icon' => 'feather-user-check'],
                            ['label' => 'Supervisors', 'href' => 'supervisors.php', 'icon' => 'feather-user'],
                        ],
                    ],
                    [
                        'title' => 'Settings',
                        'items' => [
                            ['label' => 'General', 'href' => 'settings-general.php', 'icon' => 'feather-settings'],
                            ['label' => 'Email', 'href' => 'settings-email.php', 'icon' => 'feather-mail'],
                            ['label' => 'OJT Settings', 'href' => 'settings-ojt.php', 'icon' => 'feather-briefcase'],
                            ['label' => 'Student Settings', 'href' => 'settings-students.php', 'icon' => 'feather-users'],
                            ['label' => 'Support', 'href' => 'settings-support.php', 'icon' => 'feather-life-buoy'],
                            ['label' => 'Notifications', 'href' => 'notifications.php', 'icon' => 'feather-bell'],
                            ['label' => 'Account Settings', 'href' => 'account-settings.php', 'icon' => 'feather-user'],
                        ],
                    ],
                    [
                        'title' => 'Logs & Audit',
                        'items' => [
                            ['label' => 'Admin Logs', 'href' => 'reports-admin-logs.php', 'icon' => 'feather-shield'],
                            ['label' => 'Login Logs', 'href' => 'reports-login-logs.php', 'icon' => 'feather-log-in'],
                            ['label' => 'Chat Logs', 'href' => 'reports-chat-logs.php', 'icon' => 'feather-message-circle'],
                            ['label' => 'Reported Chats', 'href' => 'reports-chat-reports.php', 'icon' => 'feather-flag'],
                            ['label' => 'Chat Penalties', 'href' => 'reports-chat-penalties.php', 'icon' => 'feather-slash'],
                        ],
                    ],
                    [
                        'title' => 'Tools',
                        'items' => [
                            ['label' => 'Excel Import', 'href' => 'import-students-excel.php', 'icon' => 'feather-upload'],
                            ['label' => 'Import Error Report', 'href' => 'reports-import-errors.php', 'icon' => 'feather-alert-triangle'],
                            ['label' => 'Manual DTR Input', 'href' => 'reports-dtr-manual-input.php', 'icon' => 'feather-edit'],
                            ['label' => 'Data Transfer', 'href' => 'import-sql.php', 'icon' => 'feather-database'],
                            ['label' => 'Appearance', 'href' => 'theme-customizer.php', 'icon' => 'feather-droplet'],
                        ],
                    ],
                ],
            ];
        }

        if (!$isStudent) {
            $navGroups[] = [
                'key' => 'profile',
                'label' => 'Profile',
                'icon' => 'feather-user',
                'routes' => ['account-settings.php', 'notifications.php'],
                'avatar' => $profileAvatar,
                'sections' => [
                    [
                        'title' => 'Account',
                        'items' => [
                            ['label' => 'Account Settings', 'href' => 'account-settings.php#security', 'icon' => 'feather-settings'],
                            ['label' => 'Notifications', 'href' => 'notifications.php', 'icon' => 'feather-bell'],
                            ['label' => 'Logout', 'href' => 'auth-login.php?logout=1', 'icon' => 'feather-log-out'],
                        ],
                    ],
                ],
            ];
        }

        return $navGroups;
    }
}
