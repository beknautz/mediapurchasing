-- media_contacts_001.sql
-- Creates media_outlets + media_contacts tables
-- Adds media_contact_id to press_release_recipients + extends recipient_type ENUM

CREATE TABLE IF NOT EXISTS media_outlets (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(255) NOT NULL,
    market       VARCHAR(100) NOT NULL DEFAULT '',
    category     VARCHAR(100) NOT NULL DEFAULT '',
    address      TEXT,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT NOW(),
    updated_at   DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id),
    KEY idx_market (market),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media_contacts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    outlet_id    INT UNSIGNED NOT NULL,
    name         VARCHAR(255) NOT NULL DEFAULT '',
    email        VARCHAR(255) NOT NULL,
    phone        VARCHAR(255) NOT NULL DEFAULT '',
    is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    notes        TEXT,
    created_at   DATETIME     NOT NULL DEFAULT NOW(),
    updated_at   DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email),
    KEY idx_outlet (outlet_id),
    KEY idx_active (is_active),
    CONSTRAINT fk_mc_outlet FOREIGN KEY (outlet_id) REFERENCES media_outlets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extend recipient_type to include media_contact
ALTER TABLE press_release_recipients
    MODIFY COLUMN recipient_type ENUM('vendor','client','media_contact') NOT NULL DEFAULT 'vendor';

-- Add media_contact_id column
ALTER TABLE press_release_recipients
    ADD COLUMN IF NOT EXISTS media_contact_id INT UNSIGNED NULL DEFAULT NULL AFTER client_id;
