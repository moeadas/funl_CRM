<?php
/**
 * White Label CRM - Migration: Deal Pipeline
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/migration-guard.php'; // H-2: block unauthenticated web access

$db = Database::getInstance();

$queries = [];

// ── 1. Deals table ────────────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `deals` (
  `deal_id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `deal_name`        VARCHAR(255) NOT NULL,
  `deal_value`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `currency`         VARCHAR(3)   NOT NULL DEFAULT 'USD',
  `stage`            VARCHAR(30)  NOT NULL DEFAULT 'prospecting',
  `probability`      INT(11)      NOT NULL DEFAULT 0 COMMENT '0-100% close probability',
  `expected_close`   DATE         DEFAULT NULL,
  `actual_close`     DATE         DEFAULT NULL,
  `lead_id`          INT(11)      DEFAULT NULL COMMENT 'Linked lead',
  `contact_id`       INT(11)      DEFAULT NULL COMMENT 'Primary contact',
  `account_id`       INT(11)      DEFAULT NULL COMMENT 'Linked account',
  `source`           VARCHAR(50)  DEFAULT NULL,
  `type`             VARCHAR(50)  DEFAULT 'New Business' COMMENT 'New Business, Renewal, Upsell',
  `description`      TEXT         DEFAULT NULL,
  `notes`            TEXT         DEFAULT NULL,
  `assigned_to`      INT(11)      DEFAULT NULL,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`deal_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_stage` (`stage`),
  KEY `idx_expected_close` (`expected_close`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_account` (`account_id`),
  KEY `idx_contact` (`contact_id`),
  KEY `idx_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 2. Deal stages config (for customizing pipeline) ────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `deal_stages` (
  `stage_id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `stage_name`       VARCHAR(30)  NOT NULL,
  `stage_label`      VARCHAR(50)  NOT NULL,
  `probability`      INT(11)      NOT NULL DEFAULT 0,
  `position`         INT(11)      NOT NULL DEFAULT 0,
  `color`            VARCHAR(7)   DEFAULT '#6b7280',
  `is_won`           TINYINT(1)   NOT NULL DEFAULT 0,
  `is_lost`          TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`stage_id`),
  UNIQUE KEY `uk_stage_name` (`company_id`, `stage_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 3. Deal activity log ────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `deal_activities` (
  `activity_id`      INT(11)      NOT NULL AUTO_INCREMENT,
  `deal_id`          INT(11)      NOT NULL,
  `user_id`          INT(11)      NOT NULL,
  `activity_type`    VARCHAR(50)  NOT NULL COMMENT 'stage_change, note, call, email, meeting, task',
  `old_value`        VARCHAR(255) DEFAULT NULL,
  `new_value`        VARCHAR(255) DEFAULT NULL,
  `note`             TEXT         DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`activity_id`),
  KEY `idx_deal` (`deal_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 4. Insert default stages ──────────────────────────────────
$queries[] = "
INSERT IGNORE INTO `deal_stages` (`company_id`, `stage_name`, `stage_label`, `probability`, `position`, `color`, `is_won`, `is_lost`) VALUES
(0, 'prospecting', 'Prospecting', 10, 1, '#9ca3af', 0, 0),
(0, 'qualification', 'Qualification', 25, 2, '#60a5fa', 0, 0),
(0, 'proposal', 'Proposal', 50, 3, '#a78bfa', 0, 0),
(0, 'negotiation', 'Negotiation', 75, 4, '#fbbf24', 0, 0),
(0, 'closed_won', 'Closed Won', 100, 5, '#22c55e', 1, 0),
(0, 'closed_lost', 'Closed Lost', 0, 6, '#ef4444', 0, 1);
";

echo "<pre>\n";
echo "White Label CRM - Deal Pipeline Migration\n";
echo "==========================================\n\n";

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
