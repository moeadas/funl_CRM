-- ============================================
-- White Label CRM - SaaS Multi-Tenant Migration
-- ============================================

-- 1. Create companies table (tenant isolation)
CREATE TABLE IF NOT EXISTS `companies` (
  `company_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `company_name` TEXT NOT NULL,
  `company_slug` TEXT NOT NULL UNIQUE,
  `email` TEXT NOT NULL,
  `phone` TEXT DEFAULT NULL,
  `website` TEXT DEFAULT NULL,
  `logo` TEXT DEFAULT NULL,
  `favicon` TEXT DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `timezone` TEXT DEFAULT 'UTC',
  `date_format` TEXT DEFAULT 'Y-m-d',
  `status` TEXT NOT NULL DEFAULT 'active' CHECK(`status` IN ('active','suspended','cancelled','trial')),
  `trial_ends_at` DATETIME DEFAULT NULL,
  `subscription_status` TEXT DEFAULT 'trial' CHECK(`subscription_status` IN ('trial','active','past_due','cancelled','suspended')),
  `stripe_customer_id` TEXT DEFAULT NULL,
  `stripe_subscription_id` TEXT DEFAULT NULL,
  `plan_id` TEXT DEFAULT NULL CHECK(`plan_id` IN ('single','team','enterprise')),
  `plan_name` TEXT DEFAULT NULL,
  `plan_user_limit` INTEGER DEFAULT 1,
  `plan_price_monthly` DECIMAL(10,2) DEFAULT 0,
  `extra_user_price` DECIMAL(10,2) DEFAULT 0,
  `billing_cycle` TEXT DEFAULT 'monthly' CHECK(`billing_cycle` IN ('monthly','yearly')),
  `current_period_start` DATETIME DEFAULT NULL,
  `current_period_end` DATETIME DEFAULT NULL,
  `cancel_at_period_end` INTEGER DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create plans table (subscription packages)
CREATE TABLE IF NOT EXISTS `plans` (
  `plan_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `plan_key` TEXT NOT NULL UNIQUE CHECK(`plan_key` IN ('single','team','enterprise')),
  `plan_name` TEXT NOT NULL,
  `description` TEXT DEFAULT NULL,
  `user_limit` INTEGER NOT NULL DEFAULT 1,
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `yearly_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `extra_user_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `features_json` TEXT DEFAULT NULL,
  `is_active` INTEGER DEFAULT 1,
  `sort_order` INTEGER DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert default plans
INSERT OR IGNORE INTO `plans` (`plan_key`, `plan_name`, `description`, `user_limit`, `monthly_price`, `yearly_price`, `extra_user_price`, `sort_order`) VALUES
('single', 'Single User', 'Perfect for solo entrepreneurs and freelancers', 1, 10.00, 100.00, 0, 1),
('team', 'Team', 'For growing teams with up to 5 users', 5, 40.00, 400.00, 8.00, 2),
('enterprise', 'Enterprise', 'For larger teams with up to 15 users', 15, 90.00, 900.00, 6.00, 3);

-- 3. Create email_verifications table
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `verification_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `email` TEXT NOT NULL,
  `token` TEXT NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);

-- 4. Create password_resets table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `email` TEXT NOT NULL,
  `token` TEXT NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 5. Create company_documents table (Document Library)
CREATE TABLE IF NOT EXISTS `company_documents` (
  `document_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `company_id` INTEGER NOT NULL,
  `uploaded_by` INTEGER NOT NULL,
  `title` TEXT NOT NULL,
  `description` TEXT DEFAULT NULL,
  `file_name` TEXT NOT NULL,
  `file_path` TEXT NOT NULL,
  `file_size` INTEGER NOT NULL,
  `file_type` TEXT DEFAULT NULL,
  `category` TEXT DEFAULT 'general' CHECK(`category` IN ('general','sales','marketing','legal','training','other')),
  `is_public` INTEGER DEFAULT 1,
  `download_count` INTEGER DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);

