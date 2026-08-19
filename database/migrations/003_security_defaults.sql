-- Seed explicit control-plane defaults after 002_saas_control_plane.sql.
-- Safe to run repeatedly.
INSERT INTO global_settings (setting_key,setting_value,is_secret) VALUES
('maintenance_mode','false',0),
('signup_open','true',0),
('default_otp_ttl_seconds','300',0),
('default_otp_length','6',0),
('global_ip_hourly_limit','50',0),
('global_email_hourly_limit','20',0),
('signup_max_per_ip_per_hour','20',0),
('signup_max_per_email_per_hour','3',0),
('login_max_per_ip_per_hour','30',0),
('login_max_per_email_per_hour','10',0),
('admin_login_max_per_ip_per_hour','20',0),
('admin_login_max_per_email_per_hour','10',0),
('password_reset_max_per_ip_per_hour','10',0),
('password_reset_max_per_email_per_hour','3',0)
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
