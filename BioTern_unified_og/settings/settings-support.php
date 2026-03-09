<?php
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'biotern_db';

function support_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function support_pick($arr, $key, $default = '') {
    if (!is_array($arr)) {
        return $default;
    }
    return array_key_exists($key, $arr) ? (string)$arr[$key] : (string)$default;
}

function support_is_selected($current, $value) {
    return ((string)$current === (string)$value) ? ' selected' : '';
}

$support_defaults = [
    'default_reply_status' => 'answered',
    'default_piped_priority' => 'medium',
    'allowed_extensions' => '.jpg,.png,.pdf,.doc,.zip,.rar',
    'ticket_replies_order' => 'ascending',
    'staff_dept_only_access' => '1',
    'notify_assignee_only' => '0',
    'notify_new_ticket' => '1',
    'notify_customer_reply' => '0',
    'staff_open_all_contacts' => '1',
    'auto_assign_first_replier' => '0',
    'allow_non_staff_ticket_access' => '1',
    'allow_non_admin_delete_attachments' => '1',
    'allow_customer_change_status' => '1',
    'show_contact_tickets_only' => '1',
    'enable_support_badge' => '1',
    'pipe_registered_users_only' => '0',
    'email_replies_only' => '1',
    'import_actual_reply_only' => '0',
];

$support_settings = $support_defaults;
$support_success = '';
$support_error = '';

$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if (!$conn->connect_errno) {
    $has_table = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($has_table && $has_table->num_rows > 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $incoming = [];
            foreach ($support_defaults as $key => $default) {
                if (in_array($key, ['default_reply_status', 'default_piped_priority', 'ticket_replies_order'], true)) {
                    $incoming[$key] = trim((string)($_POST[$key] ?? $default));
                } else {
                    $incoming[$key] = isset($_POST[$key]) && (string)$_POST[$key] === '1' ? '1' : '0';
                }
            }

            $allowed_raw = isset($_POST['allowed_extensions']) && is_array($_POST['allowed_extensions']) ? $_POST['allowed_extensions'] : [];
            $allowed_clean = [];
            foreach ($allowed_raw as $ext) {
                $ext = strtolower(trim((string)$ext));
                if ($ext !== '' && preg_match('/^\.[a-z0-9]+$/', $ext)) {
                    $allowed_clean[] = $ext;
                }
            }
            $allowed_clean = array_values(array_unique($allowed_clean));
            $incoming['allowed_extensions'] = !empty($allowed_clean) ? implode(',', $allowed_clean) : $support_defaults['allowed_extensions'];

            $stmt = $conn->prepare("
                INSERT INTO system_settings (`key`, `value`, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()
            ");

            if ($stmt) {
                $ok = true;
                foreach ($incoming as $k => $v) {
                    $full_key = 'support.' . $k;
                    $stmt->bind_param('ss', $full_key, $v);
                    if (!$stmt->execute()) {
                        $ok = false;
                        $support_error = 'Failed to save support settings.';
                        break;
                    }
                }
                $stmt->close();
                if ($ok) {
                    $support_success = 'Support settings saved.';
                }
            } else {
                $support_error = 'Unable to save support settings.';
            }
        }

        $res = $conn->query("SELECT `key`, `value` FROM system_settings WHERE `key` LIKE 'support.%'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $raw_key = isset($row['key']) ? (string)$row['key'] : '';
                $short_key = str_replace('support.', '', $raw_key);
                if (array_key_exists($short_key, $support_defaults)) {
                    $support_settings[$short_key] = isset($row['value']) ? (string)$row['value'] : '';
                }
            }
            $res->close();
        }
    } else {
        $support_error = 'system_settings table not found in database.';
    }
    $conn->close();
} else {
    $support_error = 'Database connection failed for support settings.';
}

$selected_extensions = array_filter(array_map('trim', explode(',', support_pick($support_settings, 'allowed_extensions', ''))));

