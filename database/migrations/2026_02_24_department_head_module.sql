-- Department Head governance module expansion
-- Safe for older MySQL/MariaDB (no IF NOT EXISTS for columns/indexes).

SET @db_name = DATABASE();

-- projects: governance fields
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='priority_level'),
    'SELECT 1',
    "ALTER TABLE projects ADD COLUMN priority_level VARCHAR(20) NOT NULL DEFAULT 'Medium' AFTER priority"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='approved_by'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN approved_by INT NULL AFTER priority_level'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='approved_date'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN approved_date DATETIME NULL AFTER approved_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='rejection_reason'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN rejection_reason TEXT NULL AFTER approved_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- decision logs for department head audit
CREATE TABLE IF NOT EXISTS decision_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    decision_type VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    decided_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_decision_logs_project_time (project_id, created_at),
    INDEX idx_decision_logs_type_time (decision_type, created_at),
    INDEX idx_decision_logs_decider_time (decided_by, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- helpful indexes for monitoring/risk pages
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND INDEX_NAME='idx_projects_dept_status_dates'),
    'SELECT 1',
    'CREATE INDEX idx_projects_dept_status_dates ON projects (status, start_date, end_date)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND INDEX_NAME='idx_projects_priority_level'),
    'SELECT 1',
    'CREATE INDEX idx_projects_priority_level ON projects (priority_level, priority)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

