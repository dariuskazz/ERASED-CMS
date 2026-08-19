-- ERASED CMS 0.1 Beta - automatic IP lockout and management
CREATE TABLE IF NOT EXISTS security_ip_lockouts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ip_address VARCHAR(45) NOT NULL,
 failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
 last_email VARCHAR(190) NULL,
 reason VARCHAR(255) NOT NULL DEFAULT 'Repeated failed login attempts',
 locked_until DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_security_ip_lockout_ip (ip_address),
 INDEX idx_security_ip_lockout_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
 ('ip_lockout_enabled','1'),
 ('ip_lockout_threshold','8'),
 ('ip_lockout_window_minutes','15'),
 ('ip_lockout_duration_minutes','30');
