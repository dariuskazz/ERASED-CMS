ALTER TABLE installed_packages
    ADD COLUMN integrity_manifest_json LONGTEXT NULL AFTER manifest_json,
    ADD COLUMN integrity_status VARCHAR(20) NOT NULL DEFAULT 'unknown' AFTER integrity_manifest_json,
    ADD COLUMN integrity_checked_at DATETIME NULL AFTER integrity_status;
