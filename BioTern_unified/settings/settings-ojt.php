<?php
require_once dirname(__DIR__) . '/config/db.php';
$page_title = 'OJT Settings';
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
                                <a class="nav-link active" href="settings-ojt.php">
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
                                <a class="nav-link" href="settings-students.php">
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
                                    <label class="form-label">Default status</label>
                                    <select class="form-select" data-select2-selector="tag">
                                        <option value="" data-bg="bg-dark" selected>New</option>
                                        <option value="" data-bg="bg-primary">Working</option>
                                        <option value="" data-bg="bg-danger">Proposed</option>
                                        <option value="" data-bg="bg-success">Contacted</option>
                                        <option value="" data-bg="bg-warning">Qualified</option>
                                    </select>
                                    <small class="form-text text-muted">Default status [Ex: Auto/Testing/Completed]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Default source</label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="primary" data-icon="feather-facebook">Facebook</option>
                                        <option value="teal" data-icon="feather-twitter">Twitter</option>
                                        <option value="teal" data-icon="feather-instagram">Instagram</option>
                                        <option value="teal" data-icon="feather-linkedin">Linkedin</option>
                                        <option value="teal" data-icon="feather-github">Github</option>
                                        <option value="teal" data-icon="feather-command">Others</option>
                                    </select>
                                    <small class="form-text text-muted">Default Priority [Ex: Facebook/Google/Others]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Default Priority </label>
                                    <select class="form-select" data-select2-selector="priority">
                                        <option value="primary" data-bg="bg-primary">Low</option>
                                        <option value="teal" data-bg="bg-teal">Medium</option>
                                        <option value="success" data-bg="bg-success">Updates</option>
                                        <option value="warning" data-bg="bg-warning">High</option>
                                        <option value="danger" data-bg="bg-danger">Urgent</option>
                                    </select>
                                    <small class="form-text text-muted">Default Priority [Ex: Low/Medium/High/Urgent]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Auto assign as admin to customer after convert</label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Auto assign as admin to customer after convert [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to import OJT </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to import OJT [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Do not allow OJT to be edited after they are converted to Students(administrators not applied)</label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Do not allow OJT to be edited after they are converted to Students(administrators not applied) [Ex: Yes/No]</small>
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


