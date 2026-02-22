-- Ensure project workflow history table exists for status transition audit.
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS project_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    changed_by INT NULL,
    notes TEXT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_status (project_id, status),
    INDEX idx_project_status_time (project_id, changed_at),
    CONSTRAINT fk_project_status_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

