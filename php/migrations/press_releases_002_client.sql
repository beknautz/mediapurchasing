-- press_releases_002_client.sql
-- Add client association to press releases + recipient type tracking

ALTER TABLE press_releases
    ADD COLUMN client_id INT NULL DEFAULT NULL AFTER created_by;

-- Drop FK on vendor_id so we can make it nullable, then re-add it
ALTER TABLE press_release_recipients
    DROP FOREIGN KEY press_release_recipients_ibfk_2;

ALTER TABLE press_release_recipients
    MODIFY COLUMN vendor_id INT NULL DEFAULT NULL,
    ADD COLUMN recipient_type ENUM('vendor','client') NOT NULL DEFAULT 'vendor' AFTER vendor_email,
    ADD COLUMN client_id INT NULL DEFAULT NULL AFTER recipient_type;

ALTER TABLE press_release_recipients
    ADD CONSTRAINT press_release_recipients_ibfk_2
        FOREIGN KEY (vendor_id) REFERENCES vendors (id) ON DELETE SET NULL;
