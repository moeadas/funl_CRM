-- ============================================
-- Migration: Add indexes for performance
-- ============================================

-- MySQL
ALTER TABLE leads ADD INDEX idx_email (email);
ALTER TABLE leads ADD INDEX idx_phone (phone);
ALTER TABLE leads ADD INDEX idx_mobile (mobile);
ALTER TABLE leads ADD INDEX idx_company_id (company_id);
ALTER TABLE leads ADD INDEX idx_assigned_to (assigned_to);
ALTER TABLE leads ADD INDEX idx_lead_status (lead_status);
ALTER TABLE leads ADD INDEX idx_created_at (created_at);
ALTER TABLE leads ADD INDEX idx_updated_at (updated_at);

-- For webhook dedup
ALTER TABLE leads ADD INDEX idx_email_company (email, company_id);
ALTER TABLE leads ADD INDEX idx_phone_company (phone, company_id);
