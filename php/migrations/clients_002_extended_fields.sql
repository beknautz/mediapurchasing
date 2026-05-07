-- migrations/clients_002_extended_fields.sql
-- Add secondary contact fields and full billing section to clients table.
-- Each ALTER is separate for MariaDB compatibility with IF NOT EXISTS.

ALTER TABLE clients ADD COLUMN IF NOT EXISTS secondary_phone VARCHAR(50)  NULL AFTER phone;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS secondary_email VARCHAR(255) NULL AFTER email;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS billing_same_as TINYINT(1)  NOT NULL DEFAULT 0 AFTER address;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS billing_company VARCHAR(255) NULL AFTER billing_same_as;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS billing_contact VARCHAR(150) NULL AFTER billing_company;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS billing_address TEXT         NULL AFTER billing_contact;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS billing_email   VARCHAR(255) NULL AFTER billing_address;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS billing_phone   VARCHAR(50)  NULL AFTER billing_email;
