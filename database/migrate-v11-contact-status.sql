-- ============================================================
-- Migration V11: Add contact_status column and ensure webforms tables
-- ============================================================

-- Add contact_status to contacts if not exists (MySQL 8.0+)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'contacts' 
    AND column_name = 'contact_status');

SET @sql := IF(@exist = 0, 
    'ALTER TABLE contacts ADD COLUMN contact_status VARCHAR(50) NOT NULL DEFAULT "Active"',
    'SELECT "contact_status column already exists"');
    
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Also ensure status column exists as fallback
SET @exist2 := (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'contacts' 
    AND column_name = 'status');

SET @sql2 := IF(@exist2 = 0, 
    'ALTER TABLE contacts ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "Active"',
    'SELECT "status column already exists"');
    
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ============================================================
-- Ensure webforms tables exist
-- ============================================================

CREATE TABLE IF NOT EXISTS webforms (
    form_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    form_name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_webforms_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webform_fields (
    field_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    crm_field VARCHAR(100),
    field_type VARCHAR(50) DEFAULT 'text',
    position INT DEFAULT 0,
    required TINYINT(1) DEFAULT 0,
    INDEX idx_webform_fields_form (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webform_submissions (
    submission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED,
    lead_id INT UNSIGNED,
    submitted_data TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_submissions_form (form_id),
    INDEX idx_submissions_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Ensure tasks table has proper columns
-- ============================================================

CREATE TABLE IF NOT EXISTS tasks (
    task_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    status ENUM('todo','in_progress','review','done','cancelled') DEFAULT 'todo',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    assigned_to INT UNSIGNED,
    lead_id INT UNSIGNED,
    contact_id INT UNSIGNED,
    deal_id INT UNSIGNED,
    due_date DATE,
    follow_up_date DATE,
    completed_at DATETIME,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tasks_company (company_id),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
