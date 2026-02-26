-- BioTern operational hardening updates
-- Run this once on biotern_db

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(120) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  before_data LONGTEXT NULL,
  after_data LONGTEXT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_action_created (action, created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_correction_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attendance_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  requester_role VARCHAR(40) NOT NULL,
  correction_reason TEXT NOT NULL,
  requested_changes LONGTEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  review_remarks TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_correction_status_created (status, created_at),
  KEY idx_correction_attendance (attendance_id),
  KEY idx_correction_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS biometric_event_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  clock_type VARCHAR(40) NOT NULL,
  clock_time TIME NOT NULL,
  event_source VARCHAR(40) NOT NULL DEFAULT 'api',
  status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
  retries INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  processed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bio_queue_status_created (status, created_at),
  KEY idx_bio_queue_student_date (student_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backup_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backup_name VARCHAR(255) NOT NULL,
  backup_path VARCHAR(255) NOT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL DEFAULT NULL,
  status ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  remarks VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_backup_status_started (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW attendance_operational_report AS
SELECT
  a.attendance_date,
  COUNT(*) AS total_records,
  SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved_records,
  SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) AS pending_records,
  SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_records,
  SUM(CASE WHEN a.total_hours IS NULL OR a.total_hours = 0 THEN 1 ELSE 0 END) AS zero_hour_records
FROM attendances a
GROUP BY a.attendance_date;

