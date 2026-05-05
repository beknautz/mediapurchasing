-- migrations/vendors_003_coverage_service.sql
-- Add coverage_area and service_options JSON columns to vendors table.
-- Safe to run multiple times (IF NOT EXISTS).

ALTER TABLE vendors
    ADD COLUMN IF NOT EXISTS coverage_area   TEXT NULL AFTER media_category,
    ADD COLUMN IF NOT EXISTS service_options TEXT NULL AFTER coverage_area;
