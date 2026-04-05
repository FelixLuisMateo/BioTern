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

$pageTitle = 'Support Settings';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<style>
.settings-shell {
    display: grid;
    grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
    gap: 24px;
}
.settings-sidebar,
.settings-panel {
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
.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.settings-field {
    display: grid;
    gap: 8px;
}
.settings-field.full {
    grid-column: 1 / -1;
}
.settings-field label {
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
}
.settings-field .form-control,
.settings-field .form-select {
    min-height: 48px;
    border-radius: 12px;
}
.settings-switches {
    display: grid;
    gap: 14px;
    margin-top: 8px;
}
.settings-switch {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid rgba(138, 155, 188, 0.14);
    background: rgba(79, 70, 229, 0.04);
}
.settings-switch h6,
.settings-switch p {
    margin: 0;
}
.settings-switch p {
    color: var(--bs-secondary-color);
    font-size: 13px;
    margin-top: 4px;
}
.settings-actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
html.app-skin-light .settings-sidebar,
html.app-skin-light .settings-panel {
    background: #ffffff;
    border-color: rgba(71, 103, 255, 0.12);
    color: #1f2937;
}
html.app-skin-light .settings-nav-title,
html.app-skin-light .settings-field label {
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
html.app-skin-light .settings-switch h6 {
    color: #0f172a;
}
html.app-skin-light .settings-hero p,
html.app-skin-light .settings-switch p {
    color: #64748b;
}
html.app-skin-light .settings-badge {
    background: rgba(82, 109, 254, 0.12);
    color: #4f46e5;
}
html.app-skin-light .settings-field .form-control,
html.app-skin-light .settings-field .form-select {
    background: #ffffff;
    border-color: rgba(148, 163, 184, 0.35);
    color: #0f172a;
}
html.app-skin-light .settings-field .form-control:focus,
html.app-skin-light .settings-field .form-select:focus {
    border-color: rgba(82, 109, 254, 0.45);
    box-shadow: 0 0 0 0.2rem rgba(82, 109, 254, 0.12);
}
html.app-skin-light .settings-switch {
    background: #f7f9ff;
    border-color: rgba(71, 103, 255, 0.12);
}
html.app-skin-dark .settings-sidebar,
html.app-skin-dark .settings-panel {
    background: rgba(19, 28, 51, 0.92);
    color: #e5eefc;
}
html.app-skin-dark .settings-nav a {
    color: #dbe7ff;
}
html.app-skin-dark .settings-field .form-control,
html.app-skin-dark .settings-field .form-select {
    background: #131b33;
    border-color: rgba(138, 155, 188, 0.24);
    color: #ffffff;
}
@media (max-width: 1199.98px) {
    .settings-shell {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767.98px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
    .settings-hero {
        flex-direction: column;
    }
    .settings-switch {
        flex-direction: column;
        align-items: stretch;
    }
    .settings-actions {
        justify-content: stretch;
    }
    .settings-actions .btn {
        flex: 1 1 auto;
    }
}
</style>
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

    <div class="main-content">
        <div class="settings-shell">
            <aside class="settings-sidebar">
                <div class="settings-nav-title">Settings Areas</div>
                <nav class="settings-nav">
                    <a href="settings-general.php"><span>General</span><i class="feather-arrow-right"></i></a>
                    <a href="settings-students.php"><span>Students</span><i class="feather-arrow-right"></i></a>
                    <a href="settings-ojt.php"><span>OJT</span><i class="feather-arrow-right"></i></a>
                    <a href="settings-email.php"><span>Email</span><i class="feather-arrow-right"></i></a>
                    <a href="settings-support.php" class="active"><span>Support</span><i class="feather-check"></i></a>
                </nav>
            </aside>

            <section class="settings-panel">
                <div class="settings-hero">
                    <div>
                        <h3>Support Contact Defaults</h3>
                        <p>Manage the support contact details, help links, and whether students can see or use support request channels.</p>
                    </div>
                    <div class="settings-badge">System Settings</div>
                </div>

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
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

