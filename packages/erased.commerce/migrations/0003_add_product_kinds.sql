ALTER TABLE commerce_products
    ADD COLUMN kind ENUM('physical','digital','subscription') NOT NULL DEFAULT 'physical' AFTER category,
    ADD COLUMN subscription_interval ENUM('month','year') NULL AFTER kind;

ALTER TABLE commerce_order_items
    ADD COLUMN product_kind ENUM('physical','digital','subscription') NOT NULL DEFAULT 'physical' AFTER product_name;

CREATE TABLE IF NOT EXISTS commerce_product_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(64) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT commerce_product_files_product_fk FOREIGN KEY (product_id) REFERENCES commerce_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commerce_downloads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT UNSIGNED NOT NULL,
    product_file_id INT UNSIGNED NOT NULL,
    token CHAR(40) NOT NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY commerce_downloads_token (token),
    CONSTRAINT commerce_downloads_order_item_fk FOREIGN KEY (order_item_id) REFERENCES commerce_order_items(id) ON DELETE CASCADE,
    CONSTRAINT commerce_downloads_product_file_fk FOREIGN KEY (product_file_id) REFERENCES commerce_product_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commerce_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(190) NOT NULL,
    customer_email VARCHAR(190) NOT NULL,
    order_id INT UNSIGNED NULL,
    status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    interval_name ENUM('month','year') NOT NULL DEFAULT 'month',
    current_period_end DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT commerce_subscriptions_product_fk FOREIGN KEY (product_id) REFERENCES commerce_products(id) ON DELETE SET NULL,
    CONSTRAINT commerce_subscriptions_order_fk FOREIGN KEY (order_id) REFERENCES commerce_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
