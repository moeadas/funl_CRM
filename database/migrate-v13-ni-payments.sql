-- Migration v13: Add payment_transactions table for NI Gateway integration
-- Stores pending/completed payment records for audit and reconciliation.

CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `company_id`    INT NOT NULL,
    `order_id`      VARCHAR(100) NOT NULL,
    `session_id`    VARCHAR(100) NOT NULL,
    `aes_key`       VARCHAR(255) DEFAULT NULL COMMENT 'AES key from NI session (ephemeral)',
    `amount`        DECIMAL(10,2) NOT NULL,
    `currency`      VARCHAR(3) NOT NULL DEFAULT 'USD',
    `status`        ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
    `gateway_response` TEXT DEFAULT NULL COMMENT 'Raw JSON response from NI gateway',
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_order` (`company_id`, `order_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Also add NI gateway settings to the settings table for platform level
-- (these are stored with company_id = NULL via save_platform_settings)
-- No separate migration needed; save_platform_settings handles inserts/updates.
