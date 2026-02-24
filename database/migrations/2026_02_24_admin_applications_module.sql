-- Admin Applications module (Engineer + Contractor)
SET @db_name = DATABASE();

-- Add account_status to employees if missing
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='account_status'),
    'SELECT 1',
    'ALTER TABLE employees ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT ''active'' AFTER role'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS engineer_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    department VARCHAR(120) NOT NULL,
    position VARCHAR(120) NOT NULL,
    specialization VARCHAR(120) NOT NULL,
    assigned_area VARCHAR(180) NOT NULL,
    prc_license_no VARCHAR(100) NOT NULL,
    prc_expiry DATE NOT NULL,
    years_experience INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    admin_remarks TEXT NULL,
    rejection_reason TEXT NULL,
    account_password_hash VARCHAR(255) NULL,
    verified_by INT NULL,
    verified_at DATETIME NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_eng_app_status (status),
    INDEX idx_eng_app_email (email),
    INDEX idx_eng_app_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contractor_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    company_name VARCHAR(180) NOT NULL,
    contact_person VARCHAR(160) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    specialization VARCHAR(120) NOT NULL,
    years_in_business INT NOT NULL DEFAULT 0,
    assigned_area VARCHAR(180) NULL,
    license_no VARCHAR(120) NULL,
    license_expiry DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    admin_remarks TEXT NULL,
    rejection_reason TEXT NULL,
    blacklist_reason TEXT NULL,
    account_password_hash VARCHAR(255) NULL,
    verified_by INT NULL,
    verified_at DATETIME NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ctr_app_status (status),
    INDEX idx_ctr_app_email (email),
    INDEX idx_ctr_app_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_type VARCHAR(20) NOT NULL,
    application_id INT NOT NULL,
    doc_type VARCHAR(60) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_docs_ref (application_type, application_id),
    INDEX idx_app_docs_type (doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_type VARCHAR(20) NOT NULL,
    application_id INT NOT NULL,
    action VARCHAR(40) NOT NULL,
    performed_by_user_id INT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_logs_ref (application_type, application_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
