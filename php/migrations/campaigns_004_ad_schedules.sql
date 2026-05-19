-- campaigns_004_ad_schedules.sql
-- Ad schedule line items parsed from vendor proposals
-- Production schedule items derived from ad schedule

CREATE TABLE IF NOT EXISTS campaign_ad_schedules (
    id              INT            AUTO_INCREMENT PRIMARY KEY,
    campaign_id     INT            NOT NULL,
    vendor_id       INT            NOT NULL DEFAULT 0,
    vendor_name     VARCHAR(255)   NOT NULL DEFAULT '',
    media_category  VARCHAR(120)   NOT NULL DEFAULT '',
    placement       VARCHAR(255)   NOT NULL DEFAULT '',
    unit_type       VARCHAR(120)   NOT NULL DEFAULT '',
    flight_start    DATE           NULL,
    flight_end      DATE           NULL,
    quantity        INT            NULL,
    unit_rate       DECIMAL(10,2)  NULL,
    total_cost      DECIMAL(10,2)  NULL,
    notes           TEXT           NULL,
    source_file     VARCHAR(500)   NOT NULL DEFAULT '',
    parsed_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sort_order      INT            NOT NULL DEFAULT 0,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cas_campaign (campaign_id),
    INDEX idx_cas_vendor   (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_production_items (
    id                  INT            AUTO_INCREMENT PRIMARY KEY,
    campaign_id         INT            NOT NULL,
    vendor_id           INT            NOT NULL DEFAULT 0,
    vendor_name         VARCHAR(255)   NOT NULL DEFAULT '',
    media_type          VARCHAR(120)   NOT NULL DEFAULT '',
    ad_name             VARCHAR(255)   NOT NULL DEFAULT '',
    unit_specs          VARCHAR(500)   NOT NULL DEFAULT '',
    material_due_date   DATE           NULL,
    status              ENUM('pending','in_progress','client_approved','delivered') NOT NULL DEFAULT 'pending',
    notes               TEXT           NULL,
    sort_order          INT            NOT NULL DEFAULT 0,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cpi_campaign (campaign_id),
    INDEX idx_cpi_vendor   (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
