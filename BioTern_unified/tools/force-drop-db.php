<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php?next=tools/force-drop-db.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin access only.';
    exit;
}

if (!function_exists('force_drop_csrf_token')) {
    function force_drop_csrf_token(): string
    {
        $token = (string)($_SESSION['force_drop_db_csrf'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['force_drop_db_csrf'] = $token;
        }
        return $token;
    }
}

if (!function_exists('force_drop_all_objects')) {
    function force_drop_all_objects(mysqli $mysqli, string $databaseName, string &$errorMessage = '', array &$summary = []): bool
    {
        $safeDb = $mysqli->real_escape_string($databaseName);
        $summary = [
            'dropped_views' => 0,
            'dropped_tables' => 0,
            'remaining_objects' => 0,
        ];

        $objects = [];
        $res = $mysqli->query("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.tables WHERE table_schema = '{$safeDb}' ORDER BY CASE WHEN TABLE_TYPE = 'VIEW' THEN 0 ELSE 1 END, TABLE_NAME ASC");
        if (!$res) {
            $errorMessage = 'Unable to read database objects: ' . $mysqli->error;
            return false;
        }

        while ($row = $res->fetch_assoc()) {
            $objects[] = [
                'name' => (string)($row['TABLE_NAME'] ?? ''),
                'type' => strtoupper((string)($row['TABLE_TYPE'] ?? 'BASE TABLE')),
            ];
        }
        $res->free();

        if (empty($objects)) {
            return true;
        }

        $mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
        $mysqli->query('SET UNIQUE_CHECKS = 0');
        $mysqli->query('SET SQL_NOTES = 0');

        $errors = [];
        foreach ($objects as $object) {
            $name = str_replace('`', '``', (string)($object['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = (string)($object['type'] ?? 'BASE TABLE');
            $sql = $type === 'VIEW'
                ? "DROP VIEW IF EXISTS `{$name}`"
                : "DROP TABLE IF EXISTS `{$name}`";

            if ($mysqli->query($sql)) {
                if ($type === 'VIEW') {
                    $summary['dropped_views']++;
                } else {
                    $summary['dropped_tables']++;
                }
                continue;
            }

            $errors[] = $type . ' `' . $name . '`: ' . $mysqli->error;
        }

        $mysqli->query('SET SQL_NOTES = 1');
        $mysqli->query('SET UNIQUE_CHECKS = 1');
        $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

        $remainingResult = $mysqli->query("SELECT COUNT(*) AS remaining_count FROM information_schema.tables WHERE table_schema = '{$safeDb}'");
        if ($remainingResult instanceof mysqli_result) {
            $row = $remainingResult->fetch_assoc();
            $summary['remaining_objects'] = (int)($row['remaining_count'] ?? 0);
            $remainingResult->free();
        }

        if ($summary['remaining_objects'] > 0 || !empty($errors)) {
            $parts = [];
            if ($summary['remaining_objects'] > 0) {
                $parts[] = 'Remaining objects: ' . $summary['remaining_objects'];
            }
            if (!empty($errors)) {
                $parts[] = 'Errors: ' . implode(' | ', array_slice($errors, 0, 4));
            }
            $errorMessage = implode('. ', $parts);
            return false;
        }

        return true;
    }
}

$statusType = '';
$statusMessage = '';
$statusDetails = [];
$csrfToken = force_drop_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $statusType = 'danger';
        $statusMessage = 'Invalid security token. Refresh and try again.';
    } elseif ((string)($_POST['confirm_drop'] ?? '') !== 'DROP ALL') {
        $statusType = 'danger';
        $statusMessage = 'Type DROP ALL exactly to confirm the force-drop action.';
    } else {
        $dropError = '';
        $summary = [];
        if (force_drop_all_objects($conn, (string)DB_NAME, $dropError, $summary)) {
            $statusType = 'success';
            $statusMessage = 'Force drop completed successfully.';
            $statusDetails[] = 'Views dropped: ' . (int)($summary['dropped_views'] ?? 0);
            $statusDetails[] = 'Tables dropped: ' . (int)($summary['dropped_tables'] ?? 0);
            $statusDetails[] = 'Remaining objects: ' . (int)($summary['remaining_objects'] ?? 0);
        } else {
            $statusType = 'danger';
            $statusMessage = 'Force drop failed. ' . $dropError;
            $statusDetails[] = 'Views dropped: ' . (int)($summary['dropped_views'] ?? 0);
            $statusDetails[] = 'Tables dropped: ' . (int)($summary['dropped_tables'] ?? 0);
            $statusDetails[] = 'Remaining objects: ' . (int)($summary['remaining_objects'] ?? 0);
        }
    }
}

$page_title = 'Force Drop Database Objects';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
.force-drop-wrap { max-width: 860px; margin: 0 auto; }
.force-drop-card { border-radius: 1rem; border: 1px solid #e5ebf2; box-shadow: 0 18px 40px rgba(16,24,40,.06); }
.force-drop-danger { background: linear-gradient(135deg, #6f1d1b 0%, #a3302a 55%, #f0b4ae 100%); color: #fff; border: 0; }
.app-skin-dark .force-drop-card { background: #16202b; border-color: #2a394b; box-shadow: 0 18px 40px rgba(0,0,0,.35); }
.app-skin-dark .force-drop-card h3,
.app-skin-dark .force-drop-card h5,
.app-skin-dark .force-drop-card label,
.app-skin-dark .force-drop-card p,
.app-skin-dark .force-drop-card li,
.app-skin-dark .force-drop-card .form-text { color: #eaf2fb; }
.app-skin-dark .force-drop-card .text-muted { color: #aab8c5 !important; }
.app-skin-dark .force-drop-card .form-control { background: #0f1720; border-color: #334354; color: #edf4fb; }
</style>
<div class="container-xxl py-4">
    <div class="force-drop-wrap">
        <?php if ($statusType !== ''): ?>
            <div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> mb-4">
                <strong><?php echo $statusType === 'success' ? 'Success:' : 'Error:'; ?></strong>
                <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($statusDetails)): ?>
                    <div class="small mt-2">
                        <?php foreach ($statusDetails as $detail): ?>
                            <div><?php echo htmlspecialchars((string)$detail, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card force-drop-card force-drop-danger mb-4">
            <div class="card-body p-4 p-md-5">
                <h3 class="mb-2 text-white">Force Drop Database Objects</h3>
                <p class="mb-0 text-white-50">This tool tries to drop every view and table in the current database one by one with foreign key checks disabled. Use it only when replace import keeps failing and you already have a backup.</p>
            </div>
        </div>

        <div class="card force-drop-card">
            <div class="card-body p-4 p-md-5">
                <h5 class="mb-3">Current target</h5>
                <p class="text-muted mb-3">Host: <strong><?php echo htmlspecialchars((string)DB_HOST, ENT_QUOTES, 'UTF-8'); ?></strong><br>Database: <strong><?php echo htmlspecialchars((string)DB_NAME, ENT_QUOTES, 'UTF-8'); ?></strong><br>Port: <strong><?php echo (int)DB_PORT; ?></strong></p>

                <ul class="text-muted">
                    <li>This action is destructive.</li>
                    <li>It can remove tables and views before you re-import the SQL dump.</li>
                    <li>Run it only if the normal replace import still cannot clear the database.</li>
                </ul>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="confirm_drop" class="form-label fw-semibold">Type <code>DROP ALL</code> to continue</label>
                        <input type="text" class="form-control" id="confirm_drop" name="confirm_drop" placeholder="DROP ALL">
                        <div class="form-text">This confirmation helps prevent accidental deletion.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-danger">Force Drop Database Objects</button>
                        <a href="import-sql.php" class="btn btn-light">Back to Import SQL</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
