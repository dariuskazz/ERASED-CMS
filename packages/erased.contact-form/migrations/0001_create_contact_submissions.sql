CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX contact_submissions_created_at (created_at),
    INDEX contact_submissions_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
