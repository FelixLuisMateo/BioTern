<?php
$page_title = 'BioTern || Theme Customizer';
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">

<div class="main-content theme-customizer-page">
    <div class="content-area" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header sticky-top">
            <div class="page-header-left">
                <div class="d-flex flex-column gap-1">
                    <h5 class="mb-0">Theme Customizer</h5>
                    <div class="fs-12 text-muted">Fine-tune colors, navigation, and typography.</div>
                </div>
            </div>
        </div>

        <div class="content-area-body">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-12 col-xl-6">
                            <div class="card theme-setting-card">
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
                                <label class="form-label mb-1" for="theme-page-scheme">Color Scheme</label>
                                <div class="fs-12 text-muted">Swap the primary accent across the UI.</div>
                            </div>
                            <div class="theme-select-wrap">
                                <select id="theme-page-scheme" class="form-select">
                                    <option value="blue">Blue (Default)</option>
                                    <option value="gray">Gray</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="option-row">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <label class="form-label mb-1" for="theme-page-menu">Sidebar Mode</label>
                                <div class="fs-12 text-muted">Auto follows screen width behavior.</div>
                            </div>
                            <div class="theme-select-wrap">
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
                            <div class="theme-select-wrap">
                                <select id="theme-page-font" class="form-select">
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

                    <div class="d-flex gap-2 pt-2">
                        <button type="button" class="btn btn-light" id="theme-page-reset">Reset Defaults</button>
                        <div class="d-flex align-items-center text-muted fs-12">Changes save automatically.</div>
                    </div>
                </div>
            </div>

                        </div>
                        <div class="col-12 col-xl-6">
                            <div class="card theme-setting-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Independent Surfaces</h5>
                    <span class="badge bg-soft-success text-success">Separate Control</span>
                </div>
                <div class="card-body" id="theme-page-surfaces-card">
                    <div class="option-row">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <label class="form-label mb-1" for="theme-page-surfaces-independent">Surface Sync</label>
                                <div class="fs-12 text-muted">When disabled, light/dark mode controls both header and navigation.</div>
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
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" data-surface-option="independent-control">
                            <div>
                                <label class="form-label mb-1">Header Style</label>
                                <div class="fs-12 text-muted">Modify top + page header styling independently from sidebar.</div>
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

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>





