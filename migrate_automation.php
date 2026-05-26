<?php
/**
 * White Label CRM - Migration: Automation / Workflows
 * Simple rule-based automation
 */
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

$queries = [];

// ── 1. Automation rules table ─────────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `automation_rules` (
  `rule_id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11)      NOT NULL,
  `rule_name`        VARCHAR(255) NOT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `trigger_type`     VARCHAR(50)  NOT NULL COMMENT 'lead_created, lead_status_changed, deal_stage_changed, task_overdue, email_opened',
  `trigger_conditions` TEXT       DEFAULT NULL COMMENT 'JSON conditions',
  `action_type`      VARCHAR(50)  NOT NULL COMMENT 'assign_user, send_email, create_task, move_deal, send_webhook, notify_user',
  `action_config`    TEXT         NOT NULL COMMENT 'JSON action parameters',
  `run_count`        INT(11)      NOT NULL DEFAULT 0,
  `last_run_at`      TIMESTAMP    NULL DEFAULT NULL,
  `created_by`       INT(11)      NOT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_trigger` (`trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 2. Automation execution log ─────────────────────────────
$queries[] = "
CREATE TABLE IF NOT EXISTS `automation_logs` (
  `log_id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `rule_id`          INT(11)      NOT NULL,
  `company_id`       INT(11)      NOT NULL,
  `trigger_entity`   VARCHAR(50)  NOT NULL COMMENT 'lead, deal, contact, task',
  `entity_id`        INT(11)      NOT NULL,
  `action_taken`     VARCHAR(255) NOT NULL,
  `status`           VARCHAR(20)  NOT NULL DEFAULT 'success' COMMENT 'success, failed, skipped',
  `error_message`    TEXT         DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_rule` (`rule_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 3. Insert sample automation rules ─────────────────────────
$queries[] = "
INSERT IGNORE INTO `automation_rules` 
(`company_id`, `rule_name`, `trigger_type`, `trigger_conditions`, `action_type`, `action_config`, `created_by`) 
VALUES
(0, 'Auto-assign leads by country', 'lead_created', '{\"conditions\":[{\"field\":\"country\",\"operator\":\"equals\",\"value\":\"\"}]}', 'assign_user', '{\"user_id\":0,\"message\":\"Auto-assigned based on country\"}', 1),
(0, 'Create follow-up task for new leads', 'lead_created', NULL, 'create_task', '{\"title\":\"Follow up with new lead\",\"due_days\":2,\"priority\":\"high\"}', 1),
(0, 'Notify when deal moves to negotiation', 'deal_stage_changed', '{\"conditions\":[{\"field\":\"stage\",\"operator\":\"equals\",\"value\":\"negotiation\"}]}', 'notify_user', '{\"message\":\"Deal moved to negotiation - requires attention!\"}', 1);
";

echo "<pre>\n";
echo "White Label CRM - Automation Migration\n";
echo "=======================================\n\n";

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
