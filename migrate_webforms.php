<?php
/**
 * White Label CRM - Migration: Public Web Forms
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/migration-guard.php'; // H-2: block unauthenticated web access

$db = Database::getInstance();

$queries = [];

$queries[] = "
CREATE TABLE IF NOT EXISTS `web_forms` (
  `form_id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `form_name`        VARCHAR(255) NOT NULL,
  `form_slug`        VARCHAR(100) NOT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `title`            VARCHAR(255) DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `success_message`  VARCHAR(255) DEFAULT 'Thank you! We will contact you soon.',
  `redirect_url`     VARCHAR(255) DEFAULT NULL,
  `fields_config`    TEXT         NOT NULL COMMENT 'JSON field definitions',
  `styling`          TEXT         DEFAULT NULL COMMENT 'JSON colors, fonts',
  `thank_you_page`   TINYINT(1)   NOT NULL DEFAULT 1,
  `notify_emails`    TEXT         DEFAULT NULL COMMENT 'Comma-separated emails',
  `auto_assign_to`   INT(11)      DEFAULT NULL,
  `lead_source`      VARCHAR(50)  DEFAULT 'Web Form',
  `submit_count`     INT(11)      NOT NULL DEFAULT 0,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`form_id`),
  UNIQUE KEY `uk_slug` (`company_id`, `form_slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$queries[] = "
CREATE TABLE IF NOT EXISTS `web_form_submissions` (
  `submission_id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `form_id`          INT(11)      NOT NULL,
  `company_id`       INT(11)      NOT NULL,
  `lead_id`          INT(11)      DEFAULT NULL,
  `data`             TEXT         NOT NULL COMMENT 'JSON submitted data',
  `ip_address`       VARCHAR(45)  DEFAULT NULL,
  `user_agent`       TEXT         DEFAULT NULL,
  `referrer`         VARCHAR(255) DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  KEY `idx_form` (`form_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "<pre>\n";
echo "White Label CRM - Web Forms Migration\n";
echo "======================================\n\n";

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
