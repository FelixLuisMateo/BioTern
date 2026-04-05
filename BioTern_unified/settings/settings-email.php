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


function se_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function se_ensure_system_settings_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100) NOT NULL,
        setting_key VARCHAR(150) NOT NULL,
        setting_value TEXT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_category_key (category, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
}

function se_fetch_settings(mysqli $conn, string $category, array $defaults): array
{
    $settings = $defaults;
    $stmt = $conn->prepare('SELECT setting_key, setting_value FROM system_settings WHERE category = ?');
    if ($stmt) {
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        $stmt->close();
    }

    return $settings;
}

function se_store_setting(mysqli $conn, string $category, string $key, string $value, int $userId): void
{
    $stmt = $conn->prepare(
        'INSERT INTO system_settings (category, setting_key, setting_value, updated_by) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
    );
    if ($stmt) {
        $stmt->bind_param('sssi', $category, $key, $value, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

se_ensure_system_settings_table($conn);

$category = 'email';
$defaults = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'mail_from_name' => 'BioTern',
    'mail_from_email' => 'noreply@biotern.local',
    'reply_to_email' => 'support@biotern.local',
    'enable_email_notifications' => '1',
    'send_application_updates' => '1',
];

$settings = se_fetch_settings($conn, $category, $defaults);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['smtp_host'] = trim((string) ($_POST['smtp_host'] ?? ''));
    $settings['smtp_port'] = trim((string) ($_POST['smtp_port'] ?? ''));
    $settings['smtp_encryption'] = trim((string) ($_POST['smtp_encryption'] ?? 'tls'));
    $settings['smtp_username'] = trim((string) ($_POST['smtp_username'] ?? ''));
    $settings['smtp_password'] = trim((string) ($_POST['smtp_password'] ?? ''));
    $settings['mail_from_name'] = trim((string) ($_POST['mail_from_name'] ?? ''));
    $settings['mail_from_email'] = trim((string) ($_POST['mail_from_email'] ?? ''));
    $settings['reply_to_email'] = trim((string) ($_POST['reply_to_email'] ?? ''));
    $settings['enable_email_notifications'] = isset($_POST['enable_email_notifications']) ? '1' : '0';
    $settings['send_application_updates'] = isset($_POST['send_application_updates']) ? '1' : '0';

    if ($settings['smtp_host'] === '') {
        $errors[] = 'SMTP host is required.';
    }
    if ($settings['smtp_port'] === '' || !ctype_digit($settings['smtp_port'])) {
        $errors[] = 'SMTP port must be a valid number.';
    }
    if (!in_array($settings['smtp_encryption'], ['tls', 'ssl', 'none'], true)) {
        $errors[] = 'Encryption must be TLS, SSL, or None.';
    }
    if ($settings['mail_from_name'] === '') {
        $errors[] = 'Sender name is required.';
    }
    if ($settings['mail_from_email'] !== '' && !filter_var($settings['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Sender email must be a valid email address.';
    }
    if ($settings['reply_to_email'] !== '' && !filter_var($settings['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Reply-to email must be a valid email address.';
    }

    if (!$errors) {
        $userId = (int) $_SESSION['user_id'];
        foreach ($settings as $key => $value) {
            se_store_setting($conn, $category, $key, (string) $value, $userId);
        }
        $success = 'Email settings saved successfully.';
    }
}

$pageTitle = 'Email Settings';
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
                <h5 class="m-b-10">Email Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                <li class="breadcrumb-item">Email</li>
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
                    <a href="settings-email.php" class="active"><span>Email</span><i class="feather-check"></i></a>
                    <a href="settings-support.php"><span>Support</span><i class="feather-arrow-right"></i></a>
                </nav>
            </aside>

            <section class="settings-panel">
                <div class="settings-hero">
                    <div>
                        <h3>Mail Delivery Defaults</h3>
                        <p>Configure the outgoing mail server, sender identity, and which BioTern updates should trigger email notifications.</p>
                    </div>
                    <div class="settings-badge">System Settings</div>
                </div>

                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= se_h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><?= se_h($success) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="settings-grid">
                        <div class="settings-field">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= se_h($settings['smtp_host']) ?>" required>
                        </div>
                        <div class="settings-field">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="text" class="form-control" id="smtp_port" name="smtp_port" value="<?= se_h($settings['smtp_port']) ?>" required>
                        </div>
                        <div class="settings-field">
                            <label for="smtp_encryption">Encryption</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $label): ?>
                                    <option value="<?= se_h($value) ?>" <?= $settings['smtp_encryption'] === $value ? 'selected' : '' ?>><?= se_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="settings-field">
                            <label for="smtp_username">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?= se_h($settings['smtp_username']) ?>">
                        </div>
                        <div class="settings-field">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?= se_h($settings['smtp_password']) ?>">
                        </div>
                        <div class="settings-field">
                            <label for="mail_from_name">Sender Name</label>
                            <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" value="<?= se_h($settings['mail_from_name']) ?>" required>
                        </div>
                        <div class="settings-field">
                            <label for="mail_from_email">Sender Email</label>
                            <input type="email" class="form-control" id="mail_from_email" name="mail_from_email" value="<?= se_h($settings['mail_from_email']) ?>">
                        </div>
                        <div class="settings-field">
                            <label for="reply_to_email">Reply-To Email</label>
                            <input type="email" class="form-control" id="reply_to_email" name="reply_to_email" value="<?= se_h($settings['reply_to_email']) ?>">
                        </div>
                        <div class="settings-field full">
                            <div class="settings-switches">
                                <label class="settings-switch">
                                    <div>
                                        <h6>Email Notifications</h6>
                                        <p>Allow the system to send notices for approvals, reminders, and workflow updates.</p>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="enable_email_notifications" <?= $settings['enable_email_notifications'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </label>
                                <label class="settings-switch">
                                    <div>
                                        <h6>Application Update Emails</h6>
                                        <p>Send email updates whenever an application moves between pending, approved, and rejected states.</p>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="send_application_updates" <?= $settings['send_application_updates'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-actions">
                        <a href="settings-general.php" class="btn btn-light">Back to General</a>
                        <button type="submit" class="btn btn-primary">Save Email Settings</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

