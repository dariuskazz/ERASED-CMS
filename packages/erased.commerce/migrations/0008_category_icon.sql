ALTER TABLE commerce_categories
    ADD COLUMN icon VARCHAR(8) NOT NULL DEFAULT '' AFTER name;
