-- BioTern unused empty table schema backup
-- Database: railway on switchback.proxy.rlwy.net:40818
-- Created at: 2026-05-21T01:09:26+08:00

-- Table: biometric_data
DROP TABLE IF EXISTS `biometric_data`;
CREATE TABLE `biometric_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `biometric_type` enum('fingerprint','face','iris') COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` longblob,
  `registered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `biometric_data_student_id_foreign` (`student_id`),
  CONSTRAINT `biometric_data_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: daily_time_records
DROP TABLE IF EXISTS `daily_time_records`;
CREATE TABLE `daily_time_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `hours_worked` decimal(5,2) NOT NULL DEFAULT '0.00',
  `remarks` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`),
  KEY `daily_time_records_student_id_foreign` (`student_id`),
  CONSTRAINT `daily_time_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: hour_logs
DROP TABLE IF EXISTS `hour_logs`;
CREATE TABLE `hour_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `date` date NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hour_logs_student_id_foreign` (`student_id`),
  CONSTRAINT `hour_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: upload_settings
DROP TABLE IF EXISTS `upload_settings`;
CREATE TABLE `upload_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `upload_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'profile_picture, manual_dtr, documents',
  `base_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path relative to web root',
  `max_file_size` bigint DEFAULT '5242880' COMMENT 'In bytes (5MB default)',
  `allowed_extensions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_upload_type` (`upload_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

