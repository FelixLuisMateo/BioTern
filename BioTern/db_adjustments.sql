-- SQL adjustments to ensure coordinator/supervisor tables have required columns
-- Run these on your `biotern_db` (MariaDB/MySQL). Review before executing.

-- Make students.user_id nullable so student records can exist without a users row
ALTER TABLE students DROP CONSTRAINT students_ibfk_1;
ALTER TABLE students MODIFY COLUMN user_id bigint(20) UNSIGNED NULL;
ALTER TABLE students ADD CONSTRAINT students_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE coordinators
  ADD COLUMN IF NOT EXISTS `user_id` bigint(20) UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS `first_name` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `last_name` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `middle_name` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `email` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `phone` varchar(20) NULL,
  ADD COLUMN IF NOT EXISTS `department_id` bigint(20) UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS `office_location` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `bio` longtext NULL,
  ADD COLUMN IF NOT EXISTS `profile_picture` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) NOT NULL DEFAULT 1;

ALTER TABLE supervisors
  ADD COLUMN IF NOT EXISTS `user_id` bigint(20) UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS `first_name` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `last_name` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `middle_name` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `email` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `phone` varchar(20) NULL,
  ADD COLUMN IF NOT EXISTS `department_id` bigint(20) UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS `specialization` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `bio` longtext NULL,
  ADD COLUMN IF NOT EXISTS `profile_picture` varchar(255) NULL,
  ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) NOT NULL DEFAULT 1;

-- Optionally add foreign keys (if departments and users exist)
-- ALTER TABLE coordinators ADD CONSTRAINT fk_coordinators_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
-- ALTER TABLE coordinators ADD CONSTRAINT fk_coordinators_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
-- ALTER TABLE supervisors ADD CONSTRAINT fk_supervisors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
-- ALTER TABLE supervisors ADD CONSTRAINT fk_supervisors_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
