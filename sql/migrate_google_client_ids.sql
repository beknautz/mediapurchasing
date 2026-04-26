-- Store each client's Google Ads Customer ID for per-client API calls.
ALTER TABLE clients
    ADD COLUMN google_ads_customer_id VARCHAR(20) NULL AFTER company_name;