-- 6. Create stripe_events table (for webhook idempotency)
CREATE TABLE IF NOT EXISTS `stripe_events` (
  `event_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `stripe_event_id` TEXT NOT NULL UNIQUE,
  `event_type` TEXT NOT NULL,
  `event_data` TEXT DEFAULT NULL,
  `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 7. Create company_invites table (for inviting team members)
CREATE TABLE IF NOT EXISTS `company_invites` (
  `invite_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `company_id` INTEGER NOT NULL,
  `invited_by` INTEGER NOT NULL,
  `email` TEXT NOT NULL,
  `role` TEXT DEFAULT 'Sales Rep' CHECK(`role` IN ('Admin','Sales Manager','Sales Rep','Viewer')),
  `token` TEXT NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `accepted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`invited_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);

-- 8. Add company_id to existing tables (for tenant isolation)
-- Users table: add company_id
ALTER TABLE `users` ADD COLUMN `company_id` INTEGER DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME DEFAULT NULL;

-- Leads table: add company_id
ALTER TABLE `leads` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Interactions table: add company_id
ALTER TABLE `interactions` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Documents table (existing lead documents): add company_id
ALTER TABLE `documents` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Activity log: add company_id
ALTER TABLE `activity_log` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Settings: add company_id (tenant-scoped settings)
ALTER TABLE `settings` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Custom fields: add company_id
ALTER TABLE `custom_fields` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Webhook endpoints: add company_id
ALTER TABLE `webhook_endpoints` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- WhatsApp messages: add company_id
ALTER TABLE `whatsapp_messages` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- VoIP calls: add company_id
ALTER TABLE `voip_calls` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- Email campaigns/lists/templates: add company_id
ALTER TABLE `email_campaigns` ADD COLUMN `company_id` INTEGER DEFAULT NULL;
ALTER TABLE `email_lists` ADD COLUMN `company_id` INTEGER DEFAULT NULL;
ALTER TABLE `email_templates` ADD COLUMN `company_id` INTEGER DEFAULT NULL;

-- 9. Create indexes for tenant queries
CREATE INDEX IF NOT EXISTS `idx_users_company` ON `users`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_leads_company` ON `leads`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_interactions_company` ON `interactions`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_settings_company` ON `settings`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_custom_fields_company` ON `custom_fields`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_activity_company` ON `activity_log`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_company_documents_company` ON `company_documents`(`company_id`);
CREATE INDEX IF NOT EXISTS `idx_company_invites_token` ON `company_invites`(`token`);
CREATE INDEX IF NOT EXISTS `idx_email_verifications_token` ON `email_verifications`(`token`);
CREATE INDEX IF NOT EXISTS `idx_password_resets_token` ON `password_resets`(`token`);

-- 10. Create a default company for existing data (migration safety)
-- This ensures existing single-tenant data still works
INSERT INTO `companies` (`company_name`, `company_slug`, `email`, `status`, `subscription_status`, `plan_id`, `plan_user_limit`) 
SELECT 'Default Company', 'default', 'admin@example.com', 'active', 'active', 'enterprise', 999
WHERE NOT EXISTS (SELECT 1 FROM `companies` WHERE `company_slug` = 'default');

-- Update existing users to belong to default company
UPDATE `users` SET `company_id` = (SELECT `company_id` FROM `companies` WHERE `company_slug` = 'default') WHERE `company_id` IS NULL;

-- Update existing leads to belong to default company
UPDATE `leads` SET `company_id` = (SELECT `company_id` FROM `companies` WHERE `company_slug` = 'default') WHERE `company_id` IS NULL;

-- Update existing settings to belong to default company
UPDATE `settings` SET `company_id` = (SELECT `company_id` FROM `companies` WHERE `company_slug` = 'default') WHERE `company_id` IS NULL;

-- ============================================
-- Migration Complete
-- ============================================
