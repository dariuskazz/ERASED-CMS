CREATE TABLE IF NOT EXISTS membership_plans (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 description TEXT NULL,
 price_minor INT UNSIGNED NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'EUR',
 billing_interval ENUM('one_time','month','year') NOT NULL DEFAULT 'month',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payment_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 provider VARCHAR(50) NOT NULL,
 provider_transaction_id VARCHAR(190) NOT NULL,
 amount_minor INT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL,
 status ENUM('pending','paid','failed','refunded','disputed') NOT NULL DEFAULT 'pending',
 metadata JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_provider_transaction(provider,provider_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS content_access (
 content_id INT UNSIGNED PRIMARY KEY,
 access_mode ENUM('public','registered','members','plan','one_time','password') NOT NULL DEFAULT 'public',
 teaser LONGTEXT NULL,
 price_minor INT UNSIGNED NULL,
 currency CHAR(3) NULL,
 plan_id INT UNSIGNED NULL,
 FOREIGN KEY(content_id) REFERENCES content(id) ON DELETE CASCADE,
 FOREIGN KEY(plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
