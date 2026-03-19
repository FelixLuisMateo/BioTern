<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php');
    exit;
}

$user = null;
$stmt = $conn->prepare('SELECT id, name, username, email, role, is_active, profile_picture, created_at FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    header('Location: auth-login-cover.php?logout=1');
    exit;
}

$studentProfile = null;
$currentRole = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
if ($currentRole === 'student') {
    $studentStmt = $conn->prepare("SELECT s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.address,
        c.name AS course_name, d.name AS department_name, sec.name AS section_name
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        WHERE s.user_id = ?
        LIMIT 1");
    if ($studentStmt) {
        $studentStmt->bind_param('i', $userId);
        $studentStmt->execute();
        $studentProfile = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
    }
}

$lastLoginAt = null;
$loginStmt = $conn->prepare('SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
if ($loginStmt) {
    $status = 'success';
    $loginStmt->bind_param('is', $userId, $status);
    $loginStmt->execute();
    $lastLogin = $loginStmt->get_result()->fetch_assoc();
    $lastLoginAt = $lastLogin['created_at'] ?? null;
    $loginStmt->close();
}

$displayName = trim((string)($user['name'] ?? 'BioTern User'));
if ($displayName === '') {
    $displayName = 'BioTern User';
}

$nameParts = preg_split('/\s+/', $displayName) ?: [];
$initials = '';
if (!empty($nameParts[0])) {
    $initials .= strtoupper(substr((string)$nameParts[0], 0, 1));
}
if (!empty($nameParts[1])) {
    $initials .= strtoupper(substr((string)$nameParts[1], 0, 1));
}
if ($initials === '') {
    $initials = 'BT';
}

$memberSinceDisplay = '-';
if (!empty($user['created_at'])) {
    $ts = strtotime((string)$user['created_at']);
    if ($ts !== false) {
        $memberSinceDisplay = date('M d, Y h:i A', $ts);
    }
}

$lastLoginDisplay = 'No login record yet';
if (!empty($lastLoginAt)) {
    $ts = strtotime((string)$lastLoginAt);
    if ($ts !== false) {
        $lastLoginDisplay = date('M d, Y h:i A', $ts);
    }
}

$page_title = 'BioTern || Profile Details';
include 'includes/header.php';
?>
<style>
    .profile-shell {
        position: relative;
        overflow: hidden;
    }

    .profile-shell::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(1200px 220px at -10% -20%, rgba(37, 99, 235, 0.14), transparent 58%),
            radial-gradient(900px 180px at 100% 0%, rgba(6, 182, 212, 0.12), transparent 60%);
        pointer-events: none;
    }

    .profile-shell > * {
        position: relative;
        z-index: 1;
    }

    .profile-hero {
        border: 1px solid rgba(37, 99, 235, 0.18);
        border-radius: 16px;
        background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 55%, #f2fcff 100%);
        padding: 22px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
    }

    .profile-persona {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .profile-avatar {
        width: 66px;
        height: 66px;
        border-radius: 18px;
        background: linear-gradient(135deg, #1d4ed8, #0891b2);
        color: #ffffff;
        font-weight: 700;
        font-size: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 12px 24px rgba(29, 78, 216, 0.25);
    }

    .profile-name {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .profile-role {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(14, 116, 144, 0.22);
        background: rgba(14, 116, 144, 0.08);
        color: #0e7490;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 600;
    }

    .profile-kpis {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        min-width: 260px;
    }

    .profile-kpi {
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #ffffff;
        border-radius: 12px;
        padding: 10px 12px;
    }

    .profile-kpi-label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .profile-kpi-value {
        margin-top: 3px;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
    }

    .profile-panel {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
    }

    .profile-panel .card-header {
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        background: #ffffff;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .profile-field {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 10px;
        padding: 10px;
        background: #f8fafc;
    }

    .profile-field-label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .profile-field-value {
        margin-top: 4px;
        color: #0f172a;
        font-size: 13px;
        font-weight: 600;
        word-break: break-word;
    }

    .profile-field.full {
        grid-column: 1 / -1;
    }

    .profile-action-btn {
        border-radius: 10px;
        font-weight: 600;
    }

    .profile-action-note {
        font-size: 12px;
        color: #64748b;
    }

    @media (max-width: 991.98px) {
        .profile-kpis {
            width: 100%;
            min-width: 0;
        }
    }

    @media (max-width: 767.98px) {
        .profile-hero {
            padding: 16px;
            border-radius: 12px;
        }

        .profile-grid {
            grid-template-columns: 1fr;
        }

        .profile-avatar {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            font-size: 18px;
        }
    }

    html.app-skin-dark .profile-hero {
        border-color: rgba(96, 165, 250, 0.26);
        background: linear-gradient(135deg, #132544 0%, #0f2942 55%, #113347 100%);
    }

    html.app-skin-dark .profile-name,
    html.app-skin-dark .profile-kpi-value,
    html.app-skin-dark .profile-field-value {
        color: #e2e8f0;
    }

    html.app-skin-dark .profile-kpi,
    html.app-skin-dark .profile-field,
    html.app-skin-dark .profile-panel .card-header,
    html.app-skin-dark .profile-panel .card-body {
        background: #10203a;
        border-color: rgba(148, 163, 184, 0.22);
    }

    html.app-skin-dark .profile-field-label,
    html.app-skin-dark .profile-kpi-label,
    html.app-skin-dark .profile-action-note {
        color: #94a3b8;
    }
    
</style>
<style>
    body.apps-account-page .main-content {
        padding-top: 0 !important;
    }

    body.apps-account-page .content-sidebar,
    body.apps-account-page .content-area {
        border-color: #e2e8f0 !important;
    }

    body.apps-account-page .content-sidebar-header,
    body.apps-account-page .content-area-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    body.apps-account-page .nxl-content-sidebar-item .nav-link {
        border-radius: 8px;
        margin: 0 0.35rem;
        color: #1f2937;
        font-weight: 600;
    }

    body.apps-account-page .nxl-content-sidebar-item .nav-link.active {
        background: #eef2ff;
        color: #1d4ed8;
    }

    html.app-skin-dark body.apps-account-page .content-sidebar,
    html.app-skin-dark body.apps-account-page .content-area {
        border-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-account-page .content-sidebar-header,
    html.app-skin-dark body.apps-account-page .content-area-header {
        background: #0f172a !important;
        border-bottom-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-account-page .nxl-content-sidebar-item .nav-link {
        color: #dbe5f5 !important;
    }

    html.app-skin-dark body.apps-account-page .nxl-content-sidebar-item .nav-link.active {
        background: #1c2740 !important;
        color: #8fb4ff !important;
    }
</style>

<script>
    document.body.classList.add('apps-account-page');
</script>

<div class="main-content d-flex">
    <div class="content-area w-100" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header bg-white sticky-top">
            <div class="page-header-left d-flex align-items-center gap-2">
                <h5 class="mb-0">Profile Details</h5>
            </div>
        </div>

        <div class="content-area-body p-3 profile-shell">
            <div class="profile-hero">
                <div class="profile-persona">
                    <div class="profile-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div>
                        <h6 class="profile-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h6>
                        <span class="profile-role"><?php echo htmlspecialchars(ucfirst((string)($user['role'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="profile-kpis">
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Account Status</div>
                        <div class="profile-kpi-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Member Since</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars($memberSinceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Last Login</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Username</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card profile-panel">
                        <div class="card-header">
                            <h6 class="mb-0">My Account</h6>
                        </div>
                        <div class="card-body">
                            <div class="profile-grid">
                                <div class="profile-field">
                                    <div class="profile-field-label">Full Name</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Username</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Email</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Role</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(ucfirst((string)($user['role'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Status</div>
                                    <div class="profile-field-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Last Login</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Member Since</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($memberSinceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (is_array($studentProfile)): ?>
                    <div class="card profile-panel mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Student Profile</h6>
                        </div>
                        <div class="card-body">
                            <div class="profile-grid">
                                <div class="profile-field">
                                    <div class="profile-field-label">Student ID</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($studentProfile['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Course</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($studentProfile['course_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Section</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($studentProfile['section_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Phone</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($studentProfile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Student Email</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($studentProfile['student_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Address</div>
                                    <div class="profile-field-value"><?php echo nl2br(htmlspecialchars((string)($studentProfile['address'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-5" id="account-settings">
                    <div class="card profile-panel">
                        <div class="card-header">
                            <h6 class="mb-0">Individual Account Settings</h6>
                        </div>
                        <div class="card-body">
                            <p class="profile-action-note mb-3">Quick actions for your own account.</p>
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-primary profile-action-btn" href="notifications.php">Open My Notifications</a>
                                <a class="btn btn-outline-secondary profile-action-btn" href="activity-feed.php">Open My Activity Feed</a>
                                <a class="btn btn-outline-dark profile-action-btn" href="auth-login-cover.php?logout=1">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
