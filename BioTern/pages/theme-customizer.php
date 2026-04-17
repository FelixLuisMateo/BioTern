<?php
$page_title = 'BioTern || Appearance';
$page_body_class = 'settings-page appearance-settings-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/settings/settings-shell.css',
    'assets/css/modules/settings/page-settings-suite.css',
    'assets/css/modules/settings/page-appearance-settings.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];
include 'includes/header.php';

$theme_scheme_options = function_exists('biotern_theme_registered_schemes')
    ? biotern_theme_registered_schemes()
    : [
        'blue' => 'Blue (Default)',
        'gray' => 'Gray',
    ];
if (!is_array($theme_scheme_options) || empty($theme_scheme_options)) {
    $theme_scheme_options = [
        'blue' => 'Blue (Default)',
        'gray' => 'Gray',
    ];
}

$theme_scheme_current_raw = (string)($biotern_theme_preferences['scheme'] ?? 'blue');
if (function_exists('biotern_theme_normalize_scheme')) {
    $theme_scheme_current = biotern_theme_normalize_scheme($theme_scheme_current_raw);
} else {
    $theme_scheme_current = strtolower(trim($theme_scheme_current_raw));
    $theme_scheme_current = preg_replace('/[^a-z0-9-]+/', '-', $theme_scheme_current);
    $theme_scheme_current = trim((string)$theme_scheme_current, '-');
    if ($theme_scheme_current === '') {
        $theme_scheme_current = 'blue';
    }
}

