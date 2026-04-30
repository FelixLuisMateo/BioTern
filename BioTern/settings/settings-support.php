<?php
require_once dirname(__DIR__) . '/config/db.php';

require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$current_role = strtolower(trim((string) (
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    ''
)));

if (!isset($_SESSION['user_id']) || $current_role !== 'admin') {
    header('Location: ../homepage.php');
    exit;
}


function sup_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sup_ensure_system_settings_table(mysqli $conn): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(191) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT 'general',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $conn->query($sql);

    $columns = [];
    if ($result = $conn->query('SHOW COLUMNS FROM system_settings')) {
        while ($row = $result->fetch_assoc()) {
            $columns[(string)$row['Field']] = true;
        }
        $result->close();
    }

    if (!isset($columns['description'])) {
        $conn->query('ALTER TABLE system_settings ADD COLUMN `description` VARCHAR(255) NULL AFTER `value`');
    }

    if (!isset($columns['category'])) {
        $conn->query("ALTER TABLE system_settings ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'general' AFTER `description`");
    }
}

function sup_fetch_settings(mysqli $conn, string $category, array $defaults): array
{
    $settings = $defaults;
    $stmt = $conn->prepare('SELECT `key`, `value` FROM system_settings WHERE category = ?');
    if ($stmt) {
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $settings[(string)$row['key']] = (string)($row['value'] ?? '');
        }
        $stmt->close();
    }

    return $settings;
}

function sup_store_setting(mysqli $conn, string $key, string $value, string $description, string $category): bool
{
    $stmt = $conn->prepare(
        'INSERT INTO system_settings (`key`, `value`, `description`, `category`, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `description` = VALUES(`description`), `category` = VALUES(`category`), updated_at = NOW()'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssss', $key, $value, $description, $category);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

sup_ensure_system_settings_table($conn);

$category = 'support';
$defaults = [
    'support_email' => 'support@biotern.local',
    'support_phone' => '+63 000 000 0000',
    'support_hours' => 'Mon-Fri 8:00 AM to 5:00 PM',
    'support_location' => 'BioTern Administration Office',
    'help_center_url' => '',
    'incident_form_url' => '',
    'allow_support_requests' => '1',
    'show_support_contact_to_students' => '1',
];

$field_meta = [
    'support_email' => 'Primary support email shown in the system.',
    'support_phone' => 'Primary support phone number.',
    'support_hours' => 'Displayed support availability hours.',
    'support_location' => 'Displayed support office or location.',
    'help_center_url' => 'Optional help center URL for users.',
    'incident_form_url' => 'Optional incident reporting form URL.',
    'allow_support_requests' => 'Allow support request features inside the platform.',
    'show_support_contact_to_students' => 'Show support contact details to students.',
];

$settings = sup_fetch_settings($conn, $category, $defaults);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['support_email'] = trim((string) ($_POST['support_email'] ?? ''));
    $settings['support_phone'] = trim((string) ($_POST['support_phone'] ?? ''));
    $settings['support_hours'] = trim((string) ($_POST['support_hours'] ?? ''));
    $settings['support_location'] = trim((string) ($_POST['support_location'] ?? ''));
    $settings['help_center_url'] = trim((string) ($_POST['help_center_url'] ?? ''));
    $settings['incident_form_url'] = trim((string) ($_POST['incident_form_url'] ?? ''));
    $settings['allow_support_requests'] = isset($_POST['allow_support_requests']) ? '1' : '0';
    $settings['show_support_contact_to_students'] = isset($_POST['show_support_contact_to_students']) ? '1' : '0';

    if ($settings['support_email'] !== '' && !filter_var($settings['support_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Support email must be a valid email address.';
    }
    if ($settings['help_center_url'] !== '' && !filter_var($settings['help_center_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Help center URL must be a valid link.';
    }
    if ($settings['incident_form_url'] !== '' && !filter_var($settings['incident_form_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Incident form URL must be a valid link.';
    }

    if (!$errors) {
        $ok = true;
        foreach ($settings as $key => $value) {
            if (!sup_store_setting($conn, $key, (string)$value, $field_meta[$key] ?? '', $category)) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            $success = 'Support settings saved successfully.';
        } else {
            $errors[] = 'Unable to save one or more settings right now.';
        }
    }
}

$page_title = 'Support Settings';
$page_body_class = 'settings-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/settings/settings-shell.css',
    'assets/css/modules/settings/page-settings-suite.css',
];
require_once dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Support Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                <li class="breadcrumb-item">Support</li>
            </ul>
        </div>
    </div>

    <div class="main-content settings-shell">
        <div class="settings-layout">
            <section class="settings-main">
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= sup_h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><?= sup_h($success) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="settings-grid">
                        <div class="settings-field">
                            <label for="support_email">Support Email</label>
                            <input type="email" class="form-control" id="support_email" name="support_email" value="<?= sup_h($settings['support_email']) ?>">
                        </div>
                        <div class="settings-field">
                            <label for="support_phone">Support Phone</label>
                            <input type="text" class="form-control" id="support_phone" name="support_phone" value="<?= sup_h($settings['support_phone']) ?>">
                        </div>
                        <div class="settings-field">
                            <label for="support_hours">Support Hours</label>
                            <input type="text" class="form-control" id="support_hours" name="support_hours" value="<?= sup_h($settings['support_hours']) ?>">
                        </div>
                        <div class="settings-field">
                            <label for="support_location">Support Location</label>
                            <input type="text" class="form-control" id="support_location" name="support_location" value="<?= sup_h($settings['support_location']) ?>">
                        </div>
                        <div class="settings-field full">
                            <label for="help_center_url">Help Center URL</label>
                            <input type="url" class="form-control" id="help_center_url" name="help_center_url" value="<?= sup_h($settings['help_center_url']) ?>">
                        </div>
                        <div class="settings-field full">
                            <label for="incident_form_url">Incident Form URL</label>
                            <input type="url" class="form-control" id="incident_form_url" name="incident_form_url" value="<?= sup_h($settings['incident_form_url']) ?>">
                        </div>
                        <div class="settings-field full">
                            <div class="settings-switches">
                                <label class="settings-switch">
                                    <div>
                                        <h6>Allow Support Requests</h6>
                                        <p>Enable support-related links and request channels across the system.</p>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="allow_support_requests" <?= $settings['allow_support_requests'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </label>
                                <label class="settings-switch">
                                    <div>
                                        <h6>Show Support Contact to Students</h6>
                                        <p>Display support contact details in student-facing pages and help sections.</p>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="show_support_contact_to_students" <?= $settings['show_support_contact_to_students'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-actions">
                        <a href="settings-general.php" class="btn btn-light">Back to General</a>
                        <button type="submit" class="btn btn-primary">Save Support Settings</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

