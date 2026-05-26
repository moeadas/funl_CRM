<?php
/**
 * White Label CRM - Migration: Tasks & Kanban Pipeline
 */
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

$queries = [];

// ── 1. Tasks table ────────────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`      INT(11)      NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `description`     TEXT         DEFAULT NULL,
  `status`          VARCHAR(20)  NOT NULL DEFAULT 'todo',
  `priority`        VARCHAR(20)  NOT NULL DEFAULT 'medium',
  `assigned_to`     INT(11)      DEFAULT NULL,
  `lead_id`         INT(11)      DEFAULT NULL,
  `due_date`        DATE         DEFAULT NULL,
  `follow_up_date`  DATE         DEFAULT NULL,
  `reminder_at`     DATETIME     DEFAULT NULL,
  `created_by`      INT(11)      NOT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_lead` (`lead_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_follow_up` (`follow_up_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 2. Add task comments ──────────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `task_comments` (
  `comment_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `task_id`      INT(11)      NOT NULL,
  `user_id`      INT(11)      NOT NULL,
  `content`      TEXT         NOT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `idx_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "<pre>\n";
echo "White Label CRM - Tasks Migration\n";
echo "====================================\n\n";

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
