<?php

if (!function_exists('biotern_mobile_header_clean_title')) {
    function biotern_mobile_header_clean_title(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/^\s*BioTern\s*(\|\||[-:])\s*/i', '', $title);
        return trim((string)$title) !== '' ? trim((string)$title) : 'BioTern';
    }
}

if (!function_exists('biotern_mobile_header_icon_for_route')) {
    function biotern_mobile_header_icon_for_route(string $route): string
    {
        $route = strtolower(basename(parse_url($route, PHP_URL_PATH) ?: $route));
        $map = [
            'homepage.php' => 'feather-home',
            'analytics.php' => 'feather-bar-chart-2',
            'students.php' => 'feather-users',
            'applications-review.php' => 'feather-clipboard',
            'attendance.php' => 'feather-clock',
            'external-attendance.php' => 'feather-briefcase',
            'reports-dtr-manual-input.php' => 'feather-edit-3',
            'ojt.php' => 'feather-archive',
            'courses.php' => 'feather-book-open',
            'departments.php' => 'feather-briefcase',
            'sections.php' => 'feather-layers',
            'apps-chat.php' => 'feather-message-circle',
            'announcements.php' => 'feather-megaphone',
            'student-documents.php' => 'feather-file-text',
            'document_certificate.php' => 'feather-award',
            'profile-details.php' => 'feather-user',
            'account-settings.php' => 'feather-settings',
            'theme-customizer.php' => 'feather-droplet',
            'notifications.php' => 'feather-bell',
            'users.php' => 'feather-user-plus',
        ];

        if (isset($map[$route])) {
            return $map[$route];
        }
        if (strpos($route, 'report') !== false || strpos($route, 'logs') !== false) {
            return 'feather-bar-chart-2';
        }
        if (strpos($route, 'document') !== false) {
            return 'feather-file-text';
        }
        if (strpos($route, 'settings') !== false) {
            return 'feather-settings';
        }
        if (strpos($route, 'student') !== false) {
            return 'feather-user-check';
        }
        return 'feather-grid';
    }
}

if (!function_exists('biotern_render_mobile_page_header')) {
    function biotern_render_mobile_page_header(array $context = []): void
    {
        $title = biotern_mobile_header_clean_title((string)($context['title'] ?? 'BioTern'));
        $route = (string)($context['route'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
        $icon = (string)($context['icon'] ?? biotern_mobile_header_icon_for_route($route));
        ?>
        <header class="biotern-mobile-page-header" data-mobile-page-header aria-label="Page header">
            <div class="biotern-mobile-page-header-main">
                <span class="biotern-mobile-page-header-icon" aria-hidden="true">
                    <i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                </span>
                <div class="biotern-mobile-page-header-copy">
                    <p class="biotern-mobile-page-header-kicker">BioTern</p>
                    <h1 class="biotern-mobile-page-header-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>
            <div class="biotern-mobile-page-header-tools" aria-label="Page tools">
                <button type="button" class="biotern-mobile-page-header-tool is-search" data-mobile-header-search-toggle hidden aria-label="Search this page">
                    <i class="feather-search"></i>
                </button>
                <button type="button" class="biotern-mobile-page-header-tool is-actions" data-mobile-header-actions-toggle hidden aria-expanded="false" aria-label="Page actions">
                    <i class="feather-grid"></i>
                </button>
            </div>
            <div class="biotern-mobile-page-header-panel is-search" data-mobile-header-search-panel hidden></div>
            <div class="biotern-mobile-page-header-panel is-actions" data-mobile-header-actions-panel hidden></div>
        </header>
        <?php
    }
}
