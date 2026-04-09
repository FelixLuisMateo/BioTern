<?php
require_once dirname(__DIR__) . '/config/db.php';

require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

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

function ss_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ss_ensure_system_settings_table(mysqli $conn): void
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

function ss_fetch_settings(mysqli $conn, string $category): array
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

function ss_store_setting(mysqli $conn, string $key, string $value, string $description, string $category): bool
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

ss_ensure_system_settings_table($conn);

$default_settings = [
    'default_student_status' => 'active',
    'default_assignment_track' => 'internal',
    'default_internal_hours' => '140',
    'default_external_hours' => '250',
    'require_student_id' => '1',
    'require_profile_picture' => '0',
    'require_emergency_contact' => '1',
    'allow_profile_self_update' => '1',
    'allow_manual_dtr_submission' => '1',
    'manual_dtr_requires_proof' => '1',
];

$field_meta = [
    'default_student_status' => 'Default status used for newly created student records.',
    'default_assignment_track' => 'Default OJT track used when no track is chosen yet.',
    'default_internal_hours' => 'Default required hours for internal placements.',
    'default_external_hours' => 'Default required hours for external placements.',
    'require_student_id' => 'Require a student ID before the profile can be considered complete.',
    'require_profile_picture' => 'Require students to upload a profile picture.',
    'require_emergency_contact' => 'Require an emergency contact on student profiles.',
    'allow_profile_self_update' => 'Allow students to update their own profile details.',
    'allow_manual_dtr_submission' => 'Allow students to submit manual DTR fallback entries.',
    'manual_dtr_requires_proof' => 'Require proof image uploads for manual DTR entries.',
];

$save_error = '';
$save_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = [
        'default_student_status' => trim((string)($_POST['default_student_status'] ?? 'active')),
        'default_assignment_track' => trim((string)($_POST['default_assignment_track'] ?? 'internal')),
        'default_internal_hours' => trim((string)($_POST['default_internal_hours'] ?? '140')),
        'default_external_hours' => trim((string)($_POST['default_external_hours'] ?? '250')),
        'require_student_id' => isset($_POST['require_student_id']) ? '1' : '0',
        'require_profile_picture' => isset($_POST['require_profile_picture']) ? '1' : '0',
        'require_emergency_contact' => isset($_POST['require_emergency_contact']) ? '1' : '0',
        'allow_profile_self_update' => isset($_POST['allow_profile_self_update']) ? '1' : '0',
        'allow_manual_dtr_submission' => isset($_POST['allow_manual_dtr_submission']) ? '1' : '0',
        'manual_dtr_requires_proof' => isset($_POST['manual_dtr_requires_proof']) ? '1' : '0',
    ];

    if (!in_array($posted['default_student_status'], ['active', 'pending', 'inactive'], true)) {
        $save_error = 'Default student status is invalid.';
    } elseif (!in_array($posted['default_assignment_track'], ['internal', 'external'], true)) {
        $save_error = 'Default assignment track is invalid.';
    } elseif (!is_numeric($posted['default_internal_hours']) || (float)$posted['default_internal_hours'] < 0) {
        $save_error = 'Default internal hours must be a valid non-negative number.';
    } elseif (!is_numeric($posted['default_external_hours']) || (float)$posted['default_external_hours'] < 0) {
        $save_error = 'Default external hours must be a valid non-negative number.';
    } else {
        $ok = true;
        foreach ($posted as $key => $value) {
            if (!ss_store_setting($conn, $key, $value, $field_meta[$key] ?? '', 'students')) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            $save_success = 'Student settings saved successfully.';
        } else {
            $save_error = 'Unable to save one or more student settings right now.';
        }
    }
}

$stored_settings = ss_fetch_settings($conn, 'students');
$settings = array_merge($default_settings, $stored_settings);

