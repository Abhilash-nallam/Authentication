CREATE DATABASE IF NOT EXISTS otp_auth
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE otp_auth;

CREATE TABLE IF NOT EXISTS api_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,
  key_hash CHAR(64) NOT NULL UNIQUE,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_api_keys_prefix (key_prefix)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id CHAR(36) NOT NULL UNIQUE,
  api_key_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(320) NOT NULL,
  purpose ENUM('registration','login','password_reset','generic') NOT NULL,
  otp_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_otp_lookup (api_key_id, email, purpose, created_at),
  INDEX idx_otp_expiry (expires_at),
  CONSTRAINT fk_otp_api_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  api_key_id BIGINT UNSIGNED NOT NULL,
  bucket_key VARCHAR(255) NOT NULL,
  window_started_at DATETIME NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rate_bucket (api_key_id, bucket_key),
  CONSTRAINT fk_rate_api_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  api_key_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  request_id CHAR(36) NULL,
  email_hash CHAR(64) NULL,
  ip_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_event (event_type),
  CONSTRAINT fk_audit_api_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dashboard_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(320) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
