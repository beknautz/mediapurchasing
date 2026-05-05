-- migrations/vendors_004_import_from_xlsx.sql
-- Auto-generated from Media_List_single_sheet_one_page.xlsx
-- Updates coverage_area + service_options for matched vendors;
-- inserts as new vendors where no name match exists.
-- Run once. Townsquare Media handled separately (two markets).

START TRANSACTION;

-- KIMA
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Live Broadcasts"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('KIMA');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'KIMA', '["Yakima/Tri-Cities"]', '["TV Spots", "Live Broadcasts"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('KIMA')
    );

-- KAPP AppleValley News
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Digital", "Social Media", "Geofencing"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('KAPP AppleValley News');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'KAPP AppleValley News', '["Yakima/Tri-Cities"]', '["TV Spots", "Digital", "Social Media", "Geofencing"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('KAPP AppleValley News')
    );

-- KNDO/KNDO
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Live Broadcasts", "Digital", "Web Takeover", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('KNDO/KNDO');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'KNDO/KNDO', '["Yakima/Tri-Cities"]', '["TV Spots", "Live Broadcasts", "Digital", "Web Takeover", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('KNDO/KNDO')
    );

-- KOX
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Live Broadcasts"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('KOX');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'KOX', '["Yakima/Tri-Cities"]', '["TV Spots", "Live Broadcasts"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('KOX')
    );

-- Univision
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Live Broadcasts"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Univision');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Univision', '["Yakima/Tri-Cities"]', '["TV Spots", "Live Broadcasts"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Univision')
    );

-- Telemundo
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Live Broadcasts", "Digital", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Telemundo');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Telemundo', '["Yakima/Tri-Cities"]', '["TV Spots", "Live Broadcasts", "Digital", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Telemundo')
    );

-- Hispanavision
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["TV Spots", "Live Broadcasts", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Hispanavision');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Hispanavision', '["Yakima/Tri-Cities"]', '["TV Spots", "Live Broadcasts", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Hispanavision')
    );

-- Townsquare Media (Yakima/Tri-Cities)
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Web Takeover", "Social Media", "Geofencing", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Townsquare Media')
      AND JSON_CONTAINS(IFNULL(coverage_area,'[]'), JSON_QUOTE('Yakima/Tri-Cities'));
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Townsquare Media', '["Yakima/Tri-Cities"]', '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Web Takeover", "Social Media", "Geofencing", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors
        WHERE LOWER(TRIM(company_name)) = LOWER('Townsquare Media')
          AND JSON_CONTAINS(IFNULL(coverage_area,'[]'), JSON_QUOTE('Yakima/Tri-Cities'))
    );

-- Stephens Media Group
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Social Media", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Stephens Media Group');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Stephens Media Group', '["Yakima/Tri-Cities"]', '["Radio Spots", "Live Broadcasts", "Remote", "Social Media", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Stephens Media Group')
    );

-- KXLE
UPDATE vendors
    SET coverage_area   = '["Ellensburg"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Social Media", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('KXLE');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'KXLE', '["Ellensburg"]', '["Radio Spots", "Live Broadcasts", "Social Media", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('KXLE')
    );

-- Cherry Creek
UPDATE vendors
    SET coverage_area   = '["Tri-Cities/Wenatchee"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Cherry Creek');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Cherry Creek', '["Tri-Cities/Wenatchee"]', '["Radio Spots", "Live Broadcasts", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Cherry Creek')
    );

-- Townsquare Media (Wenatchee)
UPDATE vendors
    SET coverage_area   = '["Wenatchee"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Digital", "Web Takeover", "Social Media", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Townsquare Media')
      AND JSON_CONTAINS(IFNULL(coverage_area,'[]'), JSON_QUOTE('Wenatchee'));
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Townsquare Media', '["Wenatchee"]', '["Radio Spots", "Live Broadcasts", "Digital", "Web Takeover", "Social Media", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors
        WHERE LOWER(TRIM(company_name)) = LOWER('Townsquare Media')
          AND JSON_CONTAINS(IFNULL(coverage_area,'[]'), JSON_QUOTE('Wenatchee'))
    );

-- Bustos Media
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Social Media", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Bustos Media');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Bustos Media', '["Yakima/Tri-Cities"]', '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Social Media", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Bustos Media')
    );

