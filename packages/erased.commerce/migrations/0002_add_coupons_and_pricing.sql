CREATE TABLE IF NOT EXISTS commerce_coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    type ENUM('percent','fixed') NOT NULL,
    value INT UNSIGNED NOT NULL,
    max_uses INT UNSIGNED NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY commerce_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE commerce_orders
    ADD COLUMN coupon_id INT UNSIGNED NULL AFTER payment_transaction_id,
    ADD COLUMN coupon_code VARCHAR(40) NULL AFTER coupon_id,
    ADD COLUMN discount_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER coupon_code,
    ADD COLUMN shipping_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_minor,
    ADD COLUMN tax_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_minor,
    ADD CONSTRAINT commerce_orders_coupon_fk FOREIGN KEY (coupon_id) REFERENCES commerce_coupons(id) ON DELETE SET NULL;
