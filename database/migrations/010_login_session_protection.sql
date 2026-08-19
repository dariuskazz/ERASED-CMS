-- ERASED CMS 0.1 Beta - complete login and session protection
CREATE TABLE IF NOT EXISTS login_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL, email VARCHAR(190) NOT NULL, ip_address VARCHAR(45) NULL,
 user_agent TEXT NULL, successful TINYINT(1) NOT NULL DEFAULT 0, failure_reason VARCHAR(100) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_history_user (user_id), INDEX idx_login_history_ip (ip_address), INDEX idx_login_history_created (created_at), INDEX idx_login_history_success (successful)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS auth_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL,
 ip_address VARCHAR(45) NULL, user_agent TEXT NULL, last_activity_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_auth_session_token (token_hash), INDEX idx_auth_session_user (user_id), INDEX idx_auth_session_active (revoked_at,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES
 ('password_min_length','12'),('password_require_uppercase','1'),('password_require_lowercase','1'),('password_require_number','1'),('password_require_symbol','1'),('session_timeout_minutes','30');
