<?php
require_once dirname(__DIR__) . '/config/db.php';

$page_title = 'BioTern || Notes';
$page_styles = [
    'assets/css/modules/apps/apps-notes-page.css',
];

$notes_script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$notes_unified_pos = stripos($notes_script_name, '/BioTern_unified/');
$notes_base_href = ($notes_unified_pos !== false)
    ? substr($notes_script_name, 0, $notes_unified_pos) . '/BioTern_unified/'
    : '/BioTern_unified/';
$notes_endpoint = $notes_base_href . 'notes.php';
$notes_user_id = (int)($_SESSION['user_id'] ?? 0);
$notes_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$notes_user_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$notes_is_student = ($notes_user_role === 'student');
$notes_workspace_kicker = $notes_is_student ? 'Student Notes' : 'Notes Workspace';
$notes_page_title = $notes_is_student ? 'My Notes' : 'Notes';
$notes_list_kicker = $notes_is_student ? 'Student Notes' : 'Your Notes';
$notes_list_title = $notes_is_student ? 'Recent notes and checklists' : 'Recent notes';
$notes_editor_empty_title = $notes_is_student ? 'Select a note to track your internship tasks.' : 'Select a note to start working.';
$notes_editor_empty_copy = $notes_is_student
    ? 'Create reminders, requirement checklists, or internship logs and keep them in one place.'
    : 'Create a fresh note or open one from the list to edit it here.';
$notes_create_first_label = $notes_is_student ? 'Create your first student note' : 'Create your first note';

include 'includes/header.php';
?>
<div
    class="app-notes-shell"
    data-notes-app
    data-notes-endpoint="<?php echo htmlspecialchars($notes_endpoint, ENT_QUOTES, 'UTF-8'); ?>"
    data-user-id="<?php echo (int)$notes_user_id; ?>"
    data-user-name="<?php echo htmlspecialchars($notes_user_name, ENT_QUOTES, 'UTF-8'); ?>"
    data-user-role="<?php echo htmlspecialchars($notes_user_role, ENT_QUOTES, 'UTF-8'); ?>"
    data-student-mode="<?php echo $notes_is_student ? '1' : '0'; ?>"
