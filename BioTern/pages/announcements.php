<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/announcements.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

biotern_boot_session(isset($conn) ? $conn : null);

function announcements_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function announcements_excerpt(string $value, int $limit = 110): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    if (strlen($value) <= $limit) {
        return $value;
    }
    return substr($value, 0, max(0, $limit - 3)) . '...';
}

function announcements_ensure_runtime_dir(string $path): bool
{
    $path = rtrim(str_replace('\\', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    if ($path === '') {
        return false;
    }

    if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
        return false;
    }

    @chmod($path, 0775);
    return is_dir($path);
}

function announcements_store_upload(array $file, ?string &$error, ?string &$mediaType): string
{
    $error = null;
    $mediaType = null;
    $GLOBALS['announcement_pending_media'] = null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $error = 'Unable to upload announcement media.';
        return '';
    }
    if ((int)($file['size'] ?? 0) > 80 * 1024 * 1024) {
        $error = 'Announcement media must be 80 MB or smaller.';
        return '';
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = '';
    if (is_file($tmp) && class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
    }
    if ($mime === '' && is_file($tmp)) {
        $imageInfo = @getimagesize($tmp);
        $mime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
    }
    if ($mime === '') {
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $extensionMimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
        ];
        $mime = $extensionMimeMap[$extension] ?? '';
    }

    $imageExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $videoExtensions = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
    ];

    if (isset($imageExtensions[$mime])) {
        $extension = $imageExtensions[$mime];
        $mediaType = 'image';
    } elseif (isset($videoExtensions[$mime])) {
        $extension = $videoExtensions[$mime];
        $mediaType = 'video';
    } else {
        $error = 'Upload a JPG, PNG, WebP, GIF, MP4, WebM, OGV, or MOV announcement file.';
        return '';
    }

    $blob = is_file($tmp) ? file_get_contents($tmp) : false;
    if ($blob === false || $blob === '') {
        $error = 'Unable to read announcement media.';
        return '';
    }

    $safeOriginalName = trim((string)($file['name'] ?? 'announcement.' . $extension));
    $GLOBALS['announcement_pending_media'] = [
        'blob' => $blob,
        'mime' => $mime,
        'name' => $safeOriginalName !== '' ? substr($safeOriginalName, 0, 255) : ('announcement.' . $extension),
        'size' => strlen($blob),
    ];

    return '__announcement_media_pending__';
}

function announcements_target_user_ids(mysqli $conn, string $targetRole, int $authorId): array
{
    $targetRole = biotern_announcements_normalize_target($targetRole);
    $where = ['is_active = 1'];
    if ($targetRole !== 'all') {
        $where[] = "LOWER(role) = '" . $conn->real_escape_string($targetRole) . "'";
    }

    $ids = [];
    $result = $conn->query('SELECT id FROM users WHERE ' . implode(' AND ', $where));
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $result->close();
    }

    if (!in_array($authorId, $ids, true) && $authorId > 0 && $targetRole === 'all') {
        $ids[] = $authorId;
    }

    return array_values(array_unique($ids));
}

