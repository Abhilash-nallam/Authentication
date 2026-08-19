-- Apply to an existing Phase 1 database. Run once during the platform upgrade.
ALTER TABLE api_keys ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE otp_challenges ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER api_key_id;
ALTER TABLE customers ADD COLUMN email_verification_hash CHAR(64) NULL AFTER password_hash;
ALTER TABLE customers ADD COLUMN email_verification_expires_at DATETIME NULL AFTER email_verification_hash;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(36) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  website_domain VARCHAR(253) NULL,
  status ENUM('draft','pending_verification','verified','suspended') NOT NULL DEFAULT 'draft',
  otp_subdomain VARCHAR(63) NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(320) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email_verification_hash CHAR(64) NULL,
  email_verification_expires_at DATETIME NULL,
  email_verified_at DATETIME NULL,
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS domain_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  domain VARCHAR(253) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  verified_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_domain_project (project_id), UNIQUE KEY uq_verified_domain (domain),
  CONSTRAINT fk_domain_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_session_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX idx_sessions_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NULL,
  request_id CHAR(36) NULL,
  event_type VARCHAR(40) NOT NULL,
  recipient_hash CHAR(64) NULL,
  provider_message_id VARCHAR(255) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_events_request (request_id), INDEX idx_email_events_created (created_at)
) ENGINE=InnoDB;

ALTER TABLE api_keys ADD INDEX idx_api_keys_project (project_id);
ALTER TABLE otp_challenges ADD INDEX idx_otp_project_email (project_id, email, purpose, created_at);
