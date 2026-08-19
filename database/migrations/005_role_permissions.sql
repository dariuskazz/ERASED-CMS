ALTER TABLE content ADD COLUMN IF NOT EXISTS author_id INT UNSIGNED NULL AFTER id;
ALTER TABLE content ADD INDEX IF NOT EXISTS idx_content_author_id (author_id);
UPDATE content SET author_id=(SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1) WHERE author_id IS NULL;
UPDATE users SET role='writer' WHERE role='author';
UPDATE users SET role='user' WHERE role='member';