-- Radio KDNA
UPDATE vendors
    SET coverage_area   = '["Yakima/Tri-Cities"]',
        service_options = '["Radio Spots", "Live Broadcasts", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Radio KDNA');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Radio KDNA', '["Yakima/Tri-Cities"]', '["Radio Spots", "Live Broadcasts", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Radio KDNA')
    );

-- Sunnyside Daily Record
UPDATE vendors
    SET coverage_area   = '["Sunnyside"]',
        service_options = '["Print Media", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Sunnyside Daily Record');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Sunnyside Daily Record', '["Sunnyside"]', '["Print Media", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Sunnyside Daily Record')
    );

-- Ellensburg Daily Record
UPDATE vendors
    SET coverage_area   = '["Ellensburg"]',
        service_options = '["Print Media", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Ellensburg Daily Record');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Ellensburg Daily Record', '["Ellensburg"]', '["Print Media", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Ellensburg Daily Record')
    );

-- Toppenish Review
UPDATE vendors
    SET coverage_area   = '["Toppenish"]',
        service_options = '["Print Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Toppenish Review');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Toppenish Review', '["Toppenish"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Toppenish Review')
    );

-- Selah Journal
UPDATE vendors
    SET coverage_area   = '["Selah"]',
        service_options = '["Print Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Selah Journal');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Selah Journal', '["Selah"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Selah Journal')
    );

-- Yakima Herald
UPDATE vendors
    SET coverage_area   = '["Yakima"]',
        service_options = '["Print Media", "Digital", "Web Takeover", "Social Media", "Email Blast", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Yakima Herald');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Yakima Herald', '["Yakima"]', '["Print Media", "Digital", "Web Takeover", "Social Media", "Email Blast", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Yakima Herald')
    );

-- El sol de
UPDATE vendors
    SET coverage_area   = '["Yakima"]',
        service_options = '["Print Media", "Digital", "Web Takeover", "Social Media", "Email Blast", "Ticket Giveaway"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('El sol de');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'El sol de', '["Yakima"]', '["Print Media", "Digital", "Web Takeover", "Social Media", "Email Blast", "Ticket Giveaway"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('El sol de')
    );

-- Grandview Herald
UPDATE vendors
    SET coverage_area   = '["Grandview"]',
        service_options = '["Print Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Grandview Herald');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Grandview Herald', '["Grandview"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Grandview Herald')
    );

-- Prosser Record Bulletin
UPDATE vendors
    SET coverage_area   = '["Prosser"]',
        service_options = '["Print Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Prosser Record Bulletin');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Prosser Record Bulletin', '["Prosser"]', '["Print Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Prosser Record Bulletin')
    );

-- Business Times
UPDATE vendors
    SET coverage_area   = '["Yakima"]',
        service_options = '["Print Media", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Business Times');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Business Times', '["Yakima"]', '["Print Media", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Business Times')
    );

-- Yakima Valley Tourism
UPDATE vendors
    SET coverage_area   = '["Yakima"]',
        service_options = '["Digital", "Social Media"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Yakima Valley Tourism');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Yakima Valley Tourism', '["Yakima"]', '["Digital", "Social Media"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Yakima Valley Tourism')
    );

-- Outfront Billboards
UPDATE vendors
    SET coverage_area   = '["Yakima/Lower Valley"]',
        service_options = '["Billboards"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Outfront Billboards');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Outfront Billboards', '["Yakima/Lower Valley"]', '["Billboards"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Outfront Billboards')
    );

-- Lamar
UPDATE vendors
    SET coverage_area   = '["Yakima"]',
        service_options = '["Billboards"]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Lamar');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Lamar', '["Yakima"]', '["Billboards"]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Lamar')
    );

-- Yakima Theatres
UPDATE vendors
    SET coverage_area   = '["Yakima"]',
        service_options = '[]',
        updated_at      = NOW()
    WHERE LOWER(TRIM(company_name)) = LOWER('Yakima Theatres');
INSERT INTO vendors (company_name, coverage_area, service_options, is_active, created_at, updated_at)
    SELECT 'Yakima Theatres', '["Yakima"]', '[]', 1, NOW(), NOW()
    WHERE NOT EXISTS (
        SELECT 1 FROM vendors WHERE LOWER(TRIM(company_name)) = LOWER('Yakima Theatres')
    );

COMMIT;