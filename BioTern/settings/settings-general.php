<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = strtolower(trim((string)(
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    ''
)));

if (!in_array($current_role, ['admin', 'coordinator'], true)) {
    header('Location: ../homepage.php');
    exit;
}

function sg_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sg_ensure_system_settings_table(mysqli $conn): void
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

function sg_fetch_settings(mysqli $conn, string $category): array
{
    $settings = [];
    $stmt = $conn->prepare('SELECT `key`, `value` FROM system_settings WHERE category = ?');
    if ($stmt) {
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $settings[(string)$row['key']] = (string)$row['value'];
        }
        $stmt->close();
    }
    return $settings;
}

function sg_store_setting(mysqli $conn, string $key, string $value, string $description, string $category): bool
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

sg_ensure_system_settings_table($conn);

$year_now = (int)date('Y');
$month_now = (int)date('n');
$school_year_start = $month_now >= 6 ? $year_now : ($year_now - 1);

$default_settings = [
    'system_name' => 'BioTern',
    'institution_name' => 'Clark College of Science and Technology',
    'institution_short_name' => 'CCST',
    'timezone' => 'Asia/Manila',
    'default_school_year' => sprintf('%d-%d', $school_year_start, $school_year_start + 1),
    'default_semester' => '2nd Semester',
    'system_email' => 'noreply@biotern.local',
    'maintenance_mode' => '0',
    'allow_student_registration' => '1',
];

$field_meta = [
    'system_name' => 'Main system name shown across the platform.',
    'institution_name' => 'Full school or institution name.',
    'institution_short_name' => 'Short label used in reports and quick headers.',
    'timezone' => 'Default timezone used by reports, logs, and schedule views.',
    'default_school_year' => 'Default school year for new records and filters.',
    'default_semester' => 'Default semester used in forms and reports.',
    'system_email' => 'Primary sender and contact email for automated notices.',
    'maintenance_mode' => 'Temporarily hide normal access while maintenance is running.',
    'allow_student_registration' => 'Allow new student applications from the public landing page.',
];

$save_error = '';
$save_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = [
        'system_name' => trim((string)($_POST['system_name'] ?? '')),
        'institution_name' => trim((string)($_POST['institution_name'] ?? '')),
        'institution_short_name' => trim((string)($_POST['institution_short_name'] ?? '')),
        'timezone' => trim((string)($_POST['timezone'] ?? 'Asia/Manila')),
        'default_school_year' => trim((string)($_POST['default_school_year'] ?? '')),
        'default_semester' => trim((string)($_POST['default_semester'] ?? '')),
        'system_email' => trim((string)($_POST['system_email'] ?? '')),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'allow_student_registration' => isset($_POST['allow_student_registration']) ? '1' : '0',
    ];

    if ($posted['system_name'] === '' || $posted['institution_name'] === '') {
        $save_error = 'System name and institution name are required.';
    } elseif ($posted['system_email'] !== '' && !filter_var($posted['system_email'], FILTER_VALIDATE_EMAIL)) {
        $save_error = 'System email must be a valid email address.';
    } else {
        $ok = true;
        foreach ($posted as $key => $value) {
            if (!sg_store_setting($conn, $key, $value, $field_meta[$key] ?? '', 'general')) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            $save_success = 'General settings saved successfully.';
        } else {
            $save_error = 'Unable to save one or more settings right now.';
        }
    }
}

$stored_settings = sg_fetch_settings($conn, 'general');
$settings = array_merge($default_settings, $stored_settings);

$page_title = 'General Settings';
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
                <h5 class="m-b-10">General Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                <li class="breadcrumb-item">General</li>
            </ul>
        </div>
    </div>
    <div class="main-content settings-shell">
        <div class="settings-layout">
        <section class="settings-main">
            <?php if ($save_success !== ''): ?>
                <div class="alert alert-success settings-alert" role="alert"><?= sg_h($save_success) ?></div>
            <?php endif; ?>
            <?php if ($save_error !== ''): ?>
                <div class="alert alert-danger settings-alert" role="alert"><?= sg_h($save_error) ?></div>
            <?php endif; ?>

            <form method="post" class="settings-form-card">
                <div class="settings-form-header">
                    <div>
                        <h4>Update General Settings</h4>
                        <p>These settings are saved into the shared system settings store and reused by the rest of the platform.</p>
                    </div>
                    <div class="settings-badge">Saved to system_settings</div>
                </div>

                <div class="settings-grid">
                    <div class="settings-field-card">
                        <label class="form-label" for="system_name">System Name</label>
                        <input class="form-control" id="system_name" name="system_name" type="text" value="<?= sg_h($settings['system_name']) ?>" required>
                        <small class="form-text">Main platform label used in headers and shared UI.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="institution_name">Institution Name</label>
                        <input class="form-control" id="institution_name" name="institution_name" type="text" value="<?= sg_h($settings['institution_name']) ?>" required>
                        <small class="form-text">Full school or institution name for documents and reports.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="institution_short_name">Institution Short Name</label>
                        <input class="form-control" id="institution_short_name" name="institution_short_name" type="text" value="<?= sg_h($settings['institution_short_name']) ?>">
                        <small class="form-text">Short label used where space is tighter.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="system_email">System Email</label>
                        <input class="form-control" id="system_email" name="system_email" type="email" value="<?= sg_h($settings['system_email']) ?>">
                        <small class="form-text">Primary sender and support contact email.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="timezone">Timezone</label>
                        <select class="form-select" id="timezone" name="timezone">
                            <?php
                            $timezone_options = ['Asia/Manila', 'Asia/Shanghai', 'UTC', 'America/New_York', 'Europe/London'];
                            foreach ($timezone_options as $tz):
                                $selected = $settings['timezone'] === $tz ? ' selected' : '';
                            ?>
                                <option value="<?= sg_h($tz) ?>"<?= $selected ?>><?= sg_h($tz) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Used by logs, attendance dates, and report timestamps.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="default_school_year">Default School Year</label>
                        <input class="form-control" id="default_school_year" name="default_school_year" type="text" value="<?= sg_h($settings['default_school_year']) ?>" placeholder="2025-2026">
                        <small class="form-text">Default value shown in new forms and filters.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="default_semester">Default Semester</label>
                        <select class="form-select" id="default_semester" name="default_semester">
                            <?php
                            $semester_options = ['1st Semester', '2nd Semester', 'Summer'];
                            foreach ($semester_options as $semester):
                                $selected = $settings['default_semester'] === $semester ? ' selected' : '';
                            ?>
                                <option value="<?= sg_h($semester) ?>"<?= $selected ?>><?= sg_h($semester) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Used when the semester is not set explicitly.</small>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="maintenance_mode">Maintenance Mode</label>
                            <small class="form-text mt-0">Temporarily limit normal access while maintenance is running.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="maintenance_mode" name="maintenance_mode" type="checkbox" value="1"<?= $settings['maintenance_mode'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="allow_student_registration">Allow Student Registration</label>
                            <small class="form-text mt-0">Controls whether new student applications can start from the public landing page.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="allow_student_registration" name="allow_student_registration" type="checkbox" value="1"<?= $settings['allow_student_registration'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <a class="btn btn-outline-light" href="../homepage.php">Cancel</a>
                    <button class="btn btn-primary" type="submit">
                        <i class="feather-save me-2"></i>
                        Save General Settings
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

