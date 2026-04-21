-- ============================================================
-- migrate_php.sql
-- Adds the tables and columns that the PHP service layer expects
-- but that are absent from the original schema.
-- Safe to run multiple times (IF NOT EXISTS / IF NOT EXISTS guards).
-- ============================================================

-- ============================================================
-- 1. media_buys — add columns the PHP services reference
-- ============================================================
ALTER TABLE media_buys
    ADD COLUMN IF NOT EXISTS total_cost    DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER final_cost,
    ADD COLUMN IF NOT EXISTS agreed_cost   DECIMAL(12,2) NULL                   AFTER total_cost,
    ADD COLUMN IF NOT EXISTS notes         TEXT          NULL                   AFTER agreed_cost;

-- ============================================================
-- 2. media_buy_items — add columns the PHP services reference
-- ============================================================
ALTER TABLE media_buy_items
    ADD COLUMN IF NOT EXISTS media_type  VARCHAR(60)   NULL          AFTER description,
    ADD COLUMN IF NOT EXISTS start_date  DATE          NULL          AFTER media_type,
    ADD COLUMN IF NOT EXISTS end_date    DATE          NULL          AFTER start_date,
    ADD COLUMN IF NOT EXISTS quantity    DECIMAL(10,2) NOT NULL DEFAULT 1 AFTER end_date,
    ADD COLUMN IF NOT EXISTS notes       TEXT          NULL          AFTER quantity;

-- ============================================================
-- 3. clients — add address-detail columns
-- ============================================================
ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS city    VARCHAR(100) NULL AFTER address,
    ADD COLUMN IF NOT EXISTS state   VARCHAR(100) NULL AFTER city,
    ADD COLUMN IF NOT EXISTS zip     VARCHAR(20)  NULL AFTER state,
    ADD COLUMN IF NOT EXISTS country VARCHAR(80)  NULL AFTER zip;

-- ============================================================
-- 4. vendors — add address-detail columns
-- ============================================================
ALTER TABLE vendors
    ADD COLUMN IF NOT EXISTS city    VARCHAR(100) NULL AFTER address,
    ADD COLUMN IF NOT EXISTS state   VARCHAR(100) NULL AFTER city,
    ADD COLUMN IF NOT EXISTS zip     VARCHAR(20)  NULL AFTER state,
    ADD COLUMN IF NOT EXISTS country VARCHAR(80)  NULL AFTER zip;

-- ============================================================
-- 5. media_buy_approvals (PHP name for the approvals workflow)
-- ============================================================
CREATE TABLE IF NOT EXISTS media_buy_approvals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_buy_id    INT UNSIGNED  NOT NULL,
    client_id       INT UNSIGNED  NULL,
    token           VARCHAR(100)  NOT NULL UNIQUE,
    status          ENUM('pending','approved','rejected','revision_requested','expired') NOT NULL DEFAULT 'pending',
    response_notes  TEXT          NULL,
    responded_at    DATETIME      NULL,
    responder_ip    VARCHAR(45)   NULL,
    expires_at      DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id)    REFERENCES clients(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. media_buy_negotiations
-- ============================================================
CREATE TABLE IF NOT EXISTS media_buy_negotiations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_buy_id      INT UNSIGNED  NOT NULL,
    user_id           INT UNSIGNED  NULL,
    proposed_cost     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes             TEXT          NULL,
    negotiation_type  VARCHAR(60)   NOT NULL DEFAULT 'counter_offer',
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. billing_queue  (flat invoice queue used by BillingService)
-- ============================================================
CREATE TABLE IF NOT EXISTS billing_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id       INT UNSIGNED  NULL,
    media_buy_id    INT UNSIGNED  NULL,
    invoice_number  VARCHAR(80)   NULL,
    invoice_date    DATE          NULL,
    due_date        DATE          NULL,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          ENUM('pending','processing','paid','disputed','cancelled') NOT NULL DEFAULT 'pending',
    source          VARCHAR(60)   NOT NULL DEFAULT 'manual',
    vendor_email    VARCHAR(180)  NULL,
    assigned_to     INT UNSIGNED  NULL,
    notes           TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)    REFERENCES vendors(id)    ON DELETE SET NULL,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. communication_logs  (email + SMS send/receive log)
