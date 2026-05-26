<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$queries = [];

$queries[] = "
CREATE TABLE IF NOT EXISTS `products` (
  `product_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `product_name`     VARCHAR(255) NOT NULL,
  `sku`              VARCHAR(100) DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `category`         VARCHAR(100) DEFAULT NULL,
  `price`            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cost`             DECIMAL(15,2) DEFAULT NULL,
  `currency`         VARCHAR(3)   NOT NULL DEFAULT 'USD',
  `quantity_in_stock` INT(11)     DEFAULT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `ticket_id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `ticket_number`    VARCHAR(50)  NOT NULL,
  `subject`          VARCHAR(255) NOT NULL,
  `description`      TEXT         NOT NULL,
  `status`           VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open, in_progress, waiting, resolved, closed',
  `priority`         VARCHAR(20)  NOT NULL DEFAULT 'medium' COMMENT 'low, medium, high, urgent',
  `category`         VARCHAR(50)  DEFAULT NULL,
  `contact_id`       INT(11)      DEFAULT NULL,
  `account_id`       INT(11)      DEFAULT NULL,
  `assigned_to`      INT(11)      DEFAULT NULL,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at`      TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`ticket_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `reply_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `ticket_id`        INT(11)      NOT NULL,
  `user_id`          INT(11)      DEFAULT NULL,
  `contact_id`       INT(11)      DEFAULT NULL,
  `message`          TEXT         NOT NULL,
  `is_internal`      TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reply_id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "<pre>\n";
echo "Products & Support Migration\n";
echo "============================\n\n";

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
