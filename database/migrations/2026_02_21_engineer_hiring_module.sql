-- Engineer/Contractor Hiring and Evaluation Module migration (compat-safe)
-- Date: 2026-02-21

SET @db_name = DATABASE();

-- ------------------------------------------------------------------
-- contractors table extensions
-- ------------------------------------------------------------------
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='full_name'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN full_name VARCHAR(150) NULL AFTER company'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='license_expiration_date'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN license_expiration_date DATE NULL AFTER license'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='certifications_text'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN certifications_text TEXT NULL AFTER specialization'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='past_project_count'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN past_project_count INT NOT NULL DEFAULT 0 AFTER experience'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='delayed_project_count'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN delayed_project_count INT NOT NULL DEFAULT 0 AFTER past_project_count'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='performance_rating'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN performance_rating DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER delayed_project_count'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='compliance_status'),
    'SELECT 1',
    "ALTER TABLE contractors ADD COLUMN compliance_status VARCHAR(30) NOT NULL DEFAULT 'Compliant' AFTER performance_rating"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='reliability_score'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN reliability_score DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER compliance_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='risk_score'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN risk_score DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER reliability_score'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='risk_level'),
    'SELECT 1',
    "ALTER TABLE contractors ADD COLUMN risk_level VARCHAR(20) NOT NULL DEFAULT 'Medium' AFTER risk_score"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND COLUMN_NAME='last_evaluated_at'),
    'SELECT 1',
    'ALTER TABLE contractors ADD COLUMN last_evaluated_at DATETIME NULL AFTER risk_level'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- engineer documents
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contractor_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT NULL,
    expires_on DATE NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT NULL,
    verified_at DATETIME NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contractor_documents_contractor (contractor_id),
    INDEX idx_contractor_documents_type (document_type),
    INDEX idx_contractor_documents_expires (expires_on),
    CONSTRAINT fk_contractor_documents_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- performance history
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contractor_project_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    project_id INT NULL,
    role_name VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Assigned',
    start_date DATE NULL,
    end_date DATE NULL,
    is_delayed TINYINT(1) NOT NULL DEFAULT 0,
    delay_days INT NOT NULL DEFAULT 0,
    completion_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    project_rating DECIMAL(5,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contractor_project_history_contractor (contractor_id),
    INDEX idx_contractor_project_history_project (project_id),
    INDEX idx_contractor_project_history_delay (is_delayed),
    CONSTRAINT fk_contractor_history_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
    CONSTRAINT fk_contractor_history_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contractor_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    violation_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'Minor',
    description TEXT NULL,
    occurred_on DATE NULL,
    resolved_on DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contractor_violations_contractor (contractor_id),
    INDEX idx_contractor_violations_status (status),
    CONSTRAINT fk_contractor_violations_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contractor_evaluation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    completion_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    delay_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    avg_rating DECIMAL(5,2) NOT NULL DEFAULT 0,
    violation_count INT NOT NULL DEFAULT 0,
    reliability_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    performance_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    risk_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    risk_level VARCHAR(20) NOT NULL DEFAULT 'Medium',
    recommendation VARCHAR(255) NULL,
    score_breakdown_json TEXT NULL,
    evaluated_by INT NULL,
    evaluated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contractor_eval_contractor (contractor_id),
    INDEX idx_contractor_eval_risk (risk_level),
    INDEX idx_contractor_eval_date (evaluated_at),
    CONSTRAINT fk_contractor_eval_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Useful index for duplicate prevention and search
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND INDEX_NAME='idx_contractors_full_name_license'),
    'SELECT 1',
    'CREATE INDEX idx_contractors_full_name_license ON contractors (full_name, license)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contractors' AND INDEX_NAME='idx_contractors_risk_level'),
    'SELECT 1',
    'CREATE INDEX idx_contractors_risk_level ON contractors (risk_level, last_evaluated_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