-- ============================================================
CREATE TABLE IF NOT EXISTS communication_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comm_type       VARCHAR(30)   NOT NULL,              -- email, email_inbound, sms
    to_email        VARCHAR(180)  NULL,
    to_name         VARCHAR(120)  NULL,
    from_email      VARCHAR(180)  NULL,
    from_name       VARCHAR(120)  NULL,
    subject         VARCHAR(255)  NULL,
    body_html       LONGTEXT      NULL,
    body_text       LONGTEXT      NULL,
    status          VARCHAR(30)   NOT NULL DEFAULT 'sent',
    error_message   TEXT          NULL,
    external_id     VARCHAR(100)  NULL,                  -- SendGrid message-id or Twilio SID
    media_buy_id    INT UNSIGNED  NULL,
    approval_id     INT UNSIGNED  NULL,
    bill_id         INT UNSIGNED  NULL,
    sent_by         INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_buy_id) REFERENCES media_buys(id)      ON DELETE SET NULL,
    FOREIGN KEY (sent_by)      REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. media_buy_approvals — add revision_requested to status ENUM
--    (ALTER is idempotent if the value is already present)
-- ============================================================
ALTER TABLE media_buy_approvals
    MODIFY COLUMN status ENUM('pending','approved','rejected','revision_requested','expired') NOT NULL DEFAULT 'pending';

-- ============================================================
-- 10. password_resets  (used by forgot-password flow)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token_hash),
    INDEX idx_user  (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. communication_logs — add attachments column for inbound files
-- ============================================================
ALTER TABLE communication_logs
    ADD COLUMN IF NOT EXISTS attachments TEXT NULL AFTER body_text;

-- ============================================================
-- 12. vendors — add media_category dropdown column
-- ============================================================
ALTER TABLE vendors
    ADD COLUMN IF NOT EXISTS media_category VARCHAR(100) NULL AFTER media_types;

-- ============================================================
-- 13. campaigns — top-level campaign entity
-- ============================================================
CREATE TABLE IF NOT EXISTS campaigns (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255)  NOT NULL,
    client_id    INT UNSIGNED  NULL,
    language     ENUM('english','spanish','both') NOT NULL DEFAULT 'both',
    status       ENUM('draft','rfp_sent','responses_in','proposal_ready','sent_to_client','approved','active','completed','cancelled') NOT NULL DEFAULT 'draft',
    total_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    flight_start DATE          NULL,
    flight_end   DATE          NULL,
    market       VARCHAR(100)  NULL,
    notes        TEXT          NULL,
    created_by   INT UNSIGNED  NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)  REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 14. campaign_channels — one row per vendor/media channel in a campaign
-- ============================================================
CREATE TABLE IF NOT EXISTS campaign_channels (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id      INT UNSIGNED  NOT NULL,
    vendor_id        INT UNSIGNED  NULL,
    media_category   VARCHAR(100)  NOT NULL,
    budget_allocated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status           ENUM('pending','rfp_sent','response_received','no_response','approved','rejected') NOT NULL DEFAULT 'pending',
    rfp_sent_at      DATETIME      NULL,
    rfp_log_id       INT UNSIGNED  NULL,
    notes            TEXT          NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id)   REFERENCES vendors(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 15. media_buys — add campaign/channel FK columns
-- ============================================================
ALTER TABLE media_buys
    ADD COLUMN IF NOT EXISTS campaign_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS channel_id  INT UNSIGNED NULL AFTER campaign_id;

-- ============================================================
-- 16. communication_logs — add campaign/channel tracking columns
-- ============================================================
ALTER TABLE communication_logs
    ADD COLUMN IF NOT EXISTS campaign_id INT UNSIGNED NULL AFTER media_buy_id,
    ADD COLUMN IF NOT EXISTS channel_id  INT UNSIGNED NULL AFTER campaign_id;
