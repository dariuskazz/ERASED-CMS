CREATE TABLE IF NOT EXISTS languages (
 code VARCHAR(12) PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 native_name VARCHAR(100) NOT NULL,
 is_default TINYINT(1) NOT NULL DEFAULT 0,
 is_enabled TINYINT(1) NOT NULL DEFAULT 1,
 is_rtl TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS content_translations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 content_id INT UNSIGNED NOT NULL,
 language_code VARCHAR(12) NOT NULL,
 title VARCHAR(255) NOT NULL,
 slug VARCHAR(255) NOT NULL,
 excerpt TEXT NULL,
 body LONGTEXT NOT NULL,
 seo_title VARCHAR(255) NULL,
 seo_description TEXT NULL,
 translation_status ENUM('missing','draft','review','complete') NOT NULL DEFAULT 'draft',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_content_language(content_id,language_code),
 UNIQUE KEY uniq_language_slug(language_code,slug),
 CONSTRAINT fk_translation_content FOREIGN KEY(content_id) REFERENCES content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO languages(code,name,native_name,is_default,is_enabled) VALUES ('en','English','English',1,1),('no','Norwegian','Norsk',0,1),('lt','Lithuanian','Lietuvių',0,1);
