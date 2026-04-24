-- Annual Ad Schedules
-- Manages per-client annual publication ad placement schedules.
-- Mirrors the HCC Annual Publication Schedules spreadsheet structure.

CREATE TABLE IF NOT EXISTS annual_ad_plans (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED   NOT NULL,
    year        SMALLINT UNSIGNED NOT NULL,
    notes       TEXT           NULL,
    created_by  INT UNSIGNED   NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY  uq_client_year (client_id, year),
    FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annual_ad_placements (
    id                         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    plan_id                    INT UNSIGNED   NOT NULL,
    client_id                  INT UNSIGNED   NOT NULL,

    -- Publication info
    publication                VARCHAR(255)   NOT NULL,
    contact_name               VARCHAR(255)   NULL,
    editorial                  VARCHAR(255)   NULL,     -- edition / topic name
    ad_number                  VARCHAR(50)    NULL,     -- e.g. CP424

    -- Dates
    run_date                   DATE           NULL,     -- publication date (triggers 7-day alert)
    artwork_deadline           DATE           NULL,
    client_approval_deadline   DATE           NULL,     -- "Ad to HC for approval"

    -- Ad specs
    ad_size                    VARCHAR(255)   NULL,
    circulation                INT            NULL,
    num_ads                    TINYINT UNSIGNED NOT NULL DEFAULT 1,

    -- Financials
    cost_to_agency             DECIMAL(10,2)  NULL,    -- what agency pays vendor
    cost_to_client             DECIMAL(10,2)  NULL,    -- what client is charged
    markup_pct                 DECIMAL(5,2)   NULL,

    -- Workflow status (mirrors spreadsheet checkboxes)
    is_reserved                TINYINT(1)     NOT NULL DEFAULT 0,
    is_designed                TINYINT(1)     NOT NULL DEFAULT 0,
    is_sent_to_client          TINYINT(1)     NOT NULL DEFAULT 0,
    is_sent_to_publication     TINYINT(1)     NOT NULL DEFAULT 0,

    -- Billing (the "billed" checkoff)
    design_invoiced            TINYINT(1)     NOT NULL DEFAULT 0,
    placement_invoiced         TINYINT(1)     NOT NULL DEFAULT 0,
    design_invoiced_at         DATETIME       NULL,
    placement_invoiced_at      DATETIME       NULL,

    -- Misc
    notes                      TEXT           NULL,
    sort_order                 SMALLINT       NOT NULL DEFAULT 0,
    created_at                 DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_plan       (plan_id),
    INDEX idx_client     (client_id),
    INDEX idx_run_date   (run_date),
    INDEX idx_unbilled   (run_date, placement_invoiced),

    FOREIGN KEY (plan_id)   REFERENCES annual_ad_plans(id)  ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
