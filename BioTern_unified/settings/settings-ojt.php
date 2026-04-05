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
        <h2>OJT Settings</h2>
        <div class="settings-page-breadcrumb">
            <a href="../homepage.php">Home</a>
            <span class="mx-2">></span>
            <a href="settings-general.php">Settings</a>
            <span class="mx-2">></span>
            <span>OJT</span>
        </div>
    </div>

    <div class="settings-layout">
        <aside class="settings-nav-card">
            <h5>Settings</h5>
            <div class="settings-nav-list">
                <a class="settings-nav-link" href="settings-general.php">
                    <i class="feather-airplay"></i>
                    <span>General</span>
                </a>
                <a class="settings-nav-link" href="settings-students.php">
                    <i class="feather-users"></i>
                    <span>Students</span>
                </a>
                <a class="settings-nav-link active" href="settings-ojt.php">
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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
