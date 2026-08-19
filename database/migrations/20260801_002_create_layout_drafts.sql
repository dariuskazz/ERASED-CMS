CREATE TABLE IF NOT EXISTS layout_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    draft_key VARCHAR(190) NOT NULL UNIQUE,
    profile_id VARCHAR(190) NOT NULL,
    target_type VARCHAR(80) NOT NULL DEFAULT 'homepage',
    target_id VARCHAR(190) NOT NULL DEFAULT 'default',
    name VARCHAR(190) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    author_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    payload_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_layout_drafts_profile (profile_id),
    INDEX idx_layout_drafts_target (target_type, target_id),
    INDEX idx_layout_drafts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
