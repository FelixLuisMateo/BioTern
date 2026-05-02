<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
require_once dirname(__DIR__) . '/includes/avatar.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function chat_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chat_supported_reactions(): array
{
    static $reactions = null;
    if ($reactions !== null) {
        return $reactions;
    }

    $reactions = [
        'Like' => html_entity_decode('&#128077;', ENT_QUOTES, 'UTF-8'),
        'Love' => html_entity_decode('&#10084;&#65039;', ENT_QUOTES, 'UTF-8'),
        'Haha' => html_entity_decode('&#128514;', ENT_QUOTES, 'UTF-8'),
        'Wow' => html_entity_decode('&#128558;', ENT_QUOTES, 'UTF-8'),
        'Sad' => html_entity_decode('&#128546;', ENT_QUOTES, 'UTF-8'),
        'Angry' => html_entity_decode('&#128545;', ENT_QUOTES, 'UTF-8'),
    ];

    return $reactions;
}

function chat_normalize_reaction_emoji(string $emoji): string
{
    $emoji = trim($emoji);
    if ($emoji === '') {
        return '';
    }

    $reactions = chat_supported_reactions();
    $aliases = [
        'ðŸ‘' => $reactions['Like'],
        $reactions['Like'] => $reactions['Like'],
        'â¤ï¸' => $reactions['Love'],
        html_entity_decode('&#10084;', ENT_QUOTES, 'UTF-8') => $reactions['Love'],
        $reactions['Love'] => $reactions['Love'],
        'ðŸ˜‚' => $reactions['Haha'],
        $reactions['Haha'] => $reactions['Haha'],
        'ðŸ˜®' => $reactions['Wow'],
        $reactions['Wow'] => $reactions['Wow'],
        'ðŸ˜¢' => $reactions['Sad'],
        $reactions['Sad'] => $reactions['Sad'],
        'ðŸ˜¡' => $reactions['Angry'],
        $reactions['Angry'] => $reactions['Angry'],
    ];

    return $aliases[$emoji] ?? $emoji;
}

function chat_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'BT';
}

function chat_normalize_role(?string $role): string
{
    return strtolower(trim((string)$role));
}

function chat_contact_group_meta(string $role, bool $isStudentView): array
{
    $normalizedRole = chat_normalize_role($role);

    if ($isStudentView) {
        if ($normalizedRole === 'supervisor') {
            return ['key' => 'supervisors', 'label' => 'Supervisors', 'order' => 10];
        }
        if ($normalizedRole === 'student') {
            return ['key' => 'same-supervisor', 'label' => 'Same Supervisor', 'order' => 20];
        }

        return ['key' => '', 'label' => '', 'order' => 99];
    }

    if ($normalizedRole === 'supervisor') {
        return ['key' => 'supervisors', 'label' => 'Supervisors', 'order' => 10];
    }
    if ($normalizedRole === 'coordinator') {
        return ['key' => 'coordinators', 'label' => 'Coordinators', 'order' => 20];
    }
    if ($normalizedRole === 'student') {
        return ['key' => 'students', 'label' => 'Students', 'order' => 30];
    }

    return ['key' => 'others', 'label' => 'Other Users', 'order' => 40];
}

function chat_time_label(?string $value): string
{
    if (!$value) {
        return 'No messages yet';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    $delta = time() - $timestamp;
    if ($delta < 60) {
        return 'Just now';
    }
    if ($delta < 3600) {
        return floor($delta / 60) . ' min ago';
    }
    if ($delta < 86400) {
        return floor($delta / 3600) . ' hr ago';
    }
    if ($delta < 604800) {
        $days = (int)floor($delta / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }

    return date('M j, Y g:i A', $timestamp);
}

function chat_avatar_path(string $profilePicture, int $userId = 0): string
{
    return biotern_avatar_public_src($profilePicture, $userId);
}

function chat_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function chat_page_url(int $userId = 0, array $extraQuery = []): string
{
    $query = $extraQuery;
    if ($userId > 0) {
        $query['user_id'] = $userId;
    }

    $url = 'apps-chat.php';
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function chat_media_url(int $messageId): string
{
    $messageId = (int)$messageId;
    if ($messageId <= 0) {
        return '';
    }
    return 'chat-media.php?mid=' . $messageId;
}

function chat_has_table(mysqli $conn, string $table): bool
{
    $table = trim($table);
    if ($table === '') {
        return false;
    }

    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '" . $safeTable . "'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function chat_student_scope(mysqli $conn, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT
            s.id,
            s.user_id,
            COALESCE(s.course_id, 0) AS course_id,
            COALESCE(s.section_id, 0) AS section_id,
            COALESCE(scope_sup_by_user.user_id, scope_sup_by_id.user_id, student_sup_by_id.user_id, student_sup_by_user.user_id, 0) AS supervisor_user_id
         FROM students s
         LEFT JOIN (
            SELECT i_full.*
            FROM internships i_full
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM internships
                GROUP BY student_id
            ) i_latest ON i_latest.latest_id = i_full.id
         ) i_scope ON i_scope.student_id = s.id
         LEFT JOIN supervisors scope_sup_by_id ON scope_sup_by_id.id = i_scope.supervisor_id
         LEFT JOIN supervisors scope_sup_by_user ON scope_sup_by_user.user_id = i_scope.supervisor_id
         LEFT JOIN supervisors student_sup_by_id ON student_sup_by_id.id = s.supervisor_id
         LEFT JOIN supervisors student_sup_by_user ON student_sup_by_user.user_id = s.supervisor_id
         LEFT JOIN users u ON u.id = ?
         WHERE s.user_id = ?
            OR (u.username <> "" AND LOWER(TRIM(COALESCE(s.student_id, ""))) = LOWER(TRIM(u.username)))
            OR (u.email <> "" AND LOWER(TRIM(COALESCE(s.email, ""))) = LOWER(TRIM(u.email)))
            OR (u.name <> "" AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")))) = LOWER(TRIM(u.name)))
         ORDER BY CASE WHEN s.user_id = ? THEN 0 ELSE 1 END, s.id DESC
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iii', $userId, $userId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    return [
        'student_id' => (int)($row['id'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'course_id' => (int)($row['course_id'] ?? 0),
        'section_id' => (int)($row['section_id'] ?? 0),
        'supervisor_user_id' => (int)($row['supervisor_user_id'] ?? 0),
    ];
}

function chat_student_can_contact_user(mysqli $conn, array $studentScope, int $recipientUserId): bool
{
    $recipientUserId = (int)$recipientUserId;

    if ($recipientUserId <= 0) {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT
            LOWER(TRIM(COALESCE(u.role, ""))) AS role,
            COALESCE(contact_sup_by_user.user_id, contact_sup_by_id.user_id, student_sup_by_id.user_id, student_sup_by_user.user_id, 0) AS supervisor_user_id
         FROM users u
         LEFT JOIN students s ON s.user_id = u.id
         LEFT JOIN (
            SELECT i_full.*
            FROM internships i_full
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM internships
                GROUP BY student_id
            ) i_latest ON i_latest.latest_id = i_full.id
         ) i_contact ON i_contact.student_id = s.id
         LEFT JOIN supervisors contact_sup_by_id ON contact_sup_by_id.id = i_contact.supervisor_id
         LEFT JOIN supervisors contact_sup_by_user ON contact_sup_by_user.user_id = i_contact.supervisor_id
         LEFT JOIN supervisors student_sup_by_id ON student_sup_by_id.id = s.supervisor_id
         LEFT JOIN supervisors student_sup_by_user ON student_sup_by_user.user_id = s.supervisor_id
         WHERE u.id = ?
           AND (u.is_active = 1 OR u.is_active IS NULL)
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $recipientUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!is_array($row)) {
        return false;
    }

    $role = chat_normalize_role((string)($row['role'] ?? ''));
    if ($role === 'supervisor') {
        return true;
    }

    if ($role !== 'student') {
        return false;
    }

    $currentSupervisorId = (int)($studentScope['supervisor_user_id'] ?? 0);
    $recipientSupervisorId = (int)($row['supervisor_user_id'] ?? 0);
    return $currentSupervisorId > 0 && $recipientSupervisorId > 0 && $currentSupervisorId === $recipientSupervisorId;
}

function chat_fetch_recent_login_user_ids(mysqli $conn): array
{
    if (!chat_has_table($conn, 'login_logs')) {
        return [];
    }

    $ids = [];
    $sql = "SELECT DISTINCT user_id FROM login_logs WHERE status = 'success' AND user_id IS NOT NULL AND created_at >= (NOW() - INTERVAL 15 MINUTE)";
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId > 0) {
                $ids[$userId] = true;
            }
        }
        $res->free();
    }

    return $ids;
}

function chat_is_online(array $recentLoginUserIds, int $userId, ?string $lastActivityAt): bool
{
    return $userId > 0 && isset($recentLoginUserIds[$userId]);
}

function chat_media_kind_from_path(string $path): string
{
    if (preg_match('~(?:^|/)chat-media\.php(?:$|[?/])~i', $path) === 1 || stripos($path, 'chat-media.php?') !== false) {
        return 'image';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'image';
    }
    if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov'], true)) {
        return 'video';
    }

    return '';
}

function chat_unsent_marker(): string
{
    return '__btchat_unsent__';
}

function chat_blocked_message_emojis(): array
{
    return [
        html_entity_decode('&#128405;', ENT_QUOTES, 'UTF-8'),
        html_entity_decode('&#127814;', ENT_QUOTES, 'UTF-8'),
        html_entity_decode('&#127825;', ENT_QUOTES, 'UTF-8'),
        html_entity_decode('&#128166;', ENT_QUOTES, 'UTF-8'),
        html_entity_decode('&#128069;', ENT_QUOTES, 'UTF-8'),
    ];
}

function chat_moderation_payload(string $text): array
{
    $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $text);
    if (!is_string($clean)) {
        $clean = $text;
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($clean, 'UTF-8') : strtolower($clean);
    $lower = strtr($lower, [
        '0' => 'o',
        '1' => 'i',
        '3' => 'e',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
        '@' => 'a',
        '$' => 's',
    ]);

    $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower);
    if (!is_string($normalized)) {
        $normalized = $lower;
    }
    $normalized = trim((string)(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized));

    $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $lower);
    if (!is_string($compact)) {
        $compact = '';
    }

    $symbolCompact = trim((string)(preg_replace('/\s+/u', '', $clean) ?? $clean));

    return [
        'clean' => $clean,
        'normalized' => $normalized,
        'compact' => $compact,
        'symbol_compact' => $symbolCompact,
    ];
}

function chat_moderation_error(string $text): string
{
    if (trim($text) === '') {
        return '';
    }

    foreach (chat_blocked_message_emojis() as $emoji) {
        if ($emoji !== '' && str_contains($text, $emoji)) {
            return 'Message blocked due to unsupported or offensive emoji.';
        }
    }

    $payload = chat_moderation_payload($text);
    $normalized = $payload['normalized'];
    $compact = $payload['compact'];
    $symbolCompact = $payload['symbol_compact'];

    $symbolCompactLower = function_exists('mb_strtolower') ? mb_strtolower($symbolCompact, 'UTF-8') : strtolower($symbolCompact);
    foreach (['./.', '/./', '.|.', '<==3', '<===3', '<====3', '8==d', '8===d', '8====d', 'b==d', 'b===d', 'b====d'] as $token) {
        if ($symbolCompactLower !== '' && str_contains($symbolCompactLower, $token)) {
            return 'Message blocked due to disallowed symbol patterns.';
        }
    }
    if ($symbolCompactLower !== '' && preg_match('/(?:<|8|b|c)[=\-~_]{2,}(?:3|d)/u', $symbolCompactLower) === 1) {
        return 'Message blocked due to disallowed symbol patterns.';
    }

    foreach ([
        'kill yourself', 'kill ur self', 'kill your self',
        'putang ina', 'putang ina mo', 'tang ina', 'tangina mo',
        'anak ng puta', 'anak ka ng puta', 'bwakanang ina', 'bwakanang ina mo',
        'gago ka', 'ulol ka', 'biot ka', 'bayot ka',
        'kupal ka', 'tarantado ka', 'hayop ka', 'hayup ka',
        'puta ka', 'gago mo',
    ] as $phrase) {
        $pattern = '/\b' . preg_quote($phrase, '/') . '\b/u';
        if ($normalized !== '' && preg_match($pattern, $normalized) === 1) {
            return 'Message blocked due to inappropriate language. Please edit and try again.';
        }
    }

    $blockedTerms = [
        // English
        'fuck', 'fucking', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'pussy',
        'nude', 'nudes', 'porn', 'sext', 'blowjob', 'handjob', 'cum', 'kys',
        'fck', 'fvck', 'phuck', 'btch', 'biatch',
        'cunt', 'whore', 'slut', 'rape', 'rapist', 'pedo', 'pedophile',
        'jizz', 'boner', 'wank', 'wanker', 'fap', 'hentai', 'horny',
        'orgasm', 'masturbate', 'masturbation', 'threesome', 'gangbang', 'creampie',
        'anal', 'erection', 'ejaculate', 'ejaculation', 'xxx',
        // Filipino / Tagalog
        'putangina', 'potangina', 'puta', 'punyeta', 'gago', 'gaga', 'tangina',
        'leche', 'buwisit', 'kupal', 'tarantado', 'pakshet', 'pakyu', 'putcha',
        'kantot', 'iyot', 'jakol', 'tite', 'pekpek', 'ulol', 'bobo',
        'ptngina', 'tngina', 'ulul',
        'tanga', 'inutil', 'ogag', 'engot', 'gagu',
        'putaragis', 'putaena', 'bwiset', 'bwisit', 'bwakanangina', 'bwakananginamo',
        'hindot', 'libog', 'salsal', 'bayag', 'burat', 'pokpok',
        'biot', 'bayot', 'bading',
        'gunggong', 'kolokoy', 'hinayupak',  'lintik', 'demonyo',
        'punyemas', 'burikat', 'pokpokin',
        // Cebuano / Visayan
        'yawa', 'yawaa', 'buang', 'otin', 'bilat', 'pisti', 'piste', 'atay',
        'amaw', 'yati',
        // Spanish
        'cono', 'coño', 'joder', 'cabron', 'cabrón', 'mierda', 'pendejo',
        'verga', 'chinga', 'culero',
        // Korean (romanized)
        'sibal', 'ssibal', 'gaeseki', 'jiral', 'byeongsin',
        // Japanese (romanized)
        'kuso', 'kutabare', 'chinko', 'manko',
    ];

    foreach ($blockedTerms as $term) {
        $pattern = '/\b' . preg_quote($term, '/') . '\b/u';
        if ($normalized !== '' && preg_match($pattern, $normalized) === 1) {
            return 'Message blocked due to inappropriate language. Please edit and try again.';
        }
        if ($compact !== '' && str_contains($compact, str_replace(' ', '', $term))) {
            return 'Message blocked due to inappropriate language. Please edit and try again.';
        }
    }

    // Native-script check — Korean Hangul, Japanese, Chinese, accented Spanish
    $blockedNativeTerms = [
        '시발', '씨발', '개새끼', '병신', '지랄', '창녀', '보지', '자지',
        'くそ', 'くたばれ', 'ちんこ', 'まんこ', '死ね', 'うんこ',
        '操你妈', '你妈的', '他妈的', '去死', '傻逼', '草泥马', '肏你',
    ];
    foreach ($blockedNativeTerms as $native) {
        if (str_contains($text, $native)) {
            return 'Message blocked due to inappropriate language. Please edit and try again.';
        }
    }

    return '';
}

