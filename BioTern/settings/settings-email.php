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

function se_fetch_settings(mysqli $conn, string $category, array $defaults): array
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

function se_store_setting(mysqli $conn, string $key, string $value, string $description, string $category): bool
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

$field_meta = [
    'smtp_host' => 'Default SMTP server hostname.',
    'smtp_port' => 'Default SMTP server port.',
    'smtp_encryption' => 'Default email encryption mode.',
    'smtp_username' => 'Username used to authenticate to the SMTP server.',
    'smtp_password' => 'Password or app password used to authenticate to the SMTP server.',
    'mail_from_name' => 'Default sender name for outgoing emails.',
    'mail_from_email' => 'Default sender email address for outgoing emails.',
    'reply_to_email' => 'Reply-to address used in outgoing emails.',
    'enable_email_notifications' => 'Global toggle for BioTern email notifications.',
    'send_application_updates' => 'Send application status updates through email.',
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
        $ok = true;
        foreach ($settings as $key => $value) {
            if (!se_store_setting($conn, $key, (string)$value, $field_meta[$key] ?? '', $category)) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            $success = 'Email settings saved successfully.';
        } else {
            $errors[] = 'Unable to save one or more settings right now.';
        }
    }
}

$page_title = 'Email Settings';
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
                    <h5 class="m-b-10">Email Settings</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                    <li class="breadcrumb-item">Email</li>
                </ul>
            </div>
        </div>
    <div class="main-content settings-shell">
        <section class="settings-panel">
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
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>


