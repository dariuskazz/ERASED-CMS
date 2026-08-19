ALTER TABLE commerce_orders
    ADD INDEX commerce_orders_created_at (created_at);

ALTER TABLE commerce_products
    ADD COLUMN subcategory VARCHAR(190) NOT NULL DEFAULT '' AFTER category,
    ADD INDEX commerce_products_category (category, subcategory);

CREATE TABLE IF NOT EXISTS commerce_product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    media_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT commerce_product_images_product_fk FOREIGN KEY (product_id) REFERENCES commerce_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
