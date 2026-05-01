-- ============================================================
-- AI Video Studio — Database Migration 001
-- Run once against the mediapurchasing database.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- ai_video_campaigns
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_campaigns` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `client_id`         INT UNSIGNED     NOT NULL DEFAULT 0,
    `campaign_name`     VARCHAR(255)     NOT NULL,
    `objective`         TEXT             NULL,
    `target_audience`   TEXT             NULL,
    `offer`             VARCHAR(500)     NULL,
    `brand_voice`       VARCHAR(100)     NULL,
    `platform`          VARCHAR(100)     NULL,
    `video_length`      INT              NULL COMMENT 'seconds',
    `aspect_ratio`      VARCHAR(10)      NULL,
    `call_to_action`    VARCHAR(255)     NULL,
    `landing_page_url`  VARCHAR(1000)    NULL,
    `notes`             TEXT             NULL,
    `status`            VARCHAR(50)      NOT NULL DEFAULT 'draft',
    `review_token`      VARCHAR(64)      NULL,
    `created_by`        INT UNSIGNED     NOT NULL DEFAULT 0,
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_review_token` (`review_token`),
    KEY `idx_client_id` (`client_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_video_scripts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_scripts` (
    `id`                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `campaign_id`           INT UNSIGNED     NOT NULL,
    `version_number`        INT              NOT NULL DEFAULT 1,
    `script_text`           LONGTEXT         NULL,
    `hook`                  TEXT             NULL,
    `scene_breakdown`       TEXT             NULL,
    `voiceover_text`        TEXT             NULL,
    `on_screen_text`        TEXT             NULL,
    `cta_text`              VARCHAR(500)     NULL,
    `claude_model`          VARCHAR(100)     NULL,
    `claude_input_tokens`   INT              NOT NULL DEFAULT 0,
    `claude_output_tokens`  INT              NOT NULL DEFAULT 0,
    `claude_cost`           DECIMAL(10,6)    NOT NULL DEFAULT 0,
    `created_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    CONSTRAINT `fk_scripts_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `ai_video_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_video_prompts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_prompts` (
    `id`                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `campaign_id`           INT UNSIGNED     NOT NULL,
    `script_id`             INT UNSIGNED     NULL,
    `version_number`        INT              NOT NULL DEFAULT 1,
    `veo_prompt`            TEXT             NULL,
    `negative_prompt`       TEXT             NULL,
    `visual_style`          VARCHAR(255)     NULL,
    `camera_direction`      VARCHAR(255)     NULL,
    `lighting`              VARCHAR(255)     NULL,
    `pacing`                VARCHAR(100)     NULL,
    `aspect_ratio`          VARCHAR(10)      NULL,
    `duration_seconds`      INT              NULL,
    `claude_model`          VARCHAR(100)     NULL,
    `claude_input_tokens`   INT              NOT NULL DEFAULT 0,
    `claude_output_tokens`  INT              NOT NULL DEFAULT 0,
    `claude_cost`           DECIMAL(10,6)    NOT NULL DEFAULT 0,
    `created_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_script_id` (`script_id`),
    CONSTRAINT `fk_prompts_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `ai_video_campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_prompts_script` FOREIGN KEY (`script_id`)
        REFERENCES `ai_video_scripts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_video_jobs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_jobs` (
    `id`                            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `campaign_id`                   INT UNSIGNED     NOT NULL,
    `script_id`                     INT UNSIGNED     NULL,
    `prompt_id`                     INT UNSIGNED     NULL,
    `parent_job_id`                 INT UNSIGNED     NULL,
    `provider`                      VARCHAR(50)      NOT NULL DEFAULT 'veo',
    `provider_job_id`               VARCHAR(500)     NULL,
    `job_status`                    VARCHAR(50)      NOT NULL DEFAULT 'queued',
    `progress_percent`              INT              NOT NULL DEFAULT 0,
    `requested_duration_seconds`    INT              NULL,
    `requested_aspect_ratio`        VARCHAR(10)      NULL,
    `provider_request_json`         LONGTEXT         NULL,
    `provider_response_json`        LONGTEXT         NULL,
    `video_url`                     TEXT             NULL,
    `local_file_path`               TEXT             NULL,
    `thumbnail_url`                 TEXT             NULL,
    `error_message`                 TEXT             NULL,
    `estimated_provider_cost`       DECIMAL(10,4)    NOT NULL DEFAULT 0,
    `actual_provider_cost`          DECIMAL(10,4)    NOT NULL DEFAULT 0,
    `total_ai_cost`                 DECIMAL(10,4)    NOT NULL DEFAULT 0,
    `created_by`                    INT UNSIGNED     NOT NULL DEFAULT 0,
    `created_at`                    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `queued_at`                     DATETIME         NULL,
    `started_at`                    DATETIME         NULL,
    `completed_at`                  DATETIME         NULL,
    `failed_at`                     DATETIME         NULL,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_script_id` (`script_id`),
    KEY `idx_prompt_id` (`prompt_id`),
    KEY `idx_parent_job_id` (`parent_job_id`),
    KEY `idx_job_status` (`job_status`),
    CONSTRAINT `fk_jobs_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `ai_video_campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jobs_script` FOREIGN KEY (`script_id`)
        REFERENCES `ai_video_scripts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_jobs_prompt` FOREIGN KEY (`prompt_id`)
        REFERENCES `ai_video_prompts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_jobs_parent` FOREIGN KEY (`parent_job_id`)
        REFERENCES `ai_video_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_video_reviews
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_reviews` (
    `id`                        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `campaign_id`               INT UNSIGNED     NOT NULL,
    `job_id`                    INT UNSIGNED     NOT NULL,
    `client_id`                 INT UNSIGNED     NOT NULL DEFAULT 0,
    `review_status`             VARCHAR(50)      NOT NULL DEFAULT 'pending',
    `reviewer_name`             VARCHAR(255)     NULL,
    `reviewer_email`            VARCHAR(255)     NULL,
    `comments`                  TEXT             NULL,
    `revision_notes`            TEXT             NULL,
    `approved_at`               DATETIME         NULL,
    `revision_requested_at`     DATETIME         NULL,
    `created_at`                DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_job_id` (`job_id`),
    CONSTRAINT `fk_reviews_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `ai_video_campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_job` FOREIGN KEY (`job_id`)
        REFERENCES `ai_video_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_video_costs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_costs` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `campaign_id`   INT UNSIGNED     NOT NULL,
    `job_id`        INT UNSIGNED     NULL,
    `cost_type`     VARCHAR(100)     NOT NULL,
    `provider`      VARCHAR(100)     NOT NULL,
    `model`         VARCHAR(100)     NULL,
    `units`         DECIMAL(10,4)    NOT NULL DEFAULT 0,
    `unit_cost`     DECIMAL(10,8)    NOT NULL DEFAULT 0,
    `total_cost`    DECIMAL(10,6)    NOT NULL DEFAULT 0,
    `notes`         TEXT             NULL,
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_job_id` (`job_id`),
    CONSTRAINT `fk_costs_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `ai_video_campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_costs_job` FOREIGN KEY (`job_id`)
        REFERENCES `ai_video_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_video_notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_notifications` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `campaign_id`       INT UNSIGNED     NOT NULL,
    `job_id`            INT UNSIGNED     NULL,
    `client_id`         INT UNSIGNED     NOT NULL DEFAULT 0,
    `notification_type` VARCHAR(100)     NOT NULL,
    `recipient_email`   VARCHAR(255)     NOT NULL,
    `subject`           VARCHAR(500)     NULL,
    `message`           TEXT             NULL,
    `sent_status`       VARCHAR(50)      NOT NULL DEFAULT 'pending',
    `sent_at`           DATETIME         NULL,
    `error_message`     TEXT             NULL,
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_job_id` (`job_id`),
    CONSTRAINT `fk_notifs_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `ai_video_campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifs_job` FOREIGN KEY (`job_id`)
        REFERENCES `ai_video_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
