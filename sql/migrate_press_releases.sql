-- Press Releases feature
-- Run once on the production database

CREATE TABLE IF NOT EXISTS press_releases (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    subject          VARCHAR(500)    NOT NULL,
    body_html        MEDIUMTEXT,
    body_text        MEDIUMTEXT,
    attachments      JSON,
    status           ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    recipient_count  INT             NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED    NULL,
    sent_at          DATETIME        NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status     (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS press_release_recipients (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    press_release_id INT UNSIGNED    NOT NULL,
    vendor_id        INT UNSIGNED    NOT NULL,
    vendor_email     VARCHAR(255)    NOT NULL DEFAULT '',
    status           ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    error_message    TEXT,
    log_id           INT UNSIGNED    NULL,
    sent_at          DATETIME        NULL,
    INDEX idx_pr_id  (press_release_id),
    INDEX idx_status (status),
    FOREIGN KEY (press_release_id) REFERENCES press_releases(id)  ON DELETE CASCADE,
    FOREIGN KEY (vendor_id)        REFERENCES vendors(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
