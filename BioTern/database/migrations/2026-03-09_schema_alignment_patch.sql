-- BioTern schema alignment patch
-- Target: MariaDB 10.4+
-- Apply on database: biotern_db

START TRANSACTION;

ALTER TABLE admin
  MODIFY id INT(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE IF NOT EXISTS coordinator_courses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coordinator_user_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coord_course (coordinator_user_id, course_id),
  KEY idx_coord_user (coordinator_user_id),
  KEY idx_coord_course (course_id),
  CONSTRAINT fk_coord_courses_user FOREIGN KEY (coordinator_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_coord_courses_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO coordinator_courses (coordinator_user_id, course_id)
SELECT DISTINCT i.coordinator_id, i.course_id
FROM internships i
WHERE i.coordinator_id IS NOT NULL
  AND i.course_id IS NOT NULL;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS type VARCHAR(50) NOT NULL DEFAULT 'system' AFTER message,
  ADD COLUMN IF NOT EXISTS action_url VARCHAR(255) NULL AFTER type;

ALTER TABLE courses
  ADD COLUMN IF NOT EXISTS internal_hours INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS external_hours INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS school_year VARCHAR(50) NULL;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS internal_total_hours INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS internal_total_hours_remaining INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS external_total_hours INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS external_total_hours_remaining INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal';

ALTER TABLE internships
  ADD COLUMN IF NOT EXISTS required_hours INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS rendered_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS completion_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS school_year VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS type VARCHAR(20) NOT NULL DEFAULT 'internal';

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(255) NULL,
  message LONGTEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_messages_pair (from_user_id, to_user_id),
  INDEX idx_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS school_years (
  id INT(11) NOT NULL AUTO_INCREMENT,
  year VARCHAR(20) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO school_years (year)
SELECT DISTINCT TRIM(school_year)
FROM internships
WHERE school_year IS NOT NULL
  AND TRIM(school_year) <> '';

DROP VIEW IF EXISTS student_profile_with_internship;

CREATE VIEW student_profile_with_internship AS
SELECT
  s.id AS id,
  s.student_id AS student_id,
  CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name,
  s.email AS email,
  s.phone AS phone,
  s.date_of_birth AS date_of_birth,
  s.gender AS gender,
  s.address AS address,
  s.emergency_contact AS emergency_contact,
  CASE WHEN s.status IN ('1', 'active') THEN 1 ELSE 0 END AS is_active,
  c.name AS course,
  c.code AS course_code,
  u.name AS user_name,
  u.role AS user_role,
  i.id AS internship_id,
  i.type AS internship_type,
  i.company_name AS company_name,
  i.position AS position,
  i.start_date AS start_date,
  i.end_date AS end_date,
  i.status AS internship_status,
  i.required_hours AS required_hours,
  i.rendered_hours AS rendered_hours,
  i.completion_percentage AS completion_percentage,
  coord.name AS coordinator_name,
  sup.name AS supervisor_name
FROM students s
LEFT JOIN courses c ON s.course_id = c.id
LEFT JOIN users u ON s.user_id = u.id
LEFT JOIN internships i ON s.id = i.student_id
LEFT JOIN users coord ON i.coordinator_id = coord.id
LEFT JOIN users sup ON i.supervisor_id = sup.id;

COMMIT;