function chat_contains_inappropriate(string $text): bool
{
    return chat_moderation_error($text) !== '';
}

function chat_normalize_contact(array $contact, array $recentLoginUserIds, bool $isStudentView = false): array
{
    $name = trim((string)($contact['name'] ?? ''));
    if ($name === '') {
        $name = (string)($contact['username'] ?? 'Unknown User');
    }

    $profilePicture = (string)($contact['profile_picture'] ?? '');
    $hasCustomAvatar = trim($profilePicture) !== '';
    $userId = (int)($contact['id'] ?? 0);
    $avatarPath = chat_avatar_path($profilePicture, $userId);
    $lastMessage = trim((string)($contact['last_message'] ?? ''));
    $lastMediaPath = trim((string)($contact['last_media_path'] ?? ''));
    $lastMessageAt = (string)($contact['last_message_at'] ?? '');

    if ($lastMessage === chat_unsent_marker()) {
        $lastMessage = 'Message was unsent';
    }

    // Replace raw media filenames with a readable contact list preview.
    $previewMediaKind = '';
    if ($lastMediaPath !== '') {
        $previewMediaKind = chat_media_kind_from_path($lastMediaPath);
    }
    if ($previewMediaKind === '' && $lastMessage !== '') {
        $candidate = basename($lastMessage);
        if (preg_match('/^msg_\d+_\d+_[a-f0-9]{8}\.[a-z0-9]+$/i', $candidate)) {
            $previewMediaKind = chat_media_kind_from_path($candidate);
        }
    }
    if ($previewMediaKind === 'image') {
        $lastMessage = 'Sent an image';
    } elseif ($previewMediaKind === 'video') {
        $lastMessage = 'Sent a video';
    }

    $role = chat_normalize_role((string)($contact['role'] ?? $contact['user_role'] ?? ''));
    $groupMeta = chat_contact_group_meta($role, $isStudentView);

    return [
        'id' => $userId,
        'name' => $name,
        'username' => (string)($contact['username'] ?? ''),
        'email' => (string)($contact['email'] ?? ''),
        'role' => $role,
        'group_key' => (string)$groupMeta['key'],
        'group_label' => (string)$groupMeta['label'],
        'group_order' => (int)$groupMeta['order'],
        'avatar_path' => $avatarPath,
        'has_custom_avatar' => $hasCustomAvatar,
        'initials' => chat_initials($name),
        'last_message' => $lastMessage,
        'last_message_at' => $lastMessageAt,
        'last_message_label' => chat_time_label($lastMessageAt),
        'unread_count' => (int)($contact['unread_count'] ?? 0),
        'message_count' => (int)($contact['message_count'] ?? 0),
        'is_online' => chat_is_online($recentLoginUserIds, $userId, $lastMessageAt),
    ];
}

function chat_normalize_messages(array $messages, int $currentUserId): array
{
    $items = [];
    $messagesById = [];
    foreach ($messages as $message) {
        $messagesById[(int)($message['message_id'] ?? 0)] = $message;
    }
    $todayDate = date('Y-m-d');
    foreach ($messages as $message) {
        $createdAt = (string)($message['created_at'] ?? '');
        $ts = $createdAt !== '' ? strtotime($createdAt) : 0;
        $timeExact = $ts > 0
            ? (date('Y-m-d', $ts) === $todayDate ? date('g:i A', $ts) : date('M j | g:i A', $ts))
            : '';
        $timeFull = $ts > 0 ? date('F j, Y \a\t g:i A', $ts) : '';
        $readAt = (string)($message['read_at'] ?? '');
        $readTs = $readAt !== '' ? strtotime($readAt) : 0;
        $readTimeExact = $readTs > 0
            ? (date('Y-m-d', $readTs) === $todayDate ? date('g:i A', $readTs) : date('M j, Y g:i A', $readTs))
            : '';
        $rawMedia = trim((string)($message['media_path'] ?? ''));
        $mediaType = $rawMedia !== '' ? chat_media_kind_from_path($rawMedia) : '';
        $messageTextRaw = (string)($message['message'] ?? '');
        $unsentAt = trim((string)($message['unsent_at'] ?? ''));
        $isOwn = (int)($message['sender_id'] ?? 0) === $currentUserId;
        $isUnsent = $unsentAt !== '' || trim($messageTextRaw) === chat_unsent_marker();
        $isPinned = (int)($message['is_pinned'] ?? 0) === 1;
        if ($isUnsent) {
            $rawMedia = '';
            $mediaType = '';
            $isPinned = false;
        }
        $replyToId = (int)($message['reply_to_message_id'] ?? 0);
        $replyPreview = '';
        $replyAuthor = '';
        if ($replyToId > 0 && isset($messagesById[$replyToId])) {
            $replyMessage = $messagesById[$replyToId];
            $replyUnsentAt = trim((string)($replyMessage['unsent_at'] ?? ''));
            $replyAuthor = ((int)($replyMessage['sender_id'] ?? 0) === $currentUserId) ? 'You' : 'Them';
            $replyText = trim((string)($replyMessage['message'] ?? ''));
            $replyMediaPath = trim((string)($replyMessage['media_path'] ?? ''));
            if ($replyUnsentAt !== '') {
                $replyText = '[Message unsent]';
            } elseif ($replyMediaPath !== '' && ($replyText === '' || $replyText === basename($replyMediaPath))) {
                $replyMediaType = chat_media_kind_from_path($replyMediaPath);
                $replyText = $replyMediaType === 'video' ? '[Video]' : '[Image]';
            }
            $replyPreview = $replyText;
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                if (mb_strlen($replyPreview) > 90) {
                    $replyPreview = mb_substr($replyPreview, 0, 87) . '...';
                }
            } elseif (strlen($replyPreview) > 90) {
                $replyPreview = substr($replyPreview, 0, 87) . '...';
            }
        }
        $rawReactionSummary = (isset($message['reaction_summary']) && is_array($message['reaction_summary'])) ? $message['reaction_summary'] : [];
        $reactionSummary = [];
        foreach ($rawReactionSummary as $reactionItem) {
            if (!is_array($reactionItem)) {
                continue;
            }
            $reactionEmoji = chat_normalize_reaction_emoji((string)($reactionItem['emoji'] ?? ''));
            $reactionCount = (int)($reactionItem['count'] ?? 0);
            if ($reactionEmoji === '' || $reactionCount <= 0) {
                continue;
            }
            $reactionSummary[] = [
                'emoji' => $reactionEmoji,
                'count' => $reactionCount,
            ];
        }

        $rawReactionUsers = (isset($message['reaction_users']) && is_array($message['reaction_users'])) ? $message['reaction_users'] : [];
        $reactionUsers = [];
        foreach ($rawReactionUsers as $reactionUser) {
            if (!is_array($reactionUser)) {
                continue;
            }
            $reactionEmoji = chat_normalize_reaction_emoji((string)($reactionUser['emoji'] ?? ''));
            if ($reactionEmoji === '') {
                continue;
            }
            $reactionUserName = trim((string)($reactionUser['name'] ?? ''));
            if ($reactionUserName === '') {
                $reactionUserName = 'Unknown user';
            }
            $reactionUserId = (int)($reactionUser['user_id'] ?? 0);
            $reactionUsers[] = [
                'user_id' => $reactionUserId,
                'name' => $reactionUserName,
                'avatar_path' => chat_avatar_path((string)($reactionUser['profile_picture'] ?? ''), $reactionUserId),
                'initials' => chat_initials($reactionUserName),
                'emoji' => $reactionEmoji,
                'is_own' => $reactionUserId === $currentUserId,
            ];
        }

        $reactionTotal = max(0, (int)($message['reaction_count'] ?? 0));
        if ($reactionTotal <= 0) {
            foreach ($reactionSummary as $reactionItem) {
                $reactionTotal += (int)($reactionItem['count'] ?? 0);
            }
        }

        $topReactionEmoji = chat_normalize_reaction_emoji((string)($message['reaction_emoji'] ?? ''));
        if (!empty($reactionSummary)) {
            $topReactionEmoji = (string)($reactionSummary[0]['emoji'] ?? $topReactionEmoji);
        }
        if ($topReactionEmoji !== '' && empty($reactionSummary) && $reactionTotal > 0) {
            $reactionSummary[] = [
                'emoji' => $topReactionEmoji,
                'count' => $reactionTotal,
            ];
        }

        if ($isUnsent) {
            $reactionSummary = [];
            $reactionUsers = [];
            $reactionTotal = 0;
            $topReactionEmoji = '';
            $replyToId = 0;
            $replyPreview = '';
            $replyAuthor = '';
        }

        $messageText = $messageTextRaw;
        if ($isUnsent) {
            $messageText = $isOwn ? 'You unsent a message' : 'This message was unsent';
        }

        $items[] = [
            'message_id' => (int)($message['message_id'] ?? 0),
            'sender_id' => (int)($message['sender_id'] ?? 0),
            'recipient_id' => (int)($message['recipient_id'] ?? 0),
            'reply_to_message_id' => $replyToId,
            'reply_preview' => $replyPreview,
            'reply_author' => $replyAuthor,
            'reaction_emoji' => $topReactionEmoji,
            'reaction_by_user_id' => (int)($message['reaction_by_user_id'] ?? 0),
            'reaction_count' => $reactionTotal,
            'reaction_summary' => $reactionSummary,
            'reaction_users' => $reactionUsers,
            'message' => $messageText,
            'subject' => (string)($message['subject'] ?? ''),
            'media_path' => $rawMedia,
            'media_type' => $mediaType,
            'created_at' => $createdAt,
            'time_label' => chat_time_label($createdAt),
            'time_exact' => $timeExact,
            'time_full' => $timeFull,
            'is_read' => (int)($message['is_read'] ?? 0) === 1,
            'read_at' => $readAt,
            'read_time_label' => $readAt !== '' ? chat_time_label($readAt) : '',
            'read_time_exact' => $readTimeExact,
            'date_key' => $ts > 0 ? date('Y-m-d', $ts) : '',
            'is_own' => $isOwn,
            'is_pinned' => $isPinned,
            'is_unsent' => $isUnsent,
            'unsent_at' => $unsentAt,
        ];
    }

    return $items;
}

function chat_ensure_messages_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_user_id BIGINT UNSIGNED NOT NULL,
            to_user_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(255) NULL,
            message LONGTEXT NOT NULL,
            reply_to_message_id BIGINT UNSIGNED NULL DEFAULT NULL,
            media_path VARCHAR(512) NULL DEFAULT NULL,
            reaction_emoji VARCHAR(32) NULL DEFAULT NULL,
            reaction_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_messages_pair (from_user_id, to_user_id),
            INDEX idx_messages_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    // Add media_path to existing tables that were created before this column existed
    $cols = [];
    $cr = $conn->query('SHOW COLUMNS FROM messages');
    if ($cr instanceof mysqli_result) {
        while ($row = $cr->fetch_assoc()) {
            $cols[] = strtolower((string)($row['Field'] ?? ''));
        }
        $cr->free();
    }
    if (!in_array('media_path', $cols, true)) {
        $conn->query("ALTER TABLE messages ADD COLUMN media_path VARCHAR(512) NULL DEFAULT NULL AFTER message");
    }
    if (!in_array('reply_to_message_id', $cols, true)) {
        $conn->query("ALTER TABLE messages ADD COLUMN reply_to_message_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER message");
    }
    if (!in_array('reaction_emoji', $cols, true)) {
        $conn->query("ALTER TABLE messages ADD COLUMN reaction_emoji VARCHAR(32) NULL DEFAULT NULL AFTER media_path");
    }
    if (!in_array('reaction_by_user_id', $cols, true)) {
        $conn->query("ALTER TABLE messages ADD COLUMN reaction_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER reaction_emoji");
    }
}

