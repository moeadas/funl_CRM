-- ============================================
-- No default user is created automatically.
-- Run setup.php after installation to create the first admin.
-- ============================================

-- ============================================
-- Insert Default Settings
-- ============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('company_name', 'Victory Genomics', 'text'),
('company_email', 'info@victorygenomics.com', 'email'),
('company_phone', '', 'text'),
('company_website', 'https://victorygenomics.com', 'url'),
('records_per_page', '25', 'number'),
('date_format', 'Y-m-d', 'text'),
('timezone', 'UTC', 'text');

COMMIT;

-- ============================================
-- End of Database Schema
-- ============================================
