-- Migration: proposals_002_agency_agreement.sql
-- Adds agency_agreements table for the fast-fill Agency Agreement document type.
-- Run once against the production database.

CREATE TABLE IF NOT EXISTS agency_agreements (
    id                      INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title                   VARCHAR(255)  NOT NULL DEFAULT '',
    status                  ENUM('draft','sent','accepted','rejected')
                                          NOT NULL DEFAULT 'draft',

    -- Client
    client_id               INT           NULL,
    client_name             VARCHAR(255)  NOT NULL DEFAULT '',
    client_address          VARCHAR(500)  NOT NULL DEFAULT '',
    client_city_state_zip   VARCHAR(255)  NOT NULL DEFAULT '',
    client_phone            VARCHAR(100)  NOT NULL DEFAULT '',
    client_representative   VARCHAR(255)  NOT NULL DEFAULT '',
    client_title            VARCHAR(255)  NOT NULL DEFAULT '',

    -- Contract dates
    contract_start          DATE          NULL,
    contract_end            DATE          NULL,

    -- Marketing services (JSON array of strings)
    -- e.g. ["Graphic Design","TV/Radio Production","Social Media Creative(s)","Media Buying"]
    services_json           TEXT          NULL,

    -- Pricing
    deposit_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_due_description VARCHAR(500)  NOT NULL DEFAULT '',
    balance_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance_due_description VARCHAR(500)  NOT NULL DEFAULT '',
    total_amount            DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- Budget breakdown — JSON arrays of {"name":"KIMA","amount":850}
    budget_tv_json          TEXT          NULL,
    budget_radio_json       TEXT          NULL,
    budget_newspaper_json   TEXT          NULL,
    budget_social_json      TEXT          NULL,

    -- Additional line items (contingency, mileage note, etc.)
    contingency_monthly     DECIMAL(10,2) NULL,
    mileage_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.60,
    hourly_rate             DECIMAL(10,2) NOT NULL DEFAULT 80.00,

    -- Free-text notes appended before terms
    additional_notes        TEXT          NULL,

    -- Tracking
    created_by              INT           NULL,
    created_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    sent_at                 DATETIME      NULL,
    accepted_at             DATETIME      NULL,

    INDEX idx_aa_status    (status),
    INDEX idx_aa_client    (client_id),
    INDEX idx_aa_created   (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
