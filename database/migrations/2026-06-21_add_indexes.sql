-- Migration: Add missing database indexes
-- Date: 2026-06-21
-- Fixes: Performance audit found leads table had no company_id index (full table scans)

CREATE INDEX idx_leads_company ON leads(company_id);
CREATE INDEX idx_proposals_company ON proposals(company_id);
CREATE INDEX idx_proposals_status ON proposals(status);
CREATE INDEX idx_contacts_created_by ON contacts(created_by);
CREATE INDEX idx_contacts_assigned ON contacts(assigned_to);
CREATE INDEX idx_email_list_members_list ON email_list_members(list_id);