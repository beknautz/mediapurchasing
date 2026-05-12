-- migrations/vendors_006_print_signage.sql
-- Insert/update Yakima-area print and signage vendors.
-- Uses UPDATE + conditional INSERT pattern (MariaDB-safe, no ON DUPLICATE KEY).
-- Run once.

START TRANSACTION;

-- ── PRINT VENDORS ─────────────────────────────────────────────────────────────

-- The Print Guys Inc.
UPDATE vendors
    SET contact_name    = 'Sales Team',
        address         = '101 N 3rd Ave, Yakima, WA 98902',
        email           = 'orders@printguys.com',
        phone           = '(509) 453-6369',
        coverage_area   = '["Yakima"]',
        service_options = '["Print Media"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('The Print Guys Inc.');
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'The Print Guys Inc.', 'Sales Team', '101 N 3rd Ave, Yakima, WA 98902', 'orders@printguys.com', '(509) 453-6369', '["Yakima"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('The Print Guys Inc.')
    );

-- Abbott's Printing Inc.
UPDATE vendors
    SET contact_name    = 'Phylisha Sanborn',
        address         = '500 S 2nd Ave, Yakima, WA 98902',
        email           = 'peewee@abbottsprinting.com',
        phone           = '(509) 452-8202',
        coverage_area   = '["Yakima"]',
        service_options = '["Print Media"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) IN (LOWER('Abbott''s Printing Inc.'), LOWER('Abbotts Printing Inc'), LOWER('Abbott''s Printing Inc'));
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Abbott''s Printing Inc.', 'Phylisha Sanborn', '500 S 2nd Ave, Yakima, WA 98902', 'peewee@abbottsprinting.com', '(509) 452-8202', '["Yakima"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) IN (LOWER('Abbott''s Printing Inc.'), LOWER('Abbotts Printing Inc'), LOWER('Abbott''s Printing Inc'))
    );

-- Instant Press Inc.
UPDATE vendors
    SET contact_name    = 'Sales Team',
        address         = '601 W Yakima Ave, Yakima, WA 98902',
        email           = 'instantpressyakima@hotmail.com',
        phone           = '(509) 457-6195',
        coverage_area   = '["Yakima"]',
        service_options = '["Print Media"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) IN (LOWER('Instant Press Inc.'), LOWER('Instant Press Inc'));
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Instant Press Inc.', 'Sales Team', '601 W Yakima Ave, Yakima, WA 98902', 'instantpressyakima@hotmail.com', '(509) 457-6195', '["Yakima"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) IN (LOWER('Instant Press Inc.'), LOWER('Instant Press Inc'))
    );

-- Minuteman Press
UPDATE vendors
    SET contact_name    = 'Sales Team',
        address         = '104 S 5th Ave, Yakima, WA 98902',
        email           = 'yakima@minutemanpress.com',
        phone           = '(509) 452-6144',
        coverage_area   = '["Yakima"]',
        service_options = '["Print Media"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Minuteman Press');
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Minuteman Press', 'Sales Team', '104 S 5th Ave, Yakima, WA 98902', 'yakima@minutemanpress.com', '(509) 452-6144', '["Yakima"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Minuteman Press')
    );


-- ── SIGNAGE VENDORS ───────────────────────────────────────────────────────────

-- Cascade Sign & Fabrication
UPDATE vendors
    SET contact_name    = 'Norm',
        address         = 'Yakima, WA 98902',
        email           = 'normh.cascade@gmail.com',
        phone           = '(509) 972-8099',
        coverage_area   = '["Yakima"]',
        service_options = '["Billboards"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) IN (LOWER('Cascade Sign & Fabrication'), LOWER('Cascade Sign and Fabrication'));
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Cascade Sign & Fabrication', 'Norm', 'Yakima, WA 98902', 'normh.cascade@gmail.com', '(509) 972-8099', '["Yakima"]', '["Billboards"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) IN (LOWER('Cascade Sign & Fabrication'), LOWER('Cascade Sign and Fabrication'))
    );

-- Sign Craft  (primary: Brad Harris; secondary: Jacob Mack — same address/phone)
UPDATE vendors
    SET contact_name    = 'Brad Harris',
        address         = '225 S 2nd Ave, Yakima, WA 98902',
        email           = 'brad@yakimasigncraft.com',
        billing_email   = 'jacob@yakimasigncraft.com',
        phone           = '(509) 248-1129',
        coverage_area   = '["Yakima"]',
        service_options = '["Billboards"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Sign Craft');
INSERT INTO vendors (company_name, contact_name, address, email, billing_email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Sign Craft', 'Brad Harris', '225 S 2nd Ave, Yakima, WA 98902', 'brad@yakimasigncraft.com', 'jacob@yakimasigncraft.com', '(509) 248-1129', '["Yakima"]', '["Billboards"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Sign Craft')
    );

-- Eagle Signs
UPDATE vendors
    SET contact_name    = 'Sales Team',
        address         = '1511 S Keys Rd, Yakima, WA 98901',
        email           = 'sales@eaglesignsllc.com',
        phone           = '(509) 453-5511',
        coverage_area   = '["Yakima"]',
        service_options = '["Billboards"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) IN (LOWER('Eagle Signs'), LOWER('Eagle Signs LLC'));
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Eagle Signs', 'Sales Team', '1511 S Keys Rd, Yakima, WA 98901', 'sales@eaglesignsllc.com', '(509) 453-5511', '["Yakima"]', '["Billboards"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) IN (LOWER('Eagle Signs'), LOWER('Eagle Signs LLC'))
    );

-- Advanced Digital Imaging
UPDATE vendors
    SET contact_name    = 'Sales Team',
        address         = '3402 W Washington Ave, Yakima, WA 98903',
        email           = 'sales@eaglesignsllc.com',
        phone           = '(509) 452-4455',
        coverage_area   = '["Yakima"]',
        service_options = '["Billboards", "Print Media"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Advanced Digital Imaging');
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Advanced Digital Imaging', 'Sales Team', '3402 W Washington Ave, Yakima, WA 98903', 'sales@eaglesignsllc.com', '(509) 452-4455', '["Yakima"]', '["Billboards", "Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Advanced Digital Imaging')
    );

-- Sign Works
UPDATE vendors
    SET contact_name    = 'Kim Thompson',
        address         = '915 W Yakima Ave, Yakima, WA 98902',
        email           = 'kim@signworksyakima.com',
        phone           = '(509) 248-8235',
        coverage_area   = '["Yakima"]',
        service_options = '["Billboards"]',
        is_active       = 1,
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) IN (LOWER('Sign Works'), LOWER('SignWorks'));
INSERT INTO vendors (company_name, contact_name, address, email, phone, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Sign Works', 'Kim Thompson', '915 W Yakima Ave, Yakima, WA 98902', 'kim@signworksyakima.com', '(509) 248-8235', '["Yakima"]', '["Billboards"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) IN (LOWER('Sign Works'), LOWER('SignWorks'))
    );

COMMIT;
