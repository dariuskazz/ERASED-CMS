ALTER TABLE commerce_products
    ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD INDEX commerce_products_featured (featured);
