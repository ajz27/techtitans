-- Create URL scans table
CREATE TABLE IF NOT EXISTS url_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scanned_url VARCHAR(2048) NOT NULL,
    scan_id VARCHAR(255),
    permalink VARCHAR(2048),
    scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scan_date TIMESTAMP NULL,
    total_engines INT DEFAULT 0,
    positive_detections INT DEFAULT 0,
    response_code INT,
    verbose_msg TEXT,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_scanned_url (scanned_url(255)),
    INDEX idx_scan_date (scan_date)
);
