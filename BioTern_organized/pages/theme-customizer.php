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

<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Theme Customizer</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Settings</li>
            <li class="breadcrumb-item">Theme Customizer</li>
        </ul>
    </div>
</div>

<div class="main-content">
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
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>
