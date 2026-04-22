-- Budget Planner feature migration
-- Run once on the production database
-- NOTE: ALTER TABLE statements will fail if columns already exist — that is safe to ignore.

-- Add demographics and media_kit columns to vendors table
ALTER TABLE vendors
    ADD COLUMN demographics TEXT NULL AFTER notes,
    ADD COLUMN media_kit    TEXT NULL AFTER demographics;

-- Create budget_proposals table
CREATE TABLE IF NOT EXISTS budget_proposals (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(255)  NOT NULL,
    event_description  TEXT          NULL,
    event_demographics TEXT          NULL,
    budget_good        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    budget_better      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    budget_best        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    allocation_json    LONGTEXT      NULL,
    ai_rationale       TEXT          NULL,
    status             ENUM('draft','sent','approved','converted') NOT NULL DEFAULT 'draft',
    approved_tier      VARCHAR(10)   NULL,
    client_id          INT UNSIGNED  NULL,
    campaign_id        INT UNSIGNED  NULL,
    created_by         INT UNSIGNED  NOT NULL,
    sent_at            DATETIME      NULL,
    approved_at        DATETIME      NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status     (status),
    INDEX idx_client_id  (client_id),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Anthropic API key setting (safe to run multiple times due to INSERT IGNORE)
INSERT IGNORE INTO workflow_settings
    (setting_key, setting_value, setting_group, label, description, updated_at)
VALUES
    ('anthropic_api_key', '', 'AI', 'Anthropic API Key',
     'Secret key for Claude AI used by the AI Budget Planner. Get yours at console.anthropic.com.',
     NOW());
