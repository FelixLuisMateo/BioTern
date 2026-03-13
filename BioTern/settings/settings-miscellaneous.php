<?php
$page_title = 'Miscellaneous Settings';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">

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
                                <a class="nav-link" href="settings-students.php">
                                    <i class="feather-users"></i>
                                    <span>Students</span>
                                </a>
                            </li>


                            <li class="nav-item">
                                <a class="nav-link active" href="settings-miscellaneous.php">
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
                                    <label class="form-label">Require client to be logged in to view contract </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Require client to be logged in to view contract [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Show setup menu item only when hover with mouse on main sidebar area </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Show setup menu item only when hover with mouse on main sidebar area [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Show help menu item on setup menu </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Show help menu item on setup menu [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to create Lead Status in Lead create/edit area? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to create Lead Status in Lead create/edit area? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to create Lead Source in Lead create/edit area? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to create Lead Source in Lead create/edit area? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to create Customer Group in Customer create/edit area? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to create Customer Group in Customer create/edit area? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to create Service in Ticket create/edit area? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to create Service in Ticket create/edit area? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to save predefined replies from ticket message </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to save predefined replies from ticket message [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to create Contract type in Contract create/edit area? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to create Contract type in Contract create/edit area? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to create Expense Category in Expense create/edit area? </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to create Expense Category in Expense create/edit area? [Ex: Yes/No]</small>
                                </div>
                                <hr class="my-5">
                                <div class="mb-5">
                                    <h4 class="fw-bold">Pusher.com</h4>
                                    <div class="fs-12 text-muted">Pusher notification setup</div>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">App ID</label>
                                    <input type="text" class="form-control" placeholder="App ID">
                                    <small class="form-text text-muted">App ID [Ex: theme_ocean]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">App key</label>
                                    <input type="text" class="form-control" placeholder="App key">
                                    <small class="form-text text-muted">App key [Ex: G-theme_ocean-2023]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">App Secret</label>
                                    <input type="text" class="form-control" placeholder="App Secret">
                                    <small class="form-text text-muted">App Secret [Ex: 25DFSDDSF584DSF5245DFSF575]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Cluster</label>
                                    <input type="text" class="form-control" placeholder="Cluster">
                                    <small class="form-text text-muted">Cluster https://pusher.com/docs/clusters</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Enable Real Time Notifications </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Enable Real Time Notifications [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label"> Enable Desktop Notifications </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Enable Desktop Notifications [Ex: Yes/No]</small>
                                </div>
                                <hr class="my-5">
                                <div class="mb-5">
                                    <h4 class="fw-bold">E-Sign</h4>
                                    <div class="fs-12 text-muted">E-Sign setup</div>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Require digital signature and identity confirmation on accept </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success" selected>Yes</option>
                                        <option value="" data-icon="feather-x text-danger">No</option>
                                    </select>
                                    <small class="form-text text-muted">Require digital signature and identity confirmation on accept [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Require digital signature and identity confirmation on accept </label>
                                    <select class="form-select" data-select2-selector="icon">
                                        <option value="" data-icon="feather-check text-success">Yes</option>
                                        <option value="" data-icon="feather-x text-danger" selected>No</option>
                                    </select>
                                    <small class="form-text text-muted">Require digital signature and identity confirmation on accept [Ex: Yes/No]</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ Footer ] start -->
                    <footer class="footer">
                        <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                            <span>Copyright &copy; <span class="app-current-year"><?php echo date('Y'); ?></span></span>
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
            </div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>






