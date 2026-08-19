CREATE TABLE IF NOT EXISTS core_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL,
    status ENUM('staged', 'applying', 'applied', 'failed', 'discarded') NOT NULL DEFAULT 'staged',
    from_version VARCHAR(64) NOT NULL,
    to_version VARCHAR(64) NOT NULL,
    staged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at DATETIME NULL,
    staged_by INT UNSIGNED NULL,
    archive_original_name VARCHAR(255) NOT NULL,
    diff_summary LONGTEXT NULL,
    pending_migrations LONGTEXT NULL,
    code_backup_directory VARCHAR(500) NULL,
    db_backup_file VARCHAR(500) NULL,
    error_message TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY core_updates_token_unique (token),
    KEY core_updates_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
