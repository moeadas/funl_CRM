-- Migration 2026-06-14: Lead UTM tracking + Dynamic Lead Source
-- Adds 5 UTM columns to leads table and a company-scoped lead_sources table.
-- Also converts lead_source from ENUM to VARCHAR(255) to support dynamic
-- values (e.g. "TikTok Q2 Campaign", "Webinar March 2026") that companies
-- add over time. The company_lead_sources table below provides the
-- autocomplete library that grows as new sources are used.

-- 1. Convert lead_source from ENUM to VARCHAR(255) (NULLable)
--    Existing ENUM values are kept as-is, but new values can be inserted.
ALTER TABLE leads
    MODIFY COLUMN lead_source VARCHAR(255) NULL DEFAULT 'Other';

-- 2. Add UTM columns to leads (NULLable for backward compatibility)
ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS utm_source VARCHAR(255) NULL AFTER lead_source,
    ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(255) NULL AFTER utm_source,
    ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(255) NULL AFTER utm_campaign,
    ADD COLUMN IF NOT EXISTS utm_content VARCHAR(500) NULL AFTER utm_medium,
    ADD COLUMN IF NOT EXISTS utm_term VARCHAR(500) NULL AFTER utm_content;

-- 3. Add landing_page + referrer for completeness
ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS landing_page VARCHAR(1000) NULL AFTER utm_term,
    ADD COLUMN IF NOT EXISTS referrer VARCHAR(1000) NULL AFTER landing_page;

-- 4. Indexes for reporting on UTM
ALTER TABLE leads
    ADD INDEX IF NOT EXISTS idx_leads_utm_source (utm_source),
    ADD INDEX IF NOT EXISTS idx_leads_utm_campaign (utm_campaign);

-- 5. Company-scoped lead source library
--    Each row represents a unique source string used by this company.
--    The form autocomplete shows these (ordered by use_count) plus a
--    hard-coded seed list of common channels.
CREATE TABLE IF NOT EXISTS company_lead_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    source_value VARCHAR(255) NOT NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 1,
    first_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_company_source (company_id, source_value),
    KEY idx_company (company_id),
    KEY idx_use_count (company_id, use_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

