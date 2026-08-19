CREATE TABLE IF NOT EXISTS commerce_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description LONGTEXT NULL,
    price_minor INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    sku VARCHAR(100) NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    track_inventory TINYINT(1) NOT NULL DEFAULT 1,
    category VARCHAR(190) NOT NULL DEFAULT '',
    featured_media_id INT UNSIGNED NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY commerce_products_slug (slug),
    UNIQUE KEY commerce_products_sku (sku),
    KEY commerce_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commerce_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL,
    customer_name VARCHAR(190) NOT NULL,
    customer_email VARCHAR(190) NOT NULL,
    status ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
    subtotal_minor INT UNSIGNED NOT NULL,
    total_minor INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    payment_transaction_id BIGINT UNSIGNED NULL,
    confirmation_token CHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY commerce_orders_number (order_number),
    CONSTRAINT commerce_orders_payment_transaction_fk FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commerce_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(190) NOT NULL,
    unit_price_minor INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total_minor INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    CONSTRAINT commerce_order_items_order_fk FOREIGN KEY (order_id) REFERENCES commerce_orders(id) ON DELETE CASCADE,
    CONSTRAINT commerce_order_items_product_fk FOREIGN KEY (product_id) REFERENCES commerce_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
