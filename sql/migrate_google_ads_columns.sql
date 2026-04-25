-- Add Google API resource columns to existing ad automation tables.
-- Run this after migrate_ad_automation.sql

ALTER TABLE crm_ad_schedules
    ADD COLUMN google_campaign_resource VARCHAR(500) NULL AFTER notes,
    ADD COLUMN google_ad_resource       VARCHAR(500) NULL AFTER google_campaign_resource,
    ADD COLUMN published_at             DATETIME     NULL AFTER google_ad_resource;

ALTER TABLE crm_social_posts
    ADD COLUMN external_post_id VARCHAR(500) NULL AFTER published_at;
