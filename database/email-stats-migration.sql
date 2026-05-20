-- ============================================
-- Email Campaign Statistics Migration
-- Adds delivery status tracking for Resend webhooks
-- ============================================

-- 1. Add new status enum values to email_campaign_log
-- MySQL doesn't support ALTER TYPE, so we need to recreate the enum
-- But for safety, we'll add a new delivery_status column instead

-- Add delivery tracking columns to email_campaign_log
ALTER TABLE `email_campaign_log`
    ADD COLUMN `resend_email_id` VARCHAR(100) DEFAULT NULL AFTER `tracking_token`,
    ADD COLUMN `delivery_status` VARCHAR(30) DEFAULT NULL AFTER `status`,
    ADD COLUMN `delivered_at` DATETIME DEFAULT NULL AFTER `opened_at`,
    ADD COLUMN `bounced_at` DATETIME DEFAULT NULL AFTER `delivered_at`,
    ADD COLUMN `bounced_reason` TEXT DEFAULT NULL AFTER `bounced_at`,
    ADD COLUMN `complained_at` DATETIME DEFAULT NULL AFTER `bounced_reason`,
    ADD COLUMN `complaint_type` VARCHAR(50) DEFAULT NULL AFTER `complained_at`,
    ADD COLUMN `junk_folder` TINYINT(1) DEFAULT NULL AFTER `complaint_type`,
    ADD COLUMN `spam_folder` TINYINT(1) DEFAULT NULL AFTER `junk_folder`,
    ADD KEY `resend_email_id` (`resend_email_id`),
    ADD KEY `delivery_status` (`delivery_status`);

-- 2. Add aggregate counters to email_campaigns
ALTER TABLE `email_campaigns`
    ADD COLUMN `total_bounced` INT NOT NULL DEFAULT 0 AFTER `total_failed`,
    ADD COLUMN `total_complained` INT NOT NULL DEFAULT 0 AFTER `total_bounced`,
    ADD COLUMN `total_delivered` INT NOT NULL DEFAULT 0 AFTER `total_complained`,
    ADD COLUMN `total_junk` INT NOT NULL DEFAULT 0 AFTER `total_delivered`,
    ADD COLUMN `total_spam` INT NOT NULL DEFAULT 0 AFTER `total_junk`,
    ADD COLUMN `total_rejected` INT NOT NULL DEFAULT 0 AFTER `total_spam`;

-- 3. Create webhook_events table for audit trail
CREATE TABLE IF NOT EXISTS `email_webhook_events` (
    `event_id` INT NOT NULL AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `log_id` INT DEFAULT NULL,
    `resend_email_id` VARCHAR(100) DEFAULT NULL,
    `event_type` VARCHAR(30) NOT NULL,
    `email_address` VARCHAR(100) DEFAULT NULL,
    `payload` TEXT DEFAULT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`event_id`),
    KEY `campaign_id` (`campaign_id`),
    KEY `resend_email_id` (`resend_email_id`),
    KEY `event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
