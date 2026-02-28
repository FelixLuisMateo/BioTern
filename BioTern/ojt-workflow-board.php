<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (file_exists(__DIR__ . '/lib/ops_helpers.php')) {
    require_once __DIR__ . '/lib/ops_helpers.php';
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';
$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$can_approve = in_array($current_role, ['admin', 'coordinator'], true);

$conn->query("CREATE TABLE IF NOT EXISTS document_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    review_notes TEXT NULL,
    approved_by INT NOT NULL DEFAULT 0,
    approved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_doc (user_id, doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_workflow'])) {
    if (!$can_approve) {
        $message = 'You are not allowed to change workflow statuses.';
        $message_type = 'warning';
    } else {
        $user_id = intval($_POST['user_id'] ?? 0);
        $doc_type = trim((string)($_POST['doc_type'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'draft'));
        $note = trim((string)($_POST['review_notes'] ?? ''));
        $allowed_docs = ['application', 'endorsement', 'moa', 'dau_moa'];
        $allowed_status = ['draft', 'for_review', 'approved', 'rejected'];
        if ($user_id > 0 && in_array($doc_type, $allowed_docs, true) && in_array($status, $allowed_status, true)) {
            $approved_by = 0;
            $approved_at = null;
            if ($status === 'approved') {
                $approved_by = $current_user_id;
                $approved_at = date('Y-m-d H:i:s');
            }
            $stmt = $conn->prepare("INSERT INTO document_workflow (user_id, doc_type, status, review_notes, approved_by, approved_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    review_notes = VALUES(review_notes),
                    approved_by = VALUES(approved_by),
                    approved_at = VALUES(approved_at),
                    updated_at = NOW()");
            $stmt->bind_param('isssis', $user_id, $doc_type, $status, $note, $approved_by, $approved_at);
            if ($stmt->execute()) {
                $message = 'Workflow updated.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update workflow.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$doc_filter = trim((string)($_GET['doc_type'] ?? 'all'));

$sql = "
SELECT
    s.id AS student_id,
    s.student_id AS school_id,
    s.first_name,
    s.last_name,
    c.name AS course_name,
    w.doc_type,
    w.status,
    w.review_notes,
    w.approved_at,
    w.updated_at,
    w.approved_by,
    s.profile_picture
FROM document_workflow w
INNER JOIN students s ON s.id = w.user_id
LEFT JOIN courses c ON c.id = s.course_id
ORDER BY w.updated_at DESC
";

$res = $conn->query($sql);
$columns = ['draft' => [], 'for_review' => [], 'approved' => [], 'rejected' => []];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $name = strtolower(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['school_id'] ?? '') . ' ' . ($row['course_name'] ?? '')));
        if ($search !== '' && strpos($name, strtolower($search)) === false) continue;
        if ($doc_filter !== 'all' && $doc_filter !== (string)$row['doc_type']) continue;
        $st = strtolower((string)($row['status'] ?? 'draft'));
        if (!isset($columns[$st])) $st = 'draft';
        $columns[$st][] = $row;
    }
}

function doc_label(string $doc): string {
    $map = ['application' => 'Application', 'endorsement' => 'Endorsement', 'moa' => 'MOA', 'dau_moa' => 'Dau MOA'];
    return $map[$doc] ?? ucfirst($doc);
}

function status_label(string $s): string {
    return ucfirst(str_replace('_', ' ', $s));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || OJT Workflow Board</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <script>
        (function(){
            try{
                var s = localStorage.getItem('app-skin-dark') || localStorage.getItem('app-skin') || localStorage.getItem('app_skin') || localStorage.getItem('theme');
                if (s && (s.indexOf && s.indexOf('dark') !== -1 || s === 'app-skin-dark')) {
                    document.documentElement.classList.add('app-skin-dark');
                }
            }catch(e){}
        })();
    </script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        body { background: #f5f7fb; }
        .board-col { min-height: 320px; }
        .board-head { font-weight: 700; font-size: 13px; letter-spacing: 0.3px; text-transform: uppercase; }
        .wf-card { border: 1px solid #e8edf6; border-radius: 10px; padding: 10px; background: #fff; margin-bottom: 10px; }
        .wf-meta { font-size: 12px; color: #6c7a92; }
        .wf-note { font-size: 12px; }
        .app-skin-dark body { background: #0b1220; }
        .app-skin-dark .wf-card { background: #111a2e; border-color: #253252; }
        .app-skin-dark .wf-meta { color: #a6b4cf; }
    </style>
</head>
<body>
<?php include_once 'includes/navigation.php'; ?>
<header class="nxl-header">
    <div class="header-wrapper">
        <div class="header-left d-flex align-items-center gap-3">
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                        <div class="hamburger-inner"></div>
                    </div>
                </div>
            </a>
            <div class="nxl-navigation-toggle">
                <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
            </div>
        </div>
        <div class="header-right ms-auto">
            <div class="d-flex align-items-center gap-2">
                <div class="nxl-h-item dark-light-theme">
                    <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button" title="Dark mode"><i class="feather-moon"></i></a>
                    <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display:none" title="Light mode"><i class="feather-sun"></i></a>
                </div>
                <a href="ojt.php" class="btn btn-light btn-sm">Back to OJT Dashboard</a>
            </div>
        </div>
    </div>
</header>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="m-b-10">OJT Document Workflow Board</h5>
                <small class="text-muted">Centralized approval tracking for internship documents</small>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> py-2"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card card-body mb-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search Student</label>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name / Student ID / Course">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Document Type</label>
                    <select name="doc_type" class="form-select">
                        <option value="all" <?php echo $doc_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="application" <?php echo $doc_filter === 'application' ? 'selected' : ''; ?>>Application</option>
                        <option value="endorsement" <?php echo $doc_filter === 'endorsement' ? 'selected' : ''; ?>>Endorsement</option>
                        <option value="moa" <?php echo $doc_filter === 'moa' ? 'selected' : ''; ?>>MOA</option>
                        <option value="dau_moa" <?php echo $doc_filter === 'dau_moa' ? 'selected' : ''; ?>>Dau MOA</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                </div>
                <div class="col-md-2">
                    <a href="ojt-workflow-board.php" class="btn btn-light w-100">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3">
            <?php foreach (['draft', 'for_review', 'approved', 'rejected'] as $col_key): ?>
                <div class="col-lg-3 board-col">
                    <div class="card card-body h-100">
                        <div class="board-head mb-2"><?php echo htmlspecialchars(status_label($col_key)); ?> (<?php echo count($columns[$col_key]); ?>)</div>
                        <?php if (empty($columns[$col_key])): ?>
                            <div class="text-muted fs-12">No records.</div>
                        <?php else: ?>
                            <?php foreach ($columns[$col_key] as $item): ?>
                                <div class="wf-card">
                                    <div class="fw-semibold"><?php echo htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))); ?></div>
                                    <div class="wf-meta"><?php echo htmlspecialchars((string)($item['school_id'] ?? '')); ?> | <?php echo htmlspecialchars((string)($item['course_name'] ?? '-')); ?></div>
                                    <div class="wf-meta mb-1">Doc: <?php echo htmlspecialchars(doc_label((string)($item['doc_type'] ?? ''))); ?></div>
                                    <div class="wf-note mb-2"><?php echo htmlspecialchars((string)($item['review_notes'] ?? '')); ?></div>
                                    <div class="wf-meta mb-2">Updated: <?php echo htmlspecialchars((string)($item['updated_at'] ?? '')); ?></div>
                                    <div class="d-flex gap-2 mb-2">
                                        <a href="ojt-view.php?id=<?php echo intval($item['student_id']); ?>#profileTab" class="btn btn-sm btn-light">Open</a>
                                    </div>
                                    <?php if ($can_approve): ?>
                                        <form method="post" class="row g-1">
                                            <input type="hidden" name="update_workflow" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo intval($item['student_id']); ?>">
                                            <input type="hidden" name="doc_type" value="<?php echo htmlspecialchars((string)$item['doc_type']); ?>">
                                            <div class="col-12">
                                                <select name="status" class="form-select form-select-sm">
                                                    <?php foreach (['draft', 'for_review', 'approved', 'rejected'] as $st): ?>
                                                        <option value="<?php echo $st; ?>" <?php echo ((string)$item['status'] === $st) ? 'selected' : ''; ?>><?php echo status_label($st); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <textarea name="review_notes" rows="2" class="form-control form-control-sm" placeholder="Review note"><?php echo htmlspecialchars((string)($item['review_notes'] ?? '')); ?></textarea>
                                            </div>
                                            <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Save</button></div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<script src="assets/vendors/js/vendors.min.js"></script>
<script src="assets/js/common-init.min.js"></script>
<script>
    (function () {
        var root = document.documentElement;
        var darkBtn = document.querySelector('.dark-button');
        var lightBtn = document.querySelector('.light-button');
        function applyTheme(isDark) {
            root.classList.toggle('app-skin-dark', isDark);
            try {
                localStorage.setItem('app-skin', isDark ? 'app-skin-dark' : 'app-skin-light');
                localStorage.setItem('app_skin', isDark ? 'app-skin-dark' : 'app-skin-light');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                if (isDark) localStorage.setItem('app-skin-dark', 'app-skin-dark');
                else localStorage.removeItem('app-skin-dark');
            } catch (e) {}
            if (darkBtn && lightBtn) {
                darkBtn.style.display = isDark ? 'none' : '';
                lightBtn.style.display = isDark ? '' : 'none';
            }
        }
        applyTheme(root.classList.contains('app-skin-dark'));
        if (darkBtn) darkBtn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(true); });
        if (lightBtn) lightBtn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(false); });
    })();
</script>
</body>
</html>
<?php $conn->close(); ?>
