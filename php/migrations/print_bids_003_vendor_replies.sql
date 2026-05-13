-- migrations/print_bids_003_vendor_replies.sql
-- Add 'replied' status and vendor_replies column to print_bids.

ALTER TABLE print_bids
    MODIFY COLUMN status ENUM('draft','sent','replied','approved','rejected') NOT NULL DEFAULT 'draft';

ALTER TABLE print_bids
    ADD COLUMN IF NOT EXISTS vendor_replies TEXT NULL
    COMMENT 'JSON array of {vendor_id, vendor_name, replied_at, notes, attachments:[{name,path,type}]}'
    AFTER attachments;
