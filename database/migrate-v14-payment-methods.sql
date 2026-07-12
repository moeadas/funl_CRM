-- Migration v14: Add payment_methods table (stored cards for NI/Stripe gateways)
-- Fixes fatal error in pages/billing.php which SELECTs from this table.
-- Columns match the read pattern in billing.php (brand, last4, exp_month, exp_year, is_default).

CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `company_id`    INT NOT NULL,
    `gateway`       VARCHAR(20) NOT NULL DEFAULT 'ni' COMMENT 'ni | stripe',
    `token`         VARCHAR(255) DEFAULT NULL COMMENT 'Gateway card/customer token (do NOT store PAN)',
    `brand`         VARCHAR(40) DEFAULT NULL COMMENT 'Visa, Mastercard, etc.',
    `last4`         VARCHAR(4) DEFAULT NULL,
    `exp_month`     VARCHAR(2) DEFAULT NULL,
    `exp_year`      VARCHAR(4) DEFAULT NULL,
    `is_default`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_pm_company` (`company_id`),
    KEY `idx_pm_default` (`company_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
