-- MySQL SaaS Multi-Tenant Migration for FunL CRM

-- 1. Create companies table
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_slug` varchar(255) NOT NULL UNIQUE,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `date_format` varchar(50) DEFAULT 'Y-m-d',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `trial_ends_at` datetime DEFAULT NULL,
  `subscription_status` varchar(20) DEFAULT 'trial',
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `plan_id` varchar(50) DEFAULT NULL,
  `plan_name` varchar(100) DEFAULT NULL,
  `plan_user_limit` int(11) DEFAULT 1,
  `plan_price_monthly` decimal(10,2) DEFAULT 0.00,
  `extra_user_price` decimal(10,2) DEFAULT 0.00,
  `billing_cycle` varchar(20) DEFAULT 'monthly',
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create plans table
CREATE TABLE IF NOT EXISTS `plans` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_key` varchar(50) NOT NULL UNIQUE,
  `plan_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `user_limit` int(11) NOT NULL DEFAULT 1,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `yearly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `extra_user_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `features_json` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default plans
INSERT IGNORE INTO `plans` (`plan_key`, `plan_name`, `description`, `user_limit`, `monthly_price`, `yearly_price`, `extra_user_price`, `sort_order`) VALUES
('single', 'Single User', 'Perfect for solo entrepreneurs and freelancers', 1, 10.00, 100.00, 0, 1),
('team', 'Team', 'For growing teams with up to 5 users', 5, 40.00, 400.00, 8.00, 2),
('enterprise', 'Enterprise', 'For larger teams with up to 15 users', 15, 90.00, 900.00, 6.00, 3);

-- Helper procedure to safely add columns to MySQL tables
DROP PROCEDURE IF EXISTS AddColumnSafely;
DELIMITER //

CREATE PROCEDURE AddColumnSafely(
    IN tbl_name VARCHAR(100),
    IN col_name VARCHAR(100),
    IN col_def VARCHAR(255)
)
BEGIN
    SET @column_exists = (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = tbl_name
          AND column_name = col_name
    );

    IF @column_exists = 0 THEN
        SET @alter_sql = CONCAT('ALTER TABLE `', tbl_name, '` ADD COLUMN `', col_name, '` ', col_def);
        PREPARE stmt FROM @alter_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

-- Add company_id and other fields to existing tables safely
CALL AddColumnSafely('users', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('users', 'is_super_admin', 'tinyint(1) DEFAULT 0');
CALL AddColumnSafely('users', 'email_verified', 'tinyint(1) DEFAULT 1');
CALL AddColumnSafely('users', 'email_verified_at', 'datetime DEFAULT NULL');

CALL AddColumnSafely('leads', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('interactions', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('documents', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('activity_log', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('settings', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('custom_fields', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('webhook_endpoints', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('whatsapp_messages', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('voip_calls', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('email_campaigns', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('email_lists', 'company_id', 'int(11) DEFAULT NULL');
CALL AddColumnSafely('email_templates', 'company_id', 'int(11) DEFAULT NULL');

DROP PROCEDURE IF EXISTS AddColumnSafely;

-- 3. Create a default company and assign existing records to it
INSERT INTO `companies` (`company_id`, `company_name`, `company_slug`, `email`, `status`, `subscription_status`, `plan_id`, `plan_user_limit`) 
VALUES (1, 'FUNL Demo', 'funl', 'admin@funl.online', 'active', 'active', 'enterprise', 999)
ON DUPLICATE KEY UPDATE `company_name` = `company_name`;

UPDATE `users` SET `company_id` = 1, `is_super_admin` = 1, `email_verified` = 1 WHERE `company_id` IS NULL;
UPDATE `leads` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `settings` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `contacts` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `accounts` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `deals` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `products` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `tasks` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `webforms` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `webform_submissions` SET `company_id` = 1 WHERE `company_id` IS NULL;
