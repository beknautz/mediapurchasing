-- migrations/vendors_005_consolidate_duplicates.sql
-- Applies vendor consolidation from vendors_consolidated_2026-05-05.xlsx
-- Merges: keep lowest original ID, absorb duplicates.
-- Splits: update original ID + repurpose stub ID for new market row.

START TRANSACTION;

-- ================================================================
-- MERGES
-- ================================================================

-- Bustos Media (id=17)
UPDATE vendors SET
    company_name    = 'Bustos Media',
    contact_name    = 'Ruben Prieto',
    email           = 'rprieto@bustosmedia.com',
    phone           = 'Cell: 509.945.7123',
    billing_email   = NULL,
    coverage_area   = '["Yakima/Tri-Cities"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Social Media", "Ticket Giveaway"]',
    address         = '415 N 2nd st, Yakima WA 98901',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 17;
DELETE FROM vendors WHERE id IN (33);  -- Bustos Media duplicate

-- Columbia Gorge News (id=72)
UPDATE vendors SET
    company_name    = 'Columbia Gorge News',
    contact_name    = 'Rachel Harrison; Kim Horton',
    email           = 'rachelh@gorgenews.com; kimh@gorgenews.com',
    phone           = NULL,
    billing_email   = NULL,
    coverage_area   = NULL,
    service_options = NULL,
    address         = NULL,
    notes           = notes,
    updated_at      = NOW()
WHERE id = 72;
DELETE FROM vendors WHERE id IN (73);  -- Columbia Gorge News stub (no name)

-- El Sol de Yakima (id=24)
UPDATE vendors SET
    company_name    = 'El Sol de Yakima',
    contact_name    = 'Gloria Ibañez',
    email           = 'gibanez@yakimaherald.com',
    phone           = '509.249.6184',
    billing_email   = NULL,
    coverage_area   = '["Yakima"]',
    service_options = '["Print Media", "Digital", "Web Takeover", "Social Media", "Email Blast", "Ticket Giveaway"]',
    address         = NULL,
    notes           = notes,
    updated_at      = NOW()
WHERE id = 24;
DELETE FROM vendors WHERE id IN (93);  -- El sol de (coverage stub)

-- Hispanavision - Yakima/Tri-Cities (id=10)
UPDATE vendors SET
    company_name    = 'Hispanavision - Yakima/Tri-Cities',
    contact_name    = 'Orson Bevins',
    email           = 'orson.bevins@hispanavisiontv.com',
    phone           = '509.452.8817',
    billing_email   = NULL,
    coverage_area   = '["Yakima/Tri-Cities"]',
    service_options = '["TV Spots", "Live Broadcasts", "Social Media"]',
    address         = '715 W Yakima Ave, Yakima, WA 98902',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 10;
DELETE FROM vendors WHERE id IN (84);  -- Hispanavision coverage stub

-- KIMA - Yakima/Tri-Cities (id=3)
UPDATE vendors SET
    company_name    = 'KIMA - Yakima/Tri-Cities',
    contact_name    = 'Steve Crow',
    email           = 'scrow@kimatv.com',
    phone           = 'Office: 509.895.8016 Cell: 509',
    billing_email   = NULL,
    coverage_area   = '["Yakima/Tri-Cities"]',
    service_options = '["TV Spots", "Live Broadcasts"]',
    address         = '2801 Terrace Heights Dr. Yakima, WA 98901',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 3;
DELETE FROM vendors WHERE id IN (80);  -- KIMA coverage stub

-- KNDO/KNDU - Yakima/Tri-Cities (id=7)
UPDATE vendors SET
    company_name    = 'KNDO/KNDU - Yakima/Tri-Cities',
    contact_name    = 'Sam Renner',
    email           = 'sam.renner@nonstoplocal.com',
    phone           = 'Office: 509.225.2310 Cell: 509',
    billing_email   = NULL,
    coverage_area   = '["Yakima/Tri-Cities"]',
    service_options = '["TV Spots", "Live Broadcasts", "Digital", "Web Takeover", "Social Media"]',
    address         = '216 W. Yakima Ave, Yakima Wa 98902',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 7;
DELETE FROM vendors WHERE id IN (81);  -- KNDO/KNDO typo stub

-- KXLE Radio - Ellensburg (id=14)
UPDATE vendors SET
    company_name    = 'KXLE Radio - Ellensburg',
    contact_name    = 'Claudia Self',
    email           = 'cpself51@gmail.com',
    phone           = NULL,
    billing_email   = NULL,
    coverage_area   = '["Ellensburg"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Social Media", "Ticket Giveaway"]',
    address         = '1311 Vantage Highway. Ellensburg 98926',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 14;
DELETE FROM vendors WHERE id IN (87);  -- KXLE coverage stub

-- Sunnyside Daily Record - Lower Valley (id=20)
UPDATE vendors SET
    company_name    = 'Sunnyside Daily Record - Lower Valley',
    contact_name    = 'Ileana Martinez',
    email           = 'imartinez@sunnysidesun.com',
    phone           = '509.837.4500 ext.115',
    billing_email   = NULL,
    coverage_area   = '["Sunnyside / Lower Valley"]',
    service_options = '["Print Media", "Social Media"]',
    address         = 'Po Box 878 Sunnyside, 98944',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 20;
