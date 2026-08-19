-- NOTE (2026-08-16, found during a codebase audit): this migration is a dead
-- no-op. 20260801_001_create_homepage_block_placements.sql already created
-- this table with a wider profile_id (VARCHAR(190), needed since profile_id
-- values can be non-numeric slugs) and an extra block_id index; because both
-- files use CREATE TABLE IF NOT EXISTS, this one's CREATE never actually
-- runs, yet the migration runner still marks it "applied" with no warning -
-- so checking this file for "what schema is live" is misleading. The real,
-- live definition is the one in the 20260801_001 migration; do not edit the
-- SQL below (already-applied migrations are a historical record, not
-- re-run), and do not add a third migration attempting to "fix" a table
-- that's already correct.
CREATE TABLE IF NOT EXISTS homepage_block_placements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instance_id VARCHAR(190) NOT NULL UNIQUE,
    profile_id VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL,
    block_id VARCHAR(190) NOT NULL,
    position_index INT UNSIGNED NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    settings_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX profile_region_position (profile_id, region, position_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
