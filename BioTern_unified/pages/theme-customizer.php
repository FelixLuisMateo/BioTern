<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = strtolower(trim((string) (
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    ''
)));

if (!isset($_SESSION['user_id']) || !in_array($current_role, ['admin', 'coordinator'], true)) {
    header('Location: ../homepage.php');
    exit;
}

$pageTitle = 'Theme Customizer';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.settings-shell {
    display: grid;
    grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
    gap: 24px;
}
.settings-sidebar,
.settings-panel,
.theme-control-card {
    background: rgba(19, 28, 51, 0.92);
    border: 1px solid rgba(138, 155, 188, 0.18);
    border-radius: 18px;
}
.settings-sidebar {
    padding: 20px;
    align-self: start;
}
.settings-panel {
    padding: 24px;
}
.settings-nav-title {
    font-size: 11px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: 16px;
}
.settings-nav {
    display: grid;
    gap: 10px;
}
.settings-nav a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    border: 1px solid rgba(138, 155, 188, 0.14);
    background: rgba(79, 70, 229, 0.06);
}
.settings-nav a.active {
    background: rgba(79, 70, 229, 0.14);
    border-color: rgba(79, 70, 229, 0.38);
    color: #fff;
}
html:not(.app-skin-dark) .settings-nav a.active {
    color: #1d4ed8;
}
.settings-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 24px;
}
.settings-hero h3 {
    margin: 0;
    font-size: 24px;
}
.settings-hero p {
    margin: 8px 0 0;
    color: var(--bs-secondary-color);
    max-width: 720px;
}
.settings-badge {
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.12);
    color: #7c9dff;
    font-weight: 600;
    white-space: nowrap;
}
.theme-actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}
.theme-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.theme-control-card {
    padding: 0;
    overflow: hidden;
}
.theme-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 20px 22px 0;
}
.theme-card-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
}
.theme-card-header p {
    margin: 8px 0 0;
    color: var(--bs-secondary-color);
}
.theme-card-body {
    padding: 18px 22px 22px;
}
.theme-option {
    padding: 16px;
    border: 1px solid rgba(138, 155, 188, 0.14);
    border-radius: 14px;
    background: rgba(79, 70, 229, 0.04);
}
.theme-option + .theme-option {
    margin-top: 14px;
}
.theme-option-copy {
    margin-bottom: 14px;
}
.theme-option-copy label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
}
.theme-option-copy p {
    margin: 0;
    color: var(--bs-secondary-color);
    font-size: 13px;
}
.theme-option-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.theme-option .form-select {
    min-height: 48px;
    border-radius: 12px;
    min-width: 240px;
}
.theme-control-card .btn-check + .btn {
    min-width: 108px;
    border-radius: 12px;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
}
.theme-side-note {
    display: grid;
    gap: 14px;
}
.theme-side-note-card {
    padding: 16px 18px;
    border-radius: 14px;
    border: 1px solid rgba(138, 155, 188, 0.14);
    background: rgba(79, 70, 229, 0.04);
}
.theme-side-note-card h5 {
    margin: 0 0 8px;
    font-size: 0.98rem;
    font-weight: 700;
}
.theme-side-note-card p {
    margin: 0;
    color: var(--bs-secondary-color);
    font-size: 13px;
}
html.app-skin-light .settings-sidebar,
html.app-skin-light .settings-panel,
html.app-skin-light .theme-control-card {
    background: #ffffff;
    border-color: rgba(71, 103, 255, 0.12);
    color: #1f2937;
}
html.app-skin-light .settings-nav-title,
html.app-skin-light .theme-option-copy label {
    color: #8a94a6;
}
html.app-skin-light .settings-nav a {
    background: #f7f9ff;
    border-color: rgba(71, 103, 255, 0.12);
    color: #334155;
}
html.app-skin-light .settings-nav a.active {
    background: rgba(82, 109, 254, 0.10);
    border-color: rgba(82, 109, 254, 0.30);
    color: #1d4ed8;
}
html.app-skin-light .settings-hero h3,
html.app-skin-light .theme-card-header h4,
html.app-skin-light .theme-side-note-card h5 {
    color: #0f172a;
}
html.app-skin-light .settings-hero p,
html.app-skin-light .theme-card-header p,
html.app-skin-light .theme-option-copy p,
html.app-skin-light .theme-side-note-card p {
    color: #64748b;
}
html.app-skin-light .settings-badge {
    background: rgba(82, 109, 254, 0.12);
    color: #4f46e5;
}
html.app-skin-light .theme-option,
html.app-skin-light .theme-side-note-card {
    background: #f7f9ff;
    border-color: rgba(71, 103, 255, 0.12);
}
html.app-skin-light .theme-option .form-select {
    background: #ffffff;
    border-color: rgba(148, 163, 184, 0.35);
    color: #0f172a;
}
html.app-skin-light .theme-option .form-select:focus {
    border-color: rgba(82, 109, 254, 0.45);
    box-shadow: 0 0 0 0.2rem rgba(82, 109, 254, 0.12);
}
html.app-skin-dark .settings-sidebar,
html.app-skin-dark .settings-panel,
html.app-skin-dark .theme-control-card {
    background: rgba(19, 28, 51, 0.92);
    color: #e5eefc;
}
html.app-skin-dark .settings-nav a {
    color: #dbe7ff;
}
html.app-skin-dark .theme-option .form-select {
    background: #131b33;
    border-color: rgba(138, 155, 188, 0.24);
    color: #ffffff;
}
@media (max-width: 1199.98px) {
    .settings-shell,
    .theme-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767.98px) {
    .settings-hero,
    .theme-option-row {
        flex-direction: column;
        align-items: stretch;
    }
    .theme-option .form-select,
    .theme-control-card .btn-check + .btn {
        min-width: 100%;
    }
    .theme-actions .btn {
        flex: 1 1 auto;
    }
}
</style>

