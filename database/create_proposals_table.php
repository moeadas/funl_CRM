<?php
/**
 * White Label CRM - Migration: Create Proposals Table
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/migration-guard.php'; // H-2: block unauthenticated web access

$db = Database::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS `proposals` (
  `proposal_id`      INT(11)        NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)        DEFAULT NULL,
  `estimate_number`  VARCHAR(50)    NOT NULL,
  `proposal_date`    DATE           DEFAULT NULL,
  `status`           VARCHAR(50)    NOT NULL DEFAULT 'Draft',
  `customer_company` VARCHAR(255)   NOT NULL,
  `contact_name`     VARCHAR(255)   DEFAULT NULL,
  `customer_address` TEXT           DEFAULT NULL,
  `line_items`       TEXT           DEFAULT NULL,
  `subtotal`         DECIMAL(15,2)  DEFAULT 0,
  `tax_rate`         DECIMAL(5,2)   DEFAULT 0,
  `tax_amount`       DECIMAL(15,2)  DEFAULT 0,
  `total`            DECIMAL(15,2)  DEFAULT 0,
  `notes`            TEXT           DEFAULT NULL,
  `accepted_by`      VARCHAR(255)   DEFAULT NULL,
  `accepted_date`    DATETIME       DEFAULT NULL,
  `created_by`       INT(11)        DEFAULT NULL,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`proposal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "Running migration: Create proposals table...\n";
try {
    $db->query($sql);
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
