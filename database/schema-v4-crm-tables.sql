
-- ================================================================
-- CRM Schema V4 — All missing tables
-- Run AFTER schema.sql + saas-migration.sql
-- ================================================================

-- ACCOUNTS
CREATE TABLE IF NOT EXISTS `accounts` (
  `account_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `account_name` VARCHAR(200) NOT NULL,
  `account_type` VARCHAR(50) DEFAULT 'Prospect',
  `status` VARCHAR(20) DEFAULT 'Active',
  `industry` VARCHAR(100) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `annual_revenue` DECIMAL(15,2) DEFAULT NULL,
  `employees` INT(11) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  KEY `idx_accounts_company` (`company_id`),
  KEY `idx_accounts_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CONTACTS
CREATE TABLE IF NOT EXISTS `contacts` (
  `contact_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `account_id` INT(11) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL DEFAULT '',
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `mobile` VARCHAR(30) DEFAULT NULL,
  `title` VARCHAR(100) DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'Active',
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`contact_id`),
  KEY `idx_contacts_company` (`company_id`),
  KEY `idx_contacts_account` (`account_id`),
  KEY `idx_contacts_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CONTACT TAGS
CREATE TABLE IF NOT EXISTS `contact_tags` (
  `tag_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `tag_name` VARCHAR(100) NOT NULL,
  `tag_color` VARCHAR(20) DEFAULT '#6b7280',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `uq_tag_company` (`company_id`, `tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_tag_map` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `contact_id` INT(11) NOT NULL,
  `tag_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_tag` (`contact_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DEAL STAGES
CREATE TABLE IF NOT EXISTS `deal_stages` (
  `stage_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) NOT NULL DEFAULT 0,
  `stage_name` VARCHAR(50) NOT NULL,
  `stage_label` VARCHAR(100) NOT NULL,
  `probability` TINYINT NOT NULL DEFAULT 10,
  `color` VARCHAR(20) DEFAULT '#6b7280',
  `position` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`stage_id`),
  UNIQUE KEY `uq_stage` (`company_id`, `stage_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `deal_stages` (`company_id`,`stage_name`,`stage_label`,`probability`,`color`,`position`) VALUES
(0,'prospecting', 'Prospecting', 10,'#6b7280',1),
(0,'qualification','Qualification', 20,'#8b5cf6',2),
(0,'proposal', 'Proposal / Demo', 40,'#2563eb',3),
(0,'negotiation', 'Negotiation', 60,'#f59e0b',4),
(0,'contract', 'Contract Sent', 80,'#ea580c',5),
(0,'closed_won', 'Closed Won', 100,'#16a34a',6),
(0,'closed_lost', 'Closed Lost', 0,'#dc2626',7);

-- DEALS
CREATE TABLE IF NOT EXISTS `deals` (
  `deal_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `deal_name` VARCHAR(200) NOT NULL,
  `deal_value` DECIMAL(15,2) DEFAULT 0,
  `currency` VARCHAR(5) DEFAULT 'USD',
  `stage` VARCHAR(50) NOT NULL DEFAULT 'prospecting',
  `probability` TINYINT DEFAULT 10,
  `type` VARCHAR(50) DEFAULT 'New Business',
  `account_id` INT(11) DEFAULT NULL,
  `contact_id` INT(11) DEFAULT NULL,
  `lead_id` INT(11) DEFAULT NULL,
  `source` VARCHAR(50) DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `expected_close` DATE DEFAULT NULL,
  `actual_close` DATE DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`deal_id`),
  KEY `idx_deals_company` (`company_id`),
  KEY `idx_deals_stage` (`stage`),
  KEY `idx_deals_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DEAL ACTIVITIES
CREATE TABLE IF NOT EXISTS `deal_activities` (
  `activity_id` INT(11) NOT NULL AUTO_INCREMENT,
  `deal_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `type` VARCHAR(50) DEFAULT 'stage_change',
  `from_stage` VARCHAR(50) DEFAULT NULL,
  `to_stage` VARCHAR(50) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`activity_id`),
  KEY `idx_deal_activities_deal` (`deal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TASKS
CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'todo',
  `priority` VARCHAR(20) DEFAULT 'medium',
  `due_date` DATETIME DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `lead_id` INT(11) DEFAULT NULL,
  `deal_id` INT(11) DEFAULT NULL,
  `contact_id` INT(11) DEFAULT NULL,
  `account_id` INT(11) DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`),
  KEY `idx_tasks_company` (`company_id`),
  KEY `idx_tasks_assigned` (`assigned_to`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PRODUCTS
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cost` DECIMAL(15,2) DEFAULT NULL,
  `currency` VARCHAR(5) DEFAULT 'USD',
  `quantity_in_stock` INT(11) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_products_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- QUOTES
CREATE TABLE IF NOT EXISTS `quotes` (
  `quote_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `quote_number` VARCHAR(50) NOT NULL,
  `quote_title` VARCHAR(255) NOT NULL,
  `deal_id` INT(11) DEFAULT NULL,
  `lead_id` INT(11) DEFAULT NULL,
  `contact_id` INT(11) DEFAULT NULL,
  `account_id` INT(11) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `issue_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `currency` VARCHAR(5) DEFAULT 'USD',
  `subtotal` DECIMAL(15,2) DEFAULT 0,
  `tax_rate` DECIMAL(5,2) DEFAULT 0,
  `tax_amount` DECIMAL(15,2) DEFAULT 0,
  `total` DECIMAL(15,2) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `terms` TEXT DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `accepted_at` DATETIME DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`quote_id`),
  KEY `idx_quotes_company` (`company_id`),
  KEY `idx_quotes_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- QUOTE ITEMS
CREATE TABLE IF NOT EXISTS `quote_items` (
  `item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `quote_id` INT(11) NOT NULL,
  `product_id` INT(11) DEFAULT NULL,
  `description` VARCHAR(500) NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT 1,
  `unit_price` DECIMAL(15,2) DEFAULT 0,
  `discount_percent` DECIMAL(5,2) DEFAULT 0,
  `line_total` DECIMAL(15,2) DEFAULT 0,
  `sort_order` INT(11) DEFAULT 0,
  PRIMARY KEY (`item_id`),
  KEY `idx_quote_items_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WEB FORMS
CREATE TABLE IF NOT EXISTS `webforms` (
  `form_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `form_name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'active',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`form_id`),
  KEY `idx_webforms_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WEB FORM FIELDS
CREATE TABLE IF NOT EXISTS `webform_fields` (
  `field_id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_id` INT(11) NOT NULL,
  `field_label` VARCHAR(200) NOT NULL,
  `crm_field` VARCHAR(100) NOT NULL,
  `field_type` VARCHAR(30) DEFAULT 'text',
  `position` INT(11) DEFAULT 0,
  `required` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`field_id`),
  KEY `idx_webform_fields_form` (`form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WEB FORM SUBMISSIONS
CREATE TABLE IF NOT EXISTS `webform_submissions` (
  `submission_id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_id` INT(11) NOT NULL,
  `company_id` INT(11) DEFAULT NULL,
  `lead_id` INT(11) DEFAULT NULL,
  `submitted_data` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  KEY `idx_submissions_form` (`form_id`),
  KEY `idx_submissions_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SUPPORT TICKETS
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `ticket_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `ticket_number` VARCHAR(50) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'open',
  `priority` VARCHAR(20) DEFAULT 'medium',
  `category` VARCHAR(100) DEFAULT NULL,
  `contact_id` INT(11) DEFAULT NULL,
  `account_id` INT(11) DEFAULT NULL,
  `lead_id` INT(11) DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`),
  KEY `idx_tickets_company` (`company_id`),
  KEY `idx_tickets_status` (`status`),
  KEY `idx_tickets_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TICKET REPLIES
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `reply_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_internal` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reply_id`),
  KEY `idx_ticket_replies_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AUTOMATION RULES
CREATE TABLE IF NOT EXISTS `automation_rules` (
  `rule_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `rule_name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `trigger_type` VARCHAR(50) NOT NULL,
  `trigger_conditions` TEXT DEFAULT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `action_config` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `run_count` INT(11) DEFAULT 0,
  `last_run_at` DATETIME DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`),
  KEY `idx_automation_company` (`company_id`),
  KEY `idx_automation_trigger` (`trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AUTOMATION LOGS
CREATE TABLE IF NOT EXISTS `automation_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `rule_id` INT(11) DEFAULT NULL,
  `company_id` INT(11) DEFAULT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT(11) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'success',
  `details` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_automation_logs_rule` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- KNOWLEDGE BASE ARTICLES
CREATE TABLE IF NOT EXISTS `kb_articles` (
  `article_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT 'General',
  `status` VARCHAR(20) DEFAULT 'published',
  `is_public` TINYINT(1) DEFAULT 0,
  `view_count` INT(11) DEFAULT 0,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`),
  UNIQUE KEY `uq_kb_slug` (`company_id`, `slug`),
  KEY `idx_kb_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
