-- ============================================
-- Migration: Add industry, company_size, annual_revenue to leads table
-- ============================================

-- MySQL
ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS industry VARCHAR(100) DEFAULT NULL AFTER specialization,
    ADD COLUMN IF NOT EXISTS company_size VARCHAR(50) DEFAULT NULL AFTER industry,
    ADD COLUMN IF NOT EXISTS annual_revenue VARCHAR(50) DEFAULT NULL AFTER company_size;

-- Also rename specialization → industry if specialization exists and industry doesn't
-- (for backward compatibility with older installs)
-- Note: This is a one-way migration. Run carefully.

-- SQLite (for sandbox mode)
-- ALTER TABLE leads ADD COLUMN industry TEXT DEFAULT NULL;
-- ALTER TABLE leads ADD COLUMN company_size TEXT DEFAULT NULL;
-- ALTER TABLE leads ADD COLUMN annual_revenue TEXT DEFAULT NULL;
