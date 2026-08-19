CREATE TABLE IF NOT EXISTS commerce_product_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    view_day DATE NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY commerce_product_views_daily (product_id, view_day),
    INDEX (view_day),
    CONSTRAINT commerce_product_views_product_fk FOREIGN KEY (product_id) REFERENCES commerce_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
