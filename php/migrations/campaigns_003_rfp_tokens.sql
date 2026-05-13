-- migrations/campaigns_003_rfp_tokens.sql
-- Add vendor reply token and replies storage to campaign_channels.

ALTER TABLE campaign_channels
    ADD COLUMN IF NOT EXISTS rfp_reply_token VARCHAR(40) NULL
        COMMENT 'Secure token for vendor self-service proposal submission'
        AFTER notes,
    ADD COLUMN IF NOT EXISTS vendor_replies TEXT NULL
        COMMENT 'JSON array of {replied_at, notes, attachments:[{name,path,type}], source}'
        AFTER rfp_reply_token;
