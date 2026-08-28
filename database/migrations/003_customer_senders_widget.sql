-- OTP Auth Customer Dashboard A: sender identities and browser-widget control plane.
-- Apply after 002_saas_control_plane.sql.

CREATE TABLE IF NOT EXISTS project_sender_identities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  local_part VARCHAR(64) NOT NULL,
  display_name VARCHAR(120) NULL,
  full_address VARCHAR(320) NOT NULL UNIQUE,
  status ENUM('reserved','pending_dns','verified','disabled') NOT NULL DEFAULT 'reserved',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sender_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  UNIQUE KEY uq_sender_project_local (project_id,local_part),
  INDEX idx_sender_project_status (project_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_widget_settings (
  project_id BIGINT UNSIGNED PRIMARY KEY,
  widget_enabled TINYINT(1) NOT NULL DEFAULT 1,
  allowed_origins TEXT NULL,
  test_enabled TINYINT(1) NOT NULL DEFAULT 1,
  production_enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_widget_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO global_settings(setting_key,setting_value,is_secret) VALUES
('widget_default_enabled','true',0),
('widget_max_requests_per_hour','100',0);
