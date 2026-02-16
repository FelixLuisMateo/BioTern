-- Add columns for rejection tracking to attendances table
ALTER TABLE `attendances` ADD COLUMN `rejection_remarks` TEXT NULL DEFAULT NULL AFTER `remarks`;
ALTER TABLE `attendances` ADD COLUMN `rejected_by` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `rejection_remarks`;
ALTER TABLE `attendances` ADD COLUMN `rejected_at` TIMESTAMP NULL DEFAULT NULL AFTER `rejected_by`;
