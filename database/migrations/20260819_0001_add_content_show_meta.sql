-- Per-item control over the "Created <date> · N min read" line under a
-- title. NULL = fall back to the type-based default (posts show it, pages
-- don't), so existing rows keep their current behaviour with no backfill.
ALTER TABLE content ADD COLUMN show_meta TINYINT(1) DEFAULT NULL;
