<?php
require_once dirname(__DIR__) . '/config/db.php';
$page_title = 'StudentsSettings';
$page_styles = ['assets/css/settings-customizer-like.css'];
include 'includes/header.php';
?>

<div class="main-content d-flex settings-theme-customizer">                <!-- [ Content Sidebar ] start -->
                <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
                    <div class="content-sidebar-header sticky-top hstack justify-content-between">
                        <h4 class="fw-bolder mb-0">Settings</h4>
                        <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                            <i class="feather-x"></i>
                        </a>
                    </div>
                    <div class="content-sidebar-body">
                        <ul class="nav flex-column nxl-content-sidebar-item">
                            <li class="nav-item">
                                <a class="nav-link" href="settings-general.php">
                                    <i class="feather-airplay"></i>
                                    <span>General</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-seo.php">
                                    <i class="feather-search"></i>
                                    <span>SEO</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-tags.php">
                                    <i class="feather-tag"></i>
                                    <span>Tags</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-email.php">
                                    <i class="feather-mail"></i>
                                    <span>Email</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-tasks.php">
                                    <i class="feather-check-circle"></i>
                                    <span>Tasks</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-ojt.php">
                                    <i class="feather-crosshair"></i>
                                    <span>Leads</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="settings-support.php">
                                    <i class="feather-life-buoy"></i>
                                    <span>Support</span>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link active" href="settings-students.php">
                                    <i class="feather-users"></i>
                                    <span>Students</span>
                                </a>
                            </li>


                            <li class="nav-item">
                                <a class="nav-link" href="settings-miscellaneous.php">
                                    <i class="feather-cast"></i>
                                    <span>Miscellaneous</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="theme-customizer.php">
                                    <i class="feather-settings"></i>
                                    <span>Theme Customizer</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- [ Content Sidebar  ] end -->
                <!-- [ Main Area  ] start -->
                <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                    <div class="content-area-header sticky-top">
                        <div class="page-header-left">
                            <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                                <i class="feather-align-left fs-24"></i>
                            </a>
                        </div>
                        <div class="page-header-right ms-auto">
                            <div class="d-flex align-items-center gap-3 page-header-right-items-wrapper">
                                <a href="javascript:void(0);" class="text-danger">Cancel</a>
                                <a href="javascript:void(0);" class="btn btn-primary successAlertMessage">
                                    <i class="feather-save me-2"></i>
                                    <span>Save Changes</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="content-area-body">
                        <div class="card mb-0">
                            <div class="card-body">
                                <div class="mb-5">
                                    <label class="form-label">Company field is required? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Company field is required? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Company requires the usage of the VAT Number field </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Company requires the usage of the VAT Number field [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow Studentsto register </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow Studentsto register [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow primary contact to manage other customer contacts </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow primary contact to manage other customer contacts [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Enable Honeypot spam validation </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Enable Honeypot spam validation [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Require registration confirmation from administrator after customer register </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Require registration confirmation from administrator after customer register [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label"> Contacts see only own files uploaded in customer area (files uploaded in customer profile) </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted"> Contacts see only own files uploaded in customer area (files uploaded in customer profile) [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow contacts to delete own files uploaded from Studentsarea </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow contacts to delete own files uploaded from Studentsarea [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label"> Use Knowledge Base </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted"> Use Knowledge Base [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow primary contact to view/edit billing & shipping details </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow primary contact to view/edit billing & shipping details [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow knowledge base to be viewed without registration </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow knowledge base to be viewed without registration [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Default contact permissions </label>
                                    <select class="form-select" data-select2-selector="default" multiple>
                                        <option value="" selected>Invoices</option>
                                        <option value="" selected>Estimates</option>
                                        <option value="" selected>Contracts</option>
                                        <option value="" selected>Proposals</option>
                                        <option value="" selected>Support</option>
                                        <option value="" selected>Projects</option>
                                    </select>
                                    <small class="form-text text-muted">Default contact permissions [Ex: USD/EUR/RUB]</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Customer Information Format (PDF and HTML)</label>
                                    <textarea class="form-control" cols="30" rows="10" placeholder="{company_name}
{street}
{city} {state}
{country_code} {zip_code}
{vat_number_with_label}"></textarea>
                                    <small class="form-text text-muted">Customer Information Format (PDF and HTML) [Ex: {company_name}, {customer_id}, {street}, {city}, {state}, {zip_code}, {country_code}, {country_name}, {phone}, {vat_number}, {vat_number_with_label}]</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ Footer ] start -->
                    <footer class="footer">
                        <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                            <span>Copyright ï¿½</span>
                            <script>
                                document.write(new Date().getFullYear());
                            </script>
                        </p>
                        <div class="d-flex align-items-center gap-4">
                            <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                            <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                            <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
                        </div>
                    </footer>
                    <!-- [ Footer ] end -->
                </div>
                <!-- [ Content Area ] end -->
            </div>
            <?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php'; ?>


