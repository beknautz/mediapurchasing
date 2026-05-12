-- migrations/vendors_007_retag_print_signage.sql
-- Add dedicated "Printing" and "Signage" service_option tags to the print shop
-- and sign shop vendors so they appear in the correct Print Bids vendor columns.
-- These tags are separate from "Print Media" (newspapers) and "Billboards" (ad sales).

-- ── PRINT SHOPS → add "Printing" tag ─────────────────────────────────────────

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Printing'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) = LOWER('The Print Guys Inc.')
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Printing"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Printing'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) IN (LOWER('Abbott''s Printing Inc.'), LOWER('Abbotts Printing Inc'), LOWER('Abbott''s Printing Inc'))
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Printing"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Printing'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) IN (LOWER('Instant Press Inc.'), LOWER('Instant Press Inc'))
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Printing"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Printing'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) = LOWER('Minuteman Press')
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Printing"');

-- ── SIGN SHOPS → add "Signage" tag ───────────────────────────────────────────

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Signage'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) IN (LOWER('Cascade Sign & Fabrication'), LOWER('Cascade Sign and Fabrication'))
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Signage"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Signage'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) = LOWER('Sign Craft')
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Signage"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Signage'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) IN (LOWER('Eagle Signs'), LOWER('Eagle Signs LLC'))
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Signage"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Signage'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) = LOWER('Advanced Digital Imaging')
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Signage"');

UPDATE vendors SET
    service_options = JSON_ARRAY_APPEND(
        COALESCE(NULLIF(service_options,''), '[]'), '$', 'Signage'
    ),
    updated_at = NOW()
WHERE LOWER(TRIM(company_name)) IN (LOWER('Sign Works'), LOWER('SignWorks'))
  AND NOT JSON_CONTAINS(COALESCE(service_options,'[]'), '"Signage"');
