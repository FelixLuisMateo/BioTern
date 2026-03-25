<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$page_body_class = 'settings-page';

$dbHost = getenv('DB_HOST');
if ($dbHost === false || $dbHost === '') {
    $dbHost = getenv('MYSQLHOST');
}
if ($dbHost === false || $dbHost === '') {
    $dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
}

$dbUser = getenv('DB_USER');
if ($dbUser === false || $dbUser === '') {
    $dbUser = getenv('MYSQLUSER');
}
if ($dbUser === false || $dbUser === '') {
    $dbUser = defined('DB_USER') ? DB_USER : 'root';
}

$dbPass = getenv('DB_PASS');
if ($dbPass === false || $dbPass === '') {
    $dbPass = getenv('MYSQLPASSWORD');
}
if ($dbPass === false) {
    $dbPass = '';
}
if ($dbPass === '' && defined('DB_PASS')) {
    $dbPass = DB_PASS;
}

$dbName = getenv('DB_NAME');
if ($dbName === false || $dbName === '') {
    $dbName = getenv('MYSQLDATABASE');
}
if ($dbName === false || $dbName === '') {
    $dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
}

$dbPortRaw = getenv('DB_PORT');
if ($dbPortRaw === false || $dbPortRaw === '') {
    $dbPortRaw = getenv('MYSQLPORT');
}
if ($dbPortRaw === false || $dbPortRaw === '') {
    $dbPortRaw = defined('DB_PORT') ? (string)DB_PORT : '3306';
}
$dbPort = (int)$dbPortRaw;
if ($dbPort <= 0) {
    $dbPort = 3306;
}

function support_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function support_pick($arr, $key, $default = ''): string
{
    if (!is_array($arr)) {
        return (string)$default;
    }
    return array_key_exists($key, $arr) ? (string)$arr[$key] : (string)$default;
}

function support_is_selected($current, $value): string
{
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

$boolean_fields = [
    'staff_dept_only_access' => 'Allow staff to access only ticket that belongs to staff departments',
    'notify_assignee_only' => 'Send staff-related ticket notifications to the ticket assignee only',
    'notify_new_ticket' => 'Receive notification on new ticket opened',
    'notify_customer_reply' => 'Receive notification when customer reply to a ticket',
    'staff_open_all_contacts' => 'Allow staff members to open tickets to all contacts?',
    'auto_assign_first_replier' => 'Automatically assign the ticket to the first staff that post a reply?',
    'allow_non_staff_ticket_access' => 'Allow access to tickets for non staff members',
    'allow_non_admin_delete_attachments' => 'Allow non-admin staff members to delete ticket attachments',
    'allow_customer_change_status' => 'Allow customer to change ticket status from Studentsarea',
    'show_contact_tickets_only' => 'In Studentsarea only show tickets related to the logged in contact (Primary contact not applied)',
    'enable_support_badge' => 'Enable support menu item badge',
    'pipe_registered_users_only' => 'Pipe Only on Registered Users',
    'email_replies_only' => 'Only Replies Allowed by Email',
    'import_actual_reply_only' => 'Try to import only the actual ticket reply (without quoted/forwarded message)',
];

$support_settings = $support_defaults;
$support_success = '';
$support_error = '';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    $support_error = 'Database connection failed for support settings.';
} else {
    $has_table = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($has_table && $has_table->num_rows > 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $incoming = [];

            foreach ($support_defaults as $key => $default) {
                if (in_array($key, ['default_reply_status', 'default_piped_priority', 'ticket_replies_order'], true)) {
                    $incoming[$key] = trim((string)($_POST[$key] ?? $default));
                } elseif ($key !== 'allowed_extensions') {
                    $incoming[$key] = (isset($_POST[$key]) && (string)$_POST[$key] === '1') ? '1' : '0';
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

            $stmt = $conn->prepare(
                "INSERT INTO system_settings (`key`, `value`, created_at, updated_at)
                 VALUES (?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()"
            );

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
                    $support_settings = array_merge($support_settings, $incoming);
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
}

$selected_extensions = array_filter(array_map('trim', explode(',', support_pick($support_settings, 'allowed_extensions', ''))));

$page_title = 'Support Settings';
include dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="main-content d-flex settings-theme-customizer">
            <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                <div class="content-area-header sticky-top">
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
                                    <small class="form-text text-muted">Allowed attachments file extensions.</small>
                                </div>

                                <div class="mb-5">
                                    <label class="form-label">Ticket Replies Order</label>
                                    <select name="ticket_replies_order" class="form-select" data-select2-selector="label">
                                        <option value="ascending" data-bg="bg-primary"<?php echo support_is_selected(support_pick($support_settings, 'ticket_replies_order'), 'ascending'); ?>>Ascending</option>
                                        <option value="descending" data-bg="bg-success"<?php echo support_is_selected(support_pick($support_settings, 'ticket_replies_order'), 'descending'); ?>>Descending</option>
                                    </select>
                                    <small class="form-text text-muted">Ticket Replies Order [Ex: Ascending/Descending]</small>
                                </div>

                                <?php foreach ($boolean_fields as $field_key => $field_label): ?>
                                    <div class="mb-5">
                                        <label class="form-label"><?php echo support_h($field_label); ?></label>
                                        <select name="<?php echo support_h($field_key); ?>" class="form-select" data-select2-selector="icon">
                                            <option value="1" data-icon="feather-check text-success"<?php echo support_is_selected(support_pick($support_settings, $field_key), '1'); ?>>Yes</option>
                                            <option value="0" data-icon="feather-x text-danger"<?php echo support_is_selected(support_pick($support_settings, $field_key), '0'); ?>>No</option>
                                        </select>
                                        <small class="form-text text-muted"><?php echo support_h($field_label); ?> [Ex: Yes/No]</small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
