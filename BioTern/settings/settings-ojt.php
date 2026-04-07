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

function so_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function so_ensure_system_settings_table(mysqli $conn): void
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

function so_fetch_settings(mysqli $conn, string $category): array
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

function so_store_setting(mysqli $conn, string $key, string $value, string $description, string $category): bool
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

so_ensure_system_settings_table($conn);

$default_settings = [
    'ojt_status_default' => 'ongoing',
    'allow_internal_ojt' => '1',
    'allow_external_ojt' => '1',
    'auto_assign_coordinator' => '0',
    'auto_assign_supervisor' => '0',
    'completion_basis' => 'hours',
    'minimum_completion_percent' => '100',
    'require_application_approval_before_ojt' => '1',
    'allow_document_generation_before_assignment' => '0',
    'internship_start_buffer_days' => '0',
];

$field_meta = [
    'ojt_status_default' => 'Default internship status used when a new OJT record is created.',
    'allow_internal_ojt' => 'Allow internal OJT placements across the system.',
    'allow_external_ojt' => 'Allow external OJT placements across the system.',
    'auto_assign_coordinator' => 'Automatically assign a coordinator when possible.',
    'auto_assign_supervisor' => 'Automatically assign a supervisor when possible.',
    'completion_basis' => 'How internship completion should be evaluated.',
    'minimum_completion_percent' => 'Minimum completion threshold required before the internship is considered complete.',
    'require_application_approval_before_ojt' => 'Require application approval before OJT records can proceed.',
    'allow_document_generation_before_assignment' => 'Allow document generation even before OJT assignment is finalized.',
    'internship_start_buffer_days' => 'Optional buffer days before an internship is considered active.',
];

$save_error = '';
$save_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = [
        'ojt_status_default' => trim((string)($_POST['ojt_status_default'] ?? 'ongoing')),
        'allow_internal_ojt' => isset($_POST['allow_internal_ojt']) ? '1' : '0',
        'allow_external_ojt' => isset($_POST['allow_external_ojt']) ? '1' : '0',
        'auto_assign_coordinator' => isset($_POST['auto_assign_coordinator']) ? '1' : '0',
        'auto_assign_supervisor' => isset($_POST['auto_assign_supervisor']) ? '1' : '0',
        'completion_basis' => trim((string)($_POST['completion_basis'] ?? 'hours')),
        'minimum_completion_percent' => trim((string)($_POST['minimum_completion_percent'] ?? '100')),
        'require_application_approval_before_ojt' => isset($_POST['require_application_approval_before_ojt']) ? '1' : '0',
        'allow_document_generation_before_assignment' => isset($_POST['allow_document_generation_before_assignment']) ? '1' : '0',
        'internship_start_buffer_days' => trim((string)($_POST['internship_start_buffer_days'] ?? '0')),
    ];

    if (!in_array($posted['ojt_status_default'], ['ongoing', 'pending', 'applied', 'completed', 'cancelled'], true)) {
        $save_error = 'Default OJT status is invalid.';
    } elseif (!in_array($posted['completion_basis'], ['hours', 'percent'], true)) {
        $save_error = 'Completion basis is invalid.';
    } elseif (!is_numeric($posted['minimum_completion_percent']) || (float)$posted['minimum_completion_percent'] < 0 || (float)$posted['minimum_completion_percent'] > 100) {
        $save_error = 'Minimum completion percent must be between 0 and 100.';
    } elseif (!ctype_digit((string)$posted['internship_start_buffer_days'])) {
        $save_error = 'Internship start buffer days must be a whole number.';
    } else {
        $ok = true;
        foreach ($posted as $key => $value) {
            if (!so_store_setting($conn, $key, $value, $field_meta[$key] ?? '', 'ojt')) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            $save_success = 'OJT settings saved successfully.';
        } else {
            $save_error = 'Unable to save one or more OJT settings right now.';
        }
    }
}

$stored_settings = so_fetch_settings($conn, 'ojt');
$settings = array_merge($default_settings, $stored_settings);

