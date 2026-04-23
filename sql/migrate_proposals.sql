-- Proposals feature migration
-- Run once on the production database

CREATE TABLE IF NOT EXISTS proposals (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(500)   NOT NULL,
    client_id    INT UNSIGNED   NULL,
    status       ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    intro_text   MEDIUMTEXT     NULL,
    notes        MEDIUMTEXT     NULL,
    valid_until  DATE           NULL,
    total_amount DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    created_by   INT UNSIGNED   NULL,
    sent_at      DATETIME       NULL,
    accepted_at  DATETIME       NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status     (status),
    INDEX idx_client_id  (client_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_items (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    proposal_id  INT UNSIGNED   NOT NULL,
    sort_order   SMALLINT       NOT NULL DEFAULT 0,
    description  TEXT           NOT NULL,
    quantity     DECIMAL(10,2)  NOT NULL DEFAULT 1.00,
    unit_price   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total_price  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_proposal_id (proposal_id),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_templates (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255)   NOT NULL,
    intro_text   MEDIUMTEXT     NULL,
    notes        MEDIUMTEXT     NULL,
    created_by   INT UNSIGNED   NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_template_items (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    template_id  INT UNSIGNED   NOT NULL,
    sort_order   SMALLINT       NOT NULL DEFAULT 0,
    description  TEXT           NOT NULL,
    quantity     DECIMAL(10,2)  NOT NULL DEFAULT 1.00,
    unit_price   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    INDEX idx_template_id (template_id),
    FOREIGN KEY (template_id) REFERENCES proposal_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
