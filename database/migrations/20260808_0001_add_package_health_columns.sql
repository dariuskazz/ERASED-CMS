ALTER TABLE installed_packages
    ADD COLUMN health_status VARCHAR(20) NOT NULL DEFAULT 'ok' AFTER enabled,
    ADD COLUMN last_error TEXT NULL AFTER health_status,
    ADD COLUMN last_error_at DATETIME NULL AFTER last_error;
