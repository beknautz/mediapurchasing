-- Add Google Business Profile location to clients
ALTER TABLE clients
    ADD COLUMN google_business_location VARCHAR(200) NULL COMMENT 'GBP location resource name e.g. locations/1234567890' AFTER google_ads_customer_id;
