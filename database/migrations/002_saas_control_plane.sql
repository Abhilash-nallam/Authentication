-- OTP Auth SaaS control-plane upgrade.
-- Apply once after 001_customer_platform.sql to an existing Phase 1/Customer Platform database.

ALTER TABLE api_keys
  ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN environment ENUM('test','production') NOT NULL DEFAULT 'test',
  ADD COLUMN status ENUM('active','disabled','revoked') NOT NULL DEFAULT 'active',
  ADD COLUMN allowed_ips TEXT NULL,
  ADD COLUMN allowed_origins TEXT NULL,
  ADD COLUMN allowed_endpoints TEXT NULL,
  ADD COLUMN allowed_purposes VARCHAR(255) NULL,
  ADD COLUMN hourly_limit INT UNSIGNED NULL,
  ADD COLUMN monthly_limit INT UNSIGNED NULL,
  ADD COLUMN created_by BIGINT UNSIGNED NULL,
  ADD COLUMN revoked_by BIGINT UNSIGNED NULL,
  ADD COLUMN revoked_reason VARCHAR(255) NULL,
  ADD COLUMN expires_at DATETIME NULL;

ALTER TABLE otp_challenges
  ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER api_key_id,
  ADD COLUMN api_key_prefix_snapshot VARCHAR(16) NULL,
  ADD COLUMN status ENUM('requested','sent','delivery_failed','verify_failed','verified','expired','consumed','unused','rate_limited','blocked') NOT NULL DEFAULT 'requested',
  ADD COLUMN delivered_at DATETIME NULL,
  ADD COLUMN delivery_failed_at DATETIME NULL,
  ADD COLUMN ses_message_id VARCHAR(255) NULL,
  ADD COLUMN last_error_code VARCHAR(80) NULL,
  ADD COLUMN recipient_domain VARCHAR(253) NULL,
  ADD COLUMN user_agent_hash CHAR(64) NULL,
  ADD COLUMN verify_failed_at DATETIME NULL,
  ADD COLUMN resend_count INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN metadata_json JSON NULL;

CREATE TABLE IF NOT EXISTS admin_roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(320) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_admin_sessions_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULL,
  project_id BIGINT UNSIGNED NULL,
  api_key_id BIGINT UNSIGNED NULL,
  request_id CHAR(36) NULL,
  event_type VARCHAR(60) NOT NULL,
  status VARCHAR(40) NULL,
  error_code VARCHAR(80) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_otp_events_created (created_at),
  INDEX idx_otp_events_customer (customer_id, created_at),
  INDEX idx_otp_events_project (project_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_settings (
  customer_id BIGINT UNSIGNED PRIMARY KEY,
  otp_length TINYINT UNSIGNED NULL,
  otp_ttl_seconds INT UNSIGNED NULL,
  max_verify_attempts INT UNSIGNED NULL,
  resend_cooldown_seconds INT UNSIGNED NULL,
  allowed_purposes VARCHAR(255) NULL,
  branding_json JSON NULL,
  webhook_url VARCHAR(2048) NULL,
  webhook_secret_hash CHAR(64) NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_settings (
  project_id BIGINT UNSIGNED PRIMARY KEY,
  environment ENUM('test','production') NOT NULL DEFAULT 'test',
  allowed_domains TEXT NULL,
  callback_url VARCHAR(2048) NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS global_settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value TEXT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usage_daily (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULL,
  project_id BIGINT UNSIGNED NULL,
  usage_date DATE NOT NULL,
  requested_count INT UNSIGNED NOT NULL DEFAULT 0,
  verified_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  delivery_failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  resent_count INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_usage_daily (customer_id, project_id, usage_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhooks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  url VARCHAR(2048) NOT NULL,
  secret_hash CHAR(64) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  webhook_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  payload_json JSON NOT NULL,
  status_code SMALLINT UNSIGNED NULL,
  attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  delivered_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blocked_entities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('customer','api_key','email_hash','ip_hash','email_domain') NOT NULL,
  entity_value VARCHAR(255) NOT NULL,
  reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_blocked_entity (entity_type, entity_value)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ses_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(80) NOT NULL,
  provider_message_id VARCHAR(255) NULL,
  recipient_hash CHAR(64) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ses_events_created (created_at),
  INDEX idx_ses_events_message (provider_message_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS billing_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  monthly_otp_limit INT UNSIGNED NOT NULL,
  hourly_otp_limit INT UNSIGNED NOT NULL,
  max_projects INT UNSIGNED NOT NULL DEFAULT 1,
  max_api_keys INT UNSIGNED NOT NULL DEFAULT 2,
  log_retention_days INT UNSIGNED NOT NULL DEFAULT 7,
  custom_branding_allowed TINYINT(1) NOT NULL DEFAULT 0,
  webhooks_allowed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_plan_subscriptions (
  customer_id BIGINT UNSIGNED PRIMARY KEY,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('trial','active','cancelled') NOT NULL DEFAULT 'trial',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES billing_plans(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS abuse_limits (
  bucket_key CHAR(64) PRIMARY KEY,
  window_started_at DATETIME NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_abuse_limits_window (window_started_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO admin_roles (id,name,description) VALUES
(1,'super_admin','Full platform control'),
(2,'support_admin','Customer and log support access'),
(3,'security_admin','Security and abuse controls'),
(4,'read_only','Read-only platform inspection');

INSERT IGNORE INTO admin_permissions (name) VALUES
('customers.view'),('customers.suspend'),('api_keys.revoke'),('otp_logs.view'),('otp_logs.export'),
('settings.update'),('ses.manage'),('dns.view'),('billing.manage'),('admins.manage'),('security.manage');

INSERT IGNORE INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p WHERE r.name='super_admin';

INSERT IGNORE INTO billing_plans (name,monthly_otp_limit,hourly_otp_limit,max_projects,max_api_keys,log_retention_days,custom_branding_allowed,webhooks_allowed) VALUES
('Free/Test',1000,100,1,2,7,0,0),
('Starter',10000,500,3,5,30,0,1),
('Pro',100000,2000,10,20,90,1,1),
('Business',1000000,10000,50,100,365,1,1);
