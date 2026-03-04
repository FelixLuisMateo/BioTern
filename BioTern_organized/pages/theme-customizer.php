<?php
$page_title = 'BioTern || Theme Customizer';
include 'includes/header.php';
?>

<style>
    .theme-setting-card .card-header {
        border-bottom: 1px dashed var(--bs-border-color);
    }

    .theme-setting-card .option-row {
        padding: 14px;
        border: 1px solid var(--bs-border-color);
        border-radius: 10px;
        margin-bottom: 12px;
        background: var(--bs-light);
    }

    html.app-skin-dark .theme-setting-card .option-row {
        background: rgba(255, 255, 255, 0.04);
    }

    .theme-setting-card .btn-check + .btn {
        min-width: 110px;
    }
</style>

<div class="main-content d-flex">
    <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
        <div class="content-sidebar-header bg-white sticky-top hstack justify-content-between">
            <h4 class="fw-bolder mb-0">Settings</h4>
            <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                <i class="feather-x"></i>
            </a>
        </div>
        <div class="content-sidebar-body">
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="nav-item"><a class="nav-link" href="settings-general.php"><i class="feather-airplay"></i><span>General</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-seo.php"><i class="feather-search"></i><span>SEO</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-tags.php"><i class="feather-tag"></i><span>Tags</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-email.php"><i class="feather-mail"></i><span>Email</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-tasks.php"><i class="feather-check-circle"></i><span>Tasks</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-ojt.php"><i class="feather-crosshair"></i><span>Leads</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-support.php"><i class="feather-life-buoy"></i><span>Support</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-students.php"><i class="feather-users"></i><span>Students</span></a></li>
                <li class="nav-item"><a class="nav-link" href="settings-miscellaneous.php"><i class="feather-cast"></i><span>Miscellaneous</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="theme-customizer.php"><i class="feather-settings"></i><span>Theme Customizer</span></a></li>
            </ul>
        </div>
    </div>

    <div class="content-area" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header bg-white sticky-top">
            <div class="page-header-left">
                <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                    <i class="feather-align-left fs-24"></i>
                </a>
            </div>
            <div class="page-header-right ms-auto">
                <div class="d-flex align-items-center gap-3 page-header-right-items-wrapper">
                    <a href="javascript:void(0);" class="text-danger" id="theme-page-cancel-link">Cancel</a>
                    <a href="javascript:void(0);" class="btn btn-primary" id="theme-page-save-link">
                        <i class="feather-save me-2"></i>
                        <span>Save Changes</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="content-area-body">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-xl-8">
                            <div class="card theme-setting-card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Appearance</h5>
                    <span class="badge bg-soft-primary text-primary">Live Preview</span>
                </div>
                <div class="card-body">
                    <div class="option-row">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <label class="form-label mb-1">Color Mode</label>
                                <div class="fs-12 text-muted">Choose light or dark interface.</div>
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
                                <label class="form-label mb-1" for="theme-page-menu">Sidebar Mode</label>
                                <div class="fs-12 text-muted">Auto follows screen width behavior.</div>
                            </div>
                            <div style="min-width: 240px;">
                                <select id="theme-page-menu" class="form-select">
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
                                <div class="fs-12 text-muted">Apply typography across the interface.</div>
                            </div>
                            <div style="min-width: 240px;">
                                <select id="theme-page-font" class="form-select">
                                    <option value="default">Default (Inter)</option>
                                    <option value="app-font-family-inter">Inter</option>
                                    <option value="app-font-family-lato">Lato</option>
                                    <option value="app-font-family-rubik">Rubik</option>
                                    <option value="app-font-family-nunito">Nunito</option>
                                    <option value="app-font-family-roboto">Roboto</option>
                                    <option value="app-font-family-poppins">Poppins</option>
                                    <option value="app-font-family-open-sans">Open Sans</option>
                                    <option value="app-font-family-montserrat">Montserrat</option>
                                    <option value="app-font-family-source-sans-pro">Source Sans Pro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-2">
                        <button type="button" class="btn btn-primary" id="theme-page-save">Save Changes</button>
                        <button type="button" class="btn btn-light" id="theme-page-reset">Reset Defaults</button>
                    </div>
                </div>
            </div>

                            <div class="card theme-setting-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Independent Surfaces</h5>
                    <span class="badge bg-soft-success text-success">Separate Control</span>
                </div>
                <div class="card-body">
                    <div class="option-row">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <label class="form-label mb-1">Navigation Style</label>
                                <div class="fs-12 text-muted">Set sidebar color independently.</div>
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

                    <div class="option-row mb-0">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <label class="form-label mb-1">Header Style</label>
                                <div class="fs-12 text-muted">Modify top header independently from sidebar.</div>
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
                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            var navHidden = document.getElementById('theme-page-navigation');
            var navLight = document.getElementById('theme-page-navigation-light');
            var navDark = document.getElementById('theme-page-navigation-dark');
            var headerHidden = document.getElementById('theme-page-header');
            var headerLight = document.getElementById('theme-page-header-light');
            var headerDark = document.getElementById('theme-page-header-dark');

            function syncNavigationRadios() {
                if (!navHidden) return;
                if (navDark) navDark.checked = navHidden.value === 'dark';
                if (navLight) navLight.checked = navHidden.value !== 'dark';
            }

            function syncHeaderRadios() {
                if (!headerHidden) return;
                if (headerDark) headerDark.checked = headerHidden.value === 'dark';
                if (headerLight) headerLight.checked = headerHidden.value !== 'dark';
            }

            if (navLight && navHidden) {
                navLight.addEventListener('change', function () {
                    if (navLight.checked) navHidden.value = 'light';
                });
            }
            if (navDark && navHidden) {
                navDark.addEventListener('change', function () {
                    if (navDark.checked) navHidden.value = 'dark';
                });
            }

            if (headerLight && headerHidden) {
                headerLight.addEventListener('change', function () {
                    if (headerLight.checked) headerHidden.value = 'light';
                });
            }
            if (headerDark && headerHidden) {
                headerDark.addEventListener('change', function () {
                    if (headerDark.checked) headerHidden.value = 'dark';
                });
            }

            var observer = new MutationObserver(function () {
                syncNavigationRadios();
                syncHeaderRadios();
            });

            if (navHidden) {
                observer.observe(navHidden, { attributes: true, attributeFilter: ['value'] });
            }
            if (headerHidden) {
                observer.observe(headerHidden, { attributes: true, attributeFilter: ['value'] });
            }

            syncNavigationRadios();
            syncHeaderRadios();

            var saveLink = document.getElementById('theme-page-save-link');
            var cancelLink = document.getElementById('theme-page-cancel-link');
            var saveButton = document.getElementById('theme-page-save');
            var resetButton = document.getElementById('theme-page-reset');

            if (saveLink && saveButton) {
                saveLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    saveButton.click();
                });
            }

            if (cancelLink && resetButton) {
                cancelLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    resetButton.click();
                });
            }
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>
