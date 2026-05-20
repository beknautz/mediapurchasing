-- press_releases_002_client.sql
-- Add client association to press releases + recipient type tracking

ALTER TABLE press_releases
    ADD COLUMN client_id INT NULL DEFAULT NULL AFTER created_by;

ALTER TABLE press_release_recipients
    MODIFY COLUMN vendor_id INT NULL DEFAULT NULL,
    ADD COLUMN recipient_type ENUM('vendor','client') NOT NULL DEFAULT 'vendor' AFTER vendor_email,
    ADD COLUMN client_id INT NULL DEFAULT NULL AFTER recipient_type;
