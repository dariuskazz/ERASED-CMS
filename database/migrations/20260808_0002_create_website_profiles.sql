CREATE TABLE IF NOT EXISTS website_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_id VARCHAR(60) NOT NULL,
    name VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    is_starter TINYINT(1) NOT NULL DEFAULT 0,
    config_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY website_profiles_status_index (status),
    KEY website_profiles_type_index (type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