$page_title = 'OJT Settings';
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
                <h5 class="m-b-10">OJT Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                <li class="breadcrumb-item">OJT</li>
            </ul>
        </div>
    </div>
    <div class="main-content settings-shell">
        <div class="settings-layout">
        <section class="settings-main">
            <div class="settings-intro-card">
                <div class="settings-intro-meta">
                    <div>
                        <h3>Internship Workflow Controls</h3>
                        <p>Set how OJT assignments begin, what completion means, and whether approvals or document generation should be allowed at different stages of the workflow.</p>
                    </div>
                    <div class="settings-badge">OJT Category</div>
                </div>
            </div>

            <?php if ($save_success !== ''): ?>
                <div class="alert alert-success settings-alert" role="alert"><?= so_h($save_success) ?></div>
            <?php endif; ?>
            <?php if ($save_error !== ''): ?>
                <div class="alert alert-danger settings-alert" role="alert"><?= so_h($save_error) ?></div>
            <?php endif; ?>

            <form method="post" class="settings-form-card">
                <div class="settings-form-header">
                    <div>
                        <h4>Update OJT Defaults</h4>
                        <p>These settings shape assignment flow, approvals, completion logic, and document timing across BioTern.</p>
                    </div>
                    <div class="settings-badge">Saved to system_settings</div>
                </div>

                <div class="settings-grid">
                    <div class="settings-field-card">
                        <label class="form-label" for="ojt_status_default">Default OJT Status</label>
                        <select class="form-select" id="ojt_status_default" name="ojt_status_default">
                            <?php foreach (['ongoing' => 'Ongoing', 'pending' => 'Pending', 'applied' => 'Applied', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                <option value="<?= so_h($value) ?>"<?= $settings['ojt_status_default'] === $value ? ' selected' : '' ?>><?= so_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Used when a new OJT assignment record is created.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="completion_basis">Completion Basis</label>
                        <select class="form-select" id="completion_basis" name="completion_basis">
                            <?php foreach (['hours' => 'Rendered Hours', 'percent' => 'Completion Percent'] as $value => $label): ?>
                                <option value="<?= so_h($value) ?>"<?= $settings['completion_basis'] === $value ? ' selected' : '' ?>><?= so_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Choose what the system should use to evaluate internship completion.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="minimum_completion_percent">Minimum Completion Percent</label>
                        <input class="form-control" id="minimum_completion_percent" name="minimum_completion_percent" type="number" min="0" max="100" step="1" value="<?= so_h($settings['minimum_completion_percent']) ?>">
                        <small class="form-text">Threshold required before the internship is considered complete.</small>
                    </div>

                    <div class="settings-field-card">
                        <label class="form-label" for="internship_start_buffer_days">Internship Start Buffer Days</label>
                        <input class="form-control" id="internship_start_buffer_days" name="internship_start_buffer_days" type="number" min="0" step="1" value="<?= so_h($settings['internship_start_buffer_days']) ?>">
                        <small class="form-text">Optional number of days before a new assignment is treated as active.</small>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="allow_internal_ojt">Allow Internal OJT</label>
                            <small class="form-text mt-0">Allow internal placements to be created and managed.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="allow_internal_ojt" name="allow_internal_ojt" type="checkbox" value="1"<?= $settings['allow_internal_ojt'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="allow_external_ojt">Allow External OJT</label>
                            <small class="form-text mt-0">Allow external placements to be created and managed.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="allow_external_ojt" name="allow_external_ojt" type="checkbox" value="1"<?= $settings['allow_external_ojt'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="auto_assign_coordinator">Auto Assign Coordinator</label>
                            <small class="form-text mt-0">Automatically assign a coordinator when a matching one is available.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="auto_assign_coordinator" name="auto_assign_coordinator" type="checkbox" value="1"<?= $settings['auto_assign_coordinator'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="auto_assign_supervisor">Auto Assign Supervisor</label>
                            <small class="form-text mt-0">Automatically assign a supervisor when a matching one is available.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="auto_assign_supervisor" name="auto_assign_supervisor" type="checkbox" value="1"<?= $settings['auto_assign_supervisor'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="require_application_approval_before_ojt">Require Approval Before OJT</label>
                            <small class="form-text mt-0">Require application approval before an OJT record can move forward.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="require_application_approval_before_ojt" name="require_application_approval_before_ojt" type="checkbox" value="1"<?= $settings['require_application_approval_before_ojt'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>

                    <div class="settings-field-card settings-toggle">
                        <div class="settings-toggle-copy">
                            <label class="form-label mb-0" for="allow_document_generation_before_assignment">Allow Early Document Generation</label>
                            <small class="form-text mt-0">Allow document generation before the OJT assignment is fully finalized.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" id="allow_document_generation_before_assignment" name="allow_document_generation_before_assignment" type="checkbox" value="1"<?= $settings['allow_document_generation_before_assignment'] === '1' ? ' checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <a class="btn btn-outline-light" href="../homepage.php">Cancel</a>
                    <button class="btn btn-primary" type="submit">
                        <i class="feather-save me-2"></i>
                        Save OJT Settings
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

