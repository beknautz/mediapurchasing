-- Ad Automation Module
-- Manages social posts, AI-generated ad copy, campaigns, and ad schedules
-- for Meta/Facebook/Instagram and Google Ads.

CREATE TABLE IF NOT EXISTS crm_marketing_campaigns (
    id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    campaign_name   VARCHAR(255)   NOT NULL,
    campaign_type   ENUM('awareness','traffic','leads','sales','engagement') NOT NULL DEFAULT 'awareness',
    platform        ENUM('meta','google','both','other') NOT NULL DEFAULT 'meta',
    objective       VARCHAR(255)   NULL,
    budget_daily    DECIMAL(10,2)  NULL,
    budget_total    DECIMAL(10,2)  NULL,
    start_date      DATE           NULL,
    end_date        DATE           NULL,
    status          ENUM('draft','active','paused','completed','archived') NOT NULL DEFAULT 'draft',
    notes           TEXT           NULL,
    created_by      INT UNSIGNED   NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME       NULL,
    INDEX idx_status     (status),
    INDEX idx_platform   (platform),
    INDEX idx_start_date (start_date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_social_posts (
    id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    campaign_id     INT UNSIGNED   NULL,
    platform        ENUM('facebook','instagram','google_business','linkedin') NOT NULL,
    title           VARCHAR(255)   NOT NULL,
    caption         TEXT           NULL,
    image_url       VARCHAR(500)   NULL,
    video_url       VARCHAR(500)   NULL,
    status          ENUM('draft','scheduled','published','failed','cancelled') NOT NULL DEFAULT 'draft',
    scheduled_at    DATETIME       NULL,
    published_at    DATETIME       NULL,
    notes           TEXT           NULL,
    created_by      INT UNSIGNED   NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME       NULL,
    INDEX idx_campaign  (campaign_id),
    INDEX idx_platform  (platform),
    INDEX idx_status    (status),
    INDEX idx_scheduled (scheduled_at),
    FOREIGN KEY (campaign_id) REFERENCES crm_marketing_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_ad_copy (
    id                  INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    campaign_id         INT UNSIGNED   NULL,
    platform            ENUM('meta','google') NOT NULL,
    business_service    VARCHAR(255)   NULL,
    offer               VARCHAR(255)   NULL,
    target_audience     VARCHAR(255)   NULL,
    location            VARCHAR(255)   NULL,
    tone                ENUM('professional','friendly','urgent','inspirational','humorous') NULL,
    objective           ENUM('awareness','traffic','leads','sales','engagement') NULL,
    -- Meta fields
    headline            VARCHAR(255)   NULL,
    primary_text        TEXT           NULL,
    description         TEXT           NULL,
    call_to_action      VARCHAR(100)   NULL,
    -- Google fields (JSON arrays)
    google_headlines    JSON           NULL,
    google_descriptions JSON           NULL,
    suggested_keywords  JSON           NULL,
    suggested_audience  TEXT           NULL,
    -- Status
    status              ENUM('draft','approved','rejected') NOT NULL DEFAULT 'draft',
    ai_generated        TINYINT(1)     NOT NULL DEFAULT 1,
    created_by          INT UNSIGNED   NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campaign  (campaign_id),
    INDEX idx_platform  (platform),
    INDEX idx_status    (status),
    FOREIGN KEY (campaign_id) REFERENCES crm_marketing_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_ad_schedules (
    id               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    campaign_id      INT UNSIGNED   NULL,
    ad_copy_id       INT UNSIGNED   NULL,
    platform         ENUM('meta','google') NOT NULL,
    ad_name          VARCHAR(255)   NOT NULL,
    start_datetime   DATETIME       NULL,
    end_datetime     DATETIME       NULL,
    daily_budget     DECIMAL(10,2)  NULL,
    target_location  VARCHAR(255)   NULL,
    target_audience  VARCHAR(255)   NULL,
    status           ENUM('draft','ready','scheduled','active','paused','completed','failed') NOT NULL DEFAULT 'draft',
    notes            TEXT           NULL,
    created_by       INT UNSIGNED   NULL,
    created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME       NULL,
    INDEX idx_campaign      (campaign_id),
    INDEX idx_ad_copy       (ad_copy_id),
    INDEX idx_platform      (platform),
    INDEX idx_status        (status),
    INDEX idx_start         (start_datetime),
    FOREIGN KEY (campaign_id) REFERENCES crm_marketing_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (ad_copy_id)  REFERENCES crm_ad_copy(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_marketing_activity_log (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    entity_type  VARCHAR(50)    NOT NULL,
    entity_id    INT UNSIGNED   NOT NULL,
    action       VARCHAR(100)   NOT NULL,
    detail       TEXT           NULL,
    created_by   INT UNSIGNED   NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
