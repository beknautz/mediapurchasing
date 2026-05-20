-- press_releases_002_client.sql
-- Add client association to press releases + recipient type tracking
-- Safe to re-run: uses IF NOT EXISTS / IF EXISTS throughout

-- ── press_releases table ─────────────────────────────────────────────────────
ALTER TABLE press_releases
    ADD COLUMN IF NOT EXISTS client_id INT NULL DEFAULT NULL AFTER created_by;

-- ── press_release_recipients table ──────────────────────────────────────────
-- Drop FK so we can make vendor_id nullable.
-- IF EXISTS prevents error if this statement already ran.
ALTER TABLE press_release_recipients
    DROP FOREIGN KEY IF EXISTS press_release_recipients_ibfk_2;

ALTER TABLE press_release_recipients
    MODIFY COLUMN vendor_id INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE press_release_recipients
    ADD COLUMN IF NOT EXISTS recipient_type ENUM('vendor','client') NOT NULL DEFAULT 'vendor' AFTER vendor_email;

ALTER TABLE press_release_recipients
    ADD COLUMN IF NOT EXISTS client_id INT NULL DEFAULT NULL AFTER recipient_type;

-- Re-add FK with nullable vendor_id (vendor delete now sets NULL instead of blocking)
ALTER TABLE press_release_recipients
    ADD CONSTRAINT press_release_recipients_ibfk_2
        FOREIGN KEY (vendor_id) REFERENCES vendors (id) ON DELETE SET NULL;
