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
  `status`          TEXT         NOT NULL DEFAULT 'todo' CHECK(status IN ('todo','in_progress','review','done','cancelled')),
  `priority`        TEXT         NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high','urgent')),
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
  KEY `company_id` (`company_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `lead_id` (`lead_id`),
  KEY `status` (`status`),
  KEY `due_date` (`due_date`),
  KEY `follow_up_date` (`follow_up_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tasks and follow-ups';
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
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_comment_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`task_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
