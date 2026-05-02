<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';

biotern_boot_session(isset($conn) ? $conn : null);

$page_title = 'BioTern || Storage';
$page_styles = [
    'assets/css/modules/apps/apps-storage-page.css',
    'assets/css/modules/apps/apps-workspace-theme.css',
];
$page_scripts = [
    'assets/js/modules/apps/apps-storage-page.js',
];
$page_body_class = trim((string)($page_body_class ?? '') . ' apps-storage-page');

$storage_endpoint = 'storage_files.php';
$storage_user_id = (int)($_SESSION['user_id'] ?? 0);
$storage_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$storage_user_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$storage_can_manage_shared = false;
$storage_default_upload_category = $storage_user_role === 'student' ? 'requirements' : 'reports';
$storage_share_targets = [];

include 'includes/header.php';
?>
<main class="nxl-container apps-container">
    <div class="nxl-content">
        <div class="main-content apps-workspace-main">
            <div
                class="app-storage-shell"
                data-storage-app
                data-storage-endpoint="<?php echo htmlspecialchars($storage_endpoint, ENT_QUOTES, 'UTF-8'); ?>"
                data-user-id="<?php echo (int)$storage_user_id; ?>"
                data-user-name="<?php echo htmlspecialchars($storage_user_name, ENT_QUOTES, 'UTF-8'); ?>"
                data-user-role="<?php echo htmlspecialchars($storage_user_role, ENT_QUOTES, 'UTF-8'); ?>"
                data-can-manage-shared="<?php echo $storage_can_manage_shared ? '1' : '0'; ?>"
                data-default-upload-category="<?php echo htmlspecialchars($storage_default_upload_category, ENT_QUOTES, 'UTF-8'); ?>"
                data-start-upload-category="<?php echo htmlspecialchars(trim((string)($_GET['upload_category'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                data-start-upload-title="<?php echo htmlspecialchars(trim((string)($_GET['upload_title'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                data-start-upload-notes="<?php echo htmlspecialchars(trim((string)($_GET['upload_notes'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
            >
                <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-3">
            <section class="card app-storage-sidebar-card">
                <div class="card-body">
                    <div class="app-storage-sidebar-head">
                        <span class="app-storage-kicker">File Hub</span>
                        <h2>Storage</h2>
                    </div>

                    <button type="button" class="app-storage-primary-button" data-open-upload>
                        <i class="feather-upload-cloud"></i>
                        <span>Upload File</span>
                    </button>

                    <?php if ($storage_user_role === 'student'): ?>
                    <div class="app-storage-shortcuts-card">
                        <span class="app-storage-kicker">Student Shortcuts</span>
                        <div class="app-storage-shortcut-list">
                            <button type="button" class="app-storage-shortcut" data-shortcut-category="requirements">Requirement Files</button>
                            <button type="button" class="app-storage-shortcut" data-shortcut-category="generated">Generated Documents</button>
                            <button type="button" class="app-storage-shortcut" data-shortcut-category="internship">Internship Files</button>
                        </div>
                    </div>

                    <div class="app-storage-checklist-card">
                        <span class="app-storage-kicker">Document Checklist</span>
                        <div class="app-storage-checklist" data-student-checklist></div>
                    </div>
                    <?php endif; ?>

                    <label class="app-storage-search" for="appStorageSearch">
                        <i class="feather-search"></i>
                        <input type="search" id="appStorageSearch" placeholder="Search files" data-search-input>
                    </label>

                    <div class="app-storage-filter-group">
                        <button type="button" class="app-storage-filter-chip is-active" data-scope-filter="all">
                            <span>All Files</span>
                            <strong data-count-all>0</strong>
                        </button>
                        <button type="button" class="app-storage-filter-chip" data-scope-filter="my">
                            <span>My Files</span>
                            <strong data-count-my>0</strong>
                        </button>
                        <button type="button" class="app-storage-filter-chip" data-scope-filter="starred">
                            <span>Starred</span>
                            <strong data-count-starred>0</strong>
                        </button>
                        <button type="button" class="app-storage-filter-chip" data-scope-filter="trash">
                            <span>Trash</span>
                            <strong data-count-trash>0</strong>
                        </button>
                    </div>

                    <label class="app-storage-category-select-wrap">
                        <span class="app-storage-kicker">Category</span>
                        <select class="form-select app-storage-category-select" data-category-select>
                            <option value="all">All Categories</option>
                            <?php if ($storage_user_role === 'student'): ?>
                            <option value="requirements">Requirements</option>
                            <?php endif; ?>
                            <option value="generated">Generated Docs</option>
                            <option value="internship">Internship</option>
                            <option value="images">Images</option>
                            <option value="reports">Reports</option>
                            <option value="other">Other</option>
                        </select>
                    </label>

                    <div class="app-storage-helper-card">
                        <span class="app-storage-kicker">Access</span>
                        <p>
                            This is your personal file hub. Files uploaded here are only visible to your account.
                        </p>
                        <div class="app-storage-resource-list">
                            <button type="button" class="app-storage-resource-tag" data-shortcut-category="generated">Forms</button>
                            <button type="button" class="app-storage-resource-tag" data-shortcut-category="reports">Policies</button>
                            <button type="button" class="app-storage-resource-tag" data-shortcut-category="generated">Templates</button>
                        </div>
                    </div>

                    <div class="app-storage-activity-card">
                        <span class="app-storage-kicker">Recent Activity</span>
                        <div class="app-storage-activity-list" data-storage-activity></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="card app-storage-list-card">
                <div class="card-body">
                    <div class="app-storage-list-head">
                        <div>
                            <span class="app-storage-kicker">Documents</span>
                            <h3>Files</h3>
                        </div>
                        <div class="app-storage-list-tools">
                            <span class="app-storage-list-meta" data-visible-count>0 files</span>
                            <label class="app-storage-sort-field">
                                <span class="visually-hidden">Sort files</span>
                                <select class="form-select form-select-sm" data-sort-select>
                                    <option value="recent">Recent</option>
                                    <option value="name">Name</option>
                                    <option value="size">Size</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="app-storage-bulkbar" data-bulkbar hidden>
                        <label class="app-storage-bulk-select-all">
                            <input type="checkbox" data-bulk-toggle-all>
                            <span data-bulk-count>0 selected</span>
                        </label>
                        <div class="app-storage-bulk-actions">
                            <button type="button" class="app-storage-secondary-button" data-bulk-delete>Delete Selected</button>
                            <button type="button" class="app-storage-secondary-button" data-bulk-restore hidden>Restore Selected</button>
                        </div>
                    </div>

                    <div class="app-storage-list" data-file-list></div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-3">
            <section class="card app-storage-details-card">
                <div class="card-body">
                    <div class="app-storage-details-empty" data-details-empty>
                        <span class="app-storage-kicker">Details</span>
                        <h3>Select a file</h3>
                        <p>Choose any file to see its category, uploader, size, and quick actions.</p>
                    </div>

                    <div class="app-storage-details" data-details-panel hidden></div>
                </div>
            </section>
        </div>
    </div>

    <div class="app-storage-upload-panel" data-upload-panel hidden>
        <div class="app-storage-upload-card">
            <div class="app-storage-upload-head">
                <div>
                    <span class="app-storage-kicker" data-upload-kicker>Upload file</span>
                    <h3 data-upload-title>Add Personal File</h3>
                </div>
                <button type="button" class="app-storage-icon-button" data-close-upload aria-label="Close upload panel">
                    <i class="feather-x"></i>
                </button>
            </div>

            <form class="app-storage-upload-form" data-upload-form>
                <input type="hidden" name="action" value="upload" data-upload-action>
                <input type="hidden" name="id" value="" data-upload-id>

                <label class="app-storage-field">
                    <span>File</span>
                    <div class="app-storage-dropzone" data-dropzone tabindex="0" role="button" aria-label="Choose a file or drag one here">
                        <input type="file" class="form-control app-storage-file-input" name="file" data-upload-file>
                        <strong data-dropzone-title>Choose a file</strong>
                        <small data-dropzone-copy>Drag and drop a document here, or click to browse.</small>
                    </div>
                </label>

                <label class="app-storage-field">
                    <span>Title</span>
                    <input type="text" class="form-control" name="title" maxlength="255" placeholder="Use a clearer document title" data-upload-title-input>
                </label>

                <label class="app-storage-field">
                    <span>Category</span>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($storage_default_upload_category, ENT_QUOTES, 'UTF-8'); ?>" data-upload-category>
                    <div class="app-storage-upload-category-options" data-upload-category-options>
                        <?php if ($storage_user_role === 'student'): ?>
                        <button type="button" class="app-storage-upload-category-choice<?php echo $storage_default_upload_category === 'requirements' ? ' is-active' : ''; ?>" data-upload-category-choice="requirements">Requirements</button>
                        <?php endif; ?>
                        <button type="button" class="app-storage-upload-category-choice" data-upload-category-choice="generated">Generated Docs</button>
                        <button type="button" class="app-storage-upload-category-choice" data-upload-category-choice="internship">Internship</button>
                        <button type="button" class="app-storage-upload-category-choice" data-upload-category-choice="images">Images</button>
                        <button type="button" class="app-storage-upload-category-choice<?php echo $storage_default_upload_category === 'reports' ? ' is-active' : ''; ?>" data-upload-category-choice="reports">Reports</button>
                        <button type="button" class="app-storage-upload-category-choice" data-upload-category-choice="other">Other</button>
                    </div>
                </label>
                <input type="hidden" name="scope" value="personal" data-upload-scope>

                <label class="app-storage-field">
                    <span>Notes</span>
                    <textarea class="form-control" name="notes" rows="4" placeholder="Add a short description or purpose for this file." data-upload-notes></textarea>
                </label>

                <div class="app-storage-upload-foot">
                    <span class="app-storage-upload-status" data-upload-status>PDF, images, Office files, and ZIP uploads are supported.</span>
                    <div class="app-storage-progress" data-upload-progress hidden>
                        <div class="app-storage-progress-bar" data-upload-progress-bar></div>
                    </div>
                    <div class="app-storage-upload-actions">
                        <button type="button" class="app-storage-secondary-button" data-close-upload>Cancel</button>
                        <button type="submit" class="app-storage-primary-button" data-upload-submit>Save File</button>
                    </div>
                </div>
            </form>
        </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
