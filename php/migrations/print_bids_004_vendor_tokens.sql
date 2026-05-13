-- migrations/print_bids_004_vendor_tokens.sql
-- Add vendor_reply_tokens column for secure vendor submission links.

ALTER TABLE print_bids
    ADD COLUMN IF NOT EXISTS vendor_reply_tokens TEXT NULL
    COMMENT 'JSON array of {token, vendor_id, vendor_name, created_at, used}'
    AFTER vendor_replies;
