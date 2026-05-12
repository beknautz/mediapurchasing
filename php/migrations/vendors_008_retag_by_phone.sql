-- migrations/vendors_008_retag_by_phone.sql
-- Retag print shops and signage shops using phone number as the match key
-- (more reliable than company name string matching).
-- Appends "Printing" or "Signage" only if not already present.
-- Run this instead of (or after) vendors_007 if 007 returned zero rows.

-- ── PRINT SHOPS → tag "Printing" ─────────────────────────────────────────────

-- The Print Guys Inc. — (509) 453-6369
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Printing"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Printing')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5094536369%'
      AND (service_options NOT LIKE '%Printing%' OR service_options IS NULL);

-- Abbott's Printing Inc. — (509) 452-8202
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Printing"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Printing')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5094528202%'
      AND (service_options NOT LIKE '%Printing%' OR service_options IS NULL);

-- Instant Press Inc. — (509) 457-6195
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Printing"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Printing')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5094576195%'
      AND (service_options NOT LIKE '%Printing%' OR service_options IS NULL);

-- Minuteman Press — (509) 452-6144
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Printing"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Printing')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5094526144%'
      AND (service_options NOT LIKE '%Printing%' OR service_options IS NULL);


-- ── SIGNAGE SHOPS → tag "Signage" ────────────────────────────────────────────

-- Cascade Sign & Fabrication — (509) 972-8099
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Signage"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Signage')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5099728099%'
      AND (service_options NOT LIKE '%Signage%' OR service_options IS NULL);

-- Sign Craft — (509) 248-1129
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Signage"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Signage')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5092481129%'
      AND (service_options NOT LIKE '%Signage%' OR service_options IS NULL);

-- Eagle Signs — (509) 453-5511
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Signage"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Signage')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5094535511%'
      AND (service_options NOT LIKE '%Signage%' OR service_options IS NULL);

-- Advanced Digital Imaging — (509) 452-4455
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Signage"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Signage')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5094524455%'
      AND (service_options NOT LIKE '%Signage%' OR service_options IS NULL);

-- Sign Works — (509) 248-8235
UPDATE vendors
    SET service_options = IF(
            service_options IS NULL OR service_options = '' OR service_options = '[]',
            '["Signage"]',
            JSON_ARRAY_APPEND(service_options, '$', 'Signage')
        ),
        updated_at = NOW()
    WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE '%5092488235%'
      AND (service_options NOT LIKE '%Signage%' OR service_options IS NULL);


-- ── Verify — run this SELECT after to confirm all 9 rows were tagged ──────────
-- SELECT id, company_name, phone, service_options FROM vendors
--  WHERE service_options LIKE '%Printing%' OR service_options LIKE '%Signage%'
-- ORDER BY company_name;