>
    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-3 col-xxl-2">
            <section class="card app-notes-sidebar-card">
                <div class="card-body">
                    <div class="app-notes-sidebar-head">
                        <span class="app-notes-kicker"><?php echo htmlspecialchars($notes_workspace_kicker, ENT_QUOTES, 'UTF-8'); ?></span>
                        <h2><?php echo htmlspecialchars($notes_page_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>

                    <div class="app-notes-create-menu" data-create-menu>
                        <button type="button" class="app-notes-primary-button" data-create-menu-toggle aria-expanded="false">
                            <i class="feather-plus"></i>
                            <span>New</span>
                            <i class="feather-chevron-down"></i>
                        </button>
                        <div class="app-notes-create-dropdown" data-create-menu-panel hidden>
                            <button type="button" class="app-notes-dropdown-item" data-create-note data-template="blank">Blank Note</button>
                            <?php if (!$notes_is_student): ?>
                            <button type="button" class="app-notes-dropdown-item" data-template="meeting">Meeting Notes</button>
                            <?php endif; ?>
                            <button type="button" class="app-notes-dropdown-item" data-template="requirements">Requirements Checklist</button>
                            <button type="button" class="app-notes-dropdown-item" data-template="internship-log">Internship Log</button>
                        </div>
                    </div>

                    <label class="app-notes-search" for="appNotesSearch">
                        <i class="feather-search"></i>
                        <input type="search" id="appNotesSearch" placeholder="Search notes" data-search-input>
                    </label>

                    <div class="app-notes-filter-group">
                        <button type="button" class="app-notes-filter-chip is-active" data-filter="active">
                            <span>Active</span>
                            <strong data-count-active>0</strong>
                        </button>
                        <?php if (!$notes_is_student): ?>
                        <button type="button" class="app-notes-filter-chip" data-filter="pinned">
                            <span>Pinned</span>
                            <strong data-count-pinned>0</strong>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="app-notes-filter-chip" data-filter="trash">
                            <span>Trash</span>
                            <strong data-count-trash>0</strong>
                        </button>
                    </div>

                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="card app-notes-list-card">
                <div class="card-body">
                    <div class="app-notes-list-head">
                        <div>
                            <span class="app-notes-kicker"><?php echo htmlspecialchars($notes_list_kicker, ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3><?php echo htmlspecialchars($notes_list_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                        <div class="app-notes-list-tools">
                            <div class="app-notes-list-meta">
                                <span data-visible-count>0 notes</span>
                            </div>
                            <?php if (!$notes_is_student): ?>
                            <label class="app-notes-sort-field">
                                <span class="visually-hidden">Sort notes</span>
                                <select class="form-select form-select-sm" data-sort-select>
                                    <option value="recent">Recent</option>
                                    <option value="title">Title</option>
                                </select>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="app-notes-list" data-notes-list></div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5 col-xxl-6">
            <section class="card app-notes-editor-card">
                <div class="card-body">
                    <div class="app-notes-editor-empty" data-editor-empty>
                        <span class="app-notes-kicker">Editor</span>
                        <h3><?php echo htmlspecialchars($notes_editor_empty_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($notes_editor_empty_copy, ENT_QUOTES, 'UTF-8'); ?></p>
                        <button type="button" class="app-notes-secondary-button" data-create-note>
                            <i class="feather-edit-3"></i>
                            <span><?php echo htmlspecialchars($notes_create_first_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                    </div>

                    <form class="app-notes-editor" data-editor-form hidden>
                        <div class="app-notes-editor-head">
                            <div class="app-notes-editor-title-wrap">
                                <span class="app-notes-kicker" data-editor-kicker>Active note</span>
                                <input
                                    type="text"
                                    class="app-notes-title-input"
                                    id="appNotesTitle"
                                    maxlength="255"
                                    placeholder="Untitled note"
                                    data-editor-title
                                >
                            </div>
                            <div class="app-notes-editor-actions">
                                <button type="button" class="app-notes-icon-button" data-action="pin" aria-label="Pin note">
                                    <i class="feather-star"></i>
                                </button>
                                <?php if (!$notes_is_student): ?>
                                <button type="button" class="app-notes-icon-button" data-action="duplicate" aria-label="Duplicate note">
                                    <i class="feather-copy"></i>
                                </button>
                                <button type="button" class="app-notes-icon-button" data-action="archive" aria-label="Archive note">
                                    <i class="feather-archive"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="app-notes-icon-button d-none" data-action="restore" aria-label="Restore note">
                                    <i class="feather-rotate-ccw"></i>
                                </button>
                                <button type="button" class="app-notes-icon-button is-danger" data-action="delete" aria-label="Delete note">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </div>
                        </div>

                        <div class="app-notes-editor-toolbar">
                            <label class="app-notes-field">
                                <span>Category</span>
                                <select class="form-select" data-editor-category>
                                    <option value="internship">Internship</option>
                                    <?php if (!$notes_is_student): ?>
                                    <option value="meeting">Meeting</option>
                                    <?php endif; ?>
                                    <option value="requirement">Requirement</option>
                                    <?php if (!$notes_is_student): ?>
                                    <option value="reminder">Reminder</option>
                                    <?php endif; ?>
                                    <option value="personal">Personal</option>
                                </select>
                            </label>
                            <label class="app-notes-field">
                                <span>Format</span>
                                <select class="form-select" data-editor-type>
                                    <option value="text">Text note</option>
                                    <option value="checklist">Checklist</option>
                                </select>
                            </label>
                            <?php if (!$notes_is_student): ?>
                            <label class="app-notes-field app-notes-field-color">
                                <span>Accent</span>
                                <input type="color" class="form-control form-control-color" value="#2563eb" data-editor-color>
                            </label>
                            <?php endif; ?>
                            <div class="app-notes-editor-state">
                                <span class="app-notes-badge" data-editor-status>Saved</span>
                                <span class="app-notes-editor-time" data-editor-updated>Just now</span>
                            </div>
                        </div>

                        <label class="app-notes-field" data-editor-body-wrap>
                            <span>Body</span>
                            <textarea
                                class="app-notes-body-input"
                                id="appNotesBody"
                                placeholder="Write down what matters. This note saves automatically."
                                data-editor-body
                            ></textarea>
                        </label>

                        <div class="app-notes-checklist" data-checklist-wrap hidden>
                            <div class="app-notes-checklist-head">
                                <span>Checklist Items</span>
                                <button type="button" class="app-notes-secondary-button" data-checklist-add>
                                    <i class="feather-plus"></i>
                                    <span>Add Item</span>
                                </button>
                            </div>
                            <div class="app-notes-checklist-list" data-checklist-list></div>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="assets/js/modules/apps/apps-notes-page.js"></script>
<?php include 'includes/footer.php'; ?>



