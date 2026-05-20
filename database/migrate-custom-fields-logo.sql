-- ============================================
-- White Label CRM Migration: Custom Fields + Logo
-- ============================================

-- 1. Add logo setting to settings table (if not exists)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_logo', '');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_favicon', '');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('app_name', 'White Label CRM');

-- 2. Create custom_fields table for dynamic lead fields
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `field_id` int(11) NOT NULL AUTO_INCREMENT,
  `field_name` varchar(50) NOT NULL,
  `field_label` varchar(100) NOT NULL,
  `field_type` enum('text','number','select','textarea','url','email','tel','date','checkbox') NOT NULL DEFAULT 'text',
  `field_options` text DEFAULT NULL COMMENT 'JSON array for select options',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`field_id`),
  UNIQUE KEY `field_name` (`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create lead_custom_values table to store values
CREATE TABLE IF NOT EXISTS `lead_custom_values` (
  `value_id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `field_value` text DEFAULT NULL,
  PRIMARY KEY (`value_id`),
  UNIQUE KEY `lead_field` (`lead_id`, `field_id`),
  KEY `lead_id` (`lead_id`),
  KEY `field_id` (`field_id`),
  CONSTRAINT `lcv_lead_fk` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`lead_id`) ON DELETE CASCADE,
  CONSTRAINT `lcv_field_fk` FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`field_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Remove horse-specific fields from leads table (they'll be custom fields now)
-- Note: These ALTERs are optional — you may keep them for existing data
-- ALTER TABLE leads DROP COLUMN number_of_horses;
-- ALTER TABLE leads DROP COLUMN horse_breed;
-- ALTER TABLE leads DROP COLUMN horse_sex;

-- 5. Add generic custom_field_data JSON column for leads (for backward compat)
ALTER TABLE leads ADD COLUMN IF NOT EXISTS `custom_field_data` JSON DEFAULT NULL;

-- 6. Insert default custom field types (optional examples)
-- Clients can add their own via Settings page
-- INSERT INTO custom_fields (field_name, field_label, field_type, sort_order) VALUES
-- ('industry', 'Industry', 'text', 1),
-- ('company_size', 'Company Size', 'select', 2);

-- ============================================
-- Migration Complete
-- ============================================
