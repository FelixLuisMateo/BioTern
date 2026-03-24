
<?php
// fingerprint_mapping.php
// Admin page to map fingerprint IDs to user IDs
require_once __DIR__ . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? DB_PORT : 3306;

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
}

// Handle form submission
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finger_id = (int)($_POST['finger_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($finger_id > 0 && $user_id > 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");
        $stmt = $conn->prepare("REPLACE INTO fingerprint_user_map (finger_id, user_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $finger_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Mapping updated!';
    } else {
        $msg = 'Invalid input.';
    }
}

// Fetch all mappings
$conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");
$mappings = [];
$res = $conn->query("SELECT * FROM fingerprint_user_map");
while ($row = $res && $res->fetch_assoc()) {
    $mappings[] = $row;
}

// Fetch all users
$users = [];
$res2 = $conn->query("SELECT id, name FROM users ORDER BY id");
while ($row = $res2 && $res2->fetch_assoc()) {
    $users[$row['id']] = $row['name'];
}

$page_title = 'Fingerprint Mapping';
$base_href = '';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navigation.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Fingerprint to User Mapping</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Fingerprint Mapping</li>
                </ul>
            </div>
        </div>

        <?php if (!empty($msg)) echo '<div class="alert alert-success py-2">' . htmlspecialchars($msg) . '</div>'; ?>

        <div class="card mb-4">
            <div class="card-header"><strong>Add / Update Mapping</strong></div>
            <div class="card-body">
                <form method="post" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="finger_id">Fingerprint ID</label>
                        <input type="number" class="form-control" name="finger_id" id="finger_id" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="user_id">User</label>
                        <select class="form-select" name="user_id" id="user_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $uid => $uname): ?>
                                <option value="<?= $uid ?>"><?= htmlspecialchars($uname) ?> (ID: <?= $uid ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Save Mapping</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Current Mappings</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Fingerprint ID</th><th>User</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mappings as $map): ?>
                            <tr>
                                <td><?= $map['finger_id'] ?></td>
                                <td><?= htmlspecialchars($users[$map['user_id']] ?? 'Unknown') ?> (ID: <?= $map['user_id'] ?>)</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
include __DIR__ . '/includes/footer.php';
$conn->close();
?>
