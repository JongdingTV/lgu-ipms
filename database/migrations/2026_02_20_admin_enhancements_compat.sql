-- Compatibility migration for MySQL/MariaDB versions without
-- "ADD COLUMN IF NOT EXISTS" and "CREATE INDEX IF NOT EXISTS".

SET @db_name = DATABASE();

-- Projects columns
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='priority_percent'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN priority_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER priority'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='engineer_license_doc'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN engineer_license_doc VARCHAR(255) NULL AFTER status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='engineer_certification_doc'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN engineer_certification_doc VARCHAR(255) NULL AFTER engineer_license_doc'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND COLUMN_NAME='engineer_credentials_doc'),
    'SELECT 1',
    'ALTER TABLE projects ADD COLUMN engineer_credentials_doc VARCHAR(255) NULL AFTER engineer_certification_doc'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Feedback columns
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='district'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN district VARCHAR(100) NULL AFTER location'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='barangay'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN barangay VARCHAR(150) NULL AFTER district'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='alternative_name'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN alternative_name VARCHAR(150) NULL AFTER barangay'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='exact_address'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN exact_address VARCHAR(255) NULL AFTER alternative_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='photo_path'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN photo_path VARCHAR(255) NULL AFTER description'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='map_lat'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN map_lat DECIMAL(10,7) NULL AFTER photo_path'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='map_lng'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN map_lng DECIMAL(10,7) NULL AFTER map_lat'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND COLUMN_NAME='map_link'),
    'SELECT 1',
    'ALTER TABLE feedback ADD COLUMN map_link VARCHAR(500) NULL AFTER map_lng'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Feedback indexes
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND INDEX_NAME='idx_feedback_priority_loc'),
    'SELECT 1',
    'CREATE INDEX idx_feedback_priority_loc ON feedback (district, barangay, alternative_name, category)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND INDEX_NAME='idx_feedback_status_date'),
    'SELECT 1',
    'CREATE INDEX idx_feedback_status_date ON feedback (status, date_submitted)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Progress tables
CREATE TABLE IF NOT EXISTS project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    planned_start DATE NULL,
    planned_end DATE NULL,
    actual_start DATE NULL,
    actual_end DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_tasks_project (project_id),
    CONSTRAINT fk_project_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    planned_date DATE NULL,
    actual_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_milestones_project (project_id),
    CONSTRAINT fk_project_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Engineer assignment table for Registered Engineers module
CREATE TABLE IF NOT EXISTS contractor_project_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contractor_id INT NOT NULL,
    project_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (contractor_id, project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