if (!array_key_exists($theme_scheme_current, $theme_scheme_options)) {
    if (function_exists('biotern_theme_scheme_label')) {
        $theme_scheme_options[$theme_scheme_current] = biotern_theme_scheme_label($theme_scheme_current) . ' (Custom)';
    } else {
        $theme_scheme_options[$theme_scheme_current] = ucwords(str_replace('-', ' ', $theme_scheme_current)) . ' (Custom)';
    }
}
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Appearance</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                    <li class="breadcrumb-item">Appearance</li>
                </ul>
            </div>
        </div>

        <div class="main-content settings-shell theme-customizer-page">
            <div class="settings-layout">
                <section class="settings-main">
                    <div class="appearance-grid">
                        <article class="card theme-setting-card appearance-card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="mb-0">Core Appearance</h5>
                                <span class="badge bg-soft-primary text-primary">Live Preview</span>
                            </div>
                            <div class="card-body">
                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div>
                                            <label class="form-label mb-1">Color Mode</label>
                                            <div class="fs-12 text-muted">Choose the default light or dark workspace.</div>
                                        </div>
                                        <div class="btn-group" role="group" aria-label="Color mode">
                                            <input class="btn-check" type="radio" name="theme-page-skin" id="theme-page-skin-light" value="light">
                                            <label class="btn btn-outline-primary" for="theme-page-skin-light">Light</label>
                                            <input class="btn-check" type="radio" name="theme-page-skin" id="theme-page-skin-dark" value="dark">
                                            <label class="btn btn-outline-primary" for="theme-page-skin-dark">Dark</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div>
                                            <label class="form-label mb-1" for="theme-page-scheme">Color Scheme</label>
                                            <div class="fs-12 text-muted">Swap the primary accent used across components.</div>
                                        </div>
                                        <div class="appearance-select-wrap">
                                            <select id="theme-page-scheme" class="form-select" data-ui-select="custom">
                                                <?php foreach ($theme_scheme_options as $theme_scheme_value => $theme_scheme_label): ?>
                                                    <?php
                                                    $theme_scheme_value = (string)$theme_scheme_value;
                                                    $theme_scheme_label = trim((string)$theme_scheme_label);
                                                    if ($theme_scheme_label === '') {
                                                        $theme_scheme_label = ucwords(str_replace('-', ' ', $theme_scheme_value));
                                                    }
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($theme_scheme_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $theme_scheme_value === $theme_scheme_current ? ' selected' : ''; ?>><?php echo htmlspecialchars($theme_scheme_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div>
                                            <label class="form-label mb-1" for="theme-page-menu">Sidebar Mode</label>
                                            <div class="fs-12 text-muted">Auto follows the responsive behavior of the dashboard.</div>
                                        </div>
                                        <div class="appearance-select-wrap">
                                            <select id="theme-page-menu" class="form-select" data-ui-select="custom">
                                                <option value="auto">Auto</option>
                                                <option value="mini">Mini</option>
                                                <option value="expanded">Expanded</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div>
                                            <label class="form-label mb-1" for="theme-page-font">Font Family</label>
                                            <div class="fs-12 text-muted">Apply typography changes across all settings and dashboards.</div>
                                        </div>
                                        <div class="appearance-select-wrap">
                                            <select id="theme-page-font" class="form-select" data-ui-select="custom">
                                                <option value="app-font-family-montserrat">Montserrat (Default)</option>
                                                <option value="app-font-family-inter">Inter</option>
                                                <option value="app-font-family-lato">Lato</option>
                                                <option value="app-font-family-rubik">Rubik</option>
                                                <option value="app-font-family-nunito">Nunito</option>
                                                <option value="app-font-family-roboto">Roboto</option>
                                                <option value="app-font-family-poppins">Poppins</option>
                                                <option value="app-font-family-open-sans">Open Sans</option>
                                                <option value="app-font-family-source-sans-pro">Source Sans Pro</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="appearance-actions-inline">
                                    <button type="button" class="btn btn-primary" id="theme-page-save">Save Changes</button>
                                    <button type="button" class="btn btn-light" id="theme-page-reset">Reset Defaults</button>
                                </div>
                            </div>
                        </article>

                        <article class="card theme-setting-card appearance-card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="mb-0">Independent Surfaces</h5>
                                <span class="badge bg-soft-success text-success">Advanced</span>
                            </div>
                            <div class="card-body" id="theme-page-surfaces-card">
                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div>
                                            <label class="form-label mb-1" for="theme-page-surfaces-independent">Surface Sync</label>
                                            <div class="fs-12 text-muted">When disabled, color mode controls both navigation and header.</div>
                                        </div>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="theme-page-surfaces-independent">
                                            <label class="form-check-label fs-12 fw-semibold ms-2" for="theme-page-surfaces-independent">Enable independent surfaces</label>
                                        </div>
                                        <input type="hidden" id="theme-page-surfaces" value="linked">
                                    </div>
                                </div>

                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" data-surface-option="independent-control">
                                        <div>
                                            <label class="form-label mb-1">Navigation Style</label>
                                            <div class="fs-12 text-muted">Control sidebar style separately from the page skin.</div>
                                        </div>
                                        <div class="btn-group" role="group" aria-label="Navigation style">
                                            <input class="btn-check" type="radio" name="theme-page-navigation-group" id="theme-page-navigation-light" value="light">
                                            <label class="btn btn-outline-primary" for="theme-page-navigation-light">Light</label>
                                            <input class="btn-check" type="radio" name="theme-page-navigation-group" id="theme-page-navigation-dark" value="dark">
                                            <label class="btn btn-outline-primary" for="theme-page-navigation-dark">Dark</label>
                                        </div>
                                        <input type="hidden" id="theme-page-navigation" value="light">
                                    </div>
                                </div>

                                <div class="option-row">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" data-surface-option="independent-control">
                                        <div>
                                            <label class="form-label mb-1">Header Style</label>
                                            <div class="fs-12 text-muted">Modify top header styling independently from the sidebar.</div>
                                        </div>
                                        <div class="btn-group" role="group" aria-label="Header style">
                                            <input class="btn-check" type="radio" name="theme-page-header-group" id="theme-page-header-light" value="light">
                                            <label class="btn btn-outline-primary" for="theme-page-header-light">Light</label>
                                            <input class="btn-check" type="radio" name="theme-page-header-group" id="theme-page-header-dark" value="dark">
                                            <label class="btn btn-outline-primary" for="theme-page-header-dark">Dark</label>
                                        </div>
                                        <input type="hidden" id="theme-page-header" value="light">
                                    </div>
                                </div>

                                <div class="appearance-note-grid">
                                    <div class="appearance-note-card">
                                        <h6 class="mb-1">Live Preview</h6>
                                        <p class="mb-0">Changes apply instantly so you can test combinations before leaving the page.</p>
                                    </div>
                                    <div class="appearance-note-card">
                                        <h6 class="mb-1">Default Reset</h6>
                                        <p class="mb-0">Reset returns to light mode, auto menu, default font, and linked surfaces.</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>





