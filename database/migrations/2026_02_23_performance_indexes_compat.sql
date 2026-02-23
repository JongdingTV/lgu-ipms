-- Performance index migration (compat version)
-- Safe for older MySQL/MariaDB versions without CREATE INDEX IF NOT EXISTS.

SET @db_name = DATABASE();

-- projects
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND INDEX_NAME='idx_projects_status_created'),
    'SELECT 1',
    'CREATE INDEX idx_projects_status_created ON projects (status, created_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND INDEX_NAME='idx_projects_name'),
    'SELECT 1',
    'CREATE INDEX idx_projects_name ON projects (name)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='projects' AND INDEX_NAME='idx_projects_sector_status'),
    'SELECT 1',
    'CREATE INDEX idx_projects_sector_status ON projects (sector, status)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- department head review queue
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='project_department_head_reviews' AND INDEX_NAME='idx_dept_review_status_decided'),
    'SELECT 1',
    'CREATE INDEX idx_dept_review_status_decided ON project_department_head_reviews (decision_status, decided_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- status requests
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='project_status_requests' AND INDEX_NAME='idx_status_requests_project_requested'),
    'SELECT 1',
    'CREATE INDEX idx_status_requests_project_requested ON project_status_requests (project_id, requested_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='project_status_requests' AND INDEX_NAME='idx_status_requests_admin_engineer'),
    'SELECT 1',
    'CREATE INDEX idx_status_requests_admin_engineer ON project_status_requests (admin_decision, engineer_decision)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- progress updates
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='project_progress_updates' AND INDEX_NAME='idx_progress_project_created'),
    'SELECT 1',
    'CREATE INDEX idx_progress_project_created ON project_progress_updates (project_id, created_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- budget/resources tables
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='milestones' AND INDEX_NAME='idx_milestones_name'),
    'SELECT 1',
    'CREATE INDEX idx_milestones_name ON milestones (name)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='expenses' AND INDEX_NAME='idx_expenses_milestone_date'),
    'SELECT 1',
    'CREATE INDEX idx_expenses_milestone_date ON expenses (milestoneId, date)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- feedback notifications
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='feedback' AND INDEX_NAME='idx_feedback_status_date_submitted'),
    'SELECT 1',
    'CREATE INDEX idx_feedback_status_date_submitted ON feedback (status, date_submitted)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

