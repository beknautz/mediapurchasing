-- Migration: ai_video_003_audio_character.sql
-- Adds tables for Runway TTS/SFX audio jobs and Act Two character performance jobs.
-- Run once against the production database.

-- -----------------------------------------------------------------------
-- Audio jobs (TTS voiceover + sound effects)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_video_audio_jobs (
    id                      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id             INT          NOT NULL,
    script_id               INT          NULL,

    job_type                ENUM('tts','sfx') NOT NULL DEFAULT 'tts'
                                COMMENT 'tts = text-to-speech voiceover, sfx = sound effect',
    provider                VARCHAR(50)  NOT NULL DEFAULT 'runway',
    provider_job_id         VARCHAR(255) NULL     COMMENT 'Runway task ID',
    job_status              ENUM('queued','processing','completed','failed')
                                NOT NULL DEFAULT 'queued',

    -- TTS-specific
    voice_preset            VARCHAR(100) NULL     COMMENT 'Runway voice preset name e.g. Maya',
    prompt_text             TEXT         NULL     COMMENT 'Script text sent to TTS',

    -- SFX-specific
    sfx_duration            FLOAT        NULL     COMMENT 'Requested duration in seconds (0.5–30)',
    sfx_loop                TINYINT(1)   NOT NULL DEFAULT 0,

    -- Output
    audio_url               VARCHAR(1000) NULL    COMMENT 'Public URL (local after download)',
    local_file_path         VARCHAR(1000) NULL,

    -- Cost
    estimated_cost          DECIMAL(10,6) NOT NULL DEFAULT 0,
    actual_cost             DECIMAL(10,6) NULL,

    -- Tracking
    created_by              INT          NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    queued_at               DATETIME     NULL,
    started_at              DATETIME     NULL,
    completed_at            DATETIME     NULL,
    failed_at               DATETIME     NULL,
    error_message           TEXT         NULL,

    -- Raw API payloads for debugging
    provider_request_json   MEDIUMTEXT   NULL,
    provider_response_json  MEDIUMTEXT   NULL,

    INDEX idx_audio_campaign (campaign_id),
    INDEX idx_audio_status   (job_status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------
-- Character performance jobs (Runway Act Two)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_video_character_jobs (
    id                      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id             INT          NOT NULL,
    video_job_id            INT          NULL     COMMENT 'Source ai_video_jobs.id (base video)',
    audio_job_id            INT          NULL     COMMENT 'Related ai_video_audio_jobs.id',

    provider                VARCHAR(50)  NOT NULL DEFAULT 'runway',
    model                   VARCHAR(100) NOT NULL DEFAULT 'act_two',
    provider_job_id         VARCHAR(255) NULL     COMMENT 'Runway task ID',
    job_status              ENUM('queued','processing','completed','failed')
                                NOT NULL DEFAULT 'queued',

    -- Inputs
    character_url           VARCHAR(1000) NULL    COMMENT 'URL of character image or video',
    character_type          ENUM('image','video') NOT NULL DEFAULT 'image',
    reference_video_url     VARCHAR(1000) NULL    COMMENT 'Driving performance video URL',

    -- Parameters
    expression_intensity    TINYINT      NOT NULL DEFAULT 3 COMMENT '1 (subtle) – 5 (exaggerated)',
    body_control            TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Include non-facial movements',
    ratio                   VARCHAR(20)  NOT NULL DEFAULT '720:1280',

    -- Output
    output_video_url        VARCHAR(1000) NULL,
    local_file_path         VARCHAR(1000) NULL,

    -- Cost
    estimated_cost          DECIMAL(10,6) NOT NULL DEFAULT 0,
    actual_cost             DECIMAL(10,6) NULL,

    -- Tracking
    created_by              INT          NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    queued_at               DATETIME     NULL,
    started_at              DATETIME     NULL,
    completed_at            DATETIME     NULL,
    failed_at               DATETIME     NULL,
    error_message           TEXT         NULL,

    -- Raw API payloads for debugging
    provider_request_json   MEDIUMTEXT   NULL,
    provider_response_json  MEDIUMTEXT   NULL,

    INDEX idx_char_campaign (campaign_id),
    INDEX idx_char_status   (job_status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
