<?php
/**
 * White Label CRM - Migration: Quotes & Proposals
 */
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

$queries = [];

$queries[] = "
CREATE TABLE IF NOT EXISTS `quotes` (
  `quote_id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `quote_number`     VARCHAR(50)  NOT NULL,
  `deal_id`          INT(11)      DEFAULT NULL,
  `lead_id`          INT(11)      DEFAULT NULL,
  `contact_id`       INT(11)      DEFAULT NULL,
  `account_id`       INT(11)      DEFAULT NULL,
  `quote_title`      VARCHAR(255) NOT NULL,
  `status`           VARCHAR(20)  NOT NULL DEFAULT 'draft' COMMENT 'draft, sent, accepted, rejected, expired',
  `issue_date`       DATE         NOT NULL,
  `expiry_date`      DATE         DEFAULT NULL,
  `subtotal`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate`         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `tax_amount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total`            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `currency`         VARCHAR(3)   NOT NULL DEFAULT 'USD',
  `notes`            TEXT         DEFAULT NULL,
  `terms`            TEXT         DEFAULT NULL,
  `footer_text`      TEXT         DEFAULT NULL,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sent_at`          TIMESTAMP    NULL DEFAULT NULL,
  `accepted_at`      TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`quote_id`),
  UNIQUE KEY `uk_quote_number` (`company_id`, `quote_number`),
  KEY `idx_deal` (`deal_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS `quote_items` (
  `item_id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `quote_id`         INT(11)      NOT NULL,
  `item_description` TEXT         NOT NULL,
  `quantity`         DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `line_total`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `sort_order`       INT(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`),
  KEY `idx_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "<pre>\n";
echo "White Label CRM - Quotes Migration\n";
echo "===================================\n\n";

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