$page_title = 'Support Settings';
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
                                <a class="nav-link active" href="settings-support.php">
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
                                <a href="settings-support.php" class="text-danger">Cancel</a>
                                <button type="submit" form="supportSettingsForm" class="btn btn-primary successAlertMessage border-0">
                                    <i class="feather-save me-2"></i>
                                    <span>Save Changes</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="content-area-body">
                        <?php if ($support_success !== ''): ?>
                            <div class="alert alert-success" role="alert"><?php echo support_h($support_success); ?></div>
                        <?php endif; ?>
                        <?php if ($support_error !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?php echo support_h($support_error); ?></div>
                        <?php endif; ?>
                        <div class="card mb-0">
                            <form id="supportSettingsForm" method="post" action="settings-support.php">
                            <div class="card-body">
                                <div class="mb-5">
                                    <label class="form-label">Default status selected when replying to ticket</label>
                                    <select name="default_reply_status" class="form-select" data-select2-selector="status">
                                        <option value="open" data-bg="bg-dark"<?php echo support_is_selected(support_pick($support_settings, 'default_reply_status'), 'open'); ?>>Open</option>
                                        <option value="in_progress" data-bg="bg-primary"<?php echo support_is_selected(support_pick($support_settings, 'default_reply_status'), 'in_progress'); ?>>In Progress</option>
                                        <option value="answered" data-bg="bg-danger"<?php echo support_is_selected(support_pick($support_settings, 'default_reply_status'), 'answered'); ?>>Answered</option>
                                        <option value="on_hold" data-bg="bg-success"<?php echo support_is_selected(support_pick($support_settings, 'default_reply_status'), 'on_hold'); ?>>On Hold</option>
                                        <option value="closed" data-bg="bg-warning"<?php echo support_is_selected(support_pick($support_settings, 'default_reply_status'), 'closed'); ?>>Closed</option>
                                    </select>
                                    <small class="form-text text-muted">Default status selected when replying to ticket [Ex: Open/Closed/Answered]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Default priority on piped ticket</label>
                                    <select name="default_piped_priority" class="form-select" data-select2-selector="priority">
                                        <option value="low" data-bg="bg-dark"<?php echo support_is_selected(support_pick($support_settings, 'default_piped_priority'), 'low'); ?>>Low</option>
                                        <option value="medium" data-bg="bg-primary"<?php echo support_is_selected(support_pick($support_settings, 'default_piped_priority'), 'medium'); ?>>Medium</option>
                                        <option value="high" data-bg="bg-danger"<?php echo support_is_selected(support_pick($support_settings, 'default_piped_priority'), 'high'); ?>>High</option>
                                        <option value="urgent" data-bg="bg-success"<?php echo support_is_selected(support_pick($support_settings, 'default_piped_priority'), 'urgent'); ?>>Urgent</option>
                                        <option value="closed" data-bg="bg-warning"<?php echo support_is_selected(support_pick($support_settings, 'default_piped_priority'), 'closed'); ?>>Closed</option>
                                    </select>
                                    <small class="form-text text-muted">Default priority on piped ticket [Ex: Low/Medium/High/Urgent/Closed]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allowed attachments file extensions</label>
                                    <select name="allowed_extensions[]" class="form-select" data-select2-selector="label" multiple>
                                        <option value=".jpg" data-bg="bg-primary"<?php echo in_array('.jpg', $selected_extensions, true) ? ' selected' : ''; ?>>.jpg</option>
                                        <option value=".png" data-bg="bg-success"<?php echo in_array('.png', $selected_extensions, true) ? ' selected' : ''; ?>>.png</option>
                                        <option value=".pdf" data-bg="bg-danger"<?php echo in_array('.pdf', $selected_extensions, true) ? ' selected' : ''; ?>>.pdf</option>
                                        <option value=".doc" data-bg="bg-secondary"<?php echo in_array('.doc', $selected_extensions, true) ? ' selected' : ''; ?>>.doc</option>
                                        <option value=".zip" data-bg="bg-dark"<?php echo in_array('.zip', $selected_extensions, true) ? ' selected' : ''; ?>>.zip</option>
                                        <option value=".rar" data-bg="bg-warning"<?php echo in_array('.rar', $selected_extensions, true) ? ' selected' : ''; ?>>.rar</option>
                                    </select>
                                    <small class="form-text text-muted">Allowed attachments file extensions [Ex: Facebook/Google/Others]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Ticket Replies Order</label>
                                    <select name="ticket_replies_order" class="form-select" data-select2-selector="label">
                                        <option value="ascending" data-bg="bg-primary"<?php echo support_is_selected(support_pick($support_settings, 'ticket_replies_order'), 'ascending'); ?>>Ascending</option>
                                        <option value="descending" data-bg="bg-success"<?php echo support_is_selected(support_pick($support_settings, 'ticket_replies_order'), 'descending'); ?>>Descending</option>
                                    </select>
                                    <small class="form-text text-muted">Ticket Replies Order [Ex: Ascending/Descending]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow staff to access only ticket that belongs to staff departments </label>
                                    <select name="staff_dept_only_access" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'staff_dept_only_access'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'staff_dept_only_access'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow staff to access only ticket that belongs to staff departments [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Send staff-related ticket notifications to the ticket assignee only </label>
                                    <select name="notify_assignee_only" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'notify_assignee_only'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'notify_assignee_only'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Send staff-related ticket notifications to the ticket assignee only [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label"> Receive notification on new ticket opened </label>
                                    <select name="notify_new_ticket" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'notify_new_ticket'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'notify_new_ticket'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted"> Receive notification on new ticket opened [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label"> Receive notification when customer reply to a ticket </label>
                                    <select name="notify_customer_reply" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'notify_customer_reply'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'notify_customer_reply'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted"> Receive notification when customer reply to a ticket [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label"> Allow staff members to open tickets to all contacts? </label>
                                    <select name="staff_open_all_contacts" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'staff_open_all_contacts'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'staff_open_all_contacts'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted"> Allow staff members to open tickets to all contacts? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Automatically assign the ticket to the first staff that post a reply? </label>
                                    <select name="auto_assign_first_replier" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'auto_assign_first_replier'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'auto_assign_first_replier'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Automatically assign the ticket to the first staff that post a reply? [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow access to tickets for non staff members </label>
                                    <select name="allow_non_staff_ticket_access" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'allow_non_staff_ticket_access'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'allow_non_staff_ticket_access'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow access to tickets for non staff members [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow non-admin staff members to delete ticket attachments </label>
                                    <select name="allow_non_admin_delete_attachments" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'allow_non_admin_delete_attachments'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'allow_non_admin_delete_attachments'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow non-admin staff members to delete ticket attachments [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Allow customer to change ticket status from Studentsarea </label>
                                    <select name="allow_customer_change_status" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'allow_customer_change_status'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'allow_customer_change_status'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Allow customer to change ticket status from Studentsarea [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">In Studentsarea only show tickets related to the logged in contact (Primary contact not applied) </label>
                                    <select name="show_contact_tickets_only" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'show_contact_tickets_only'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'show_contact_tickets_only'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">In Studentsarea only show tickets related to the logged in contact (Primary contact not applied) [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Enable support menu item badge </label>
                                    <select name="enable_support_badge" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'enable_support_badge'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'enable_support_badge'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Enable support menu item badge [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Pipe Only on Registered Users </label>
                                    <select name="pipe_registered_users_only" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'pipe_registered_users_only'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'pipe_registered_users_only'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Pipe Only on Registered Users [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Only Replies Allowed by Email </label>
                                    <select name="email_replies_only" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'email_replies_only'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'email_replies_only'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Only Replies Allowed by Email [Ex: Yes/No]</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Try to import only the actual ticket reply (without quoted/forwarded message) </label>
                                    <select name="import_actual_reply_only" class="form-select" data-select2-selector="icon">
                                        <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, 'import_actual_reply_only'), '1'); ?>>Yes</option>
                                        <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, 'import_actual_reply_only'), '0'); ?>>No</option>
                                    </select>
                                    <small class="form-text text-muted">Try to import only the actual ticket reply (without quoted/forwarded message) [Ex: Yes/No]</small>
                                </div>
                            </div>
                            </form>
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
            <?php include 'includes/footer.php'; ?>
