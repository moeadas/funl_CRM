<?php
/**
 * White Label CRM - Migration: Contacts & Accounts
 */
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

$queries = [];

// ── 1. Accounts table (companies/organizations) ──────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `accounts` (
  `account_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `account_name`     VARCHAR(255) NOT NULL,
  `account_type`     VARCHAR(20)  NOT NULL DEFAULT 'Customer' COMMENT 'Prospect, Customer, Partner, Vendor, Competitor, Other',
  `industry`         VARCHAR(100) DEFAULT NULL,
  `website`          VARCHAR(255) DEFAULT NULL,
  `phone`            VARCHAR(50)  DEFAULT NULL,
  `address`          TEXT         DEFAULT NULL,
  `city`             VARCHAR(100) DEFAULT NULL,
  `state_province`   VARCHAR(100) DEFAULT NULL,
  `country`          VARCHAR(100) DEFAULT NULL,
  `postal_code`      VARCHAR(20)  DEFAULT NULL,
  `billing_address`  TEXT         DEFAULT NULL,
  `tax_id`           VARCHAR(50)  DEFAULT NULL,
  `annual_revenue`   DECIMAL(15,2) DEFAULT NULL,
  `employee_count`   INT(11)      DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `facebook_url`     VARCHAR(255) DEFAULT NULL,
  `instagram_url`    VARCHAR(255) DEFAULT NULL,
  `linkedin_url`     VARCHAR(255) DEFAULT NULL,
  `twitter_url`      VARCHAR(255) DEFAULT NULL,
  `lead_source`      VARCHAR(50)  DEFAULT NULL,
  `lead_id`          INT(11)      DEFAULT NULL COMMENT 'Linked lead if converted',
  `assigned_to`      INT(11)      DEFAULT NULL,
  `status`           VARCHAR(20)  NOT NULL DEFAULT 'Active' COMMENT 'Active, Inactive, Prospect',
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_type` (`account_type`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_name` (`account_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 2. Contacts table (people) ───────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `contacts` (
  `contact_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `account_id`       INT(11)      DEFAULT NULL COMMENT 'Belongs to account/company',
  `first_name`       VARCHAR(100) NOT NULL,
  `last_name`        VARCHAR(100) NOT NULL,
  `title`            VARCHAR(100) DEFAULT NULL,
  `email`            VARCHAR(255) DEFAULT NULL,
  `phone`            VARCHAR(50)  DEFAULT NULL,
  `mobile`           VARCHAR(50)  DEFAULT NULL,
  `address`          TEXT         DEFAULT NULL,
  `city`             VARCHAR(100) DEFAULT NULL,
  `state_province`   VARCHAR(100) DEFAULT NULL,
  `country`          VARCHAR(100) DEFAULT NULL,
  `postal_code`      VARCHAR(20)  DEFAULT NULL,
  `birthday`         DATE         DEFAULT NULL,
  `website`          VARCHAR(255) DEFAULT NULL,
  `facebook_url`     VARCHAR(255) DEFAULT NULL,
  `instagram_url`    VARCHAR(255) DEFAULT NULL,
  `linkedin_url`     VARCHAR(255) DEFAULT NULL,
  `twitter_url`      VARCHAR(255) DEFAULT NULL,
  `notes`            TEXT         DEFAULT NULL,
  `contact_status`   VARCHAR(20)  NOT NULL DEFAULT 'Active' COMMENT 'Active, Inactive, Do Not Contact',
  `lead_source`      VARCHAR(50)  DEFAULT NULL,
  `lead_id`          INT(11)      DEFAULT NULL COMMENT 'Linked lead if converted',
  `assigned_to`      INT(11)      DEFAULT NULL,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`contact_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_account` (`account_id`),
  KEY `idx_name` (`last_name`, `first_name`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`contact_status`),
  KEY `idx_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 3. Contact tags / categories ─────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `contact_tags` (
  `tag_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`   INT(11)      NOT NULL,
  `tag_name`     VARCHAR(50)  NOT NULL,
  `tag_color`    VARCHAR(7)   DEFAULT '#6b7280',
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `uk_tag_name` (`company_id`, `tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 4. Contact-tag junction ────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `contact_tag_map` (
  `contact_id` INT(11) NOT NULL,
  `tag_id`     INT(11) NOT NULL,
  PRIMARY KEY (`contact_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "<pre>\n";
echo "White Label CRM - Contacts & Accounts Migration\n";
echo "================================================\n\n";

$success = true;
foreach ($queries as $i => $sql) {
    try {
        $db->query($sql);
        echo "Query " . ($i + 1) . ": OK\n";
    } catch (Exception $e) {
        echo "Query " . ($i + 1) . ": FAILED - " . $e->getMessage() . "\n";
        $success = false;
    }
}

if ($success) {
    echo "\nMigration completed successfully!\n";
} else {
    echo "\nMigration completed with errors.\n";
}
echo "</pre>\n";
?>
