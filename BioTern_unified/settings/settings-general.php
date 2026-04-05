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
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .settings-shell {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .settings-page-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .settings-page-title h2 {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 700;
    }
    .settings-page-breadcrumb {
        color: var(--bs-secondary-color, #8ea4c9);
        font-size: 0.95rem;
    }
    .settings-page-breadcrumb a {
        color: inherit;
        text-decoration: none;
    }
    .settings-layout {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 1.5rem;
    }
    .settings-nav-card,
    .settings-intro-card,
    .settings-form-card {
        border: 1px solid rgba(90, 123, 255, 0.18);
        border-radius: 18px;
        background: rgba(19, 28, 51, 0.92);
    }
    .settings-nav-card {
        padding: 1rem;
        height: fit-content;
        position: sticky;
        top: 96px;
    }
    .settings-nav-card h5 {
        margin: 0 0 1rem;
        font-size: 1rem;
        font-weight: 700;
    }
    .settings-nav-list {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }
    .settings-nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-radius: 12px;
        color: inherit;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid transparent;
    }
    .settings-nav-link.active {
        background: rgba(82, 109, 254, 0.15);
        border-color: rgba(82, 109, 254, 0.35);
        color: #dfe7ff;
    }
    .settings-nav-link:hover {
        border-color: rgba(82, 109, 254, 0.24);
        color: inherit;
    }
    .settings-main {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .settings-intro-card {
        padding: 1.35rem 1.5rem;
    }
    .settings-intro-meta {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .settings-intro-card h3 {
        margin: 0 0 0.45rem;
        font-size: 1.35rem;
        font-weight: 700;
    }
    .settings-intro-card p,
    .settings-form-header p {
        margin: 0;
        color: var(--bs-secondary-color, #9ab0d3);
    }
    .settings-badge {
        border-radius: 999px;
        padding: 0.55rem 0.9rem;
        font-size: 0.85rem;
        font-weight: 700;
        background: rgba(82, 109, 254, 0.12);
        color: #88a3ff;
        white-space: nowrap;
    }
    .settings-form-card {
        padding: 1.5rem;
    }
    .settings-form-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }
    .settings-form-header h4 {
        margin: 0 0 0.35rem;
        font-size: 1.15rem;
        font-weight: 700;
    }
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem 1.15rem;
    }
    .settings-field-card {
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.02);
    }
    .settings-field-card .form-label {
        margin-bottom: 0.45rem;
        font-weight: 700;
    }
    .settings-field-card .form-text {
        margin-top: 0.45rem;
        color: var(--bs-secondary-color, #8ea4c9);
    }
    .settings-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .settings-toggle-copy {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .settings-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }
    .alert.settings-alert {
        margin-bottom: 0;
        border-radius: 14px;
    }
    html.app-skin-light .settings-nav-card,
    html.app-skin-light .settings-intro-card,
    html.app-skin-light .settings-form-card {
        background: #ffffff;
        border-color: rgba(71, 103, 255, 0.12);
    }
    html.app-skin-light .settings-nav-link {
        background: #f7f9ff;
    }
    html.app-skin-light .settings-nav-link.active {
        background: #eef3ff;
        color: #223a74;
    }
    html.app-skin-light .settings-field-card {
        background: #f8faff;
        border-color: rgba(71, 103, 255, 0.1);
    }
    @media (max-width: 1199.98px) {
        .settings-layout {
            grid-template-columns: 1fr;
        }
        .settings-nav-card {
            position: static;
        }
    }
    @media (max-width: 767.98px) {
        .settings-shell {
            padding: 18px;
        }
        .settings-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content settings-shell">
    <div class="settings-page-title">
        <h2>General Settings</h2>
        <div class="settings-page-breadcrumb">
            <a href="../homepage.php">Home</a>
            <span class="mx-2">></span>
            <a href="settings-general.php">Settings</a>
            <span class="mx-2">></span>
            <span>General</span>
        </div>
    </div>

    <div class="settings-layout">
        <aside class="settings-nav-card">
            <h5>Settings</h5>
            <div class="settings-nav-list">
                <a class="settings-nav-link active" href="settings-general.php">
                    <i class="feather-airplay"></i>
                    <span>General</span>
                </a>
                <a class="settings-nav-link" href="settings-students.php">
                    <i class="feather-users"></i>
                    <span>Students</span>
                </a>
                <a class="settings-nav-link" href="settings-ojt.php">
                    <i class="feather-crosshair"></i>
                    <span>OJT</span>
                </a>
                <a class="settings-nav-link" href="settings-email.php">
                    <i class="feather-mail"></i>
                    <span>Email</span>
                </a>
                <a class="settings-nav-link" href="settings-support.php">
                    <i class="feather-life-buoy"></i>
                    <span>Support</span>
                </a>
            </div>
        </aside>

        <section class="settings-main">
            <div class="settings-intro-card">
                <div class="settings-intro-meta">
                    <div>
                        <h3>System Defaults</h3>
                        <p>Set the platform identity, academic defaults, and global access rules used across BioTern. These values become the shared starting point for forms, reports, and public registration.</p>
                    </div>
                    <div class="settings-badge">General Category</div>
                </div>
            </div>

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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