$page_title = 'Student Settings';
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
                <h5 class="m-b-10">Student Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                <li class="breadcrumb-item">Students</li>
            </ul>
        </div>
    </div>
    <div class="main-content settings-shell">
        <div class="settings-layout">
        <section class="settings-main">
            <?php if ($save_success !== ''): ?>
                <div class="alert alert-success settings-alert" role="alert"><?= ss_h($save_success) ?></div>
            <?php endif; ?>
            <?php if ($save_error !== ''): ?>
                <div class="alert alert-danger settings-alert" role="alert"><?= ss_h($save_error) ?></div>
            <?php endif; ?>

            <form method="post" class="settings-form-card">
                <div class="settings-form-header">
                    <div>
                        <h4>Update Student Defaults</h4>
                        <p>These values shape the default student setup, profile requirements, and manual DTR submission rules.</p>
                    </div>
                    <div class="settings-badge">Saved to system_settings</div>
                </div>

                <div class="settings-grid">
                    <div class="settings-field-card">
                        <label class="form-label" for="default_student_status">Default Student Status</label>
                        <select class="form-select" id="default_student_status" name="default_student_status">
                            <?php foreach (['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive'] as $value => $label): ?>
                                <option value="<?= ss_h($value) ?>"<?= $settings['default_student_status'] === $value ? ' selected' : '' ?>><?= ss_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Used when a new student record is created.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="default_assignment_track">Default Assignment Track</label>
                        <select class="form-select" id="default_assignment_track" name="default_assignment_track">
                            <?php foreach (['internal' => 'Internal', 'external' => 'External'] as $value => $label): ?>
                                <option value="<?= ss_h($value) ?>"<?= $settings['default_assignment_track'] === $value ? ' selected' : '' ?>><?= ss_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Default OJT track used before the assignment is adjusted.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="default_internal_hours">Default Internal Hours</label>
                        <input class="form-control" id="default_internal_hours" name="default_internal_hours" type="number" min="0" step="0.5" value="<?= ss_h($settings['default_internal_hours']) ?>">
                        <small class="form-text">Default required hours for internal placements.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="default_external_hours">Default External Hours</label>
                        <input class="form-control" id="default_external_hours" name="default_external_hours" type="number" min="0" step="0.5" value="<?= ss_h($settings['default_external_hours']) ?>">
                        <small class="form-text">Default required hours for external placements.</small>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="require_student_id">Require Student ID</label>
                            <small class="form-text mt-0">Students must have a student number before the record is considered complete.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="require_student_id" name="require_student_id" type="checkbox" value="1"<?= $settings['require_student_id'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="require_profile_picture">Require Profile Picture</label>
                            <small class="form-text mt-0">Require a photo before the student profile is marked ready.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="require_profile_picture" name="require_profile_picture" type="checkbox" value="1"<?= $settings['require_profile_picture'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="require_emergency_contact">Require Emergency Contact</label>
                            <small class="form-text mt-0">Require emergency contact details on student profiles.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="require_emergency_contact" name="require_emergency_contact" type="checkbox" value="1"<?= $settings['require_emergency_contact'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="allow_profile_self_update">Allow Profile Self Update</label>
                            <small class="form-text mt-0">Allow students to edit their own profile details.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="allow_profile_self_update" name="allow_profile_self_update" type="checkbox" value="1"<?= $settings['allow_profile_self_update'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="allow_manual_dtr_submission">Allow Manual DTR Submission</label>
                            <small class="form-text mt-0">Allow students to submit fallback DTR entries when the biometric flow is unavailable.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="allow_manual_dtr_submission" name="allow_manual_dtr_submission" type="checkbox" value="1"<?= $settings['allow_manual_dtr_submission'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="manual_dtr_requires_proof">Manual DTR Requires Proof</label>
                            <small class="form-text mt-0">Require a proof image when students submit manual DTR entries.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="manual_dtr_requires_proof" name="manual_dtr_requires_proof" type="checkbox" value="1"<?= $settings['manual_dtr_requires_proof'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <a class="btn btn-outline-light" href="../homepage.php">Cancel</a>
                    <button class="btn btn-primary" type="submit">
                        <i class="feather-save me-2"></i>
                        Save Student Settings
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