function announcements_send_notifications(mysqli $conn, array $userIds, string $title, string $body): int
{
    $message = trim($body);
    if ($message === '') {
        $message = 'A new announcement has been posted.';
    }
    $sent = 0;
    foreach ($userIds as $targetUserId) {
        if (biotern_notify($conn, (int)$targetUserId, $title, $message, 'system', 'announcements.php')) {
            $sent++;
        }
    }
    return $sent;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($userId <= 0) {
    header('Location: auth-login.php');
    exit;
}
if (!biotern_announcements_can_manage($role)) {
    http_response_code(403);
    exit('Forbidden');
}

biotern_announcements_ensure_tables($conn);

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = strtolower(trim((string)($_POST['announcement_action'] ?? '')));

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $uploadError = null;
        $mediaType = null;
        $mediaPath = announcements_store_upload($_FILES['announcement_media'] ?? [], $uploadError, $mediaType);
        $mediaType = biotern_announcements_normalize_media_type((string)($mediaType ?? 'image'));
        $popupSize = biotern_announcements_normalize_size((string)($_POST['popup_size'] ?? 'medium'));
        $accentColor = biotern_announcements_normalize_color((string)($_POST['accent_color'] ?? '#3454d1'));
        $buttonLabel = trim((string)($_POST['button_label'] ?? 'Got It'));
        if ($buttonLabel === '') {
            $buttonLabel = 'Got It';
        }
        if (strlen($buttonLabel) > 80) {
            $buttonLabel = substr($buttonLabel, 0, 80);
        }
        $showTitle = isset($_POST['show_title']) ? 1 : 0;
        $displayMode = biotern_announcements_normalize_display_mode((string)($_POST['display_mode'] ?? 'popup'));
        $target = biotern_announcements_normalize_target((string)($_POST['target_role'] ?? 'all'));
        $startsAt = biotern_announcements_datetime_or_null($_POST['starts_at'] ?? null);
        $endsAt = biotern_announcements_datetime_or_null($_POST['ends_at'] ?? null);
        if ($title === '' && $mediaPath !== '') {
            $title = 'Photo Announcement';
            $showTitle = 0;
        }

        if ($uploadError !== null) {
            $error = $uploadError;
        } elseif ($title === '' || ($body === '' && $mediaPath === '')) {
            $error = 'Add a title and either a message or an announcement photo.';
        } elseif ($startsAt === null || $endsAt === null) {
            $error = 'Start and end date/time are required.';
        } elseif (strtotime($endsAt) < strtotime($startsAt)) {
            $error = 'End date cannot be earlier than start date.';
        } else {
            $mediaForInsert = $mediaPath === '__announcement_media_pending__' ? '' : $mediaPath;
            $stmt = $conn->prepare(
                "INSERT INTO announcements (title, body, media_path, media_type, popup_size, accent_color, button_label, show_title, display_mode, target_role, starts_at, ends_at, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())"
            );
            if ($stmt) {
                $stmt->bind_param('sssssssissssi', $title, $body, $mediaForInsert, $mediaType, $popupSize, $accentColor, $buttonLabel, $showTitle, $displayMode, $target, $startsAt, $endsAt, $userId);
                if ($stmt->execute()) {
                    $announcementId = (int)$stmt->insert_id;
                    $pendingMedia = is_array($GLOBALS['announcement_pending_media'] ?? null) ? $GLOBALS['announcement_pending_media'] : null;
                    if ($announcementId > 0 && $pendingMedia !== null) {
                        $mediaUrl = 'announcement-media.php?id=' . $announcementId;
                        $mediaMime = (string)($pendingMedia['mime'] ?? '');
                        $mediaName = (string)($pendingMedia['name'] ?? '');
                        $mediaSize = (int)($pendingMedia['size'] ?? 0);
                        $mediaBlob = (string)($pendingMedia['blob'] ?? '');
                        $mediaStmt = $conn->prepare('UPDATE announcements SET media_path = ?, media_mime = ?, media_name = ?, media_size = ?, media_blob = ? WHERE id = ? LIMIT 1');
                        if ($mediaStmt) {
                            $mediaStmt->bind_param('sssisi', $mediaUrl, $mediaMime, $mediaName, $mediaSize, $mediaBlob, $announcementId);
                            if (!$mediaStmt->execute()) {
                                $mediaError = $mediaStmt->error;
                                $cleanupStmt = $conn->prepare('DELETE FROM announcements WHERE id = ? LIMIT 1');
                                if ($cleanupStmt) {
                                    $cleanupStmt->bind_param('i', $announcementId);
                                    $cleanupStmt->execute();
                                    $cleanupStmt->close();
                                }
                                $error = 'Unable to save announcement media in the database. ' . $mediaError;
                            }
                            $mediaStmt->close();
                            if ($error === '') {
                                $mediaPath = $mediaUrl;
                            }
                        } else {
                            $cleanupStmt = $conn->prepare('DELETE FROM announcements WHERE id = ? LIMIT 1');
                            if ($cleanupStmt) {
                                $cleanupStmt->bind_param('i', $announcementId);
                                $cleanupStmt->execute();
                                $cleanupStmt->close();
                            }
                            $error = 'Unable to prepare announcement media save.';
                        }
                    }
                    if ($error === '') {
                        $sentCount = 0;
                        if (in_array($displayMode, ['notification', 'both'], true)) {
                            $sentCount = announcements_send_notifications($conn, announcements_target_user_ids($conn, $target, $userId), $title, $body);
                        }
                        $message = 'Announcement posted successfully.';
                        if ($sentCount > 0) {
                            $message .= ' Notifications sent: ' . $sentCount . '.';
                        }
                    }
                } else {
                    $error = 'Unable to save announcement right now.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare announcement save.';
            }
        }
    } elseif ($action === 'toggle') {
        $announcementId = (int)($_POST['announcement_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($announcementId > 0) {
            $stmt = $conn->prepare('UPDATE announcements SET is_active = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $isActive, $announcementId);
                $stmt->execute();
                $stmt->close();
                $message = $isActive === 1 ? 'Announcement reactivated.' : 'Announcement hidden.';
            }
        }
    } elseif ($action === 'delete') {
        $announcementId = (int)($_POST['announcement_id'] ?? 0);
        if ($announcementId > 0) {
            $readStmt = $conn->prepare('DELETE FROM announcement_reads WHERE announcement_id = ?');
            if ($readStmt) {
                $readStmt->bind_param('i', $announcementId);
                $readStmt->execute();
                $readStmt->close();
            }

            $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $announcementId);
                if ($stmt->execute()) {
                    $message = 'Announcement deleted permanently.';
                } else {
                    $error = 'Unable to delete announcement right now.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare announcement delete.';
            }
        }
    }
}

