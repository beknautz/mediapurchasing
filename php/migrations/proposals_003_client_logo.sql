-- proposals_003_client_logo.sql
-- Add logo_url column to clients table

ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS logo_url VARCHAR(500) NULL DEFAULT NULL;
