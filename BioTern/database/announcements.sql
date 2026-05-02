CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    media_path VARCHAR(255) NULL,
    media_type VARCHAR(20) NOT NULL DEFAULT 'image',
    popup_size VARCHAR(20) NOT NULL DEFAULT 'medium',
    accent_color VARCHAR(20) NOT NULL DEFAULT '#3454d1',
    button_label VARCHAR(80) NOT NULL DEFAULT 'Got It',
    show_title TINYINT(1) NOT NULL DEFAULT 1,
    target_role VARCHAR(30) NOT NULL DEFAULT 'all',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_active (is_active, target_role),
    INDEX idx_announcements_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    dismissed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_announcement_user (announcement_id, user_id),
    INDEX idx_announcement_reads_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
