-- ============================================
-- White Label CRM - SQLite Sandbox Schema (SaaS Ready)
-- ============================================

-- Table: companies (tenant isolation)
CREATE TABLE IF NOT EXISTS "companies" (
  "company_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_name" TEXT NOT NULL,
  "company_slug" TEXT NOT NULL UNIQUE,
  "email" TEXT NOT NULL,
  "phone" TEXT DEFAULT NULL,
  "website" TEXT DEFAULT NULL,
  "logo" TEXT DEFAULT NULL,
  "favicon" TEXT DEFAULT NULL,
  "address" TEXT DEFAULT NULL,
  "timezone" TEXT DEFAULT 'UTC',
  "date_format" TEXT DEFAULT 'Y-m-d',
  "status" TEXT NOT NULL DEFAULT 'active' CHECK("status" IN ('active','suspended','cancelled','trial')),
  "trial_ends_at" DATETIME DEFAULT NULL,
  "subscription_status" TEXT DEFAULT 'trial' CHECK("subscription_status" IN ('trial','active','past_due','cancelled','suspended')),
  "stripe_customer_id" TEXT DEFAULT NULL,
  "stripe_subscription_id" TEXT DEFAULT NULL,
  "plan_id" TEXT DEFAULT NULL CHECK("plan_id" IN ('single','team','enterprise')),
  "plan_name" TEXT DEFAULT NULL,
  "plan_user_limit" INTEGER DEFAULT 1,
  "plan_price_monthly" DECIMAL(10,2) DEFAULT 0,
  "extra_user_price" DECIMAL(10,2) DEFAULT 0,
  "billing_cycle" TEXT DEFAULT 'monthly' CHECK("billing_cycle" IN ('monthly','yearly')),
  "current_period_start" DATETIME DEFAULT NULL,
  "current_period_end" DATETIME DEFAULT NULL,
  "cancel_at_period_end" INTEGER DEFAULT 0,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: plans (subscription packages)
CREATE TABLE IF NOT EXISTS "plans" (
  "plan_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "plan_key" TEXT NOT NULL UNIQUE CHECK("plan_key" IN ('single','team','enterprise')),
  "plan_name" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "user_limit" INTEGER NOT NULL DEFAULT 1,
  "monthly_price" DECIMAL(10,2) NOT NULL DEFAULT 0,
  "yearly_price" DECIMAL(10,2) NOT NULL DEFAULT 0,
  "extra_user_price" DECIMAL(10,2) NOT NULL DEFAULT 0,
  "features_json" TEXT DEFAULT NULL,
  "is_active" INTEGER DEFAULT 1,
  "sort_order" INTEGER DEFAULT 0,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default plans
INSERT OR IGNORE INTO "plans" ("plan_key", "plan_name", "description", "user_limit", "monthly_price", "yearly_price", "extra_user_price", "sort_order") VALUES
('single', 'Single User', 'Perfect for solo entrepreneurs and freelancers', 1, 10.00, 100.00, 0, 1),
('team', 'Team', 'For growing teams with up to 5 users', 5, 40.00, 400.00, 8.00, 2),
('enterprise', 'Enterprise', 'For larger teams with up to 15 users', 15, 90.00, 900.00, 6.00, 3);

-- Table: users
CREATE TABLE IF NOT EXISTS "users" (
  "user_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "username" TEXT NOT NULL UNIQUE,
  "email" TEXT NOT NULL UNIQUE,
  "password_hash" TEXT NOT NULL,
  "full_name" TEXT NOT NULL,
  "role" TEXT NOT NULL DEFAULT 'Sales Rep' CHECK("role" IN ('Admin','Sales Manager','Sales Rep','Viewer')),
  "phone" TEXT DEFAULT NULL,
  "whatsapp_number" TEXT DEFAULT NULL,
  "wa_notify_enabled" INTEGER NOT NULL DEFAULT 0,
  "avatar" TEXT DEFAULT NULL,
  "email_verified_at" DATETIME DEFAULT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "last_login" DATETIME DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'Active' CHECK("status" IN ('Active','Inactive')),
  "is_super_admin" INTEGER DEFAULT 0,
  FOREIGN KEY ("company_id") REFERENCES "companies"("company_id") ON DELETE CASCADE
);

-- Table: leads
CREATE TABLE IF NOT EXISTS "leads" (
  "lead_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "lead_type" TEXT NOT NULL DEFAULT 'Business' CHECK("lead_type" IN ('Business','Individual','Partner','Reseller','Other')),
  "company_name" TEXT NOT NULL,
  "contact_person" TEXT DEFAULT NULL,
  "title_position" TEXT DEFAULT NULL,
  "region" TEXT NOT NULL CHECK("region" IN ('North America','Europe','Middle East','Asia-Pacific','Latin America','Africa','Other')),
  "country" TEXT NOT NULL,
  "city" TEXT DEFAULT NULL,
  "address" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "mobile" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "website" TEXT DEFAULT NULL,
  "facebook_url" TEXT DEFAULT NULL,
  "instagram_url" TEXT DEFAULT NULL,
  "linkedin_url" TEXT DEFAULT NULL,
  "twitter_url" TEXT DEFAULT NULL,
  "youtube_url" TEXT DEFAULT NULL,
  "specialization" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "lead_status" TEXT NOT NULL DEFAULT 'New Lead' CHECK("lead_status" IN ('New Lead','Contacted','Interested','Not Interested','Schedule Call','Call Scheduled','Demo Scheduled','Proposal Sent','Negotiation','Won','Lost','On Hold')),
  "lead_source" TEXT DEFAULT 'Other' CHECK("lead_source" IN ('Website','Facebook','Instagram','Google Ads','LinkedIn','Referral','Cold Outreach','Event','Import','Other')),
  "priority" TEXT DEFAULT 'Medium' CHECK("priority" IN ('Low','Medium','High','Urgent')),
  "assigned_to" INTEGER DEFAULT NULL,
  "created_by" INTEGER NOT NULL DEFAULT 1,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "custom_field_data" TEXT DEFAULT NULL,
  FOREIGN KEY ("company_id") REFERENCES "companies"("company_id") ON DELETE CASCADE
);

-- Table: interactions
CREATE TABLE IF NOT EXISTS "interactions" (
  "interaction_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "lead_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "interaction_type" TEXT NOT NULL CHECK("interaction_type" IN ('Call','Email','Meeting','Demo','Follow-up','Note','WhatsApp','SMS')),
  "interaction_date" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "subject" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "outcome" TEXT DEFAULT NULL CHECK("outcome" IN ('Positive','Neutral','Negative','No Response')),
  "follow_up_date" DATE DEFAULT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: documents (lead attachments)
CREATE TABLE IF NOT EXISTS "documents" (
  "document_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER NOT NULL,
  "document_type" TEXT DEFAULT 'Other' CHECK("document_type" IN ('Proposal','Contract','Brochure','Test Results','Presentation','Other')),
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "file_path" TEXT NOT NULL,
  "file_size" INTEGER NOT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: company_documents (Document Library - Admin managed)
CREATE TABLE IF NOT EXISTS "company_documents" (
  "document_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "uploaded_by" INTEGER NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "file_name" TEXT NOT NULL,
  "file_path" TEXT NOT NULL,
  "file_size" INTEGER NOT NULL,
  "file_type" TEXT DEFAULT NULL,
  "category" TEXT DEFAULT 'general' CHECK("category" IN ('general','sales','marketing','legal','training','other')),
  "is_public" INTEGER DEFAULT 1,
  "download_count" INTEGER DEFAULT 0,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY ("company_id") REFERENCES "companies"("company_id") ON DELETE CASCADE,
  FOREIGN KEY ("uploaded_by") REFERENCES "users"("user_id") ON DELETE CASCADE
);

-- Table: activity_log
CREATE TABLE IF NOT EXISTS "activity_log" (
  "log_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER NOT NULL,
  "action" TEXT NOT NULL,
  "entity_type" TEXT NOT NULL CHECK("entity_type" IN ('Lead','User','Document','Interaction','System')),
  "entity_id" INTEGER DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "ip_address" TEXT DEFAULT NULL,
  "user_agent" TEXT DEFAULT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: settings
CREATE TABLE IF NOT EXISTS "settings" (
  "setting_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "setting_key" TEXT NOT NULL,
  "setting_value" TEXT NOT NULL,
  "setting_type" TEXT NOT NULL DEFAULT 'text',
  "updated_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE("company_id", "setting_key")
);

-- Table: custom_fields
CREATE TABLE IF NOT EXISTS "custom_fields" (
  "field_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "field_name" TEXT NOT NULL,
  "field_label" TEXT NOT NULL,
  "field_type" TEXT NOT NULL DEFAULT 'text' CHECK("field_type" IN ('text','number','select','textarea','url','email','tel','date','checkbox')),
  "field_options" TEXT DEFAULT NULL,
  "is_required" INTEGER NOT NULL DEFAULT 0,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE("company_id", "field_name")
);

-- Table: lead_custom_values
CREATE TABLE IF NOT EXISTS "lead_custom_values" (
  "value_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "lead_id" INTEGER NOT NULL,
  "field_id" INTEGER NOT NULL,
  "field_value" TEXT DEFAULT NULL,
  UNIQUE("lead_id", "field_id"),
  FOREIGN KEY ("lead_id") REFERENCES "leads"("lead_id") ON DELETE CASCADE,
  FOREIGN KEY ("field_id") REFERENCES "custom_fields"("field_id") ON DELETE CASCADE
);

-- Table: email_verifications
CREATE TABLE IF NOT EXISTS "email_verifications" (
  "verification_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "email" TEXT NOT NULL,
  "token" TEXT NOT NULL UNIQUE,
  "expires_at" DATETIME NOT NULL,
  "verified_at" DATETIME DEFAULT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY ("user_id") REFERENCES "users"("user_id") ON DELETE CASCADE
);

-- Table: password_resets
CREATE TABLE IF NOT EXISTS "password_resets" (
  "reset_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "email" TEXT NOT NULL,
  "token" TEXT NOT NULL UNIQUE,
  "expires_at" DATETIME NOT NULL,
  "used_at" DATETIME DEFAULT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: company_invites
CREATE TABLE IF NOT EXISTS "company_invites" (
  "invite_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "invited_by" INTEGER NOT NULL,
  "email" TEXT NOT NULL,
  "role" TEXT DEFAULT 'Sales Rep' CHECK("role" IN ('Admin','Sales Manager','Sales Rep','Viewer')),
  "token" TEXT NOT NULL UNIQUE,
  "expires_at" DATETIME NOT NULL,
  "accepted_at" DATETIME DEFAULT NULL,
  "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY ("company_id") REFERENCES "companies"("company_id") ON DELETE CASCADE,
  FOREIGN KEY ("invited_by") REFERENCES "users"("user_id") ON DELETE CASCADE
);

-- Table: stripe_events
CREATE TABLE IF NOT EXISTS "stripe_events" (
  "event_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "stripe_event_id" TEXT NOT NULL UNIQUE,
  "event_type" TEXT NOT NULL,
  "event_data" TEXT DEFAULT NULL,
  "processed_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Default Data
-- ============================================

-- Default company
INSERT OR IGNORE INTO "companies" ("company_id", "company_name", "company_slug", "email", "status", "subscription_status", "plan_id", "plan_user_limit") 
VALUES (1, 'Demo Company', 'demo', 'admin@example.com', 'active', 'active', 'enterprise', 999);

-- Default admin user (password: admin123) - ALSO a super admin
INSERT OR IGNORE INTO "users" ("user_id", "company_id", "username", "email", "password_hash", "full_name", "role", "status", "email_verified_at", "is_super_admin", "created_at")
VALUES (1, 1, 'admin', 'admin@example.com', '$2y$10$Coojo0STMC9le0gJ0oidPuq7mj3nGipQC1Quib/ymJiHIGBJDk7Ce', 'System Admin', 'Admin', 'Active', datetime('now'), 1, datetime('now'));

-- Default settings
INSERT OR IGNORE INTO "settings" ("company_id", "setting_key", "setting_value", "setting_type") VALUES
(1, 'company_name', 'Your Company', 'text'),
(1, 'company_email', 'hello@example.com', 'email'),
(1, 'company_phone', '', 'text'),
(1, 'company_website', 'https://example.com', 'url'),
(1, 'company_logo', '', 'text'),
(1, 'company_favicon', '', 'text'),
(1, 'app_name', 'White Label CRM', 'text'),
(1, 'records_per_page', '25', 'number'),
(1, 'date_format', 'Y-m-d', 'text'),
(1, 'timezone', 'UTC', 'text'),
(1, 'email_from_name', 'Your Company', 'text');

-- ============================================
-- Indexes
-- ============================================
CREATE INDEX IF NOT EXISTS "idx_users_company" ON "users"("company_id");
CREATE INDEX IF NOT EXISTS "idx_leads_company" ON "leads"("company_id");
CREATE INDEX IF NOT EXISTS "idx_interactions_company" ON "interactions"("company_id");
CREATE INDEX IF NOT EXISTS "idx_settings_company" ON "settings"("company_id");
CREATE INDEX IF NOT EXISTS "idx_custom_fields_company" ON "custom_fields"("company_id");
CREATE INDEX IF NOT EXISTS "idx_activity_company" ON "activity_log"("company_id");
CREATE INDEX IF NOT EXISTS "idx_company_documents_company" ON "company_documents"("company_id");
CREATE INDEX IF NOT EXISTS "idx_company_invites_token" ON "company_invites"("token");
CREATE INDEX IF NOT EXISTS "idx_email_verifications_token" ON "email_verifications"("token");
CREATE INDEX IF NOT EXISTS "idx_password_resets_token" ON "password_resets"("token");
CREATE INDEX IF NOT EXISTS "idx_leads_status" ON "leads"("lead_status");
CREATE INDEX IF NOT EXISTS "idx_leads_type" ON "leads"("lead_type");
CREATE INDEX IF NOT EXISTS "idx_leads_assigned" ON "leads"("assigned_to");
CREATE INDEX IF NOT EXISTS "idx_interactions_lead" ON "interactions"("lead_id");
CREATE INDEX IF NOT EXISTS "idx_custom_values_lead" ON "lead_custom_values"("lead_id");
CREATE INDEX IF NOT EXISTS "idx_custom_values_field" ON "lead_custom_values"("field_id");