DELETE FROM vendors WHERE id IN (90);  -- Sunnyside coverage stub

-- Stephens Media Group - Tri-Cities (id=13)
UPDATE vendors SET
    company_name    = 'Stephens Media Group - Tri-Cities',
    contact_name    = 'Dan Manella',
    email           = 'dan.manella@smgnational.com',
    phone           = 'Office 509.783.0783',
    billing_email   = NULL,
    coverage_area   = '["Tri-Cities"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Social Media", "Ticket Giveaway"]',
    address         = '4304 W 24th Ave Suite 200',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 13;

-- Stephens Media Group - Yakima (id=12)
UPDATE vendors SET
    company_name    = 'Stephens Media Group - Yakima',
    contact_name    = 'Laurie Hammermeister',
    email           = 'laurie.hammermeister@smgnational.com',
    phone           = 'Office: 509.248.2900  Cell: 50',
    billing_email   = NULL,
    coverage_area   = '["Yakima"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Social Media", "Ticket Giveaway"]',
    address         = '17 N 3rd St. Suite 103 Yakima, WA  98901',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 12;
DELETE FROM vendors WHERE id IN (86);  -- Stephens shared stub (served both Yakima + Tri-Cities rows)

-- ================================================================
-- SPLITS
-- ================================================================

-- Grandview Herald (id=25)
UPDATE vendors SET
    company_name    = 'Grandview Herald',
    contact_name    = 'Rebecca Fink',
    email           = 'salesrep@therecordbulletin.com',
    phone           = NULL,
    billing_email   = NULL,
    coverage_area   = '["Grandview"]',
    service_options = '["Print Media"]',
    address         = 'P.O. Box 750, Prosser, WA 99350',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 25;
-- Prosser Record Bulletin (id=95)
UPDATE vendors SET
    company_name    = 'Prosser Record Bulletin',
    contact_name    = 'Rebecca Fink',
    email           = 'salesrep@therecordbulletin.com',
    phone           = NULL,
    billing_email   = NULL,
    coverage_area   = '["Prosser"]',
    service_options = '["Print Media"]',
    address         = 'P.O. Box 750, Prosser, WA 99350',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 95;
DELETE FROM vendors WHERE id IN (94);  -- Grandview stub (absorbed into id=25)

-- Selah Journal (id=22)
UPDATE vendors SET
    company_name    = 'Selah Journal',
    contact_name    = 'Adam Smith',
    email           = 'asmith3421@hotmail.com',
    phone           = 'Office: 509.823.4580',
    billing_email   = NULL,
    coverage_area   = '["Selah"]',
    service_options = '["Print Media"]',
    address         = NULL,
    notes           = notes,
    updated_at      = NOW()
WHERE id = 22;
-- Toppenish Review (id=91)
UPDATE vendors SET
    company_name    = 'Toppenish Review',
    contact_name    = 'Adam Smith',
    email           = 'asmith3421@hotmail.com',
    phone           = 'Office: 509.823.4580',
    billing_email   = NULL,
    coverage_area   = '["Toppenish"]',
    service_options = '["Print Media"]',
    address         = NULL,
    notes           = notes,
    updated_at      = NOW()
WHERE id = 91;
DELETE FROM vendors WHERE id IN (92);  -- Selah stub (absorbed into id=22)

-- Townsquare Media - Yakima (id=11)
UPDATE vendors SET
    company_name    = 'Townsquare Media - Yakima',
    contact_name    = 'Nicole Cook',
    email           = 'nicole.cook@townsquaremedia.com',
    phone           = 'Cell: 509.823.3115 Office: 509',
    billing_email   = NULL,
    coverage_area   = '["Yakima"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Web Takeover", "Social Media", "Geofencing", "Ticket Giveaway"]',
    address         = '4010 Summitview Ave, Yakima, WA 98908',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 11;
-- Townsquare Media - Tri-Cities (id=85)
UPDATE vendors SET
    company_name    = 'Townsquare Media - Tri-Cities',
    contact_name    = 'Nicole Cook',
    email           = 'nicole.cook@townsquaremedia.com',
    phone           = 'Cell: 509.823.3115 Office: 509',
    billing_email   = NULL,
    coverage_area   = '["Tri-Cities"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Remote", "Digital", "Web Takeover", "Social Media", "Geofencing", "Ticket Giveaway"]',
    address         = '4010 Summitview Ave, Yakima, WA 98908',
    notes           = notes,
    updated_at      = NOW()
WHERE id = 85;
-- Townsquare Media - Wenatchee (id=15)
UPDATE vendors SET
    company_name    = 'Townsquare Media - Wenatchee',
    contact_name    = 'Michaella Collins',
    email           = 'michaella.collins@townsquaremedia.com',
    phone           = 'Direct: 509.888.8428 Cell: 509',
    billing_email   = NULL,
    coverage_area   = '["Wenatchee"]',
    service_options = '["Radio Spots", "Live Broadcasts", "Digital", "Web Takeover", "Social Media", "Ticket Giveaway"]',
    address         = NULL,
    notes           = notes,
    updated_at      = NOW()
WHERE id = 15;
DELETE FROM vendors WHERE id IN (16, 89);  -- Townsquare shared stubs (Yakima/TC and KPQ)

COMMIT;