<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Theme Customizer</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                <li class="breadcrumb-item">Tools</li>
                <li class="breadcrumb-item">Theme Customizer</li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="settings-shell">
            <aside class="settings-sidebar">
                <div class="settings-nav-title">Tools</div>
                <nav class="settings-nav">
                    <a href="../tools/import-students-excel.php"><span>Excel Import</span><i class="feather-arrow-right"></i></a>
                    <a href="../tools/import-sql.php"><span>Data Transfer</span><i class="feather-arrow-right"></i></a>
                    <a href="theme-customizer.php" class="active"><span>Theme Customizer</span><i class="feather-arrow-right"></i></a>
                </nav>
            </aside>

            <section class="settings-panel">
                <div class="settings-hero">
                    <div>
                        <h3>System Appearance</h3>
                        <p>Adjust the BioTern interface skin, sidebar mode, typography, and independent surface styling. Changes apply live and can be saved as your working defaults.</p>
                    </div>
                    <div class="settings-badge">Personal Preference</div>
                </div>

                <div class="theme-grid">
                    <div class="theme-control-card">
                        <div class="theme-card-header">
                            <div>
                                <h4>Core Appearance</h4>
                                <p>Choose the base theme, menu behavior, and font family for the whole workspace.</p>
                            </div>
                        </div>
                        <div class="theme-card-body">
                            <div class="theme-option">
                                <div class="theme-option-row">
                                    <div class="theme-option-copy">
                                        <label>Color Mode</label>
                                        <p>Switch between light and dark presentation.</p>
                                    </div>
                                    <div class="btn-group" role="group" aria-label="Color mode">
                                        <input class="btn-check" type="radio" name="theme-page-skin" id="theme-page-skin-light" value="light">
                                        <label class="btn btn-outline-primary" for="theme-page-skin-light">Light</label>
                                        <input class="btn-check" type="radio" name="theme-page-skin" id="theme-page-skin-dark" value="dark">
                                        <label class="btn btn-outline-primary" for="theme-page-skin-dark">Dark</label>
                                    </div>
                                </div>
                            </div>

                            <div class="theme-option">
                                <div class="theme-option-row">
                                    <div class="theme-option-copy">
                                        <label for="theme-page-menu">Sidebar Mode</label>
                                        <p>Auto keeps the adaptive menu behavior used across BioTern.</p>
                                    </div>
                                    <select id="theme-page-menu" class="form-select">
                                        <option value="auto">Auto</option>
                                        <option value="mini">Mini</option>
                                        <option value="expanded">Expanded</option>
                                    </select>
                                </div>
                            </div>

                            <div class="theme-option">
                                <div class="theme-option-row">
                                    <div class="theme-option-copy">
                                        <label for="theme-page-font">Font Family</label>
                                        <p>Apply typography changes across the interface without changing the page layout.</p>
                                    </div>
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
                    </div>

                    <div class="theme-control-card">
                        <div class="theme-card-header">
                            <div>
                                <h4>Independent Surfaces</h4>
                                <p>Control the sidebar and header separately when you want a mixed light or dark workspace.</p>
                            </div>
                        </div>
                        <div class="theme-card-body">
                            <div class="theme-option">
                                <div class="theme-option-row">
                                    <div class="theme-option-copy">
                                        <label>Navigation Style</label>
                                        <p>Choose whether the left navigation stays light or dark.</p>
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

                            <div class="theme-option">
                                <div class="theme-option-row">
                                    <div class="theme-option-copy">
                                        <label>Header Style</label>
                                        <p>Set the top header independently from the sidebar style.</p>
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

                            <div class="theme-side-note">
                                <div class="theme-side-note-card">
                                    <h5>Live Preview</h5>
                                    <p>The page updates immediately when you save, so you can verify the combination before leaving this screen.</p>
                                </div>
                                <div class="theme-side-note-card">
                                    <h5>Reset Defaults</h5>
                                    <p>Reset returns the interface to light mode, automatic menu sizing, default font, and light header and navigation styling.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="theme-actions">
                    <button type="button" class="btn btn-light" id="theme-page-reset">Reset Defaults</button>
                    <button type="button" class="btn btn-primary" id="theme-page-save">Save Changes</button>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="assets/js/theme-customizer-page-runtime.js"></script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
