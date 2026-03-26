<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

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
    $normalized = ltrim(str_replace('\\', '/', trim($profilePicture)), '/');
    if ($normalized !== '') {
        return $normalized;
    }

    // No picture stored â€“ use a numbered default avatar so different users look distinct
    $num = $userId > 0 ? (($userId % 12) + 1) : 1;
    return 'assets/images/avatar/' . $num . '.png';
}

function chat_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = '{"ok":false,"error":"Response encoding failed."}';
    }
    echo $json;
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
    if ($userId > 0 && isset($recentLoginUserIds[$userId])) {
        return true;
    }

    if (!$lastActivityAt) {
        return false;
    }

    $timestamp = strtotime($lastActivityAt);
    if ($timestamp === false) {
        return false;
    }

    return (time() - $timestamp) <= 300;
}

function chat_media_kind_from_path(string $path): string
{
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

function chat_normalize_contact(array $contact, array $recentLoginUserIds): array
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

    return [
        'id' => $userId,
        'name' => $name,
        'username' => (string)($contact['username'] ?? ''),
        'email' => (string)($contact['email'] ?? ''),
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
            ? (date('Y-m-d', $ts) === $todayDate ? date('g:i A', $ts) : date('M j Â· g:i A', $ts))
            : '';
        $timeFull = $ts > 0 ? date('F j, Y \a\t g:i A', $ts) : '';
        $timeClock = $ts > 0 ? date('g:i A', $ts) : '';
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
            'time_clock' => $timeClock,
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
    if (!isset($reportCols['moderator_note'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN moderator_note VARCHAR(255) NULL DEFAULT NULL AFTER status");
    }
    if (!isset($reportCols['reviewed_by_user_id'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN reviewed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER moderator_note");
    }
    if (!isset($reportCols['reviewed_at'])) {
        $conn->query("ALTER TABLE message_reports ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by_user_id");
    }
}

function chat_message_meta(mysqli $conn): array
{
    chat_ensure_messages_table($conn);
    chat_ensure_message_reactions_table($conn);
    chat_ensure_message_pins_table($conn);
    chat_ensure_message_reports_table($conn);

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
        'reactions_ready' => chat_has_table($conn, 'message_reactions'),
        'pins_ready' => chat_has_table($conn, 'message_pins'),
        'reports_ready' => chat_has_table($conn, 'message_reports'),
    ];
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$currentUserRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$isStudentChatUser = ($currentUserRole === 'student');
$currentStudentCourseId = 0;
$studentScopeErrorMessage = 'Students can only chat with students from the same course.';

if ($isStudentChatUser && $currentUserId > 0) {
    $studentCourseStmt = $conn->prepare('SELECT course_id FROM students WHERE user_id = ? LIMIT 1');
    if ($studentCourseStmt) {
        $studentCourseStmt->bind_param('i', $currentUserId);
        $studentCourseStmt->execute();
        $studentCourseRow = $studentCourseStmt->get_result()->fetch_assoc();
        $studentCourseStmt->close();
        $currentStudentCourseId = (int)($studentCourseRow['course_id'] ?? 0);
    }
}

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

                    $reportStmt = $conn->prepare("INSERT INTO message_reports (message_id, reporter_user_id, reported_user_id, reason, status, moderator_note, reviewed_by_user_id, reviewed_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', NULL, NULL, NULL, NOW(), NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'open', moderator_note = NULL, reviewed_by_user_id = NULL, reviewed_at = NULL, updated_at = NOW()");
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
                $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
                if ($safeExt === '') {
                    $mimeToExt = [
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                    ];
                    $safeExt = $mimeToExt[$mime] ?? 'bin';
                }
                $originalBase = pathinfo((string)$file['name'], PATHINFO_FILENAME);
                $originalBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$originalBase);
                $originalBase = trim((string)$originalBase, " ._-");
                if ($originalBase === '') {
                    $originalBase = 'upload';
                }
                if (function_exists('mb_substr')) {
                    $originalBase = (string)mb_substr($originalBase, 0, 90, 'UTF-8');
                } else {
                    $originalBase = substr($originalBase, 0, 90);
                }
                $destDir = dirname(__DIR__) . '/uploads/chat_media/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                $fileName = $originalBase . '.' . $safeExt;
                $suffix = 1;
                while (is_file($destDir . $fileName) && $suffix < 5000) {
                    $fileName = $originalBase . '_' . $suffix . '.' . $safeExt;
                    $suffix++;
                }
                $destPath = $destDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $uploadedMediaPath = 'uploads/chat_media/' . $fileName;
                    if ($draftMessage === '') {
                        $draftMessage = $fileName; // non-empty placeholder so NOT NULL constraint is satisfied
                    }
                } else {
                    $mediaUploadError = 'Could not save the uploaded file.';
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
        $recipientStmt = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
        $recipient = null;
        if ($recipientStmt) {
            $recipientStmt->bind_param('i', $selectedUserId);
            $recipientStmt->execute();
            $recipient = $recipientStmt->get_result()->fetch_assoc();
            $recipientStmt->close();
        }

        if (!$recipient) {
            $errorMessage = 'The selected recipient was not found.';
        } elseif ($isStudentChatUser && $selectedUserId !== $currentUserId) {
            if ($currentStudentCourseId <= 0) {
                $errorMessage = $studentScopeErrorMessage;
            } else {
                $sameCourseStmt = $conn->prepare('SELECT 1 FROM students WHERE user_id = ? AND course_id = ? LIMIT 1');
                $isSameCourseRecipient = false;
                if ($sameCourseStmt) {
                    $sameCourseStmt->bind_param('ii', $selectedUserId, $currentStudentCourseId);
                    $sameCourseStmt->execute();
                    $isSameCourseRecipient = (bool)$sameCourseStmt->get_result()->fetch_assoc();
                    $sameCourseStmt->close();
                }
                if (!$isSameCourseRecipient) {
                    $errorMessage = $studentScopeErrorMessage;
                }
            }
        } else {
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
                $insertStmt->bind_param($bindTypes, ...$bindValues);
                $executed = $insertStmt->execute();
                $insertErrNo = (int)$insertStmt->errno;
                $insertError = (string)$insertStmt->error;
                $insertStmt->close();

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
        $studentScopeFilterSql = ' AND (u.id = ? OR EXISTS (SELECT 1 FROM students su WHERE su.user_id = u.id AND su.course_id = ?))';
        $contactTypes .= 'ii';
        $contactParams[] = $currentUserId;
        $contactParams[] = $currentStudentCourseId;
    }

    $contactsSql = '
        SELECT
            u.id,
            u.name,
            u.username,
            u.email,
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
            $errorMessage = $studentScopeErrorMessage;
        }
    }

    if ($selectedContact === null) {
        $selectedStmt = $conn->prepare('SELECT id, name, username, email, profile_picture FROM users WHERE id = ? LIMIT 1');
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
    $normalizedContacts[] = chat_normalize_contact($contact, $recentLoginUserIds);
}

$normalizedSelectedContact = null;
if ($selectedContact) {
    $normalizedSelectedContact = chat_normalize_contact($selectedContact, $recentLoginUserIds);
}

$normalizedMessages = chat_normalize_messages($conversationMessages, $currentUserId);
$initialMessagesJson = json_encode(array_values($normalizedMessages), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if ($initialMessagesJson === false) {
    $initialMessagesJson = '[]';
}

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

include 'includes/header.php';
?>
<style>
    /* â”€â”€ Light mode tokens (default) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    :root {
        --chat-shell-bg: #eef2f6;
        --chat-shell-shadow: 0 4px 24px rgba(13, 16, 28, 0.08);
        --chat-left-bg: #ffffff;
        --chat-left-color: #1f2937;
        --chat-left-border: rgba(15, 23, 42, 0.12);
        --chat-search-bg: #f8fafc;
        --chat-search-border: rgba(30, 41, 59, 0.2);
        --chat-search-color: #1f2937;
        --chat-search-placeholder: rgba(30, 41, 59, 0.58);
        --chat-item-hover: rgba(30, 41, 59, 0.08);
        --chat-name-color: #111827;
        --chat-time-color: rgba(17, 24, 39, 0.7);
        --chat-snippet-color: rgba(17, 24, 39, 0.76);
        --chat-main-bg: #f2f5fa;
        --chat-header-border: rgba(30, 41, 59, 0.16);
        --chat-header-bg: #ffffff;
        --chat-header-name-color: #111827;
        --chat-header-sub-color: rgba(17, 24, 39, 0.78);
        --chat-actions-color: #334155;
        --chat-menu-dot-color: #1f2937;
        --chat-menu-dot-hover: rgba(30, 41, 59, 0.1);
        --chat-attach-btn-color: #334155;
        --chat-send-from: #1e40af;
        --chat-send-to: #1e3a8a;
        --chat-send-text: #ffffff;
        --chat-bubble-bg: #e9eff6;
        --chat-bubble-color: #111827;
        --chat-own-bubble-bg: #1e3a8a;
        --chat-own-bubble-color: #ffffff;
        --chat-own-bubble-border: #1e3a8a;
        --chat-own-bubble-shadow: 0 2px 9px rgba(30, 58, 138, 0.2);
        --chat-own-meta-color: rgba(239, 246, 255, 0.92);
        --chat-meta-color: rgba(17, 24, 39, 0.7);
        --chat-compose-border: rgba(30, 41, 59, 0.16);
        --chat-compose-bg: #ffffff;
        --chat-compose-inner-bg: #f8fafc;
        --chat-compose-inner-border: rgba(30, 41, 59, 0.2);
        --chat-compose-input-color: #111827;
        --chat-compose-input-placeholder: rgba(17, 24, 39, 0.55);
        --chat-status-dot-border: #ffffff;
        --chat-empty-color: rgba(17, 24, 39, 0.76);
    }

    /* â”€â”€ Dark mode overrides â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    html.app-skin-dark {
        --chat-shell-bg: #111827;
        --chat-shell-shadow: 0 4px 24px rgba(0, 0, 0, 0.35);
        --chat-left-bg: #1f2937;
        --chat-left-color: #e2e8f0;
        --chat-left-border: rgba(148, 163, 184, 0.28);
        --chat-search-bg: #0f172a;
        --chat-search-border: rgba(148, 163, 184, 0.4);
        --chat-search-color: #e2e8f0;
        --chat-search-placeholder: rgba(226, 232, 240, 0.72);
        --chat-item-hover: rgba(148, 163, 184, 0.18);
        --chat-name-color: #f1f5f9;
        --chat-time-color: rgba(226, 232, 240, 0.8);
        --chat-snippet-color: rgba(226, 232, 240, 0.84);
        --chat-main-bg: #0f172a;
        --chat-header-border: rgba(148, 163, 184, 0.3);
        --chat-header-bg: #1f2937;
        --chat-header-name-color: #f1f5f9;
        --chat-header-sub-color: rgba(226, 232, 240, 0.85);
        --chat-actions-color: #cbd5e1;
        --chat-menu-dot-color: #e2e8f0;
        --chat-menu-dot-hover: rgba(148, 163, 184, 0.22);
        --chat-attach-btn-color: #cbd5e1;
        --chat-send-from: #2563eb;
        --chat-send-to: #1d4ed8;
        --chat-send-text: #ffffff;
        --chat-bubble-bg: #243244;
        --chat-bubble-color: #f1f5f9;
        --chat-own-bubble-bg: #2563eb;
        --chat-own-bubble-color: #f8fbff;
        --chat-own-bubble-border: #1d4ed8;
        --chat-own-bubble-shadow: 0 3px 12px rgba(2, 6, 23, 0.45);
        --chat-own-meta-color: rgba(219, 234, 254, 0.94);
        --chat-meta-color: rgba(226, 232, 240, 0.82);
        --chat-compose-border: rgba(148, 163, 184, 0.32);
        --chat-compose-bg: #1f2937;
        --chat-compose-inner-bg: #0f172a;
        --chat-compose-inner-border: rgba(148, 163, 184, 0.42);
        --chat-compose-input-color: #f1f5f9;
        --chat-compose-input-placeholder: rgba(226, 232, 240, 0.7);
        --chat-status-dot-border: #1f2937;
        --chat-empty-color: rgba(226, 232, 240, 0.84);
    }

    .main-content {
        padding-top: 0 !important;
        height: 100%;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        position: relative;
        z-index: 0;
    }

    html.chat-page-lock,
    body.chat-page-lock {
        height: 100%;
        overflow: hidden !important;
    }

    body.chat-page-lock .nxl-container {
        height: calc(100vh - 64px);
        max-height: calc(100vh - 64px);
        overflow: hidden;
    }

    body.chat-page-lock .nxl-container .nxl-content {
        height: 100%;
        max-height: 100%;
        overflow: hidden;
    }

    .nxl-container .nxl-content {
        height: calc(100vh - 80px);
        max-height: calc(100vh - 80px);
        overflow: hidden;
        position: relative;
        z-index: 0;
    }

    .btchat-page-alert {
        margin: 0 0 0.5rem 0;
        flex-shrink: 0;
        position: relative;
        z-index: 30;
    }

    .btchat-shell {
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--chat-shell-shadow);
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        height: 100%;
        max-height: 100%;
        min-height: 0;
        background: var(--chat-shell-bg);
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 92%, transparent);
        position: relative;
        z-index: 0;
    }

    .btchat-left {
        background: var(--chat-left-bg);
        color: var(--chat-left-color);
        border-right: 1px solid var(--chat-left-border);
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
        position: relative;
    }

    .btchat-left::after {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 1px;
        background: linear-gradient(180deg, transparent, color-mix(in srgb, var(--chat-header-border) 96%, transparent), transparent);
        pointer-events: none;
    }

    .btchat-left-header {
        padding: 1.15rem 1.15rem 0.8rem;
    }

    .btchat-left-title {
        margin: 0;
        font-size: 1.18rem;
        line-height: 1;
        letter-spacing: 0.02em;
        text-transform: none;
        font-weight: 700;
        color: var(--chat-name-color);
    }

    .btchat-search-wrap {
        padding: 0 1rem 1rem;
    }

    .btchat-search {
        width: 100%;
        border: 1px solid var(--chat-search-border) !important;
        border-radius: 999px;
        background: var(--chat-search-bg) !important;
        color: var(--chat-search-color) !important;
        padding: 0.72rem 1rem;
        font-size: 0.92rem;
        outline: none;
        box-shadow: none !important;
    }

    .btchat-search::placeholder {
        color: var(--chat-search-placeholder);
    }

    /* Explicit dark-mode overrides â€“ beat the app theme's input rules */
    html.app-skin-dark .btchat-search {
        background-color: #121a2d !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        color: #b1b4c0 !important;
        box-shadow: none !important;
    }

    html.app-skin-dark .btchat-search::placeholder {
        color: rgba(177, 180, 192, 0.7) !important;
    }

    .btchat-list {
        overflow-y: auto;
        min-height: 0;
        padding-bottom: 0.5rem;
    }

    .btchat-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        text-decoration: none;
        color: inherit;
        margin: 0.18rem 0.55rem;
        border-radius: 14px;
        padding: 0.68rem;
        border: 1px solid transparent;
        transition: background-color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }

    .btchat-item:hover,
    .btchat-item.active {
        background: color-mix(in srgb, var(--chat-item-hover) 78%, transparent);
        border-color: color-mix(in srgb, var(--chat-header-border) 88%, transparent);
        transform: translateX(2px);
    }

    .btchat-avatar,
    .btchat-avatar-text {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        flex-shrink: 0;
        position: relative;
    }

    .btchat-avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .btchat-avatar {
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .btchat-avatar-text {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        font-weight: 700;
        letter-spacing: 0.05em;
    }

    .btchat-status-dot {
        position: absolute;
        right: 1px;
        bottom: 1px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #64748b;
        border: 2px solid var(--chat-status-dot-border);
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.25);
    }

    .btchat-status-dot.online {
        background: #22c55e;
    }

    .btchat-meta {
        min-width: 0;
        width: 100%;
    }

    .btchat-name-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem;
    }

    .btchat-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--chat-name-color);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btchat-time {
        font-size: 0.78rem;
        color: var(--chat-time-color);
        white-space: nowrap;
    }

    .btchat-snippet-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        margin-top: 2px;
    }

    .btchat-snippet {
        color: var(--chat-snippet-color);
        font-size: 0.92rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btchat-main {
        position: relative;
        background: var(--chat-main-bg);
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .btchat-main::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(1200px 340px at 50% -180px, color-mix(in srgb, var(--chat-send-from) 11%, transparent), transparent 72%),
            radial-gradient(520px 200px at -80px 92%, color-mix(in srgb, var(--chat-send-to) 8%, transparent), transparent 70%);
        pointer-events: none;
    }

    .btchat-main > * {
        position: relative;
        z-index: 1;
    }

    .btchat-chat-header {
        padding: 0.95rem 1.18rem;
        border-bottom: 1px solid var(--chat-header-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: color-mix(in srgb, var(--chat-header-bg) 94%, transparent);
        backdrop-filter: blur(6px);
        z-index: 22;
        overflow: visible;
    }

    .btchat-chat-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .btchat-back-btn {
        display: none;
        border: 0;
        background: transparent;
        color: var(--chat-actions-color);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
    }

    .btchat-back-btn:hover {
        background: var(--chat-menu-dot-hover);
    }

    .btchat-chat-name {
        color: var(--chat-header-name-color);
        font-size: 1rem;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btchat-chat-sub {
        color: var(--chat-header-sub-color);
        font-size: 0.82rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btchat-actions {
        color: var(--chat-actions-color);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.05rem;
        position: relative;
        z-index: 26;
    }

    .btchat-menu-toggle {
        border: 0;
        background: transparent;
        color: var(--chat-menu-dot-color);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.15rem;
    }

    .btchat-menu-toggle:hover {
        background: var(--chat-menu-dot-hover);
    }

    /* Prevent global tooltip/pseudo-label styles from rendering text near the three-dot button */
    .btchat-menu-toggle::before,
    .btchat-menu-toggle::after,
    .msg-hover-menu-btn::before,
    .msg-hover-menu-btn::after {
        content: none !important;
        display: none !important;
    }

    .btchat-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        min-width: 160px;
        border-radius: 10px;
        border: 1px solid var(--chat-header-border);
        background: var(--chat-header-bg);
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
        padding: 0.3rem;
        display: none;
        z-index: 80;
    }

    .btchat-menu.show {
        display: block;
    }

    .btchat-menu-item {
        width: 100%;
        border: 0;
        background: transparent;
        color: var(--chat-header-name-color);
        text-align: left;
        border-radius: 8px;
        padding: 0.46rem 0.58rem;
        font-size: 0.88rem;
        cursor: pointer;
    }

    .btchat-menu-item:hover,
    .btchat-menu-item:focus-visible {
        background: var(--chat-item-hover);
        outline: none;
    }

    .btchat-menu-divider {
        height: 1px;
        background: var(--chat-header-border);
        margin: 0.25rem 0.2rem;
    }

    .btchat-menu-item.danger {
        color: #ef4444;
    }

    /* Keep chat modals clean from global tooltip overlays */
    .main-content .tooltip {
        display: none !important;
    }

    .chat-confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 12000;
        padding: 1rem;
    }

    .chat-confirm-overlay.show {
        display: flex;
    }

    .chat-confirm-modal {
        width: min(420px, 100%);
        border-radius: 14px;
        border: 1px solid var(--chat-header-border);
        background: var(--chat-header-bg);
        color: var(--chat-header-name-color);
        box-shadow: 0 18px 38px rgba(2, 6, 23, 0.42);
        padding: 1rem;
    }

    .chat-confirm-title {
        margin: 0 0 0.45rem;
        font-size: 1.03rem;
        font-weight: 700;
    }

    .chat-confirm-text {
        margin: 0;
        font-size: 0.9rem;
        color: var(--chat-header-sub-color);
    }

    .chat-confirm-actions {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
        gap: 0.55rem;
    }

    .chat-confirm-btn {
        border: 1px solid var(--chat-header-border);
        border-radius: 9px;
        background: transparent;
        color: var(--chat-header-name-color);
        padding: 0.42rem 0.72rem;
        font-size: 0.86rem;
        cursor: pointer;
    }

    .chat-confirm-btn.danger {
        border-color: rgba(239, 68, 68, 0.45);
        background: rgba(239, 68, 68, 0.14);
        color: #ef4444;
    }

    .chat-contact-overlay {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 12050;
        padding: 1rem;
    }

    .chat-contact-overlay.show {
        display: flex;
    }

    .chat-contact-modal {
        width: min(460px, 100%);
        border-radius: 14px;
        border: 1px solid var(--chat-header-border);
        background: var(--chat-header-bg);
        color: var(--chat-header-name-color);
        box-shadow: 0 18px 38px rgba(2, 6, 23, 0.42);
        overflow: hidden;
    }

    .chat-contact-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
        padding: 0.88rem 0.95rem;
        border-bottom: 1px solid var(--chat-header-border);
    }

    .chat-contact-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }

    .chat-contact-close {
        border: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--chat-item-hover);
        color: var(--chat-header-name-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.12rem;
        line-height: 1;
    }

    .chat-contact-body {
        padding: 0.95rem;
    }

    .chat-contact-identity {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-bottom: 0.8rem;
        margin-bottom: 0.8rem;
        border-bottom: 1px solid color-mix(in srgb, var(--chat-header-border) 78%, transparent);
    }

    .chat-contact-avatar-host .btchat-avatar,
    .chat-contact-avatar-host .btchat-avatar-text {
        width: 52px;
        height: 52px;
    }

    .chat-contact-name {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--chat-header-name-color);
    }

    .chat-contact-sub {
        margin: 0.12rem 0 0;
        font-size: 0.84rem;
        color: var(--chat-header-sub-color);
    }

    .chat-contact-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.48rem;
    }

    .chat-contact-row {
        display: grid;
        grid-template-columns: 108px 1fr;
        gap: 0.6rem;
        align-items: start;
        font-size: 0.88rem;
    }

    .chat-contact-key {
        color: var(--chat-header-sub-color);
        font-weight: 700;
    }

    .chat-contact-value {
        color: var(--chat-header-name-color);
        word-break: break-word;
    }

    .chat-contact-value.preview {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .chat-contact-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.55rem;
        margin-top: 0.9rem;
        padding-top: 0.9rem;
        border-top: 1px solid color-mix(in srgb, var(--chat-header-border) 78%, transparent);
    }

    .chat-contact-action {
        border: 1px solid var(--chat-header-border);
        border-radius: 9px;
        background: transparent;
        color: var(--chat-header-name-color);
        padding: 0.42rem 0.72rem;
        font-size: 0.86rem;
        cursor: pointer;
    }

    .chat-contact-action.danger {
        border-color: rgba(239, 68, 68, 0.45);
        background: rgba(239, 68, 68, 0.14);
        color: #ef4444;
    }

    .chat-report-overlay {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 12100;
        padding: 1rem;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .chat-report-overlay.show {
        display: flex;
        pointer-events: auto;
        opacity: 1;
    }

    .chat-report-modal {
        width: min(480px, 100%);
        border-radius: 14px;
        border: 1px solid var(--chat-header-border);
        background: var(--chat-header-bg);
        color: var(--chat-header-name-color);
        box-shadow: 0 18px 38px rgba(2, 6, 23, 0.42);
        padding: 1rem;
        overflow: hidden;
    }

    .chat-report-title {
        margin: 0 0 0.35rem;
        font-size: 1.03rem;
        font-weight: 700;
    }

    .chat-report-text {
        margin: 0 0 0.8rem;
        font-size: 0.9rem;
        color: var(--chat-header-sub-color);
    }

    .chat-report-field {
        margin-bottom: 0.7rem;
        overflow: hidden;
    }

    .chat-report-label {
        display: block;
        margin-bottom: 0.36rem;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        color: var(--chat-header-name-color);
    }

    .chat-report-select,
    .chat-report-note {
        width: 100%;
        border: 1px solid var(--chat-compose-inner-border);
        border-radius: 10px;
        background: var(--chat-compose-inner-bg);
        color: var(--chat-compose-input-color);
        padding: 0.56rem 0.66rem;
        font-size: 0.88rem;
    }

    .chat-report-select::placeholder,
    .chat-report-note::placeholder {
        color: var(--chat-compose-input-placeholder);
    }

    .chat-report-select:focus,
    .chat-report-note:focus {
        outline: none;
        border-color: color-mix(in srgb, var(--chat-actions-color) 70%, var(--chat-header-border));
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--chat-actions-color) 25%, transparent);
    }

    .chat-report-note {
        min-height: 74px;
        resize: vertical;
    }

    html.app-skin-dark .chat-report-select option {
        background: #1f2937;
        color: #f1f5f9;
    }

    .chat-report-actions {
        margin-top: 0.9rem;
        display: flex;
        justify-content: flex-end;
        gap: 0.55rem;
    }

    .btchat-thread {
        padding: 1.1rem 1.15rem;
        overflow-y: auto;
        min-height: 0;
        flex: 1;
        transition: opacity 0.16s ease;
    }

    .btchat-thread.is-loading {
        opacity: 0.52;
    }

    .msg-row {
        display: flex;
        align-items: flex-end;
        margin-bottom: 0.18rem;
        gap: 0.45rem;
        width: fit-content;
        max-width: 100%;
    }

    .msg-hover-menu-btn {
        border: 0;
        background: transparent;
        color: var(--chat-meta-color);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.15s ease;
        cursor: pointer;
        font-size: 0.95rem;
        line-height: 1;
        flex-shrink: 0;
        align-self: center;
    }

    .msg-row:hover .msg-hover-menu-btn,
    .msg-hover-menu-btn:focus-visible {
        opacity: 1;
        pointer-events: auto;
    }

    .msg-hover-menu-btn:hover {
        background: var(--chat-item-hover);
        color: var(--chat-header-name-color);
    }

    .msg-bubble.is-pinned {
        border-color: rgba(250, 204, 21, 0.92) !important;
        box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.72), 0 10px 24px rgba(250, 204, 21, 0.26), 0 8px 18px rgba(17, 24, 39, 0.24);
    }

    .msg-bubble.is-pinned::after {
        content: 'PIN';
        position: absolute;
        top: -0.62rem;
        right: 0.52rem;
        font-size: 0.58rem;
        letter-spacing: 0.08em;
        font-weight: 800;
        line-height: 1;
        padding: 0.18rem 0.32rem;
        border-radius: 999px;
        color: #111827;
        background: linear-gradient(135deg, #fde047, #f59e0b);
        box-shadow: 0 6px 14px rgba(245, 158, 11, 0.34);
        text-transform: uppercase;
        pointer-events: none;
    }

    .msg-row.has-reaction {
        padding-bottom: 0.95rem;
    }

    .msg-reaction-badge {
        position: absolute;
        right: -0.36rem;
        bottom: -0.9rem;
        margin-top: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 1.5rem;
        border-radius: 0.75rem;
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 72%, transparent);
        background: linear-gradient(180deg, color-mix(in srgb, var(--chat-header-bg) 92%, #ffffff 8%), var(--chat-header-bg));
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.24);
        font-size: 0.92rem;
        line-height: 1;
        padding: 0 0.42rem;
        z-index: 3;
        gap: 0.28rem;
        cursor: pointer;
    }

    .msg-reaction-icons {
        display: inline-flex;
        align-items: center;
        gap: 0.12rem;
        font-size: 0.88rem;
        line-height: 1;
    }

    .msg-reaction-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .msg-reaction-count {
        margin-left: 0.08rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--chat-header-name-color);
        letter-spacing: 0.01em;
    }

    .chat-reactions-overlay {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.62);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 15000;
        padding: 1rem;
    }

    .chat-reactions-overlay.show {
        display: flex;
    }

    .chat-reactions-modal {
        width: min(560px, 100%);
        max-height: min(76vh, 620px);
        border-radius: 14px;
        border: 1px solid var(--chat-header-border);
        background: var(--chat-header-bg);
        color: var(--chat-header-name-color);
        box-shadow: 0 22px 46px rgba(2, 6, 23, 0.46);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .chat-reactions-head {
        padding: 0.78rem 0.92rem;
        border-bottom: 1px solid var(--chat-header-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
    }

    .chat-reactions-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }

    .chat-reactions-close {
        border: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--chat-item-hover);
        color: var(--chat-header-name-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.15rem;
        line-height: 1;
    }

    .chat-media-overlay {
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
        background: radial-gradient(circle at center, rgba(15, 23, 42, 0.52) 0%, rgba(2, 6, 23, 0.9) 58%, rgba(2, 6, 23, 0.97) 100%);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2147483000;
        padding: clamp(0.45rem, 1.3vw, 0.95rem);
        backdrop-filter: blur(18px) saturate(92%);
        overflow: hidden;
        isolation: isolate;
    }

    .chat-media-overlay::before,
    .chat-media-overlay::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        width: clamp(54px, 11vw, 138px);
        pointer-events: none;
        z-index: 1;
        backdrop-filter: blur(22px);
    }

    .chat-media-overlay::before {
        left: 0;
        background: linear-gradient(90deg, rgba(30, 41, 59, 0.62) 0%, rgba(15, 23, 42, 0.34) 65%, rgba(15, 23, 42, 0) 100%);
    }

    .chat-media-overlay::after {
        right: 0;
        background: linear-gradient(270deg, rgba(30, 41, 59, 0.62) 0%, rgba(15, 23, 42, 0.34) 65%, rgba(15, 23, 42, 0) 100%);
    }

    .chat-media-overlay.show {
        display: flex;
    }

    .chat-media-modal {
        width: 100%;
        height: 100%;
        max-width: none;
        max-height: none;
        position: relative;
        z-index: 2;
        border-radius: 0;
        border: 0;
        background: transparent;
        color: var(--chat-header-name-color);
        box-shadow: none;
        display: flex;
        flex-direction: column;
        overflow: visible;
    }

    .chat-media-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: absolute;
        top: calc(env(safe-area-inset-top, 0px) + 0.9rem);
        left: calc(env(safe-area-inset-left, 0px) + 0.9rem);
        right: calc(env(safe-area-inset-right, 0px) + 0.9rem);
        z-index: 22;
        padding: 0;
        pointer-events: none;
    }

    .chat-media-head-left,
    .chat-media-head-right {
        display: flex;
        align-items: center;
        gap: 0.48rem;
        pointer-events: auto;
    }

    .chat-media-title {
        display: none;
    }

    .chat-media-icon-btn {
        border: 0;
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 50%;
        background: rgba(15, 23, 42, 0.86);
        color: #f8fafc;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1;
        box-shadow: 0 10px 24px rgba(2, 6, 23, 0.42);
        backdrop-filter: blur(7px);
        transition: background 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
    }

    .chat-media-icon-btn:hover {
        background: rgba(30, 41, 59, 0.96);
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(2, 6, 23, 0.48);
    }

    .chat-media-icon-btn:focus-visible {
        outline: 2px solid rgba(148, 163, 184, 0.66);
        outline-offset: 2px;
    }

    .chat-media-close {
        font-size: 1.2rem;
        padding-top: 1px;
    }

    .chat-media-icon {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .chat-media-body {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        overflow: hidden;
        background: transparent;
    }

    .chat-media-view {
        display: block;
        max-width: min(96vw, 1560px);
        max-height: calc(100vh - 1.2rem);
        width: auto;
        height: auto;
        margin: auto;
        border: 0;
        border-radius: 8px;
        background: transparent;
        box-shadow: 0 18px 40px rgba(2, 6, 23, 0.44);
        object-fit: contain;
    }

    .chat-media-view.video {
        max-width: min(94vw, 1380px);
        max-height: calc(100vh - 1.2rem);
        width: auto;
        height: auto;
        background: #000;
    }

    .chat-media-stage {
        width: 100%;
        height: 100%;
        max-width: none;
        max-height: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chat-media-frame {
        width: auto;
        height: auto;
        max-width: calc(100vw - clamp(106px, 22vw, 264px));
        max-height: calc(100vh - 1.2rem);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 767.98px) {
        .chat-media-overlay::before,
        .chat-media-overlay::after {
            width: 30px;
        }

        .chat-media-head {
            top: 0.45rem;
            left: 0.45rem;
            right: 0.45rem;
        }

        .chat-media-icon-btn {
            width: 38px;
            height: 38px;
        }

        .chat-media-frame,
        .chat-media-view,
        .chat-media-view.video {
            max-width: calc(100vw - 1.25rem);
            max-height: calc(100vh - 1.15rem);
        }
    }

    html.chat-media-open,
    body.chat-media-open {
        overflow: hidden !important;
    }

    .chat-reactions-tabs {
        display: flex;
        gap: 0.35rem;
        padding: 0.65rem 0.85rem 0.45rem;
        border-bottom: 1px solid var(--chat-header-border);
        overflow-x: auto;
    }

    .chat-reactions-tab {
        border: 1px solid var(--chat-header-border);
        background: transparent;
        color: var(--chat-header-name-color);
        border-radius: 999px;
        padding: 0.26rem 0.72rem;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
    }

    .chat-reactions-tab.active {
        background: var(--chat-item-hover);
        border-color: transparent;
    }

    .chat-reactions-list {
        padding: 0.45rem 0.25rem 0.55rem;
        overflow-y: auto;
    }

    .chat-reactions-empty {
        padding: 1.2rem 0.9rem 1.4rem;
        text-align: center;
        color: var(--chat-header-sub-color);
        font-size: 0.9rem;
    }

    .chat-reaction-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.5rem 0.72rem;
        border-radius: 10px;
        margin: 0 0.45rem;
    }

    .chat-reaction-row:hover {
        background: var(--chat-item-hover);
    }

    .chat-reaction-user {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.58rem;
    }

    .chat-reaction-avatar,
    .chat-reaction-avatar-text {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .chat-reaction-avatar {
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.14);
    }

    .chat-reaction-avatar-text {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .chat-reaction-user-meta {
        min-width: 0;
    }

    .chat-reaction-user-name {
        font-size: 0.89rem;
        font-weight: 700;
        line-height: 1.15;
        color: var(--chat-header-name-color);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-reaction-user-sub {
        font-size: 0.76rem;
        color: var(--chat-header-sub-color);
        margin-top: 0.1rem;
    }

    .chat-reaction-emoji {
        font-size: 1.2rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .msg-row.own .msg-reaction-badge {
        right: auto;
        left: -0.36rem;
    }

    .msg-bubble.has-media .msg-reaction-badge {
        bottom: 0.36rem;
        right: 0.36rem;
    }

    .msg-row.own .msg-bubble.has-media .msg-reaction-badge {
        right: auto;
        left: 0.36rem;
    }

    .msg-reply-quote {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.14rem;
        max-width: 100%;
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 42%, transparent);
        background: color-mix(in srgb, var(--chat-header-bg) 78%, transparent);
        box-shadow: none;
        border-radius: 7px;
        padding: 0.3rem 0.42rem 0.32rem 0.6rem;
        margin-bottom: 0.36rem;
        font-size: 0.73rem;
        line-height: 1.24;
        color: var(--chat-header-name-color);
        overflow: hidden;
    }

    .msg-reply-quote::before {
        content: '';
        position: absolute;
        top: 0.26rem;
        bottom: 0.26rem;
        left: 0.24rem;
        width: 2px;
        border-radius: 999px;
        background: linear-gradient(180deg, #38bdf8 0%, #2563eb 100%);
    }

    .msg-row.own .msg-reply-quote {
        border-color: color-mix(in srgb, var(--chat-own-bg) 30%, rgba(255, 255, 255, 0.74));
        background: color-mix(in srgb, var(--chat-own-bg) 10%, #ffffff 90%);
        box-shadow: none;
    }

    .msg-row.own .msg-reply-quote::before {
        background: linear-gradient(180deg, #dbeafe 0%, #ffffff 100%);
    }

    .msg-reply-quote strong {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        flex-shrink: 0;
        font-size: 0.58rem;
        color: var(--chat-header-sub-color);
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        opacity: 0.86;
    }

    .msg-reply-quote-text {
        display: block;
        min-width: 0;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--chat-header-name-color);
        font-size: 0.75rem;
        font-weight: 600;
        opacity: 0.93;
    }

    .msg-action-menu {
        position: fixed;
        min-width: 238px;
        border-radius: 14px;
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 85%, transparent);
        background: color-mix(in srgb, var(--chat-header-bg) 96%, #0ea5e9 4%);
        box-shadow: 0 20px 42px rgba(2, 6, 23, 0.34);
        padding: 0.45rem;
        z-index: 14000;
        display: none;
        --msg-menu-arrow-left: 50%;
    }

    .msg-action-menu.show {
        display: block;
    }

    .msg-action-menu::after {
        content: '';
        position: absolute;
        left: var(--msg-menu-arrow-left);
        transform: translateX(-50%);
        border-left: 9px solid transparent;
        border-right: 9px solid transparent;
    }

    .msg-action-menu[data-placement="top"]::after {
        top: 100%;
        border-top: 9px solid color-mix(in srgb, var(--chat-header-bg) 96%, #0ea5e9 4%);
    }

    .msg-action-menu[data-placement="bottom"]::after {
        bottom: 100%;
        border-bottom: 9px solid color-mix(in srgb, var(--chat-header-bg) 96%, #0ea5e9 4%);
    }

    .msg-action-emoji-row {
        display: flex;
        gap: 0.28rem;
        margin-bottom: 0.42rem;
        padding: 0.2rem 0.1rem 0.52rem;
        border-bottom: 1px dashed color-mix(in srgb, var(--chat-header-border) 75%, transparent);
    }

    .msg-action-emoji-row.is-hidden {
        display: none;
    }

    .msg-emoji-btn {
        border: 1px solid transparent;
        background: color-mix(in srgb, var(--chat-item-hover) 78%, transparent);
        width: 30px;
        height: 30px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.14s ease, border-color 0.14s ease, background-color 0.14s ease;
    }

    .msg-emoji-btn:hover {
        transform: translateY(-1px);
        border-color: color-mix(in srgb, var(--chat-header-border) 85%, transparent);
        background: color-mix(in srgb, var(--chat-item-hover) 60%, transparent);
    }

    .msg-action-item {
        width: 100%;
        border: 0;
        background: transparent;
        color: var(--chat-header-name-color);
        text-align: left;
        border-radius: 10px;
        padding: 0.5rem 0.62rem;
        font-size: 0.84rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .msg-action-item:hover,
    .msg-action-item:focus-visible {
        background: color-mix(in srgb, var(--chat-item-hover) 78%, transparent);
        outline: none;
    }

    .msg-action-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: transparent;
        border-radius: 99px;
    }

    .msg-action-item:hover::before,
    .msg-action-item:focus-visible::before {
        background: color-mix(in srgb, var(--chat-actions-color) 74%, transparent);
    }

    .msg-action-item.danger {
        color: #ef4444;
    }

    .msg-action-item.is-hidden {
        display: none;
    }

    .msg-row.msg-group-last,
    .msg-row.msg-group-only {
        margin-bottom: 0.55rem;
    }

    .msg-row.own {
        margin-left: auto;
        justify-content: flex-end;
    }

    .msg-row:not(.own) {
        margin-right: auto;
    }

    .msg-bubble {
        max-width: min(72%, 620px);
        border-radius: 14px;
        padding: 0.56rem 0.84rem;
        font-size: 0.95rem;
        line-height: 1.5;
        color: var(--chat-bubble-color);
        background: var(--chat-bubble-bg);
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 88%, transparent);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.09);
        position: relative;
        overflow: visible;
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: pre-line;
    }

    .msg-row.own .msg-bubble {
        background: var(--chat-own-bubble-bg);
        color: var(--chat-own-bubble-color);
        border-color: var(--chat-own-bubble-border);
        box-shadow: var(--chat-own-bubble-shadow);
    }

    .msg-bubble.has-media {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none;
        padding: 0;
        width: auto;
        max-width: min(260px, 82vw);
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .msg-row.own .msg-bubble.has-media {
        margin-left: auto;
        align-items: flex-end;
    }

    .msg-row:not(.own) .msg-bubble.has-media {
        margin-right: auto;
    }

    .msg-bubble.has-media.is-pinned {
        border: 2px solid rgba(250, 204, 21, 0.92) !important;
        border-radius: 0.8rem;
        box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.55), 0 10px 24px rgba(250, 204, 21, 0.22) !important;
        padding: 2px;
        background: color-mix(in srgb, #f59e0b 18%, transparent) !important;
    }

    .msg-bubble.is-unsent {
        font-style: italic;
        opacity: 0.88;
        border-style: dashed;
        color: var(--chat-meta-color);
    }

    .msg-row.own .msg-bubble.is-unsent {
        color: var(--chat-own-meta-color);
    }

    .msg-bubble.has-media .msg-meta {
        margin-top: 0.28rem;
        padding: 0 0.2rem;
        width: 100%;
    }

    /* â”€â”€ Message grouping â€“ own (right, teal) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .msg-row.own .msg-bubble.msg-group-first  { border-radius: 14px 14px 6px 14px; }
    .msg-row.own .msg-bubble.msg-group-middle { border-radius: 14px 6px 6px 14px; }
    .msg-row.own .msg-bubble.msg-group-last   { border-radius: 6px 14px 14px 14px; }

    /* â”€â”€ Message grouping â€“ other (left, gray) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .msg-row:not(.own) .msg-bubble.msg-group-first  { border-radius: 14px 14px 14px 6px; }
    .msg-row:not(.own) .msg-bubble.msg-group-middle { border-radius: 6px 14px 14px 6px; }
    .msg-row:not(.own) .msg-bubble.msg-group-last   { border-radius: 6px 14px 14px 14px; }

    /* â”€â”€ Thread avatar (left side, other's messages) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .msg-thread-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        flex-shrink: 0;
        object-fit: cover;
        align-self: flex-end;
        border: 2px solid rgba(255, 255, 255, 0.15);
    }

    .msg-thread-avatar-text {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        align-self: flex-end;
    }

    .msg-thread-avatar-placeholder {
        width: 28px;
        height: 28px;
        flex-shrink: 0;
        visibility: hidden;
    }

    /* â”€â”€ Date separator â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .msg-date-sep {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.7rem 0 0.35rem;
        font-size: 0.76rem;
        color: var(--chat-meta-color);
        letter-spacing: 0.03em;
    }

    .msg-date-sep::before,
    .msg-date-sep::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--chat-header-border);
    }

    /* â”€â”€ Sent / Seen status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .msg-seen {
        text-align: right;
        font-size: 0.74rem;
        color: var(--chat-meta-color);
        padding: 0.05rem 0.5rem 0.45rem;
        font-style: normal;
        font-weight: 600;
    }

    /* â”€â”€ Scroll-to-bottom button â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    #chat-scroll-btn {
        position: absolute;
        bottom: 5.5rem;
        right: 1.25rem;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--chat-header-bg);
        border: 1px solid var(--chat-header-border);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.22);
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        color: var(--chat-header-name-color);
        font-size: 1rem;
        transition: opacity 0.2s;
    }

    #chat-scroll-btn.visible {
        display: flex;
    }

    #chat-scroll-btn .scroll-btn-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: #fff;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 700;
        min-width: 16px;
        height: 16px;
        padding: 0 3px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .msg-meta-outside {
        margin: 0;
        padding: 0 0.18rem;
        font-size: 0.72rem;
        line-height: 1.2;
        color: var(--chat-meta-color);
        display: flex;
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        pointer-events: none;
        transition: opacity 0.14s ease, max-height 0.14s ease, margin 0.14s ease;
    }

    .msg-meta-outside.own {
        justify-content: flex-end;
        color: var(--chat-own-meta-color);
    }

    .msg-meta-outside.other {
        justify-content: flex-start;
    }

    .msg-block {
        display: block;
        width: 100%;
        max-width: 100%;
    }

    .msg-block.own {
        margin-left: 0;
    }

    .msg-block.other {
        margin-right: 0;
    }

    .msg-row:hover + .msg-meta-outside,
    .msg-row:focus-within + .msg-meta-outside {
        margin: -0.04rem 0 0.34rem;
        max-height: 1.2rem;
        opacity: 1;
        pointer-events: auto;
    }

    @media (hover: none) {
        .msg-meta-outside {
            margin: -0.04rem 0 0.34rem;
            max-height: 1.2rem;
            opacity: 1;
            pointer-events: auto;
        }

        .msg-hover-menu-btn {
            opacity: 0.9;
            pointer-events: auto;
        }
    }

    .btchat-compose {
        border-top: 1px solid var(--chat-compose-border);
        padding: 0.86rem 1.02rem calc(1rem + env(safe-area-inset-bottom, 0px));
        background: color-mix(in srgb, var(--chat-compose-bg) 95%, transparent);
        backdrop-filter: blur(6px);
    }

    .btchat-compose-warning {
        display: none;
        align-items: flex-start;
        gap: 0.55rem;
        margin: 0 0 0.65rem;
        padding: 0.72rem 0.82rem;
        border-radius: 12px;
        border: 1px solid rgba(248, 113, 113, 0.28);
        background: rgba(127, 29, 29, 0.22);
        color: #fecaca;
        font-size: 0.83rem;
        line-height: 1.4;
    }

    .btchat-compose-warning.show {
        display: none;
    }

    .btchat-compose-warning.show[data-warning-visible="1"] {
        display: flex;
    }

    .btchat-compose-warning-icon {
        flex-shrink: 0;
        width: 1.1rem;
        text-align: center;
        font-weight: 700;
        line-height: 1.3;
    }

    .btchat-compose-warning-text {
        min-width: 0;
        font-weight: 600;
    }

    .btchat-compose-inner {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 14px;
        background: var(--chat-compose-inner-bg);
        border: 1px solid var(--chat-compose-inner-border);
        padding: 0.5rem;
        min-height: 52px;
    }

    .btchat-compose-input {
        flex: 1;
        background: transparent;
        border: 0;
        color: var(--chat-compose-input-color);
        outline: none;
        font-size: 0.93rem;
        resize: none;
        overflow-y: auto;
        min-height: 40px;
        max-height: 140px;
        line-height: 1.4;
        padding: 0.48rem 0.32rem;
    }

    .btchat-compose-input::placeholder {
        color: var(--chat-compose-input-placeholder);
    }

    .btchat-send-btn {
        border: 0;
        border-radius: 12px;
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--chat-send-from), var(--chat-send-to));
        color: var(--chat-send-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 18px rgba(14, 165, 233, 0.35);
    }

    .btchat-send-btn:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }

    .btchat-send-btn.is-like {
        background: none;
        font-size: 1.2rem;
        line-height: 1;
        color: var(--chat-attach-btn-color);
        box-shadow: none;
    }

    .msg-time-exact {
        opacity: 0.7;
        font-style: italic;
    }

    .msg-media {
        display: block;
        max-width: 100%;
        max-height: 260px;
        width: 100%;
        height: auto;
        border: 0;
        border-radius: 0.65rem;
        margin-bottom: 0.35rem;
        cursor: pointer;
    }

    .msg-bubble.has-media .msg-media,
    .msg-bubble.has-media .msg-media-video {
        margin-left: 0;
        margin-right: 0;
    }

    .msg-media-video {
        display: block;
        max-width: 100%;
        max-height: 280px;
        width: 100%;
        height: auto;
        border: 0;
        border-radius: 0.65rem;
        margin-bottom: 0.35rem;
        cursor: pointer;
    }

    .btchat-attach-btn {
        background: color-mix(in srgb, var(--chat-item-hover) 75%, transparent);
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 85%, transparent);
        width: 36px;
        height: 36px;
        padding: 0;
        color: var(--chat-attach-btn-color);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        line-height: 1;
        flex-shrink: 0;
        align-self: center;
        border-radius: 11px;
        transition: transform 0.14s ease;
    }

    .btchat-attach-btn:hover {
        transform: translateY(-1px);
    }

    .btchat-emoji-btn {
        background: color-mix(in srgb, var(--chat-item-hover) 75%, transparent);
        border: 1px solid color-mix(in srgb, var(--chat-header-border) 85%, transparent);
        width: 36px;
        height: 36px;
        padding: 0;
        color: var(--chat-attach-btn-color);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        line-height: 1;
        flex-shrink: 0;
        align-self: center;
        border-radius: 11px;
        transition: transform 0.14s ease;
    }

    .btchat-emoji-btn:hover {
        transform: translateY(-1px);
    }

    #chat-emoji-picker {
        display: none;
        position: absolute;
        bottom: calc(100% + 10px);
        left: 0.8rem;
        width: min(360px, calc(100vw - 2.2rem));
        border-radius: 14px;
        border: 1px solid var(--chat-header-border);
        background: var(--chat-header-bg);
        box-shadow: 0 16px 36px rgba(2, 6, 23, 0.45);
        z-index: 120;
        overflow: hidden;
    }

    #chat-emoji-picker.show {
        display: block;
    }

    .chat-emoji-search-wrap {
        padding: 0.65rem 0.65rem 0.45rem;
    }

    .chat-emoji-search {
        width: 100%;
        border: 1px solid var(--chat-compose-inner-border);
        border-radius: 999px;
        background: var(--chat-compose-inner-bg);
        color: var(--chat-compose-input-color);
        font-size: 0.86rem;
        padding: 0.5rem 0.78rem;
        outline: none;
    }

    .chat-emoji-grid {
        display: grid;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        gap: 0.25rem;
        padding: 0 0.55rem 0.5rem;
        max-height: 210px;
        overflow-y: auto;
    }

    .chat-emoji-item {
        border: 0;
        background: transparent;
        border-radius: 8px;
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.24rem;
        line-height: 1;
        padding: 0;
    }

    .chat-emoji-item:hover {
        background: var(--chat-item-hover);
    }

    .chat-emoji-empty {
        padding: 0.2rem 0.1rem 0.65rem;
        text-align: center;
        font-size: 0.78rem;
        color: var(--chat-snippet-color);
    }

    .chat-emoji-tabs {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.15rem;
        border-top: 1px solid var(--chat-header-border);
        padding: 0.35rem 0.45rem;
        background: var(--chat-compose-inner-bg);
    }

    .chat-emoji-tab {
        border: 0;
        background: transparent;
        color: var(--chat-snippet-color);
        width: 30px;
        height: 28px;
        border-radius: 8px;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .chat-emoji-tab.active,
    .chat-emoji-tab:hover {
        color: var(--chat-header-name-color);
        background: var(--chat-item-hover);
    }

    #chat-media-preview {
        display: none;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.9rem 0;
        font-size: 0.82rem;
        color: var(--chat-snippet-color);
    }

    #chat-reply-preview {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.4rem 0.9rem 0;
        font-size: 0.8rem;
        color: var(--chat-snippet-color);
    }

    #chat-reply-preview.has-reply {
        display: flex;
    }

    .chat-reply-remove {
        background: none;
        border: 0;
        cursor: pointer;
        color: #ef4444;
        padding: 0;
        font-size: 1rem;
        line-height: 1;
    }

    #chat-media-preview.has-file {
        display: flex;
    }

    .chat-preview-thumb {
        width: 38px;
        height: 38px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid var(--chat-compose-inner-border);
    }

    .chat-preview-remove {
        background: none;
        border: 0;
        cursor: pointer;
        color: #ef4444;
        padding: 0;
        font-size: 1rem;
        line-height: 1;
    }

    .btchat-system-alert {
        position: absolute;
        top: 1rem;
        right: 1rem;
        max-width: 320px;
        z-index: 220;
    }

    .btchat-empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--chat-empty-color);
        padding: 2rem;
    }

    @media (max-width: 1199px) {
        .btchat-shell {
            grid-template-columns: 300px minmax(0, 1fr);
            height: 100%;
            max-height: 100%;
            min-height: 0;
        }
    }

    @media (max-width: 991px) {
        body.chat-page-lock .nxl-container {
            height: calc(100dvh - 62px);
            max-height: calc(100dvh - 62px);
        }

        .nxl-container .nxl-content {
            height: calc(100dvh - 74px);
            max-height: calc(100dvh - 74px);
            padding: 0 !important;
        }

        .main-content {
            padding: 0 !important;
        }

        .btchat-shell {
            display: block;
            height: 100%;
            max-height: 100%;
            min-height: 0;
            border-radius: 0;
            border-left: 0;
            border-right: 0;
        }

        .btchat-left {
            max-height: none;
            min-height: 0;
            height: 100%;
        }

        .btchat-main {
            min-height: 0;
            display: none;
            height: 100%;
        }

        .btchat-shell.btchat-mobile-convo-open .btchat-left {
            display: none;
        }

        .btchat-shell.btchat-mobile-convo-open .btchat-main {
            display: flex;
        }

        .btchat-left-header {
            padding: 0.9rem 0.9rem 0.65rem;
        }

        .btchat-search-wrap {
            padding: 0 0.78rem 0.78rem;
        }

        .btchat-item {
            margin: 0.14rem 0.45rem;
            padding: 0.6rem;
        }

        .btchat-chat-header {
            padding: 0.8rem 0.9rem;
        }

        .btchat-back-btn {
            display: inline-flex;
        }

        .btchat-thread {
            padding: 0.9rem 0.8rem;
        }

        .btchat-compose {
            padding: 0.72rem 0.78rem calc(3.6rem + env(safe-area-inset-bottom, 0px));
        }

        .msg-bubble {
            max-width: 90%;
        }

        .msg-bubble.has-media {
            max-width: min(220px, 78vw);
        }

        .msg-media {
            max-width: 100%;
        }

        .msg-media-video {
            max-width: 100%;
        }

        #chat-scroll-btn {
            right: 0.85rem;
            bottom: 5rem;
        }
    }

    @media (max-width: 767px) {
        .btchat-left-title {
            font-size: 1.05rem;
        }

        .btchat-avatar,
        .btchat-avatar-text {
            width: 42px;
            height: 42px;
        }

        .btchat-time {
            font-size: 0.72rem;
        }

        .btchat-snippet {
            font-size: 0.84rem;
        }

        .btchat-actions {
            gap: 0.45rem;
        }

        .btchat-menu {
            min-width: 148px;
        }

        .msg-bubble {
            max-width: 94%;
            font-size: 0.9rem;
            padding: 0.5rem 0.74rem;
        }

        .msg-reaction-badge {
            min-width: 1.9rem;
            height: 1.4rem;
            padding: 0 0.34rem;
        }

        .btchat-compose-inner {
            gap: 0.4rem;
            padding: 0.46rem;
            min-height: 50px;
        }

        .btchat-attach-btn,
        .btchat-emoji-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
        }

        .btchat-send-btn {
            width: 38px;
            height: 38px;
        }

        #chat-emoji-picker {
            left: 0.45rem;
            width: calc(100vw - 0.9rem);
            max-width: none;
        }

        .chat-reactions-overlay {
            padding: 0.65rem;
        }

        .chat-reactions-modal {
            max-height: min(82dvh, 620px);
        }
    }

    @media (max-width: 480px) {
        .btchat-chat-sub {
            font-size: 0.76rem;
        }

        .btchat-thread {
            padding: 0.75rem 0.62rem;
        }

        .msg-row {
            gap: 0.3rem;
        }

        .msg-meta-outside {
            font-size: 0.68rem;
        }

        .msg-action-menu {
            min-width: 208px;
            max-width: calc(100vw - 0.8rem);
        }

        .chat-confirm-overlay,
        .chat-report-overlay,
        .chat-reactions-overlay,
        .chat-media-overlay {
            padding: 0.3rem;
        }

        .chat-confirm-modal,
        .chat-report-modal,
        .chat-reactions-modal,
        .chat-media-modal {
            width: 100%;
        }

        .chat-media-body {
            padding: 0.4rem 0;
        }

        .chat-media-view {
            max-width: calc(100vw - 0.6rem);
            max-height: calc(100vh - 0.6rem);
            border-radius: 16px;
        }

        .chat-media-view.video {
            width: calc(100vw - 0.6rem);
        }

        .chat-media-close {
            min-width: 80px;
            height: 36px;
            padding: 0 0.72rem 0 0.58rem;
            font-size: 0.78rem;
        }
    }
</style>
<div class="main-content">
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger btchat-page-alert"><?php echo chat_esc($errorMessage); ?></div>
    <?php endif; ?>
    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success btchat-page-alert"><?php echo chat_esc($successMessage); ?></div>
    <?php endif; ?>

    <div class="btchat-shell" id="btchat-app" data-selected-user-id="<?php echo (int)$selectedUserId; ?>">
        <aside class="btchat-left">
            <div class="btchat-left-header">
                <h2 class="btchat-left-title">Inbox</h2>
            </div>
            <div class="btchat-search-wrap">
                <input type="search" class="btchat-search" id="btchat-search" placeholder="Search contacts">
            </div>
            <div class="btchat-list" id="btchat-list">
                <?php if (empty($contacts)): ?>
                    <div class="px-3 py-4 text-white-50">No users available.</div>
                <?php else: ?>
                    <?php foreach ($normalizedContacts as $contact): ?>
                        <?php
                        $isActiveContact = (int)$contact['id'] === $selectedUserId;
                        $contactName = (string)$contact['name'];
                        ?>
                        <a class="btchat-item<?php echo $isActiveContact ? ' active' : ''; ?>" href="<?php echo chat_esc(chat_page_url((int)$contact['id'])); ?>" data-user-id="<?php echo (int)$contact['id']; ?>">
                            <span class="btchat-avatar-wrap">
                                <img src="<?php echo chat_esc((string)$contact['avatar_path']); ?>" alt="<?php echo chat_esc($contactName); ?>" class="btchat-avatar" onerror="this.style.display='none';var f=this.nextElementSibling;if(f&&f.classList.contains('btchat-avatar-text'))f.style.removeProperty('display');">
                                <span class="btchat-avatar-text" style="display:none"><?php echo chat_esc((string)$contact['initials']); ?></span>
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
                &#8595;<span class="scroll-btn-badge" style="display:none"></span>
            </button>
            <?php if ($normalizedSelectedContact): ?>
                <?php
                $selectedName = (string)$normalizedSelectedContact['name'];
                ?>
                <div class="btchat-chat-header" id="btchat-chat-header">
                    <div class="btchat-chat-title">
                        <button type="button" class="btchat-back-btn" id="btchat-mobile-back" aria-label="Back to conversations" title="Back">&#8592;</button>
                        <span class="btchat-avatar-wrap">
                            <img src="<?php echo chat_esc((string)$normalizedSelectedContact['avatar_path']); ?>" alt="<?php echo chat_esc($selectedName); ?>" class="btchat-avatar" onerror="this.style.display='none';var f=this.nextElementSibling;if(f&&f.classList.contains('btchat-avatar-text'))f.style.removeProperty('display');">
                            <span class="btchat-avatar-text" style="display:none"><?php echo chat_esc((string)$normalizedSelectedContact['initials']); ?></span>
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
                            $isUnsentMessage = !empty($message['is_unsent']);
                            ?>
                            <div class="msg-block<?php echo !empty($message['is_own']) ? ' own' : ' other'; ?>">
                                <div class="msg-row<?php echo !empty($message['is_own']) ? ' own' : ''; ?><?php echo $hasReaction ? ' has-reaction' : ''; ?>">
                                    <?php if (!empty($message['is_own'])): ?>
                                        <button type="button" class="msg-hover-menu-btn" data-message-id="<?php echo (int)($message['message_id'] ?? 0); ?>">&#8226;&#8226;&#8226;</button>
                                    <?php endif; ?>
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
                                        <?php if ($hasReaction): ?>
                                            <button type="button" class="msg-reaction-badge" data-reaction-mid="<?php echo (int)($message['message_id'] ?? 0); ?>" aria-label="View reactions">
                                                <span class="msg-reaction-icons"><?php foreach ($reactionIcons as $reactionIcon): ?><span class="msg-reaction-icon"><?php echo chat_esc((string)$reactionIcon); ?></span><?php endforeach; ?></span>
                                                <span class="msg-reaction-count"><?php echo $reactionTotal; ?></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (empty($message['is_own']) && !$isUnsentMessage): ?>
                                        <button type="button" class="msg-hover-menu-btn" data-message-id="<?php echo (int)($message['message_id'] ?? 0); ?>">&#8226;&#8226;&#8226;</button>
                                    <?php endif; ?>
                                </div>
                                <div class="msg-meta-outside <?php echo !empty($message['is_own']) ? 'own' : 'other'; ?>" title="<?php echo chat_esc((string)$message['time_full']); ?>">
                                    <?php echo chat_esc((string)($message['time_clock'] !== '' ? $message['time_clock'] : ($message['time_exact'] !== '' ? $message['time_exact'] : $message['time_label']))); ?>
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
                        <input type="file" name="chat_media" id="chat-media-input" accept="image/*" style="display:none">
                        <div id="chat-reply-preview">
                            <span id="chat-reply-label"></span>
                            <button type="button" class="chat-reply-remove" id="chat-reply-remove" title="Cancel reply">&#x2715;</button>
                        </div>
                        <div id="chat-emoji-picker">
                            <div class="chat-emoji-search-wrap">
                                <input type="search" id="chat-emoji-search" class="chat-emoji-search" placeholder="Search emoji">
                            </div>
                            <div class="chat-emoji-grid" id="chat-emoji-grid"></div>
                            <div class="chat-emoji-empty" id="chat-emoji-empty" style="display:none">No emoji found</div>
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
                            <img id="chat-preview-thumb" class="chat-preview-thumb" src="" alt="" style="display:none">
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

<script>
    (function () {
        if (document.documentElement) {
            document.documentElement.classList.add('chat-page-lock');
        }
        if (document.body) {
            document.body.classList.add('chat-page-lock');
        }

        var app = document.getElementById('btchat-app');
        if (!app || !window.fetch) {
            return;
        }

        var chatBaseUrl = <?php echo json_encode(chat_page_url(), JSON_UNESCAPED_SLASHES); ?>;
        var listEl = document.getElementById('btchat-list');
        var threadEl = document.getElementById('btchat-thread');
        var headerEl = document.getElementById('btchat-chat-header');
        var formEl = document.getElementById('btchat-compose-form');
        var inputEl = document.getElementById('btchat-message-input');
        var replyToInputEl = document.getElementById('chat-reply-to-message-id');
        var replyPreviewEl = document.getElementById('chat-reply-preview');
        var replyLabelEl = document.getElementById('chat-reply-label');
        var replyRemoveEl = document.getElementById('chat-reply-remove');
        var sendBtnEl = document.getElementById('btchat-send-btn');
        var alertEl = document.getElementById('btchat-alert');
        var composeWarningEl = document.getElementById('btchat-compose-warning');
        var composeWarningTextEl = document.getElementById('btchat-compose-warning-text');
        var searchEl = document.getElementById('btchat-search');
        var confirmModalEl = document.getElementById('chat-confirm-modal');
        var confirmTitleEl = document.getElementById('chat-confirm-title');
        var confirmTextEl = document.getElementById('chat-confirm-text');
        var confirmOkEl = document.getElementById('chat-confirm-ok');
        var confirmCancelEl = document.getElementById('chat-confirm-cancel');
        var contactModalEl = document.getElementById('chat-contact-modal');
        var contactCloseEl = document.getElementById('chat-contact-close');
        var contactAvatarHostEl = document.getElementById('chat-contact-avatar-host');
        var contactNameEl = document.getElementById('chat-contact-name');
        var contactSubEl = document.getElementById('chat-contact-sub');
        var contactUserIdEl = document.getElementById('chat-contact-user-id');
        var contactUsernameEl = document.getElementById('chat-contact-username');
        var contactEmailEl = document.getElementById('chat-contact-email');
        var contactStatusEl = document.getElementById('chat-contact-status');
        var contactMutedStateEl = document.getElementById('chat-contact-muted-state');
        var contactLastActiveEl = document.getElementById('chat-contact-last-active');
        var contactLastMessageEl = document.getElementById('chat-contact-last-message');
        var contactUnreadEl = document.getElementById('chat-contact-unread');
        var contactTotalEl = document.getElementById('chat-contact-total');
        var contactReportableEl = document.getElementById('chat-contact-reportable');
        var contactMuteEl = document.getElementById('chat-contact-mute');
        var contactReportUserEl = document.getElementById('chat-contact-report-user');
        var contactCloseSecondaryEl = document.getElementById('chat-contact-close-secondary');
        var reportModalEl = document.getElementById('chat-report-modal');
        var reportReasonEl = document.getElementById('chat-report-reason');
        var reportNoteEl = document.getElementById('chat-report-note');
        var reportOkEl = document.getElementById('chat-report-ok');
        var reportCancelEl = document.getElementById('chat-report-cancel');
        var messageActionMenuEl = document.getElementById('msg-action-menu');
        var reactionsModalEl = document.getElementById('chat-reactions-modal');
        var reactionsTabsEl = document.getElementById('chat-reactions-tabs');
        var reactionsListEl = document.getElementById('chat-reactions-list');
        var reactionsCloseEl = document.getElementById('chat-reactions-close');
        var mediaModalEl = document.getElementById('chat-media-modal');
        var mediaModalBodyEl = document.getElementById('chat-media-body');
        var mediaModalTitleEl = document.getElementById('chat-media-title');
        var mediaModalCloseEl = document.getElementById('chat-media-close');
        var mediaModalDownloadEl = document.getElementById('chat-media-download');
        var currentUserId = <?php echo (int)$currentUserId; ?>;
        var selectedUserId = parseInt(app.getAttribute('data-selected-user-id') || '0', 10) || 0;
        var initialMessages = <?php echo $initialMessagesJson; ?>;
        var selectedContactRef = null;
        var messageCache = {};
        var replyTarget = null;
        var pendingConfirmFn = null;
        var pendingReportFn = null;
        var activeMessageActionId = 0;
        var activeMessageActionTriggerEl = null;
        var pollHandle = null;
        var currentSearch = '';
        var reactionsModalState = null;
        var mediaModalState = null;
        var fetchAbortController = null;
        var stateRequestToken = 0;
        var lastContactsSignature = '';
        var lastContactsData = [];
        var lastHeaderSignature = '';
        var lastMessagesSignature = '';
        var lastRenderedUserId = 0;
        var contactModalUserId = 0;
        var suppressHeaderToggleUntil = 0;
        var mobileLayoutQuery = (typeof window.matchMedia === 'function') ? window.matchMedia('(max-width: 991px)') : null;

        // Render media viewer at document level so it is never clipped by app containers.
        if (mediaModalEl && document.body && mediaModalEl.parentNode !== document.body) {
            document.body.appendChild(mediaModalEl);
        }

        if (Array.isArray(initialMessages) && initialMessages.length) {
            initialMessages.forEach(function (msg) {
                if (!msg || !msg.message_id) { return; }
                messageCache[String(msg.message_id)] = msg;
            });
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function nl2br(value) {
            return escapeHtml(value).replace(/\n/g, '<br>');
        }

        function scrubActionTooltips(rootEl) {
            var scope = rootEl || document;
            var buttons = scope.querySelectorAll('.btchat-menu-toggle, .msg-hover-menu-btn');

            buttons.forEach(function (btn) {
                btn.removeAttribute('title');
                btn.removeAttribute('data-bs-toggle');
                btn.removeAttribute('data-bs-original-title');
                btn.removeAttribute('data-original-title');
                btn.removeAttribute('aria-describedby');

                if (window.bootstrap && window.bootstrap.Tooltip && typeof window.bootstrap.Tooltip.getInstance === 'function') {
                    var tip = window.bootstrap.Tooltip.getInstance(btn);
                    if (tip && typeof tip.dispose === 'function') {
                        tip.dispose();
                    }
                }
            });

            document.querySelectorAll('.tooltip').forEach(function (tipEl) {
                var tipId = tipEl.getAttribute('id');
                if (!tipId) {
                    tipEl.remove();
                    return;
                }

                var triggerEl = document.querySelector('[aria-describedby="' + tipId + '"]');
                if (!triggerEl || triggerEl.matches('.btchat-menu-toggle, .msg-hover-menu-btn')) {
                    tipEl.remove();
                }
            });
        }

        function parseJsonResponse(response) {
            return response.text().then(function (text) {
                var payload = null;
                try {
                    payload = text ? JSON.parse(text) : null;
                } catch (error) {
                    payload = null;
                }
                return {
                    ok: response.ok,
                    payload: payload,
                    text: text
                };
            });
        }

        function postChatAction(formData, userId) {
            return fetch(chatBaseUrl + '?user_id=' + encodeURIComponent(userId), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            }).then(parseJsonResponse);
        }

        function buildContactsSignature(contacts) {
            var items = Array.isArray(contacts) ? contacts : [];
            return items.map(function (item) {
                return [
                    item.id || 0,
                    item.last_message_at || '',
                    item.unread_count || 0,
                    item.message_count || 0,
                    item.last_message || ''
                ].join(':');
            }).join('|');
        }

        function buildHeaderSignature(contact) {
            if (!contact || !contact.id) { return ''; }
            return [
                String(contact.id || 0),
                String(contact.name || ''),
                String(contact.email || ''),
                String(contact.username || ''),
                contact.is_online ? '1' : '0',
                isConversationMuted(contact.id) ? '1' : '0'
            ].join('|');
        }

        function buildMessagesSignature(messages) {
            var items = Array.isArray(messages) ? messages : [];
            return items.map(function (item) {
                return [
                    item.message_id || 0,
                    item.time_clock || '',
                    item.time_exact || '',
                    item.message || '',
                    item.media_path || '',
                    item.is_unsent ? 1 : 0,
                    item.unsent_at || '',
                    item.reaction_count || 0,
                    item.reaction_emoji || '',
                    item.is_pinned ? 1 : 0,
                    item.is_read ? 1 : 0,
                    item.read_at || ''
                ].join(':');
            }).join('|');
        }

        function deliveryStatusLabel(msg) {
            if (!msg || !msg.is_own) {
                return '';
            }
            if (msg.is_read) {
                if (msg.read_time_exact) {
                    return 'Seen at ' + msg.read_time_exact;
                }
                return 'Seen';
            }
            return 'Delivered';
        }

        function avatarMarkup(contact) {
            var onErr = "this.style.display='none';var f=this.nextElementSibling;if(f&&f.classList.contains('btchat-avatar-text'))f.style.removeProperty('display');";
            var imgTag = '<img src="' + escapeHtml(contact ? contact.avatar_path : '') + '" alt="' + escapeHtml(contact ? contact.name : '') + '" class="btchat-avatar" onerror="' + onErr + '">';
            var spanTag = '<span class="btchat-avatar-text" style="display:none">' + escapeHtml(contact ? contact.initials : 'BT') + '</span>';
            var dotClass = contact && contact.is_online ? 'btchat-status-dot online' : 'btchat-status-dot';
            return '<span class="btchat-avatar-wrap">' + imgTag + spanTag + '<span class="' + dotClass + '"></span></span>';
        }

        function showAlert(type, message) {
            if (!alertEl) {
                return;
            }
            if (!message) {
                alertEl.innerHTML = '';
                return;
            }
            var klass = type === 'error' ? 'alert-danger' : 'alert-success';
            alertEl.innerHTML = '<div class="alert ' + klass + ' mb-0">' + escapeHtml(message) + '</div>';
            window.setTimeout(function () {
                if (alertEl) {
                    alertEl.innerHTML = '';
                }
            }, 2500);
        }

        function clearComposeWarning() {
            if (!composeWarningEl) {
                return;
            }
            composeWarningEl.classList.remove('show');
            composeWarningEl.setAttribute('data-warning-visible', '0');
            composeWarningEl.setAttribute('aria-hidden', 'true');
            if (composeWarningTextEl) {
                composeWarningTextEl.textContent = '';
            }
        }

        function showComposeWarning(message) {
            var warningText = String(message || '').trim();
            if (!composeWarningEl || !composeWarningTextEl || warningText === '') {
                clearComposeWarning();
                return;
            }
            composeWarningTextEl.textContent = warningText;
            composeWarningEl.classList.add('show');
            composeWarningEl.setAttribute('data-warning-visible', '1');
            composeWarningEl.setAttribute('aria-hidden', 'false');
        }

        function isModerationWarning(message) {
            var text = String(message || '').toLowerCase();
            return text.indexOf('message blocked') !== -1 || text.indexOf('disallowed symbol') !== -1;
        }

        function setThreadLoading(isLoading) {
            if (!threadEl) {
                return;
            }
            threadEl.classList.toggle('is-loading', !!isLoading);
        }

        function setActiveContactVisual(userId) {
            if (!listEl) {
                return;
            }
            var items = listEl.querySelectorAll('a[data-user-id]');
            items.forEach(function (item) {
                var itemUserId = parseInt(item.getAttribute('data-user-id') || '0', 10) || 0;
                item.classList.toggle('active', itemUserId === userId);
            });
        }

        function isMobileLayout() {
            return !!(mobileLayoutQuery && mobileLayoutQuery.matches);
        }

        function setMobileConversationOpen(shouldOpen) {
            if (!app) {
                return;
            }
            if (!isMobileLayout()) {
                app.classList.remove('btchat-mobile-convo-open');
                return;
            }
            app.classList.toggle('btchat-mobile-convo-open', !!shouldOpen);
        }

        function setMobileHistoryState(view, userId) {
            if (!isMobileLayout() || !window.history || typeof window.history.replaceState !== 'function') {
                return;
            }
            var normalizedView = view === 'conversation' ? 'conversation' : 'list';
            var normalizedUserId = (userId && userId > 0) ? userId : 0;
            var state = {
                btchatView: normalizedView,
                userId: normalizedUserId
            };
            var url = normalizedView === 'conversation' && normalizedUserId > 0
                ? (chatBaseUrl + '?user_id=' + normalizedUserId)
                : chatBaseUrl;
            try {
                window.history.replaceState(state, '', url);
            } catch (e) {
                // Ignore history API issues on restricted environments.
            }
        }

        function pushMobileConversationState(userId) {
            if (!isMobileLayout() || !window.history || typeof window.history.pushState !== 'function' || !(userId > 0)) {
                return;
            }
            var url = chatBaseUrl + '?user_id=' + userId;
            try {
                window.history.pushState({ btchatView: 'conversation', userId: userId }, '', url);
            } catch (e) {
                // Ignore history API issues on restricted environments.
            }
        }

        function primeHeaderFromListItem(link) {
            if (!headerEl || !link) {
                return;
            }
            var contactNameEl = link.querySelector('.btchat-name');
            var contactSnippetEl = link.querySelector('.btchat-snippet');
            var avatarImg = link.querySelector('.btchat-avatar');
            var avatarText = link.querySelector('.btchat-avatar-text');
            var contact = {
                id: selectedUserId,
                name: contactNameEl ? contactNameEl.textContent.trim() : 'Conversation',
                email: contactSnippetEl ? contactSnippetEl.textContent.trim() : '',
                username: '',
                avatar_path: avatarImg ? (avatarImg.getAttribute('src') || '') : '',
                initials: avatarText ? avatarText.textContent.trim() : 'BT',
                is_online: !!link.querySelector('.btchat-status-dot.online')
            };
            renderHeader(contact);
        }

        function renderContacts(contacts) {
            if (!listEl) {
                return;
            }
            var items = Array.isArray(contacts) ? contacts : [];
            if (currentSearch) {
                var term = currentSearch.toLowerCase();
                items = items.filter(function (item) {
                    var haystack = ((item.name || '') + ' ' + (item.username || '') + ' ' + (item.email || '')).toLowerCase();
                    return haystack.indexOf(term) !== -1;
                });
            }
            if (!items.length) {
                listEl.innerHTML = '<div class="px-3 py-4 text-white-50">No matching users.</div>';
                return;
            }
            listEl.innerHTML = items.map(function (contact) {
                var activeClass = contact.id === selectedUserId ? ' active' : '';
                var unread = contact.unread_count > 0 ? '<span class="badge rounded-pill bg-primary">' + contact.unread_count + '</span>' : '';
                var snippet = contact.last_message ? contact.last_message : 'No messages yet';
                return '' +
                    '<a class="btchat-item' + activeClass + '" href="' + chatBaseUrl + '?user_id=' + contact.id + '" data-user-id="' + contact.id + '">' +
                        avatarMarkup(contact) +
                        '<div class="btchat-meta">' +
                            '<div class="btchat-name-row">' +
                                '<span class="btchat-name">' + escapeHtml(contact.name) + '</span>' +
                                '<span class="btchat-time">' + escapeHtml(contact.last_message_label || 'No messages yet') + '</span>' +
                            '</div>' +
                            '<div class="btchat-snippet-row">' +
                                '<span class="btchat-snippet">' + escapeHtml(snippet) + '</span>' +
                                unread +
                            '</div>' +
                        '</div>' +
                    '</a>';
            }).join('');
        }

        function renderHeader(contact) {
            if (!headerEl || !contact) {
                return;
            }
            var subtitle = contact.is_online ? 'Online' : (contact.email || contact.username || '');
            var muteLabel = isConversationMuted(contact.id) ? 'Unmute conversation' : 'Mute conversation';
            headerEl.innerHTML = '' +
                '<div class="btchat-chat-title">' +
                    '<button type="button" class="btchat-back-btn" id="btchat-mobile-back" aria-label="Back to conversations" title="Back">&#8592;</button>' +
                    avatarMarkup(contact) +
                    '<div class="min-w-0">' +
                        '<div class="btchat-chat-name">' + escapeHtml(contact.name) + '</div>' +
                        '<div class="btchat-chat-sub">' + escapeHtml(subtitle) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="btchat-actions">' +
                    '<button type="button" class="btchat-menu-toggle"><i class="feather-more-horizontal"></i></button>' +
                    '<div class="btchat-menu" role="menu">' +
                        '<button type="button" class="btchat-menu-item" data-action="view-contact">Contact details</button>' +
                        '<button type="button" class="btchat-menu-item" data-action="mute-conversation">' + escapeHtml(muteLabel) + '</button>' +
                        '<div class="btchat-menu-divider" role="separator"></div>' +
                        '<button type="button" class="btchat-menu-item" data-action="refresh-chat">Refresh chat</button>' +
                        '<button type="button" class="btchat-menu-item" data-action="scroll-bottom">Jump to recent</button>' +
                        '<div class="btchat-menu-divider" role="separator"></div>' +
                        '<button type="button" class="btchat-menu-item danger" data-action="delete-conversation">Delete conversation</button>' +
                    '</div>' +
                '</div>';
            bindHeaderMenu();
            scrubActionTooltips(headerEl);
        }

        function muteStorageKey(userId) {
            return 'chatMutedUser:' + String(userId || 0);
        }

        function isConversationMuted(userId) {
            try {
                return window.localStorage.getItem(muteStorageKey(userId)) === '1';
            } catch (e) {
                return false;
            }
        }

        function setConversationMuted(userId, muted) {
            try {
                if (muted) {
                    window.localStorage.setItem(muteStorageKey(userId), '1');
                } else {
                    window.localStorage.removeItem(muteStorageKey(userId));
                }
            } catch (e) {
                // localStorage can fail in private mode; ignore silently.
            }
        }

        function togglePinMessageById(messageId, shouldPin) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'toggle-pin');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('pin_state', shouldPin ? 'pin' : 'unpin');
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to update pin.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to update pin.');
            });
        }

        function closeConfirmModal() {
            if (!confirmModalEl) { return; }
            confirmModalEl.classList.remove('show');
            confirmModalEl.setAttribute('aria-hidden', 'true');
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            pendingConfirmFn = null;
        }

        function closeContactModal() {
            if (!contactModalEl) { return; }
            contactModalEl.classList.remove('show');
            contactModalEl.setAttribute('aria-hidden', 'true');
            contactModalUserId = 0;
            closeHeaderMenus();
        }

        function latestReportableMessageIdForUser(userId) {
            if (!(userId > 0)) {
                return 0;
            }

            var latestId = 0;
            Object.keys(messageCache).forEach(function (key) {
                var msg = messageCache[key];
                if (!msg || msg.is_unsent) {
                    return;
                }
                if (parseInt(msg.sender_id || '0', 10) !== userId) {
                    return;
                }
                var mid = parseInt(msg.message_id || '0', 10);
                if (mid > latestId) {
                    latestId = mid;
                }
            });

            return latestId;
        }

        function openContactModal(contact) {
            if (!contactModalEl || !contact) { return; }

            contactModalUserId = parseInt(contact.id || '0', 10) || 0;
            var displayName = String(contact.name || contact.username || 'Unknown user');
            var username = String(contact.username || '-');
            var email = String(contact.email || '-');
            var subtitle = email !== '-' ? email : username;
            var onlineState = contact.is_online ? 'Online' : 'Offline';
            var lastActive = String(contact.last_message_label || 'No messages yet');
            var lastMessagePreview = String(contact.last_message || 'No messages yet');
            var unreadCount = Math.max(0, parseInt(contact.unread_count || '0', 10));
            var totalMessages = Math.max(0, parseInt(contact.message_count || '0', 10));
            var isMuted = isConversationMuted(contactModalUserId);
            var reportableCount = 0;

            if (lastMessagePreview.length > 180) {
                lastMessagePreview = lastMessagePreview.slice(0, 177) + '...';
            }

            Object.keys(messageCache).forEach(function (key) {
                var msg = messageCache[key];
                if (!msg || msg.is_unsent) {
                    return;
                }
                if (parseInt(msg.sender_id || '0', 10) === contactModalUserId) {
                    reportableCount += 1;
                }
            });

            if (contactAvatarHostEl) {
                contactAvatarHostEl.innerHTML = avatarMarkup(contact);
            }
            if (contactNameEl) {
                contactNameEl.textContent = displayName;
            }
            if (contactSubEl) {
                contactSubEl.textContent = subtitle;
            }
            if (contactUserIdEl) {
                contactUserIdEl.textContent = contactModalUserId > 0 ? String(contactModalUserId) : '-';
            }
            if (contactUsernameEl) {
                contactUsernameEl.textContent = username;
            }
            if (contactEmailEl) {
                contactEmailEl.textContent = email;
            }
            if (contactStatusEl) {
                contactStatusEl.textContent = onlineState;
            }
            if (contactMutedStateEl) {
                contactMutedStateEl.textContent = isMuted ? 'Muted' : 'Unmuted';
            }
            if (contactLastActiveEl) {
                contactLastActiveEl.textContent = lastActive;
            }
            if (contactLastMessageEl) {
                contactLastMessageEl.textContent = lastMessagePreview;
            }
            if (contactUnreadEl) {
                contactUnreadEl.textContent = String(unreadCount);
            }
            if (contactTotalEl) {
                contactTotalEl.textContent = String(totalMessages);
            }
            if (contactReportableEl) {
                contactReportableEl.textContent = reportableCount + (reportableCount === 1 ? ' message' : ' messages');
            }
            if (contactMuteEl) {
                contactMuteEl.textContent = isMuted ? 'Unmute conversation' : 'Mute conversation';
            }

            closeHeaderMenus();
            closeMessageActionMenu();
            contactModalEl.classList.add('show');
            contactModalEl.setAttribute('aria-hidden', 'false');
            if (contactCloseEl) {
                contactCloseEl.focus();
            }
        }

        function openConfirmModal(title, text, onConfirm, confirmLabel) {
            if (!confirmModalEl) {
                if (typeof onConfirm === 'function') { onConfirm(); }
                return;
            }
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            pendingConfirmFn = typeof onConfirm === 'function' ? onConfirm : null;
            if (confirmTitleEl) { confirmTitleEl.textContent = title || 'Confirm action'; }
            if (confirmTextEl) { confirmTextEl.textContent = text || 'Are you sure?'; }
            if (confirmOkEl) { confirmOkEl.textContent = confirmLabel || 'Confirm'; }
            confirmModalEl.classList.add('show');
            confirmModalEl.setAttribute('aria-hidden', 'false');
            if (confirmOkEl) { confirmOkEl.focus(); }
        }

        function closeMessageActionMenu() {
            if (!messageActionMenuEl) { return; }
            messageActionMenuEl.classList.remove('show');
            messageActionMenuEl.setAttribute('aria-hidden', 'true');
            messageActionMenuEl.removeAttribute('data-message-id');
            messageActionMenuEl.removeAttribute('data-placement');
            messageActionMenuEl.style.removeProperty('--msg-menu-arrow-left');
            messageActionMenuEl.style.removeProperty('left');
            messageActionMenuEl.style.removeProperty('top');
            if (activeMessageActionTriggerEl && typeof activeMessageActionTriggerEl.blur === 'function') {
                activeMessageActionTriggerEl.blur();
            }
            activeMessageActionTriggerEl = null;
            activeMessageActionId = 0;
        }

        function closeReactionsModal() {
            if (!reactionsModalEl) { return; }
            reactionsModalEl.classList.remove('show');
            reactionsModalEl.setAttribute('aria-hidden', 'true');
            reactionsModalState = null;
            if (reactionsTabsEl) { reactionsTabsEl.innerHTML = ''; }
            if (reactionsListEl) { reactionsListEl.innerHTML = ''; }
        }

        function closeMediaModal() {
            if (!mediaModalEl) { return; }
            mediaModalEl.classList.remove('show');
            mediaModalEl.setAttribute('aria-hidden', 'true');
            mediaModalState = null;
            document.documentElement.classList.remove('chat-media-open');
            if (document.body) {
                document.body.classList.remove('chat-media-open');
            }
            if (mediaModalBodyEl) {
                var activeVideo = mediaModalBodyEl.querySelector('video');
                if (activeVideo) {
                    try {
                        activeVideo.pause();
                    } catch (e) {
                        // Ignore pause issues on detached media nodes.
                    }
                }
                mediaModalBodyEl.innerHTML = '';
            }
            if (mediaModalTitleEl) {
                mediaModalTitleEl.textContent = 'Image';
            }
            if (mediaModalDownloadEl) {
                mediaModalDownloadEl.disabled = true;
            }
        }

        function getMediaFilename(src, type) {
            var fallback = type === 'video' ? 'chat-video' : 'chat-image';
            if (!src) { return fallback; }
            var cleanSrc = String(src).split('#')[0].split('?')[0];
            var parts = cleanSrc.split('/');
            var name = parts.length ? parts[parts.length - 1] : '';
            name = name ? name.trim() : '';
            return name || fallback;
        }

        function downloadMediaFromModal() {
            if (!mediaModalState || !mediaModalState.src) {
                return;
            }
            var src = mediaModalState.src;
            var anchor = document.createElement('a');
            anchor.href = src;
            anchor.download = getMediaFilename(src, mediaModalState.type);
            anchor.rel = 'noopener';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
        }

        function openMediaModal(type, src, label) {
            if (!mediaModalEl || !mediaModalBodyEl || !src) {
                return;
            }

            var mediaType = type === 'video' ? 'video' : 'image';
            var title = label || (mediaType === 'video' ? 'Video' : 'Image');
            var mediaHtml = '';

            if (mediaType === 'video') {
                mediaHtml = '<video src="' + escapeHtml(src) + '" class="chat-media-view video" controls autoplay playsinline preload="auto"></video>';
            } else {
                mediaHtml = '<img src="' + escapeHtml(src) + '" class="chat-media-view image" alt="' + escapeHtml(title) + '" loading="eager">';
            }

            mediaModalState = {
                type: mediaType,
                src: src,
                title: title
            };

            mediaModalBodyEl.innerHTML = '<div class="chat-media-stage"><div class="chat-media-frame">' + mediaHtml + '</div></div>';
            if (mediaModalTitleEl) {
                mediaModalTitleEl.textContent = title;
            }
            if (mediaModalDownloadEl) {
                mediaModalDownloadEl.disabled = false;
            }
            mediaModalEl.classList.add('show');
            mediaModalEl.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('chat-media-open');
            if (document.body) {
                document.body.classList.add('chat-media-open');
            }

            var loadedMediaEl = mediaModalBodyEl.querySelector('.chat-media-view');
            if (loadedMediaEl) {
                loadedMediaEl.addEventListener('error', function () {
                    mediaModalBodyEl.innerHTML = '<div class="chat-reactions-empty">Failed to load media.</div>';
                }, { once: true });
            }
            if (mediaModalCloseEl) {
                mediaModalCloseEl.focus();
            }
        }

        function renderReactionsModal() {
            if (!reactionsModalState || !reactionsTabsEl || !reactionsListEl) { return; }
            var summary = Array.isArray(reactionsModalState.summary) ? reactionsModalState.summary : [];
            var users = Array.isArray(reactionsModalState.users) ? reactionsModalState.users : [];
            var activeFilter = reactionsModalState.filter || 'all';
            var totalCount = parseInt(reactionsModalState.total || '0', 10);
            if (!(totalCount > 0)) {
                totalCount = users.length;
            }

            var tabsHtml = '<button type="button" class="chat-reactions-tab' + (activeFilter === 'all' ? ' active' : '') + '" data-reaction-filter="all">All ' + totalCount + '</button>';
            tabsHtml += summary.map(function (item) {
                return '<button type="button" class="chat-reactions-tab' + (activeFilter === item.emoji ? ' active' : '') + '" data-reaction-filter="' + escapeHtml(item.emoji) + '">' + escapeHtml(item.emoji) + ' ' + parseInt(item.count || '0', 10) + '</button>';
            }).join('');
            reactionsTabsEl.innerHTML = tabsHtml;

            var filteredUsers = users.filter(function (item) {
                return activeFilter === 'all' || item.emoji === activeFilter;
            });

            if (!filteredUsers.length) {
                reactionsListEl.innerHTML = '<div class="chat-reactions-empty">No reactions found.</div>';
                return;
            }

            reactionsListEl.innerHTML = filteredUsers.map(function (item) {
                var name = item.name || 'Unknown user';
                var avatar = item.avatar_path || '';
                var initials = item.initials || 'BT';
                var onErr = "this.style.display='none';var f=this.nextElementSibling;if(f)f.style.removeProperty('display');";
                return '' +
                    '<div class="chat-reaction-row">' +
                        '<div class="chat-reaction-user">' +
                            '<img src="' + escapeHtml(avatar) + '" class="chat-reaction-avatar" alt="' + escapeHtml(name) + '" onerror="' + onErr + '">' +
                            '<span class="chat-reaction-avatar-text" style="display:none">' + escapeHtml(initials) + '</span>' +
                            '<div class="chat-reaction-user-meta">' +
                                '<div class="chat-reaction-user-name">' + escapeHtml(name) + '</div>' +
                                '<div class="chat-reaction-user-sub">' + (item.is_own ? 'You reacted' : 'Reacted') + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<span class="chat-reaction-emoji">' + escapeHtml(item.emoji || '') + '</span>' +
                    '</div>';
            }).join('');
        }

        function openReactionsModal(messageId) {
            if (!reactionsModalEl || !messageId) { return; }
            var msg = messageCache[String(messageId)];
            if (!msg) { return; }

            var summaryRaw = Array.isArray(msg.reaction_summary) ? msg.reaction_summary : [];
            var summary = summaryRaw
                .map(function (item) {
                    return {
                        emoji: String(item && item.emoji ? item.emoji : ''),
                        count: parseInt(item && item.count ? item.count : '0', 10)
                    };
                })
                .filter(function (item) {
                    return item.emoji !== '' && item.count > 0;
                });

            var usersRaw = Array.isArray(msg.reaction_users) ? msg.reaction_users : [];
            var users = usersRaw
                .map(function (item) {
                    var name = String(item && item.name ? item.name : '').trim() || 'Unknown user';
                    return {
                        user_id: parseInt(item && item.user_id ? item.user_id : '0', 10) || 0,
                        name: name,
                        avatar_path: String(item && item.avatar_path ? item.avatar_path : ''),
                        initials: String(item && item.initials ? item.initials : 'BT'),
                        emoji: String(item && item.emoji ? item.emoji : ''),
                        is_own: !!(item && item.is_own)
                    };
                })
                .filter(function (item) {
                    return item.emoji !== '';
                });

            if (!summary.length && msg.reaction_emoji) {
                var fallbackCount = parseInt(msg.reaction_count || '1', 10);
                summary = [{ emoji: String(msg.reaction_emoji), count: fallbackCount > 0 ? fallbackCount : 1 }];
            }

            var total = parseInt(msg.reaction_count || '0', 10);
            if (!(total > 0)) {
                total = users.length;
            }

            reactionsModalState = {
                messageId: messageId,
                summary: summary,
                users: users,
                total: total,
                filter: 'all'
            };

            renderReactionsModal();
            reactionsModalEl.classList.add('show');
            reactionsModalEl.setAttribute('aria-hidden', 'false');
        }

        function getMessagePreview(msg) {
            if (!msg) { return ''; }
            var txt = (msg.message || '').trim();
            if (!txt && msg.media_type === 'image') { return '[Image]'; }
            if (!txt && msg.media_type === 'video') { return '[Video]'; }
            if (msg.media_path && txt === msg.media_path.split('/').pop()) {
                return msg.media_type === 'video' ? '[Video]' : '[Image]';
            }
            return txt;
        }

        function clearReplyTarget() {
            replyTarget = null;
            if (replyToInputEl) { replyToInputEl.value = '0'; }
            if (replyLabelEl) { replyLabelEl.textContent = ''; }
            if (replyPreviewEl) { replyPreviewEl.classList.remove('has-reply'); }
        }

        function setReplyTarget(msg) {
            if (!msg) { clearReplyTarget(); return; }
            replyTarget = msg;
            if (replyToInputEl) { replyToInputEl.value = String(msg.message_id || 0); }
            if (replyLabelEl) {
                var preview = getMessagePreview(msg);
                replyLabelEl.textContent = 'Replying to ' + (msg.is_own ? 'yourself' : 'them') + ': ' + (preview || '[Message]');
            }
            if (replyPreviewEl) { replyPreviewEl.classList.add('has-reply'); }
        }

        function openMessageActionMenu(messageId, triggerEl) {
            if (!messageActionMenuEl || !triggerEl) { return; }
            var msg = messageCache[String(messageId)];
            if (!msg) { return; }

            activeMessageActionId = messageId;
            activeMessageActionTriggerEl = triggerEl;
            messageActionMenuEl.dataset.messageId = String(messageId);

            var emojiRow = messageActionMenuEl.querySelector('.msg-action-emoji-row');
            var replyBtn = messageActionMenuEl.querySelector('[data-msg-action="reply"]');
            var unsendBtn = messageActionMenuEl.querySelector('[data-msg-action="unsend"]');
            var removeBtn = messageActionMenuEl.querySelector('[data-msg-action="remove"]');
            var reportBtn = messageActionMenuEl.querySelector('[data-msg-action="report"]');
            var pinBtn = messageActionMenuEl.querySelector('[data-msg-action="pin"]');
            var isPinned = !!msg.is_pinned;
            var isUnsent = !!msg.is_unsent;

            if (pinBtn) {
                pinBtn.textContent = isPinned ? 'Unpin message' : 'Pin message';
                pinBtn.classList.toggle('is-hidden', isUnsent);
            }
            if (replyBtn) {
                replyBtn.classList.toggle('is-hidden', isUnsent);
            }
            if (unsendBtn) {
                unsendBtn.classList.toggle('is-hidden', !msg.is_own || isUnsent);
            }
            if (removeBtn) {
                removeBtn.classList.toggle('is-hidden', !(msg.is_own && isUnsent));
            }
            if (reportBtn) {
                reportBtn.classList.toggle('is-hidden', !!msg.is_own);
            }
            if (emojiRow) {
                emojiRow.classList.toggle('is-hidden', isUnsent);
            }

            messageActionMenuEl.classList.add('show');
            messageActionMenuEl.setAttribute('aria-hidden', 'false');

            var rect = triggerEl.getBoundingClientRect();
            var menuRect = messageActionMenuEl.getBoundingClientRect();
            var menuW = menuRect.width || 220;
            var menuH = menuRect.height || 180;
            var left = rect.left + (rect.width / 2) - (menuW / 2);
            var top = rect.top - menuH - 12;
            var placement = 'top';

            if (left < 8) {
                left = 8;
            }
            if (left + menuW > window.innerWidth - 8) {
                left = window.innerWidth - menuW - 8;
            }
            if (top < 8) {
                top = rect.bottom + 12;
                placement = 'bottom';
            }

            var arrowLeft = rect.left + (rect.width / 2) - left;
            if (arrowLeft < 18) { arrowLeft = 18; }
            if (arrowLeft > menuW - 18) { arrowLeft = menuW - 18; }

            messageActionMenuEl.dataset.placement = placement;
            messageActionMenuEl.style.setProperty('--msg-menu-arrow-left', arrowLeft + 'px');
            messageActionMenuEl.style.left = left + 'px';
            messageActionMenuEl.style.top = top + 'px';
        }

        function reactToMessage(messageId, reactionEmoji) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'react-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('reaction_emoji', reactionEmoji || '');
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;

                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                    return;
                }

                if (payload && payload.error) {
                    showAlert('error', payload.error);
                    return;
                }

                if (result.ok) {
                    fetchState(true);
                    return;
                }

                showAlert('error', 'Failed to react to message.');
            }).catch(function () {
                fetchState(true);
            });
        }

        function deleteConversationByUserId(userId) {
            if (!userId) { return; }
            var fd = new FormData();
            fd.set('action', 'delete-conversation');
            fd.set('user_id', String(userId));
            fd.set('ajax', '1');
            postChatAction(fd, userId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: false, forceScroll: true });
                    clearMediaPreview();
                    showAlert('success', payload.success || 'Conversation deleted.');
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to delete conversation.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to delete conversation.');
            });
        }

        function unsendMessageById(messageId) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'unsend-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to unsend message.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to unsend message.');
            });
        }

        function removeMessageById(messageId) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'remove-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to remove message.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to remove message.');
            });
        }

        function closeReportModal() {
            if (!reportModalEl) { return; }
            reportModalEl.classList.remove('show');
            reportModalEl.setAttribute('aria-hidden', 'true');
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            // Ensure all form elements are properly reset
            if (reportReasonEl) {
                reportReasonEl.blur();
            }
            if (reportNoteEl) {
                reportNoteEl.blur();
            }
            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }
            pendingReportFn = null;
        }

        function buildReportReason() {
            var baseReason = reportReasonEl ? String(reportReasonEl.value || '').trim() : '';
            var extraNote = reportNoteEl ? String(reportNoteEl.value || '').trim() : '';
            if (!baseReason) {
                baseReason = 'Inappropriate message';
            }
            var reason = extraNote ? (baseReason + ': ' + extraNote) : baseReason;
            return reason.slice(0, 255);
        }

        function openReportModal(onConfirm) {
            if (!reportModalEl) {
                if (typeof onConfirm === 'function') {
                    onConfirm('Inappropriate message');
                }
                return;
            }
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            pendingReportFn = typeof onConfirm === 'function' ? onConfirm : null;
            if (reportReasonEl) {
                reportReasonEl.value = 'Harassment or abusive language';
            }
            if (reportNoteEl) {
                reportNoteEl.value = '';
            }
            reportModalEl.classList.add('show');
            reportModalEl.setAttribute('aria-hidden', 'false');
            if (reportReasonEl) {
                reportReasonEl.focus();
            }
        }

        function reportMessageById(messageId, reason) {
            if (!selectedUserId || !messageId) { return; }
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            var fd = new FormData();
            fd.set('action', 'report-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('reason', String(reason || 'Inappropriate message'));
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    showAlert('success', payload.success || 'Message reported.');
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to report message.');
                }
                window.setTimeout(function () {
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    scrubActionTooltips(document);
                }, 0);
            }).catch(function () {
                showAlert('error', 'Failed to report message.');
                window.setTimeout(function () {
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    scrubActionTooltips(document);
                }, 0);
            });
        }

        function reportUserFromContactModal() {
            if (!(contactModalUserId > 0)) {
                showAlert('error', 'Select a contact first.');
                return;
            }

            var latestMessageId = latestReportableMessageIdForUser(contactModalUserId);
            if (!(latestMessageId > 0)) {
                showAlert('error', 'No recent message from this user to report.');
                return;
            }

            closeContactModal();
            openReportModal(function (selectedReason) {
                openConfirmModal(
                    'Report this user?',
                    'This will report their most recent message for moderation review. Reason: ' + selectedReason,
                    function () { reportMessageById(latestMessageId, selectedReason); },
                    'Report'
                );
            });
        }

        function closeDeleteConfirm() {
            if (!deleteConfirmEl) { return; }
            deleteConfirmEl.classList.remove('show');
            deleteConfirmEl.setAttribute('aria-hidden', 'true');
            pendingDeleteUserId = 0;
        }

        function openDeleteConfirm(userId) {
            if (!deleteConfirmEl || !userId) { return; }
            pendingDeleteUserId = userId;
            deleteConfirmEl.classList.add('show');
            deleteConfirmEl.setAttribute('aria-hidden', 'false');
            if (deleteConfirmOkEl) { deleteConfirmOkEl.focus(); }
        }

        function closeHeaderMenus() {
            if (!headerEl) { return; }
            var menus = headerEl.querySelectorAll('.btchat-menu.show');
            menus.forEach(function (menuEl) {
                menuEl.classList.remove('show');
            });
        }

        function bindHeaderMenu() {
            if (!headerEl) { return; }
            var backBtn = headerEl.querySelector('.btchat-back-btn');
            var toggle = headerEl.querySelector('.btchat-menu-toggle');
            var menu = headerEl.querySelector('.btchat-menu');

            if (backBtn) {
                backBtn.onclick = function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    setMobileConversationOpen(false);
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    closeEmojiPicker();
                };
            }

            if (!toggle || !menu) { return; }

            toggle.onclick = function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (Date.now() < suppressHeaderToggleUntil) {
                    return;
                }
                menu.classList.toggle('show');
                if (menu.classList.contains('show')) {
                    var firstItem = menu.querySelector('.btchat-menu-item');
                    if (firstItem) { firstItem.focus(); }
                }
            };

            toggle.onkeydown = function (event) {
                if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    if (Date.now() < suppressHeaderToggleUntil) {
                        return;
                    }
                    menu.classList.add('show');
                    var firstItem = menu.querySelector('.btchat-menu-item');
                    if (firstItem) { firstItem.focus(); }
                } else if (event.key === 'Escape') {
                    menu.classList.remove('show');
                }
            };

            menu.onkeydown = function (event) {
                var items = Array.prototype.slice.call(menu.querySelectorAll('.btchat-menu-item'));
                if (!items.length) { return; }
                var activeIndex = items.indexOf(document.activeElement);
                if (event.key === 'Escape') {
                    event.preventDefault();
                    menu.classList.remove('show');
                    toggle.focus();
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    items[(activeIndex + 1 + items.length) % items.length].focus();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    items[(activeIndex - 1 + items.length) % items.length].focus();
                }
            };

            menu.onclick = function (event) {
                var btn = event.target.closest('.btchat-menu-item');
                if (!btn) { return; }
                var action = btn.getAttribute('data-action') || '';
                menu.classList.remove('show');
                if (action === 'refresh-chat') {
                    fetchState(true);
                } else if (action === 'scroll-bottom') {
                    scrollThreadToBottom(true);
                    updateScrollBtn();
                } else if (action === 'view-contact') {
                    var c = selectedContactRef;
                    if (!c) { return; }
                    openContactModal(c);
                } else if (action === 'mute-conversation') {
                    if (!selectedContactRef) { return; }
                    var currentlyMuted = isConversationMuted(selectedContactRef.id);
                    setConversationMuted(selectedContactRef.id, !currentlyMuted);
                    renderHeader(selectedContactRef);
                    clearComposeWarning();
                } else if (action === 'delete-conversation') {
                    if (!selectedUserId) { return; }
                    openConfirmModal(
                        'Delete conversation?',
                        'This will remove all messages in this conversation for your account.',
                        function () { deleteConversationByUserId(selectedUserId); },
                        'Delete'
                    );
                }
            };
        }

        function isAtBottom() {
            if (!threadEl) { return true; }
            return (threadEl.scrollHeight - threadEl.scrollTop - threadEl.clientHeight) < 120;
        }

        function scrollThreadToBottom(force) {
            if (!threadEl) { return; }
            if (force || isAtBottom()) {
                threadEl.scrollTop = threadEl.scrollHeight;
            }
        }

        var scrollBtnEl = document.getElementById('chat-scroll-btn');

        function updateScrollBtn() {
            if (!scrollBtnEl) { return; }
            if (isAtBottom()) {
                scrollBtnEl.classList.remove('visible');
            } else {
                scrollBtnEl.classList.add('visible');
            }
        }

        function updateSendBtn() {
            if (!sendBtnEl || !inputEl) { return; }
            var hasMedia = mediaInputEl && mediaInputEl.files && mediaInputEl.files.length > 0;
            var hasText = inputEl.value.replace(/[\s\n\r\t]+/g, '') !== '';
            if (hasText || hasMedia) {
                sendBtnEl.classList.remove('is-like');
                sendBtnEl.innerHTML = '<i class="feather-send"></i>';
                sendBtnEl.dataset.mode = 'send';
            } else {
                sendBtnEl.classList.add('is-like');
                sendBtnEl.innerHTML = '&#128077;';
                sendBtnEl.dataset.mode = 'like';
            }
        }

        function dateSepLabel(dateKey) {
            if (!dateKey) { return ''; }
            var today = new Date();
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var todayKey = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());
            var yest = new Date(today);
            yest.setDate(today.getDate() - 1);
            var yestKey = yest.getFullYear() + '-' + pad(yest.getMonth() + 1) + '-' + pad(yest.getDate());
            if (dateKey === todayKey) { return 'Today'; }
            if (dateKey === yestKey) { return 'Yesterday'; }
            var parts = dateKey.split('-');
            var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
        }

        function renderMessages(messages, selectedContact, forceScroll) {
            if (!threadEl) { return; }
            var items = Array.isArray(messages) ? messages : [];
            if (!items.length) {
                threadEl.innerHTML = '<div class="btchat-empty"><div><h6 class="mb-2 text-white">No messages yet</h6><div class="text-white-50">Start the conversation with ' + escapeHtml(selectedContact ? selectedContact.name : 'this user') + '.</div></div></div>';
                return;
            }

            var wasBottom = forceScroll || isAtBottom();
            var n = items.length;
            messageCache = {};

            // Compute grouping: consecutive same-sender in same date_key
            var groups = new Array(n);
            for (var gi = 0; gi < n; gi++) {
                var prevSame = gi > 0 && items[gi - 1].sender_id === items[gi].sender_id && items[gi - 1].date_key === items[gi].date_key;
                var nextSame = gi < n - 1 && items[gi + 1].sender_id === items[gi].sender_id && items[gi].date_key === items[gi + 1].date_key;
                if (prevSame && nextSame) { groups[gi] = 'msg-group-middle'; }
                else if (prevSame) { groups[gi] = 'msg-group-last'; }
                else if (nextSame) { groups[gi] = 'msg-group-first'; }
                else { groups[gi] = 'msg-group-only'; }
            }

            // Find last own message index for Sent indicator
            var lastOwnIdx = -1;
            for (var li = n - 1; li >= 0; li--) {
                if (items[li].is_own) { lastOwnIdx = li; break; }
            }

            var html = '';
            var lastDateKey = '';
            var onErrAvatar = "this.style.display='none';var s=this.nextElementSibling;if(s)s.style.display='inline-flex';";

            for (var mi = 0; mi < n; mi++) {
                var msg = items[mi];
                var grp = groups[mi];
                var dk = msg.date_key || '';
                var isUnsent = !!msg.is_unsent;
                messageCache[String(msg.message_id)] = msg;

                // Date separator between days
                if (dk && dk !== lastDateKey) {
                    html += '<div class="msg-date-sep">' + escapeHtml(dateSepLabel(dk)) + '</div>';
                    lastDateKey = dk;
                }

                // Media
                var mediaHtml = '';
                if (!isUnsent && msg.media_type === 'image' && msg.media_path) {
                    mediaHtml = '<img src="' + escapeHtml(msg.media_path) + '" class="msg-media" alt="image" data-media-viewer="image">';
                } else if (!isUnsent && msg.media_type === 'video' && msg.media_path) {
                    mediaHtml = '<video src="' + escapeHtml(msg.media_path) + '" class="msg-media-video" controls preload="metadata" data-media-viewer="video"></video>';
                }

                var displayMsg = msg.message || '';
                if (msg.media_path && displayMsg === msg.media_path.split('/').pop()) {
                    displayMsg = '';
                }

                // Meta (time): only on last/only of a group
                var metaOutsideHtml = '';
                if (grp === 'msg-group-last' || grp === 'msg-group-only') {
                    var metaTextRaw = msg.time_clock || msg.time_exact || msg.time_label || '';
                    var metaText = escapeHtml(metaTextRaw);
                    var metaTitle = escapeHtml(msg.time_full || metaTextRaw);
                    metaOutsideHtml = '<div class="msg-meta-outside ' + (msg.is_own ? 'own' : 'other') + '" title="' + metaTitle + '">' + metaText + '</div>';
                }

                // Avatar for other's messages (left side)
                var avatarHtml = '';
                if (!msg.is_own) {
                    if (grp === 'msg-group-last' || grp === 'msg-group-only') {
                        var avSrc = escapeHtml(selectedContact ? selectedContact.avatar_path : '');
                        var avInit = escapeHtml(selectedContact ? selectedContact.initials : 'BT');
                        avatarHtml = '<span style="flex-shrink:0;align-self:flex-end;display:inline-flex">' +
                            '<img src="' + avSrc + '" class="msg-thread-avatar" title="' + escapeHtml(selectedContact ? selectedContact.name : '') + '" onerror="' + onErrAvatar + '">' +
                            '<span class="msg-thread-avatar-text" style="display:none">' + avInit + '</span>' +
                            '</span>';
                    } else {
                        avatarHtml = '<span class="msg-thread-avatar-placeholder"></span>';
                    }
                }

                // Bubble title = full time for all messages (accessible via hover)
                var bubbleTitle = msg.time_full ? ' title="' + escapeHtml(msg.time_full) + '"' : '';
                var pinnedClass = msg.is_pinned ? ' is-pinned' : '';
                var reactionSummaryRaw = Array.isArray(msg.reaction_summary) ? msg.reaction_summary : [];
                var reactionSummary = reactionSummaryRaw
                    .map(function (item) {
                        return {
                            emoji: String(item && item.emoji ? item.emoji : ''),
                            count: parseInt(item && item.count ? item.count : '0', 10)
                        };
                    })
                    .filter(function (item) {
                        return item.emoji !== '' && item.count > 0;
                    });
                var reactionCount = parseInt(msg.reaction_count || '0', 10);
                if (!(reactionCount > 0)) {
                    reactionCount = reactionSummary.reduce(function (sum, item) {
                        return sum + (item.count || 0);
                    }, 0);
                }
                if (!reactionSummary.length && msg.reaction_emoji) {
                    reactionSummary = [{ emoji: String(msg.reaction_emoji), count: reactionCount > 0 ? reactionCount : 1 }];
                }
                var hasReaction = !isUnsent && reactionCount > 0 && reactionSummary.length > 0;
                var reactionIconsHtml = reactionSummary.slice(0, 2).map(function (item) {
                    return '<span class="msg-reaction-icon">' + escapeHtml(item.emoji) + '</span>';
                }).join('');

                html += '<div class="msg-block ' + (msg.is_own ? 'own' : 'other') + '">';
                html += '<div class="msg-row' + (msg.is_own ? ' own' : '') + ' ' + grp + (hasReaction ? ' has-reaction' : '') + '">';
                if (!msg.is_own) { html += avatarHtml; }
                if (msg.is_own) {
                    html += '<button type="button" class="msg-hover-menu-btn" data-message-id="' + msg.message_id + '">&#8226;&#8226;&#8226;</button>';
                }
                html += '<div class="msg-bubble ' + grp + (msg.media_path ? ' has-media' : '') + (isUnsent ? ' is-unsent' : '') + pinnedClass + '"' + bubbleTitle + '>';
                if (msg.reply_preview && !isUnsent) {
                    html += '<div class="msg-reply-quote"><strong>' + escapeHtml(msg.reply_author || '') + '</strong><span class="msg-reply-quote-text">' + escapeHtml(msg.reply_preview) + '</span></div>';
                }
                html += mediaHtml;
                if (displayMsg) { html += nl2br(displayMsg); }
                if (hasReaction) {
                    html += '<button type="button" class="msg-reaction-badge" data-reaction-mid="' + msg.message_id + '" aria-label="View reactions">' +
                        '<span class="msg-reaction-icons">' + reactionIconsHtml + '</span>' +
                        '<span class="msg-reaction-count">' + reactionCount + '</span>' +
                    '</button>';
                }
                html += '</div>';
                if (!msg.is_own && !isUnsent) {
                    html += '<button type="button" class="msg-hover-menu-btn" data-message-id="' + msg.message_id + '">&#8226;&#8226;&#8226;</button>';
                }
                html += '</div>';
                html += metaOutsideHtml;
                html += '</div>';

                // Sent indicator under the last own message
                if (mi === lastOwnIdx) {
                    var statusLabel = deliveryStatusLabel(msg);
                    html += '<div class="msg-seen">' + escapeHtml(statusLabel || 'Sent') + '</div>';
                }
            }

            threadEl.innerHTML = html;

            if (wasBottom) {
                threadEl.scrollTop = threadEl.scrollHeight;
            }
            updateScrollBtn();
            scrubActionTooltips(threadEl);
        }

        function applyState(payload, options) {
            var state = payload || {};
            var previousSelectedUserId = selectedUserId;
            if (typeof state.selectedUserId === 'number' && state.selectedUserId > 0) {
                selectedUserId = state.selectedUserId;
                app.setAttribute('data-selected-user-id', String(selectedUserId));
            }
            if (selectedUserId !== previousSelectedUserId) {
                clearReplyTarget();
            }
            var contacts = state.contacts || [];
            lastContactsData = contacts;
            var messages = state.messages || [];
            var contactsSignature = buildContactsSignature(contacts);
            var messagesSignature = buildMessagesSignature(messages);
            var forceScroll = !!(options && options.forceScroll);
            var contactChanged = selectedUserId !== previousSelectedUserId;

            if (contactsSignature !== lastContactsSignature || contactChanged) {
                renderContacts(contacts);
                lastContactsSignature = contactsSignature;
            }

            if (state.selectedContact) {
                selectedContactRef = state.selectedContact;
                var headerSignature = buildHeaderSignature(state.selectedContact);
                if (contactChanged || headerSignature !== lastHeaderSignature) {
                    renderHeader(state.selectedContact);
                    lastHeaderSignature = headerSignature;
                }
                if (contactChanged && selectedUserId > 0) {
                    setMobileConversationOpen(true);
                }
                if (forceScroll || contactChanged || messagesSignature !== lastMessagesSignature || selectedUserId !== lastRenderedUserId) {
                    renderMessages(messages, state.selectedContact, forceScroll);
                    lastMessagesSignature = messagesSignature;
                    lastRenderedUserId = selectedUserId;
                }
            } else {
                selectedContactRef = null;
                lastHeaderSignature = '';
                setMobileConversationOpen(false);
                clearReplyTarget();
                lastMessagesSignature = '';
                lastRenderedUserId = 0;
            }
            if (formEl && selectedUserId > 0) {
                formEl.setAttribute('action', chatBaseUrl + '?user_id=' + selectedUserId);
                var userField = formEl.querySelector('input[name="user_id"]');
                if (userField) {
                    userField.value = String(selectedUserId);
                }
            }
            if (!options || !options.keepInput) {
                if (inputEl) {
                    inputEl.value = '';
                }
            }
            setActiveContactVisual(selectedUserId);
            setThreadLoading(false);
        }

        function fetchState(showErrors, options) {
            if (!selectedUserId) {
                return Promise.resolve(null);
            }
            var requestToken = ++stateRequestToken;
            if (fetchAbortController && typeof fetchAbortController.abort === 'function') {
                fetchAbortController.abort();
            }
            fetchAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var requestUrl = chatBaseUrl + '?ajax=1&user_id=' + encodeURIComponent(selectedUserId);
            return fetch(requestUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: fetchAbortController ? fetchAbortController.signal : undefined
            }).then(parseJsonResponse).then(function (result) {
                var payload = result.payload;
                if (requestToken !== stateRequestToken) {
                    return null;
                }
                if (payload && payload.ok) {
                    applyState(payload, {
                        keepInput: true,
                        forceScroll: !!(options && options.forceScroll)
                    });
                } else if (showErrors) {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to refresh chat.');
                }
                return payload;
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return null;
                }
                if (showErrors) {
                    showAlert('error', 'Failed to refresh chat.');
                }
                setThreadLoading(false);
                return null;
            });
        }

        if (listEl) {
            listEl.addEventListener('click', function (event) {
                var link = event.target.closest('a[data-user-id]');
                if (!link) {
                    return;
                }
                event.preventDefault();
                selectedUserId = parseInt(link.getAttribute('data-user-id') || '0', 10) || 0;
                if (!selectedUserId) {
                    window.location.href = link.getAttribute('href') || chatBaseUrl;
                    return;
                }
                var wasMobileConversationOpen = isMobileLayout() && app.classList.contains('btchat-mobile-convo-open');
                setActiveContactVisual(selectedUserId);
                primeHeaderFromListItem(link);
                setThreadLoading(true);
                setMobileConversationOpen(true);
                if (isMobileLayout()) {
                    if (wasMobileConversationOpen) {
                        setMobileHistoryState('conversation', selectedUserId);
                    } else {
                        pushMobileConversationState(selectedUserId);
                    }
                } else {
                    history.replaceState(null, '', chatBaseUrl + '?user_id=' + selectedUserId);
                }
                fetchState(true, { forceScroll: true }).then(function (payload) {
                    if (!payload || !payload.ok) {
                        setThreadLoading(false);
                        window.location.href = link.getAttribute('href') || (chatBaseUrl + '?user_id=' + selectedUserId);
                    }
                });
            });
        }

        // â”€â”€ Media attach button & preview â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        var mediaInputEl = document.getElementById('chat-media-input');
        var attachBtnEl = document.getElementById('chat-attach-btn');
        var previewEl = document.getElementById('chat-media-preview');
        var previewThumbEl = document.getElementById('chat-preview-thumb');
        var previewNameEl = document.getElementById('chat-preview-name');
        var previewRemoveEl = document.getElementById('chat-preview-remove');
        var emojiBtnEl = document.getElementById('chat-emoji-btn');
        var emojiPickerEl = document.getElementById('chat-emoji-picker');
        var emojiGridEl = document.getElementById('chat-emoji-grid');
        var emojiSearchEl = document.getElementById('chat-emoji-search');
        var emojiTabsEl = document.getElementById('chat-emoji-tabs');
        var emojiEmptyEl = document.getElementById('chat-emoji-empty');
        var activeEmojiCategory = 'smileys';
        var blockedChatEmojis = ['🖕', '🍆', '🍑', '💦', '👅'];

        var emojiCatalog = {
            smileys: ['😀','😁','😂','🤣','😊','🙂','😉','😍','😘','😎','🤔','🙄','😢','😭','😡','🤬'],
            people: ['👍','👎','👏','🙌','🙏','👌','🤝','💪','👀','🫶','❤️','💔','🔥','✨','🎉','💯'],
            animals: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🦄'],
            food: ['🍎','🍌','🍇','🍉','🍓','🍍','🥑','🍅','🍔','🍕','🍟','🌮','🍣','🍜','🍩','🍪'],
            travel: ['🚗','🚌','🚕','🚓','🚑','🚒','🚲','✈️','🚆','🚀','🛳️','🏝️','🏙️','🗺️','🌋','🌉'],
            objects: ['⌚','📱','💻','⌨️','🖥️','📷','🎥','📞','💡','🔦','🔋','🔌','🧰','⚙️','💰','💎'],
            symbols: ['❤️','💔','❗','❓','✅','☑️','⚠️','🚫','🔔','🔕','♻️','▶️','⏸️','⏹️','⏺️','🔁'],
            flags: ['🏁','🚩','🏳️','🏴','🏳️‍🌈','🏳️‍⚧️','🇵🇭','🇺🇸','🇬🇧','🇯🇵','🇰🇷','🇨🇦','🇦🇺','🇫🇷','🇮🇹','🇪🇸']
        };

        function emojiMatchesQuery(emoji, query) {
            if (!query) { return true; }
            // Simple fallback matching on known category and direct emoji glyph.
            return emoji.indexOf(query) !== -1;
        }

        function getChatModerationError(message) {
            var text = String(message || '').trim();
            if (!text) { return ''; }

            for (var i = 0; i < blockedChatEmojis.length; i++) {
                if (text.indexOf(blockedChatEmojis[i]) !== -1) {
                    return 'Message blocked due to unsupported or offensive emoji.';
                }
            }

            var symbolCompact = text.replace(/\s+/g, '');
            var symbolCompactLower = symbolCompact.toLowerCase();
            var blockedSymbolTokens = ['./.', '/./', '.|.', '<==3', '<===3', '<====3', '8==d', '8===d', '8====d', 'b==d', 'b===d', 'b====d'];
            for (var si = 0; si < blockedSymbolTokens.length; si++) {
                if (symbolCompactLower.indexOf(blockedSymbolTokens[si]) !== -1) {
                    return 'Message blocked due to disallowed symbol patterns.';
                }
            }
            if (/(?:<|8|b|c)[=\-~_]{2,}(?:3|d)/.test(symbolCompactLower)) {
                return 'Message blocked due to disallowed symbol patterns.';
            }

            var normalized = text
                .toLowerCase()
                .replace(/[013457@$]/g, function (char) {
                    return ({ '0': 'o', '1': 'i', '3': 'e', '4': 'a', '5': 's', '7': 't', '@': 'a', '$': 's' })[char] || char;
                })
                .replace(/[^a-z0-9\s]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            var compact = normalized.replace(/\s+/g, '');
            var blockedTerms = [
                'fuck', 'fucking', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'pussy',
                'nude', 'nudes', 'porn', 'sext', 'blowjob', 'handjob', 'cum', 'kys',
                'fck', 'fvck', 'phuck', 'btch', 'biatch',
                'cunt', 'whore', 'slut', 'rape', 'rapist', 'pedo', 'pedophile',
                'jizz', 'boner', 'wank', 'wanker', 'fap', 'hentai', 'horny',
                'orgasm', 'masturbate', 'masturbation', 'threesome', 'gangbang', 'creampie',
                'anal', 'erection', 'ejaculate', 'ejaculation', 'xxx',
                'putangina', 'potangina', 'puta', 'punyeta', 'gago', 'gaga', 'tangina',
                'leche', 'buwisit', 'kupal', 'tarantado', 'pakshet', 'pakyu', 'putcha',
                'kantot', 'iyot', 'jakol', 'tite', 'pekpek', 'ulol', 'bobo',
                'ptngina', 'tngina', 'ulul',
                'tanga', 'inutil', 'ogag', 'engot', 'gagu',
                'putaragis', 'putaena', 'bwiset', 'bwisit', 'bwakanangina', 'bwakananginamo',
                'hindot', 'libog', 'salsal', 'bayag', 'burat', 'pokpok',
                'biot', 'bayot', 'bading',
                'gunggong', 'kolokoy', 'hinayupak', 'lintik', 'demonyo',
                'punyemas', 'burikat', 'pokpokin',
                'yawa', 'yawaa', 'buang', 'otin', 'bilat', 'pisti', 'piste', 'atay', 'amaw', 'yati',
                'cono', 'joder', 'cabron', 'mierda', 'pendejo', 'verga', 'chinga', 'culero',
                'sibal', 'ssibal', 'gaeseki', 'jiral', 'byeongsin',
                'kuso', 'kutabare', 'chinko', 'manko'
            ];
            var blockedPhrases = [
                'kill yourself', 'kill ur self', 'kill your self',
                'putang ina', 'putang ina mo', 'tang ina', 'tangina mo',
                'anak ng puta', 'anak ka ng puta', 'bwakanang ina', 'bwakanang ina mo',
                'gago ka', 'ulol ka', 'biot ka', 'bayot ka',
                'kupal ka', 'tarantado ka',
                'puta ka', 'gago mo'
            ];
            var blockedNativeTerms = [
                '\uC2DC\uBC1C', '\uC528\uBC1C', '\uAC1C\uC0C8\uB07C', '\uBCD1\uC2E0', '\uC9C0\uB784', '\uCC3D\uB140', '\uBCF4\uC9C0', '\uC790\uC9C0',
                '\u304F\u305D', '\u304F\u305F\u3070\u308C', '\u3061\u3093\u3053', '\u307E\u3093\u3053', '\u6B7B\u306D', '\u3046\u3093\u3053',
                '\u64CD\u4F60\u5988', '\u4F60\u5988\u7684', '\u4ED6\u5988\u7684', '\u53BB\u6B7B', '\u50BB\u903C', '\u8349\u6CE5\u9A6C', '\u8085\u4F60',
                'co\u00F1o', 'cabr\u00F3n'
            ];

            for (var pi = 0; pi < blockedPhrases.length; pi++) {
                var phrasePattern = new RegExp('\\b' + blockedPhrases[pi].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
                if (phrasePattern.test(normalized)) {
                    return 'Message blocked due to inappropriate language. Please edit and try again.';
                }
            }

            var textLower = text.toLowerCase();
            for (var ni = 0; ni < blockedNativeTerms.length; ni++) {
                if (textLower.indexOf(blockedNativeTerms[ni].toLowerCase()) !== -1) {
                    return 'Message blocked due to inappropriate language. Please edit and try again.';
                }
            }

            for (var ti = 0; ti < blockedTerms.length; ti++) {
                var term = blockedTerms[ti];
                var pattern = new RegExp('\\b' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
                if (pattern.test(normalized) || compact.indexOf(term) !== -1) {
                    return 'Message blocked due to inappropriate language. Please edit and try again.';
                }
            }

            return '';
        }

        function renderEmojiPicker() {
            if (!emojiGridEl) { return; }
            var query = emojiSearchEl ? emojiSearchEl.value.trim() : '';
            var source = emojiCatalog[activeEmojiCategory] || [];
            var filtered = source.filter(function (e) {
                return blockedChatEmojis.indexOf(e) === -1 && emojiMatchesQuery(e, query);
            });

            emojiGridEl.innerHTML = filtered.map(function (emoji) {
                return '<button type="button" class="chat-emoji-item" data-chat-emoji="' + emoji + '">' + emoji + '</button>';
            }).join('');

            if (emojiEmptyEl) {
                emojiEmptyEl.style.display = filtered.length ? 'none' : 'block';
            }
        }

        function closeEmojiPicker() {
            if (emojiPickerEl) {
                emojiPickerEl.classList.remove('show');
            }
        }

        function insertEmojiAtCursor(emoji) {
            if (!inputEl || !emoji) { return; }
            var start = inputEl.selectionStart || 0;
            var end = inputEl.selectionEnd || 0;
            var value = inputEl.value || '';
            inputEl.value = value.slice(0, start) + emoji + value.slice(end);
            var caret = start + emoji.length;
            inputEl.focus();
            inputEl.setSelectionRange(caret, caret);
            autoGrowInput();
            updateSendBtn();
        }

        function clearMediaPreview() {
            if (mediaInputEl) { mediaInputEl.value = ''; }
            if (previewEl) { previewEl.classList.remove('has-file'); }
            if (previewThumbEl) { previewThumbEl.style.display = 'none'; previewThumbEl.src = ''; }
            if (previewNameEl) { previewNameEl.textContent = ''; }
        }

        if (attachBtnEl && mediaInputEl) {
            attachBtnEl.addEventListener('click', function () {
                mediaInputEl.click();
            });
            mediaInputEl.addEventListener('change', function () {
                var file = mediaInputEl.files && mediaInputEl.files[0];
                if (!file) { clearMediaPreview(); updateSendBtn(); return; }
                if (file.type.indexOf('image/') !== 0) {
                    showAlert('error', 'Only image files are allowed.');
                    clearMediaPreview();
                    updateSendBtn();
                    return;
                }
                if (file.size > (10 * 1024 * 1024)) {
                    showAlert('error', 'Image is too large (max 10 MB).');
                    clearMediaPreview();
                    updateSendBtn();
                    return;
                }
                if (previewEl) { previewEl.classList.add('has-file'); }
                if (previewNameEl) { previewNameEl.textContent = file.name; }
                if (previewThumbEl) {
                    if (file.type.indexOf('image') === 0) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            previewThumbEl.src = e.target.result;
                            previewThumbEl.style.display = '';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewThumbEl.style.display = 'none';
                    }
                }
                updateSendBtn();
            });
        }

        if (previewRemoveEl) {
            previewRemoveEl.addEventListener('click', function () {
                clearMediaPreview();
                updateSendBtn();
            });
        }

        if (emojiBtnEl && emojiPickerEl) {
            emojiBtnEl.addEventListener('click', function (event) {
                event.preventDefault();
                emojiPickerEl.classList.toggle('show');
                if (emojiPickerEl.classList.contains('show')) {
                    renderEmojiPicker();
                    if (emojiSearchEl) { emojiSearchEl.focus(); }
                }
            });

            if (emojiGridEl) {
                emojiGridEl.addEventListener('click', function (event) {
                    var btn = event.target.closest('[data-chat-emoji]');
                    if (!btn) { return; }
                    insertEmojiAtCursor(btn.getAttribute('data-chat-emoji') || '');
                    closeEmojiPicker();
                });
            }

            if (emojiTabsEl) {
                emojiTabsEl.addEventListener('click', function (event) {
                    var tab = event.target.closest('[data-emoji-cat]');
                    if (!tab) { return; }
                    activeEmojiCategory = tab.getAttribute('data-emoji-cat') || 'smileys';
                    var allTabs = emojiTabsEl.querySelectorAll('[data-emoji-cat]');
                    allTabs.forEach(function (el) {
                        el.classList.toggle('active', el === tab);
                    });
                    renderEmojiPicker();
                });
            }

            if (emojiSearchEl) {
                emojiSearchEl.addEventListener('input', renderEmojiPicker);
            }
        }

        if (scrollBtnEl && threadEl) {
            scrollBtnEl.addEventListener('click', function () {
                scrollThreadToBottom(true);
                updateScrollBtn();
            });
            threadEl.addEventListener('scroll', function () {
                updateScrollBtn();
                closeMessageActionMenu();
            });
        }

        if (threadEl) {
            threadEl.addEventListener('click', function (event) {
                var mediaEl = event.target.closest('[data-media-viewer]');
                if (mediaEl) {
                    event.preventDefault();
                    event.stopPropagation();
                    openMediaModal(
                        mediaEl.getAttribute('data-media-viewer') || '',
                        mediaEl.getAttribute('src') || '',
                        mediaEl.getAttribute('data-media-viewer') === 'video' ? 'Video' : 'Image'
                    );
                    return;
                }

                var reactionBtn = event.target.closest('.msg-reaction-badge[data-reaction-mid]');
                if (reactionBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    var reactionMid = parseInt(reactionBtn.getAttribute('data-reaction-mid') || '0', 10);
                    openReactionsModal(reactionMid);
                    return;
                }

                var actionBtn = event.target.closest('.msg-hover-menu-btn');
                if (actionBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    var mid = parseInt(actionBtn.getAttribute('data-message-id') || '0', 10);
                    openMessageActionMenu(mid, actionBtn);
                }
            });
        }

        if (messageActionMenuEl) {
            messageActionMenuEl.addEventListener('click', function (event) {
                var emojiBtn = event.target.closest('.msg-emoji-btn');
                if (emojiBtn) {
                    var emoji = emojiBtn.getAttribute('data-emoji') || '';
                    if (!emoji || !activeMessageActionId) { return; }
                    var reactionMessageId = activeMessageActionId;
                    closeMessageActionMenu();
                    reactToMessage(reactionMessageId, emoji);
                    return;
                }

                var action = event.target.closest('.msg-action-item');
                if (!action || !activeMessageActionId) { return; }
                var type = action.getAttribute('data-msg-action') || '';
                var mid = activeMessageActionId;
                var msg = messageCache[String(mid)];
                closeMessageActionMenu();
                if (!msg) { return; }

                if (type === 'reply') {
                    setReplyTarget(msg);
                    autoGrowInput();
                    updateSendBtn();
                    inputEl.focus();
                } else if (type === 'pin') {
                    var wasPinned = !!msg.is_pinned;
                    togglePinMessageById(mid, !wasPinned);
                } else if (type === 'unsend') {
                    if (!msg.is_own) { return; }
                    openConfirmModal(
                        'Unsend this message?',
                        'This message will be removed from the conversation.',
                        function () { unsendMessageById(mid); },
                        'Unsend'
                    );
                } else if (type === 'remove') {
                    if (!msg.is_own || !msg.is_unsent) { return; }
                    openConfirmModal(
                        'Remove this unsent bubble?',
                        'This will permanently delete this unsent message.',
                        function () { removeMessageById(mid); },
                        'Remove'
                    );
                } else if (type === 'report') {
                    if (msg.is_own) { return; }
                    openReportModal(function (selectedReason) {
                        openConfirmModal(
                            'Report this message?',
                            'This will send the message to chat reports for review. Reason: ' + selectedReason,
                            function () { reportMessageById(mid, selectedReason); },
                            'Report'
                        );
                    });
                }
            });
        }

        // â”€â”€ BioTern Chat compose behavior: auto-grow + Enter to send â”€â”€
        function autoGrowInput() {
            if (!inputEl) { return; }
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px';
        }

        if (inputEl) {
            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    if (isMobileLayout()) {
                        return;
                    }
                    event.preventDefault();
                    var hasMediaOnEnter = mediaInputEl && mediaInputEl.files && mediaInputEl.files.length > 0;
                    var hasTextOnEnter = inputEl.value.replace(/[\r\n\s]+/g, '') !== '';
                    if (!hasTextOnEnter && !hasMediaOnEnter) {
                        return;
                    }
                    if (formEl) {
                        formEl.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                }
            });
            inputEl.addEventListener('input', function () {
                autoGrowInput();
                updateSendBtn();
                clearComposeWarning();
            });
            autoGrowInput();
        }

        if (formEl) {
            formEl.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!selectedUserId || !inputEl) {
                    return;
                }
                var message = inputEl.value.trim();
                var normalizedMessage = inputEl.value.replace(/[\r\n\s]+/g, '');
                var hasMedia = mediaInputEl && mediaInputEl.files && mediaInputEl.files.length > 0;
                var mode = sendBtnEl && sendBtnEl.dataset ? sendBtnEl.dataset.mode : 'send';
                var moderationError = getChatModerationError(message);

                if (!hasMedia && normalizedMessage === '') {
                    message = mode === 'like' ? '\uD83D\uDC4D' : '';
                }

                if (!message && !hasMedia) {
                    return;
                }
                if (moderationError) {
                    showComposeWarning(moderationError);
                    if (inputEl) { inputEl.focus(); }
                    return;
                }
                var formData = new FormData(formEl);
                formData.set('action', 'send-message');
                formData.set('ajax', '1');
                formData.set('user_id', String(selectedUserId));
                formData.set('message', message);
                if (sendBtnEl) {
                    sendBtnEl.disabled = true;
                }
                postChatAction(formData, selectedUserId).then(function (result) {
                    if (result.payload && result.payload.ok) {
                        clearComposeWarning();
                        applyState(result.payload, { keepInput: false, forceScroll: true });
                        clearReplyTarget();
                        clearMediaPreview();
                        updateSendBtn();
                        autoGrowInput();
                    } else if (result.payload && !result.payload.ok) {
                        var errorMessage = result.payload.error ? result.payload.error : 'Failed to send.';
                        if (isModerationWarning(errorMessage)) {
                            showComposeWarning(errorMessage);
                        } else {
                            showAlert('error', errorMessage);
                        }
                    } else if (result.ok) {
                        clearComposeWarning();
                        fetchState(true, { forceScroll: true });
                    } else {
                        var rawError = (result && result.text) ? String(result.text).trim() : '';
                        if (rawError !== '') {
                            rawError = rawError.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                        }
                        showAlert('error', rawError !== '' ? rawError : 'Failed to send.');
                    }
                }).catch(function () {
                    fetchState(true, { forceScroll: true });
                }).finally(function () {
                    if (sendBtnEl) { sendBtnEl.disabled = false; }
                    if (inputEl) { inputEl.focus(); }
                });
            });
        }

        if (searchEl) {
            searchEl.addEventListener('input', function () {
                currentSearch = searchEl.value.trim();
                if (lastContactsData.length) {
                    renderContacts(lastContactsData);
                    return;
                }
                fetchState(false);
            });
        }

        if (replyRemoveEl) {
            replyRemoveEl.addEventListener('click', function () {
                clearReplyTarget();
                if (inputEl) { inputEl.focus(); }
            });
        }

        document.addEventListener('click', function (event) {
            if (!headerEl) { return; }
            if (!event.target.closest('.btchat-actions')) {
                closeHeaderMenus();
            }
            if (messageActionMenuEl && !event.target.closest('#msg-action-menu') && !event.target.closest('.msg-hover-menu-btn')) {
                closeMessageActionMenu();
            }
            if (emojiPickerEl && !event.target.closest('#chat-emoji-picker') && !event.target.closest('#chat-emoji-btn')) {
                closeEmojiPicker();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeHeaderMenus();
                closeConfirmModal();
                closeContactModal();
                closeReportModal();
                closeMessageActionMenu();
                closeEmojiPicker();
                closeReactionsModal();
                closeMediaModal();
            }
        });

        if (confirmCancelEl) {
            confirmCancelEl.addEventListener('click', closeConfirmModal);
        }

        if (contactCloseEl) {
            contactCloseEl.addEventListener('click', closeContactModal);
        }

        if (contactCloseSecondaryEl) {
            contactCloseSecondaryEl.addEventListener('click', closeContactModal);
        }

        if (contactMuteEl) {
            contactMuteEl.addEventListener('click', function () {
                if (!(contactModalUserId > 0)) {
                    return;
                }
                var currentlyMuted = isConversationMuted(contactModalUserId);
                setConversationMuted(contactModalUserId, !currentlyMuted);
                contactMuteEl.textContent = currentlyMuted ? 'Mute conversation' : 'Unmute conversation';
                if (contactMutedStateEl) {
                    contactMutedStateEl.textContent = currentlyMuted ? 'Unmuted' : 'Muted';
                }

                if (selectedContactRef && parseInt(selectedContactRef.id || '0', 10) === contactModalUserId) {
                    renderHeader(selectedContactRef);
                }
                clearComposeWarning();
            });
        }

        if (contactReportUserEl) {
            contactReportUserEl.addEventListener('click', function () {
                reportUserFromContactModal();
            });
        }

        if (reportCancelEl) {
            reportCancelEl.addEventListener('click', closeReportModal);
        }

        if (reportOkEl) {
            reportOkEl.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                suppressHeaderToggleUntil = Date.now() + 650;
                var reason = buildReportReason();
                var fn = pendingReportFn;
                closeReportModal();
                if (typeof fn === 'function') {
                    fn(reason);
                }
            });
        }

        if (confirmOkEl) {
            confirmOkEl.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                suppressHeaderToggleUntil = Date.now() + 650;
                var fn = pendingConfirmFn;
                closeConfirmModal();
                if (typeof fn === 'function') {
                    fn();
                }
                window.setTimeout(function () {
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    scrubActionTooltips(document);
                }, 0);
            });
        }

        if (confirmModalEl) {
            confirmModalEl.addEventListener('click', function (event) {
                if (event.target === confirmModalEl) {
                    closeConfirmModal();
                }
            });
        }

        if (contactModalEl) {
            contactModalEl.addEventListener('click', function (event) {
                if (event.target === contactModalEl) {
                    closeContactModal();
                }
            });
        }

        if (reportModalEl) {
            reportModalEl.addEventListener('click', function (event) {
                if (event.target === reportModalEl) {
                    closeReportModal();
                }
            });
        }

        if (reactionsTabsEl) {
            reactionsTabsEl.addEventListener('click', function (event) {
                var tab = event.target.closest('[data-reaction-filter]');
                if (!tab || !reactionsModalState) { return; }
                reactionsModalState.filter = tab.getAttribute('data-reaction-filter') || 'all';
                renderReactionsModal();
            });
        }

        if (reactionsCloseEl) {
            reactionsCloseEl.addEventListener('click', closeReactionsModal);
        }

        if (reactionsModalEl) {
            reactionsModalEl.addEventListener('click', function (event) {
                if (event.target === reactionsModalEl) {
                    closeReactionsModal();
                }
            });
        }

        if (mediaModalCloseEl) {
            mediaModalCloseEl.addEventListener('click', closeMediaModal);
        }

        if (mediaModalDownloadEl) {
            mediaModalDownloadEl.addEventListener('click', downloadMediaFromModal);
        }

        if (mediaModalEl) {
            mediaModalEl.addEventListener('click', function (event) {
                if (event.target === mediaModalEl) {
                    closeMediaModal();
                }
            });
        }

        if (mobileLayoutQuery) {
            mobileLayoutQuery.addEventListener('change', function () {
                if (!isMobileLayout()) {
                    app.classList.remove('btchat-mobile-convo-open');
                    return;
                }
                if (selectedUserId > 0 && app.classList.contains('btchat-mobile-convo-open')) {
                    setMobileConversationOpen(true);
                }
            });
        }

        window.addEventListener('popstate', function (event) {
            if (!isMobileLayout()) {
                return;
            }
            var state = event.state || {};
            if (state.btchatView === 'conversation' && state.userId > 0) {
                selectedUserId = parseInt(state.userId, 10) || 0;
                if (!selectedUserId) {
                    setMobileConversationOpen(false);
                    return;
                }
                app.setAttribute('data-selected-user-id', String(selectedUserId));
                setActiveContactVisual(selectedUserId);
                setMobileConversationOpen(true);
                setThreadLoading(true);
                fetchState(false, { forceScroll: false });
                return;
            }

            setMobileConversationOpen(false);
            closeHeaderMenus();
            closeMessageActionMenu();
            closeEmojiPicker();
            closeReactionsModal();
        });

        bindHeaderMenu();
        scrubActionTooltips(app);

        if (isMobileLayout()) {
            setMobileHistoryState('list', 0);
        }

        clearReplyTarget();
        renderEmojiPicker();
        updateSendBtn();
        autoGrowInput();
        scrollThreadToBottom(true);
        updateScrollBtn();
        fetchState(false, { forceScroll: true });

        pollHandle = window.setInterval(function () {
            fetchState(false);
        }, 5000);
    })();
</script>

<?php
include 'includes/footer.php';
$conn->close();
?>
