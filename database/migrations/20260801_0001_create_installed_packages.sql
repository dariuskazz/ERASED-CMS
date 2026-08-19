CREATE TABLE IF NOT EXISTS installed_packages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id VARCHAR(190) NOT NULL,
    package_type VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    version VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    installed_path VARCHAR(500) NOT NULL,
    manifest_json LONGTEXT NOT NULL,
    installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY installed_packages_package_id_unique (package_id),
    KEY installed_packages_type_enabled_index (package_type, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