$announcements = [];
$result = $conn->query(
    "SELECT a.*, u.name AS author_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.created_by
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT 50"
);
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $result->close();
}

$page_title = 'Announcements';
$page_body_class = 'settings-page announcements-page';
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
                    <h5 class="m-b-10">Announcements</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Announcements</li>
                </ul>
            </div>
        </div>

        <div class="main-content settings-shell">
            <div class="settings-layout">
                <section class="settings-main">
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success settings-alert" role="alert"><?php echo announcements_h($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger settings-alert" role="alert"><?php echo announcements_h($error); ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="settings-form-card mb-4">
                        <input type="hidden" name="announcement_action" value="create">
                        <div class="settings-form-header">
                            <div>
                                <h4>Post Announcement</h4>
                                <p>Upload a poster/photo, set when it starts and ends, then choose whether users see a popup, notification, or both.</p>
                            </div>
                            <div class="settings-badge">Popup + notification ready</div>
                        </div>

                        <div class="settings-grid">
                            <div class="settings-field-card">
                                <label class="form-label" for="title">Title</label>
                                <input type="text" class="form-control" id="title" name="title" maxlength="255">
                                <small class="form-text">Optional when uploading a photo-only popup.</small>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="target_role">Show To</label>
                                <select class="form-select" id="target_role" name="target_role">
                                    <option value="all">Everyone</option>
                                    <option value="student">Students</option>
                                    <option value="coordinator">Coordinators</option>
                                    <option value="supervisor">Supervisors</option>
                                    <option value="admin">Admins</option>
                                </select>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="display_mode">Display As</label>
                                <select class="form-select" id="display_mode" name="display_mode">
                                    <option value="popup">Popup Only</option>
                                    <option value="notification">Notification Only</option>
                                    <option value="both" selected>Popup + Notification</option>
                                </select>
                                <small class="form-text">Use both for important announcements. Notification-only will not interrupt users.</small>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="announcement_media">Announcement Photo or Video</label>
                                <input type="file" class="form-control" id="announcement_media" name="announcement_media" accept="image/png,image/jpeg,image/webp,image/gif,video/mp4,video/webm,video/ogg,video/quicktime">
                                <small class="form-text">Use a poster image or short video. JPG, PNG, WebP, GIF, MP4, WebM, OGV, or MOV up to 80 MB.</small>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="popup_size">Popup Size</label>
                                <select class="form-select" id="popup_size" name="popup_size">
                                    <option value="medium">Medium</option>
                                    <option value="wide">Wide</option>
                                    <option value="full">Full Screen</option>
                                    <option value="small">Small</option>
                                </select>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="accent_color">Accent Color</label>
                                <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" value="#3454d1">
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="button_label">Dismiss Button Text</label>
                                <input type="text" class="form-control" id="button_label" name="button_label" maxlength="80" value="Got It">
                            </div>
                            <div class="settings-field-card settings-toggle">
                                <div class="settings-toggle-copy">
                                    <label class="form-label mb-0" for="show_title">Show Title On Popup</label>
                                    <small class="form-text mt-0">Turn this off for photo-only announcements.</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" id="show_title" name="show_title" type="checkbox" value="1" checked>
                                </div>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="starts_at">Start Showing</label>
                                <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" required>
                                <small class="form-text">The popup will not appear before this date and time.</small>
                            </div>
                            <div class="settings-field-card">
                                <label class="form-label" for="ends_at">Stop Showing</label>
                                <input type="datetime-local" class="form-control" id="ends_at" name="ends_at" required>
                                <small class="form-text">The popup automatically stops after this date and time.</small>
                            </div>
                            <div class="settings-field-card full">
                                <label class="form-label" for="body">Optional Caption</label>
                                <textarea class="form-control" id="body" name="body" rows="4"></textarea>
                            </div>
                        </div>

                        <div class="settings-actions">
                            <button class="btn btn-primary" type="submit">
                                <i class="feather-send me-2"></i>
                                Post Announcement
                            </button>
                        </div>
                    </form>

                    <section class="card settings-panel-card">
                        <div class="card-body">
                            <div class="settings-form-header">
                                <div>
                                    <h4>Recent Announcements</h4>
                                    <p>Hide an announcement to pause it, or delete it when it should be removed permanently.</p>
                                </div>
                            </div>

                            <?php if (empty($announcements)): ?>
                                <div class="text-muted">No announcements yet.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Announcement</th>
                                                <th>Audience</th>
                                                <th>Display</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($announcements as $item): ?>
                                                <?php
                                                $active = (int)($item['is_active'] ?? 0) === 1;
                                                $createdAt = (string)($item['created_at'] ?? '');
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo announcements_h($item['title'] ?? 'Announcement'); ?></strong>
                                                        <?php if (trim((string)($item['media_path'] ?? '')) !== ''): ?>
                                                            <div class="mt-2">
                                                                <?php if (biotern_announcements_normalize_media_type((string)($item['media_type'] ?? 'image')) === 'video'): ?>
                                                                    <video src="<?php echo announcements_h((string)$item['media_path']); ?>" muted style="width: 120px; max-height: 72px; object-fit: cover; border-radius: 6px;"></video>
                                                                <?php else: ?>
                                                                    <img src="<?php echo announcements_h((string)$item['media_path']); ?>" alt="" style="width: 96px; max-height: 72px; object-fit: cover; border-radius: 6px;">
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-muted small"><?php echo announcements_h(announcements_excerpt((string)($item['body'] ?? ''))); ?></div>
                                                    </td>
                                                    <td><?php echo announcements_h(biotern_announcements_target_label((string)($item['target_role'] ?? 'all'))); ?></td>
                                                    <td><?php echo announcements_h(biotern_announcements_display_mode_label((string)($item['display_mode'] ?? 'popup'))); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $active ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'; ?>">
                                                            <?php echo $active ? 'Active' : 'Hidden'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo announcements_h($createdAt !== '' ? date('M d, Y h:i A', strtotime($createdAt)) : ''); ?></td>
                                                    <td class="text-end">
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="announcement_action" value="toggle">
                                                            <input type="hidden" name="announcement_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                            <input type="hidden" name="is_active" value="<?php echo $active ? 0 : 1; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $active ? 'btn-outline-danger' : 'btn-outline-primary'; ?>">
                                                                <?php echo $active ? 'Hide' : 'Reactivate'; ?>
                                                            </button>
                                                        </form>
                                                        <form method="post" class="d-inline" data-confirm-message="Delete this announcement permanently?">
                                                            <input type="hidden" name="announcement_action" value="delete">
                                                            <input type="hidden" name="announcement_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger ms-1">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </section>
            </div>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
