CREATE TABLE IF NOT EXISTS commerce_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description TEXT NULL,
    parent_id INT UNSIGNED NULL,
    image_media_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY commerce_categories_slug (slug),
    KEY commerce_categories_parent (parent_id),
    CONSTRAINT commerce_categories_parent_fk FOREIGN KEY (parent_id) REFERENCES commerce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE commerce_products
    ADD COLUMN category_id INT UNSIGNED NULL AFTER subcategory,
    ADD CONSTRAINT commerce_products_category_fk FOREIGN KEY (category_id) REFERENCES commerce_categories(id) ON DELETE SET NULL,
    ADD KEY commerce_products_category_id (category_id),
    ADD FULLTEXT INDEX commerce_products_search_ft (name, description, sku);

-- One-time backfill: the old category/subcategory free-text columns become
-- real rows (subcategory nested under its category as a two-level
-- hierarchy), and every existing product is linked to the most specific
-- match. The columns themselves are left in place rather than dropped -
-- ProductRepository stops reading them once this ships, but keeping them
-- is a free, zero-risk rollback path if anything about the new table needs
-- fixing later.
INSERT INTO commerce_categories (name, slug, parent_id)
SELECT DISTINCT TRIM(category), LOWER(REPLACE(REPLACE(REPLACE(TRIM(category), ' ', '-'), '&', 'and'), '/', '-')), NULL
FROM commerce_products WHERE TRIM(category) <> '';

INSERT INTO commerce_categories (name, slug, parent_id)
SELECT DISTINCT TRIM(p.subcategory),
    CONCAT(
        LOWER(REPLACE(REPLACE(REPLACE(TRIM(p.category), ' ', '-'), '&', 'and'), '/', '-')), '-',
        LOWER(REPLACE(REPLACE(REPLACE(TRIM(p.subcategory), ' ', '-'), '&', 'and'), '/', '-'))
    ),
    parent.id
FROM commerce_products p
JOIN commerce_categories parent ON parent.name = TRIM(p.category) AND parent.parent_id IS NULL
WHERE TRIM(p.subcategory) <> '';

UPDATE commerce_products p
LEFT JOIN commerce_categories top ON top.name = TRIM(p.category) AND top.parent_id IS NULL
LEFT JOIN commerce_categories sub ON sub.name = TRIM(p.subcategory) AND sub.parent_id = top.id
SET p.category_id = COALESCE(sub.id, top.id)
WHERE TRIM(p.category) <> '';
