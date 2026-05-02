-- Migration: ai_video_002_prompt_image.sql
-- Adds prompt_image_url to ai_video_prompts for Runway image-to-video support.
-- Run once against the production database.

ALTER TABLE ai_video_prompts
    ADD COLUMN prompt_image_url VARCHAR(1000) NULL DEFAULT NULL
        COMMENT 'Full public URL of reference image for Runway image-to-video. Null = text-to-video only.'
        AFTER veo_prompt;
