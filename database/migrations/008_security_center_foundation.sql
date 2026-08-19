-- ==========================================================
-- ERASED CMS 0.1 Beta
-- Security Center Foundation
-- Migration 008
-- ==========================================================

CREATE TABLE IF NOT EXISTS security_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    level ENUM(
        'info',
        'notice',
        'warning',
        'danger',
        'critical'
    ) NOT NULL DEFAULT 'info',

    event_type VARCHAR(100) NOT NULL,

    user_id INT UNSIGNED NULL,

    username VARCHAR(190) NULL,

    ip_address VARCHAR(45) NULL,

    user_agent TEXT NULL,

    details LONGTEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX(level),
    INDEX(event_type),
    INDEX(user_id),
    INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_scan_results (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    check_name VARCHAR(150) NOT NULL,

    category VARCHAR(100) NOT NULL,

    status ENUM(
        'ok',
        'warning',
        'failed'
    ) NOT NULL,

    severity ENUM(
        'low',
        'medium',
        'high',
        'critical'
    ) NOT NULL,

    message TEXT,

    recommendation TEXT,

    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX(category),
    INDEX(status),
    INDEX(severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_recommendations (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    recommendation_key VARCHAR(150) UNIQUE NOT NULL,

    title VARCHAR(255) NOT NULL,

    description TEXT NOT NULL,

    severity ENUM(
        'low',
        'medium',
        'high',
        'critical'
    ) NOT NULL,

    is_completed TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    completed_at TIMESTAMP NULL,

    INDEX(severity),
    INDEX(is_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO security_settings
(setting_key, setting_value)
VALUES
('security.enabled','1'),
('security.score','100'),
('security.last_scan',''),
('security.auto_scan','0'),
('security.log_retention_days','180'),
('security.headers.enabled','1'),
('security.events.enabled','1'),
('security.dashboard.version','0.1-beta');
