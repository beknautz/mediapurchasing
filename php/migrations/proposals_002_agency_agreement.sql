-- Migration: proposals_002_agency_agreement.sql
-- Adds agency_agreements table and agency branding settings to workflow_settings.
-- Run once against the production database.

-- -----------------------------------------------------------------------
-- Agency Agreements table
-- -----------------------------------------------------------------------
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
    services_json           TEXT          NULL,

    -- Pricing
    deposit_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_due_description VARCHAR(500)  NOT NULL DEFAULT '',
    balance_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance_due_description VARCHAR(500)  NOT NULL DEFAULT '',
    total_amount            DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- Budget breakdown — fully dynamic categories
    -- JSON: [{"label":"TV","rows":[{"name":"KIMA","amount":850}]}, ...]
    budget_json             TEXT          NULL,

    -- Additional terms
    contingency_monthly     DECIMAL(10,2) NULL,
    mileage_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0.60,
    hourly_rate             DECIMAL(10,2) NOT NULL DEFAULT 80.00,
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

-- -----------------------------------------------------------------------
-- Patch existing table: add budget_json if missing (idempotent).
-- Needed when the table was created before the budget redesign.
-- -----------------------------------------------------------------------
ALTER TABLE agency_agreements
    ADD COLUMN IF NOT EXISTS budget_json TEXT NULL
        COMMENT 'JSON: [{"label":"TV","rows":[{"name":"KIMA","amount":850}]}]'
        AFTER total_amount;

-- -----------------------------------------------------------------------
-- Agency branding settings
-- These appear in Admin → Settings under the "Agency Branding" group.
-- Columns: setting_key, setting_value, label, description, setting_group
-- -----------------------------------------------------------------------
INSERT INTO workflow_settings (setting_key, setting_value, label, description, setting_group)
VALUES
    ('agency_name',
     'Enigma, Inc. DBA Enigma Marketing',
     'Agency Name',
     'Full legal name shown on all contracts and documents.',
     'Agency Branding'),

    ('agency_dba',
     'Enigma Marketing',
     'DBA / Trade Name',
     'Doing-business-as name used in short references.',
     'Agency Branding'),

    ('agency_address',
     '3601 W Washington STE 130',
     'Street Address',
     'Agency street address printed on contracts.',
     'Agency Branding'),

    ('agency_city_state_zip',
     'Yakima, WA 98903',
     'City, State, Zip',
     '',
     'Agency Branding'),

    ('agency_phone',
     '509-452-3733',
     'Phone Number',
     '',
     'Agency Branding'),

    ('agency_signer_name',
     'Duane Gordon',
     'Authorized Signer Name',
     'Name printed on contract signature lines.',
     'Agency Branding'),

    ('agency_signer_title',
     'Managing Partner',
     'Authorized Signer Title',
     '',
     'Agency Branding'),

    ('agency_logo_url',
     '',
     'Logo URL',
     'Publicly accessible URL or server path to your logo (e.g. /uploads/logo.png). Shown at the top of printed documents. Leave blank to use text-only header.',
     'Agency Branding')

ON DUPLICATE KEY UPDATE
    label         = VALUES(label),
    description   = VALUES(description),
    setting_group = VALUES(setting_group);
