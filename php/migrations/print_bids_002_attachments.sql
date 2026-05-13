-- migrations/print_bids_002_attachments.sql
-- Add attachments JSON column to print_bids table.

ALTER TABLE print_bids ADD COLUMN IF NOT EXISTS attachments TEXT NULL COMMENT 'JSON array of {name, path, type} objects' AFTER notes;