function chat_ensure_message_reactions_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS message_reactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            emoji VARCHAR(32) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_message_user (message_id, user_id),
            INDEX idx_message_emoji (message_id, emoji),
            INDEX idx_reaction_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function chat_ensure_message_media_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS chat_message_media (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL DEFAULT '',
            media_mime VARCHAR(64) NOT NULL,
            media_data LONGBLOB NOT NULL,
            media_size INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_chat_message_media_message (message_id),
            INDEX idx_chat_message_media_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function chat_ensure_message_pins_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS message_pins (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT UNSIGNED NOT NULL,
            pinned_by_user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_message_pin (message_id),
            INDEX idx_pinned_by_user (pinned_by_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function chat_ensure_message_reports_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS message_reports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT UNSIGNED NOT NULL,
            reporter_user_id BIGINT UNSIGNED NOT NULL,
            reported_user_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL DEFAULT 'Inappropriate message',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            resolution_action VARCHAR(40) NOT NULL DEFAULT 'none',
            punishment_until TIMESTAMP NULL DEFAULT NULL,
            moderator_note VARCHAR(255) NULL DEFAULT NULL,
            reviewed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_reporter_message (message_id, reporter_user_id),
            INDEX idx_reported_user (reported_user_id),
            INDEX idx_report_status (status),
            INDEX idx_report_reviewed_at (reviewed_at),
            INDEX idx_report_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $reportCols = [];
    $res = $conn->query('SHOW COLUMNS FROM message_reports');
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $field = strtolower((string)($row['Field'] ?? ''));
            if ($field !== '') {
                $reportCols[$field] = true;
            }
        }
        $res->free();
    }

    if (!isset($reportCols['status'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open' AFTER reason");
    }
    if (!isset($reportCols['resolution_action'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN resolution_action VARCHAR(40) NOT NULL DEFAULT 'none' AFTER status");
    }
    if (!isset($reportCols['punishment_until'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN punishment_until TIMESTAMP NULL DEFAULT NULL AFTER resolution_action");
    }
    if (!isset($reportCols['moderator_note'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN moderator_note VARCHAR(255) NULL DEFAULT NULL AFTER punishment_until");
    }
    if (!isset($reportCols['reviewed_by_user_id'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN reviewed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER moderator_note");
    }
    if (!isset($reportCols['reviewed_at'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by_user_id");
    }
}

function chat_ensure_user_penalties_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS chat_user_penalties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(40) NOT NULL DEFAULT 'warning',
            reason VARCHAR(255) NULL DEFAULT NULL,
            moderator_note VARCHAR(255) NULL DEFAULT NULL,
            starts_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            ends_at TIMESTAMP NULL DEFAULT NULL,
            created_by_user_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_chat_penalty_report (report_id),
            INDEX idx_chat_penalty_user_active (user_id, is_active),
            INDEX idx_chat_penalty_action (action),
            INDEX idx_chat_penalty_ends (ends_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function chat_active_penalty(mysqli $conn, int $userId): ?array
{
    chat_ensure_user_penalties_table($conn);
    $stmt = $conn->prepare("SELECT action, reason, moderator_note, ends_at
        FROM chat_user_penalties
        WHERE user_id = ?
          AND is_active = 1
          AND action IN ('mute_chat', 'restrict_chat', 'suspend_chat')
          AND (ends_at IS NULL OR ends_at > NOW())
        ORDER BY CASE action WHEN 'suspend_chat' THEN 1 WHEN 'mute_chat' THEN 2 WHEN 'restrict_chat' THEN 3 ELSE 4 END, id DESC
        LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function chat_penalty_error_message(array $penalty, string $recipientRole = ''): string
{
    $action = strtolower(trim((string)($penalty['action'] ?? '')));
    $until = trim((string)($penalty['ends_at'] ?? ''));
    $suffix = $until !== '' ? ' until ' . $until : '';

    if ($action === 'restrict_chat' && chat_normalize_role($recipientRole) === 'student') {
        return 'Your chat is restricted' . $suffix . '. You can message staff, but not students.';
    }
    if ($action === 'mute_chat') {
        return 'You are muted from sending chat messages' . $suffix . '.';
    }
    if ($action === 'suspend_chat') {
        return 'Your chat access is suspended' . $suffix . '.';
    }

    return '';
}

function chat_message_meta(mysqli $conn): array
{
    chat_ensure_messages_table($conn);
    chat_ensure_message_media_table($conn);
    chat_ensure_message_reactions_table($conn);
    chat_ensure_message_pins_table($conn);
    chat_ensure_message_reports_table($conn);
    chat_ensure_user_penalties_table($conn);

    $columns = [];
    $res = $conn->query('SHOW COLUMNS FROM messages');
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $field = strtolower((string)($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
        $res->free();
    }

    $senderCol = isset($columns['from_user_id']) ? 'from_user_id' : (isset($columns['sender_id']) ? 'sender_id' : '');
    $recipientCol = isset($columns['to_user_id']) ? 'to_user_id' : (isset($columns['recipient_id']) ? 'recipient_id' : '');

    return [
        'ready' => $senderCol !== '' && $recipientCol !== '' && isset($columns['message']) && isset($columns['id']),
        'sender_col' => $senderCol,
        'recipient_col' => $recipientCol,
        'id_col' => isset($columns['id']) ? 'id' : '',
        'subject_col' => isset($columns['subject']) ? 'subject' : '',
        'message_type_col' => isset($columns['message_type']) ? 'message_type' : '',
        'reply_to_col' => isset($columns['reply_to_message_id']) ? 'reply_to_message_id' : '',
        'media_path_col' => isset($columns['media_path']) ? 'media_path' : '',
        'reaction_emoji_col' => isset($columns['reaction_emoji']) ? 'reaction_emoji' : '',
        'reaction_by_col' => isset($columns['reaction_by_user_id']) ? 'reaction_by_user_id' : '',
        'is_read_col' => isset($columns['is_read']) ? 'is_read' : '',
        'read_at_col' => isset($columns['read_at']) ? 'read_at' : '',
        'created_at_col' => isset($columns['created_at']) ? 'created_at' : '',
        'updated_at_col' => isset($columns['updated_at']) ? 'updated_at' : '',
        'deleted_at_col' => isset($columns['deleted_at']) ? 'deleted_at' : '',
        'media_table_ready' => chat_has_table($conn, 'chat_message_media'),
        'reactions_ready' => chat_has_table($conn, 'message_reactions'),
        'pins_ready' => chat_has_table($conn, 'message_pins'),
        'reports_ready' => chat_has_table($conn, 'message_reports'),
    ];
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$currentUserRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$isStudentChatUser = ($currentUserRole === 'student');
$studentScope = $isStudentChatUser ? chat_student_scope($conn, $currentUserId) : null;
$studentScopeErrorMessage = 'Students can only chat with supervisors and students under the same supervisor.';
$studentScopeMissingMessage = 'Your student profile needs an assigned supervisor before student chat can appear.';

$page_title = 'BioTern || Chat';
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$isAjaxRequest = ((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || ((string)($_REQUEST['ajax'] ?? '') === '1');

$messageMeta = chat_message_meta($conn);
$selectedUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : (int)($_GET['user_id'] ?? 0);
$draftMessage = '';
$errorMessage = '';
$successMessage = '';
$composeWarningMessage = '';

if (isset($_SESSION['chat_flash']) && is_array($_SESSION['chat_flash'])) {
    $successMessage = (string)($_SESSION['chat_flash']['success'] ?? '');
    $errorMessage = (string)($_SESSION['chat_flash']['error'] ?? '');
    unset($_SESSION['chat_flash']);
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'delete-conversation' && $messageMeta['ready']) {
    $selectedUserId = (int)($_POST['user_id'] ?? 0);

    if ($selectedUserId <= 0) {
        $errorMessage = 'Select a conversation first.';
    } else {
        if ($messageMeta['deleted_at_col'] !== '') {
            $deleteSql = 'UPDATE messages
                SET ' . $messageMeta['deleted_at_col'] . ' = NOW()' . ($messageMeta['updated_at_col'] !== '' ? ', ' . $messageMeta['updated_at_col'] . ' = NOW()' : '') . '
                WHERE ((' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?)
                    OR (' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?))
                  AND ' . $messageMeta['deleted_at_col'] . ' IS NULL';
        } else {
            $deleteSql = 'DELETE FROM messages
                WHERE ((' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?)
                    OR (' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?))';
        }

        $deleteStmt = $conn->prepare($deleteSql);
        if (!$deleteStmt) {
            $errorMessage = 'Failed to prepare delete query.';
        } else {
            $deleteStmt->bind_param('iiii', $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
            $ok = $deleteStmt->execute();
            $deleteStmt->close();
            if ($ok) {
                $successMessage = 'Conversation deleted.';
                if (!$isAjaxRequest) {
                    $_SESSION['chat_flash'] = ['success' => $successMessage];
                    header('Location: ' . chat_page_url($selectedUserId));
                    exit;
                }
            } else {
                $errorMessage = 'Failed to delete conversation.';
            }
        }
    }
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'unsend-message' && $messageMeta['ready']) {
    $selectedUserId = (int)($_POST['user_id'] ?? 0);
    $messageId = (int)($_POST['message_id'] ?? 0);

    if ($selectedUserId <= 0 || $messageId <= 0) {
        $errorMessage = 'Invalid message request.';
    } else {
        if ($messageMeta['deleted_at_col'] !== '') {
            $unsendSql = 'UPDATE messages
                SET ' . $messageMeta['deleted_at_col'] . ' = NOW()' . ($messageMeta['updated_at_col'] !== '' ? ', ' . $messageMeta['updated_at_col'] . ' = NOW()' : '') . '
                WHERE ' . $messageMeta['id_col'] . ' = ?
                                    AND ' . $messageMeta['sender_col'] . ' = ?
                  AND ' . $messageMeta['deleted_at_col'] . ' IS NULL';
        } else {
                        $unsetMediaSql = $messageMeta['media_path_col'] !== '' ? ', ' . $messageMeta['media_path_col'] . ' = NULL' : '';
                        $unsetReplySql = $messageMeta['reply_to_col'] !== '' ? ', ' . $messageMeta['reply_to_col'] . ' = NULL' : '';
                        $unsetSubjectSql = $messageMeta['subject_col'] !== '' ? ', ' . $messageMeta['subject_col'] . ' = NULL' : '';
                        $unsendSql = 'UPDATE messages
                                SET message = ?' . $unsetMediaSql . $unsetReplySql . $unsetSubjectSql . ($messageMeta['updated_at_col'] !== '' ? ', ' . $messageMeta['updated_at_col'] . ' = NOW()' : '') . '
                                WHERE ' . $messageMeta['id_col'] . ' = ?
                                    AND ' . $messageMeta['sender_col'] . ' = ?';
        }

        $unsendStmt = $conn->prepare($unsendSql);
        if (!$unsendStmt) {
            $errorMessage = 'Failed to prepare unsend query.';
        } else {
            if ($messageMeta['deleted_at_col'] !== '') {
                $unsendStmt->bind_param('ii', $messageId, $currentUserId);
            } else {
                $unsentMarker = chat_unsent_marker();
                $unsendStmt->bind_param('sii', $unsentMarker, $messageId, $currentUserId);
            }
            $ok = $unsendStmt->execute();
            $affected = $unsendStmt->affected_rows;
            $unsendStmt->close();

            if ($ok && $affected > 0) {
                if (!empty($messageMeta['pins_ready'])) {
                    $cleanupPinStmt = $conn->prepare('DELETE FROM message_pins WHERE message_id = ?');
                    if ($cleanupPinStmt) {
                        $cleanupPinStmt->bind_param('i', $messageId);
                        $cleanupPinStmt->execute();
                        $cleanupPinStmt->close();
                    }
                }
                $successMessage = 'Message unsent.';
            } else {
                $errorMessage = 'Unable to unsend this message.';
            }
        }
    }
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'remove-message' && $messageMeta['ready']) {
    $selectedUserId = (int)($_POST['user_id'] ?? 0);
    $messageId = (int)($_POST['message_id'] ?? 0);

    if ($selectedUserId <= 0 || $messageId <= 0) {
        $errorMessage = 'Invalid message request.';
    } else {
        if ($messageMeta['deleted_at_col'] !== '') {
            $removeSql = 'DELETE FROM messages
                WHERE ' . $messageMeta['id_col'] . ' = ?
                  AND ' . $messageMeta['sender_col'] . ' = ?
                  AND ' . $messageMeta['deleted_at_col'] . ' IS NOT NULL';
            $removeStmt = $conn->prepare($removeSql);
            if (!$removeStmt) {
                $errorMessage = 'Failed to prepare remove query.';
            } else {
                $removeStmt->bind_param('ii', $messageId, $currentUserId);
                $ok = $removeStmt->execute();
                $affected = $removeStmt->affected_rows;
                $removeStmt->close();
                if ($ok && $affected > 0) {
                    if (!empty($messageMeta['pins_ready'])) {
                        $cleanupPinStmt = $conn->prepare('DELETE FROM message_pins WHERE message_id = ?');
                        if ($cleanupPinStmt) {
                            $cleanupPinStmt->bind_param('i', $messageId);
                            $cleanupPinStmt->execute();
                            $cleanupPinStmt->close();
                        }
                    }
                    if (!empty($messageMeta['reactions_ready'])) {
                        $cleanupStmt = $conn->prepare('DELETE FROM message_reactions WHERE message_id = ?');
                        if ($cleanupStmt) {
                            $cleanupStmt->bind_param('i', $messageId);
                            $cleanupStmt->execute();
                            $cleanupStmt->close();
                        }
                    }
                    $successMessage = 'Message removed.';
                } else {
                    $errorMessage = 'Unable to remove this message.';
                }
            }
        } else {
            $removeSql = 'DELETE FROM messages
                WHERE ' . $messageMeta['id_col'] . ' = ?
                  AND ' . $messageMeta['sender_col'] . ' = ?
                  AND message = ?';
            $removeStmt = $conn->prepare($removeSql);
            if (!$removeStmt) {
                $errorMessage = 'Failed to prepare remove query.';
            } else {
                $unsentMarker = chat_unsent_marker();
                $removeStmt->bind_param('iis', $messageId, $currentUserId, $unsentMarker);
                $ok = $removeStmt->execute();
                $affected = $removeStmt->affected_rows;
                $removeStmt->close();
                if ($ok && $affected > 0) {
                    if (!empty($messageMeta['pins_ready'])) {
                        $cleanupPinStmt = $conn->prepare('DELETE FROM message_pins WHERE message_id = ?');
                        if ($cleanupPinStmt) {
                            $cleanupPinStmt->bind_param('i', $messageId);
                            $cleanupPinStmt->execute();
                            $cleanupPinStmt->close();
                        }
                    }
                    if (!empty($messageMeta['reactions_ready'])) {
                        $cleanupStmt = $conn->prepare('DELETE FROM message_reactions WHERE message_id = ?');
                        if ($cleanupStmt) {
                            $cleanupStmt->bind_param('i', $messageId);
                            $cleanupStmt->execute();
                            $cleanupStmt->close();
                        }
                    }
                    $successMessage = 'Message removed.';
                } else {
                    $errorMessage = 'Unable to remove this message.';
                }
            }
        }
    }
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'react-message' && $messageMeta['ready']) {
    $selectedUserId = (int)($_POST['user_id'] ?? 0);
    $messageId = (int)($_POST['message_id'] ?? 0);
    $reactionEmoji = trim((string)($_POST['reaction_emoji'] ?? ''));

    if ($selectedUserId <= 0 || $messageId <= 0) {
        $errorMessage = 'Invalid reaction request.';
    } elseif (empty($messageMeta['reactions_ready'])) {
        $errorMessage = 'Reactions are not available.';
    } else {
        if (function_exists('mb_substr')) {
            $reactionEmoji = mb_substr($reactionEmoji, 0, 8);
        } else {
            $reactionEmoji = substr($reactionEmoji, 0, 16);
        }
        $reactionEmoji = chat_normalize_reaction_emoji($reactionEmoji);
        $supportedReactionEmoji = array_values(chat_supported_reactions());

        if ($reactionEmoji !== '' && !in_array($reactionEmoji, $supportedReactionEmoji, true)) {
            $errorMessage = 'Only the standard chat reactions are allowed.';
        }

        $checkSql = 'SELECT ' . $messageMeta['id_col'] . ' AS message_id
            FROM messages
            WHERE ' . $messageMeta['id_col'] . ' = ?
              AND ((' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?)
                OR (' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?))'
              . ($messageMeta['deleted_at_col'] !== '' ? ' AND ' . $messageMeta['deleted_at_col'] . ' IS NULL' : '') . '
            LIMIT 1';
        $checkStmt = $errorMessage === '' ? $conn->prepare($checkSql) : null;
        if ($errorMessage === '' && !$checkStmt) {
            $errorMessage = 'Failed to validate reaction target.';
        } elseif ($errorMessage === '') {
            $checkStmt->bind_param('iiiii', $messageId, $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
            $checkStmt->execute();
            $hasMessage = (bool)$checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$hasMessage) {
                $errorMessage = 'Message not found in this conversation.';
            } else {
                $existingEmoji = '';
                $existingStmt = $conn->prepare('SELECT emoji FROM message_reactions WHERE message_id = ? AND user_id = ? LIMIT 1');
                if ($existingStmt) {
                    $existingStmt->bind_param('ii', $messageId, $currentUserId);
                    $existingStmt->execute();
                    $existingRow = $existingStmt->get_result()->fetch_assoc();
                    $existingStmt->close();
                    $existingEmoji = chat_normalize_reaction_emoji((string)($existingRow['emoji'] ?? ''));
                }

                if ($reactionEmoji === '' || $reactionEmoji === $existingEmoji) {
                    $deleteReactionStmt = $conn->prepare('DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?');
                    if (!$deleteReactionStmt) {
                        $errorMessage = 'Failed to remove reaction.';
                    } else {
                        $deleteReactionStmt->bind_param('ii', $messageId, $currentUserId);
                        $ok = $deleteReactionStmt->execute();
                        $deleteReactionStmt->close();
                        if ($ok) {
                            $successMessage = 'Reaction removed.';
                        } else {
                            $errorMessage = 'Failed to remove reaction.';
                        }
                    }
                } else {
                    $upsertReactionStmt = $conn->prepare('INSERT INTO message_reactions (message_id, user_id, emoji, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), updated_at = NOW()');
                    if (!$upsertReactionStmt) {
                        $errorMessage = 'Failed to save reaction.';
                    } else {
                        $upsertReactionStmt->bind_param('iis', $messageId, $currentUserId, $reactionEmoji);
                        $ok = $upsertReactionStmt->execute();
                        $upsertReactionStmt->close();
                        if ($ok) {
                            $successMessage = 'Reaction sent.';
                        } else {
                            $errorMessage = 'Failed to save reaction.';
                        }
                    }
                }
            }
        }
    }
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'toggle-pin' && $messageMeta['ready']) {
    $selectedUserId = (int)($_POST['user_id'] ?? 0);
    $messageId = (int)($_POST['message_id'] ?? 0);
    $pinState = trim(strtolower((string)($_POST['pin_state'] ?? '')));
    $shouldPin = $pinState !== '0' && $pinState !== 'false' && $pinState !== 'off' && $pinState !== 'unpin';

    if ($selectedUserId <= 0 || $messageId <= 0) {
        $errorMessage = 'Invalid pin request.';
    } elseif (empty($messageMeta['pins_ready'])) {
        $errorMessage = 'Pinning is not available.';
    } else {
        $checkSql = 'SELECT ' . $messageMeta['id_col'] . ' AS message_id
            FROM messages
            WHERE ' . $messageMeta['id_col'] . ' = ?
              AND ((' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?)
                OR (' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?))'
              . ($messageMeta['deleted_at_col'] !== '' ? ' AND ' . $messageMeta['deleted_at_col'] . ' IS NULL' : ' AND message <> ?') . '
            LIMIT 1';
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            $errorMessage = 'Failed to validate pin target.';
        } else {
            if ($messageMeta['deleted_at_col'] !== '') {
                $checkStmt->bind_param('iiiii', $messageId, $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
            } else {
                $unsentMarker = chat_unsent_marker();
                $checkStmt->bind_param('iiiiis', $messageId, $currentUserId, $selectedUserId, $selectedUserId, $currentUserId, $unsentMarker);
            }
            $checkStmt->execute();
            $hasMessage = (bool)$checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$hasMessage) {
                $errorMessage = 'Message not found in this conversation.';
            } elseif ($shouldPin) {
                $pinStmt = $conn->prepare('INSERT INTO message_pins (message_id, pinned_by_user_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE pinned_by_user_id = VALUES(pinned_by_user_id), updated_at = NOW()');
                if (!$pinStmt) {
                    $errorMessage = 'Failed to pin message.';
                } else {
                    $pinStmt->bind_param('ii', $messageId, $currentUserId);
                    $ok = $pinStmt->execute();
                    $pinStmt->close();
                    if ($ok) {
                        $successMessage = 'Message pinned.';
                    } else {
                        $errorMessage = 'Failed to pin message.';
                    }
                }
            } else {
                $unpinStmt = $conn->prepare('DELETE FROM message_pins WHERE message_id = ?');
                if (!$unpinStmt) {
                    $errorMessage = 'Failed to unpin message.';
                } else {
                    $unpinStmt->bind_param('i', $messageId);
                    $ok = $unpinStmt->execute();
                    $unpinStmt->close();
                    if ($ok) {
                        $successMessage = 'Message unpinned.';
                    } else {
                        $errorMessage = 'Failed to unpin message.';
                    }
                }
            }
        }
    }
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'report-message' && $messageMeta['ready']) {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $reportReason = trim((string)($_POST['reason'] ?? ''));

    if ($messageId <= 0) {
        $errorMessage = 'Invalid report request.';
    } elseif (empty($messageMeta['reports_ready'])) {
        $errorMessage = 'Reporting is not available.';
    } else {
        $checkSql = 'SELECT ' . $messageMeta['id_col'] . ' AS message_id, ' . $messageMeta['sender_col'] . ' AS sender_id, ' . $messageMeta['recipient_col'] . ' AS recipient_id
            FROM messages
            WHERE ' . $messageMeta['id_col'] . ' = ?
              AND (' . $messageMeta['sender_col'] . ' = ? OR ' . $messageMeta['recipient_col'] . ' = ?)'
              . ($messageMeta['deleted_at_col'] !== '' ? ' AND ' . $messageMeta['deleted_at_col'] . ' IS NULL' : ' AND message <> ?') . '
            LIMIT 1';
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            $errorMessage = 'Failed to validate report target.';
        } else {
            if ($messageMeta['deleted_at_col'] !== '') {
                $checkStmt->bind_param('iii', $messageId, $currentUserId, $currentUserId);
            } else {
                $unsentMarker = chat_unsent_marker();
                $checkStmt->bind_param('iiis', $messageId, $currentUserId, $currentUserId, $unsentMarker);
            }
            $checkStmt->execute();
            $targetRow = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$targetRow) {
                $errorMessage = 'Message not found in this conversation.';
            } else {
                $reportedUserId = (int)($targetRow['sender_id'] ?? 0);
                if ($reportedUserId <= 0 || $reportedUserId === $currentUserId) {
                    $errorMessage = 'This message cannot be reported.';
                } else {
                    if ($reportReason === '') {
                        $reportReason = 'Inappropriate message';
                    }
                    if (function_exists('mb_substr')) {
                        $reportReason = mb_substr($reportReason, 0, 255, 'UTF-8');
                    } else {
                        $reportReason = substr($reportReason, 0, 255);
                    }

                    $reportStmt = $conn->prepare("INSERT INTO message_reports (message_id, reporter_user_id, reported_user_id, reason, status, resolution_action, punishment_until, moderator_note, reviewed_by_user_id, reviewed_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', 'none', NULL, NULL, NULL, NULL, NOW(), NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'open', resolution_action = 'none', punishment_until = NULL, moderator_note = NULL, reviewed_by_user_id = NULL, reviewed_at = NULL, updated_at = NOW()");
                    if (!$reportStmt) {
                        $errorMessage = 'Failed to submit report.';
                    } else {
                        $reportStmt->bind_param('iiis', $messageId, $currentUserId, $reportedUserId, $reportReason);
                        $ok = $reportStmt->execute();
                        $reportStmt->close();
                        if ($ok) {
                            $successMessage = 'Message reported.';
                        } else {
                            $errorMessage = 'Failed to submit report.';
                        }
                    }
                }
            }
        }
    }
}

if ($requestMethod === 'POST' && (string)($_POST['action'] ?? '') === 'send-message' && $messageMeta['ready']) {
    $selectedUserId = (int)($_POST['user_id'] ?? 0);
    $plainDraftMessage = trim((string)($_POST['message'] ?? ''));
    $draftMessage = $plainDraftMessage;
    $replyToMessageId = (int)($_POST['reply_to_message_id'] ?? 0);
    $messageModerationError = $plainDraftMessage !== '' ? chat_moderation_error($plainDraftMessage) : '';
    $composeWarningMessage = '';

    // Handle optional media upload
    $uploadedMediaPath = '';
    $mediaUploadError = '';
    $uploadedMediaBlob = '';
    $uploadedMediaMime = '';
    $uploadedMediaName = '';
    $uploadedMediaSize = 0;
    if (!empty($_FILES['chat_media']['name'])) {
        $file = $_FILES['chat_media'];
        $imageMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $allowedMime = $imageMime;
        $maxImageSize = 10 * 1024 * 1024; // 10 MB
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $mediaUploadError = 'File upload failed (code ' . (int)$file['error'] . ').';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMime, true)) {
                $mediaUploadError = 'Only image files are allowed.';
            } elseif (in_array($mime, $imageMime, true) && $file['size'] > $maxImageSize) {
                $mediaUploadError = 'Image is too large (max 10 MB).';
            } else {
                $blob = @file_get_contents((string)$file['tmp_name']);
                if (!is_string($blob) || $blob === '') {
                    $mediaUploadError = 'Could not read the uploaded file.';
                } else {
                    $uploadedMediaBlob = $blob;
                    $uploadedMediaMime = (string)$mime;
                    $uploadedMediaName = trim((string)($file['name'] ?? 'image'));
                    $uploadedMediaSize = (int)($file['size'] ?? strlen($blob));
                    $uploadedMediaPath = '__chat_media_pending__';
                    if ($draftMessage === '') {
                        $draftMessage = $uploadedMediaName !== '' ? $uploadedMediaName : 'Image';
                    }
                }
            }
        }
        if ($mediaUploadError !== '') {
            $errorMessage = $mediaUploadError;
        }
    }

    if ($selectedUserId <= 0) {
        $errorMessage = 'Select a recipient before sending a message.';
    } elseif ($messageModerationError !== '') {
        $errorMessage = $messageModerationError;
        $composeWarningMessage = $messageModerationError;
    } elseif ($draftMessage === '' && $uploadedMediaPath === '') {
        $errorMessage = 'Message cannot be empty.';
    } elseif ($errorMessage === '') {
        $recipientStmt = $conn->prepare("SELECT id, name, COALESCE(role, '') AS role FROM users WHERE id = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
        $recipient = null;
        if ($recipientStmt) {
            $recipientStmt->bind_param('i', $selectedUserId);
            $recipientStmt->execute();
            $recipient = $recipientStmt->get_result()->fetch_assoc();
            $recipientStmt->close();
        }

        $activeChatPenalty = chat_active_penalty($conn, $currentUserId);
        $recipientRole = $recipient ? chat_normalize_role((string)($recipient['role'] ?? '')) : '';

        if (!$recipient) {
            $errorMessage = 'The selected recipient was not found.';
        }

        if ($errorMessage === '' && $activeChatPenalty) {
            $penaltyError = chat_penalty_error_message($activeChatPenalty, $recipientRole);
            if ($penaltyError !== '') {
                $errorMessage = $penaltyError;
            }
        }

        if ($errorMessage === '' && $isStudentChatUser) {
            if (!$studentScope) {
                $errorMessage = $studentScopeMissingMessage;
            } elseif (!chat_student_can_contact_user($conn, $studentScope, $selectedUserId)) {
                $hasStudentSupervisor = (int)($studentScope['supervisor_user_id'] ?? 0) > 0;
                $errorMessage = (!$hasStudentSupervisor && $recipientRole === 'student')
                    ? $studentScopeMissingMessage
                    : $studentScopeErrorMessage;
            }
        }

        if ($errorMessage === '') {
            if ($replyToMessageId > 0 && $messageMeta['id_col'] !== '') {
                $replyCheckSql = 'SELECT ' . $messageMeta['id_col'] . ' AS id FROM messages
                    WHERE ' . $messageMeta['id_col'] . ' = ?
                      AND ((' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?)
                        OR (' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?))'
                      . ($messageMeta['deleted_at_col'] !== '' ? ' AND ' . $messageMeta['deleted_at_col'] . ' IS NULL' : '') . '
                    LIMIT 1';
                $replyCheckStmt = $conn->prepare($replyCheckSql);
                if ($replyCheckStmt) {
                    $replyCheckStmt->bind_param('iiiii', $replyToMessageId, $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
                    $replyCheckStmt->execute();
                    $replyFound = (bool)$replyCheckStmt->get_result()->fetch_assoc();
                    $replyCheckStmt->close();
                    if (!$replyFound) {
                        $replyToMessageId = 0;
                    }
                } else {
                    $replyToMessageId = 0;
                }
            }

            $insertColumns = [$messageMeta['sender_col'], $messageMeta['recipient_col'], 'message'];
            $insertValues = ['?', '?', '?'];
            $bindTypes = 'iis';
            $bindValues = [$currentUserId, $selectedUserId, $draftMessage];

            if ($messageMeta['reply_to_col'] !== '' && $replyToMessageId > 0) {
                $insertColumns[] = $messageMeta['reply_to_col'];
                $insertValues[] = '?';
                $bindTypes .= 'i';
                $bindValues[] = $replyToMessageId;
            }

            if ($messageMeta['media_path_col'] !== '' && $uploadedMediaPath !== '') {
                $insertColumns[] = $messageMeta['media_path_col'];
                $insertValues[] = '?';
                $bindTypes .= 's';
                $bindValues[] = $uploadedMediaPath;
            }

            if ($messageMeta['subject_col'] !== '') {
                $insertColumns[] = $messageMeta['subject_col'];
                $insertValues[] = '?';
                $bindTypes .= 's';
                $bindValues[] = 'BioTern Chat';
            }

            if ($messageMeta['message_type_col'] !== '') {
                $insertColumns[] = $messageMeta['message_type_col'];
                $insertValues[] = '?';
                $bindTypes .= 's';
                $bindValues[] = 'general';
            }

            if ($messageMeta['is_read_col'] !== '') {
                $insertColumns[] = $messageMeta['is_read_col'];
                $insertValues[] = '0';
            }

            if ($messageMeta['read_at_col'] !== '') {
                $insertColumns[] = $messageMeta['read_at_col'];
                $insertValues[] = 'NULL';
            }

            if ($messageMeta['created_at_col'] !== '') {
                $insertColumns[] = $messageMeta['created_at_col'];
                $insertValues[] = 'NOW()';
            }

            if ($messageMeta['updated_at_col'] !== '') {
                $insertColumns[] = $messageMeta['updated_at_col'];
                $insertValues[] = 'NOW()';
            }

            $insertSql = 'INSERT INTO messages (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';
            $insertStmt = $conn->prepare($insertSql);

            if (!$insertStmt) {
                $errorMessage = 'Failed to prepare the message insert query.';
            } else {
                $usesBlobMedia = $uploadedMediaBlob !== '' && !empty($messageMeta['media_table_ready']);
                $executed = false;
                $insertErrNo = 0;
                $insertError = '';
                $messageId = 0;

                if ($usesBlobMedia) {
                    $conn->begin_transaction();
                }

                try {
                    $insertStmt->bind_param($bindTypes, ...$bindValues);
                    $executed = $insertStmt->execute();
                    $insertErrNo = (int)$insertStmt->errno;
                    $insertError = (string)$insertStmt->error;
                    $messageId = (int)$insertStmt->insert_id;
                    $insertStmt->close();

                    if ($executed && $usesBlobMedia) {
                        $mediaStmt = $conn->prepare('INSERT INTO chat_message_media (message_id, original_name, media_mime, media_data, media_size, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE original_name = VALUES(original_name), media_mime = VALUES(media_mime), media_data = VALUES(media_data), media_size = VALUES(media_size), updated_at = NOW()');
                        if (!$mediaStmt) {
                            throw new RuntimeException('Failed to prepare media save query.');
                        }
                        $null = null;
                        $mediaStmt->bind_param('issbi', $messageId, $uploadedMediaName, $uploadedMediaMime, $null, $uploadedMediaSize);
                        $mediaStmt->send_long_data(3, $uploadedMediaBlob);
                        if (!$mediaStmt->execute()) {
                            $mediaError = (string)$mediaStmt->error;
                            $mediaStmt->close();
                            throw new RuntimeException($mediaError !== '' ? $mediaError : 'Failed to save chat media.');
                        }
                        $mediaStmt->close();

                        $mediaUrl = chat_media_url($messageId);
                        $updateMediaStmt = $conn->prepare('UPDATE messages SET ' . $messageMeta['media_path_col'] . ' = ? WHERE ' . $messageMeta['id_col'] . ' = ? LIMIT 1');
                        if (!$updateMediaStmt) {
                            throw new RuntimeException('Failed to update chat media URL.');
                        }
                        $updateMediaStmt->bind_param('si', $mediaUrl, $messageId);
                        if (!$updateMediaStmt->execute()) {
                            $updateMediaError = (string)$updateMediaStmt->error;
                            $updateMediaStmt->close();
                            throw new RuntimeException($updateMediaError !== '' ? $updateMediaError : 'Failed to update chat media URL.');
                        }
                        $updateMediaStmt->close();
                    }

                    if ($usesBlobMedia) {
                        $conn->commit();
                    }
                } catch (Throwable $chatMediaException) {
                    if ($usesBlobMedia) {
                        $conn->rollback();
                    }
                    $executed = false;
                    $insertError = $chatMediaException->getMessage();
                }

                if ($executed) {
                    $successMessage = 'Message sent.';
                    if (!$isAjaxRequest) {
                        $_SESSION['chat_flash'] = ['success' => $successMessage];
                    }
                    if (function_exists('biotern_notify')) {
                        $senderDisplay = $currentUserName !== '' ? $currentUserName : 'A user';
                        $notificationPreview = preg_replace('/\s+/', ' ', strip_tags($draftMessage));
                        $notificationPreview = trim((string)$notificationPreview);
                        if ($notificationPreview === '' && $uploadedMediaPath !== '') {
                            $notificationPreview = 'Sent an attachment.';
                        }
                        if ($notificationPreview === '') {
                            $notificationPreview = 'You have a new chat message.';
                        } elseif (strlen($notificationPreview) > 160) {
                            $notificationPreview = substr($notificationPreview, 0, 157) . '...';
                        }
                        biotern_notify(
                            $conn,
                            $selectedUserId,
                            'New chat message from ' . $senderDisplay,
                            $notificationPreview,
                            'message',
                            chat_page_url($currentUserId)
                        );
                    }
                    if (!$isAjaxRequest) {
                        header('Location: ' . chat_page_url($selectedUserId));
                        exit;
                    }
                } else {
                    error_log('[BioTern Chat] send-message failed: sender=' . $currentUserId . ' recipient=' . $selectedUserId . ' errno=' . $insertErrNo . ' error=' . $insertError);
                    $errorMessage = 'Failed to send the message.';
                }
            }
        }
    }
}

$deletedMessageFilter = $messageMeta['deleted_at_col'] !== '' ? ' AND m.' . $messageMeta['deleted_at_col'] . ' IS NULL' : '';
$deletedConversationFilter = $messageMeta['deleted_at_col'] !== '' ? ' AND ' . $messageMeta['deleted_at_col'] . ' IS NULL' : '';

$contacts = [];
if ($currentUserId > 0 && $messageMeta['ready']) {
    $orderExpr = $messageMeta['created_at_col'] !== '' ? 'm.' . $messageMeta['created_at_col'] : 'm.' . $messageMeta['id_col'];
    $unreadSelect = '0 AS unread_count';
    $lastMediaSelect = "'' AS last_media_path";
    $contactTypes = 'iiiiii';
    $contactParams = [$currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId];

    if ($messageMeta['media_path_col'] !== '') {
        $lastMediaSelect = '(
                SELECT m.' . $messageMeta['media_path_col'] . '
                FROM messages m
                WHERE ((m.' . $messageMeta['sender_col'] . ' = ? AND m.' . $messageMeta['recipient_col'] . ' = u.id)
                    OR (m.' . $messageMeta['sender_col'] . ' = u.id AND m.' . $messageMeta['recipient_col'] . ' = ?))' . $deletedMessageFilter . '
                ORDER BY ' . $orderExpr . ' DESC, m.' . $messageMeta['id_col'] . ' DESC
                LIMIT 1
            ) AS last_media_path';
        $contactTypes .= 'ii';
        $contactParams[] = $currentUserId;
        $contactParams[] = $currentUserId;
    }

    if ($messageMeta['is_read_col'] !== '') {
        $unreadSelect = '(
                SELECT COUNT(*)
                FROM messages m
                WHERE m.' . $messageMeta['sender_col'] . ' = u.id
                  AND m.' . $messageMeta['recipient_col'] . ' = ?
                  AND COALESCE(m.' . $messageMeta['is_read_col'] . ', 0) = 0' . $deletedMessageFilter . '
            ) AS unread_count';
        $contactTypes .= 'i';
        $contactParams[] = $currentUserId;
    }

    $studentScopeFilterSql = '';
    if ($isStudentChatUser) {
        $studentScopeFilterSql = ' AND u.id <> ? AND (
            LOWER(TRIM(COALESCE(u.role, ""))) = "supervisor"
            OR (
                LOWER(TRIM(COALESCE(u.role, ""))) = "student"
                AND ? > 0
                AND COALESCE(contact_sup_by_user.user_id, contact_sup_by_id.user_id, student_sup_by_id.user_id, student_sup_by_user.user_id, 0) = ?
            )
        )';
        $contactTypes .= 'i';
        $contactParams[] = $currentUserId;
        $contactTypes .= 'ii';
        $contactParams[] = (int)($studentScope['supervisor_user_id'] ?? 0);
        $contactParams[] = (int)($studentScope['supervisor_user_id'] ?? 0);
    }

    $contactsSql = '
        SELECT
            u.id,
            u.name,
            u.username,
            u.email,
            COALESCE(u.role, "") AS role,
            u.profile_picture,
            (
                SELECT m.message
                FROM messages m
                WHERE ((m.' . $messageMeta['sender_col'] . ' = ? AND m.' . $messageMeta['recipient_col'] . ' = u.id)
                    OR (m.' . $messageMeta['sender_col'] . ' = u.id AND m.' . $messageMeta['recipient_col'] . ' = ?))' . $deletedMessageFilter . '
                ORDER BY ' . $orderExpr . ' DESC, m.' . $messageMeta['id_col'] . ' DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT ' . ($messageMeta['created_at_col'] !== '' ? 'm.' . $messageMeta['created_at_col'] : 'm.' . $messageMeta['id_col']) . '
                FROM messages m
                WHERE ((m.' . $messageMeta['sender_col'] . ' = ? AND m.' . $messageMeta['recipient_col'] . ' = u.id)
                    OR (m.' . $messageMeta['sender_col'] . ' = u.id AND m.' . $messageMeta['recipient_col'] . ' = ?))' . $deletedMessageFilter . '
                ORDER BY ' . $orderExpr . ' DESC, m.' . $messageMeta['id_col'] . ' DESC
                LIMIT 1
            ) AS last_message_at,
            (
                SELECT COUNT(*)
                FROM messages m
                WHERE ((m.' . $messageMeta['sender_col'] . ' = ? AND m.' . $messageMeta['recipient_col'] . ' = u.id)
                    OR (m.' . $messageMeta['sender_col'] . ' = u.id AND m.' . $messageMeta['recipient_col'] . ' = ?))' . $deletedMessageFilter . '
            ) AS message_count,
            ' . $lastMediaSelect . ',
            ' . $unreadSelect . '
        FROM users u
            LEFT JOIN students s_contact ON s_contact.user_id = u.id
            LEFT JOIN (
                SELECT i_full.*
                FROM internships i_full
                INNER JOIN (
                    SELECT student_id, MAX(id) AS latest_id
                    FROM internships
                    GROUP BY student_id
                ) i_latest ON i_latest.latest_id = i_full.id
            ) i_contact ON i_contact.student_id = s_contact.id
            LEFT JOIN supervisors contact_sup_by_id ON contact_sup_by_id.id = i_contact.supervisor_id
            LEFT JOIN supervisors contact_sup_by_user ON contact_sup_by_user.user_id = i_contact.supervisor_id
            LEFT JOIN supervisors student_sup_by_id ON student_sup_by_id.id = s_contact.supervisor_id
            LEFT JOIN supervisors student_sup_by_user ON student_sup_by_user.user_id = s_contact.supervisor_id
            WHERE (u.is_active = 1 OR u.is_active IS NULL)' . $studentScopeFilterSql . '
        ORDER BY
            CASE WHEN last_message_at IS NULL THEN 1 ELSE 0 END,
            last_message_at DESC,
            u.name ASC';

    $contactsStmt = $conn->prepare($contactsSql);
    if ($contactsStmt) {
        $contactsStmt->bind_param($contactTypes, ...$contactParams);
        $contactsStmt->execute();
        $contactsRes = $contactsStmt->get_result();
        while ($row = $contactsRes->fetch_assoc()) {
            $contacts[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? $row['username'] ?? 'Unknown User'),
                'username' => (string)($row['username'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'role' => (string)($row['role'] ?? ''),
                'profile_picture' => (string)($row['profile_picture'] ?? ''),
                'last_message' => (string)($row['last_message'] ?? ''),
                'last_media_path' => (string)($row['last_media_path'] ?? ''),
                'last_message_at' => (string)($row['last_message_at'] ?? ''),
                'message_count' => (int)($row['message_count'] ?? 0),
                'unread_count' => (int)($row['unread_count'] ?? 0),
            ];
        }
        $contactsStmt->close();
    }

    if (!empty($contacts)) {
        usort($contacts, static function (array $left, array $right) use ($isStudentChatUser): int {
            $leftGroup = chat_contact_group_meta((string)($left['role'] ?? ''), $isStudentChatUser);
            $rightGroup = chat_contact_group_meta((string)($right['role'] ?? ''), $isStudentChatUser);

            $leftOrder = (int)($leftGroup['order'] ?? 99);
            $rightOrder = (int)($rightGroup['order'] ?? 99);
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            $leftTs = strtotime((string)($left['last_message_at'] ?? '')) ?: 0;
            $rightTs = strtotime((string)($right['last_message_at'] ?? '')) ?: 0;
            if ($leftTs !== $rightTs) {
                return $rightTs <=> $leftTs;
            }

            return strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
        });
    }
}

$recentLoginUserIds = chat_fetch_recent_login_user_ids($conn);

if ($selectedUserId <= 0 && !empty($contacts)) {
    $selectedUserId = (int)$contacts[0]['id'];
}

$selectedContact = null;
if ($selectedUserId > 0) {
    foreach ($contacts as $contact) {
        if ((int)$contact['id'] === $selectedUserId) {
            $selectedContact = $contact;
            break;
        }
    }

    if ($isStudentChatUser && $selectedContact === null) {
        $selectedUserId = 0;
        if ($errorMessage === '') {
            $errorMessage = ($studentScope && (int)($studentScope['supervisor_user_id'] ?? 0) > 0)
                ? $studentScopeErrorMessage
                : $studentScopeMissingMessage;
        }
    }

    if ($selectedContact === null) {
        $selectedStmt = $conn->prepare('SELECT id, name, username, email, COALESCE(role, "") AS role, profile_picture FROM users WHERE id = ? LIMIT 1');
        if ($selectedStmt) {
            $selectedStmt->bind_param('i', $selectedUserId);
            $selectedStmt->execute();
            $selectedRow = $selectedStmt->get_result()->fetch_assoc();
            $selectedStmt->close();
            if ($selectedRow) {
                $selectedContact = [
                    'id' => (int)($selectedRow['id'] ?? 0),
                    'name' => (string)($selectedRow['name'] ?? $selectedRow['username'] ?? 'Unknown User'),
                    'username' => (string)($selectedRow['username'] ?? ''),
                    'email' => (string)($selectedRow['email'] ?? ''),
                    'role' => (string)($selectedRow['role'] ?? ''),
                    'profile_picture' => (string)($selectedRow['profile_picture'] ?? ''),
                    'last_message' => '',
                    'last_media_path' => '',
                    'last_message_at' => '',
                    'message_count' => 0,
                    'unread_count' => 0,
                ];
                array_unshift($contacts, $selectedContact);
            }
        }
    }
}

if ($selectedContact === null && !empty($contacts)) {
    $selectedContact = $contacts[0];
    $selectedUserId = (int)($selectedContact['id'] ?? 0);
}

if ($selectedContact && $messageMeta['is_read_col'] !== '') {
    $markReadSql = 'UPDATE messages SET ' . $messageMeta['is_read_col'] . ' = 1';
    if ($messageMeta['read_at_col'] !== '') {
        $markReadSql .= ', ' . $messageMeta['read_at_col'] . ' = NOW()';
    }
    $markReadSql .= ' WHERE ' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ? AND COALESCE(' . $messageMeta['is_read_col'] . ', 0) = 0' . $deletedConversationFilter;

    $markReadStmt = $conn->prepare($markReadSql);
    if ($markReadStmt) {
        $markReadStmt->bind_param('ii', $selectedUserId, $currentUserId);
        $markReadStmt->execute();
        $markReadStmt->close();
    }

    if (function_exists('biotern_notification_columns')) {
        $notificationColumns = biotern_notification_columns($conn);
        if (!empty($notificationColumns)) {
            $notificationWhere = 'user_id = ? AND is_read = 0';
            if (isset($notificationColumns['action_url'])) {
                $notificationWhere .= ' AND action_url = ?';
            }
            if (isset($notificationColumns['type'])) {
                $notificationWhere .= ' AND type = ?';
            } elseif (isset($notificationColumns['title'])) {
                $notificationWhere .= ' AND title = ?';
            }
            if (isset($notificationColumns['deleted_at'])) {
                $notificationWhere .= ' AND deleted_at IS NULL';
            }

            $notificationSql = 'UPDATE notifications SET is_read = 1 WHERE ' . $notificationWhere;
            $notificationStmt = $conn->prepare($notificationSql);
            if ($notificationStmt) {
                $chatActionUrl = chat_page_url($selectedUserId);
                if (isset($notificationColumns['action_url']) && isset($notificationColumns['type'])) {
                    $notificationType = 'message';
                    $notificationStmt->bind_param('iss', $currentUserId, $chatActionUrl, $notificationType);
                } elseif (isset($notificationColumns['action_url']) && isset($notificationColumns['title'])) {
                    $notificationTitle = 'New chat message';
                    $notificationStmt->bind_param('iss', $currentUserId, $chatActionUrl, $notificationTitle);
                } elseif (isset($notificationColumns['action_url'])) {
                    $notificationStmt->bind_param('is', $currentUserId, $chatActionUrl);
                } elseif (isset($notificationColumns['type'])) {
                    $notificationType = 'message';
                    $notificationStmt->bind_param('is', $currentUserId, $notificationType);
                } elseif (isset($notificationColumns['title'])) {
                    $notificationTitle = 'New chat message';
                    $notificationStmt->bind_param('is', $currentUserId, $notificationTitle);
                } else {
                    $notificationStmt->bind_param('i', $currentUserId);
                }
                $notificationStmt->execute();
                $notificationStmt->close();
            }
        }
    }
}

$conversationMessages = [];
if ($selectedContact && $messageMeta['ready']) {
    $orderPrimary = $messageMeta['created_at_col'] !== '' ? $messageMeta['created_at_col'] : $messageMeta['id_col'];
    $outerMessageIdExpr = 'messages.' . $messageMeta['id_col'];
    $legacyReactionCol = $messageMeta['reaction_emoji_col'] !== '' ? $messageMeta['reaction_emoji_col'] : 'NULL';
    $reactionEmojiSelect = ($messageMeta['reactions_ready'] ?? false)
        ? '(SELECT mr.emoji
            FROM message_reactions mr
            WHERE mr.message_id = ' . $outerMessageIdExpr . '
            GROUP BY mr.emoji
            ORDER BY COUNT(*) DESC, mr.emoji ASC
            LIMIT 1)'
        : $legacyReactionCol;
    $reactionCountSelect = ($messageMeta['reactions_ready'] ?? false)
        ? '(SELECT COUNT(*) FROM message_reactions mr_cnt WHERE mr_cnt.message_id = ' . $outerMessageIdExpr . ')'
        : '(CASE WHEN ' . $legacyReactionCol . ' IS NULL OR ' . $legacyReactionCol . " = '' THEN 0 ELSE 1 END)";
    $reactionBySelect = ($messageMeta['reactions_ready'] ?? false)
        ? '0'
        : ($messageMeta['reaction_by_col'] !== '' ? $messageMeta['reaction_by_col'] : 'NULL');

    $conversationSql = 'SELECT
            ' . $messageMeta['id_col'] . ' AS message_id,
            ' . $messageMeta['sender_col'] . ' AS sender_id,
            ' . $messageMeta['recipient_col'] . ' AS recipient_id,
            message,
            ' . ($messageMeta['reply_to_col'] !== '' ? $messageMeta['reply_to_col'] : 'NULL') . ' AS reply_to_message_id,
            ' . ($messageMeta['subject_col'] !== '' ? $messageMeta['subject_col'] : 'NULL') . ' AS subject,
            ' . ($messageMeta['media_path_col'] !== '' ? $messageMeta['media_path_col'] : 'NULL') . ' AS media_path,
            ' . $reactionEmojiSelect . ' AS reaction_emoji,
            ' . $reactionBySelect . ' AS reaction_by_user_id,
            ' . $reactionCountSelect . ' AS reaction_count,
            ' . (!empty($messageMeta['pins_ready']) ? '(CASE WHEN EXISTS (SELECT 1 FROM message_pins mp WHERE mp.message_id = ' . $outerMessageIdExpr . ') THEN 1 ELSE 0 END)' : '0') . ' AS is_pinned,
            ' . ($messageMeta['is_read_col'] !== '' ? $messageMeta['is_read_col'] : '0') . ' AS is_read,
            ' . ($messageMeta['read_at_col'] !== '' ? $messageMeta['read_at_col'] : 'NULL') . ' AS read_at,
            ' . ($messageMeta['created_at_col'] !== '' ? $messageMeta['created_at_col'] : 'NULL') . ' AS created_at,
            ' . ($messageMeta['deleted_at_col'] !== '' ? $messageMeta['deleted_at_col'] : 'NULL') . ' AS unsent_at
        FROM messages
        WHERE ((' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?)
            OR (' . $messageMeta['sender_col'] . ' = ? AND ' . $messageMeta['recipient_col'] . ' = ?))
        ORDER BY ' . $orderPrimary . ' ASC, ' . $messageMeta['id_col'] . ' ASC
        LIMIT 200';

    $conversationStmt = $conn->prepare($conversationSql);
    if ($conversationStmt) {
        $conversationStmt->bind_param('iiii', $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
        $conversationStmt->execute();
        $conversationRes = $conversationStmt->get_result();
        while ($row = $conversationRes->fetch_assoc()) {
            $conversationMessages[] = [
                'message_id' => (int)($row['message_id'] ?? 0),
                'sender_id' => (int)($row['sender_id'] ?? 0),
                'recipient_id' => (int)($row['recipient_id'] ?? 0),
                'reply_to_message_id' => (int)($row['reply_to_message_id'] ?? 0),
                'message' => (string)($row['message'] ?? ''),
                'subject' => (string)($row['subject'] ?? ''),
                'media_path' => (string)($row['media_path'] ?? ''),
                'reaction_emoji' => chat_normalize_reaction_emoji((string)($row['reaction_emoji'] ?? '')),
                'reaction_by_user_id' => (int)($row['reaction_by_user_id'] ?? 0),
                'reaction_count' => (int)($row['reaction_count'] ?? 0),
                'is_pinned' => (int)($row['is_pinned'] ?? 0),
                'is_read' => (int)($row['is_read'] ?? 0),
                'read_at' => (string)($row['read_at'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'unsent_at' => (string)($row['unsent_at'] ?? ''),
            ];
        }
        $conversationStmt->close();
    }

    if (!empty($messageMeta['reactions_ready']) && !empty($conversationMessages)) {
        $messageIds = [];
        foreach ($conversationMessages as $conversationMessage) {
            $messageId = (int)($conversationMessage['message_id'] ?? 0);
            if ($messageId > 0) {
                $messageIds[$messageId] = $messageId;
            }
        }

        if (!empty($messageIds)) {
            $messageIds = array_values($messageIds);
            $placeholders = implode(', ', array_fill(0, count($messageIds), '?'));
            $reactionSql = 'SELECT mr.message_id, mr.user_id, mr.emoji, mr.updated_at, u.name, u.username, u.profile_picture
                FROM message_reactions mr
                LEFT JOIN users u ON u.id = mr.user_id
                WHERE mr.message_id IN (' . $placeholders . ')
                ORDER BY mr.message_id ASC, mr.updated_at ASC, mr.id ASC';
            $reactionStmt = $conn->prepare($reactionSql);
            if ($reactionStmt) {
                $reactionStmt->bind_param(str_repeat('i', count($messageIds)), ...$messageIds);
                $reactionStmt->execute();
                $reactionRes = $reactionStmt->get_result();

                $reactionSummaryByMessage = [];
                $reactionUsersByMessage = [];
                while ($reactionRow = $reactionRes->fetch_assoc()) {
                    $messageId = (int)($reactionRow['message_id'] ?? 0);
                    $emoji = chat_normalize_reaction_emoji((string)($reactionRow['emoji'] ?? ''));
                    if ($messageId <= 0 || $emoji === '') {
                        continue;
                    }

                    if (!isset($reactionSummaryByMessage[$messageId])) {
                        $reactionSummaryByMessage[$messageId] = [];
                    }
                    if (!isset($reactionSummaryByMessage[$messageId][$emoji])) {
                        $reactionSummaryByMessage[$messageId][$emoji] = 0;
                    }
                    $reactionSummaryByMessage[$messageId][$emoji]++;

                    $reactionUserId = (int)($reactionRow['user_id'] ?? 0);
                    $reactionUserName = trim((string)($reactionRow['name'] ?? ''));
                    if ($reactionUserName === '') {
                        $reactionUserName = trim((string)($reactionRow['username'] ?? ''));
                    }
                    if ($reactionUserName === '') {
                        $reactionUserName = 'Unknown user';
                    }

                    if (!isset($reactionUsersByMessage[$messageId])) {
                        $reactionUsersByMessage[$messageId] = [];
                    }
                    $reactionUsersByMessage[$messageId][] = [
                        'user_id' => $reactionUserId,
                        'name' => $reactionUserName,
                        'profile_picture' => (string)($reactionRow['profile_picture'] ?? ''),
                        'emoji' => $emoji,
                    ];
                }
                $reactionStmt->close();

                foreach ($conversationMessages as &$conversationMessage) {
                    $messageId = (int)($conversationMessage['message_id'] ?? 0);
                    $emojiCounts = $reactionSummaryByMessage[$messageId] ?? [];

                    arsort($emojiCounts);
                    $reactionSummary = [];
                    $reactionTotal = 0;
                    foreach ($emojiCounts as $emoji => $count) {
                        $count = (int)$count;
                        if ($count <= 0) {
                            continue;
                        }
                        $reactionSummary[] = [
                            'emoji' => (string)$emoji,
                            'count' => $count,
                        ];
                        $reactionTotal += $count;
                    }

                    $conversationMessage['reaction_summary'] = $reactionSummary;
                    $conversationMessage['reaction_users'] = $reactionUsersByMessage[$messageId] ?? [];
                    $conversationMessage['reaction_count'] = $reactionTotal;
                    $conversationMessage['reaction_emoji'] = !empty($reactionSummary) ? (string)$reactionSummary[0]['emoji'] : '';
                    $conversationMessage['reaction_by_user_id'] = 0;
                }
                unset($conversationMessage);
            }
        }
    }
}

$normalizedContacts = [];
foreach ($contacts as $contact) {
    $normalizedContact = chat_normalize_contact($contact, $recentLoginUserIds, $isStudentChatUser);
    if ($isStudentChatUser && (string)($normalizedContact['group_key'] ?? '') === '') {
        continue;
    }
    $normalizedContacts[] = $normalizedContact;
}

$normalizedSelectedContact = null;
if ($selectedContact) {
    $normalizedSelectedContact = chat_normalize_contact($selectedContact, $recentLoginUserIds, $isStudentChatUser);
}

$normalizedMessages = chat_normalize_messages($conversationMessages, $currentUserId);
$hasStudentSupervisorScope = $isStudentChatUser && $studentScope && (int)($studentScope['supervisor_user_id'] ?? 0) > 0;
$contactSearchPlaceholder = $isStudentChatUser ? 'Search supervisors or peers' : 'Search contacts';
$emptyContactsMessage = $isStudentChatUser
    ? ($hasStudentSupervisorScope ? 'No supervisors or same-supervisor students available for chat.' : $studentScopeMissingMessage)
    : 'No users available.';

if ($isAjaxRequest) {
    if ($errorMessage !== '') {
        chat_json_response([
            'ok' => false,
            'error' => $errorMessage,
            'contacts' => $normalizedContacts,
            'selectedUserId' => $selectedUserId,
            'selectedContact' => $normalizedSelectedContact,
            'messages' => $normalizedMessages,
        ], 400);
    }

    chat_json_response([
        'ok' => true,
        'success' => $successMessage,
        'contacts' => $normalizedContacts,
        'selectedUserId' => $selectedUserId,
        'selectedContact' => $normalizedSelectedContact,
        'messages' => $normalizedMessages,
    ]);
}

$page_title = 'BioTern || Chat';
$page_body_class = isset($page_body_class) && is_string($page_body_class)
    ? trim($page_body_class . ' chat-app-page')
    : 'chat-app-page';

$page_styles = isset($page_styles) && is_array($page_styles) ? $page_styles : [];
$page_styles[] = 'assets/css/modules/apps/apps-chat-page.css';
$page_styles = array_values(array_unique($page_styles));

$page_scripts = isset($page_scripts) && is_array($page_scripts) ? $page_scripts : [];
$page_scripts[] = 'assets/js/modules/apps/apps-chat-page.js';
$page_scripts = array_values(array_unique($page_scripts));

include 'includes/header.php';
?>

<main class="nxl-container btchat-page-wrap">
    <div class="nxl-content">
<div class="main-content btchat-main-content">
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger btchat-page-alert"><?php echo chat_esc($errorMessage); ?></div>
    <?php endif; ?>
    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success btchat-page-alert"><?php echo chat_esc($successMessage); ?></div>
    <?php endif; ?>

    <div class="btchat-shell" id="btchat-app" data-selected-user-id="<?php echo (int)$selectedUserId; ?>" data-chat-base-url="<?php echo chat_esc(chat_page_url()); ?>" data-current-user-id="<?php echo (int)$currentUserId; ?>">
        <aside class="btchat-left">
            <div class="btchat-left-header">
                <h2 class="btchat-left-title">Chat</h2>
            </div>
            <div class="btchat-search-wrap">
                <input type="search" class="btchat-search" id="btchat-search" placeholder="<?php echo chat_esc($contactSearchPlaceholder); ?>">
            </div>
            <div class="btchat-list" id="btchat-list">
                <?php if (empty($contacts)): ?>
                    <div class="px-3 py-4 text-white-50"><?php echo chat_esc($emptyContactsMessage); ?></div>
                <?php else: ?>
                    <?php $currentContactGroupKey = ''; ?>
                    <?php foreach ($normalizedContacts as $contact): ?>
                        <?php
                        $isActiveContact = (int)$contact['id'] === $selectedUserId;
                        $contactName = (string)$contact['name'];
                        $contactGroupKey = (string)($contact['group_key'] ?? '');
                        $contactGroupLabel = (string)($contact['group_label'] ?? '');
                        ?>
                        <?php if ($contactGroupKey !== '' && $contactGroupKey !== $currentContactGroupKey): ?>
                            <div class="btchat-group-label"><?php echo chat_esc($contactGroupLabel); ?></div>
                            <?php $currentContactGroupKey = $contactGroupKey; ?>
                        <?php endif; ?>
                        <a class="btchat-item<?php echo $isActiveContact ? ' active' : ''; ?>" href="<?php echo chat_esc(chat_page_url((int)$contact['id'])); ?>" data-user-id="<?php echo (int)$contact['id']; ?>">
                            <span class="btchat-avatar-wrap">
                                <img src="<?php echo chat_esc((string)$contact['avatar_path']); ?>" alt="<?php echo chat_esc($contactName); ?>" class="btchat-avatar js-avatar-fallback">
                                <span class="btchat-avatar-text chat-avatar-fallback-hidden"><?php echo chat_esc((string)$contact['initials']); ?></span>
                                <span class="btchat-status-dot<?php echo !empty($contact['is_online']) ? ' online' : ''; ?>"></span>
                            </span>
                            <div class="btchat-meta">
                                <div class="btchat-name-row">
                                    <span class="btchat-name"><?php echo chat_esc($contactName); ?></span>
                                    <span class="btchat-time"><?php echo chat_esc((string)$contact['last_message_label']); ?></span>
                                </div>
                                <div class="btchat-snippet-row">
                                    <span class="btchat-snippet"><?php echo chat_esc((string)($contact['last_message'] !== '' ? $contact['last_message'] : 'No messages yet')); ?></span>
                                    <?php if ((int)($contact['unread_count'] ?? 0) > 0): ?>
                                        <span class="badge rounded-pill bg-primary"><?php echo (int)$contact['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="btchat-main">
            <div id="btchat-alert" class="btchat-system-alert"></div>
            <button id="chat-scroll-btn" type="button" aria-label="Scroll to bottom">
                &#8595;<span class="scroll-btn-badge chat-init-hidden"></span>
            </button>
            <?php if ($normalizedSelectedContact): ?>
                <?php
                $selectedName = (string)$normalizedSelectedContact['name'];
                ?>
                <div class="btchat-chat-header" id="btchat-chat-header">
                    <div class="btchat-chat-title">
                        <button type="button" class="btchat-back-btn" id="btchat-mobile-back" aria-label="Back to conversations" title="Back">&#8592;</button>
                        <span class="btchat-avatar-wrap">
                            <img src="<?php echo chat_esc((string)$normalizedSelectedContact['avatar_path']); ?>" alt="<?php echo chat_esc($selectedName); ?>" class="btchat-avatar js-avatar-fallback">
                            <span class="btchat-avatar-text chat-avatar-fallback-hidden"><?php echo chat_esc((string)$normalizedSelectedContact['initials']); ?></span>
                            <span class="btchat-status-dot<?php echo !empty($normalizedSelectedContact['is_online']) ? ' online' : ''; ?>"></span>
                        </span>
                        <div class="min-w-0">
                            <div class="btchat-chat-name"><?php echo chat_esc($selectedName); ?></div>
                            <div class="btchat-chat-sub"><?php echo chat_esc(!empty($normalizedSelectedContact['is_online']) ? 'Online' : ((string)($normalizedSelectedContact['email'] ?? $normalizedSelectedContact['username'] ?? ''))); ?></div>
                        </div>
                    </div>
                    <div class="btchat-actions">
                        <button type="button" class="btchat-menu-toggle">
                            <i class="feather-more-horizontal"></i>
                        </button>
                        <div class="btchat-menu" role="menu">
                            <button type="button" class="btchat-menu-item" data-action="view-contact">Contact details</button>
                            <button type="button" class="btchat-menu-item" data-action="mute-conversation">Mute conversation</button>
                            <div class="btchat-menu-divider" role="separator"></div>
                            <button type="button" class="btchat-menu-item" data-action="refresh-chat">Refresh chat</button>
                            <button type="button" class="btchat-menu-item" data-action="scroll-bottom">Jump to recent</button>
                            <div class="btchat-menu-divider" role="separator"></div>
                            <button type="button" class="btchat-menu-item danger" data-action="delete-conversation">Delete conversation</button>
                        </div>
                    </div>
                </div>

                <div class="btchat-thread" id="btchat-thread">
                    <?php if (empty($normalizedMessages)): ?>
                        <div class="btchat-empty">
                            <div>
                                <h6 class="mb-2 text-white">No messages yet</h6>
                                <div class="text-white-50">Start the conversation with <?php echo chat_esc($selectedName); ?>.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($normalizedMessages as $message): ?>
                            <?php
                            $reactionSummary = (isset($message['reaction_summary']) && is_array($message['reaction_summary'])) ? $message['reaction_summary'] : [];
                            $reactionTotal = (int)($message['reaction_count'] ?? 0);
                            if ($reactionTotal <= 0) {
                                foreach ($reactionSummary as $reactionItem) {
                                    $reactionTotal += (int)($reactionItem['count'] ?? 0);
                                }
                            }
                            if (empty($reactionSummary) && (string)($message['reaction_emoji'] ?? '') !== '' && $reactionTotal > 0) {
                                $reactionSummary[] = [
                                    'emoji' => (string)$message['reaction_emoji'],
                                    'count' => $reactionTotal,
                                ];
                            }
                            $reactionIcons = [];
                            foreach ($reactionSummary as $reactionItem) {
                                $reactionEmoji = trim((string)($reactionItem['emoji'] ?? ''));
                                if ($reactionEmoji === '') {
                                    continue;
                                }
                                $reactionIcons[] = $reactionEmoji;
                                if (count($reactionIcons) >= 2) {
                                    break;
                                }
                            }
                            $hasReaction = $reactionTotal > 0 && !empty($reactionIcons);
                            ?>
                            <div class="msg-row<?php echo !empty($message['is_own']) ? ' own' : ''; ?><?php echo $hasReaction ? ' has-reaction' : ''; ?>">
                                <div class="msg-bubble<?php echo !empty($message['media_path']) ? ' has-media' : ''; ?>">
                                    <?php if ((string)($message['reply_preview'] ?? '') !== ''): ?>
                                        <div class="msg-reply-quote"><strong><?php echo chat_esc((string)($message['reply_author'] ?? '')); ?></strong><span class="msg-reply-quote-text"><?php echo chat_esc((string)$message['reply_preview']); ?></span></div>
                                    <?php endif; ?>
                                    <?php if ((string)$message['media_type'] === 'image'): ?>
                                        <img src="<?php echo chat_esc((string)$message['media_path']); ?>" class="msg-media" alt="image" data-media-viewer="image">
                                    <?php elseif ((string)$message['media_type'] === 'video'): ?>
                                        <video src="<?php echo chat_esc((string)$message['media_path']); ?>" class="msg-media-video" controls preload="metadata" data-media-viewer="video"></video>
                                    <?php endif; ?>
                                    <?php $displayMsg = (string)$message['message']; if (!empty($message['media_path']) && $displayMsg === basename((string)$message['media_path'])) $displayMsg = ''; ?>
                                    <?php if ($displayMsg !== ''): ?><?php echo nl2br(chat_esc($displayMsg)); ?><?php endif; ?>
                                    <div class="msg-meta" title="<?php echo chat_esc((string)$message['time_full']); ?>">
                                        <?php echo chat_esc((string)$message['time_label']); ?><?php if ((string)$message['time_exact'] !== ''): ?> &middot; <span class="msg-time-exact"><?php echo chat_esc((string)$message['time_exact']); ?></span><?php endif; ?>
                                    </div>
                                    <?php if ($hasReaction): ?>
                                        <button type="button" class="msg-reaction-badge" data-reaction-mid="<?php echo (int)($message['message_id'] ?? 0); ?>" aria-label="View reactions">
                                            <span class="msg-reaction-icons"><?php foreach ($reactionIcons as $reactionIcon): ?><span class="msg-reaction-icon"><?php echo chat_esc((string)$reactionIcon); ?></span><?php endforeach; ?></span>
                                            <span class="msg-reaction-count"><?php echo $reactionTotal; ?></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="btchat-compose">
                    <form method="post" action="<?php echo chat_esc(chat_page_url((int)$selectedUserId)); ?>" id="btchat-compose-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="send-message">
                        <input type="hidden" name="user_id" value="<?php echo (int)$selectedUserId; ?>">
                        <input type="hidden" name="reply_to_message_id" id="chat-reply-to-message-id" value="0">
                        <input type="file" name="chat_media" id="chat-media-input" accept="image/*" class="btchat-file-input">
                        <div id="chat-reply-preview">
                            <span id="chat-reply-label"></span>
                            <button type="button" class="chat-reply-remove" id="chat-reply-remove" title="Cancel reply">&#x2715;</button>
                        </div>
                        <div id="chat-emoji-picker">
                            <div class="chat-emoji-search-wrap">
                                <input type="search" id="chat-emoji-search" class="chat-emoji-search" placeholder="Search emoji">
                            </div>
                            <div class="chat-emoji-grid" id="chat-emoji-grid"></div>
                            <div class="chat-emoji-empty chat-init-hidden" id="chat-emoji-empty">No emoji found</div>
                            <div class="chat-emoji-tabs" id="chat-emoji-tabs">
                                <button type="button" class="chat-emoji-tab active" data-emoji-cat="smileys" title="Smileys">&#128512;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="people" title="People">&#129489;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="animals" title="Animals">&#128049;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="food" title="Food">&#127828;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="travel" title="Travel">&#128663;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="objects" title="Objects">&#128161;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="symbols" title="Symbols">&#10133;</button>
                                <button type="button" class="chat-emoji-tab" data-emoji-cat="flags" title="Flags">&#127937;</button>
                            </div>
                        </div>
                        <div id="chat-media-preview">
                            <img id="chat-preview-thumb" class="chat-preview-thumb chat-init-hidden" src="" alt="">
                            <span id="chat-preview-name"></span>
                            <button type="button" class="chat-preview-remove" id="chat-preview-remove" title="Remove">&#x2715;</button>
                        </div>
                        <?php $composeWarningText = trim((string)$composeWarningMessage); ?>
                        <div class="btchat-compose-warning<?php echo $composeWarningText !== '' ? ' show' : ''; ?>" id="btchat-compose-warning" data-warning-visible="<?php echo $composeWarningText !== '' ? '1' : '0'; ?>"<?php echo $composeWarningText === '' ? ' aria-hidden="true"' : ''; ?>>
                            <span class="btchat-compose-warning-icon" aria-hidden="true">!</span>
                            <span class="btchat-compose-warning-text" id="btchat-compose-warning-text"><?php echo chat_esc($composeWarningText); ?></span>
                        </div>
                        <div class="btchat-compose-inner">
                            <button type="button" class="btchat-attach-btn" id="chat-attach-btn" title="Send image or video">
                                <i class="feather-paperclip"></i>
                            </button>
                            <button type="button" class="btchat-emoji-btn" id="chat-emoji-btn" title="Emoji">
                                <i class="feather-smile"></i>
                            </button>
                            <textarea class="btchat-compose-input" id="btchat-message-input" name="message" placeholder="Aa" rows="1"><?php echo chat_esc($draftMessage); ?></textarea>
                            <button type="submit" class="btchat-send-btn" id="btchat-send-btn" aria-label="Send" data-mode="send">
                                <i class="feather-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="btchat-empty">
                    <div>
                        <h5 class="mb-2 text-white">Choose a conversation</h5>
                        <div class="text-white-50">Select someone from the left to start chatting.</div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
    </div>
</main>

<div class="chat-confirm-overlay" id="chat-confirm-modal" aria-hidden="true">
    <div class="chat-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="chat-confirm-title">
        <h6 class="chat-confirm-title" id="chat-confirm-title">Confirm action</h6>
        <p class="chat-confirm-text" id="chat-confirm-text">Are you sure?</p>
        <div class="chat-confirm-actions">
            <button type="button" class="chat-confirm-btn" id="chat-confirm-cancel">Cancel</button>
            <button type="button" class="chat-confirm-btn danger" id="chat-confirm-ok">Confirm</button>
        </div>
    </div>
</div>

<div class="chat-contact-overlay" id="chat-contact-modal" aria-hidden="true">
    <div class="chat-contact-modal" role="dialog" aria-modal="true" aria-labelledby="chat-contact-title">
        <div class="chat-contact-head">
            <h6 class="chat-contact-title" id="chat-contact-title">Contact details</h6>
            <button type="button" class="chat-contact-close" id="chat-contact-close" aria-label="Close">&times;</button>
        </div>
        <div class="chat-contact-body">
            <div class="chat-contact-identity">
                <div class="chat-contact-avatar-host" id="chat-contact-avatar-host"></div>
                <div>
                    <p class="chat-contact-name" id="chat-contact-name">Unknown user</p>
                    <p class="chat-contact-sub" id="chat-contact-sub">-</p>
                </div>
            </div>
            <div class="chat-contact-grid">
                <div class="chat-contact-row"><div class="chat-contact-key">User ID</div><div class="chat-contact-value" id="chat-contact-user-id">-</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Username</div><div class="chat-contact-value" id="chat-contact-username">-</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Email</div><div class="chat-contact-value" id="chat-contact-email">-</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Status</div><div class="chat-contact-value" id="chat-contact-status">Offline</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Conversation</div><div class="chat-contact-value" id="chat-contact-muted-state">Unmuted</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Last active</div><div class="chat-contact-value" id="chat-contact-last-active">No messages yet</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Last message</div><div class="chat-contact-value preview" id="chat-contact-last-message">No messages yet</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Unread</div><div class="chat-contact-value" id="chat-contact-unread">0</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Messages</div><div class="chat-contact-value" id="chat-contact-total">0</div></div>
                <div class="chat-contact-row"><div class="chat-contact-key">Reportable</div><div class="chat-contact-value" id="chat-contact-reportable">0 messages</div></div>
            </div>
            <div class="chat-contact-actions">
                <button type="button" class="chat-contact-action" id="chat-contact-mute">Mute conversation</button>
                <button type="button" class="chat-contact-action danger" id="chat-contact-report-user">Report user</button>
                <button type="button" class="chat-contact-action" id="chat-contact-close-secondary">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="chat-report-overlay" id="chat-report-modal" aria-hidden="true">
    <div class="chat-report-modal" role="dialog" aria-modal="true" aria-labelledby="chat-report-title">
        <h6 class="chat-report-title" id="chat-report-title">Report message</h6>
        <p class="chat-report-text">Tell us why you are reporting this message.</p>
        <div class="chat-report-field">
            <label for="chat-report-reason" class="chat-report-label">Reason</label>
            <select id="chat-report-reason" class="chat-report-select">
                <option value="Harassment or abusive language">Harassment or abusive language</option>
                <option value="Spam or scam">Spam or scam</option>
                <option value="Sexual content">Sexual content</option>
                <option value="Hate speech">Hate speech</option>
                <option value="Threat or violence">Threat or violence</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="chat-report-field">
            <label for="chat-report-note" class="chat-report-label">Details (optional)</label>
            <textarea id="chat-report-note" class="chat-report-note" maxlength="220" placeholder="Add short details to help moderation review."></textarea>
        </div>
        <div class="chat-report-actions">
            <button type="button" class="chat-confirm-btn" id="chat-report-cancel">Cancel</button>
            <button type="button" class="chat-confirm-btn danger" id="chat-report-ok">Continue</button>
        </div>
    </div>
</div>

<div class="chat-reactions-overlay" id="chat-reactions-modal" aria-hidden="true">
    <div class="chat-reactions-modal" role="dialog" aria-modal="true" aria-labelledby="chat-reactions-title">
        <div class="chat-reactions-head">
            <h6 class="chat-reactions-title" id="chat-reactions-title">Reaction details</h6>
            <button type="button" class="chat-reactions-close" id="chat-reactions-close" aria-label="Close">&times;</button>
        </div>
        <div class="chat-reactions-tabs" id="chat-reactions-tabs"></div>
        <div class="chat-reactions-list" id="chat-reactions-list"></div>
    </div>
</div>

<div class="chat-media-overlay" id="chat-media-modal" aria-hidden="true">
    <div class="chat-media-modal" role="dialog" aria-modal="true" aria-labelledby="chat-media-title">
        <div class="chat-media-head">
            <div class="chat-media-head-left">
                <h6 class="chat-media-title" id="chat-media-title">Image</h6>
                <button type="button" class="chat-media-icon-btn chat-media-close" id="chat-media-close" aria-label="Close viewer">&times;</button>
            </div>
            <div class="chat-media-head-right">
                <button type="button" class="chat-media-icon-btn" id="chat-media-download" aria-label="Download media" title="Download media">
                    <svg class="chat-media-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 4v10"></path>
                        <path d="M8.5 10.5 12 14l3.5-3.5"></path>
                        <path d="M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div class="chat-media-body" id="chat-media-body"></div>
    </div>
</div>

<div class="msg-action-menu" id="msg-action-menu" aria-hidden="true">
    <div class="msg-action-emoji-row">
        <?php foreach (chat_supported_reactions() as $reactionLabel => $reactionEmoji): ?>
        <button type="button" class="msg-emoji-btn" data-emoji="<?php echo chat_esc($reactionEmoji); ?>" title="<?php echo chat_esc($reactionLabel); ?>"><?php echo chat_esc($reactionEmoji); ?></button>
        <?php endforeach; ?>
    </div>
    <button type="button" class="msg-action-item" data-msg-action="reply">Reply</button>
    <button type="button" class="msg-action-item" data-msg-action="pin">Pin message</button>
    <button type="button" class="msg-action-item" data-msg-action="unsend">Unsend</button>
    <button type="button" class="msg-action-item danger is-hidden" data-msg-action="remove">Remove</button>
    <button type="button" class="msg-action-item danger" data-msg-action="report">Report</button>
</div>



<?php
include 'includes/footer.php';
$conn->close();
?>
