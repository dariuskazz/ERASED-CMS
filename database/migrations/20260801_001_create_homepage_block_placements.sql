CREATE TABLE IF NOT EXISTS `homepage_block_placements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `instance_id` VARCHAR(190) NOT NULL,
    `profile_id` VARCHAR(190) NOT NULL,
    `region` VARCHAR(100) NOT NULL,
    `block_id` VARCHAR(190) NOT NULL,
    `position_index` INT UNSIGNED NOT NULL DEFAULT 0,
    `visible` TINYINT(1) NOT NULL DEFAULT 1,
    `settings_json` LONGTEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `homepage_block_placements_instance_unique` (`instance_id`),
    KEY `homepage_block_placements_profile_region_position` (`profile_id`, `region`, `position_index`),
    KEY `homepage_block_placements_block` (`block_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
